import { expect, test } from "@playwright/test";

const BASE_URL = process.env.WMCP_BASE_URL || "http://localhost:18080";

const STOREFRONT_TOOLS = [
	"add_to_cart",
	"checkout_handoff",
	"compare_products",
	"get_agent_guide",
	"get_cart",
	"get_product",
	"get_store_policy",
	"get_storefront_context",
	"remove_from_cart",
	"report_agent_feedback",
	"report_capability_gap",
	"search_products",
	"update_cart_quantity",
];

const AGENTOPS_TOOLS = [
	"explain_agent_workflow",
	"get_agent_analytics_overview",
	"get_agent_conversion_funnel",
	"get_capability_gaps",
	"get_opportunity_signals",
	"get_tool_health",
	"query_agent_workflows",
	"run_webmcp_diagnostics",
	"set_tool_enabled",
];

const browserDiagnostics = new WeakMap();
const pageDiagnostics = new WeakMap();

function observePage( page, diagnostics ) {
	pageDiagnostics.set( page, diagnostics );
	page.on( "console", ( message ) => {
		if ( message.type() === "error" ) {
			const locationUrl = message.location().url || "";
			if (
				message.text().includes( "status of 401" ) &&
				locationUrl.includes( "/wp-json/wmcp-agentops/v1/manifest" )
			) {
				// A fresh browser intentionally probes the no-side-effect manifest
				// endpoint before bootstrapping its private server session.
				return;
			}

			// WordPress core probes emoji support with a blob Worker, catches the CSP
			// rejection, and immediately uses its non-worker fallback. The demo CSP
			// intentionally does not grant blob-worker execution.
			if (
				message.text().startsWith( "Creating a worker from 'blob:" ) &&
				message.text().includes( "worker-src" ) &&
				message.text().endsWith( "The action has been blocked." )
			) {
				return;
			}

			const status = message.text().match( /status of (\d{3})/ )?.[ 1 ];
			const expectedIndex = status
				? diagnostics.expectedHttpErrors.indexOf( Number( status ) )
				: -1;
			if ( expectedIndex >= 0 ) {
				diagnostics.expectedHttpErrors.splice( expectedIndex, 1 );
			} else {
				diagnostics.consoleErrors.push( message.text() );
			}
		}
	} );
	page.on( "pageerror", ( error ) => diagnostics.pageErrors.push( error.message ) );
	page.on( "requestfailed", ( request ) => {
		const failure = request.failure();
		if ( failure?.errorText === "net::ERR_ABORTED" ) {
			return;
		}

		diagnostics.failedRequests.push(
			`${ request.method() } ${ request.url() } · ${ failure?.errorText || "unknown failure" }`
		);
	} );
}

function allowHttpConsoleErrors( page, statuses ) {
	pageDiagnostics.get( page ).expectedHttpErrors.push( ...statuses );
}

async function installModelContextMock( context ) {
	await context.addInitScript( () => {
		const state = {
			activeTools: new Map(),
			invalidations: [],
			registrations: [],
		};

		globalThis.__wmcpBrowserTest = state;

		const originalSetItem = globalThis.Storage.prototype.setItem;
		globalThis.Storage.prototype.setItem = function ( key, value ) {
			if ( key === "wmcp-agentops:manifest-invalidation" ) {
				try {
					state.invalidations.push( JSON.parse( value ) );
				} catch {
					// The production runtime owns validation; the test records valid JSON only.
				}
			}

			return originalSetItem.call( this, key, value );
		};

		Object.defineProperty( globalThis.document, "modelContext", {
			configurable: true,
			value: {
				registerTool( definition, options = {} ) {
					const registration = { definition, options };
					state.registrations.push( registration );
					state.activeTools.set( definition.name, definition );

					options.signal?.addEventListener(
						"abort",
						() => {
							if ( state.activeTools.get( definition.name ) === definition ) {
								state.activeTools.delete( definition.name );
							}
						},
						{ once: true }
					);

					return Promise.resolve();
				},
			},
		} );
	} );
}

async function installMissingModelContext( context ) {
	await context.addInitScript( () => {
		Object.defineProperty( globalThis.document, "modelContext", {
			configurable: true,
			value: undefined,
		} );
	} );
}

async function waitForRuntime( page, expectedToolCount ) {
	await expect
		.poll( () => page.evaluate( () => globalThis.document.documentElement.dataset.wmcpStatus ) )
		.toBe( "ready" );
	await expect
		.poll( () => page.evaluate( () => globalThis.__wmcpBrowserTest.activeTools.size ) )
		.toBe( expectedToolCount );
}

async function registrationSnapshot( page ) {
	return page.evaluate( () =>
		globalThis.__wmcpBrowserTest.registrations.map( ( { definition, options } ) => ( {
			annotations: definition.annotations,
			definitionKeys: Object.keys( definition ).sort(),
			description: definition.description,
			executeType: typeof definition.execute,
			inputSchema: definition.inputSchema,
			name: definition.name,
			registrationKeys: Object.keys( options ).sort(),
			signalAborted: options.signal?.aborted,
			signalIsAbortSignal: options.signal instanceof globalThis.AbortSignal,
			title: definition.title,
		} ) )
	);
}

async function executeActiveTool( page, name, input = {} ) {
	return page.evaluate(
		async ( invocation ) => {
			const definition = globalThis.__wmcpBrowserTest.activeTools.get( invocation.name );
			if ( ! definition ) {
				throw new Error( `Tool ${ invocation.name } is not actively registered.` );
			}

			return definition.execute( invocation.input );
		},
		{ input, name }
	);
}

async function executeFirstRegisteredTool( page, name, input = {} ) {
	return page.evaluate(
		async ( invocation ) => {
			const registration = globalThis.__wmcpBrowserTest.registrations.find(
				( candidate ) => candidate.definition.name === invocation.name
			);
			if ( ! registration ) {
				throw new Error( `Tool ${ invocation.name } was never registered.` );
			}

			return registration.definition.execute( invocation.input );
		},
		{ input, name }
	);
}

async function activeToolNames( page ) {
	return page.evaluate( () => Array.from( globalThis.__wmcpBrowserTest.activeTools.keys() ).sort() );
}

async function resetCurrentSession( page ) {
	if ( page.isClosed() || ! page.url().startsWith( "http" ) ) {
		return;
	}

	await page.evaluate( async () => {
		const config = globalThis.wmcpConfig;
		if ( ! config?.manifestUrl || ! config?.resetUrl ) {
			return;
		}

		let manifest = globalThis.wmcpRuntime?.activeManifest;
		if ( ! manifest ) {
			if ( config.sessionUrl ) {
				const sessionResponse = await fetch( config.sessionUrl, {
					body: JSON.stringify( { surface: config.surface || "storefront" } ),
					cache: "no-store",
					credentials: "same-origin",
					headers: { Accept: "application/json", "Content-Type": "application/json" },
					method: "POST",
				} );
				if ( sessionResponse.status === 429 ) {
					return;
				}
				if ( ! sessionResponse.ok ) {
					throw new Error( `Demo session bootstrap failed with HTTP ${ sessionResponse.status }.` );
				}
			}

			const manifestResponse = await fetch( config.manifestUrl, {
				cache: "no-store",
				credentials: "same-origin",
				headers: { Accept: "application/json" },
			} );
			if ( ! manifestResponse.ok ) {
				throw new Error( `Demo manifest fetch failed with HTTP ${ manifestResponse.status }.` );
			}
			manifest = await manifestResponse.json();
		}

		const token = manifest.session?.csrf_token;
		if ( ! token ) {
			throw new Error( "Demo manifest did not provide a reset token." );
		}

		const response = await fetch( config.resetUrl, {
			body: JSON.stringify( { surface: manifest.surface || config.surface || "storefront" } ),
			cache: "no-store",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-WMCP-CSRF": token,
			},
			method: "POST",
		} );
		const payload = await response.json().catch( () => null );
		if ( ! response.ok || payload?.ok !== true ) {
			throw new Error( payload?.error?.message || `Demo reset failed with HTTP ${ response.status }.` );
		}
	} );
}

async function searchAndAddProduct( page ) {
	const initialCart = await executeActiveTool( page, "get_cart" );
	expect( initialCart.ok ).toBe( true );
	const search = await executeActiveTool( page, "search_products", {
		in_stock_only: true,
		limit: 4,
		max_price: 120,
		query: "waterproof backpack",
	} );

	expect( search.ok ).toBe( true );
	expect( search.result.result_count ).toBeGreaterThan( 0 );
	const product = search.result.products[ 0 ];
	expect( product.purchasable ).toBe( true );

	const add = await executeActiveTool( page, "add_to_cart", {
		expected_cart_revision: initialCart.result.cart_revision,
		product_id: product.id,
		quantity: 1,
	} );

	expect( add.ok ).toBe( true );
	expect( add.result.cart.item_count ).toBe( 1 );

	return { add, product, search };
}

async function prepareCheckout( page ) {
	const cart = await executeActiveTool( page, "get_cart" );
	expect( cart.ok ).toBe( true );
	expect( cart.result.item_count ).toBeGreaterThan( 0 );

	const handoff = await executeActiveTool( page, "checkout_handoff", {
		expected_cart_revision: cart.result.cart_revision,
	} );
	expect( handoff.ok ).toBe( true );
	expect( handoff.result.checkout_url ).toContain( "/checkout/" );

	return handoff;
}

async function placeDemoOrder( page, checkoutUrl ) {
	await page.goto( checkoutUrl );
	await expect( page.locator( "form.checkout" ) ).toBeVisible();
	await expect( page.locator( "#payment_method_wmcp_agentops_demo" ) ).toBeAttached();
	await page.locator( "#payment_method_wmcp_agentops_demo" ).check();
	await expect( page.locator( "label[for='payment_method_wmcp_agentops_demo']" ) ).toContainText( "Demo payment" );

	await Promise.all( [
		page.waitForURL( /\/checkout\/order-received\//, { timeout: 20_000 } ),
		page.locator( "#place_order" ).click(),
	] );

	await expect( page.getByRole( "main" ) ).toContainText( /Order #:\s*\d+/ );
	await expect( page.locator( "body" ) ).toContainText( /order has been received|thank you/i );
}

async function analyticsOverview( page ) {
	await page.goto( "/agentops-demo/" );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
	const overview = await executeActiveTool( page, "get_agent_analytics_overview", {} );
	expect( overview.ok ).toBe( true );

	return overview.result;
}

test.beforeEach( async ( { context }, testInfo ) => {
	const diagnostics = {
		consoleErrors: [],
		expectedHttpErrors: [],
		failedRequests: [],
		pageErrors: [],
	};
	browserDiagnostics.set( testInfo, diagnostics );
	context.pages().forEach( ( page ) => observePage( page, diagnostics ) );
	context.on( "page", ( page ) => observePage( page, diagnostics ) );
} );

test.afterEach( async ( { page }, testInfo ) => {
	await resetCurrentSession( page );
	const diagnostics = browserDiagnostics.get( testInfo );
	expect( diagnostics.pageErrors, "unexpected browser script errors" ).toEqual( [] );
	expect( diagnostics.consoleErrors, "unexpected browser console errors" ).toEqual( [] );
	expect( diagnostics.failedRequests, "unexpected browser request failures" ).toEqual( [] );
	expect( diagnostics.expectedHttpErrors, "expected HTTP errors were not observed" ).toEqual( [] );
} );

test( "keeps the ordinary storefront usable when WebMCP is unavailable", async ( { context, page } ) => {
	await installMissingModelContext( context );
	await page.goto( "/" );
	await expect( page ).toHaveTitle( /Agent SNR/ );
	await expect( page.locator( "#wmcp-landing-title" ) ).toContainText( "Agent SNR" );
	await expect( page.locator( ".wmcp-wordmark" ).first() ).toContainText( /Agent\s*SNR/ );
	await expect( page.getByRole( "link", { exact: true, name: "Agent SNR — Overview" } ).first() ).toBeVisible();
	await expect( page.getByRole( "link", { exact: true, name: "Agent SNR — Monitor" } ).first() ).toBeVisible();

	await page.goto( "/storefront-demo/" );

	await expect
		.poll( () => page.evaluate( () => globalThis.document.documentElement.dataset.wmcpStatus ) )
		.toBe( "unsupported-browser" );
	await expect( page.locator( 'span[data-wmcp-status]' ) ).toHaveText( "WebMCP not detected" );
	await expect( page.locator( ".wmcp-product-card" ) ).toHaveCount( 12 );
	await expect( page.locator( ".wmcp-product-card a" ).first() ).toHaveAttribute( "href", /\/product\// );
	await expect( page.locator( ".wmcp-cart-link" ) ).toHaveAttribute( "href", /\/cart\/?$/ );
} );

test( "cacheable page HTML contains no session or credential state", async ( { request } ) => {
	const response = await request.get( "/storefront-demo/" );
	const html = await response.text();

	expect( response.ok() ).toBe( true );
	expect( html ).not.toContain( "csrf_token" );
	expect( html ).not.toContain( "wmcp_demo_session=" );
	expect( html ).not.toMatch( /data-workflow-id=["'][0-9A-HJKMNP-TV-Z]{26}/ );
} );

test( "registers every storefront and Agent SNR tool with standard imperative fields", async ( { context, page } ) => {
	await installModelContextMock( context );

	for ( const surface of [
		{ expected: STOREFRONT_TOOLS, path: "/storefront-demo/" },
		{ expected: AGENTOPS_TOOLS, path: "/agentops-demo/" },
	] ) {
		await page.goto( surface.path );
		await waitForRuntime( page, surface.expected.length );

		const registrations = await registrationSnapshot( page );
		expect( registrations.map( ( item ) => item.name ).sort() ).toEqual( [ ...surface.expected ].sort() );

		for ( const registration of registrations ) {
			expect( registration.definitionKeys ).toEqual( [
				"annotations",
				"description",
				"execute",
				"inputSchema",
				"name",
				"title",
			] );
			expect( registration.registrationKeys ).toEqual( [ "signal" ] );
			expect( registration.signalIsAbortSignal ).toBe( true );
			expect( registration.signalAborted ).toBe( false );
			expect( registration.executeType ).toBe( "function" );
			expect( registration.title.length ).toBeGreaterThan( 0 );
			expect( registration.description.length ).toBeGreaterThan( 0 );
			expect( registration.inputSchema ).toMatchObject( {
				additionalProperties: false,
				type: "object",
			} );
			expect( registration.annotations ).toEqual( {
				readOnlyHint: expect.any( Boolean ),
				untrustedContentHint: expect.any( Boolean ),
			} );
		}
	}
} );

test( "executes search, cart, and checkout handoff through registered callbacks", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	const { product } = await searchAndAddProduct( page );

	await expect( page.locator( "[data-wmcp-search-results]" ) ).toContainText( product.name );
	await expect( page.locator( `.wmcp-product-card[data-product-id="${ product.id }"]` ) ).toHaveClass( /wmcp-is-match/ );
	await expect( page.locator( "[data-wmcp-cart]" ) ).toContainText( product.name );
	await expect( page.locator( "[data-wmcp-cart-count]" ).first() ).toHaveText( "1" );

	const cart = await executeActiveTool( page, "get_cart" );
	expect( cart.ok ).toBe( true );
	expect( cart.result.item_count ).toBe( 1 );

	const handoff = await executeActiveTool( page, "checkout_handoff", {
		expected_cart_revision: cart.result.cart_revision,
	} );
	expect( handoff.ok ).toBe( true );
	expect( handoff.result.checkout_url ).toContain( "/checkout/" );

	const checkoutLink = page.locator( "[data-wmcp-checkout-link]" );
	await expect( page.locator( "#wmcp-checkout-title" ) ).toHaveText( "Checkout handoff ready" );
	await expect( checkoutLink ).toBeVisible();
	await expect( checkoutLink ).toContainText( "Continue to demo checkout" );
	await expect( checkoutLink ).toHaveAttribute( "href", /\/checkout\/?$/ );
} );

test( "hydrates and synchronizes the shared cart badge across storefront tabs", async ( { context, page } ) => {
	await installModelContextMock( context );
	const observerPage = await context.newPage();
	let latePage;

	try {
		await page.goto( "/storefront-demo/" );
		await observerPage.goto( "/storefront-demo/" );
		await waitForRuntime( page, STOREFRONT_TOOLS.length );
		await waitForRuntime( observerPage, STOREFRONT_TOOLS.length );

		const observerBadge = observerPage.locator( "[data-wmcp-cart-count]" ).first();
		await expect( observerBadge ).toHaveText( "0" );
		await expect( observerBadge ).toHaveAttribute( "aria-label", "0 items in cart" );

		await searchAndAddProduct( page );

		await expect( observerBadge ).toHaveText( "1" );
		await expect( observerBadge ).toHaveAttribute( "aria-label", "1 item in cart" );
		await expect( observerPage.locator( "#wmcp-cart-title" ) ).toHaveText( "No cart signal yet" );

		latePage = await context.newPage();
		await latePage.goto( "/storefront-demo/" );
		await waitForRuntime( latePage, STOREFRONT_TOOLS.length );
		await expect( latePage.locator( "[data-wmcp-cart-count]" ).first() ).toHaveText( "1" );
		await expect( latePage.locator( "[data-wmcp-cart-count]" ).first() ).toHaveAttribute(
			"aria-label",
			"1 item in cart"
		);
	} finally {
		await latePage?.close();
		await observerPage.close();
	}
} );

test( "places a real classic-checkout demo order with direct agent attribution", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	await searchAndAddProduct( page );
	const handoff = await prepareCheckout( page );
	await placeDemoOrder( page, handoff.result.checkout_url );

	const overview = await analyticsOverview( page );
	expect( overview.commerce.orders_created ).toBeGreaterThanOrEqual( 1 );
	expect( overview.commerce.orders_paid ).toBeGreaterThanOrEqual( 1 );
	expect( overview.revenue.attribution.direct.orders ).toBeGreaterThanOrEqual( 1 );
	expect( overview.revenue.attribution.assisted?.orders || 0 ).toBe( 0 );
} );

test( "an actual agent removal followed by human re-add is influenced, never direct", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	const { add, product } = await searchAndAddProduct( page );
	const line = add.result.cart.items[ 0 ];
	const remove = await executeActiveTool( page, "remove_from_cart", {
		cart_item_key: line.cart_item_key,
		expected_cart_revision: add.result.cart.cart_revision,
	} );
	expect( remove.ok ).toBe( true );
	expect( remove.result.cart.item_count ).toBe( 0 );

	await page.goto( "/?add-to-cart=" + product.id );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );
	const humanCart = await executeActiveTool( page, "get_cart" );
	expect( humanCart.result.item_count ).toBe( 1 );

	const handoff = await prepareCheckout( page );
	await placeDemoOrder( page, handoff.result.checkout_url );

	const overview = await analyticsOverview( page );
	expect( overview.revenue.attribution.influenced.orders ).toBeGreaterThanOrEqual( 1 );
	expect( overview.revenue.attribution.direct?.orders || 0 ).toBe( 0 );
	expect( overview.revenue.attribution.assisted?.orders || 0 ).toBe( 0 );
} );

test( "withholds the no-charge gateway from a human-only cart", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );
	const productId = await page.locator( ".wmcp-product-card" ).first().getAttribute( "data-product-id" );
	expect( productId ).toMatch( /^\d+$/ );

	await page.goto( "/?add-to-cart=" + productId );
	await page.goto( "/checkout/" );
	await expect( page.locator( "form.checkout" ) ).toBeVisible();
	await expect( page.locator( "#payment_method_wmcp_agentops_demo" ) ).toHaveCount( 0 );

	await page.goto( "/agentops-demo/" );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
} );

test( "skips direct registration inside an iframe", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.setContent( `<iframe title="embedded storefront" src="${ BASE_URL }/storefront-demo/"></iframe>` );

	const frame = await ( await page.locator( "iframe" ).elementHandle() ).contentFrame();
	expect( frame ).not.toBeNull();
	await frame.waitForLoadState( "domcontentloaded" );
	await expect
		.poll( () => frame.evaluate( () => globalThis.document.documentElement.dataset.wmcpStatus ) )
		.toBe( "embedded-context" );
	expect( await frame.evaluate( () => globalThis.__wmcpBrowserTest.registrations.length ) ).toBe( 0 );
	await expect( frame.locator( "span[data-wmcp-status]" ) ).toHaveText( "Embedded context" );
} );

test( "manually loads current-session storefront evidence into Agent SNR", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	const search = await executeActiveTool( page, "search_products", {
		in_stock_only: true,
		limit: 4,
		max_price: 120,
		query: "waterproof backpack",
	} );
	expect( search.ok ).toBe( true );

	const gap = await executeActiveTool( page, "report_capability_gap", {
		context: { color: "blue" },
		related_product_id: search.result.products[ 0 ].id,
		requested_capability: "back_in_stock_notification",
		user_goal: "Notify the shopper when the blue version is back in stock.",
	} );
	expect( gap.ok ).toBe( true );

	await page.goto( "/agentops-demo/" );
	await expect( page ).toHaveTitle( /Agent SNR/ );
	await expect( page.getByRole( "heading", { exact: true, name: "Agent SNR" } ).first() ).toBeVisible();
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
	await page.locator( "[data-wmcp-load-dashboard]" ).click();

	await expect( page.locator( "[data-wmcp-announcer]" ) ).toContainText( "Current-session evidence loaded" );
	await expect( page.locator( "[data-wmcp-error]" ) ).toBeHidden();
	await expect
		.poll( async () => Number.parseInt( await page.locator( '[data-metric="commerce.product_searches"]' ).textContent(), 10 ) )
		.toBeGreaterThan( 0 );
	await expect( page.locator( "[data-wmcp-workflows] [data-explain-workflow]" ).first() ).toBeVisible();
	await expect( page.locator( "[data-wmcp-tool-health]" ) ).toContainText( "search_products" );
	await expect( page.locator( "[data-wmcp-gaps]" ) ).toContainText( /back-in-stock notification/i );
	await expect
		.poll( async () => Number.parseInt( await page.locator( '[data-funnel-stage="product_search"] [data-funnel-count]' ).textContent(), 10 ) )
		.toBeGreaterThan( 0 );
} );

test( "discovers the guide, records missed demand, and separates agent feedback from site evidence", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	await expect( page.locator( "[data-wmcp-agent-guide]" ) ).toBeVisible();
	await expect( page.locator( "[data-wmcp-guide-status]" ) ).toHaveText( "Start here" );
	const guide = await executeActiveTool( page, "get_agent_guide", {} );
	expect( guide.ok ).toBe( true );
	expect( guide.result.version ).toBe( "1.0" );
	await expect( page.locator( "[data-wmcp-guide-status]" ) ).toHaveText( "Read by agent" );

	const zero = await executeActiveTool( page, "search_products", {
		attributes: { water_rating: "IPX5" },
		in_stock_only: true,
		limit: 6,
		max_price: 100,
		query: "waterproof backpack",
	} );
	expect( zero.ok ).toBe( true );
	expect( zero.result.result_count ).toBe( 0 );
	expect( zero.result.opportunity_signal ).toMatchObject( {
		evidence_status: "verified",
		signal_code: "zero_results",
		source: "site_observed",
	} );
	await expect( page.locator( "[data-wmcp-search-opportunity]" ) ).toBeVisible();
	await expect( page.locator( "[data-wmcp-search-opportunity]" ) ).toContainText( /Site observed|recorded/i );

	const relaxed = await executeActiveTool( page, "search_products", {
		in_stock_only: true,
		limit: 6,
		max_price: 100,
		query: "waterproof backpack",
	} );
	expect( relaxed.ok ).toBe( true );
	expect( relaxed.result.result_count ).toBe( 2 );
	expect( relaxed.result.products.every( ( product ) => product.attributes.water_rating === "IPX4" ) ).toBe( true );
	const harborLite = relaxed.result.products.find( ( product ) => product.name === "HarborLite 16 Pack" );
	expect( harborLite ).toBeTruthy();

	const cart = await executeActiveTool( page, "get_cart" );
	const add = await executeActiveTool( page, "add_to_cart", {
		expected_cart_revision: cart.result.cart_revision,
		product_id: harborLite.id,
		quantity: 1,
	} );
	const handoff = await executeActiveTool( page, "checkout_handoff", {
		expected_cart_revision: add.result.cart.cart_revision,
	} );
	const feedback = await executeActiveTool( page, "report_agent_feedback", {
		evidence_event_ids: [ zero.event_id, relaxed.event_id, handoff.event_id ],
		feedback_type: "constraint_encountered",
		outcome: "partial",
		ratings: {
			effort: "medium",
			evidence_quality: "sufficient",
			handoff_quality: "smooth",
			policy_clarity: "not_applicable",
		},
		reason_code: "budget_tradeoff",
		requested_metrics: [
			"eligible_product_count",
			"highest_matching_water_rating",
			"search_refinement_count",
			"checkout_conversion",
			"paid_order_value",
		],
		step: "checkout_handoff",
		suggested_owner_action: "improve_product_coverage",
	} );
	expect( feedback.ok ).toBe( true );
	expect( feedback.result ).toMatchObject( {
		evidence_status: "linked",
		recorded: true,
		trust: "agent_reported",
	} );
	expect( feedback.result.measured_context.eligible_product_count.value ).toBe( 2 );
	expect( feedback.result.measured_context.highest_matching_water_rating.value ).toBe( "IPX4" );
	expect( feedback.result.measured_context.checkout_conversion.status ).toBe( "pending" );
	await expect( page.locator( '[data-wmcp-panel="feedback"]' ) ).toBeVisible();
	await expect( page.locator( "[data-wmcp-feedback-trust]" ) ).toContainText( "Agent reported" );
	await expect( page.locator( "[data-wmcp-feedback-metrics]" ) ).toContainText( "IPX4" );

	await page.goto( "/agentops-demo/" );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
	const signals = await executeActiveTool( page, "get_opportunity_signals", {} );
	expect( signals.ok ).toBe( true );
	expect( signals.result.items.some( ( item ) => item.sources.site_observed ) ).toBe( true );
	expect( signals.result.items.some( ( item ) => item.sources.agent_reported ) ).toBe( true );
	await page.locator( "[data-wmcp-load-dashboard]" ).click();
	await expect( page.locator( "[data-wmcp-opportunities]" ) ).toContainText( "Site observed" );
	await expect( page.locator( "[data-wmcp-opportunities]" ) ).toContainText( "Agent reported" );
	await expect( page.locator( "[data-wmcp-opportunities]" ) ).not.toContainText( /lost revenue/i );
} );

test( "opens a bounded partial replay for a large Agent Sessions workflow", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	let cart;
	for ( let invocation = 0; invocation < 12; invocation++ ) {
		cart = await executeActiveTool( page, "get_cart" );
		expect( cart.ok ).toBe( true );
	}
	const workflowId = cart.workflow_id;

	await page.goto( "/agentops-demo/" );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
	await page.locator( "[data-wmcp-load-dashboard]" ).click();
	await expect( page.locator( "[data-wmcp-announcer]" ) ).toContainText( "Current-session evidence loaded" );

	const workflowRow = page.locator( `[data-workflow-id="${ workflowId }"]` );
	await expect( workflowRow ).toBeVisible();
	await workflowRow.locator( "[data-explain-workflow]" ).click();

	await expect( page.locator( "#wmcp-timeline-title" ) ).toHaveText( `Workflow ${ workflowId.slice( 0, 8 ) }…` );
	await expect( page.locator( "[data-wmcp-timeline-count]" ) ).toContainText( "partial replay" );
	await expect( page.locator( "[data-wmcp-timeline] li" ).first() ).toBeVisible();
	await expect( page.locator( "[data-wmcp-error]" ) ).toBeHidden();
} );

test( "disabling compare removes its registration and denies stale or direct execution", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );
	expect( await activeToolNames( page ) ).toContain( "compare_products" );

	const agentOpsPage = await context.newPage();
	await agentOpsPage.goto( "/agentops-demo/" );
	await waitForRuntime( agentOpsPage, AGENTOPS_TOOLS.length );

	const policy = await executeActiveTool( agentOpsPage, "set_tool_enabled", {
		enabled: false,
		reason: "Browser acceptance test for current-session governance",
		scope: "demo_session",
		tool_name: "compare_products",
	} );
	expect( policy.ok ).toBe( true );
	expect( policy.result.after.enabled ).toBe( false );

	const crossSurfaceMessage = await agentOpsPage.evaluate( () =>
		globalThis.__wmcpBrowserTest.invalidations.find(
			( message ) => message.reason === "tool_result"
		)
	);
	expect( crossSurfaceMessage ).toMatchObject( {
		reason: "tool_result",
		surface: null,
		type: "wmcp:manifest-invalidated",
	} );

	await expect.poll( () => activeToolNames( page ) ).not.toContain( "compare_products" );
	await expect
		.poll( () => page.evaluate( () => globalThis.wmcpRuntime.activeManifest.tools.map( ( tool ) => tool.name ) ) )
		.not.toContain( "compare_products" );
	expect(
		await page.evaluate( () =>
			globalThis.__wmcpBrowserTest.registrations.find(
				( registration ) => registration.definition.name === "compare_products"
			).options.signal.aborted
		)
	).toBe( true );

	allowHttpConsoleErrors( page, [ 409, 403 ] );
	const staleExecution = await executeFirstRegisteredTool( page, "compare_products", {
		product_ids: [ 1, 2 ],
	} );
	expect( staleExecution.ok ).toBe( false );
	expect( [ "manifest_stale", "tool_disabled" ] ).toContain( staleExecution.error.code );

	const directDenial = await page.evaluate( async () => {
		const config = globalThis.wmcpConfig;
		const manifest = await fetch( config.manifestUrl, {
			cache: "no-store",
			credentials: "same-origin",
			headers: { Accept: "application/json" },
		} ).then( ( response ) => response.json() );
		const response = await fetch( `${ config.executionBaseUrl }/tools/compare_products`, {
			body: JSON.stringify( {
				input: { product_ids: [ 1, 2 ] },
				manifest_revision: manifest.manifest_revision,
				request_id: globalThis.crypto.randomUUID(),
				schema_version: manifest.schema_version,
				workflow_id: manifest.workflow_id,
			} ),
			cache: "no-store",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-WMCP-CSRF": manifest.session.csrf_token,
			},
			method: "POST",
		} );

		return { body: await response.json(), status: response.status };
	} );
	expect( directDenial.status ).toBe( 403 );
	expect( directDenial.body.error.code ).toBe( "tool_disabled" );
} );

test( "reset rotates the private scope and clears the current cart", async ( { context, page } ) => {
	await installModelContextMock( context );
	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );
	await searchAndAddProduct( page );
	await expect( page.locator( "[data-wmcp-cart-count]" ).first() ).toHaveText( "1" );

	await page.goto( "/agentops-demo/" );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );
	const resetResponse = page.waitForResponse(
		( response ) => response.request().method() === "POST" && response.url().includes( "/demo/reset" )
	);
	await Promise.all( [
		page.waitForNavigation( { waitUntil: "domcontentloaded" } ),
		page.locator( "[data-wmcp-reset]" ).click(),
	] );
	const reset = await resetResponse;
	expect( reset.ok() ).toBe( true );
	expect( await reset.json() ).toMatchObject( {
		manifest: { surface: "agentops" },
		ok: true,
	} );
	await waitForRuntime( page, AGENTOPS_TOOLS.length );

	await page.goto( "/storefront-demo/" );
	await waitForRuntime( page, STOREFRONT_TOOLS.length );

	const cart = await executeActiveTool( page, "get_cart" );
	expect( cart.ok ).toBe( true );
	expect( cart.result.item_count ).toBe( 0 );
	expect( cart.result.items ).toEqual( [] );
	await expect( page.locator( "[data-wmcp-cart-count]" ).first() ).toHaveText( "0" );
	await expect( page.locator( "#wmcp-cart-title" ) ).toHaveText( "Session cart is empty" );
} );

test( "separate browser sessions cannot see analytics or policy overrides", async ( { browser, context, page } ) => {
	await installModelContextMock( context );
	const otherContext = await browser.newContext( { baseURL: BASE_URL } );
	await installModelContextMock( otherContext );
	const otherPage = await otherContext.newPage();

	try {
		await page.goto( "/storefront-demo/" );
		await waitForRuntime( page, STOREFRONT_TOOLS.length );
		await executeActiveTool( page, "search_products", {
			in_stock_only: true,
			limit: 4,
			max_price: 120,
			query: "waterproof backpack",
		} );

		await page.goto( "/agentops-demo/" );
		await waitForRuntime( page, AGENTOPS_TOOLS.length );
		const ownOverview = await executeActiveTool( page, "get_agent_analytics_overview", {} );
		expect( ownOverview.result.workflows.total ).toBeGreaterThan( 0 );

		await otherPage.goto( "/agentops-demo/" );
		await waitForRuntime( otherPage, AGENTOPS_TOOLS.length );
		const otherOverview = await executeActiveTool( otherPage, "get_agent_analytics_overview", {} );
		expect( otherOverview.result.workflows.total ).toBe( 0 );

		const policy = await executeActiveTool( page, "set_tool_enabled", {
			enabled: false,
			reason: "Isolation acceptance test",
			scope: "demo_session",
			tool_name: "compare_products",
		} );
		expect( policy.result.after.enabled ).toBe( false );

		await otherPage.goto( "/storefront-demo/" );
		await waitForRuntime( otherPage, STOREFRONT_TOOLS.length );
		expect( await activeToolNames( otherPage ) ).toContain( "compare_products" );
	} finally {
		await resetCurrentSession( otherPage );
		await otherContext.close();
	}
} );
