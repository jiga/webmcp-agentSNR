"use strict";

const test = require( "node:test" );
const assert = require( "node:assert/strict" );
const path = require( "node:path" );

const {
	MockCustomEvent,
	MockDocument,
	MockWindow,
	element,
	jsonResponse,
	queuedFetch,
	runBrowserScript,
	waitFor,
} = require( "./dom-harness.js" );

const SCRIPT = path.resolve( __dirname, "../../plugin/wmcp-agentsnr/assets/js/agentsnr-runtime.js" );

function checkItem( document, key ) {
	return element( document, "li", {
		dataset: { check: key },
		children: [
			element( document, "strong", { dataset: { checkStatus: "" }, text: "Checking" } ),
			element( document, "small", { dataset: { checkDetail: "" } } ),
		],
	} );
}

function readinessFixture() {
	const document = new MockDocument();
	const checks = Object.fromEntries( [
		"secure_context",
		"top_level",
		"webmcp_api",
		"registration",
		"origin_agent_cluster",
		"database",
		"woocommerce",
		"attribution",
		"manifest",
	].map( ( key ) => [ key, checkItem( document, key ) ] ) );
	const root = element( document, "main", {
		className: "wmcp-field wmcp-health",
		children: [
			element( document, "span", { dataset: { wmcpStatus: "" } } ),
			element( document, "div", { dataset: { wmcpStatusChip: "" } } ),
			element( document, "span", { dataset: { wmcpLatestTool: "" } } ),
			...Object.values( checks ),
			element( document, "strong", { dataset: { wmcpToolCount: "" } } ),
			element( document, "strong", { dataset: { wmcpWorkflow: "" } } ),
			element( document, "strong", { dataset: { wmcpHealthScore: "" } } ),
			element( document, "span", { dataset: { wmcpHealthSummary: "" } } ),
			element( document, "p", { dataset: { wmcpAnnouncer: "" } } ),
			element( document, "div", { dataset: { wmcpError: "" }, hidden: true } ),
		],
	} );
	document.body.append( root );
	return { checks, document, root };
}

test( "unsupported browser and a zero-tool manifest report unavailable readiness without breaking the human surface", async () => {
	const fixture = readinessFixture();
	const fetch = queuedFetch( [
		jsonResponse( {
			diagnostics: {
				checks: {
					database: { status: "passed", version: "1" },
					woocommerce: { status: "enabled", version: "10.2" },
				},
			},
		}, { headers: { "Origin-Agent-Cluster": "?1" } } ),
		jsonResponse( {
			manifest_revision: "rev_zero",
			tools: [],
			workflow_id: "workflow_zero_tools",
		} ),
	] );
	const window = new MockWindow( fixture.document, {
		fetch,
		isSecureContext: true,
		wmcpConfig: {
			healthUrl: "/health",
			manifestUrl: "/manifest",
		},
	} );

	runBrowserScript( SCRIPT, { document: fixture.document, window } );
	await waitFor( () => fetch.calls.length === 2 && fixture.root.querySelector( "[data-wmcp-tool-count]" ).textContent === "0" );
	window.dispatchEvent( new MockCustomEvent( "wmcp:status", { detail: { status: "unsupported-browser" } } ) );

	assert.equal( fixture.root.querySelector( "[data-wmcp-status]" ).textContent, "WebMCP not detected" );
	assert.equal( fixture.root.querySelector( "[data-wmcp-status-chip]" ).dataset.state, "unsupported" );
	assert.equal( fixture.checks.webmcp_api.dataset.state, "unavailable" );
	assert.equal( fixture.checks.webmcp_api.querySelector( "[data-check-status]" ).textContent, "Not detected" );
	assert.equal( fixture.checks.registration.dataset.state, "unavailable" );
	assert.equal( fixture.checks.registration.querySelector( "[data-check-status]" ).textContent, "WebMCP not detected" );
	assert.equal( fixture.checks.manifest.dataset.state, "unavailable" );
	assert.equal( fixture.checks.manifest.querySelector( "[data-check-status]" ).textContent, "0 tools" );
	assert.equal( fixture.root.querySelector( "[data-wmcp-workflow]" ).textContent, "workflow…" );
	assert.match( fixture.root.querySelector( "[data-wmcp-announcer]" ).textContent, /human site remains available/i );
	assert.equal( fixture.root.querySelector( "[data-wmcp-error]" ).hidden, true );
} );

test( "client abort messaging does not claim authoritative server cancellation", () => {
	const fixture = readinessFixture();
	const window = new MockWindow( fixture.document, { wmcpConfig: {} } );
	runBrowserScript( SCRIPT, { document: fixture.document, window } );

	window.dispatchEvent( new MockCustomEvent( "wmcp:tool-cancelled", {
		detail: {
			clientStoppedWaiting: true,
			message: "The client stopped waiting. The server may still complete the request.",
			outcomeMayComplete: true,
			tool: "add_to_cart",
		},
	} ) );

	assert.equal(
		fixture.root.querySelector( "[data-wmcp-latest-tool]" ).textContent,
		"add_to_cart · client stopped waiting"
	);
	assert.match( fixture.root.querySelector( "[data-wmcp-announcer]" ).textContent, /server may still complete/i );
} );
