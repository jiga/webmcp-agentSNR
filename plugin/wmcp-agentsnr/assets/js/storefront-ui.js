/*
 * Storefront visible-state adapter for WebMCP runtime events.
 */
( function () {
	"use strict";

	const root = document.querySelector( '.wmcp-field[data-wmcp-surface="storefront"]' );
	if ( ! root ) {
		return;
	}

	const stageForTool = {
		add_to_cart: "cart",
		prepare_checkout_handoff: "checkout",
		compare_products: "comparison",
		get_cart: "cart",
		get_product: "search",
		get_store_policy: "policy",
		remove_from_cart: "cart",
		report_capability_gap: "gap",
		report_agent_feedback: "feedback",
		search_products: "search",
		update_cart_quantity: "cart",
	};
	const orderedStages = [ "search", "comparison", "policy", "cart", "checkout" ];
	const measuredMetricLabels = {
		attributed_order_count: "attributed orders",
		checkout_conversion: "checkout converted",
		checkout_handoff: "checkout handoff",
		eligible_product_count: "eligible products",
		highest_matching_water_rating: "highest water rating",
		net_attributed_value: "net attributed",
		paid_order_value: "paid order value",
		search_refinement_count: "search refinements",
	};

	function one( selector, scope = root ) {
		return scope.querySelector( selector );
	}

	function all( selector, scope = root ) {
		return Array.from( scope.querySelectorAll( selector ) );
	}

	function text( target, value ) {
		if ( target ) {
			target.textContent = value === null || value === undefined || value === "" ? "—" : String( value );
		}
	}

	function record( value ) {
		return value && typeof value === "object" && ! Array.isArray( value ) ? value : {};
	}

	function list( value ) {
		return Array.isArray( value ) ? value : [];
	}

	function safeUrl( value ) {
		try {
			const url = new URL( String( value || "" ), window.location.origin );
			return [ "http:", "https:" ].includes( url.protocol ) ? url.href : "";
		} catch {
			return "";
		}
	}

	function money( value, currency = "USD" ) {
		const number = Number( value );
		if ( ! Number.isFinite( number ) ) {
			return "—";
		}
		try {
			return new Intl.NumberFormat( undefined, { currency: currency || "USD", style: "currency" } ).format( number );
		} catch {
			return `${ currency || "$" } ${ number.toFixed( 2 ) }`;
		}
	}

	function pretty( key ) {
		return String( key || "" ).replaceAll( "_", " ").replace( /\b\w/g, ( value ) => value.toUpperCase() );
	}

	function signalPanel( stage ) {
		const panel = one( `[data-wmcp-panel="${ stage }"]` );
		if ( ! panel ) {
			return;
		}
		panel.hidden = false;
		panel.classList.remove( "wmcp-is-updated" );
		window.requestAnimationFrame( () => panel.classList.add( "wmcp-is-updated" ) );
	}

	function updateRail( stage, tool ) {
		if ( ! orderedStages.includes( stage ) ) {
			return;
		}
		const stageIndex = orderedStages.indexOf( stage );
		const currentIndex = orderedStages.findIndex( ( name ) => one( `[data-stage="${ name }"]` )?.classList.contains( "wmcp-is-active" ) );
		const cartChanged = [ "add_to_cart", "remove_from_cart", "update_cart_quantity" ].includes( tool );
		const activeIndex = cartChanged ? stageIndex : Math.max( currentIndex, stageIndex );
		orderedStages.forEach( ( name ) => {
			const item = one( `[data-stage="${ name }"]` );
			if ( ! item ) {
				return;
			}
			item.classList.toggle( "wmcp-is-active", orderedStages[ activeIndex ] === name );
			if ( cartChanged && orderedStages.indexOf( name ) > stageIndex ) {
				item.classList.remove( "wmcp-is-complete" );
				if ( name === "checkout" ) {
					text( one( "small", item ), "Human confirmation required" );
				}
			}
			if ( name === stage ) {
				item.classList.add( "wmcp-is-complete" );
				text( one( "small", item ), `${ tool } completed` );
			}
		} );
	}

	function markProducts( products, comparison = false ) {
		const ids = new Set( list( products ).map( ( product ) => String( product?.id || product?.product_id || "" ) ) );
		all( ".wmcp-product-card" ).forEach( ( card ) => {
			const matched = ids.has( card.dataset.productId );
			card.classList.toggle( comparison ? "wmcp-is-compared" : "wmcp-is-match", matched );
			if ( ! comparison ) {
				const flag = one( "[data-match-flag]", card );
				if ( flag ) {
					flag.hidden = ! matched;
				}
			}
		} );
	}

	function renderSearch( result ) {
		const products = list( result.products || ( result.product ? [ result.product ] : [] ) );
		const container = one( "[data-wmcp-search-results]" );
		const heading = one( "#wmcp-search-results-title" );
		text( one( "[data-wmcp-result-count]" ), `${ result.result_count ?? products.length } found` );
		text( heading, products.length ? `${ products.length } field matches` : "No matching products" );
		if ( ! container ) {
			return;
		}
		container.replaceChildren();
		if ( products.length === 0 ) {
			const empty = document.createElement( "p" );
			empty.className = "wmcp-empty-state";
			text( empty, "No public products matched the supplied facts. The standard catalog remains available." );
			container.append( empty );
			markProducts( [] );
			return;
		}

		const items = document.createElement( "ul" );
		items.className = "wmcp-result-list";
		products.forEach( ( product ) => {
			const item = document.createElement( "li" );
			const label = document.createElement( "strong" );
			const price = document.createElement( "span" );
			const url = safeUrl( product.url );
			if ( url ) {
				const link = document.createElement( "a" );
				link.href = url;
				text( link, product.name || `Product ${ product.id }` );
				label.append( link );
			} else {
				text( label, product.name || `Product ${ product.id }` );
			}
			text( price, money( product.price, product.currency ) );
			item.append( label, price );
			items.append( item );
		} );
		container.append( items );
		markProducts( products );
	}

	function feedbackAction( nextActions ) {
		return list( nextActions ).map( record ).find( ( action ) => {
			return ( action.tool || action.name ) === "report_agent_feedback";
		} ) || {};
	}

	function renderSearchOpportunity( result, nextActions ) {
		const container = one( "[data-wmcp-search-opportunity]" );
		if ( ! container ) {
			return;
		}

		const signals = list( result.opportunity_signals );
		const opportunity = record( result.opportunity_signal || result.opportunity || signals[ 0 ] );
		const action = feedbackAction( nextActions );
		const zeroResults = Number( result.result_count ) === 0;
		const recorded = opportunity.recorded === true || Boolean( opportunity.id || opportunity.signal_id || opportunity.signal_key );
		const hasOpportunity = recorded;
		const summary = opportunity.summary || opportunity.title || ( zeroResults
			? "No public product matched this search. The zero-result evidence is available to the current-session opportunity view."
			: "The site recorded a meaningful constraint in this search." );
		const hint = action.message || action.reason || action.signal_code || "";

		container.hidden = ! hasOpportunity;
		container.dataset.state = recorded ? "recorded" : zeroResults ? "observed" : "feedback-invited";
		text( one( "[data-wmcp-opportunity-summary]", container ), hasOpportunity ? summary : "" );
		const hintTarget = one( "[data-wmcp-feedback-hint]", container );
		if ( hintTarget ) {
			hintTarget.hidden = ! hint;
			text( hintTarget, hint ? `Feedback invited · ${ pretty( hint ) }` : "" );
		}
	}

	function comparisonValue( row, criterion ) {
		const value = row?.[ criterion ];
		if ( value === null || value === undefined || value === "" ) {
			return "Missing";
		}
		if ( criterion === "price" ) {
			return money( value );
		}
		if ( criterion === "capacity" ) {
			return `${ value } L`;
		}
		if ( criterion === "weight" ) {
			return `${ value } kg`;
		}
		if ( criterion === "laptop_size" ) {
			return `${ value } in`;
		}
		if ( criterion === "return_days" ) {
			return `${ value } days`;
		}
		return String( value );
	}

	function renderComparison( result ) {
		const products = list( result.products );
		const criteria = list( result.criteria );
		const matrix = list( result.matrix );
		const container = one( "[data-wmcp-comparison]" );
		text( one( "#wmcp-comparison-title" ), products.length ? `${ products.length } products compared` : "Comparison unavailable" );
		if ( ! container ) {
			return;
		}
		container.replaceChildren();
		if ( products.length === 0 ) {
			const empty = document.createElement( "p" );
			empty.className = "wmcp-empty-state";
			text( empty, "No comparison facts were returned." );
			container.append( empty );
			return;
		}

		const table = document.createElement( "table" );
		table.className = "wmcp-comparison-table";
		const head = document.createElement( "thead" );
		const headRow = document.createElement( "tr" );
		const factHead = document.createElement( "th" );
		factHead.scope = "col";
		text( factHead, "Fact" );
		headRow.append( factHead );
		products.forEach( ( product ) => {
			const cell = document.createElement( "th" );
			cell.scope = "col";
			text( cell, product.name || product.id );
			headRow.append( cell );
		} );
		head.append( headRow );
		table.append( head );
		const body = document.createElement( "tbody" );
		criteria.forEach( ( criterion ) => {
			const row = document.createElement( "tr" );
			const label = document.createElement( "th" );
			label.scope = "row";
			text( label, pretty( criterion ) );
			row.append( label );
			products.forEach( ( product ) => {
				const matrixRow = matrix.find( ( item ) => Number( item?.product_id ) === Number( product.id ) ) || {};
				const cell = document.createElement( "td" );
				text( cell, comparisonValue( matrixRow, criterion ) );
				row.append( cell );
			} );
			body.append( row );
		} );
		table.append( body );
		container.append( table );

		if ( result.score_explanation ) {
			const note = document.createElement( "p" );
			note.className = "wmcp-empty-state";
			text( note, result.score_explanation );
			container.append( note );
		}
		markProducts( products, true );
	}

	function renderPolicy( result ) {
		const policies = list( result.policies );
		const container = one( "[data-wmcp-policy]" );
		const first = record( policies[ 0 ] );
		text( one( "#wmcp-policy-title" ), policies.length ? `${ pretty( first.type ) } policy verified` : "No published policy returned" );
		if ( ! container ) {
			return;
		}
		container.replaceChildren();
		policies.forEach( ( policy ) => {
			const card = document.createElement( "div" );
			const excerpt = document.createElement( "p" );
			const source = document.createElement( "small" );
			const url = safeUrl( policy.url );
			card.className = "wmcp-policy-evidence";
			text( excerpt, policy.evidence_excerpt || "Published policy facts returned without an excerpt." );
			const days = policy.product_return_days || policy.facts?.return_days;
			text( source, `${ pretty( policy.type ) } · ${ days ? `${ days } day return window · ` : "" }effective ${ policy.effective_date || "date not supplied" }` );
			card.append( excerpt, source );
			if ( url ) {
				const link = document.createElement( "a" );
				link.className = "wmcp-text-link";
				link.href = url;
				text( link, "Open published policy source →" );
				card.append( link );
			}
			container.append( card );
		} );
	}

	function normalizeCart( result ) {
		if ( result.cart && typeof result.cart === "object" ) {
			return result.cart;
		}
		return result;
	}

	function renderCartCount( value ) {
		if ( ! Number.isInteger( value ) || value < 0 ) {
			return false;
		}

		all( "[data-wmcp-cart-count]" ).forEach( ( target ) => {
			text( target, value );
			target.setAttribute( "aria-label", `${ value } ${ value === 1 ? "item" : "items" } in cart` );
		} );
		return true;
	}

	function invalidateCheckout( message = "The cart changed. Prepare a new checkout handoff after reviewing it." ) {
		const link = one( "[data-wmcp-checkout-link]" );
		if ( link ) {
			link.hidden = true;
			link.removeAttribute( "href" );
		}
		text( one( "#wmcp-checkout-title" ), "Checkout handoff required" );
		text( one( "[data-wmcp-checkout-message]" ), message );
	}

	function renderCart( value, invalidateHandoff = false ) {
		const cart = record( normalizeCart( value ) );
		const items = list( cart.items );
		const container = one( "[data-wmcp-cart]" );
		const calculatedItemCount = items.reduce( ( total, item ) => total + Number( item.quantity || 0 ), 0 );
		const itemCount = Number.isInteger( cart.item_count ) && cart.item_count >= 0
			? cart.item_count
			: calculatedItemCount;
		text( one( "#wmcp-cart-title" ), items.length ? `${ itemCount } items in session cart` : "Session cart is empty" );
		renderCartCount( itemCount );
		if ( invalidateHandoff || items.length === 0 ) {
			invalidateCheckout( items.length === 0 ? "Add a purchasable product before preparing checkout." : undefined );
		}
		if ( ! container ) {
			return;
		}
		container.replaceChildren();
		if ( items.length === 0 ) {
			const empty = document.createElement( "p" );
			empty.className = "wmcp-empty-state";
			text( empty, "The current WooCommerce cart is empty." );
			container.append( empty );
			return;
		}

		const itemList = document.createElement( "ul" );
		itemList.className = "wmcp-cart-items";
		items.forEach( ( item ) => {
			const row = document.createElement( "li" );
			const name = document.createElement( "strong" );
			const quantity = document.createElement( "span" );
			const total = document.createElement( "span" );
			text( name, item.name || `Product ${ item.product_id }` );
			text( quantity, `×${ item.quantity || 0 }` );
			text( total, money( item.line_total, cart.currency ) );
			row.append( name, quantity, total );
			itemList.append( row );
		} );
		const summary = document.createElement( "p" );
		summary.className = "wmcp-cart-total";
		const label = document.createElement( "span" );
		const total = document.createElement( "strong" );
		text( label, "Subtotal" );
		text( total, money( cart.subtotal, cart.currency ) );
		summary.append( label, total );
		container.append( itemList, summary );
	}

	function renderCheckout( result ) {
		renderCart( result.cart || {} );
		text( one( "#wmcp-checkout-title" ), "Checkout handoff ready" );
		text( one( "[data-wmcp-checkout-message]" ), result.message || "Continue to the normal checkout for human review." );
		const link = one( "[data-wmcp-checkout-link]" );
		const url = safeUrl( result.checkout_url );
		if ( link && url ) {
			link.href = url;
			link.hidden = false;
		}
	}

	function renderGap( result ) {
		text( one( "[data-wmcp-gap-message]" ), result.message || "The unsupported request was recorded for the merchant. No notification or reservation was created." );
	}

	function rawMetricValue( key, value ) {
		if ( [ "net_attributed_value", "paid_order_value" ].includes( key ) ) {
			return list( value ).map( ( item ) => {
				const amount = record( item );
				return money( amount.value, amount.currency );
			} ).filter( ( item ) => item !== "—" ).join( " + " ) || "—";
		}
		if ( typeof value === "boolean" ) {
			return value ? "yes" : "no";
		}
		return value === null || value === undefined || value === "" ? "—" : String( value );
	}

	function metricValue( key, value ) {
		const envelope = record( value );
		if ( Object.hasOwn( envelope, "status" ) && Object.hasOwn( envelope, "value" ) ) {
			const measured = rawMetricValue( key, envelope.value );
			const status = pretty( envelope.status || "unknown" );
			return `${ measured } (${ status })`;
		}
		return rawMetricValue( key, value );
	}

	function measuredContextSummary( value ) {
		const context = record( value );
		return Object.keys( measuredMetricLabels ).filter( ( key ) => Object.hasOwn( context, key ) ).map( ( key ) => {
			return `${ measuredMetricLabels[ key ] }: ${ metricValue( key, context[ key ] ) }`;
		} ).join( " · " ) || "No site-computed measurement returned";
	}

	function evidenceStatus( value ) {
		return {
			linked: "Site evidence linked",
			unlinked: "Agent report only",
			verified: "Site evidence verified",
		}[ String( value || "" ) ] || "Evidence status unavailable";
	}

	function renderAgentFeedback( result ) {
		const panel = one( '[data-wmcp-panel="feedback"]' );
		if ( ! panel ) {
			return;
		}

		const trust = result.trust === "agent_reported" ? "Agent reported" : "Unverified agent report";
		panel.dataset.evidenceStatus = String( result.evidence_status || "unlinked" );
		panel.dataset.feedbackTrust = String( result.trust || "unverified" );
		text( one( "[data-wmcp-feedback-trust]", panel ), trust );
		text( one( "[data-wmcp-feedback-evidence-status]", panel ), evidenceStatus( result.evidence_status ) );
		text( one( "#wmcp-feedback-title", panel ), result.replayed ? "Agent feedback receipt confirmed" : "Agent feedback recorded" );
		text( one( "[data-wmcp-feedback-message]", panel ), result.message || "The feedback was recorded as agent-reported testimony and kept separate from site-verified facts." );
		text( one( "[data-wmcp-feedback-metrics]", panel ), measuredContextSummary( result.measured_context ) );
		text( one( "[data-wmcp-feedback-action]", panel ), pretty( result.suggested_owner_action || "No action suggested" ) );
	}

	function markGuideRead( result ) {
		const guide = one( "[data-wmcp-agent-guide]" );
		if ( ! guide ) {
			return;
		}
		guide.dataset.state = "read";
		guide.classList.remove( "wmcp-is-updated" );
		window.requestAnimationFrame( () => guide.classList.add( "wmcp-is-updated" ) );
		text( one( "[data-wmcp-guide-version]", guide ), result.guide_version || result.version || "1.1" );
		text( one( "[data-wmcp-guide-status]", guide ), "Read by agent" );
		const feedback = record( result.feedback || result.feedback_policy );
		const triggers = list( feedback.triggers || feedback.recommended_when );
		if ( triggers.length ) {
			const maximum = Number.isInteger( feedback.max_reports_per_workflow )
				? ` Maximum ${ feedback.max_reports_per_workflow } reports per workflow.`
				: "";
			text(
				one( "[data-wmcp-feedback-policy]", guide ),
				`Optional feedback after ${ triggers.map( ( trigger ) => String( trigger ).replaceAll( "_", " " ) ).join( ", " ) }.${ maximum }`
			);
		}
	}

	function renderUpdate( detail ) {
		const tool = detail.tool || detail.response?.tool?.name || "";
		const response = record( detail.response );
		const result = record( response.result );
		const nextActions = list( response.next_actions );
		if ( tool === "get_agent_guide" ) {
			markGuideRead( result );
			return;
		}
		const stage = stageForTool[ tool ];
		if ( ! stage ) {
			return;
		}

		if ( tool === "search_products" || tool === "get_product" ) {
			all( ".wmcp-product-card" ).forEach( ( card ) => card.classList.remove( "wmcp-is-compared" ) );
			const searchResult = tool === "get_product" ? { products: [ result.product || result ], result_count: 1 } : result;
			renderSearch( searchResult );
			renderSearchOpportunity( searchResult, nextActions );
		} else if ( tool === "compare_products" ) {
			renderComparison( result );
		} else if ( tool === "get_store_policy" ) {
			renderPolicy( result );
		} else if ( [ "get_cart", "add_to_cart", "remove_from_cart", "update_cart_quantity" ].includes( tool ) ) {
			renderCart( result, tool !== "get_cart" );
		} else if ( tool === "prepare_checkout_handoff" ) {
			renderCheckout( result );
		} else if ( tool === "report_capability_gap" ) {
			renderGap( result );
		} else if ( tool === "report_agent_feedback" ) {
			renderAgentFeedback( result );
		}

		updateRail( stage, tool );
		signalPanel( stage );
	}

	window.addEventListener( "wmcp:manifest-ready", ( event ) => {
		const manifest = record( event.detail );
		const cart = record( manifest.cart );
		renderCartCount( cart.item_count );
	} );
	window.addEventListener( "wmcp:ui-update", ( event ) => renderUpdate( event.detail || {} ) );
}() );
