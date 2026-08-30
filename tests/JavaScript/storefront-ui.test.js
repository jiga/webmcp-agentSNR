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

const SCRIPT = path.resolve( __dirname, "../../plugin/wmcp-agentops/assets/js/storefront-ui.js" );

function panel( document, stage, children ) {
	return element( document, "section", {
		dataset: { wmcpPanel: stage },
		hidden: stage === "gap",
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
	const root = element( document, "main", {
		className: "wmcp-field",
		dataset: { wmcpSurface: "storefront" },
		children: [
			...Object.values( stages ),
			panel( document, "search", [
				element( document, "span", { dataset: { wmcpResultCount: "" } } ),
				element( document, "h3", { id: "wmcp-search-results-title" } ),
				element( document, "div", { dataset: { wmcpSearchResults: "" } } ),
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
		],
	} );
	document.body.append( root );
	const window = new MockWindow( document, { origin: "https://storefront.test" } );
	runBrowserScript( SCRIPT, { document, window } );
	return { checkoutLink, document, root, stages, window };
}

function render( window, tool, result, extra = {} ) {
	window.dispatchEvent( new MockCustomEvent( "wmcp:ui-update", {
		detail: Object.assign( {
			response: { result, tool: { name: tool } },
			tool,
		}, extra ),
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

	render( fixture.window, "checkout_handoff", {
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
