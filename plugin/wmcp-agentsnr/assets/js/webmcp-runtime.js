/*
 * WordPress WebMCP agent-monitoring browser runtime.
 *
 * This file intentionally has no runtime dependencies. It can be loaded as a
 * classic browser script and is also exported through CommonJS for the Node
 * test suite.
 */
( function () {
	"use strict";

	const DEFAULTS = Object.freeze( {
		broadcastChannelName: "wmcp-agentsnr:manifest-invalidation",
		invalidationStorageKey: "wmcp-agentsnr:manifest-invalidation",
		maxDescriptionChars: 500,
		maxManifestBytes: 262144,
		maxOutputBytes: 32768,
	} );

	const EVENTS = Object.freeze( {
		manifestError: "wmcp:manifest-error",
		manifestInvalidated: "wmcp:manifest-invalidated",
		manifestReady: "wmcp:manifest-ready",
		status: "wmcp:status",
		toolCancelled: "wmcp:tool-cancelled",
		toolError: "wmcp:tool-error",
		toolResult: "wmcp:tool-result",
		toolStart: "wmcp:tool-start",
		uiUpdate: "wmcp:ui-update",
	} );

	const TOOL_NAME_PATTERN = /^[A-Za-z0-9_.-]{1,128}$/;
	const ERROR_CODE_PATTERN = /^[a-z0-9_.-]{1,64}$/;
	const MAX_RECENT_INVALIDATION_NONCES = 64;
	const CART_MUTATION_TOOLS = new Set( [
		"add_to_cart",
		"remove_from_cart",
		"update_cart_quantity",
	] );

	class WebMCPRuntimeError extends Error {
		constructor( code, message, options = {} ) {
			super( message );
			this.name = "WebMCPRuntimeError";
			this.code = code;
			this.retryable = Boolean( options.retryable );
			this.httpStatus = options.httpStatus || null;
			this.cause = options.cause;
		}
	}

	class WebMCPRuntime {
		constructor( config = {}, environment = {} ) {
			this.config = Object.assign( {}, DEFAULTS, config );

			const globalObject = typeof globalThis === "object" ? globalThis : {};
			this.window = environment.window || config.window || globalObject.window || null;
			this.document = environment.document || config.document || globalObject.document || null;
			this.fetch = environment.fetch || config.fetch || globalObject.fetch || null;
			this.AbortController =
				environment.AbortController || config.AbortController || globalObject.AbortController;
			this.CustomEvent = environment.CustomEvent || config.CustomEvent || globalObject.CustomEvent;
			this.Event = environment.Event || config.Event || globalObject.Event;
			this.TextEncoder = environment.TextEncoder || config.TextEncoder || globalObject.TextEncoder;
			this.TextDecoder = environment.TextDecoder || config.TextDecoder || globalObject.TextDecoder;
			this.crypto = environment.crypto || config.crypto || globalObject.crypto || null;
			const timerHost = this.window || globalObject;
			const setTimer = environment.setTimeout || config.setTimeout || globalObject.setTimeout;
			const clearTimer = environment.clearTimeout || config.clearTimeout || globalObject.clearTimeout;
			this.setTimeout = typeof setTimer === "function" ? setTimer.bind( timerHost ) : null;
			this.clearTimeout = typeof clearTimer === "function" ? clearTimer.bind( timerHost ) : null;
			this.BroadcastChannel =
				environment.BroadcastChannel ||
				config.BroadcastChannel ||
				( this.window && this.window.BroadcastChannel ) ||
				null;

			this.registrationController = null;
			this.pendingRegistrationController = null;
			this.manifestFetchController = null;
			this.manifestRevision = null;
			this.activeManifest = null;
			this.activeExecutions = new Map();
			this.credentialRefreshTimer = null;
			this.sessionReady = false;

			this.started = false;
			this.stopped = false;
			this.invalidationChannel = null;
			this.invalidationListenersInstalled = false;
			this.refreshPromise = null;
			this.refreshQueued = false;
			this.refreshReasons = new Set();
			this.recentInvalidationNonces = new Set();

			this.handleInvalidationEvent = this.handleInvalidationEvent.bind( this );
			this.handleBroadcastInvalidation = this.handleBroadcastInvalidation.bind( this );
			this.handleStorageInvalidation = this.handleStorageInvalidation.bind( this );
		}

		async start() {
			this.stopped = false;
			this.setStatus( "detecting" );

			if ( ! this.window || ! this.document ) {
				this.setStatus( "unsupported-environment", { code: "browser_environment_missing" } );
				return null;
			}

			if ( ! this.isTopLevelDocument() ) {
				this.setStatus( "embedded-context" );
				return null;
			}

			if ( typeof this.document.modelContext?.registerTool !== "function" ) {
				this.setStatus( "unsupported-browser" );
				return null;
			}

			if ( typeof this.fetch !== "function" || typeof this.AbortController !== "function" ) {
				const error = new WebMCPRuntimeError(
					"runtime_unavailable",
					"The browser is missing a required WebMCP runtime capability."
				);
				this.reportManifestError( error, { phase: "startup" } );
				throw error;
			}

			this.started = true;
			this.setStatus( "api-detected" );
			this.installInvalidationListeners();

			return this.refreshManifest( { reason: "start" } );
		}

		stop() {
			this.started = false;
			this.stopped = true;

			this.abortController( this.manifestFetchController );
			this.abortController( this.pendingRegistrationController );
			this.abortController( this.registrationController );
			if ( this.credentialRefreshTimer && typeof this.clearTimeout === "function" ) {
				this.clearTimeout( this.credentialRefreshTimer );
			}
			this.credentialRefreshTimer = null;

			this.manifestFetchController = null;
			this.pendingRegistrationController = null;
			this.registrationController = null;
			this.manifestRevision = null;
			this.activeManifest = null;
			this.sessionReady = false;
			this.refreshQueued = false;
			this.refreshReasons.clear();
			this.recentInvalidationNonces.clear();

			this.removeInvalidationListeners();
			this.clearRegistrationDataset();
			this.setStatus( "stopped", { toolCount: 0 } );
		}

		dispose() {
			this.stop();
		}

		isTopLevelDocument() {
			try {
				return this.window.top === this.window.self;
			} catch {
				return false;
			}
		}

		refreshManifest( options = {} ) {
			if ( this.stopped || ! this.started ) {
				return Promise.reject(
					new WebMCPRuntimeError(
						"runtime_not_started",
						"The WebMCP runtime must be started before refreshing its manifest."
					)
				);
			}

			this.refreshQueued = true;
			this.refreshReasons.add( options.reason || "manual" );

			if ( this.refreshPromise ) {
				return this.refreshPromise;
			}

			const promise = this.drainManifestRefreshes();
			this.refreshPromise = promise;

			const clearPromise = () => {
				if ( this.refreshPromise === promise ) {
					this.refreshPromise = null;
				}
			};

			promise.then( clearPromise, clearPromise );
			return promise;
		}

		async whenIdle() {
			while ( this.refreshPromise ) {
				try {
					await this.refreshPromise;
				} catch {
					// Status and manifest-error events already expose refresh failures.
				}
			}
		}

		async drainManifestRefreshes() {
			let manifest = this.activeManifest;

			do {
				this.refreshQueued = false;
				const reason = Array.from( this.refreshReasons ).join( "," ) || "manual";
				this.refreshReasons.clear();

				try {
					manifest = await this.refreshManifestOnce( reason );
				} catch ( error ) {
					if ( ! this.refreshQueued ) {
						throw error;
					}
				}
			} while ( this.refreshQueued );

			return manifest;
		}

		async refreshManifestOnce( reason ) {
			const hadActiveRegistration = Boolean( this.registrationController );
			this.setStatus( hadActiveRegistration ? "refreshing" : "loading-manifest", { reason } );

			let manifest;
			try {
				manifest = await this.fetchManifest();
			} catch ( error ) {
				if ( this.stopped ) {
					throw error;
				}
				this.setStatus( hadActiveRegistration ? "ready-stale" : "error", {
					code: error.code || "manifest_fetch_failed",
					reason,
				} );
				this.reportManifestError( error, { phase: "fetch", reason } );
				throw error;
			}

			if (
				this.registrationController &&
				! this.registrationController.signal.aborted &&
				manifest.manifest_revision === this.manifestRevision
			) {
				// Session and CSRF material may rotate without changing the tool catalog.
				this.activeManifest = manifest;
				this.scheduleCredentialRefresh( manifest );
				this.markReady( manifest, reason, false );
				return manifest;
			}

			return this.applyManifest( manifest, reason );
		}

		async ensureSession( force = false ) {
			const sessionUrl = this.config.sessionUrl || this.config.session_url;
			if ( this.sessionReady && ! force ) {
				return;
			}
			if ( typeof sessionUrl !== "string" || sessionUrl.length === 0 ) {
				this.sessionReady = true;
				return;
			}

			let response;
			try {
				response = await this.fetch( sessionUrl, {
					body: JSON.stringify( { surface: this.config.surface || "storefront" } ),
					cache: "no-store",
					credentials: "same-origin",
					headers: { Accept: "application/json", "Content-Type": "application/json" },
					method: "POST",
				} );
			} catch ( cause ) {
				throw new WebMCPRuntimeError(
					"session_bootstrap_failed",
					"The private demo session could not be started.",
					{ retryable: true, cause }
				);
			}

			if ( ! this.responseIsSuccessful( response ) ) {
				throw new WebMCPRuntimeError(
					"session_bootstrap_failed",
					"The private demo session endpoint returned an error.",
					{ retryable: Number( response.status ) >= 500, httpStatus: response.status }
				);
			}

			this.sessionReady = true;
		}

		async fetchManifest( allowSessionRetry = true ) {
			const manifestUrl = this.config.manifestUrl || this.config.manifest_url;
			if ( typeof manifestUrl !== "string" || manifestUrl.length === 0 ) {
				throw new WebMCPRuntimeError(
					"configuration_error",
					"A dynamic WebMCP manifest URL is required."
				);
			}

			const controller = new this.AbortController();
			this.manifestFetchController = controller;

			try {
				let response;
				try {
					response = await this.fetch( manifestUrl, {
						cache: "no-store",
						credentials: "same-origin",
						headers: Object.assign(
							{ Accept: "application/json" },
							this.config.manifestHeaders || {}
						),
						method: "GET",
						signal: controller.signal,
					} );
				} catch ( cause ) {
					if ( controller.signal.aborted ) {
						throw this.abortError( controller.signal );
					}

					throw new WebMCPRuntimeError(
						"manifest_fetch_failed",
						"The WebMCP manifest could not be loaded.",
						{ retryable: true, cause }
					);
				}

				if ( ! this.responseIsSuccessful( response ) ) {
					if ( Number( response.status ) === 401 && allowSessionRetry ) {
						this.sessionReady = false;
						await this.ensureSession( true );

						return this.fetchManifest( false );
					}
					throw new WebMCPRuntimeError(
						"manifest_fetch_failed",
						"The WebMCP manifest endpoint returned an error.",
						{ retryable: Number( response.status ) >= 500, httpStatus: response.status }
					);
				}

				const value = await this.readJsonResponse(
					response,
					this.config.maxManifestBytes,
					"manifest"
				);

				return this.validateManifest( value );
			} finally {
				if ( this.manifestFetchController === controller ) {
					this.manifestFetchController = null;
				}
			}
		}

		validateManifest( value ) {
			if ( ! this.isRecord( value ) ) {
				throw new WebMCPRuntimeError(
					"manifest_invalid",
					"The WebMCP manifest must be a JSON object."
				);
			}

			if (
				typeof value.manifest_revision !== "string" ||
				value.manifest_revision.length === 0 ||
				value.manifest_revision.length > 256
			) {
				throw new WebMCPRuntimeError(
					"manifest_invalid",
					"The WebMCP manifest revision is missing or invalid."
				);
			}

			if ( ! Array.isArray( value.tools ) ) {
				throw new WebMCPRuntimeError(
					"manifest_invalid",
					"The WebMCP manifest tools member must be an array."
				);
			}

			const names = new Set();
			const tools = value.tools.map( ( tool ) => {
				if ( ! this.isRecord( tool ) || ! TOOL_NAME_PATTERN.test( tool.name || "" ) ) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						"The WebMCP manifest contains an invalid tool name."
					);
				}

				if ( names.has( tool.name ) ) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						"The WebMCP manifest contains a duplicate tool name."
					);
				}
				names.add( tool.name );

				if (
					typeof tool.description !== "string" ||
					tool.description.length === 0 ||
					tool.description.length > this.config.maxDescriptionChars
				) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						`The description for ${ tool.name } is missing or too long.`
					);
				}

				if ( tool.title !== undefined && typeof tool.title !== "string" ) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						`The title for ${ tool.name } is invalid.`
					);
				}

				if (
					tool.inputSchema !== undefined &&
					( ! this.isRecord( tool.inputSchema ) || Array.isArray( tool.inputSchema ) )
				) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						`The input schema for ${ tool.name } is invalid.`
					);
				}

				try {
					JSON.stringify( tool.inputSchema );
				} catch ( cause ) {
					throw new WebMCPRuntimeError(
						"manifest_invalid",
						`The input schema for ${ tool.name } is not serializable.`,
						{ cause }
					);
				}

				return Object.assign( {}, tool );
			} );

			return Object.assign( {}, value, { tools } );
		}

		async applyManifest( manifest, reason ) {
			const previousManifest = this.activeManifest;
			const previousController = this.registrationController;

			this.setStatus( "registering", {
				attemptedRevision: manifest.manifest_revision,
				reason,
				toolCount: manifest.tools.length,
			} );

			// The current API has no updateTool()/unregisterTool(). Aborting the
			// prior registration signal is the standards-defined removal mechanism.
			this.abortController( previousController );
			this.registrationController = null;
			this.manifestRevision = null;
			this.activeManifest = null;
			this.clearRegistrationDataset();

			const candidateController = new this.AbortController();
			this.pendingRegistrationController = candidateController;

			try {
				await this.registerManifestTools( manifest, candidateController );

				if ( candidateController.signal.aborted || this.stopped ) {
					throw new WebMCPRuntimeError(
						"registration_cancelled",
						"WebMCP tool registration was cancelled.",
						{ retryable: true }
					);
				}

				// Commit the revision only after the complete registration batch settles.
				this.registrationController = candidateController;
				this.manifestRevision = manifest.manifest_revision;
				this.activeManifest = manifest;
				this.pendingRegistrationController = null;
				this.markReady( manifest, reason, true );
				return manifest;
			} catch ( cause ) {
				this.abortController( candidateController );
				if ( this.pendingRegistrationController === candidateController ) {
					this.pendingRegistrationController = null;
				}

				const error =
					cause instanceof WebMCPRuntimeError
						? cause
						: new WebMCPRuntimeError(
							"registration_failed",
							"The browser rejected one or more WebMCP tools.",
							{ retryable: true, cause }
						);

				let rollbackError = null;
				let rolledBack = false;

				if ( previousManifest && ! this.stopped ) {
					const rollbackController = new this.AbortController();
					this.pendingRegistrationController = rollbackController;

					try {
						await this.registerManifestTools( previousManifest, rollbackController );

						if ( rollbackController.signal.aborted || this.stopped ) {
							throw new WebMCPRuntimeError(
								"rollback_cancelled",
								"The prior WebMCP registration set could not be restored."
							);
						}

						this.registrationController = rollbackController;
						this.manifestRevision = previousManifest.manifest_revision;
						this.activeManifest = previousManifest;
						rolledBack = true;
					} catch ( rollbackCause ) {
						this.abortController( rollbackController );
						rollbackError = rollbackCause;
					} finally {
						if ( this.pendingRegistrationController === rollbackController ) {
							this.pendingRegistrationController = null;
						}
					}
				}

				if ( this.stopped ) {
					throw error;
				}

				this.setStatus( rolledBack ? "ready-stale" : "error", {
					code: error.code,
					reason,
					revision: rolledBack ? previousManifest.manifest_revision : undefined,
					rolledBack,
					toolCount: rolledBack ? previousManifest.tools.length : 0,
				} );
				this.reportManifestError( error, {
					attemptedRevision: manifest.manifest_revision,
					phase: "registration",
					reason,
					rollbackError: rollbackError
						? this.publicError( rollbackError )
						: null,
					rolledBack,
				} );

				throw error;
			}
		}

		async registerManifestTools( manifest, controller ) {
			const modelContext = this.document.modelContext;
			const registrations = manifest.tools.map( ( tool ) =>
				Promise.resolve().then( () =>
					modelContext.registerTool(
						this.createStandardToolDefinition( manifest, tool ),
						{ signal: controller.signal }
					)
				)
			);

			await Promise.all( registrations );
		}

		createStandardToolDefinition( manifest, tool ) {
			const definition = {
				description: tool.description,
				execute: ( input, options = {} ) =>
					this.executeTool( tool.name, input, options, manifest ),
				name: tool.name,
			};

			if ( tool.title !== undefined ) {
				definition.title = tool.title;
			}
			if ( tool.inputSchema !== undefined ) {
				definition.inputSchema = tool.inputSchema;
			}
			if ( this.isRecord( tool.annotations ) ) {
				const annotations = {};
				if ( typeof tool.annotations.readOnlyHint === "boolean" ) {
					annotations.readOnlyHint = tool.annotations.readOnlyHint;
				}
				if ( typeof tool.annotations.untrustedContentHint === "boolean" ) {
					annotations.untrustedContentHint = tool.annotations.untrustedContentHint;
				}
				definition.annotations = annotations;
			}

			return definition;
		}

		async executeTool( toolName, input = {}, options = {}, registeredManifest = null ) {
			const manifest = this.resolveExecutionManifest( toolName, registeredManifest );
			const tool = manifest?.tools.find( ( candidate ) => candidate.name === toolName );

			if ( ! manifest || ! tool ) {
				return this.errorEnvelope(
					"tool_unavailable",
					"This tool is no longer available. Refresh the site tools and try another action.",
					false
				);
			}

			const ownedController = options.signal ? null : new this.AbortController();
			const signal = options.signal || ownedController.signal;
			const requestId = this.createRequestId();
			const execution = { controller: ownedController, requestId, signal, toolName };
			this.activeExecutions.set( requestId, execution );
			this.updateExecutionCount();
			this.dispatch( EVENTS.toolStart, { requestId, tool: toolName } );

			try {
				if ( signal.aborted ) {
					throw this.abortError( signal );
				}

				const body = {
					input: input || {},
					manifest_revision: manifest.manifest_revision,
					request_id: requestId,
					schema_version: manifest.schema_version || "1.0",
					workflow_id: manifest.workflow_id || null,
				};

				let serializedBody;
				try {
					serializedBody = JSON.stringify( body );
				} catch ( cause ) {
					throw new WebMCPRuntimeError(
						"invalid_input",
						"The tool input is not JSON-serializable.",
						{ cause }
					);
				}

				let requestOptions = {
					body: serializedBody,
					cache: "no-store",
					credentials: "same-origin",
					headers: this.executionHeaders( manifest ),
					method: "POST",
					signal,
				};

				const maxOutputBytes = this.outputLimitForTool( tool );
				let effectiveManifest = manifest;
				let delivery = await this.sendToolRequest(
					this.executionUrl( toolName ),
					requestOptions,
					maxOutputBytes,
					signal
				);
				let ambiguityObserved = delivery.replayedAfterAmbiguity;
				let envelope = this.responseIsSuccessful( delivery.response )
					? this.normalizeSuccessfulResponse( delivery.responseBody, effectiveManifest )
					: this.mapHttpError( delivery.response, delivery.responseBody, effectiveManifest );

				if (
					ambiguityObserved &&
					envelope.ok === false &&
					envelope.error.code !== "csrf_invalid"
				) {
					throw this.outcomeUnconfirmedError();
				}

				if ( envelope.ok === false && envelope.error.code === "csrf_invalid" ) {
					let refreshed = false;
					try {
						await this.refreshManifest( { reason: "credentials_expired" } );
						refreshed = ! signal.aborted;
					} catch {
						// Preserve the original credential error when refresh fails.
					}

					if ( refreshed ) {
						effectiveManifest = this.activeManifest || manifest;
						requestOptions = Object.assign( {}, requestOptions, {
							headers: this.executionHeaders( effectiveManifest ),
						} );
						delivery = await this.sendToolRequest(
							this.executionUrl( toolName ),
							requestOptions,
							maxOutputBytes,
							signal
						);
						ambiguityObserved = ambiguityObserved || delivery.replayedAfterAmbiguity;
						envelope = this.responseIsSuccessful( delivery.response )
							? this.normalizeSuccessfulResponse( delivery.responseBody, effectiveManifest )
							: this.mapHttpError( delivery.response, delivery.responseBody, effectiveManifest );
					}

					if ( ambiguityObserved && envelope.ok === false ) {
						throw this.outcomeUnconfirmedError();
					}
				}

				if ( envelope.ok === false ) {
					if ( envelope.error.code === "manifest_stale" ) {
						try {
							await this.invalidate( "manifest_stale", { broadcast: true } );
						} catch {
							// The original recoverable server error remains the tool result.
						}
					}

					this.dispatch( EVENTS.toolError, {
						requestId,
						response: envelope,
						tool: toolName,
					} );
					return envelope;
				}

				this.dispatch( EVENTS.toolResult, {
					requestId,
					response: envelope,
					tool: toolName,
				} );

				if ( this.isRecord( envelope.ui ) ) {
					this.dispatch( EVENTS.uiUpdate, {
						requestId,
						response: envelope,
						tool: toolName,
						ui: envelope.ui,
					} );
				}

				if ( this.toolResultInvalidatesManifest( toolName, envelope, effectiveManifest ) ) {
					try {
						const refresh = this.invalidate( "tool_result", {
							broadcast: true,
							surface: toolName === "set_tool_enabled" ? null : undefined,
						} );
						if ( CART_MUTATION_TOOLS.has( toolName ) ) {
							refresh.catch( () => {} );
						} else {
							await refresh;
						}
					} catch {
						// The server action succeeded. Do not turn it into a replay-prone failure.
					}
				}

				return envelope;
			} catch ( error ) {
				if ( signal.aborted ) {
					const clientStop = this.abortError( signal );
					this.dispatch( EVENTS.toolCancelled, {
						clientStoppedWaiting: true,
						message: clientStop.message,
						outcomeMayComplete: true,
						requestId,
						tool: toolName,
					} );
					throw clientStop;
				}

				const envelope = this.exceptionEnvelope( error, manifest );
				this.dispatch( EVENTS.toolError, {
					requestId,
					response: envelope,
					tool: toolName,
				} );
				return envelope;
			} finally {
				this.activeExecutions.delete( requestId );
				this.updateExecutionCount();
			}
		}

		async sendToolRequest( url, requestOptions, maximumBytes, signal ) {
			let replayedAfterAmbiguity = false;
			for ( let attempt = 0; attempt < 2; attempt++ ) {
				if ( signal.aborted ) {
					throw this.abortError( signal );
				}

				let response;
				try {
					response = await this.fetch( url, requestOptions );
				} catch ( cause ) {
					if ( signal.aborted ) {
						throw this.abortError( signal );
					}

					if ( 0 === attempt ) {
						replayedAfterAmbiguity = true;
						continue;
					}

					throw this.outcomeUnconfirmedError( cause );
				}

				try {
					const responseBody = await this.readJsonResponse(
						response,
						maximumBytes,
						"tool"
					);
					if (
						this.responseIsSuccessful( response ) &&
						( ! this.isRecord( responseBody ) || Array.isArray( responseBody ) )
					) {
						if ( 0 === attempt ) {
							replayedAfterAmbiguity = true;
							continue;
						}

						throw this.outcomeUnconfirmedError();
					}

					return { response, responseBody, replayedAfterAmbiguity };
				} catch ( cause ) {
					if ( signal.aborted ) {
						throw this.abortError( signal );
					}

					// An unreadable tool response is outcome-ambiguous even when an HTTP
					// status arrived: post-write finalization can legitimately return 409/500.
					if ( 0 === attempt ) {
						replayedAfterAmbiguity = true;
						continue;
					}

					throw this.outcomeUnconfirmedError( cause );
				}
			}

			throw this.outcomeUnconfirmedError();
		}

		outcomeUnconfirmedError( cause = null ) {
			return new WebMCPRuntimeError(
				"outcome_unconfirmed",
				"The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
				{ cause, retryable: false }
			);
		}

		resolveExecutionManifest( toolName, registeredManifest ) {
			if (
				this.activeManifest?.tools.some( ( tool ) => tool.name === toolName )
			) {
				return this.activeManifest;
			}

			return registeredManifest;
		}

		executionUrl( toolName ) {
			const template = this.config.executionUrlTemplate || this.config.execution_url_template;
			if ( typeof template === "string" && template.includes( "{tool_name}" ) ) {
				return template.replace( "{tool_name}", encodeURIComponent( toolName ) );
			}

			const base =
				this.config.executionBaseUrl ||
				this.config.execution_base_url ||
				this.config.restBaseUrl ||
				this.config.rest_base_url;

			if ( typeof base !== "string" || base.length === 0 ) {
				throw new WebMCPRuntimeError(
					"configuration_error",
					"A WebMCP tool execution URL is required."
				);
			}

			return `${ base.replace( /\/+$/, "" ) }/tools/${ encodeURIComponent( toolName ) }`;
		}

		executionHeaders( manifest ) {
			const headers = Object.assign(
				{
					Accept: "application/json",
					"Content-Type": "application/json",
				},
				this.config.executionHeaders || {}
			);
			const session = this.isRecord( manifest.session ) ? manifest.session : {};
			const csrfToken = session.csrf_token || session.csrfToken;
			const wpNonce = session.wp_nonce || session.wpNonce || this.config.wpNonce;

			if ( csrfToken ) {
				headers[ "X-WMCP-CSRF" ] = csrfToken;
			}
			if ( wpNonce ) {
				headers[ "X-WP-Nonce" ] = wpNonce;
			}
			if ( this.window?.location?.href ) {
				headers[ "X-WMCP-Page-URL" ] = String( this.window.location.href ).slice( 0, 2048 );
			}

			return headers;
		}

		outputLimitForTool( tool ) {
			const configuredLimit = this.positiveInteger(
				this.config.maxOutputBytes,
				DEFAULTS.maxOutputBytes
			);
			const toolLimit = this.positiveInteger(
				tool.max_output_bytes || tool.maxOutputBytes,
				configuredLimit
			);

			return Math.min( configuredLimit, toolLimit );
		}

		normalizeSuccessfulResponse( value, manifest ) {
			if ( value === null ) {
				return this.errorEnvelope(
					"outcome_unconfirmed",
					"The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
					false,
					manifest
				);
			}
			if ( ! this.isRecord( value ) || Array.isArray( value ) ) {
				return this.errorEnvelope(
					"invalid_response",
					"The tool endpoint returned an invalid response envelope.",
					true,
					manifest
				);
			}

			if ( value.ok === false ) {
				return this.normalizeServerError( value, 200, manifest );
			}

			return value;
		}

		mapHttpError( response, value, manifest ) {
			return this.normalizeServerError( value, Number( response.status ) || 500, manifest, response );
		}

		normalizeServerError( value, status, manifest, response = null ) {
			const defaults = {
				400: [ "invalid_request", "The tool request was invalid.", false ],
				401: [ "authentication_required", "Authentication is required for this tool.", false ],
				403: [ "forbidden", "This tool request is not permitted.", false ],
				404: [ "tool_unavailable", "This tool is not available.", false ],
				409: [ "conflict", "The site state changed. Refresh the tools and retry.", true ],
				413: [ "output_too_large", "The tool response exceeded the allowed size.", false ],
				429: [ "rate_limited", "Too many tool requests were made. Try again later.", true ],
				500: [ "internal_error", "The tool could not be completed.", true ],
				503: [ "service_unavailable", "The tool service is temporarily unavailable.", true ],
			};
			const fallback = defaults[ status ] || ( status >= 500 ? defaults[ 500 ] : [
				"request_failed",
				"The tool request could not be completed.",
				false,
			] );
			const record = this.isRecord( value ) ? value : {};
			const serverError = this.isRecord( record.error ) ? record.error : record;
			const serverCode = typeof serverError.code === "string" ? serverError.code : "";
			const code = ERROR_CODE_PATTERN.test( serverCode ) ? serverCode : fallback[ 0 ];
			const serverMessage =
				typeof serverError.message === "string" && serverError.message.length <= 500
					? serverError.message
					: fallback[ 1 ];
			const retryable =
				typeof serverError.retryable === "boolean"
					? serverError.retryable
					: fallback[ 2 ];
			const envelope = this.errorEnvelope( code, serverMessage, retryable, manifest );

			if (
				typeof serverError.recovery === "string" &&
				serverError.recovery.length <= 500
			) {
				envelope.error.recovery = serverError.recovery;
			}

			const retryAfter = response?.headers?.get?.( "Retry-After" );
			if ( retryAfter ) {
				envelope.error.retry_after = String( retryAfter ).slice( 0, 64 );
			}

			for ( const key of [ "event_id", "workflow_id", "manifest_revision" ] ) {
				if ( typeof record[ key ] === "string" ) {
					envelope[ key ] = record[ key ];
				}
			}

			return envelope;
		}

		exceptionEnvelope( error, manifest ) {
			if ( error instanceof WebMCPRuntimeError ) {
				return this.errorEnvelope(
					error.code,
					error.message,
					error.retryable,
					manifest
				);
			}

			return this.errorEnvelope(
				"network_error",
				"The tool endpoint could not be reached.",
				true,
				manifest
			);
		}

		errorEnvelope( code, message, retryable, manifest = null ) {
			const envelope = {
				error: { code, message, retryable: Boolean( retryable ) },
				ok: false,
			};

			if ( manifest?.workflow_id ) {
				envelope.workflow_id = manifest.workflow_id;
			}
			if ( manifest?.manifest_revision ) {
				envelope.manifest_revision = manifest.manifest_revision;
			}

			return envelope;
		}

		toolResultInvalidatesManifest( toolName, envelope, manifest ) {
			if (
				toolName === "set_tool_enabled" ||
				CART_MUTATION_TOOLS.has( toolName )
			) {
				return true;
			}

			const result = this.isRecord( envelope.result ) ? envelope.result : {};
			const nextRevision =
				envelope.manifest_revision ||
				result.manifest_revision ||
				result.effective_manifest_revision;

			return Boolean( nextRevision && nextRevision !== manifest.manifest_revision );
		}

		async invalidate( reason = "explicit", options = {} ) {
			if ( options.broadcast !== false ) {
				this.broadcastInvalidation( reason, options.surface );
			}

			return this.refreshManifest( { reason } );
		}

		installInvalidationListeners() {
			if ( this.invalidationListenersInstalled || ! this.window ) {
				return;
			}

			if ( typeof this.window.addEventListener === "function" ) {
				this.window.addEventListener( EVENTS.manifestInvalidated, this.handleInvalidationEvent );
				this.window.addEventListener( "storage", this.handleStorageInvalidation );
			}

			if ( this.BroadcastChannel && this.config.broadcastChannelName ) {
				try {
					this.invalidationChannel = new this.BroadcastChannel(
						this.config.broadcastChannelName
					);
					this.invalidationChannel.addEventListener(
						"message",
						this.handleBroadcastInvalidation
					);
				} catch {
					this.invalidationChannel = null;
				}
			}

			this.invalidationListenersInstalled = true;
		}

		removeInvalidationListeners() {
			if ( ! this.invalidationListenersInstalled || ! this.window ) {
				return;
			}

			if ( typeof this.window.removeEventListener === "function" ) {
				this.window.removeEventListener(
					EVENTS.manifestInvalidated,
					this.handleInvalidationEvent
				);
				this.window.removeEventListener( "storage", this.handleStorageInvalidation );
			}

			if ( this.invalidationChannel ) {
				this.invalidationChannel.removeEventListener?.(
					"message",
					this.handleBroadcastInvalidation
				);
				this.invalidationChannel.close?.();
				this.invalidationChannel = null;
			}

			this.invalidationListenersInstalled = false;
		}

		handleInvalidationEvent( event ) {
			const detail = this.isRecord( event?.detail ) ? event.detail : {};
			this.invalidate( detail.reason || "browser_event", {
				broadcast: detail.broadcast !== false,
				surface: Object.hasOwn( detail, "surface" ) ? detail.surface : undefined,
			} ).catch( () => {} );
		}

		handleBroadcastInvalidation( event ) {
			if ( ! this.acceptInvalidationMessage( event?.data ) ) {
				return;
			}

			this.invalidate( event.data.reason || "broadcast", { broadcast: false } ).catch(
				() => {}
			);
		}

		handleStorageInvalidation( event ) {
			if ( event?.key !== this.config.invalidationStorageKey || ! event.newValue ) {
				return;
			}

			let message;
			try {
				message = JSON.parse( event.newValue );
			} catch {
				return;
			}

			if ( ! this.acceptInvalidationMessage( message ) ) {
				return;
			}

			this.invalidate( message.reason || "storage", { broadcast: false } ).catch(
				() => {}
			);
		}

		invalidationMessageMatches( message ) {
			if ( ! this.isRecord( message ) || message.type !== EVENTS.manifestInvalidated ) {
				return false;
			}

			const siteId = this.config.siteId || this.activeManifest?.site_id;
			const surface = this.config.surface || this.activeManifest?.surface;

			if ( message.site_id && siteId && message.site_id !== siteId ) {
				return false;
			}
			if ( message.surface && surface && message.surface !== surface ) {
				return false;
			}

			return true;
		}

		acceptInvalidationMessage( message ) {
			if ( ! this.invalidationMessageMatches( message ) ) {
				return false;
			}

			const nonce = message.nonce;
			if ( typeof nonce !== "string" || nonce.length === 0 || nonce.length > 256 ) {
				return true;
			}
			if ( this.recentInvalidationNonces.has( nonce ) ) {
				return false;
			}

			this.recentInvalidationNonces.add( nonce );
			if ( this.recentInvalidationNonces.size > MAX_RECENT_INVALIDATION_NONCES ) {
				const oldest = this.recentInvalidationNonces.values().next().value;
				this.recentInvalidationNonces.delete( oldest );
			}

			return true;
		}

		broadcastInvalidation( reason, surfaceOverride = undefined ) {
			const surface =
				surfaceOverride === null
					? null
					: surfaceOverride || this.config.surface || this.activeManifest?.surface || null;
			const message = {
				nonce: this.createRequestId(),
				reason,
				site_id: this.config.siteId || this.activeManifest?.site_id || null,
				surface,
				timestamp: Date.now(),
				type: EVENTS.manifestInvalidated,
			};

			try {
				this.invalidationChannel?.postMessage( message );
			} catch {
				// Cross-tab invalidation is best-effort; the local refresh still runs.
			}

			try {
				this.window?.localStorage?.setItem(
					this.config.invalidationStorageKey,
					JSON.stringify( message )
				);
			} catch {
				// Storage can be disabled in privacy modes; BroadcastChannel may still work.
			}
		}

		markReady( manifest, reason, registered ) {
			this.scheduleCredentialRefresh( manifest );
			this.setStatus( "ready", {
				reason,
				registered,
				revision: manifest.manifest_revision,
				toolCount: manifest.tools.length,
			} );
			this.dispatch( EVENTS.manifestReady, manifest );
		}

		scheduleCredentialRefresh( manifest ) {
			if ( this.credentialRefreshTimer && typeof this.clearTimeout === "function" ) {
				this.clearTimeout( this.credentialRefreshTimer );
				this.credentialRefreshTimer = null;
			}

			const expiresAt = Date.parse( manifest?.session?.expires_at || "" );
			if ( ! Number.isFinite( expiresAt ) || typeof this.setTimeout !== "function" ) {
				return;
			}

			const delay = Math.max( 1000, expiresAt - Date.now() - 60000 );
			this.credentialRefreshTimer = this.setTimeout( () => {
				this.credentialRefreshTimer = null;
				if ( this.started && ! this.stopped ) {
					this.refreshManifest( { reason: "credentials_expiring" } ).catch( () => {} );
				}
			}, delay );
		}

		setStatus( status, detail = {} ) {
			const dataset = this.document?.documentElement?.dataset;
			if ( dataset ) {
				dataset.wmcpStatus = status;
				if ( detail.revision ) {
					dataset.wmcpManifestRevision = String( detail.revision );
				}
				if ( Number.isInteger( detail.toolCount ) ) {
					dataset.wmcpToolCount = String( detail.toolCount );
				}
				if ( detail.code ) {
					dataset.wmcpErrorCode = String( detail.code );
				} else if ( status === "ready" ) {
					delete dataset.wmcpErrorCode;
				}
			}

			this.dispatch( EVENTS.status, Object.assign( { status }, detail ) );
		}

		updateExecutionCount() {
			const dataset = this.document?.documentElement?.dataset;
			if ( dataset ) {
				dataset.wmcpActiveExecutions = String( this.activeExecutions.size );
			}
		}

		clearRegistrationDataset() {
			const dataset = this.document?.documentElement?.dataset;
			if ( dataset ) {
				delete dataset.wmcpManifestRevision;
				delete dataset.wmcpToolCount;
			}
		}

		reportManifestError( error, detail = {} ) {
			this.dispatch(
				EVENTS.manifestError,
				Object.assign( { error: this.publicError( error ) }, detail )
			);
		}

		publicError( error ) {
			return {
				code: error?.code || "runtime_error",
				message: error?.message || "The WebMCP runtime encountered an error.",
				name: error?.name || "Error",
				retryable: Boolean( error?.retryable ),
			};
		}

		dispatch( type, detail ) {
			if ( typeof this.window?.dispatchEvent !== "function" ) {
				return;
			}

			let event;
			try {
				if ( typeof this.CustomEvent === "function" ) {
					event = new this.CustomEvent( type, { detail } );
				} else if ( typeof this.Event === "function" ) {
					event = new this.Event( type );
					Object.defineProperty( event, "detail", { value: detail } );
				} else {
					return;
				}
				this.window.dispatchEvent( event );
			} catch {
				// Visible UI events must never break tool registration or execution.
			}
		}

		async readJsonResponse( response, maximumBytes, context ) {
			const text = await this.readResponseText( response, maximumBytes, context );
			if ( text.length === 0 ) {
				return null;
			}

			try {
				return JSON.parse( text );
			} catch ( cause ) {
				throw new WebMCPRuntimeError(
					context === "manifest" ? "manifest_invalid" : "invalid_response",
					context === "manifest"
						? "The WebMCP manifest endpoint returned invalid JSON."
						: "The tool endpoint returned invalid JSON.",
					{ retryable: true, cause }
				);
			}
		}

		async readResponseText( response, maximumBytes, context ) {
			const limit = this.positiveInteger(
				maximumBytes,
				context === "manifest" ? DEFAULTS.maxManifestBytes : DEFAULTS.maxOutputBytes
			);
			const contentLength = Number( response?.headers?.get?.( "Content-Length" ) );

			if ( Number.isFinite( contentLength ) && contentLength > limit ) {
				try {
					await response.body?.cancel?.();
				} catch {
					// The size error below is the actionable result.
				}
				throw this.sizeError( context );
			}

			if (
				response?.body?.getReader &&
				typeof this.TextDecoder === "function"
			) {
				const reader = response.body.getReader();
				const chunks = [];
				let total = 0;

				while ( true ) {
					const result = await reader.read();
					if ( result.done ) {
						break;
					}
					const chunk = result.value;
					total += chunk.byteLength;
					if ( total > limit ) {
						await reader.cancel?.();
						throw this.sizeError( context );
					}
					chunks.push( chunk );
				}

				const bytes = new Uint8Array( total );
				let offset = 0;
				for ( const chunk of chunks ) {
					bytes.set( chunk, offset );
					offset += chunk.byteLength;
				}

				return new this.TextDecoder().decode( bytes );
			}

			const text = await response.text();
			if ( this.byteLength( text ) > limit ) {
				throw this.sizeError( context );
			}

			return text;
		}

		sizeError( context ) {
			return new WebMCPRuntimeError(
				context === "manifest" ? "manifest_too_large" : "output_too_large",
				context === "manifest"
					? "The WebMCP manifest exceeded the allowed size."
					: "The tool response exceeded the allowed size.",
				{ httpStatus: 413 }
			);
		}

		byteLength( value ) {
			if ( typeof this.TextEncoder === "function" ) {
				return new this.TextEncoder().encode( value ).byteLength;
			}

			return unescape( encodeURIComponent( value ) ).length;
		}

		responseIsSuccessful( response ) {
			if ( typeof response?.ok === "boolean" ) {
				return response.ok;
			}

			const status = Number( response?.status );
			return status >= 200 && status < 300;
		}

		createRequestId() {
			if ( typeof this.config.requestIdFactory === "function" ) {
				return String( this.config.requestIdFactory() );
			}
			if ( typeof this.crypto?.randomUUID === "function" ) {
				return `req_${ this.crypto.randomUUID().replace( /-/g, "" ) }`;
			}

			return `req_${ Date.now().toString( 36 ) }_${ Math.random()
				.toString( 36 )
				.slice( 2, 14 ) }`;
		}

		abortError( signal ) {
			const error = new Error(
				"The client stopped waiting for the tool response. The server may still complete the request."
			);
			error.name = "AbortError";
			error.code = "client_stopped_waiting";
			error.outcomeMayComplete = true;
			if ( signal?.reason !== undefined ) {
				error.cause = signal.reason;
			}
			return error;
		}

		abortController( controller ) {
			if ( controller && ! controller.signal.aborted ) {
				controller.abort();
			}
		}

		positiveInteger( value, fallback ) {
			const number = Number( value );
			return Number.isSafeInteger( number ) && number > 0 ? number : fallback;
		}

		isRecord( value ) {
			return value !== null && typeof value === "object";
		}
	}

	function discoverConfig( browserWindow, browserDocument ) {
		const root = browserDocument?.querySelector?.( "[data-wmcp-surface]" );
		if ( ! root ) {
			return null;
		}

		const declaredSurface = root.dataset?.wmcpSurface;
		const surface = declaredSurface === "agentsnr" ? "agentsnr" : "storefront";
		const apiLink = browserDocument.querySelector?.(
			'link[rel="https://api.w.org/"]'
		)?.href;
		const fallbackRoot = browserWindow?.location?.origin
			? `${ browserWindow.location.origin }/wp-json/`
			: null;
		const apiRoot = apiLink || fallbackRoot;

		if ( ! apiRoot ) {
			return null;
		}

		try {
			const namespace = new URL( "wmcp-agentsnr/v1/", apiRoot ).toString();

			return {
				autoStart: [ "storefront", "agentsnr" ].includes( declaredSurface ),
				executionBaseUrl: namespace.replace( /\/$/, "" ),
				healthUrl: `${ namespace }health`,
				manifestUrl: `${ namespace }manifest?surface=${ encodeURIComponent( surface ) }`,
				resetUrl: `${ namespace }demo/reset`,
				sessionUrl: `${ namespace }session`,
				surface,
			};
		} catch {
			return null;
		}
	}

	function bootstrap( targetWindow = null ) {
		const browserWindow =
			targetWindow || ( typeof window === "object" ? window : null );
		const browserDocument = browserWindow?.document;
		const config =
			browserWindow?.wmcpConfig ||
			discoverConfig( browserWindow, browserDocument );

		if ( browserWindow && config ) {
			browserWindow.wmcpConfig = config;
		}
		if ( ! browserWindow || ! browserDocument || ! config || config.autoStart === false ) {
			return null;
		}
		if ( browserWindow.wmcpRuntime instanceof WebMCPRuntime ) {
			return browserWindow.wmcpRuntime;
		}

		const runtime = new WebMCPRuntime( config, {
			document: browserDocument,
			fetch: browserWindow.fetch?.bind( browserWindow ),
			window: browserWindow,
		} );
		browserWindow.wmcpRuntime = runtime;

		const start = () => runtime.start().catch( () => {} );
		if ( browserDocument.readyState === "loading" ) {
			browserDocument.addEventListener( "DOMContentLoaded", start, { once: true } );
		} else {
			Promise.resolve().then( start );
		}

		return runtime;
	}

	const api = {
		DEFAULTS,
		EVENTS,
		WebMCPRuntime,
		WebMCPRuntimeError,
		bootstrap,
		discoverConfig,
	};

	if ( typeof module === "object" && module.exports ) {
		module.exports = api;
	}

	if ( typeof window === "object" ) {
		window.WMCPAgentSNR = Object.assign( {}, window.WMCPAgentSNR || {}, api );
		bootstrap( window );
	}
}() );
