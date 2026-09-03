/*
 * Agent SNR dashboard renderer and conventional human-triggered data loader.
 */
( function () {
	"use strict";

	const root = document.querySelector( '.wmcp-field[data-wmcp-surface="agentsnr"]' );
	const config = window.wmcpConfig || {};
	let currentManifest = null;
	let loading = false;
	let explanationRequest = 0;
	const monitorState = {
		overview: null,
		opportunities: null,
		toolHealth: null,
	};
	const measuredMetricLabels = {
		attributed_order_count: "attributed orders",
		checkout_conversion: "checkout converted",
		checkout_handoff: "checkout handoff",
		eligible_product_count: "eligible products",
		highest_matching_water_rating: "highest water rating",
		net_attributed_value: "net attributed",
		paid_order_value: "paid order value",
		search_refinement_count: "search refinements",
		viewed_product_value_context: "Viewed-product value context",
	};

	if ( ! root ) {
		return;
	}

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

	function getPath( source, path ) {
		return path.split( "." ).reduce( ( value, key ) => value?.[ key ], source );
	}

	function pretty( value ) {
		return String( value || "" ).replaceAll( "_", " ").replaceAll( ".", " · ").replace( /\b\w/g, ( character ) => character.toUpperCase() );
	}

	function percentage( value ) {
		const number = Number( value );
		return Number.isFinite( number ) ? `${ Math.round( number * 1000 ) / 10 }%` : "—";
	}

	function money( value, currency = "USD" ) {
		const number = Number( value );
		if ( ! Number.isFinite( number ) ) {
			return "—";
		}
		try {
			return new Intl.NumberFormat( undefined, { currency: currency || "USD", maximumFractionDigits: 2, style: "currency" } ).format( number );
		} catch {
			return `${ currency || "$" } ${ number.toFixed( 2 ) }`;
		}
	}

	function moneyByCurrency( values, field = "net" ) {
		const entries = Object.entries( record( values ) );
		if ( entries.length === 0 ) {
			return money( 0 );
		}
		return entries.map( ( [ currency, totals ] ) => {
			const amount = typeof totals === "number" ? totals : totals?.[ field ];
			return money( amount, currency );
		} ).join( " + " );
	}

	function dateTime( value ) {
		if ( ! value ) {
			return "—";
		}
		const normalized = String( value ).includes( "T" ) ? String( value ) : `${ value.replace( " ", "T" ) }Z`;
		const date = new Date( normalized );
		return Number.isNaN( date.valueOf() ) ? String( value ) : new Intl.DateTimeFormat( undefined, { dateStyle: "short", timeStyle: "medium" } ).format( date );
	}

	function timeOnly( value ) {
		if ( ! value ) {
			return "—";
		}
		const normalized = String( value ).includes( "T" ) ? String( value ) : `${ value.replace( " ", "T" ) }Z`;
		const date = new Date( normalized );
		return Number.isNaN( date.valueOf() ) ? String( value ).slice( -8 ) : new Intl.DateTimeFormat( undefined, { hour: "2-digit", minute: "2-digit", second: "2-digit" } ).format( date );
	}

	function stateTag( value ) {
		const tag = document.createElement( "span" );
		const bad = [ "failed", "denied", "cancelled", "abandoned", "expired", "disabled" ].includes( String( value ) );
		tag.className = `wmcp-state-tag ${ bad ? "wmcp-is-bad" : "wmcp-is-good" }`;
		text( tag, pretty( value ) );
		return tag;
	}

	function announce( message ) {
		text( one( "[data-wmcp-announcer]" ), message );
	}

	function showError( message ) {
		const target = one( "[data-wmcp-error]" );
		if ( target ) {
			target.hidden = ! message;
			text( target, message || "" );
		}
	}

	function metricNumber( value ) {
		if ( value === null || value === undefined || value === "" ) {
			return null;
		}
		const number = Number( value );
		return Number.isFinite( number ) && number >= 0 ? number : null;
	}

	function plural( value, singular, pluralForm = `${ singular }s` ) {
		return `${ value } ${ Number( value ) === 1 ? singular : pluralForm }`;
	}

	function compareText( left, right ) {
		const first = String( left );
		const second = String( right );
		return first === second ? 0 : first < second ? -1 : 1;
	}

	function outcomeEvidence( item, countField, rateField, noun ) {
		const count = metricNumber( item[ countField ] );
		const rate = metricNumber( item[ rateField ] );
		const parts = [];
		if ( count !== null ) {
			parts.push( plural( count, `${ noun } call` ) );
		}
		if ( rate !== null ) {
			parts.push( `${ percentage( rate ) } rate` );
		}
		return parts.join( " · " ) || `Recorded ${ noun } outcome`;
	}

	function topErrorEvidence( item ) {
		const errors = list( item.top_errors ).map( record ).filter( ( error ) => typeof error.code === "string" && error.code !== "" );
		errors.sort( ( left, right ) => {
			const countDifference = ( metricNumber( right.count ) || 0 ) - ( metricNumber( left.count ) || 0 );
			return countDifference || compareText( left.code, right.code );
		} );
		return errors.map( ( error ) => {
			const count = metricNumber( error.count );
			return count === null ? error.code : `${ error.code } × ${ count }`;
		} ).join( " · " );
	}

	function failureSeverity( count, rate ) {
		return ( count !== null && count >= 3 ) || ( rate !== null && rate >= 0.25 )
			? { className: "wmcp-signal-critical", label: "Critical", rank: 0 }
			: { className: "wmcp-signal-warning", label: "Warning", rank: 1 };
	}

	function resetMonitorState() {
		monitorState.overview = null;
		monitorState.opportunities = null;
		monitorState.toolHealth = null;
		renderSignals();
	}

	function coverageMessage() {
		const health = record( monitorState.toolHealth );
		const opportunities = record( monitorState.opportunities );
		const reasons = [];
		if ( health.truncated === true ) {
			reasons.push( "tool-health results were truncated" );
		}
		if ( opportunities.has_more === true ) {
			reasons.push( opportunities.compatibility_source === "capability_gaps"
				? "more capability-gap groups are available"
				: "more opportunity-signal groups are available" );
		}
		return reasons.join( "; " );
	}

	function toolSignals() {
		const health = monitorState.toolHealth;
		const items = health === null ? [] : list( health.items || health.tools );
		const signals = [];
		items.forEach( ( rawItem ) => {
			const item = record( rawItem );
			const tool = typeof item.tool_name === "string" && item.tool_name !== "" ? item.tool_name : "Unknown tool";
			const failed = metricNumber( item.failed );
			const failureRate = metricNumber( item.failure_rate );
			const denied = metricNumber( item.denied );
			const denialRate = metricNumber( item.denial_rate );
			const codes = topErrorEvidence( item );
			if ( ( failed !== null && failed > 0 ) || ( failureRate !== null && failureRate > 0 ) ) {
				const severity = failureSeverity( failed, failureRate );
				signals.push( {
					affected: failed || 0,
					className: severity.className,
					fields: [
						[ "Affected", outcomeEvidence( item, "failed", "failure_rate", "failed" ) ],
						[ "Tool", tool ],
						[ "Top terminal codes (all outcomes)", codes || "No terminal error code returned" ],
						[ "Severity rule", "Critical at 3 failed calls or a 25% failure rate" ],
					],
					severity: severity.label,
					severityRank: severity.rank,
					sortKey: `0:${ tool }:${ codes }`,
					title: `${ pretty( tool ) } recorded failures`,
					type: "Reliability signal",
					typeRank: 0,
				} );
			}
			if ( ( denied !== null && denied > 0 ) || ( denialRate !== null && denialRate > 0 ) ) {
				signals.push( {
					affected: denied || 0,
					className: "wmcp-signal-warning",
					fields: [
						[ "Affected", outcomeEvidence( item, "denied", "denial_rate", "denied" ) ],
						[ "Tool", tool ],
						[ "Top terminal codes (all outcomes)", codes || "No terminal error code returned" ],
					],
					severity: "Warning",
					severityRank: 1,
					sortKey: `1:${ tool }:${ codes }`,
					title: `${ pretty( tool ) } recorded denied calls`,
					type: "Denial signal",
					typeRank: 1,
				} );
			}
		} );

		if ( signals.length === 0 && monitorState.overview !== null && ( health === null || items.length === 0 ) ) {
			const calls = record( monitorState.overview.tool_calls );
			const outcomes = [
				[ "failed", "failure_rate", "Reliability signal", "Failures recorded across this scope", 0 ],
				[ "denied", "denial_rate", "Denial signal", "Denied calls recorded across this scope", 1 ],
			];
			outcomes.forEach( ( [ countField, rateField, type, title, typeRank ] ) => {
				const count = metricNumber( calls[ countField ] );
				const rate = metricNumber( calls[ rateField ] );
				if ( ( count !== null && count > 0 ) || ( rate !== null && rate > 0 ) ) {
					const level = countField === "failed"
						? failureSeverity( count, rate )
						: { className: "wmcp-signal-warning", label: "Warning", rank: 1 };
					const fields = [
						[ "Affected", outcomeEvidence( calls, countField, rateField, countField ) ],
						[ "Tool", "All tools in the loaded scope" ],
						[ "Top terminal codes (all outcomes)", "Per-tool code detail not loaded" ],
					];
					if ( countField === "failed" ) {
						fields.push( [ "Severity rule", "Critical at 3 failed calls or a 25% failure rate" ] );
					}
					signals.push( {
						affected: count || 0,
						className: level.className,
						fields,
						severity: level.label,
						severityRank: level.rank,
						sortKey: `2:${ countField }`,
						title,
						type,
						typeRank,
					} );
				}
			} );
		}
		return signals;
	}

	function opportunitySignals() {
		if ( monitorState.opportunities === null ) {
			return [];
		}
		return list( monitorState.opportunities.items ).map( record ).filter( ( item ) => {
			const evidenceCount = ( metricNumber( item.observed_count ) || 0 ) + ( metricNumber( item.feedback_count ) || 0 );
			return ! [ "resolved", "dismissed" ].includes( String( item.status || "" ).toLowerCase() ) && evidenceCount > 0;
		} ).map( ( item ) => {
			const observed = metricNumber( item.observed_count ) || 0;
			const feedback = metricNumber( item.feedback_count ) || 0;
			const workflows = metricNumber( item.affected_workflows ) || 0;
			const sources = sourceLabels( item );
			const badges = evidenceLabels( item );
			const evidence = item.compatibility_evidence || `${ plural( observed, "site observation" ) } · ${ plural( feedback, "agent report" ) }`;
			return {
				affected: observed + feedback,
				category: String( item.category || "opportunity" ),
				className: "wmcp-signal-opportunity",
				fields: [
					[ "Evidence", evidence ],
					[ "Signal code", item.signal_code || "—" ],
					[ "Workflows", plural( workflows, "workflow" ) ],
					[ "Suggested action", pretty( item.suggested_owner_action || "review signal" ) ],
				],
				severity: "Opportunity",
				severityRank: 2,
				signalKey: String( item.signal_key || "" ),
				sortKey: `3:${ item.category || "" }:${ item.signal_key || item.title || "" }`,
				badges,
				sources,
				title: item.title || `${ pretty( item.category || "opportunity" ) } recorded`,
				type: item.display_type || pretty( item.category || "Opportunity signal" ),
				typeRank: 2,
			};
		} );
	}

	function sourceLabels( item ) {
		const sources = record( item.sources );
		const labels = [];
		if ( ( metricNumber( sources.site_observed ) || 0 ) > 0 ) {
			labels.push( "Site observed" );
		}
		if ( ( metricNumber( sources.agent_reported ) || 0 ) > 0 ) {
			labels.push( "Agent reported" );
		}
		return labels;
	}

	function evidenceLabels( item ) {
		const labels = sourceLabels( item );
		if ( item.evidence_status === "verified" ) {
			labels.push( "Site verified" );
		}
		return labels;
	}

	function sourceList( labels, status = "" ) {
		const group = document.createElement( "span" );
		group.className = "wmcp-signal-source-list";
		if ( status ) {
			const severity = document.createElement( "span" );
			severity.className = "wmcp-signal-severity";
			text( severity, status );
			group.append( severity );
		}
		list( labels ).forEach( ( source ) => {
			const label = document.createElement( "span" );
			label.className = "wmcp-signal-source";
			label.dataset.signalSource = String( source ).toLowerCase().replaceAll( " ", "-" );
			text( label, source );
			group.append( label );
		} );
		return group;
	}

	function renderSignals() {
		const container = one( "[data-wmcp-signals]" );
		if ( ! container ) {
			return;
		}
		const signals = [ ...toolSignals(), ...opportunitySignals() ];
		signals.sort( ( left, right ) => {
			return left.severityRank - right.severityRank || left.typeRank - right.typeRank || right.affected - left.affected || compareText( left.sortKey, right.sortKey );
		} );
		container.replaceChildren();
		const partialCoverage = coverageMessage();
		if ( signals.length === 0 ) {
			const empty = document.createElement( "p" );
			empty.className = "wmcp-empty-state";
			const complete = Object.values( monitorState ).every( ( value ) => value !== null );
			if ( partialCoverage ) {
				text( empty, `No recorded signals appear in the returned evidence. Coverage is partial: ${ partialCoverage }.` );
			} else {
				text( empty, complete ? "No recorded signals in this loaded scope." : "No recorded signals in the evidence loaded so far." );
			}
			container.append( empty );
			return;
		}

		if ( partialCoverage ) {
			const coverage = document.createElement( "p" );
			coverage.className = "wmcp-empty-state wmcp-signal-coverage";
			text( coverage, `Partial signal coverage: ${ partialCoverage }.` );
			container.append( coverage );
		}
		const signalList = document.createElement( "div" );
		signalList.className = "wmcp-signal-list";
		signals.forEach( ( signal ) => {
			const card = document.createElement( "article" );
			card.className = `wmcp-signal-card ${ signal.className }`;
			card.dataset.signalType = signal.type.toLowerCase().replaceAll( " ", "-" );
			if ( signal.category ) {
				card.dataset.signalCategory = signal.category;
			}
			if ( signal.signalKey ) {
				card.dataset.signalKey = signal.signalKey;
			}
			if ( list( signal.sources ).length ) {
				card.dataset.signalSource = signal.sources.join( "+" ).toLowerCase().replaceAll( " ", "-" );
			}
			const label = document.createElement( "div" );
			label.className = "wmcp-panel-label";
			const type = document.createElement( "span" );
			type.className = "wmcp-signal-type";
			text( type, signal.type );
			label.append( type );
			if ( list( signal.badges || signal.sources ).length ) {
				label.append( sourceList( signal.badges || signal.sources, signal.severity ) );
			} else {
				const severity = document.createElement( "span" );
				severity.className = "wmcp-signal-severity";
				text( severity, signal.severity );
				label.append( severity );
			}
			const title = document.createElement( "h3" );
			text( title, signal.title );
			const metadata = document.createElement( "dl" );
			metadata.className = "wmcp-signal-meta";
			signal.fields.forEach( ( [ term, value ] ) => {
				const field = document.createElement( "div" );
				const name = document.createElement( "dt" );
				const detail = document.createElement( "dd" );
				text( name, term );
				text( detail, value );
				field.append( name, detail );
				metadata.append( field );
			} );
			card.append( label, title, metadata );
			signalList.append( card );
		} );
		container.append( signalList );
	}

	function renderOverview( result ) {
		monitorState.overview = record( result );
		renderSignals();
		all( "[data-metric]" ).forEach( ( target ) => {
			const path = target.dataset.metric;
			let value = getPath( result, path );
			if ( path.endsWith( "_rate" ) ) {
				text( target, percentage( value ) );
			} else if ( path === "revenue.net" || path === "revenue.refunds" ) {
				const field = path.endsWith( "refunds" ) ? "refunds" : "net";
				text( target, value === undefined
					? moneyByCurrency( result.revenue?.by_currency, field )
					: money( value, result.revenue?.currency || "USD" ) );
			} else {
				text( target, value );
			}
		} );
		renderAttribution( result.revenue?.attribution );
	}

	function renderAttribution( attribution ) {
		all( "[data-attribution]" ).forEach( ( card ) => {
			const value = record( record( attribution )[ card.dataset.attribution ] );
			text( one( "[data-attribution-orders]", card ), `${ value.orders ?? 0 } orders` );
			text( one( "[data-attribution-gross]", card ), moneyByCurrency( value.by_currency, "gross" ) );
			text( one( "[data-attribution-refunds]", card ), moneyByCurrency( value.by_currency, "refunds" ) );
			text( one( "[data-attribution-net]", card ), moneyByCurrency( value.by_currency, "net" ) );
		} );
	}

	function renderFunnel( result ) {
		const stages = list( result.stages );
		stages.forEach( ( stage ) => {
			const key = stage.stage || stage.key;
			const item = one( `[data-funnel-stage="${ key }"]` );
			if ( ! item ) {
				return;
			}
			const count = stage.workflow_count ?? stage.count ?? 0;
			item.classList.toggle( "wmcp-has-signal", Number( count ) > 0 );
			text( one( "[data-funnel-count]", item ), count );
			text( one( "[data-funnel-rate]", item ), percentage( stage.conversion_from_start ?? stage.conversion_rate ) );
			text( one( "[data-funnel-previous]", item ), percentage( stage.conversion_from_previous ) );
			const duration = metricNumber( stage.median_time_to_next_ms );
			const timing = duration === null ? "" : ` · median ${ duration } ms`;
			text( one( "[data-funnel-reason]", item ), `${ pretty( stage.top_exit_reason || "No recorded exit" ) }${ timing }` );
		} );
	}

	function renderWorkflows( result ) {
		const items = list( result.items || result.workflows );
		if ( items[ 0 ]?.workflow_id ) {
			all( "[data-wmcp-workflow]" ).forEach( ( target ) => {
				text( target, `${ String( items[ 0 ].workflow_id ).slice( 0, 8 ) }…` );
			} );
		}
		const body = one( "[data-wmcp-workflows]" );
		if ( ! body ) {
			return;
		}
		body.replaceChildren();
		if ( items.length === 0 ) {
			const row = document.createElement( "tr" );
			const cell = document.createElement( "td" );
			cell.colSpan = 5;
			cell.className = "wmcp-table-empty";
			text( cell, "No storefront workflow is recorded in this authorized scope yet." );
			row.append( cell );
			body.append( row );
			return;
		}

		items.forEach( ( item ) => {
			const row = document.createElement( "tr" );
			row.dataset.workflowId = item.workflow_id;
			const idCell = document.createElement( "td" );
			const select = document.createElement( "button" );
			const workflowLabel = `${ String( item.workflow_id ).slice( 0, 8 ) }…`;
			select.type = "button";
			select.dataset.explainWorkflow = item.workflow_id;
			select.dataset.workflowLabel = workflowLabel;
			select.title = `Explain workflow ${ item.workflow_id }`;
			text( select, workflowLabel );
			idCell.append( select );
			const status = document.createElement( "td" );
			status.append( stateTag( item.status ) );
			const calls = document.createElement( "td" );
			text( calls, item.tool_count ?? 0 );
			const last = document.createElement( "td" );
			text( last, item.last_event?.tool_name || item.last_event?.event_name || dateTime( item.last_event_at ) );
			const net = document.createElement( "td" );
			text( net, moneyByCurrency( item.commerce?.by_currency ) );
			row.append( idCell, status, calls, last, net );
			body.append( row );
		} );

		if ( result.has_more === true ) {
			const row = document.createElement( "tr" );
			row.className = "wmcp-table-note";
			row.dataset.workflowCoverage = "partial";
			const cell = document.createElement( "td" );
			cell.colSpan = 5;
			text( cell, `Showing the first ${ items.length } agent workflows. More are available in this scope.` );
			row.append( cell );
			body.append( row );
		}

		all( "[data-explain-workflow]", body ).forEach( ( button ) => {
			button.addEventListener( "click", () => explainWorkflow( button.dataset.explainWorkflow, button.closest( "tr" ) ) );
		} );

	}

	function renderExplanation( result ) {
		const workflow = record( result.workflow );
		const timeline = list( result.timeline );
		text( one( "#wmcp-timeline-title" ), workflow.workflow_id ? `Workflow ${ workflow.workflow_id.slice( 0, 8 ) }…` : "Workflow explanation" );
		text( one( "[data-wmcp-explanation]" ), result.explanation || "The workflow explanation was returned without narrative text." );
		text(
			one( "[data-wmcp-timeline-count]" ),
			result.truncated === true
				? `${ plural( timeline.length, "event" ) } shown · partial replay`
				: `${ timeline.length } events`
		);
		const productIds = Array.from( new Set( timeline.flatMap( ( event ) => list( event.product_ids ).map( String ) ) ) );
		const orders = list( result.commerce_outcome?.orders );
		const opportunities = list( result.opportunity_signals );
		const feedback = list( result.agent_feedback );
		const orderSummary = orders.length
			? orders.map( ( order ) => `#${ order.order_id } ${ pretty( order.attribution_class ) } ${ money( order.net, order.currency ) }` ).join( " · " )
			: "No attributed order";
		const opportunitySummary = opportunities.length
			? `${ plural( opportunities.length, "site-observed signal" ) } · ${ Array.from( new Set( opportunities.map( ( item ) => evidenceStatusLabel( item.evidence_status ) ) ) ).join( " · " ) }`
			: "No site-observed opportunity signal";
		const feedbackSummary = feedback.length
			? `${ plural( feedback.length, "agent-reported item" ) } · ${ Array.from( new Set( feedback.map( ( item ) => evidenceStatusLabel( item.evidence_status ) ) ) ).join( " · " ) }`
			: "No agent feedback";
		text( one( '[data-evidence="status"]' ), pretty( workflow.status ) );
		text( one( '[data-evidence="products"]' ), productIds.join( ", " ) || "No product evidence" );
		text( one( '[data-evidence="orders"]' ), orderSummary );
		text( one( '[data-evidence="gaps"]' ), list( result.capability_gaps ).length );
		text( one( '[data-evidence="opportunities"]' ), opportunitySummary );
		text( one( '[data-evidence="feedback"]' ), feedbackSummary );
		const container = one( "[data-wmcp-timeline]" );
		if ( ! container ) {
			return;
		}
		container.replaceChildren();
		timeline.forEach( ( event ) => {
			const item = document.createElement( "li" );
			const problem = [ "failed", "denied", "cancelled" ].includes( event.outcome ) || event.error_code;
			item.classList.toggle( "wmcp-is-problem", Boolean( problem ) );
			const time = document.createElement( "time" );
			text( time, timeOnly( event.occurred_at ) );
			const label = document.createElement( "span" );
			const eventName = document.createElement( "strong" );
			const eventMeta = document.createElement( "small" );
			text( eventName, event.tool?.name || event.event_name );
			const metadata = [];
			if ( event.tool ) {
				metadata.push( `v${ event.tool.version || "—" }`, event.tool.risk_class || "unclassified" );
			}
			if ( list( event.product_ids ).length ) {
				metadata.push( `products ${ event.product_ids.join( "," ) }` );
			}
			const properties = summarizeProperties( event.properties );
			if ( properties ) {
				metadata.push( properties );
			}
			text( eventMeta, metadata.join( " · " ) );
			label.append( eventName, eventMeta );
			const outcome = document.createElement( "b" );
			text( outcome, `${ event.outcome || event.error_code || "event" }${ event.duration_ms === null || event.duration_ms === undefined ? "" : ` · ${ event.duration_ms } ms` }` );
			item.append( time, label, outcome );
			container.append( item );
		} );
	}

	function resetExplanationEvidence( status, fallback ) {
		text( one( '[data-evidence="status"]' ), status );
		text( one( '[data-evidence="products"]' ), fallback );
		text( one( '[data-evidence="orders"]' ), fallback );
		text( one( '[data-evidence="gaps"]' ), fallback );
		text( one( '[data-evidence="opportunities"]' ), fallback );
		text( one( '[data-evidence="feedback"]' ), fallback );
		one( "[data-wmcp-timeline]" )?.replaceChildren();
	}

	function renderExplanationLoading( workflowId ) {
		const shortId = String( workflowId ).slice( 0, 8 );
		text( one( "#wmcp-timeline-title" ), "Loading workflow replay…" );
		text( one( "[data-wmcp-explanation]" ), `Loading redacted evidence for workflow ${ shortId }…` );
		text( one( "[data-wmcp-timeline-count]" ), "Loading…" );
		resetExplanationEvidence( "Loading", "—" );
	}

	function renderExplanationUnavailable( workflowId ) {
		const shortId = String( workflowId ).slice( 0, 8 );
		text( one( "#wmcp-timeline-title" ), "Workflow replay unavailable" );
		text( one( "[data-wmcp-explanation]" ), `The redacted replay for workflow ${ shortId }… could not be loaded.` );
		text( one( "[data-wmcp-timeline-count]" ), "0 events" );
		resetExplanationEvidence( "Unavailable", "Not available" );
	}

	function setWorkflowRowBusy( row, busy ) {
		if ( ! row ) {
			return;
		}

		row.classList.toggle( "wmcp-is-loading", busy );
		if ( busy ) {
			row.setAttribute( "aria-busy", "true" );
		} else {
			row.removeAttribute( "aria-busy" );
		}

		const button = one( "[data-explain-workflow]", row );
		if ( button ) {
			button.disabled = busy;
			text( button, busy ? "Loading…" : button.dataset.workflowLabel );
			if ( busy ) {
				button.setAttribute( "aria-busy", "true" );
			} else {
				button.removeAttribute( "aria-busy" );
			}
		}
	}

	function selectWorkflowRow( selectedRow ) {
		all( "[data-workflow-id]" ).forEach( ( row ) => {
			const selected = row === selectedRow;
			row.classList.toggle( "wmcp-is-selected", selected );
			row.classList.remove( "wmcp-has-error" );
			const button = one( "[data-explain-workflow]", row );
			if ( selected ) {
				button?.setAttribute( "aria-current", "true" );
			} else {
				button?.removeAttribute( "aria-current" );
			}
			setWorkflowRowBusy( row, selected );
		} );
	}

	function summarizeProperties( value ) {
		return Object.entries( record( value ) ).slice( 0, 5 ).map( ( [ key, item ] ) => {
			let summary;
			if ( Array.isArray( item ) ) {
				summary = item.slice( 0, 5 ).map( String ).join( "," );
			} else if ( item && typeof item === "object" ) {
				summary = Object.entries( item ).slice( 0, 3 ).map( ( [ nestedKey, nestedValue ] ) => `${ nestedKey }:${ String( nestedValue ) }` ).join( "," );
			} else {
				summary = String( item );
			}
			return `${ key }=${ summary }`;
		} ).join( " · " );
	}

	function renderToolHealth( result ) {
		monitorState.toolHealth = record( result );
		renderSignals();
		const items = list( result.items || result.tools );
		const body = one( "[data-wmcp-tool-health]" );
		if ( ! body ) {
			return;
		}
		body.replaceChildren();
		if ( items.length === 0 ) {
			const row = document.createElement( "tr" );
			const cell = document.createElement( "td" );
			cell.colSpan = 8;
			cell.className = "wmcp-table-empty";
			text( cell, "No terminal tool calls are recorded in this scope." );
			row.append( cell );
			body.append( row );
			return;
		}

		items.forEach( ( item ) => {
			const row = document.createElement( "tr" );
			const values = [
				`${ item.tool_name }\nv${ item.version || "—" }`,
				`${ item.calls } / ${ item.workflows }`,
				`${ percentage( item.success_rate ) } / ${ percentage( item.failure_rate ) } / ${ percentage( item.denial_rate ) }`,
				`${ item.p50_duration_ms ?? "—" } / ${ item.p95_duration_ms ?? "—" } ms`,
				item.top_errors?.[ 0 ]?.code || "None",
				`${ item.cart_mutations ?? 0 } / ${ item.checkout_handoffs ?? 0 }`,
				`${ item.attributed_orders ?? 0 } / ${ moneyByCurrency( item.net_attributed_revenue ) }`,
			];
			values.forEach( ( value ) => {
				const cell = document.createElement( "td" );
				text( cell, value );
				row.append( cell );
			} );
			const state = document.createElement( "td" );
			state.append( stateTag( item.enabled === false ? "disabled" : item.enabled === true ? "enabled" : "policy inherited" ) );
			row.append( state );
			body.append( row );
		} );
	}

	function valueContext( value ) {
		if ( Array.isArray( value ) ) {
			if ( value.length === 0 ) {
				return "—";
			}
			return value.map( ( item ) => typeof item === "object" ? money( item.value ?? item.total ?? 0, item.currency ) : String( item ) ).join( ", " );
		}
		if ( value && typeof value === "object" ) {
			if ( Object.hasOwn( value, "value" ) || Object.hasOwn( value, "total" ) ) {
				return money( value.value ?? value.total ?? 0, value.currency );
			}
			return moneyByCurrency( value );
		}
		return value ?? "—";
	}

	function rawMeasuredMetricValue( key, value ) {
		if ( [ "net_attributed_value", "paid_order_value" ].includes( key ) ) {
			return list( value ).map( ( item ) => {
				const amount = record( item );
				return money( amount.value, amount.currency );
			} ).filter( ( item ) => item !== "—" ).join( " + " ) || "—";
		}
		if ( key === "viewed_product_value_context" ) {
			return valueContext( value );
		}
		if ( typeof value === "boolean" ) {
			return value ? "yes" : "no";
		}
		return value === null || value === undefined || value === "" ? "—" : String( value );
	}

	function measuredMetricValue( key, value ) {
		const envelope = record( value );
		if ( Object.hasOwn( envelope, "status" ) && Object.hasOwn( envelope, "value" ) ) {
			const measured = rawMeasuredMetricValue( key, envelope.value );
			const status = pretty( envelope.status || "unknown" );
			return `${ measured } (${ status })`;
		}
		return rawMeasuredMetricValue( key, value );
	}

	function measuredContextSummary( value ) {
		const context = record( value );
		return Object.keys( measuredMetricLabels ).filter( ( key ) => Object.hasOwn( context, key ) ).map( ( key ) => {
			return `${ measuredMetricLabels[ key ] }: ${ measuredMetricValue( key, context[ key ] ) }`;
		} ).join( " · " ) || "No site-computed measurement returned";
	}

	function shortWorkflowId( value ) {
		const workflowId = String( value || "" ).trim();
		if ( ! workflowId ) {
			return "";
		}
		return workflowId.length > 8 ? `${ workflowId.slice( 0, 8 ) }…` : workflowId;
	}

	function measurementSourceLabel( value ) {
		return {
			agent_reported: "Agent reported",
			site_observed: "Site observed",
		}[ String( value || "" ) ] || "Source unavailable";
	}

	function measurementDisplay( item ) {
		const scope = record( item.measurement_scope );
		const affectedWorkflows = metricNumber( item.affected_workflows ) || 0;
		const hasScope = [ "single_workflow", "latest_workflow_sample" ].includes( String( scope.kind || "" ) )
			&& String( scope.workflow_id || "" ).trim() !== "";
		if ( ! hasScope ) {
			return {
				label: "Measurement unavailable",
				provenance: "No workflow measurement sample returned",
				scopeLabel: "Measurement scope",
			};
		}
		const isLatestSample = affectedWorkflows > 1 || scope.kind === "latest_workflow_sample";
		const provenance = [];
		const workflowId = shortWorkflowId( scope.workflow_id );
		if ( workflowId ) {
			provenance.push( `Workflow ${ workflowId }` );
		}
		if ( scope.source ) {
			provenance.push( measurementSourceLabel( scope.source ) );
		}
		if ( scope.occurred_at ) {
			provenance.push( dateTime( scope.occurred_at ) );
		}
		return {
			label: isLatestSample ? "Latest workflow sample" : "Site measured",
			provenance: `${ isLatestSample ? "Not aggregate" : "Single workflow" }${ provenance.length ? ` · ${ provenance.join( " · " ) }` : "" }`,
			scopeLabel: isLatestSample ? "Sample scope" : "Measurement scope",
		};
	}

	function evidenceStatusLabel( value ) {
		return {
			linked: "Agent report linked to workflow evidence",
			unlinked: "Agent report without linked site evidence",
			verified: "Site-observed evidence verified",
		}[ String( value || "" ) ] || "Evidence status unavailable";
	}

	function normalizedCapabilityGap( value ) {
		const item = record( value );
		const requests = metricNumber( item.requests ) || 0;
		return {
			affected_workflows: metricNumber( item.affected_workflows ) || 0,
			category: "capability_gap",
			evidence_status: "linked",
			feedback_count: requests,
			compatibility_evidence: `${ plural( requests, "request" ) } · ${ plural( metricNumber( item.affected_workflows ) || 0, "workflow" ) }`,
			latest_occurrence: item.latest_occurrence,
			measured_context: {
				viewed_product_value_context: item.viewed_product_value_context,
			},
			observed_count: 0,
			related_product_ids: list( item.related_product_ids ),
			signal_code: item.capability,
			signal_key: item.gap_key || item.capability,
			sources: { agent_reported: requests, site_observed: 0 },
			status: item.status,
			suggested_owner_action: "review capability",
			title: item.capability ? `${ pretty( item.capability ) } demand recorded` : "Unsupported capability reported",
			display_type: "Opportunity gap",
		};
	}

	function renderOpportunities( result ) {
		monitorState.opportunities = record( result );
		renderSignals();
		const items = list( result.items );
		const container = one( "[data-wmcp-opportunities]" ) || one( "[data-wmcp-gaps]" );
		const feedbackCount = items.reduce( ( total, item ) => total + ( metricNumber( record( item ).feedback_count ) || 0 ), 0 );
		text( one( "[data-wmcp-opportunity-count]" ), items.length );
		text( one( "[data-wmcp-feedback-count]" ), feedbackCount );
		if ( ! container ) {
			return;
		}
		const countTarget = one( "[data-wmcp-opportunity-count]" );
		if ( countTarget ) {
			countTarget.title = result.has_more === true
				? `Showing ${ items.length } grouped signals; more are available in this scope.`
				: `${ items.length } grouped opportunity signals in this scope.`;
		}
		container.replaceChildren();
		if ( items.length === 0 ) {
			const empty = document.createElement( "p" );
			empty.className = "wmcp-empty-state";
			text( empty, "No grouped opportunity or feedback signal is recorded in this scope." );
			container.append( empty );
			return;
		}

		items.forEach( ( item, index ) => {
			item = record( item );
			const sources = sourceLabels( item );
			const badges = evidenceLabels( item );
			const measurement = measurementDisplay( item );
			const card = document.createElement( "article" );
			card.className = "wmcp-gap-card wmcp-opportunity-card";
			card.dataset.signalCategory = String( item.category || "opportunity" );
			card.dataset.signalKey = String( item.signal_key || "" );
			card.dataset.signalSource = sources.join( "+" ).toLowerCase().replaceAll( " ", "-" );
			const label = document.createElement( "div" );
			label.className = "wmcp-panel-label";
			const kind = document.createElement( "span" );
			const number = document.createElement( "span" );
			text( kind, pretty( item.category || "Opportunity signal" ) );
			text( number, String( index + 1 ).padStart( 2, "0" ) );
			label.append( kind, badges.length ? sourceList( badges ) : number );
			const title = document.createElement( "h3" );
			text( title, item.title || pretty( item.signal_code || "Opportunity signal" ) );
			const details = document.createElement( "dl" );
			const fields = [
				[ "Site observations", metricNumber( item.observed_count ) || 0 ],
				[ "Agent feedback", metricNumber( item.feedback_count ) || 0 ],
				[ "Signal", item.signal_code || "—" ],
				[ "Workflows", metricNumber( item.affected_workflows ) || 0 ],
				[ "Distinct sessions", metricNumber( item.distinct_sessions ) || 0 ],
				[ "Related products", list( item.related_product_ids ).join( ", " ) || "—" ],
				[ measurement.label, measuredContextSummary( item.measured_context ) ],
				[ measurement.scopeLabel, measurement.provenance ],
				[ "Evidence", evidenceStatusLabel( item.evidence_status ) ],
				[ "Suggested action", pretty( item.suggested_owner_action || "review signal" ) ],
				[ "Latest", dateTime( item.latest_occurrence ) ],
				[ "Status", pretty( item.status || "open" ) ],
			];
			fields.forEach( ( [ term, value ] ) => {
				const wrapper = document.createElement( "div" );
				const dt = document.createElement( "dt" );
				const dd = document.createElement( "dd" );
				text( dt, term );
				text( dd, value );
				wrapper.append( dt, dd );
				details.append( wrapper );
			} );
			card.append( label, title, details );
			container.append( card );
		} );
	}

	function renderGaps( result ) {
		renderOpportunities( {
			compatibility_source: "capability_gaps",
			has_more: result.has_more === true,
			items: list( result.items || result.gaps ).map( normalizedCapabilityGap ),
		} );
	}

	function renderPolicyChange( result ) {
		const name = result.tool_name;
		const enabled = result.after?.enabled;
		const requested = result.requested_enabled;
		const row = name ? one( `[data-policy-tool="${ name }"]` ) : null;
		if ( row ) {
			let label = "Blocked by global or site policy";
			if ( requested === false ) {
				label = "Disabled for this demo session";
			} else if ( requested === true && enabled === true ) {
				label = "Site enabled · session override cleared";
			}
			text( one( "[data-policy-state]", row ), label );
			row.classList.toggle( "wmcp-policy-disabled", enabled === false );
		}
		const metric = one( '[data-metric="policy_changes"]' );
		if ( metric ) {
			const count = Number.parseInt( metric.textContent, 10 );
			text( metric, Number.isFinite( count ) ? count + 1 : 1 );
		}
	}

	function renderToolResult( detail ) {
		const tool = detail.tool || detail.response?.tool?.name;
		const result = record( detail.response?.result );
		if ( tool === "get_agent_analytics_overview" ) {
			renderOverview( result );
		} else if ( tool === "get_agent_conversion_funnel" ) {
			renderFunnel( result );
		} else if ( tool === "query_agent_workflows" ) {
			renderWorkflows( result );
		} else if ( tool === "explain_agent_workflow" ) {
			renderExplanation( result );
		} else if ( tool === "get_tool_health" ) {
			renderToolHealth( result );
		} else if ( tool === "get_opportunity_signals" ) {
			renderOpportunities( result );
		} else if ( tool === "get_capability_gaps" ) {
			renderGaps( result );
		} else if ( tool === "set_tool_enabled" ) {
			renderPolicyChange( result );
		}
	}

	function uuid() {
		if ( typeof crypto.randomUUID === "function" ) {
			return crypto.randomUUID();
		}
		const bytes = crypto.getRandomValues( new Uint8Array( 16 ) );
		bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40;
		bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80;
		const hex = Array.from( bytes, ( value ) => value.toString( 16 ).padStart( 2, "0" ) ).join( "" );
		return `${ hex.slice( 0, 8 ) }-${ hex.slice( 8, 12 ) }-${ hex.slice( 12, 16 ) }-${ hex.slice( 16, 20 ) }-${ hex.slice( 20 ) }`;
	}

	async function requestJson( url, options, context, maximumBytes = 32768 ) {
		let response;
		try {
			response = await fetch( url, options );
		} catch {
			throw new Error( `The ${ context } service could not be reached.` );
		}

		const announcedLength = Number( response.headers.get( "Content-Length" ) );
		if ( Number.isFinite( announcedLength ) && announcedLength > maximumBytes ) {
			throw new Error( `The ${ context } response exceeded the safe display limit.` );
		}

		const body = await response.text();
		if ( new TextEncoder().encode( body ).byteLength > maximumBytes ) {
			throw new Error( `The ${ context } response exceeded the safe display limit.` );
		}

		let payload;
		try {
			payload = body ? JSON.parse( body ) : null;
		} catch {
			throw new Error( `The ${ context } service returned an unreadable response.` );
		}
		if ( ! payload || typeof payload !== "object" || Array.isArray( payload ) ) {
			throw new Error( `The ${ context } service returned an invalid response.` );
		}
		if ( ! response.ok || payload.ok === false ) {
			const serverMessage = payload.error?.message;
			throw new Error( typeof serverMessage === "string" && serverMessage.length <= 500 ? serverMessage : `The ${ context } request did not complete.` );
		}

		return payload;
	}

	async function fetchManifest() {
		if ( currentManifest ) {
			return currentManifest;
		}
		if ( config.sessionUrl ) {
			await requestJson( config.sessionUrl, {
				body: JSON.stringify( { surface: "agentsnr" } ),
				cache: "no-store",
				credentials: "same-origin",
				headers: { Accept: "application/json", "Content-Type": "application/json" },
				method: "POST",
			}, "demo session" );
		}
		const payload = await requestJson(
			config.manifestUrl,
			{ cache: "no-store", credentials: "same-origin", headers: { Accept: "application/json" } },
			"Agent SNR manifest",
			262144
		);
		currentManifest = payload;
		window.dispatchEvent( new CustomEvent( "wmcp:manifest-snapshot", { detail: payload } ) );
		return payload;
	}

	function dispatchToolResult( tool, payload ) {
		const detail = { requestId: payload.event_id || uuid(), response: payload, tool };
		window.dispatchEvent( new CustomEvent( "wmcp:tool-result", { detail } ) );
		window.dispatchEvent( new CustomEvent( "wmcp:ui-update", { detail: Object.assign( { ui: payload.ui || {} }, detail ) } ) );
	}

	async function execute( tool, input = {}, options = {} ) {
		const manifest = await fetchManifest();
		if ( ! manifest.tools?.some( ( definition ) => definition.name === tool ) ) {
			throw new Error( `${ tool } is not available in the current policy scope.` );
		}
		const payload = await requestJson( `${ String( config.executionBaseUrl ).replace( /\/+$/, "" ) }/tools/${ encodeURIComponent( tool ) }`, {
			body: JSON.stringify( {
				input,
				manifest_revision: manifest.manifest_revision,
				request_id: uuid(),
				schema_version: manifest.schema_version,
				workflow_id: manifest.workflow_id,
			} ),
			cache: "no-store",
			credentials: "same-origin",
			headers: {
				Accept: "application/json",
				"Content-Type": "application/json",
				"X-WMCP-CSRF": manifest.session?.csrf_token || "",
			},
			method: "POST",
		}, `${ tool } tool` );
		if ( options.dispatch !== false ) {
			dispatchToolResult( tool, payload );
		}
		return payload;
	}

	async function explainWorkflow( workflowId, selectedRow = null ) {
		if ( ! workflowId ) {
			return;
		}
		const request = ++explanationRequest;
		selectWorkflowRow( selectedRow );
		renderExplanationLoading( workflowId );
		showError( "" );
		announce( `Loading workflow replay for ${ String( workflowId ).slice( 0, 8 ) }…` );
		try {
			const payload = await execute(
				"explain_agent_workflow",
				{ workflow_id: workflowId },
				{ dispatch: false }
			);
			if ( request !== explanationRequest ) {
				return;
			}
			dispatchToolResult( "explain_agent_workflow", payload );
			showError( "" );
			announce( `Workflow replay loaded for ${ String( workflowId ).slice( 0, 8 ) }…` );
		} catch ( error ) {
			if ( request !== explanationRequest ) {
				return;
			}
			const message = error.message || "The workflow could not be explained.";
			selectedRow?.classList.add( "wmcp-has-error" );
			renderExplanationUnavailable( workflowId );
			showError( message );
			announce( `Workflow replay unavailable. ${ message }` );
		} finally {
			if ( request === explanationRequest ) {
				setWorkflowRowBusy( selectedRow, false );
			}
		}
	}

	async function loadDashboard() {
		if ( loading ) {
			return;
		}
		loading = true;
		resetMonitorState();
		const button = one( "[data-wmcp-load-dashboard]" );
		if ( button ) {
			button.disabled = true;
		}
		showError( "" );
		announce( "Loading current-session overview, funnel, workflows, tool health, and opportunity signals." );
		try {
			currentManifest = null;
			const manifest = await fetchManifest();
			const opportunityTool = manifest.tools?.some( ( definition ) => definition.name === "get_opportunity_signals" )
				? "get_opportunity_signals"
				: "get_capability_gaps";
			const tools = [
				"get_agent_analytics_overview",
				"get_agent_conversion_funnel",
				"query_agent_workflows",
				"get_tool_health",
				opportunityTool,
			];
			const results = await Promise.allSettled( tools.map( ( tool ) => execute( tool, tool === "query_agent_workflows" ? { limit: 20 } : {} ) ) );
			const failures = results.filter( ( result ) => result.status === "rejected" );
			if ( failures.length ) {
				throw new Error( `${ failures.length } dashboard ${ failures.length === 1 ? "query" : "queries" } could not be completed. ${ failures[ 0 ].reason?.message || "" }` );
			}
			announce( "Current-session evidence loaded. Select a workflow to inspect its redacted timeline." );
		} catch ( error ) {
			showError( error.message || "The current-session dashboard could not be loaded." );
			announce( "The dashboard request was not completed." );
		} finally {
			loading = false;
			if ( button ) {
				button.disabled = false;
			}
		}
	}

	window.addEventListener( "wmcp:manifest-ready", ( event ) => {
		currentManifest = event.detail || currentManifest;
	} );
	window.addEventListener( "wmcp:ui-update", ( event ) => renderToolResult( event.detail || {} ) );
	one( "[data-wmcp-load-dashboard]" )?.addEventListener( "click", loadDashboard );
}() );
