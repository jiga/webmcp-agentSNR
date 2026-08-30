/*
 * Shared public-surface interactions and readiness reporting.
 */
( function () {
	"use strict";

	const config = window.wmcpConfig || {};
	const root = document.querySelector( ".wmcp-field" );
	let healthSnapshot = null;

	if ( ! root ) {
		return;
	}

	const statusLabels = {
		"api-detected": "WebMCP detected",
		detecting: "Checking WebMCP",
		"embedded-context": "Embedded context",
		error: "Runtime unavailable",
		"loading-manifest": "Loading tool manifest",
		ready: "WebMCP ready",
		"ready-stale": "Using prior manifest",
		refreshing: "Refreshing tools",
		registering: "Registering tools",
		stopped: "Runtime stopped",
		"unsupported-browser": "WebMCP not detected",
		"unsupported-environment": "Browser unavailable",
	};

	function one( selector, scope = root ) {
		return scope.querySelector( selector );
	}

	function all( selector, scope = root ) {
		return Array.from( scope.querySelectorAll( selector ) );
	}

	function text( target, value ) {
		if ( target ) {
			target.textContent = value === null || value === undefined ? "—" : String( value );
		}
	}

	function announce( message ) {
		text( one( "[data-wmcp-announcer]" ), message );
	}

	function showError( message ) {
		const error = one( "[data-wmcp-error]" );
		if ( ! error ) {
			return;
		}

		error.hidden = ! message;
		text( error, message || "" );
	}

	function statusState( status ) {
		if ( status === "ready" ) {
			return "ready";
		}
		if ( [ "error", "unsupported-browser", "unsupported-environment", "embedded-context" ].includes( status ) ) {
			return "unsupported";
		}
		return "checking";
	}

	function updateStatus( detail = {} ) {
		const status = detail.status || document.documentElement.dataset.wmcpStatus || "detecting";
		all( "[data-wmcp-status]" ).forEach( ( target ) => text( target, statusLabels[ status ] || status ) );
		all( "[data-wmcp-status-chip]" ).forEach( ( chip ) => {
			chip.dataset.state = statusState( status );
		} );

		const registration = one( '[data-check="registration"]' );
		if ( registration ) {
			setCheck(
				registration,
				status === "ready" ? "passed" : statusState( status ) === "unsupported" ? "unavailable" : "checking",
				status === "ready" ? "Passed" : statusLabels[ status ] || "Checking"
			);
		}
	}

	function setCheck( item, state, label, detail = "" ) {
		if ( ! item ) {
			return;
		}
		item.dataset.state = state;
		text( one( "[data-check-status]", item ), label );
		if ( detail ) {
			text( one( "[data-check-detail]", item ), detail );
		}
	}

	function browserFacts() {
		let topLevel = false;
		try {
			topLevel = window.top === window.self;
		} catch {
			// Cross-origin frame access means this cannot be a top-level document.
		}

		return {
			secure_context: Boolean( window.isSecureContext ),
			top_level: topLevel,
			webmcp_api: typeof document.modelContext?.registerTool === "function",
		};
	}

	function updateBrowserChecks() {
		const facts = browserFacts();
		const labels = {
			secure_context: facts.secure_context ? "Passed" : "Requires HTTPS",
			top_level: facts.top_level ? "Passed" : "Embedded",
			webmcp_api: facts.webmcp_api ? "Detected" : "Not detected",
		};

		Object.keys( facts ).forEach( ( key ) => {
			all( `[data-check="${ key }"]` ).forEach( ( item ) => {
				setCheck( item, facts[ key ] ? "passed" : "unavailable", labels[ key ] );
			} );
		} );

		return facts;
	}

	async function fetchJson( url, options = {} ) {
		const response = await window.fetch( url, Object.assign( {
			cache: "no-store",
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		}, options ) );
		let payload;
		try {
			payload = await response.json();
		} catch {
			throw new Error( "The server returned an unreadable readiness response." );
		}
		if ( ! response.ok || payload?.ok === false ) {
			throw new Error( payload?.error?.message || "The readiness request failed." );
		}

		return { payload, response };
	}

	async function ensureSession( surface = config.surface || "storefront" ) {
		if ( ! config.sessionUrl ) {
			return;
		}

		await fetchJson( config.sessionUrl, {
			body: JSON.stringify( { surface } ),
			headers: { Accept: "application/json", "Content-Type": "application/json" },
			method: "POST",
		} );
	}

	function publicCheckStatus( status ) {
		if ( [ "passed", "enabled", "server_ready" ].includes( status ) ) {
			return { label: status === "enabled" ? "Enabled" : "Passed", state: "passed" };
		}
		if ( status === "not_demo" ) {
			return { label: "Production", state: "passed" };
		}
		if ( status === "disabled_or_unavailable" ) {
			return { label: "Disabled", state: "neutral" };
		}
		if ( status === "disabled" ) {
			return { label: "Disabled", state: "unavailable" };
		}
		return { label: String( status || "Unavailable" ).replaceAll( "_", " " ), state: "unavailable" };
	}

	function renderServerChecks( diagnostics ) {
		const checks = diagnostics?.checks || {};
		Object.entries( checks ).forEach( ( [ key, value ] ) => {
			const result = publicCheckStatus( value?.status );
			all( `[data-check="${ key }"]` ).forEach( ( item ) => {
				const version = value?.version ? `Version ${ value.version }` : "";
				setCheck( item, result.state, result.label, version );
			} );
		} );

		const database = publicCheckStatus( checks.database?.status );
		const commerce = publicCheckStatus( checks.woocommerce?.status );
		all( '[data-check="attribution"]' ).forEach( ( item ) => {
			const ready = database.state === "passed" && commerce.state === "passed";
			setCheck( item, ready ? "passed" : "unavailable", ready ? "Ready" : "Unavailable" );
		} );
		if ( Number.isInteger( checks.manifest?.storefront_tools ) ) {
			all( "[data-wmcp-tool-count]" ).forEach( ( target ) => {
				text( target, checks.manifest.storefront_tools );
			} );
		}
	}

	function renderManifest( manifest ) {
		if ( ! manifest || ! Array.isArray( manifest.tools ) ) {
			return;
		}

		all( "[data-wmcp-tool-count]" ).forEach( ( target ) => text( target, manifest.tools.length ) );
		all( "[data-wmcp-workflow]" ).forEach( ( target ) => {
			text( target, manifest.workflow_id ? `${ manifest.workflow_id.slice( 0, 8 ) }…` : "—" );
			target.title = manifest.workflow_id || "";
		} );

		all( '[data-check="manifest"]' ).forEach( ( item ) => {
			setCheck( item, manifest.tools.length > 0 ? "passed" : "unavailable", `${ manifest.tools.length } tools` );
		} );

		const overrides = manifest.governance?.session_overrides || {};
		all( "[data-policy-tool]" ).forEach( ( row ) => {
			const override = overrides[ row.dataset.policyTool ];
			if ( override?.enabled !== false ) {
				return;
			}
			row.classList.add( "wmcp-policy-disabled" );
			const reason = typeof override.reason === "string" && override.reason
				? ` · ${ override.reason }`
				: "";
			text( one( "[data-policy-state]", row ), `Disabled for this demo session${ reason }` );
		} );

	}

	async function loadHealth() {
		if ( ! config.healthUrl ) {
			return;
		}

		const facts = updateBrowserChecks();
		try {
			const { payload, response } = await fetchJson( config.healthUrl );
			const diagnostics = payload.diagnostics || {};
			renderServerChecks( diagnostics );

			const originHeader = response.headers.get( "Origin-Agent-Cluster" );
			const originItems = all( '[data-check="origin_agent_cluster"]' );
			originItems.forEach( ( item ) => {
				setCheck( item, originHeader === "?1" ? "passed" : "neutral", originHeader === "?1" ? "Observed" : "Not observed" );
			} );

			let manifest = null;
			if ( config.manifestUrl && config.autoStart !== false ) {
				try {
					await ensureSession();
					manifest = ( await fetchJson( config.manifestUrl ) ).payload;
					renderManifest( manifest );
				} catch {
					all( '[data-check="manifest"]' ).forEach( ( item ) => setCheck( item, "unavailable", "Unavailable" ) );
				}
			}

			const publicChecks = {};
			Object.entries( diagnostics.checks || {} ).forEach( ( [ key, value ] ) => {
				publicChecks[ key ] = { status: value?.status || "unavailable" };
				if ( value?.version ) {
					publicChecks[ key ].version = value.version;
				}
			} );
			healthSnapshot = {
				browser: Object.assign( {}, facts, { origin_agent_cluster: originHeader === "?1" } ),
				diagnostics: { checks: publicChecks },
				manifest: manifest ? {
					tool_count: manifest.tools.length,
				} : null,
				generated_at: new Date().toISOString(),
			};
			updateHealthScore();
			announce( facts.webmcp_api ? "Readiness checks complete. WebMCP is available in this browser." : "Server checks passed. WebMCP is not detected in this browser; the human site remains available." );
		} catch ( error ) {
			showError( error.message || "The public health endpoint could not be reached." );
			announce( "Readiness checks could not be completed." );
		}
	}

	function updateHealthScore() {
		const items = all( ".wmcp-health [data-check]" );
		if ( items.length === 0 ) {
			return;
		}
		const passed = items.filter( ( item ) => [ "passed", "neutral" ].includes( item.dataset.state ) ).length;
		const percentage = Math.round( ( passed / items.length ) * 100 );
		text( one( "[data-wmcp-health-score]" ), `${ percentage }%` );
		text( one( "[data-wmcp-health-summary]" ), `${ passed } of ${ items.length } checks ready` );
	}

	function copyText( value ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( value );
		}

		return new Promise( ( resolve, reject ) => {
			const field = document.createElement( "textarea" );
			field.value = value;
			field.setAttribute( "readonly", "" );
			field.style.position = "fixed";
			field.style.opacity = "0";
			document.body.append( field );
			field.select();
			try {
				document.execCommand( "copy" ) ? resolve() : reject( new Error( "Copy failed" ) );
			} catch ( error ) {
				reject( error );
			} finally {
				field.remove();
			}
		} );
	}

	function installCopyButtons() {
		all( "[data-copy-target]" ).forEach( ( button ) => {
			button.addEventListener( "click", async () => {
				const key = button.dataset.copyTarget;
				const source = one( `[data-copy-source="${ key }"]` );
				const label = one( "[data-copy-label]", button );
				if ( ! source ) {
					return;
				}
				try {
					await copyText( source.textContent.trim() );
					text( label, "Copied" );
					announce( "Prompt copied to the clipboard." );
					window.setTimeout( () => text( label, key === "storefront" ? "Copy prompt" : key === "shopper" ? "Copy shopper prompt" : "Copy operator prompt" ), 1600 );
				} catch {
					text( label, "Select and copy" );
					announce( "Automatic copy was unavailable. Select the prompt text and copy it manually." );
				}
			} );
		} );
	}

	async function manifestForReset() {
		if ( ! config.manifestUrl ) {
			throw new Error( "The demo manifest URL is unavailable." );
		}
		await ensureSession();
		return ( await fetchJson( config.manifestUrl ) ).payload;
	}

	function installResetButtons() {
		all( "[data-wmcp-reset]" ).forEach( ( button ) => {
			button.addEventListener( "click", async () => {
				const feedback = one( "[data-wmcp-reset-feedback]" );
				button.disabled = true;
				text( feedback, "Rotating this browser’s private demo scope…" );
				showError( "" );
				try {
					const manifest = await manifestForReset();
					const surface = button.dataset.resetSurface || config.surface || "storefront";
					const { payload } = await fetchJson( config.resetUrl, {
						body: JSON.stringify( { surface } ),
						headers: {
							Accept: "application/json",
							"Content-Type": "application/json",
							"X-WMCP-CSRF": manifest.session?.csrf_token || "",
						},
						method: "POST",
					} );
					text( feedback, payload.message || "A fresh private demo session is ready." );
					window.dispatchEvent( new CustomEvent( "wmcp:manifest-invalidated", {
						detail: { broadcast: true, reason: "demo_reset", surface: null },
					} ) );
					announce( "The demo session was reset. Reloading the field surface." );
					window.setTimeout( () => window.location.reload(), 500 );
				} catch ( error ) {
					button.disabled = false;
					text( feedback, "" );
					showError( error.message || "The demo session could not be reset." );
				}
			} );
		} );
	}

	function installHealthDownload() {
		const button = one( "[data-wmcp-download-health]" );
		if ( ! button ) {
			return;
		}
		button.addEventListener( "click", () => {
			if ( ! healthSnapshot ) {
				announce( "Wait for the readiness checks to finish before downloading." );
				return;
			}
			const blob = new Blob( [ JSON.stringify( healthSnapshot, null, 2 ) ], { type: "application/json" } );
			const url = URL.createObjectURL( blob );
			const link = document.createElement( "a" );
			link.href = url;
			link.download = "webmcp-public-readiness.json";
			document.body.append( link );
			link.click();
			link.remove();
			URL.revokeObjectURL( url );
			announce( "The public readiness report was downloaded." );
		} );
	}

	window.addEventListener( "wmcp:status", ( event ) => updateStatus( event.detail || {} ) );
	window.addEventListener( "wmcp:manifest-ready", ( event ) => {
		renderManifest( event.detail );
		updateStatus( { status: "ready" } );
		updateHealthScore();
	} );
	window.addEventListener( "wmcp:manifest-snapshot", ( event ) => {
		renderManifest( event.detail );
	} );
	window.addEventListener( "wmcp:tool-start", ( event ) => {
		const detail = event.detail || {};
		all( "[data-wmcp-latest-tool]" ).forEach( ( target ) => text( target, `${ detail.tool || "tool" } · running` ) );
		announce( `${ detail.tool || "Tool" } started.` );
	} );
	window.addEventListener( "wmcp:tool-result", ( event ) => {
		const detail = event.detail || {};
		all( "[data-wmcp-latest-tool]" ).forEach( ( target ) => text( target, `${ detail.tool || "tool" } · success` ) );
		announce( `${ detail.tool || "Tool" } completed and the visible state was updated.` );
		showError( "" );
	} );
	window.addEventListener( "wmcp:tool-error", ( event ) => {
		const detail = event.detail || {};
		const message = detail.response?.error?.message || "The tool could not complete the request.";
		all( "[data-wmcp-latest-tool]" ).forEach( ( target ) => text( target, `${ detail.tool || "tool" } · failed` ) );
		showError( message );
		announce( `${ detail.tool || "Tool" } failed. ${ message }` );
	} );
	window.addEventListener( "wmcp:tool-cancelled", ( event ) => {
		const detail = event.detail || {};
		const message = detail.message || "The client stopped waiting. The server may still complete the request.";
		all( "[data-wmcp-latest-tool]" ).forEach( ( target ) => text( target, `${ detail.tool || "tool" } · client stopped waiting` ) );
		announce( `${ detail.tool || "Tool" }: ${ message }` );
	} );

	installCopyButtons();
	installResetButtons();
	installHealthDownload();
	const initialFacts = updateBrowserChecks();
	updateStatus(
		config.autoStart === false
			? { status: initialFacts.webmcp_api ? "api-detected" : "unsupported-browser" }
			: {}
	);

	if ( root.matches( ".wmcp-health, .wmcp-landing" ) ) {
		loadHealth();
	}
}() );
