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

const SCRIPT = path.resolve( __dirname, "../../plugin/wmcp-agentsnr/assets/js/storefront-ui.js" );

function panel( document, stage, children ) {
	return element( document, "section", {
		dataset: { wmcpPanel: stage },
		hidden: [ "feedback", "gap" ].includes( stage ),
		children,
	} );
}

function storefrontFixture() {
	const document = new MockDocument();
	const stages = Object.fromEntries( [ "search", "comparison", "policy", "cart", "checkout" ].map( ( stage ) => [
		stage,
		element( document, "li", {
			dataset: { stage },
			children: [ element( document, "small", { text: "Awaiting signal" } ) ],
		} ),
	] ) );
	const checkoutLink = element( document, "a", {
		dataset: { wmcpCheckoutLink: "" },
		hidden: true,
	} );
	const guide = element( document, "section", {
		dataset: { wmcpAgentGuide: "", state: "published" },
		children: [
			element( document, "strong", { dataset: { wmcpGuideVersion: "" }, text: "1.0" } ),
			element( document, "span", { dataset: { wmcpGuideStatus: "" }, text: "Published" } ),
			element( document, "p", { dataset: { wmcpFeedbackPolicy: "" } } ),
		],
	} );
	const opportunity = element( document, "aside", {
		dataset: { wmcpSearchOpportunity: "" },
		hidden: true,
		children: [
			element( document, "p", { dataset: { wmcpOpportunitySummary: "" } } ),
			element( document, "p", { dataset: { wmcpFeedbackHint: "" }, hidden: true } ),
		],
	} );
	const feedbackPanel = panel( document, "feedback", [
		element( document, "span", { dataset: { wmcpFeedbackTrust: "" } } ),
		element( document, "span", { dataset: { wmcpFeedbackEvidenceStatus: "" } } ),
		element( document, "h3", { id: "wmcp-feedback-title" } ),
		element( document, "p", { dataset: { wmcpFeedbackMessage: "" } } ),
		element( document, "dd", { dataset: { wmcpFeedbackMetrics: "" } } ),
		element( document, "dd", { dataset: { wmcpFeedbackAction: "" } } ),
	] );
	const root = element( document, "main", {
		className: "wmcp-field",
		dataset: { wmcpSurface: "storefront" },
		children: [
			guide,
			...Object.values( stages ),
			panel( document, "search", [
				element( document, "span", { dataset: { wmcpResultCount: "" } } ),
				element( document, "h3", { id: "wmcp-search-results-title" } ),
				element( document, "div", { dataset: { wmcpSearchResults: "" } } ),
				opportunity,
			] ),
			panel( document, "comparison", [
				element( document, "h3", { id: "wmcp-comparison-title" } ),
				element( document, "div", { dataset: { wmcpComparison: "" } } ),
			] ),
			panel( document, "policy", [
				element( document, "h3", { id: "wmcp-policy-title" } ),
				element( document, "div", { dataset: { wmcpPolicy: "" } } ),
			] ),
			panel( document, "cart", [
				element( document, "h3", { id: "wmcp-cart-title" } ),
				element( document, "span", { dataset: { wmcpCartCount: "" } } ),
				element( document, "div", { dataset: { wmcpCart: "" } } ),
			] ),
			panel( document, "checkout", [
				element( document, "h3", { id: "wmcp-checkout-title" } ),
				element( document, "p", { dataset: { wmcpCheckoutMessage: "" } } ),
				checkoutLink,
			] ),
			panel( document, "gap", [
				element( document, "p", { dataset: { wmcpGapMessage: "" } } ),
			] ),
			feedbackPanel,
		],
	} );
	document.body.append( root );
	const window = new MockWindow( document, { origin: "https://storefront.test" } );
	runBrowserScript( SCRIPT, { document, window } );
	return { checkoutLink, document, feedbackPanel, guide, opportunity, root, stages, window };
}

function render( window, tool, result, response = {} ) {
	window.dispatchEvent( new MockCustomEvent( "wmcp:ui-update", {
		detail: {
			response: Object.assign( { result, tool: { name: tool } }, response ),
			tool,
		},
	} ) );
}

function cart( revision, quantity = 1 ) {
	return {
		currency: "USD",
		item_count: quantity,
		items: [ {
			line_total: 109 * quantity,
			name: "AlpineFlow 24 Pack",
			product_id: 101,
			quantity,
		} ],
		revision,
		subtotal: 109 * quantity,
	};
}

test( "late earlier-stage results do not regress the storefront workflow rail", () => {
	const fixture = storefrontFixture();

	render( fixture.window, "compare_products", {
		criteria: [],
		matrix: [],
		products: [],
	} );
	render( fixture.window, "search_products", {
		products: [],
		result_count: 0,
	} );

	assert.equal( fixture.stages.search.classList.contains( "wmcp-is-complete" ), true );
	assert.equal( fixture.stages.comparison.classList.contains( "wmcp-is-complete" ), true );
	assert.equal( fixture.stages.search.classList.contains( "wmcp-is-active" ), false );
	assert.equal( fixture.stages.comparison.classList.contains( "wmcp-is-active" ), true );
} );

test( "a cart mutation revokes a previously prepared checkout handoff", () => {
	const fixture = storefrontFixture();
	const cartCount = fixture.root.querySelector( "[data-wmcp-cart-count]" );

	render( fixture.window, "prepare_checkout_handoff", {
		cart: cart( "cart-rev-1" ),
		checkout_url: "/checkout/",
		message: "Review the cart and place the demo order yourself.",
	} );

	assert.equal( fixture.checkoutLink.hidden, false );
	assert.equal( fixture.checkoutLink.href, "https://storefront.test/checkout/" );
	assert.equal( fixture.root.querySelector( "#wmcp-checkout-title" ).textContent, "Checkout handoff ready" );
	assert.equal( fixture.stages.checkout.classList.contains( "wmcp-is-complete" ), true );
	assert.equal( cartCount.textContent, "1" );
	assert.equal( cartCount.getAttribute( "aria-label" ), "1 item in cart" );

	render( fixture.window, "add_to_cart", {
		cart: cart( "cart-rev-2", 2 ),
	} );

	assert.equal( fixture.checkoutLink.hidden, true );
	assert.equal( fixture.checkoutLink.href, "" );
	assert.equal( fixture.root.querySelector( "#wmcp-checkout-title" ).textContent, "Checkout handoff required" );
	assert.equal( fixture.stages.checkout.classList.contains( "wmcp-is-complete" ), false );
	assert.equal( fixture.stages.cart.classList.contains( "wmcp-is-active" ), true );
	assert.equal( cartCount.textContent, "2" );
	assert.equal( cartCount.getAttribute( "aria-label" ), "2 items in cart" );
	assert.equal(
		fixture.root.querySelector( "[data-wmcp-checkout-message]" ).textContent,
		"The cart changed. Prepare a new checkout handoff after reviewing it."
	);
} );

test( "private manifest snapshots hydrate the cart badge and ignore invalid counts", () => {
	const fixture = storefrontFixture();
	const cartCount = fixture.root.querySelector( "[data-wmcp-cart-count]" );

	fixture.window.dispatchEvent( new MockCustomEvent( "wmcp:manifest-ready", {
		detail: { cart: { item_count: 1 } },
	} ) );

	assert.equal( cartCount.textContent, "1" );
	assert.equal( cartCount.getAttribute( "aria-label" ), "1 item in cart" );

	for ( const detail of [
		{},
		{ cart: {} },
		{ cart: { item_count: -1 } },
		{ cart: { item_count: 1.5 } },
		{ cart: { item_count: "2" } },
	] ) {
		fixture.window.dispatchEvent( new MockCustomEvent( "wmcp:manifest-ready", { detail } ) );
	}

	assert.equal( cartCount.textContent, "1" );
	assert.equal( cartCount.getAttribute( "aria-label" ), "1 item in cart" );

	fixture.window.dispatchEvent( new MockCustomEvent( "wmcp:manifest-ready", {
		detail: { cart: { item_count: 3 } },
	} ) );

	assert.equal( cartCount.textContent, "3" );
	assert.equal( cartCount.getAttribute( "aria-label" ), "3 items in cart" );
} );

test( "policy evidence keeps malicious content as text and uses a fixed published-source label", () => {
	const fixture = storefrontFixture();
	const maliciousExcerpt = '<img src=x onerror="globalThis.compromised=true">Returns last 30 days.';
	const maliciousType = "returns<script>stealSession()</script>";

	render( fixture.window, "get_store_policy", {
		policies: [ {
			effective_date: "2026-08-30",
			evidence_excerpt: maliciousExcerpt,
			facts: { return_days: 30 },
			type: maliciousType,
			url: "https://storefront.test/returns/",
		} ],
	} );

	const policy = fixture.root.querySelector( "[data-wmcp-policy]" );
	const link = policy.querySelector( "a" );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === maliciousExcerpt ) );
	assert.equal( link.textContent, "Open published policy source →" );
	assert.equal( link.href, "https://storefront.test/returns/" );
	assert.equal( fixture.document.innerHTMLWrites.length, 0 );
	assert.equal( fixture.root.querySelectorAll( "script" ).length, 0 );
	assert.equal( fixture.root.querySelectorAll( "img" ).length, 0 );
} );

test( "unsafe policy source URLs are omitted instead of becoming executable links", () => {
	const fixture = storefrontFixture();

	render( fixture.window, "get_store_policy", {
		policies: [ {
			effective_date: "2026-08-30",
			evidence_excerpt: "Published return facts.",
			type: "returns",
			url: "javascript:globalThis.compromised=true",
		} ],
	} );

	assert.equal( fixture.root.querySelector( "[data-wmcp-policy]" ).querySelector( "a" ), null );
} );

test( "Agent Guide marks discovery as read without changing commerce progress", () => {
	const fixture = storefrontFixture();

	render( fixture.window, "get_agent_guide", {
		feedback: {
			recommended_when: [ "zero_results", "human_handoff" ],
		},
		version: "1.1",
	} );

	assert.equal( fixture.guide.dataset.state, "read" );
	assert.equal( fixture.guide.classList.contains( "wmcp-is-updated" ), true );
	assert.equal( fixture.guide.querySelector( "[data-wmcp-guide-version]" ).textContent, "1.1" );
	assert.equal( fixture.guide.querySelector( "[data-wmcp-guide-status]" ).textContent, "Read by agent" );
	assert.match( fixture.guide.querySelector( "[data-wmcp-feedback-policy]" ).textContent, /zero results, human handoff/ );
	assert.equal( Object.values( fixture.stages ).some( ( stage ) => stage.classList.contains( "wmcp-is-complete" ) ), false );
} );

test( "zero-result search shows a site-observed opportunity and bounded feedback hint", () => {
	const fixture = storefrontFixture();

	render(
		fixture.window,
		"search_products",
		{
			opportunity_signals: [ {
				recorded: true,
				signal_id: "01ARZ3NDEKTSV4RRFFQ69G5FAV",
				summary: "No IPX5 waterproof backpack matched under $100.",
			} ],
			products: [],
			result_count: 0,
		},
		{
			next_actions: [ {
				reason: "zero_results",
				tool: "report_agent_feedback",
			} ],
		}
	);

	assert.equal( fixture.opportunity.hidden, false );
	assert.equal( fixture.opportunity.dataset.state, "recorded" );
	assert.match( fixture.opportunity.querySelector( "[data-wmcp-opportunity-summary]" ).textContent, /No IPX5/ );
	const hint = fixture.opportunity.querySelector( "[data-wmcp-feedback-hint]" );
	assert.equal( hint.hidden, false );
	assert.match( hint.textContent, /Feedback invited · Zero Results/ );
} );

test( "zero results without a recorded server signal keep the opportunity notice hidden", () => {
	const fixture = storefrontFixture();

	render(
		fixture.window,
		"search_products",
		{ products: [], result_count: 0 },
		{
			next_actions: [ { reason: "zero_results", tool: "report_agent_feedback" } ],
		}
	);

	assert.equal( fixture.opportunity.hidden, true );
	assert.equal( fixture.opportunity.querySelector( "[data-wmcp-opportunity-summary]" ).textContent, "—" );
} );

test( "a later ordinary search clears prior opportunity evidence and feedback text", () => {
	const fixture = storefrontFixture();

	render(
		fixture.window,
		"search_products",
		{
			opportunity_signals: [ {
				recorded: true,
				signal_id: "01ARZ3NDEKTSV4RRFFQ69G5FAV",
				summary: "No IPX5 waterproof backpack matched under $100.",
			} ],
			products: [],
			result_count: 0,
		},
		{ next_actions: [ { reason: "zero_results", tool: "report_agent_feedback" } ] }
	);
	assert.equal( fixture.opportunity.hidden, false );

	render( fixture.window, "search_products", {
		products: [ {
			currency: "USD",
			id: 18,
			name: "HarborLite 16 Pack",
			price: 69,
			url: "https://storefront.test/product/harborlite-16/",
		} ],
		result_count: 4,
	} );

	assert.equal( fixture.opportunity.hidden, true );
	assert.equal( fixture.opportunity.querySelector( "[data-wmcp-opportunity-summary]" ).textContent, "—" );
	assert.equal( fixture.opportunity.querySelector( "[data-wmcp-feedback-hint]" ).hidden, true );
	assert.equal( fixture.opportunity.querySelector( "[data-wmcp-feedback-hint]" ).textContent, "—" );
} );

test( "feedback receipt keeps agent testimony separate from site-computed metrics", () => {
	const fixture = storefrontFixture();
	const hostileMessage = '<img src=x onerror="stealSession()">';

	render( fixture.window, "search_products", { products: [], result_count: 0 } );
	render( fixture.window, "report_agent_feedback", {
		evidence_status: "linked",
		measured_context: {
			checkout_conversion: { status: "pending", value: null },
			eligible_product_count: { status: "verified", value: 2 },
			highest_matching_water_rating: { status: "verified", value: "IPX4" },
			search_refinement_count: { status: "verified", value: 2 },
		},
		message: hostileMessage,
		suggested_owner_action: "improve_product_coverage",
		trust: "agent_reported",
	} );

	assert.equal( fixture.feedbackPanel.hidden, false );
	assert.equal( fixture.feedbackPanel.dataset.feedbackTrust, "agent_reported" );
	assert.equal( fixture.feedbackPanel.dataset.evidenceStatus, "linked" );
	assert.equal( fixture.feedbackPanel.querySelector( "[data-wmcp-feedback-trust]" ).textContent, "Agent reported" );
	assert.equal( fixture.feedbackPanel.querySelector( "[data-wmcp-feedback-evidence-status]" ).textContent, "Site evidence linked" );
	assert.match( fixture.feedbackPanel.querySelector( "[data-wmcp-feedback-metrics]" ).textContent, /eligible products: 2 \(Verified\)/ );
	assert.match( fixture.feedbackPanel.querySelector( "[data-wmcp-feedback-metrics]" ).textContent, /checkout converted: — \(Pending\)/ );
	assert.equal( fixture.feedbackPanel.querySelector( "[data-wmcp-feedback-action]" ).textContent, "Improve Product Coverage" );
	assert.ok( fixture.document.textContentWrites.some( ( write ) => write.value === hostileMessage ) );
	assert.equal( fixture.root.querySelectorAll( "img" ).length, 0 );
	assert.equal( fixture.document.innerHTMLWrites.length, 0 );
	assert.equal( fixture.stages.search.classList.contains( "wmcp-is-active" ), true );
} );
