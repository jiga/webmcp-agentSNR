"use strict";

const test = require( "node:test" );
const assert = require( "node:assert/strict" );
const path = require( "node:path" );

const {
	MockCustomEvent,
	MockDocument,
	MockWindow,
	element,
	runBrowserScript,
} = require( "./dom-harness.js" );

const SCRIPT = path.resolve( __dirname, "../../plugin/wmcp-agentops/assets/js/dashboard.js" );

function attributionCard( document, attribution ) {
	return element( document, "article", {
		dataset: { attribution },
		children: [
			element( document, "strong", { dataset: { attributionOrders: "" } } ),
			element( document, "dd", { dataset: { attributionGross: "" } } ),
			element( document, "dd", { dataset: { attributionRefunds: "" } } ),
			element( document, "dd", { dataset: { attributionNet: "" } } ),
		],
	} );
}

function dashboardFixture() {
	const document = new MockDocument();
	const policyState = element( document, "span", { dataset: { policyState: "" }, text: "Site enabled" } );
	const policyRow = element( document, "div", {
		className: "wmcp-public-tool",
		dataset: { policyTool: "compare_products" },
		children: [ policyState ],
	} );
	const root = element( document, "main", {
		className: "wmcp-field",
		dataset: { wmcpSurface: "agentops" },
		children: [
			element( document, "strong", { dataset: { metric: "revenue.net" } } ),
			element( document, "strong", { dataset: { metric: "revenue.refunds" } } ),
			element( document, "strong", { dataset: { metric: "policy_changes" }, text: "—" } ),
			attributionCard( document, "direct" ),
			attributionCard( document, "assisted" ),
			attributionCard( document, "influenced" ),
			element( document, "strong", { dataset: { wmcpWorkflow: "" } } ),
			element( document, "tbody", { dataset: { wmcpWorkflows: "" } } ),
			element( document, "tbody", { dataset: { wmcpToolHealth: "" } } ),
			element( document, "div", { dataset: { wmcpGaps: "" } } ),
			policyRow,
			element( document, "p", { dataset: { wmcpAnnouncer: "" } } ),
			element( document, "div", { dataset: { wmcpError: "" }, hidden: true } ),
		],
	} );
	document.body.append( root );
	const window = new MockWindow( document, {
		wmcpConfig: {
			executionBaseUrl: "/wp-json/wmcp-agentops/v1",
			manifestUrl: "/manifest",
		},
	} );
	runBrowserScript( SCRIPT, { document, window } );
	return { document, policyRow, policyState, root, window };
}

function render( window, tool, result ) {
	window.dispatchEvent( new MockCustomEvent( "wmcp:ui-update", {
		detail: {
			response: { result, tool: { name: tool } },
			tool,
		},
	} ) );
}

function formattedMoney( value, currency ) {
	return new Intl.NumberFormat( undefined, {
		currency,
		maximumFractionDigits: 2,
		style: "currency",
	} ).format( value );
}

test( "overview and attribution preserve separate totals for every returned currency", () => {
	const fixture = dashboardFixture();
	const totals = {
		USD: { gross: 125, net: 100, refunds: 25 },
		EUR: { gross: 80, net: 75, refunds: 5 },
	};

	render( fixture.window, "get_agent_analytics_overview", {
		revenue: {
			attribution: {
				direct: { by_currency: totals, orders: 3 },
			},
			by_currency: totals,
		},
	} );

	assert.equal(
		fixture.root.querySelector( '[data-metric="revenue.net"]' ).textContent,
		`${ formattedMoney( 100, "USD" ) } + ${ formattedMoney( 75, "EUR" ) }`
	);
	assert.equal(
		fixture.root.querySelector( '[data-metric="revenue.refunds"]' ).textContent,
		`${ formattedMoney( 25, "USD" ) } + ${ formattedMoney( 5, "EUR" ) }`
	);
	const direct = fixture.root.querySelector( '[data-attribution="direct"]' );
	assert.equal( direct.querySelector( "[data-attribution-orders]" ).textContent, "3 orders" );
	assert.equal( direct.querySelector( "[data-attribution-gross]" ).textContent, `${ formattedMoney( 125, "USD" ) } + ${ formattedMoney( 80, "EUR" ) }` );
	assert.equal( direct.querySelector( "[data-attribution-refunds]" ).textContent, `${ formattedMoney( 25, "USD" ) } + ${ formattedMoney( 5, "EUR" ) }` );
	assert.equal( direct.querySelector( "[data-attribution-net]" ).textContent, `${ formattedMoney( 100, "USD" ) } + ${ formattedMoney( 75, "EUR" ) }` );
} );

test( "workflow, tool-health, and capability-gap collection responses consume their items shape", async ( t ) => {
	await t.test( "workflows.items", () => {
		const fixture = dashboardFixture();
		const maliciousSignal = '<img src=x onerror="globalThis.compromised=true">';

		render( fixture.window, "query_agent_workflows", {
			items: [ {
				commerce: { by_currency: { USD: { net: 42 } } },
				last_event: { tool_name: maliciousSignal },
				status: "failed",
				tool_count: 4,
				workflow_id: "workflow_items_contract",
			} ],
		} );

		const body = fixture.root.querySelector( "[data-wmcp-workflows]" );
		assert.equal( body.children.length, 1 );
		assert.equal( body.children[ 0 ].dataset.workflowId, "workflow_items_contract" );
		assert.match( body.textContent, /workflow…/ );
		assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === maliciousSignal ) );
		assert.equal( fixture.document.innerHTMLWrites.length, 0 );
		assert.equal( fixture.root.querySelectorAll( "img" ).length, 0 );
	} );

	await t.test( "health.items", () => {
		const fixture = dashboardFixture();
		const maliciousTool = "<script>stealSession()</script>";

		render( fixture.window, "get_tool_health", {
			items: [ {
				attributed_orders: 1,
				calls: 5,
				cart_mutations: 1,
				checkout_handoffs: 1,
				enabled: false,
				denial_rate: 0.2,
				failure_rate: 0.2,
				net_attributed_revenue: { GBP: { net: 31 } },
				p50_duration_ms: 12,
				p95_duration_ms: 45,
				success_rate: 0.6,
				tool_name: maliciousTool,
				top_errors: [ { code: "policy_denied" } ],
				version: "1.2.3",
				workflows: 2,
			} ],
		} );

		const body = fixture.root.querySelector( "[data-wmcp-tool-health]" );
		assert.equal( body.children.length, 1 );
		assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value.includes( maliciousTool ) ) );
		assert.match( body.textContent, /policy_denied/ );
		const state = body.querySelector( ".wmcp-state-tag" );
		assert.equal( state.textContent, "Disabled" );
		assert.equal( state.classList.contains( "wmcp-is-bad" ), true );
		assert.equal( fixture.document.innerHTMLWrites.length, 0 );
		assert.equal( fixture.root.querySelectorAll( "script" ).length, 0 );
	} );

	await t.test( "gaps.items", () => {
		const fixture = dashboardFixture();
		const maliciousCapability = "<svg onload=stealSession()>";

		render( fixture.window, "get_capability_gaps", {
			items: [ {
				affected_workflows: 2,
				capability: maliciousCapability,
				latest_occurrence: "2026-08-30 12:00:00",
				related_product_ids: [ 10, 12 ],
				requests: 3,
				status: "open",
				viewed_product_value_context: {
					USD: { net: 49 },
				},
			} ],
		} );

		const gaps = fixture.root.querySelector( "[data-wmcp-gaps]" );
		assert.equal( gaps.children.length, 1 );
		assert.equal( gaps.children[ 0 ].classList.contains( "wmcp-gap-card" ), true );
		assert.match( gaps.textContent, /Svg Onload=StealSession/ );
		assert.match( gaps.textContent, /Viewed-product value context/ );
		assert.equal( fixture.document.innerHTMLWrites.length, 0 );
		assert.equal( fixture.root.querySelectorAll( "svg" ).length, 0 );
	} );
} );

test( "session policy updates distinguish a session disable from a server policy denial", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "set_tool_enabled", {
		after: { enabled: false },
		requested_enabled: false,
		tool_name: "compare_products",
	} );

	assert.equal( fixture.policyState.textContent, "Disabled for this demo session" );
	assert.equal( fixture.policyRow.classList.contains( "wmcp-policy-disabled" ), true );
	assert.equal( fixture.root.querySelector( '[data-metric="policy_changes"]' ).textContent, "1" );

	render( fixture.window, "set_tool_enabled", {
		after: { enabled: false },
		requested_enabled: true,
		tool_name: "compare_products",
	} );

	assert.equal( fixture.policyState.textContent, "Blocked by global or site policy" );
	assert.equal( fixture.policyRow.classList.contains( "wmcp-policy-disabled" ), true );
	assert.equal( fixture.root.querySelector( '[data-metric="policy_changes"]' ).textContent, "2" );
} );
