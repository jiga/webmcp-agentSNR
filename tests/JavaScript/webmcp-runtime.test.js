"use strict";

const test = require( "node:test" );
const assert = require( "node:assert/strict" );

const {
	EVENTS,
	WebMCPRuntime,
} = require( "../../plugin/wmcp-agentops/assets/js/webmcp-runtime.js" );

class MockCustomEvent {
	constructor( type, options = {} ) {
		this.type = type;
		this.detail = options.detail;
	}
}

class MockWindow {
	constructor() {
		this.listeners = new Map();
		this.localStorageWrites = [];
		this.localStorage = {
			setItem: ( key, value ) => {
				this.localStorageWrites.push( { key, value } );
			},
		};
		this.self = this;
		this.top = this;
	}

	addEventListener( type, listener ) {
		const listeners = this.listeners.get( type ) || new Set();
		listeners.add( listener );
		this.listeners.set( type, listeners );
	}

	removeEventListener( type, listener ) {
		this.listeners.get( type )?.delete( listener );
	}

	dispatchEvent( event ) {
		for ( const listener of this.listeners.get( event.type ) || [] ) {
			listener.call( this, event );
		}
		return true;
	}
}

class MockBroadcastChannel {
	constructor( name ) {
		this.name = name;
		this.listeners = new Map();
		this.messages = [];
		this.closed = false;
	}

	addEventListener( type, listener ) {
		const listeners = this.listeners.get( type ) || new Set();
		listeners.add( listener );
		this.listeners.set( type, listeners );
	}

	removeEventListener( type, listener ) {
		this.listeners.get( type )?.delete( listener );
	}

	postMessage( message ) {
		this.messages.push( message );
	}

	emit( message ) {
		for ( const listener of this.listeners.get( "message" ) || [] ) {
			listener( { data: message } );
		}
	}

	close() {
		this.closed = true;
	}
}

class MockModelContext {
	constructor() {
		this.active = new Map();
		this.calls = [];
		this.onRegister = null;
	}

	registerTool( definition, options = {} ) {
		const signal = options.signal;
		const call = { definition, options, signal };
		this.calls.push( call );

		if ( signal?.aborted ) {
			return Promise.reject( signal.reason || abortError() );
		}
		if ( this.active.has( definition.name ) ) {
			const error = new Error( `Duplicate tool: ${ definition.name }` );
			error.name = "InvalidStateError";
			return Promise.reject( error );
		}

		const registration = { definition, signal };
		this.active.set( definition.name, registration );
		signal?.addEventListener(
			"abort",
			() => {
				if ( this.active.get( definition.name ) === registration ) {
					this.active.delete( definition.name );
				}
			},
			{ once: true }
		);

		try {
			return Promise.resolve( this.onRegister?.( call ) );
		} catch ( error ) {
			return Promise.reject( error );
		}
	}
}

function abortError() {
	const error = new Error( "Aborted" );
	error.name = "AbortError";
	return error;
}

function deferred() {
	let resolve;
	let reject;
	const promise = new Promise( ( promiseResolve, promiseReject ) => {
		resolve = promiseResolve;
		reject = promiseReject;
	} );
	return { promise, reject, resolve };
}

function jsonResponse( value, options = {} ) {
	const status = options.status ?? 200;
	const text = options.rawText ?? JSON.stringify( value );
	const headers = new Map(
		Object.entries( options.headers || {} ).map( ( [ key, headerValue ] ) => [
			key.toLowerCase(),
			String( headerValue ),
		] )
	);

	return {
		body: options.body || null,
		headers: {
			get: ( name ) => headers.get( String( name ).toLowerCase() ) ?? null,
		},
		ok: status >= 200 && status < 300,
		status,
		text: async () => text,
	};
}

function manifest( revision, toolNames = [ "search_products" ], overrides = {} ) {
	return Object.assign(
		{
			manifest_revision: revision,
			schema_version: "1.0",
			session: { csrf_token: `csrf-${ revision }` },
			site_id: "site_test",
			surface: "storefront",
			tools: toolNames.map( ( name ) => ( {
				annotations: {
					readOnlyHint: true,
					serverOnlyAnnotation: "must-not-leak",
					untrustedContentHint: false,
				},
				description: `Run ${ name } using structured public data.`,
				inputSchema: {
					additionalProperties: false,
					properties: {},
					type: "object",
				},
				name,
				risk_class: "read",
				title: name.replaceAll( "_", " " ),
				version: "1.0.0",
			} ) ),
			workflow_id: "workflow_test",
		},
		overrides
	);
}

function fetchQueue( responses = [] ) {
	const calls = [];
	const queue = Array.from( responses );
	const fetch = async ( url, options = {} ) => {
		calls.push( { options, url } );
		if ( queue.length === 0 ) {
			throw new Error( `No queued response for ${ options.method || "GET" } ${ url }` );
		}

		const next = queue.shift();
		return typeof next === "function" ? next( url, options ) : next;
	};
	fetch.calls = calls;
	fetch.queue = queue;
	return fetch;
}

function runtimeHarness( options = {} ) {
	const modelContext = options.modelContext || new MockModelContext();
	const window = options.window || new MockWindow();
	const document = options.document || {
		documentElement: { dataset: {} },
		modelContext,
		readyState: "complete",
	};
	window.document = document;

	let nextRequestId = 0;
	const runtime = new WebMCPRuntime(
		Object.assign(
			{
				broadcastChannelName: "runtime-tests",
				executionBaseUrl: "/wp-json/wmcp-agentops/v1",
				invalidationStorageKey: "runtime-tests:manifest",
				manifestUrl: "/wp-json/wmcp-agentops/v1/manifest?surface=storefront",
				requestIdFactory: () => `req_test_${ ++nextRequestId }`,
				siteId: "site_test",
				surface: "storefront",
			},
			options.config || {}
		),
		{
			AbortController,
			BroadcastChannel: options.BroadcastChannel || MockBroadcastChannel,
			clearTimeout: options.clearTimeout,
			CustomEvent: MockCustomEvent,
			document,
			fetch: options.fetch,
			setTimeout: options.setTimeout,
			TextDecoder,
			TextEncoder,
			window,
		}
	);

	return { document, modelContext, runtime, window };
}

async function waitFor( predicate, message = "condition was not met" ) {
	for ( let attempt = 0; attempt < 50; attempt++ ) {
		if ( predicate() ) {
			return;
		}
		await new Promise( ( resolve ) => setImmediate( resolve ) );
	}
	assert.fail( message );
}

test( "start skips iframe and unsupported browser contexts without fetching", async ( t ) => {
	await t.test( "embedded document", async () => {
		const fetch = fetchQueue();
		const harness = runtimeHarness( { fetch } );
		harness.window.top = {};

		assert.equal( await harness.runtime.start(), null );
		assert.equal( harness.document.documentElement.dataset.wmcpStatus, "embedded-context" );
		assert.equal( fetch.calls.length, 0 );
		assert.equal( harness.modelContext.calls.length, 0 );
	} );

	await t.test( "missing imperative API", async () => {
		const fetch = fetchQueue();
		const harness = runtimeHarness( { fetch } );
		harness.document.modelContext = {};

		assert.equal( await harness.runtime.start(), null );
		assert.equal( harness.document.documentElement.dataset.wmcpStatus, "unsupported-browser" );
		assert.equal( fetch.calls.length, 0 );
	} );
} );

test( "manifest fetch is no-store and the complete registration batch is awaited", async () => {
	const firstRegistration = deferred();
	const secondRegistration = deferred();
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "search_products", "get_product" ] ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	harness.modelContext.onRegister = ( call ) =>
		call.definition.name === "search_products"
			? firstRegistration.promise
			: secondRegistration.promise;

	const statuses = [];
	const readyEvents = [];
	harness.window.addEventListener( EVENTS.status, ( event ) => statuses.push( event.detail ) );
	harness.window.addEventListener( EVENTS.manifestReady, ( event ) =>
		readyEvents.push( event.detail )
	);

	const startPromise = harness.runtime.start();
	await waitFor( () => harness.modelContext.calls.length === 2 );

	assert.equal( harness.runtime.manifestRevision, null );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "registering" );
	assert.equal( fetch.calls[ 0 ].options.method, "GET" );
	assert.equal( fetch.calls[ 0 ].options.cache, "no-store" );
	assert.equal( fetch.calls[ 0 ].options.credentials, "same-origin" );
	assert.equal( fetch.calls[ 0 ].options.headers.Accept, "application/json" );
	assert.ok( fetch.calls[ 0 ].options.signal instanceof AbortSignal );

	firstRegistration.resolve();
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( harness.runtime.manifestRevision, null );

	secondRegistration.resolve();
	await startPromise;

	assert.equal( harness.runtime.manifestRevision, "rev_1" );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "ready" );
	assert.equal( harness.document.documentElement.dataset.wmcpToolCount, "2" );
	assert.equal( readyEvents.length, 1 );
	assert.equal( readyEvents[ 0 ].manifest_revision, "rev_1" );
	assert.ok( statuses.some( ( status ) => status.status === "api-detected" ) );
	assert.ok( statuses.some( ( status ) => status.status === "ready" ) );

	for ( const call of harness.modelContext.calls ) {
		assert.deepEqual( Object.keys( call.definition ).sort(), [
			"annotations",
			"description",
			"execute",
			"inputSchema",
			"name",
			"title",
		] );
		assert.deepEqual( call.definition.annotations, {
			readOnlyHint: true,
			untrustedContentHint: false,
		} );
		assert.deepEqual( Object.keys( call.options ), [ "signal" ] );
	}
} );

test( "a valid server session loads the manifest without a redundant bootstrap POST", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
	] );
	const harness = runtimeHarness( {
		config: { sessionUrl: "/wp-json/wmcp-agentops/v1/session" },
		fetch,
	} );

	await harness.runtime.start();

	assert.equal( fetch.calls.length, 1 );
	assert.equal( fetch.calls[ 0 ].options.method, "GET" );
} );

test( "a missing server session bootstraps only after manifest 401 and retries", async () => {
	const fetch = fetchQueue( [
		jsonResponse( {
			error: { code: "session_required" },
			ok: false,
		}, { status: 401 } ),
		jsonResponse( { ok: true } ),
		jsonResponse( manifest( "rev_1" ) ),
	] );
	const harness = runtimeHarness( {
		config: { sessionUrl: "/wp-json/wmcp-agentops/v1/session" },
		fetch,
	} );

	await harness.runtime.start();

	assert.deepEqual(
		fetch.calls.map( ( call ) => call.options.method ),
		[ "GET", "POST", "GET" ]
	);
	assert.equal( fetch.calls[ 1 ].url, "/wp-json/wmcp-agentops/v1/session" );
} );

test( "partial registration failure rolls back and remains retryable", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "search_products" ] ) ),
		jsonResponse( manifest( "rev_2", [ "search_products", "compare_products" ] ) ),
		jsonResponse( manifest( "rev_2", [ "search_products", "compare_products" ] ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	const manifestErrors = [];
	let failComparisonOnce = true;
	harness.modelContext.onRegister = ( call ) => {
		if ( call.definition.name === "compare_products" && failComparisonOnce ) {
			failComparisonOnce = false;
			const error = new Error( "Rejected by browser" );
			error.name = "NotAllowedError";
			throw error;
		}
		return undefined;
	};
	harness.window.addEventListener( EVENTS.manifestError, ( event ) =>
		manifestErrors.push( event.detail )
	);

	await harness.runtime.start();
	const firstController = harness.runtime.registrationController;
	assert.deepEqual( Array.from( harness.modelContext.active.keys() ), [ "search_products" ] );

	await assert.rejects(
		harness.runtime.refreshManifest( { reason: "policy_change" } ),
		( error ) => error.code === "registration_failed"
	);

	assert.equal( firstController.signal.aborted, true );
	assert.equal( harness.runtime.manifestRevision, "rev_1" );
	assert.equal( harness.runtime.registrationController.signal.aborted, false );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "ready-stale" );
	assert.deepEqual( Array.from( harness.modelContext.active.keys() ), [ "search_products" ] );
	assert.equal( manifestErrors.at( -1 ).rolledBack, true );
	assert.equal( manifestErrors.at( -1 ).attemptedRevision, "rev_2" );

	await harness.runtime.refreshManifest( { reason: "retry" } );
	assert.equal( harness.runtime.manifestRevision, "rev_2" );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "ready" );
	assert.deepEqual( Array.from( harness.modelContext.active.keys() ).sort(), [
		"compare_products",
		"search_products",
	] );
} );

test( "a bad fetched manifest does not remove the active registration set", async () => {
	const invalidManifest = manifest( "rev_bad", [ "search_products", "search_products" ] );
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( invalidManifest ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const registrationController = harness.runtime.registrationController;

	await assert.rejects(
		harness.runtime.refreshManifest( { reason: "invalid_manifest" } ),
		( error ) => error.code === "manifest_invalid"
	);

	assert.equal( registrationController.signal.aborted, false );
	assert.equal( harness.runtime.registrationController, registrationController );
	assert.equal( harness.runtime.manifestRevision, "rev_1" );
	assert.deepEqual( Array.from( harness.modelContext.active.keys() ), [ "search_products" ] );
} );

test( "failed refresh keeps last good tools and reports stale-ready status", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		() => Promise.reject( new TypeError( "network unavailable" ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	const errors = [];
	harness.window.addEventListener( EVENTS.manifestError, ( event ) => errors.push( event.detail ) );

	await harness.runtime.start();
	const registrationController = harness.runtime.registrationController;

	await assert.rejects(
		harness.runtime.refreshManifest( { reason: "network_test" } ),
		( error ) => error.code === "manifest_fetch_failed" && error.retryable
	);

	assert.equal( registrationController.signal.aborted, false );
	assert.equal( harness.runtime.registrationController, registrationController );
	assert.equal( harness.runtime.manifestRevision, "rev_1" );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "ready-stale" );
	assert.equal( errors.at( -1 ).phase, "fetch" );
} );

test( "same-revision refresh rotates credentials without duplicate registration", async () => {
	const firstManifest = manifest( "rev_1" );
	const rotatedManifest = manifest( "rev_1", [ "search_products" ], {
		session: { csrf_token: "csrf-rotated" },
	} );
	const fetch = fetchQueue( [
		jsonResponse( firstManifest ),
		jsonResponse( rotatedManifest ),
		jsonResponse( { ok: true, result: { count: 1 } } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "search_products" ).definition;
	await harness.runtime.refreshManifest( { reason: "token_rotation" } );

	assert.equal( harness.modelContext.calls.length, 1 );
	assert.equal( harness.runtime.activeManifest.session.csrf_token, "csrf-rotated" );
	await definition.execute( {}, { signal: new AbortController().signal } );
	assert.equal( fetch.calls[ 2 ].options.headers[ "X-WMCP-CSRF" ], "csrf-rotated" );
} );

test( "registration replacement never cancels an in-flight execution", async () => {
	const toolResponse = deferred();
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		( url, options ) => {
			assert.match( url, /\/tools\/search_products$/ );
			assert.equal( options.method, "POST" );
			return toolResponse.promise;
		},
		jsonResponse( manifest( "rev_2" ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	const resultEvents = [];
	harness.window.addEventListener( EVENTS.toolResult, ( event ) =>
		resultEvents.push( event.detail )
	);

	await harness.runtime.start();
	const firstRegistration = harness.runtime.registrationController;
	const oldDefinition = harness.modelContext.active.get( "search_products" ).definition;
	const executionController = new AbortController();
	const executionPromise = oldDefinition.execute(
		{ query: "waterproof" },
		{ signal: executionController.signal }
	);
	await waitFor( () => fetch.calls.length === 2 );

	assert.equal( fetch.calls[ 1 ].options.signal, executionController.signal );
	assert.equal( harness.runtime.activeExecutions.size, 1 );
	await harness.runtime.refreshManifest( { reason: "catalog_changed" } );

	assert.equal( firstRegistration.signal.aborted, true );
	assert.equal( executionController.signal.aborted, false );
	assert.equal( harness.runtime.activeExecutions.size, 1 );
	assert.equal( harness.runtime.manifestRevision, "rev_2" );

	toolResponse.resolve(
		jsonResponse( {
			event_id: "evt_1",
			ok: true,
			result: { count: 2 },
			ui: { event: "search_results_changed", revision: "ui_1" },
			workflow_id: "workflow_test",
		} )
	);
	const result = await executionPromise;

	assert.equal( result.ok, true );
	assert.equal( result.result.count, 2 );
	assert.equal( resultEvents.length, 1 );
	assert.equal( harness.runtime.activeExecutions.size, 0 );
	assert.equal( harness.document.documentElement.dataset.wmcpActiveExecutions, "0" );

	const request = JSON.parse( fetch.calls[ 1 ].options.body );
	assert.equal( request.manifest_revision, "rev_1" );
	assert.deepEqual( request.input, { query: "waterproof" } );
	assert.equal( fetch.calls[ 1 ].options.headers[ "X-WMCP-CSRF" ], "csrf-rev_1" );
} );

test( "execution cancellation reaches fetch and is classified separately", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		( url, options ) =>
			new Promise( ( resolve, reject ) => {
				options.signal.addEventListener( "abort", () => reject( abortError() ), {
					once: true,
				} );
			} ),
	] );
	const harness = runtimeHarness( { fetch } );
	const cancelledEvents = [];
	const errorEvents = [];
	harness.window.addEventListener( EVENTS.toolCancelled, ( event ) =>
		cancelledEvents.push( event.detail )
	);
	harness.window.addEventListener( EVENTS.toolError, ( event ) => errorEvents.push( event.detail ) );

	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "search_products" ).definition;
	const executionController = new AbortController();
	const executionPromise = definition.execute( {}, { signal: executionController.signal } );
	await waitFor( () => fetch.calls.length === 2 );
	executionController.abort();

	await assert.rejects(
		executionPromise,
		( error ) =>
			error.name === "AbortError" &&
			error.code === "client_stopped_waiting" &&
			error.outcomeMayComplete === true &&
			/may still complete/i.test( error.message )
	);
	assert.equal( fetch.calls[ 1 ].options.signal, executionController.signal );
	assert.equal( cancelledEvents.length, 1 );
	assert.equal( cancelledEvents[ 0 ].clientStoppedWaiting, true );
	assert.equal( cancelledEvents[ 0 ].outcomeMayComplete, true );
	assert.match( cancelledEvents[ 0 ].message, /may still complete/i );
	assert.equal( errorEvents.length, 0 );
	assert.equal( harness.runtime.activeExecutions.size, 0 );
} );

test( "ambiguous transport failure retries once with the exact serialized request", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		() => Promise.reject( new TypeError( "connection reset after send" ) ),
		jsonResponse( { ok: true, result: { recovered: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );
	const starts = [];
	harness.window.addEventListener( EVENTS.toolStart, ( event ) => starts.push( event.detail ) );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( { query: "pack" }, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( result.result.recovered, true );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
	assert.equal(
		JSON.parse( posts[ 0 ].options.body ).request_id,
		JSON.parse( posts[ 1 ].options.body ).request_id
	);
	assert.equal( starts.length, 1 );
} );

test( "ambiguous response-read failure retries once with the exact serialized request", async () => {
	const unreadableResponse = jsonResponse( { ok: true } );
	unreadableResponse.text = async () => {
		throw new TypeError( "response stream terminated" );
	};
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		unreadableResponse,
		jsonResponse( { ok: true, result: { recovered: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( result.result.recovered, true );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
} );

test( "corrupt 2xx JSON retries once with the exact request then becomes non-retryable", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( null, { rawText: "{" } ),
		jsonResponse( null, { rawText: "{" } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
	assert.deepEqual( result.error, {
		code: "outcome_unconfirmed",
		message: "The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
		retryable: false,
	} );
} );

test( "invalid 2xx JSON shape can recover through the same-ID replay", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( [ "not", "an", "envelope" ] ),
		jsonResponse( { ok: true, result: { recovered: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( result.result.recovered, true );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
} );

test( "empty 2xx tool response is ambiguous and recovers through the same-ID replay", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( null, { rawText: "" } ),
		jsonResponse( { ok: true, result: { recovered: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( result.result.recovered, true );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
} );

test( "unreadable non-2xx responses replay once with the same ID then become outcome-unknown", async () => {
	const unreadable = () => {
		const response = jsonResponse( null, { status: 503 } );
		response.text = async () => {
			throw new TypeError( "response stream terminated" );
		};
		return response;
	};
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		unreadable(),
		unreadable(),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );

	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
	assert.deepEqual( result.error, {
		code: "outcome_unconfirmed",
		message: "The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
		retryable: false,
	} );
} );

test( "request-in-progress after an ambiguity retry never invites a new-ID retry", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		() => Promise.reject( new TypeError( "connection reset after send" ) ),
		jsonResponse( {
			error: {
				code: "request_in_progress",
				message: "An identical request is still running.",
				retryable: true,
			},
			ok: false,
		}, { status: 409 } ),
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const result = await harness.modelContext.active
		.get( "search_products" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
	assert.equal( result.error.code, "outcome_unconfirmed" );
	assert.equal( result.error.retryable, false );
} );

test( "oversized and HTTP error responses return stable serializable envelopes", async ( t ) => {
	await t.test( "oversized 2xx replay remains outcome-unknown and non-retryable", async () => {
		const fetch = fetchQueue( [
			jsonResponse( manifest( "rev_1" ) ),
			jsonResponse( { ok: true, result: { too: "large" } }, {
				headers: { "Content-Length": "999" },
			} ),
			jsonResponse( { ok: true, result: { too: "large" } }, {
				headers: { "Content-Length": "999" },
			} ),
		] );
		const harness = runtimeHarness( { config: { maxOutputBytes: 64 }, fetch } );
		await harness.runtime.start();

		const result = await harness.modelContext.active
			.get( "search_products" )
			.definition.execute( {}, { signal: new AbortController().signal } );

		assert.deepEqual( result.error, {
			code: "outcome_unconfirmed",
			message: "The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
			retryable: false,
		} );
		const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );
		assert.equal( posts.length, 2 );
		assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
		assert.doesNotThrow( () => JSON.stringify( result ) );
	} );

	await t.test( "WordPress error and Retry-After mapping", async () => {
		const fetch = fetchQueue( [
			jsonResponse( manifest( "rev_1" ) ),
			jsonResponse(
				{
					code: "rate_limited",
					data: { status: 429 },
					message: "Please wait before trying this tool again.",
				},
				{ headers: { "Retry-After": "30" }, status: 429 }
			),
		] );
		const harness = runtimeHarness( { fetch } );
		await harness.runtime.start();

		const result = await harness.modelContext.active
			.get( "search_products" )
			.definition.execute( {}, { signal: new AbortController().signal } );

		assert.equal( result.ok, false );
		assert.deepEqual( result.error, {
			code: "rate_limited",
			message: "Please wait before trying this tool again.",
			retry_after: "30",
			retryable: true,
		} );
	} );

	await t.test( "network failure mapping", async () => {
		const fetch = fetchQueue( [
			jsonResponse( manifest( "rev_1" ) ),
			() => Promise.reject( new TypeError( "connection reset" ) ),
			() => Promise.reject( new TypeError( "connection reset again" ) ),
		] );
		const harness = runtimeHarness( { fetch } );
		await harness.runtime.start();

		const result = await harness.modelContext.active
			.get( "search_products" )
			.definition.execute( {}, { signal: new AbortController().signal } );

		assert.deepEqual( result.error, {
			code: "outcome_unconfirmed",
			message: "The client could not confirm the tool outcome. It may have completed on the server; refresh the current state before trying again.",
			retryable: false,
		} );
		assert.doesNotThrow( () => JSON.stringify( result ) );
	} );
} );

test( "manifest_stale execution errors refresh registrations before returning", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse(
			{
				error: {
					code: "manifest_stale",
					message: "The available tools changed.",
					recovery: "Refresh the site tools and retry.",
					retryable: true,
				},
				manifest_revision: "rev_2",
				ok: false,
				workflow_id: "workflow_test",
			},
			{ status: 409 }
		),
		jsonResponse( manifest( "rev_2", [ "get_product" ] ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();
	const oldDefinition = harness.modelContext.active.get( "search_products" ).definition;

	const result = await oldDefinition.execute( {}, { signal: new AbortController().signal } );

	assert.equal( result.ok, false );
	assert.equal( result.error.code, "manifest_stale" );
	assert.equal( result.error.retryable, true );
	assert.equal( result.manifest_revision, "rev_2" );
	assert.equal( harness.runtime.manifestRevision, "rev_2" );
	assert.deepEqual( Array.from( harness.modelContext.active.keys() ), [ "get_product" ] );
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 2 );
	assert.equal( harness.runtime.invalidationChannel.messages.length, 1 );
	assert.equal( harness.runtime.invalidationChannel.messages[ 0 ].surface, "storefront" );
	assert.equal( harness.window.localStorageWrites.length, 1 );
} );

test( "explicit and cross-tab invalidations refresh matching catalogs", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( manifest( "rev_2" ) ),
		jsonResponse( manifest( "rev_3" ) ),
		jsonResponse( manifest( "rev_4" ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();

	harness.window.dispatchEvent(
		new MockCustomEvent( EVENTS.manifestInvalidated, {
			detail: { broadcast: false, reason: "explicit_test" },
		} )
	);
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.manifestRevision, "rev_2" );

	harness.runtime.invalidationChannel.emit( {
		reason: "broadcast_test",
		site_id: "site_test",
		surface: "storefront",
		type: EVENTS.manifestInvalidated,
	} );
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.manifestRevision, "rev_3" );

	harness.window.dispatchEvent( {
		key: "runtime-tests:manifest",
		newValue: JSON.stringify( {
			reason: "storage_test",
			site_id: "site_test",
			surface: "storefront",
			type: EVENTS.manifestInvalidated,
		} ),
		type: "storage",
	} );
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.manifestRevision, "rev_4" );
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 4 );

	harness.runtime.invalidationChannel.emit( {
		reason: "wrong_site",
		site_id: "another_site",
		surface: "storefront",
		type: EVENTS.manifestInvalidated,
	} );
	await harness.runtime.whenIdle();
	assert.equal( fetch.calls.length, 4 );
} );

test( "cross-transport invalidations dedupe matching nonces after scope filtering", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		jsonResponse( manifest( "rev_2" ) ),
		jsonResponse( manifest( "rev_3" ) ),
		jsonResponse( manifest( "rev_4" ) ),
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();

	const sharedMessage = {
		nonce: "invalidation_shared",
		reason: "cart_changed",
		site_id: "site_test",
		surface: "storefront",
		type: EVENTS.manifestInvalidated,
	};
	harness.runtime.invalidationChannel.emit( sharedMessage );
	harness.window.dispatchEvent( {
		key: "runtime-tests:manifest",
		newValue: JSON.stringify( sharedMessage ),
		type: "storage",
	} );
	await harness.runtime.whenIdle();

	assert.equal( harness.runtime.manifestRevision, "rev_2" );
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 2 );

	harness.runtime.invalidationChannel.emit( Object.assign( {}, sharedMessage, {
		nonce: "invalidation_distinct",
	} ) );
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.manifestRevision, "rev_3" );

	const filteredMessage = Object.assign( {}, sharedMessage, {
		nonce: "invalidation_filtered_first",
		surface: "agentops",
	} );
	harness.runtime.invalidationChannel.emit( filteredMessage );
	await harness.runtime.whenIdle();
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 3 );

	harness.window.dispatchEvent( {
		key: "runtime-tests:manifest",
		newValue: JSON.stringify( Object.assign( {}, filteredMessage, {
			surface: "storefront",
		} ) ),
		type: "storage",
	} );
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.manifestRevision, "rev_4" );
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 4 );
} );

test( "accepted invalidation nonce memory remains bounded", () => {
	const harness = runtimeHarness( { fetch: fetchQueue() } );
	const message = {
		reason: "cart_changed",
		site_id: "site_test",
		surface: "storefront",
		type: EVENTS.manifestInvalidated,
	};

	for ( let index = 0; index < 65; index++ ) {
		assert.equal( harness.runtime.acceptInvalidationMessage( Object.assign( {}, message, {
			nonce: `invalidation_${ index }`,
		} ) ), true );
	}

	assert.equal( harness.runtime.recentInvalidationNonces.size, 64 );
	assert.equal( harness.runtime.recentInvalidationNonces.has( "invalidation_0" ), false );
	assert.equal( harness.runtime.recentInvalidationNonces.has( "invalidation_64" ), true );
} );

test( "successful policy tools refresh locally and notify other tabs", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "set_tool_enabled" ], { surface: "agentops" } ) ),
		jsonResponse( {
			manifest_revision: "rev_2",
			ok: true,
			result: { effective_manifest_revision: "rev_2" },
		} ),
		jsonResponse( manifest( "rev_2", [], { surface: "agentops" } ) ),
	] );
	const harness = runtimeHarness( {
		config: { surface: "agentops" },
		fetch,
	} );
	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "set_tool_enabled" ).definition;

	const result = await definition.execute(
		{ enabled: false, tool_name: "compare_products" },
		{ signal: new AbortController().signal }
	);

	assert.equal( result.ok, true );
	assert.equal( harness.runtime.manifestRevision, "rev_2" );
	assert.equal( harness.modelContext.active.size, 0 );
	assert.equal( harness.runtime.invalidationChannel.messages.length, 1 );
	assert.equal( harness.runtime.invalidationChannel.messages[ 0 ].surface, null );
	assert.equal( harness.window.localStorageWrites.length, 1 );
} );

test( "successful cart mutations refresh same-revision private manifests without duplicate registration", async ( t ) => {
	for ( const toolName of [ "add_to_cart", "remove_from_cart", "update_cart_quantity" ] ) {
		await t.test( toolName, async () => {
			const fetch = fetchQueue( [
				jsonResponse( manifest( "rev_1", [ toolName ], {
					cart: { item_count: 0 },
				} ) ),
				jsonResponse( {
					ok: true,
					result: { cart: { item_count: 1 } },
				} ),
				jsonResponse( manifest( "rev_1", [ toolName ], {
					cart: { item_count: 1 },
				} ) ),
			] );
			const harness = runtimeHarness( { fetch } );
			const readyEvents = [];
			harness.window.addEventListener( EVENTS.manifestReady, ( event ) =>
				readyEvents.push( event.detail )
			);

			await harness.runtime.start();
			const registrationController = harness.runtime.registrationController;
			const definition = harness.modelContext.active.get( toolName ).definition;

			const result = await definition.execute(
				{},
				{ signal: new AbortController().signal }
			);

			assert.equal( result.ok, true );
			await harness.runtime.whenIdle();
			assert.equal( harness.runtime.manifestRevision, "rev_1" );
			assert.equal( harness.runtime.activeManifest.cart.item_count, 1 );
			assert.equal( readyEvents.length, 2 );
			assert.equal( readyEvents[ 1 ].cart.item_count, 1 );
			assert.equal( harness.runtime.registrationController, registrationController );
			assert.equal( harness.modelContext.calls.length, 1 );
			assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 2 );
			const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );
			assert.equal( posts.length, 1 );
			assert.match( posts[ 0 ].url, new RegExp( `/tools/${ toolName }$` ) );
			assert.equal( fetch.calls.some( ( call ) => /\/tools\/get_cart$/.test( call.url ) ), false );
			assert.equal( harness.runtime.invalidationChannel.messages.length, 1 );
			const message = harness.runtime.invalidationChannel.messages[ 0 ];
			assert.equal( message.reason, "tool_result" );
			assert.equal( message.surface, "storefront" );
			assert.equal( Object.hasOwn( message, "cart" ), false );
			assert.equal( harness.window.localStorageWrites.length, 1 );
			assert.equal(
				Object.hasOwn( JSON.parse( harness.window.localStorageWrites[ 0 ].value ), "cart" ),
				false
			);
		} );
	}
} );

test( "a successful cart mutation does not wait for its best-effort manifest refresh", async () => {
	const refreshResponse = deferred();
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "add_to_cart" ], {
			cart: { item_count: 0 },
		} ) ),
		jsonResponse( {
			ok: true,
			result: { cart: { item_count: 1 } },
		} ),
		() => refreshResponse.promise,
	] );
	const harness = runtimeHarness( { fetch } );

	await harness.runtime.start();
	const execution = harness.modelContext.active
		.get( "add_to_cart" )
		.definition.execute( {}, { signal: new AbortController().signal } );
	await waitFor( () => fetch.calls.length === 3 );

	const result = await Promise.race( [
		execution,
		new Promise( ( resolve ) => setImmediate( () => resolve( null ) ) ),
	] );
	assert.notEqual( result, null, "the successful mutation should resolve while refresh is pending" );
	assert.equal( result.ok, true );
	assert.equal( harness.runtime.invalidationChannel.messages.length, 1 );

	refreshResponse.resolve( jsonResponse( manifest( "rev_1", [ "add_to_cart" ], {
		cart: { item_count: 1 },
	} ) ) );
	await harness.runtime.whenIdle();
	assert.equal( harness.runtime.activeManifest.cart.item_count, 1 );
} );

test( "credential expiry schedules a same-revision manifest refresh", async () => {
	const timers = [];
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "search_products" ], {
			session: { csrf_token: "csrf-old", expires_at: new Date( Date.now() + 120000 ).toISOString() },
		} ) ),
		jsonResponse( manifest( "rev_1", [ "search_products" ], {
			session: { csrf_token: "csrf-new", expires_at: new Date( Date.now() + 240000 ).toISOString() },
		} ) ),
	] );
	const harness = runtimeHarness( {
		clearTimeout: () => {},
		fetch,
		setTimeout: ( callback, delay ) => {
			timers.push( { callback, delay } );
			return timers.length;
		},
	} );

	await harness.runtime.start();
	assert.equal( timers.length, 1 );
	assert.ok( timers[ 0 ].delay > 0 );
	timers[ 0 ].callback();
	await harness.runtime.whenIdle();

	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 2 );
	assert.equal( harness.runtime.activeManifest.session.csrf_token, "csrf-new" );
	harness.runtime.stop();
} );

test( "csrf_invalid refreshes credentials and safely retries once", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "search_products" ], { session: { csrf_token: "csrf-old" } } ) ),
		jsonResponse( {
			error: { code: "csrf_invalid", message: "Expired", retryable: true },
			ok: false,
		}, { status: 403 } ),
		jsonResponse( manifest( "rev_1", [ "search_products" ], { session: { csrf_token: "csrf-new" } } ) ),
		jsonResponse( { ok: true, result: { retried: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "search_products" ).definition;

	const result = await definition.execute( { query: "pack" }, { signal: new AbortController().signal } );

	assert.equal( result.ok, true );
	assert.equal( result.result.retried, true );
	assert.equal( fetch.calls.filter( ( call ) => call.options.method === "GET" ).length, 2 );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );
	assert.equal( posts.length, 2 );
	assert.equal( posts[ 0 ].options.body, posts[ 1 ].options.body );
	assert.equal(
		JSON.parse( posts[ 0 ].options.body ).request_id,
		JSON.parse( posts[ 1 ].options.body ).request_id
	);
	assert.equal( posts[ 0 ].options.headers[ "X-WMCP-CSRF" ], "csrf-old" );
	assert.equal( posts[ 1 ].options.headers[ "X-WMCP-CSRF" ], "csrf-new" );
} );

test( "ambiguity then csrf refresh preserves the original request envelope", async () => {
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1", [ "search_products" ], { session: { csrf_token: "csrf-old" } } ) ),
		() => Promise.reject( new TypeError( "connection reset after send" ) ),
		jsonResponse( {
			error: { code: "csrf_invalid", message: "Expired", retryable: true },
			ok: false,
		}, { status: 403 } ),
		jsonResponse( manifest( "rev_1", [ "search_products" ], { session: { csrf_token: "csrf-new" } } ) ),
		jsonResponse( { ok: true, result: { recovered: true } } ),
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "search_products" ).definition;

	const result = await definition.execute( { query: "pack" }, { signal: new AbortController().signal } );
	const posts = fetch.calls.filter( ( call ) => call.options.method === "POST" );

	assert.equal( result.result.recovered, true );
	assert.equal( posts.length, 3 );
	assert.equal( new Set( posts.map( ( call ) => call.options.body ) ).size, 1 );
	assert.equal(
		new Set( posts.map( ( call ) => JSON.parse( call.options.body ).request_id ) ).size,
		1
	);
	assert.equal( posts[ 2 ].options.headers[ "X-WMCP-CSRF" ], "csrf-new" );
} );

test( "stop unregisters tools and listeners without aborting active calls", async () => {
	const executionResponse = deferred();
	const fetch = fetchQueue( [
		jsonResponse( manifest( "rev_1" ) ),
		() => executionResponse.promise,
	] );
	const harness = runtimeHarness( { fetch } );
	await harness.runtime.start();
	const definition = harness.modelContext.active.get( "search_products" ).definition;
	const executionController = new AbortController();
	const resultPromise = definition.execute( {}, { signal: executionController.signal } );
	await waitFor( () => fetch.calls.length === 2 );
	const channel = harness.runtime.invalidationChannel;

	harness.runtime.stop();
	assert.equal( harness.modelContext.active.size, 0 );
	assert.equal( harness.document.documentElement.dataset.wmcpStatus, "stopped" );
	assert.equal( executionController.signal.aborted, false );
	assert.equal( channel.closed, true );

	executionResponse.resolve( jsonResponse( { ok: true, result: { preserved: true } } ) );
	assert.equal( ( await resultPromise ).result.preserved, true );
} );
