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

function dashboardFixture( options = {} ) {
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
			element( document, "button", { dataset: { wmcpLoadDashboard: "" } } ),
			element( document, "li", {
				dataset: { funnelStage: "workflow_started" },
				children: [
					element( document, "span", { dataset: { funnelCount: "" } } ),
					element( document, "span", { dataset: { funnelRate: "" } } ),
					element( document, "span", { dataset: { funnelPrevious: "" } } ),
					element( document, "small", { dataset: { funnelReason: "" } } ),
				],
			} ),
			element( document, "strong", { dataset: { metric: "revenue.net" } } ),
			element( document, "strong", { dataset: { metric: "revenue.refunds" } } ),
				element( document, "strong", { dataset: { metric: "policy_changes" }, text: "—" } ),
				element( document, "strong", { dataset: { wmcpOpportunityCount: "" }, text: "—" } ),
				element( document, "span", { dataset: { wmcpFeedbackCount: "" }, text: "—" } ),
			attributionCard( document, "direct" ),
			attributionCard( document, "assisted" ),
			attributionCard( document, "influenced" ),
			element( document, "div", { dataset: { wmcpSignals: "" } } ),
			element( document, "strong", { dataset: { wmcpWorkflow: "" } } ),
			element( document, "h3", { id: "wmcp-timeline-title" } ),
			element( document, "p", { dataset: { wmcpExplanation: "" } } ),
			element( document, "span", { dataset: { wmcpTimelineCount: "" } } ),
			element( document, "dd", { dataset: { evidence: "status" } } ),
			element( document, "dd", { dataset: { evidence: "products" } } ),
			element( document, "dd", { dataset: { evidence: "orders" } } ),
				element( document, "dd", { dataset: { evidence: "gaps" } } ),
				element( document, "dd", { dataset: { evidence: "opportunities" } } ),
				element( document, "dd", { dataset: { evidence: "feedback" } } ),
			element( document, "ol", { dataset: { wmcpTimeline: "" } } ),
			element( document, "tbody", { dataset: { wmcpWorkflows: "" } } ),
			element( document, "tbody", { dataset: { wmcpToolHealth: "" } } ),
				element( document, "div", { dataset: { wmcpGaps: "", wmcpOpportunities: "" } } ),
			policyRow,
			element( document, "p", { dataset: { wmcpAnnouncer: "" } } ),
			element( document, "div", { dataset: { wmcpError: "" }, hidden: true } ),
		],
	} );
	document.body.append( root );
	const window = new MockWindow( document, {
		fetch: options.fetch,
		wmcpConfig: Object.assign( {
			executionBaseUrl: "/wp-json/wmcp-agentops/v1",
			manifestUrl: "/manifest",
		}, options.wmcpConfig || {} ),
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

function formattedDateTime( value ) {
	return new Intl.DateTimeFormat( undefined, {
		dateStyle: "short",
		timeStyle: "medium",
	} ).format( new Date( `${ value.replace( " ", "T" ) }Z` ) );
}

function signalCards( root ) {
	return root.querySelectorAll( "[data-wmcp-signals] .wmcp-signal-card" );
}

function workflow( workflowId, overrides = {} ) {
	return Object.assign( {
		commerce: { by_currency: {} },
		last_event: { event_name: "workflow.started" },
		status: "completed",
		tool_count: 1,
		workflow_id: workflowId,
	}, overrides );
}

function explanation( workflowId, label = "Returned evidence." ) {
	return {
		agent_feedback: [],
		capability_gaps: [],
		commerce_outcome: { orders: [] },
		explanation: label,
		opportunity_signals: [],
		timeline: [ {
			duration_ms: 12,
			event_name: "tool.call.succeeded",
			occurred_at: "2026-08-30 12:00:00",
			outcome: "success",
			product_ids: [ 101 ],
		} ],
		truncated: false,
		workflow: { status: "completed", workflow_id: workflowId },
	};
}

function explanationManifest() {
	return {
		manifest_revision: "revision-1",
		schema_version: "1",
		session: { csrf_token: "csrf" },
		tools: [ { name: "explain_agent_workflow" } ],
		workflow_id: "current-session-workflow",
	};
}

function deferred() {
	let resolve;
	const promise = new Promise( ( promiseResolve ) => {
		resolve = promiseResolve;
	} );
	return { promise, resolve };
}

test( "journey stages omit unknown median timing instead of displaying null", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_agent_conversion_funnel", {
		stages: [ {
			conversion_from_previous: 0,
			conversion_from_start: 0,
			median_time_to_next_ms: null,
			stage: "workflow_started",
			top_exit_reason: "no_recorded_exit",
			workflow_count: 0,
		} ],
	} );

	const reason = fixture.root.querySelector( '[data-funnel-stage="workflow_started"] [data-funnel-reason]' );
	assert.equal( reason.textContent, "No Recorded Exit" );
	assert.doesNotMatch( reason.textContent, /null|undefined/i );
} );

test( "Agent Sessions discloses when more workflows exist beyond the returned page", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "query_agent_workflows", {
		has_more: true,
		items: [ {
			commerce: { by_currency: {} },
			last_event: { event_name: "workflow.started" },
			status: "active",
			tool_count: 1,
			workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE",
		} ],
	} );

	const note = fixture.root.querySelector( '[data-workflow-coverage="partial"]' );
	assert.match( note.textContent, /first 1 agent workflows/ );
	assert.match( note.textContent, /More are available/ );
} );

test( "Workflow Replay discloses a truncated event timeline", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "explain_agent_workflow", {
		capability_gaps: [],
		commerce_outcome: { orders: [] },
		explanation: "Returned evidence.",
		timeline: [ {
			duration_ms: 12,
			event_name: "tool.call.succeeded",
			occurred_at: "2026-08-30 12:00:00",
			outcome: "success",
			product_ids: [],
		} ],
		truncated: true,
		workflow: {
			status: "completed",
			workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE",
		},
	} );

	assert.equal(
		fixture.root.querySelector( "[data-wmcp-timeline-count]" ).textContent,
		"1 event shown · partial replay"
	);
} );

test( "clicking an Agent Sessions row exposes contained loading state then renders replay", async () => {
	const selectedWorkflow = "01M18QH3GTR0AJB8NCGN35R5CE";
	const fetch = queuedFetch( [
		jsonResponse( explanationManifest() ),
		jsonResponse( { ok: true, result: explanation( selectedWorkflow ) } ),
	] );
	const fixture = dashboardFixture( { fetch } );
	render( fixture.window, "query_agent_workflows", {
		items: [
			workflow( selectedWorkflow ),
			workflow( "01M18QH3GTR0AJB8NCGN35R5CF" ),
		],
	} );
	const rows = fixture.root.querySelectorAll( "[data-workflow-id]" );
	const selectedButton = rows[ 0 ].querySelector( "[data-explain-workflow]" );
	const otherButton = rows[ 1 ].querySelector( "[data-explain-workflow]" );
	const error = fixture.root.querySelector( "[data-wmcp-error]" );
	error.hidden = false;
	error.textContent = "Previous replay error";
	let resultEvents = 0;
	let updateEvents = 0;
	fixture.window.addEventListener( "wmcp:tool-result", () => resultEvents++ );
	fixture.window.addEventListener( "wmcp:ui-update", ( event ) => {
		if ( event.detail?.tool === "explain_agent_workflow" ) {
			updateEvents++;
		}
	} );

	selectedButton.click();

	assert.equal( rows[ 0 ].classList.contains( "wmcp-is-selected" ), true );
	assert.equal( rows[ 0 ].classList.contains( "wmcp-is-loading" ), true );
	assert.equal( rows[ 0 ].getAttribute( "aria-busy" ), "true" );
	assert.equal( selectedButton.disabled, true );
	assert.equal( selectedButton.textContent, "Loading…" );
	assert.equal( selectedButton.getAttribute( "aria-busy" ), "true" );
	assert.equal( selectedButton.getAttribute( "aria-current" ), "true" );
	assert.equal( rows[ 1 ].classList.contains( "wmcp-is-loading" ), false );
	assert.equal( otherButton.disabled, false );
	assert.equal( otherButton.getAttribute( "aria-current" ), null );
	assert.equal( fixture.root.querySelector( "#wmcp-timeline-title" ).textContent, "Loading workflow replay…" );

	await waitFor( () => selectedButton.disabled === false, "workflow replay did not settle" );

	assert.equal( rows[ 0 ].classList.contains( "wmcp-is-selected" ), true );
	assert.equal( rows[ 0 ].classList.contains( "wmcp-is-loading" ), false );
	assert.equal( rows[ 0 ].getAttribute( "aria-busy" ), null );
	assert.equal( selectedButton.getAttribute( "aria-busy" ), null );
	assert.equal( selectedButton.textContent, `${ selectedWorkflow.slice( 0, 8 ) }…` );
	assert.equal( fixture.root.querySelector( "[data-wmcp-explanation]" ).textContent, "Returned evidence." );
	assert.equal( fixture.root.querySelector( "[data-wmcp-timeline]" ).children.length, 1 );
	assert.equal( error.hidden, true );
	assert.doesNotMatch( error.textContent, /Previous replay error/ );
	assert.match( fixture.root.querySelector( "[data-wmcp-announcer]" ).textContent, /Workflow replay loaded/ );
	assert.equal( resultEvents, 1 );
	assert.equal( updateEvents, 1 );
} );

test( "output_too_large clears stale replay and leaves the failed row selected", async () => {
	const selectedWorkflow = "01M18QH3GTR0AJB8NCGN35R5CE";
	const fetch = queuedFetch( [
		jsonResponse( explanationManifest() ),
		jsonResponse( {
			error: {
				code: "output_too_large",
				message: "The workflow replay exceeded the safe display limit.",
			},
			ok: false,
		}, { status: 413 } ),
	] );
	const fixture = dashboardFixture( { fetch } );
	render( fixture.window, "query_agent_workflows", {
		items: [ workflow( selectedWorkflow ) ],
	} );
	render( fixture.window, "explain_agent_workflow", explanation(
		"stale-workflow",
		"Stale replay evidence that must be cleared."
	) );
	const row = fixture.root.querySelector( "[data-workflow-id]" );
	const button = row.querySelector( "[data-explain-workflow]" );
	let resultEvents = 0;
	let updateEvents = 0;
	fixture.window.addEventListener( "wmcp:tool-result", () => resultEvents++ );
	fixture.window.addEventListener( "wmcp:ui-update", () => updateEvents++ );

	button.click();
	await waitFor( () => button.disabled === false, "failed workflow replay did not settle" );

	assert.equal( row.classList.contains( "wmcp-is-selected" ), true );
	assert.equal( row.classList.contains( "wmcp-has-error" ), true );
	assert.equal( row.classList.contains( "wmcp-is-loading" ), false );
	assert.equal( button.textContent, `${ selectedWorkflow.slice( 0, 8 ) }…` );
	assert.equal( button.getAttribute( "aria-busy" ), null );
	assert.equal( button.getAttribute( "aria-current" ), "true" );
	assert.equal( fixture.root.querySelector( "#wmcp-timeline-title" ).textContent, "Workflow replay unavailable" );
	assert.match( fixture.root.querySelector( "[data-wmcp-explanation]" ).textContent, /could not be loaded/ );
	assert.equal( fixture.root.querySelector( "[data-wmcp-timeline-count]" ).textContent, "0 events" );
	assert.equal( fixture.root.querySelector( '[data-evidence="status"]' ).textContent, "Unavailable" );
	assert.equal( fixture.root.querySelector( '[data-evidence="products"]' ).textContent, "Not available" );
	assert.equal( fixture.root.querySelector( '[data-evidence="orders"]' ).textContent, "Not available" );
	assert.equal( fixture.root.querySelector( '[data-evidence="gaps"]' ).textContent, "Not available" );
	assert.equal( fixture.root.querySelector( "[data-wmcp-timeline]" ).children.length, 0 );
	assert.doesNotMatch( fixture.root.textContent, /Stale replay evidence that must be cleared/ );
	const error = fixture.root.querySelector( "[data-wmcp-error]" );
	assert.equal( error.hidden, false );
	assert.match( error.textContent, /exceeded the safe display limit/ );
	assert.match( fixture.root.querySelector( "[data-wmcp-announcer]" ).textContent, /Workflow replay unavailable/ );
	assert.equal( resultEvents, 0 );
	assert.equal( updateEvents, 0 );
} );

test( "a late rapid-click replay cannot replace the latest selected workflow", async () => {
	const firstWorkflow = "01M18QH3GTR0AJB8NCGN35R5CE";
	const secondWorkflow = "01M18QH3GTR0AJB8NCGN35R5CF";
	const firstResponse = deferred();
	const fetch = queuedFetch( [
		jsonResponse( explanationManifest() ),
		() => firstResponse.promise,
		jsonResponse( { ok: true, result: explanation( secondWorkflow, "Latest replay." ) } ),
	] );
	const fixture = dashboardFixture( { fetch } );
	render( fixture.window, "query_agent_workflows", {
		items: [ workflow( firstWorkflow ), workflow( secondWorkflow ) ],
	} );
	const rows = fixture.root.querySelectorAll( "[data-workflow-id]" );
	const firstButton = rows[ 0 ].querySelector( "[data-explain-workflow]" );
	const secondButton = rows[ 1 ].querySelector( "[data-explain-workflow]" );
	let replayUpdates = 0;
	fixture.window.addEventListener( "wmcp:ui-update", ( event ) => {
		if ( event.detail?.tool === "explain_agent_workflow" ) {
			replayUpdates++;
		}
	} );

	firstButton.click();
	await waitFor( () => fetch.calls.length === 2, "first replay request did not start" );
	secondButton.click();
	await waitFor(
		() => secondButton.disabled === false && fixture.root.querySelector( "[data-wmcp-explanation]" ).textContent === "Latest replay.",
		"latest replay did not settle"
	);

	assert.equal( firstButton.disabled, false );
	assert.equal( rows[ 0 ].classList.contains( "wmcp-is-selected" ), false );
	assert.equal( rows[ 1 ].classList.contains( "wmcp-is-selected" ), true );
	assert.equal( secondButton.getAttribute( "aria-current" ), "true" );

	firstResponse.resolve( jsonResponse( {
		ok: true,
		result: explanation( firstWorkflow, "Late stale replay." ),
	} ) );
	await new Promise( ( resolve ) => setImmediate( resolve ) );

	assert.equal( fixture.root.querySelector( "[data-wmcp-explanation]" ).textContent, "Latest replay." );
	assert.equal( fixture.root.querySelector( "#wmcp-timeline-title" ).textContent, `Workflow ${ secondWorkflow.slice( 0, 8 ) }…` );
	assert.equal( replayUpdates, 1 );
} );

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

test( "Signals queue renders tool errors with exact reliability evidence and severity", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_tool_health", {
		items: [ {
			calls: 8,
			denied: 0,
			denial_rate: 0,
			failed: 3,
			failure_rate: 0.375,
			tool_name: "search_products",
			top_errors: [
				{ code: "catalog_timeout", count: 2 },
				{ code: "invalid_filter", count: 1 },
			],
		} ],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 1 );
	assert.equal( cards[ 0 ].dataset.signalType, "reliability-signal" );
	assert.equal( cards[ 0 ].classList.contains( "wmcp-signal-critical" ), true );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-type" ).textContent, "Reliability signal" );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-severity" ).textContent, "Critical" );
	assert.match( cards[ 0 ].textContent, /3 failed calls · 37\.5% rate/ );
	assert.match( cards[ 0 ].textContent, /search_products/ );
	assert.match( cards[ 0 ].textContent, /catalog_timeout × 2 · invalid_filter × 1/ );
} );

test( "Signals queue labels denied calls neutrally instead of claiming policy causality", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_tool_health", {
		items: [ {
			calls: 4,
			denied: 2,
			denial_rate: 0.5,
			failed: 0,
			failure_rate: 0,
			tool_name: "add_to_cart",
			top_errors: [ { code: "policy_denied", count: 2 } ],
		} ],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 1 );
	assert.equal( cards[ 0 ].dataset.signalType, "denial-signal" );
	assert.equal( cards[ 0 ].classList.contains( "wmcp-signal-warning" ), true );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-type" ).textContent, "Denial signal" );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-severity" ).textContent, "Warning" );
	assert.match( cards[ 0 ].textContent, /2 denied calls · 50% rate/ );
	assert.match( cards[ 0 ].textContent, /add_to_cart/ );
	assert.match( cards[ 0 ].textContent, /policy_denied × 2/ );
} );

test( "Signals queue renders active grouped capability gaps as opportunities", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_capability_gaps", {
		items: [
			{
				affected_workflows: 2,
				capability: "back_in_stock_notification",
				requests: 4,
				status: "open",
			},
			{
				affected_workflows: 1,
				capability: "gift_wrapping",
				requests: 1,
				status: "resolved",
			},
		],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 1 );
	assert.equal( cards[ 0 ].dataset.signalType, "opportunity-gap" );
	assert.equal( cards[ 0 ].classList.contains( "wmcp-signal-opportunity" ), true );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-type" ).textContent, "Opportunity gap" );
	assert.equal( cards[ 0 ].querySelector( ".wmcp-signal-severity" ).textContent, "Opportunity" );
	assert.match( cards[ 0 ].textContent, /4 requests · 2 workflows/ );
	assert.match( cards[ 0 ].textContent, /back_in_stock_notification/ );
	assert.doesNotMatch( cards[ 0 ].textContent, /gift_wrapping/ );
} );

test( "Signals keep site observation, agent testimony, and verified measurements distinct", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_opportunity_signals", {
		has_more: false,
		items: [ {
			affected_workflows: 2,
			category: "demand_gap",
			evidence_status: "linked",
			feedback_count: 1,
			latest_occurrence: "2026-08-31 12:00:00",
			measured_context: {
				checkout_conversion: { status: "verified", value: true },
				eligible_product_count: { status: "verified", value: 2 },
				highest_matching_water_rating: { status: "verified", value: "IPX4" },
				net_attributed_value: { status: "verified", value: [ { currency: "USD", value: 69 } ] },
				search_refinement_count: { status: "verified", value: 2 },
			},
			measurement_scope: {
				kind: "latest_workflow_sample",
				occurred_at: "2026-08-31 11:59:00",
				source: "agent_reported",
				workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE",
			},
			observed_count: 1,
			related_product_ids: [ 18, 21 ],
			signal_key: "waterproof-under-100",
			sources: { agent_reported: 1, site_observed: 1 },
			status: "open",
			suggested_owner_action: "improve_product_coverage",
			title: "Waterproof backpack under $100",
		} ],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 1 );
	assert.equal( cards[ 0 ].dataset.signalCategory, "demand_gap" );
	assert.equal( cards[ 0 ].dataset.signalSource, "site-observed+agent-reported" );
	assert.deepEqual(
		cards[ 0 ].querySelectorAll( "[data-signal-source]" ).map( ( label ) => label.textContent ),
		[ "Site observed", "Agent reported" ]
	);
	assert.match( cards[ 0 ].textContent, /1 site observation · 1 agent report/ );
	assert.match( cards[ 0 ].textContent, /Improve Product Coverage/ );

	const detail = fixture.root.querySelector( "[data-wmcp-opportunities] .wmcp-opportunity-card" );
	assert.equal( detail.dataset.signalSource, "site-observed+agent-reported" );
	assert.match( detail.textContent, /eligible products: 2 \(Verified\)/ );
	assert.match( detail.textContent, /highest water rating: IPX4 \(Verified\)/ );
	assert.match( detail.textContent, /checkout converted: yes \(Verified\)/ );
	assert.match( detail.textContent, new RegExp( formattedMoney( 69, "USD" ).replace( /[$]/g, "\\$&" ) ) );
	assert.match( detail.textContent, /Latest workflow sample/ );
	assert.match( detail.textContent, /Not aggregate · Workflow 01M18QH3… · Agent reported/ );
	assert.equal( detail.textContent.includes( formattedDateTime( "2026-08-31 11:59:00" ) ), true );
	assert.doesNotMatch( detail.textContent, /01M18QH3GTR0AJB8NCGN35R5CE/ );
	assert.equal( fixture.root.querySelector( "[data-wmcp-opportunity-count]" ).textContent, "1" );
	assert.equal( fixture.root.querySelector( "[data-wmcp-feedback-count]" ).textContent, "1" );
} );

test( "single-workflow opportunity measurements retain the Site measured label", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_opportunity_signals", {
		items: [ {
			affected_workflows: 1,
			category: "inventory_gap",
			evidence_status: "verified",
			feedback_count: 0,
			measured_context: { eligible_product_count: { status: "verified", value: 0 } },
			measurement_scope: {
				kind: "single_workflow",
				occurred_at: "2026-08-31 12:00:00",
				source: "site_observed",
				workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE",
			},
			observed_count: 1,
			sources: { agent_reported: 0, site_observed: 1 },
			title: "Matching product unavailable",
		} ],
	} );

	const detail = fixture.root.querySelector( "[data-wmcp-opportunities] .wmcp-opportunity-card" );
	assert.match( detail.textContent, /Site measuredeligible products: 0 \(Verified\)/ );
	assert.match( detail.textContent, /Measurement scopeSingle workflow · Workflow 01M18QH3… · Site observed/ );
	assert.doesNotMatch( detail.textContent, /Latest workflow sample|Not aggregate/ );
} );

test( "multiple affected workflows force a latest-sample label even with inconsistent scope kind", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_opportunity_signals", {
		items: [ {
			affected_workflows: 3,
			category: "demand_gap",
			feedback_count: 1,
			measured_context: { search_refinement_count: { status: "verified", value: 2 } },
			measurement_scope: {
				kind: "single_workflow",
				occurred_at: "2026-08-31 12:00:00",
				source: "agent_reported",
				workflow_id: "short-id",
			},
			observed_count: 0,
			sources: { agent_reported: 1, site_observed: 0 },
			title: "Repeated refinements",
		} ],
	} );

	const detail = fixture.root.querySelector( "[data-wmcp-opportunities] .wmcp-opportunity-card" );
	assert.match( detail.textContent, /Latest workflow samplesearch refinements: 2 \(Verified\)/ );
	assert.match( detail.textContent, /Sample scopeNot aggregate · Workflow short-id · Agent reported/ );
} );

test( "missing measurement scope is labeled unavailable instead of inventing a sample", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_opportunity_signals", {
		items: [ {
			affected_workflows: 4,
			category: "capability_gap",
			feedback_count: 2,
			measured_context: {},
			measurement_scope: null,
			observed_count: 0,
			sources: { agent_reported: 1, site_observed: 0 },
			title: "Back-in-stock capability requested",
		} ],
	} );

	const detail = fixture.root.querySelector( "[data-wmcp-opportunities] .wmcp-opportunity-card" );
	assert.match( detail.textContent, /Measurement unavailableNo site-computed measurement returned/ );
	assert.match( detail.textContent, /Measurement scopeNo workflow measurement sample returned/ );
	assert.doesNotMatch( detail.textContent, /Latest workflow sample|Not aggregate|Site measured|Single workflow/ );
} );

test( "site-observed opportunities never imply agent testimony or lost revenue", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_opportunity_signals", {
		items: [ {
			affected_workflows: 1,
			category: "demand_gap",
			evidence_status: "verified",
			feedback_count: 0,
			measured_context: { eligible_product_count: { status: "verified", value: 0 } },
			observed_count: 1,
			related_product_ids: [],
			signal_key: "zero-results",
			sources: { agent_reported: 0, site_observed: 1 },
			title: "No IPX5 waterproof backpack under $100",
		} ],
	} );

	const card = signalCards( fixture.root )[ 0 ];
	assert.equal( card.dataset.signalSource, "site-observed" );
	assert.match( card.textContent, /Site observed/ );
	assert.doesNotMatch( card.textContent, /Agent reported|lost revenue/i );
	assert.equal( fixture.root.querySelector( "[data-wmcp-feedback-count]" ).textContent, "0" );
} );

test( "opportunity values are rendered only as text", () => {
	const fixture = dashboardFixture();
	const hostileTitle = '<img src=x onerror="stealSession()">';
	const hostileAction = '</dd><script>stealSession()</script>';
	const hostileWorkflow = '<svg onload="stealSession()">workflow-secret-suffix';

	render( fixture.window, "get_opportunity_signals", {
		items: [ {
			affected_workflows: 1,
			category: "experience_friction",
			evidence_status: "linked",
			feedback_count: 1,
			measured_context: {},
			measurement_scope: {
				kind: "latest_workflow_sample",
				occurred_at: '</dd><script>stealSession()</script>',
				source: '<img src=x onerror="stealSession()">',
				workflow_id: hostileWorkflow,
			},
			observed_count: 0,
			signal_key: "hostile",
			sources: { agent_reported: 1, site_observed: 0 },
			suggested_owner_action: hostileAction,
			title: hostileTitle,
		} ],
	} );

	const opportunities = fixture.root.querySelector( "[data-wmcp-opportunities]" );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === hostileTitle ) );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value.toLowerCase().includes( "<script>stealsession()" ) ) );
	assert.match( opportunities.textContent, /Source unavailable/ );
	assert.doesNotMatch( opportunities.textContent, new RegExp( hostileWorkflow.replace( /[.*+?^${}()|[\]\\]/g, "\\$&" ) ) );
	assert.equal( opportunities.querySelectorAll( "img" ).length, 0 );
	assert.equal( opportunities.querySelectorAll( "script" ).length, 0 );
	assert.equal( fixture.document.innerHTMLWrites.length, 0 );
} );

test( "Workflow Replay separates site-observed opportunities from agent feedback", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "explain_agent_workflow", {
		agent_feedback: [ { evidence_status: "linked" } ],
		capability_gaps: [],
		commerce_outcome: { orders: [] },
		explanation: "The journey completed with a recorded constraint.",
		opportunity_signals: [ { evidence_status: "verified" } ],
		timeline: [],
		truncated: false,
		workflow: { status: "completed", workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE" },
	} );

	assert.match( fixture.root.querySelector( '[data-evidence="opportunities"]' ).textContent, /1 site-observed signal · Site-observed evidence verified/ );
	assert.match( fixture.root.querySelector( '[data-evidence="feedback"]' ).textContent, /1 agent-reported item · Agent report linked to workflow evidence/ );
} );

test( "Signals queue reports a clear empty state after all monitoring evidence loads", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_agent_analytics_overview", {
		tool_calls: { denied: 0, denial_rate: 0, failed: 0, failure_rate: 0 },
	} );
	render( fixture.window, "get_tool_health", { items: [] } );
	render( fixture.window, "get_capability_gaps", { items: [] } );

	const signals = fixture.root.querySelector( "[data-wmcp-signals]" );
	assert.equal( signalCards( fixture.root ).length, 0 );
	assert.equal( signals.querySelector( ".wmcp-empty-state" ).textContent, "No recorded signals in this loaded scope." );
} );

test( "Signals queue renders hostile tool, code, and capability values only as text", () => {
	const fixture = dashboardFixture();
	const hostileTool = '<img src=x onerror="stealSession()">';
	const hostileCode = "</dd><script>stealSession()</script>";
	const hostileCapability = '<svg onload="stealSession()">';

	render( fixture.window, "get_capability_gaps", {
		items: [ { affected_workflows: 1, capability: hostileCapability, requests: 1, status: "open" } ],
	} );
	render( fixture.window, "get_tool_health", {
		items: [ {
			calls: 1,
			denied: 0,
			failure_rate: 1,
			failed: 1,
			tool_name: hostileTool,
			top_errors: [ { code: hostileCode, count: 1 } ],
		} ],
	} );

	const signals = fixture.root.querySelector( "[data-wmcp-signals]" );
	assert.equal( signalCards( fixture.root ).length, 2 );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === hostileTool ) );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value.includes( hostileCode ) ) );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === hostileCapability ) );
	assert.equal( signals.querySelectorAll( "img" ).length, 0 );
	assert.equal( signals.querySelectorAll( "script" ).length, 0 );
	assert.equal( signals.querySelectorAll( "svg" ).length, 0 );
	assert.equal( fixture.document.innerHTMLWrites.length, 0 );
} );

test( "Signals queue labels mixed failure and denial codes as tool-level terminal evidence", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_tool_health", {
		items: [ {
			calls: 4,
			denied: 1,
			denial_rate: 0.25,
			failed: 1,
			failure_rate: 0.25,
			tool_name: "checkout_handoff",
			top_errors: [
				{ code: "catalog_timeout", count: 1 },
				{ code: "policy_denied", count: 1 },
			],
		} ],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 2 );
	cards.forEach( ( card ) => {
		assert.match( card.textContent, /Top terminal codes \(all outcomes\)/ );
		assert.match( card.textContent, /catalog_timeout × 1 · policy_denied × 1/ );
	} );
} );

test( "Signals queue orders critical evidence before warnings using one disclosed classifier", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_tool_health", {
		items: [
			{
				calls: 20,
				denied: 0,
				failed: 2,
				failure_rate: 0.1,
				tool_name: "warning_tool",
				top_errors: [ { code: "warning_code", count: 2 } ],
			},
			{
				calls: 1,
				denied: 0,
				failed: 1,
				failure_rate: 1,
				tool_name: "critical_tool",
				top_errors: [ { code: "critical_code", count: 1 } ],
			},
		],
	} );

	const cards = signalCards( fixture.root );
	assert.equal( cards.length, 2 );
	assert.match( cards[ 0 ].textContent, /critical_tool/ );
	assert.equal( cards[ 0 ].classList.contains( "wmcp-signal-critical" ), true );
	assert.match( cards[ 0 ].textContent, /Critical at 3 failed calls or a 25% failure rate/ );
	assert.match( cards[ 1 ].textContent, /warning_tool/ );
	assert.equal( cards[ 1 ].classList.contains( "wmcp-signal-warning" ), true );
} );

test( "Signals queue reaches the same deterministic order regardless of response arrival order", () => {
	const first = dashboardFixture();
	const second = dashboardFixture();
	const overview = { tool_calls: { denied: 1, denial_rate: 0.2, failed: 1, failure_rate: 0.2 } };
	const health = {
		items: [ {
			denied: 1,
			denial_rate: 0.2,
			failed: 1,
			failure_rate: 0.2,
			tool_name: "compare_products",
			top_errors: [ { code: "policy_denied", count: 1 } ],
		} ],
	};
	const gaps = {
		items: [ { affected_workflows: 1, capability: "gift_wrapping", requests: 2, status: "open" } ],
	};

	render( first.window, "get_agent_analytics_overview", overview );
	render( first.window, "get_tool_health", health );
	render( first.window, "get_capability_gaps", gaps );
	render( second.window, "get_capability_gaps", gaps );
	render( second.window, "get_tool_health", health );
	render( second.window, "get_agent_analytics_overview", overview );

	const firstCards = signalCards( first.root );
	const secondCards = signalCards( second.root );
	assert.deepEqual( firstCards.map( ( card ) => card.dataset.signalType ), [ "reliability-signal", "denial-signal", "opportunity-gap" ] );
	assert.deepEqual( secondCards.map( ( card ) => card.dataset.signalType ), firstCards.map( ( card ) => card.dataset.signalType ) );
	assert.deepEqual( secondCards.map( ( card ) => card.textContent ), firstCards.map( ( card ) => card.textContent ) );
} );

test( "Signals queue discloses truncated and paginated evidence instead of claiming complete scope", () => {
	const fixture = dashboardFixture();

	render( fixture.window, "get_agent_analytics_overview", {
		tool_calls: { denied: 0, denial_rate: 0, failed: 0, failure_rate: 0 },
	} );
	render( fixture.window, "get_tool_health", { items: [], truncated: true } );
	render( fixture.window, "get_capability_gaps", { has_more: true, items: [] } );

	const signals = fixture.root.querySelector( "[data-wmcp-signals]" );
	assert.equal( signalCards( fixture.root ).length, 0 );
	assert.match( signals.textContent, /No recorded signals appear in the returned evidence/ );
	assert.match( signals.textContent, /tool-health results were truncated/ );
	assert.match( signals.textContent, /more capability-gap groups are available/ );
	assert.doesNotMatch( signals.textContent, /No recorded signals in this loaded scope/ );
} );

test( "Signals queue clears the previous snapshot before a refresh with a failed source", async () => {
	const toolNames = [
		"get_agent_analytics_overview",
		"get_agent_conversion_funnel",
		"query_agent_workflows",
		"get_tool_health",
		"get_opportunity_signals",
	];
	const fetch = queuedFetch( [
		jsonResponse( {
			manifest_revision: "revision-2",
			schema_version: "1",
			session: { csrf_token: "csrf" },
			tools: toolNames.map( ( name ) => ( { name } ) ),
			workflow_id: "01M18QH3GTR0AJB8NCGN35R5CE",
		} ),
		jsonResponse( { ok: true, result: { tool_calls: { denied: 0, failed: 0 } } } ),
		jsonResponse( { ok: true, result: { stages: [] } } ),
		jsonResponse( { ok: true, result: { items: [] } } ),
		jsonResponse( { error: { message: "Health unavailable" }, ok: false }, { status: 503 } ),
		jsonResponse( { ok: true, result: { items: [] } } ),
	] );
	const fixture = dashboardFixture( { fetch } );

	render( fixture.window, "get_tool_health", {
		items: [ {
			failed: 4,
			failure_rate: 1,
			tool_name: "stale_tool",
			top_errors: [ { code: "stale_code", count: 4 } ],
		} ],
	} );
	assert.match( fixture.root.querySelector( "[data-wmcp-signals]" ).textContent, /stale_tool/ );

	const button = fixture.root.querySelector( "[data-wmcp-load-dashboard]" );
	button.click();
	assert.equal( signalCards( fixture.root ).length, 0 );
	assert.doesNotMatch( fixture.root.querySelector( "[data-wmcp-signals]" ).textContent, /stale_tool|stale_code/ );

	await waitFor( () => fetch.calls.length === 6 && button.disabled === false, "dashboard refresh did not settle" );
	assert.equal( signalCards( fixture.root ).length, 0 );
	assert.doesNotMatch( fixture.root.querySelector( "[data-wmcp-signals]" ).textContent, /stale_tool|stale_code/ );
	assert.match( fixture.root.querySelector( "[data-wmcp-error]" ).textContent, /1 dashboard query could not be completed/ );
} );
