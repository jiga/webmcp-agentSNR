import fs from "node:fs/promises";
import path from "node:path";
import { randomUUID } from "node:crypto";
import { pathToFileURL } from "node:url";
import { chromium } from "playwright";
import {
	assertShowcaseOrigin,
	CAPTURE_OUTPUT_MARKER,
	CAPTURE_OUTPUT_MARKER_CONTENT,
	loadShowcaseAdminCredentials,
	resolveShowcaseBaseUrl,
	sanitizeShowcaseConsoleLocation,
	validateCaptureOutputDirectory,
} from "./showcase-config.mjs";

const { baseUrl: BASE_URL } = resolveShowcaseBaseUrl();
const OUTPUT_DIR = path.resolve(
	process.env.WMCP_SHOWCASE_OUTPUT || "submission/demo-screenshots"
);

const STOREFRONT_TOOL_COUNT = 12;
const AGENT_SNR_TOOL_COUNT = 8;
const SHOWCASE_CREDENTIALS_FILE = path.resolve(
	process.env.WMCP_SHOWCASE_CREDENTIALS_FILE || ".release-test/showcase-runtime/operator-credentials"
);

let adminCredentials;

export function hasRenderedMetricValue( value ) {
	const normalized = typeof value === "string" ? value.trim() : "";
	return normalized !== "" && normalized !== "—";
}

async function waitForRefundEvidence( page, timeout = 20_000 ) {
	const expected = {
		netAttributed: "$0.00",
		ordersPaid: "1",
		refundValue: "$69.00",
	};
	const selectors = {
		netAttributed: '[data-metric="revenue.net"]',
		ordersPaid: '[data-metric="commerce.orders_paid"]',
		refundValue: '[data-metric="revenue.refunds"]',
	};
	const deadline = Date.now() + timeout;
	let actual = {};

	do {
		actual = Object.fromEntries(
			await Promise.all(
				Object.entries( selectors ).map( async ( [ key, selector ] ) => [
					key,
					( ( await page.locator( selector ).textContent() ) || "" ).trim(),
				] )
			)
		);
		if (
			Object.values( actual ).every( hasRenderedMetricValue ) &&
			Object.entries( expected ).every( ( [ key, value ] ) => actual[ key ] === value )
		) {
			return actual;
		}
		await page.waitForTimeout( 100 );
	} while ( Date.now() < deadline );

	throw new Error(
		`Refund evidence did not render the expected paid-order, refund, and net values: ${ JSON.stringify( actual ) }`
	);
}

async function getAdminCredentials() {
	if ( adminCredentials ) {
		return adminCredentials;
	}
	adminCredentials = await loadShowcaseAdminCredentials( {
		credentialsFile: SHOWCASE_CREDENTIALS_FILE,
	} );
	return adminCredentials;
}

async function installModelContextMock( context ) {
	await context.addInitScript( () => {
		const state = { activeTools: new Map() };
		globalThis.__agentSnrShowcase = state;

		Object.defineProperty( globalThis.document, "modelContext", {
			configurable: true,
			value: {
				registerTool( definition, options = {} ) {
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

async function waitForTools( page, count ) {
	await page.waitForFunction(
		( expected ) =>
			document.documentElement.dataset.wmcpStatus === "ready" &&
			globalThis.__agentSnrShowcase?.activeTools?.size === expected,
		count,
		{ timeout: 20_000 }
	);
}

async function callTool( page, name, input = {} ) {
	return page.evaluate(
		async ( invocation ) => {
			const definition = globalThis.__agentSnrShowcase.activeTools.get( invocation.name );
			if ( ! definition ) {
				throw new Error( `Tool ${ invocation.name } is not registered.` );
			}
			return definition.execute( invocation.input );
		},
		{ input, name }
	);
}

async function captureViewport( page, outputDirectory, fileName, focusSelector, scrollOffset = 0 ) {
	if ( focusSelector ) {
		await page.locator( focusSelector ).scrollIntoViewIfNeeded();
		if ( scrollOffset ) {
			await page.evaluate( ( offset ) => window.scrollBy( 0, offset ), scrollOffset );
		}
		await page.waitForTimeout( 250 );
	}
	const target = path.join( outputDirectory, fileName );
	await page.screenshot( { path: target, fullPage: false } );
	return path
		.relative( process.cwd(), path.join( OUTPUT_DIR, fileName ) )
		.split( path.sep )
		.join( "/" );
}

async function loginAdmin( page ) {
	const credentials = await getAdminCredentials();
	await page.goto( `${ BASE_URL }/wp-admin/` );
	const loginUrl = assertShowcaseOrigin( page.url(), BASE_URL, "Admin login" );
	if ( loginUrl.pathname === "/wp-login.php" ) {
		await page.getByLabel( "Username or Email Address", { exact: true } ).fill( credentials.user );
		await page.getByLabel( "Password", { exact: true } ).fill( credentials.password );
		await Promise.all( [
			page.waitForURL(
				( url ) => url.origin === BASE_URL && url.pathname.startsWith( "/wp-admin/" ),
				{ timeout: 20_000 }
			),
			page.getByRole( "button", { name: "Log In" } ).click(),
		] );
	}
	const adminUrl = assertShowcaseOrigin( page.url(), BASE_URL, "Admin session" );
	if ( ! adminUrl.pathname.startsWith( "/wp-admin/" ) ) {
		throw new Error( "The showcase operator login did not reach WordPress admin." );
	}
}

async function refundOrder( page, orderId, amount ) {
	const publicSessionCookies = await page.context().cookies( BASE_URL );
	await loginAdmin( page );
	await page.goto( `${ BASE_URL }/wp-admin/post.php?post=${ orderId }&action=edit` );
	assertShowcaseOrigin( page.url(), BASE_URL, "Order editor" );
	await page.getByRole( "button", { name: "Refund", exact: true } ).click();

	const quantities = page.locator( ".refund_order_item_qty" );
	for ( let index = 0; index < ( await quantities.count() ); index++ ) {
		await quantities.nth( index ).evaluate( ( element ) => {
			element.value = "1";
		} );
	}

	const lineTotals = page.locator( ".refund_line_total" );
	if ( ( await lineTotals.count() ) === 1 ) {
		await lineTotals.first().evaluate( ( element, value ) => {
			element.value = value;
		}, amount.toFixed( 2 ) );
	}
	await page.locator( "#refund_amount" ).evaluate( ( element, value ) => {
		element.value = value;
	}, amount.toFixed( 2 ) );
	await page.locator( "#refund_reason" ).evaluate( ( element ) => {
		element.value = "Agent SNR showcase refund";
	} );
	await Promise.all( [
		page.waitForEvent( "dialog", { timeout: 5_000 } ).then( async ( dialog ) => {
			if (
				dialog.type() !== "confirm" ||
				! dialog.message().includes( "process this refund" )
			) {
				await dialog.dismiss();
				throw new Error( `Unexpected refund dialog: ${ dialog.message() }` );
			}
			await dialog.accept();
		} ),
		page.waitForNavigation( { waitUntil: "domcontentloaded", timeout: 20_000 } ),
		page.locator( ".do-manual-refund" ).click(),
	] );
	await page
		.locator( "#woocommerce-order-items .wc-order-totals tr" )
		.filter( { hasText: "Net Payment" } )
		.waitFor( { state: "visible", timeout: 20_000 } );

	await page.goto( "about:blank" );
	await page.context().clearCookies();
	await page.context().addCookies( publicSessionCookies );
}

async function runShowcase( browser, outputDirectory ) {
	const context = await browser.newContext( {
		baseURL: BASE_URL,
		viewport: { width: 1440, height: 900 },
	} );
	await installModelContextMock( context );
	const page = await context.newPage();
	const consoleErrors = [];
	page.on( "console", ( message ) => {
		if (
			message.type() === "error" &&
			! message.text().includes( "status of 401" ) &&
			! message.text().includes( "Creating a worker from 'blob:" ) &&
			! message.text().includes( "Loading the image 'https://secure.gravatar.com/avatar/" )
		) {
			const location = message.location();
			consoleErrors.push( sanitizeShowcaseConsoleLocation( location.url, BASE_URL ) );
		}
	} );

	await page.goto( "/" );
	const landingScreenshot = await captureViewport(
		page,
		outputDirectory,
		"01-agent-snr-overview.png"
	);

	await page.goto( "/storefront-demo/" );
	await waitForTools( page, STOREFRONT_TOOL_COUNT );
	const guide = await callTool( page, "get_agent_guide" );
	const initialCart = await callTool( page, "get_cart" );
	const zeroSearch = await callTool( page, "search_products", {
		attributes: { water_rating: "IPX5" },
		in_stock_only: true,
		limit: 6,
		max_price: 100,
		query: "waterproof backpack",
	} );
	if (
		zeroSearch.result.result_count !== 0 ||
		zeroSearch.result.opportunity_signal?.signal_code !== "zero_results"
	) {
		throw new Error( "The zero-result opportunity signal was not recorded." );
	}
	const guideScreenshot = await captureViewport(
		page,
		outputDirectory,
		"02-agent-guide.png",
		"#wmcp-agent-guide"
	);
	const opportunityScreenshot = await captureViewport(
		page,
		outputDirectory,
		"03-zero-result-opportunity.png",
		'[data-wmcp-search-opportunity]'
	);
	const search = await callTool( page, "search_products", {
		in_stock_only: true,
		limit: 6,
		max_price: 100,
		query: "waterproof backpack",
	} );
	const byName = Object.fromEntries( search.result.products.map( ( product ) => [ product.name, product ] ) );
	const harborLite = byName[ "HarborLite 16 Pack" ];
	const rainTrail = byName[ "RainTrail 20 Pack" ];
	if ( ! harborLite || ! rainTrail ) {
		throw new Error( "The showcase products were not found." );
	}
	await callTool( page, "compare_products", {
		criteria: [ "price", "capacity", "water_rating", "weight", "return_days" ],
		product_ids: [ harborLite.id, rainTrail.id ],
	} );
	await callTool( page, "get_store_policy", { policy_type: "returns", product_id: harborLite.id } );
	const add = await callTool( page, "add_to_cart", {
		expected_cart_revision: initialCart.result.cart_revision,
		product_id: harborLite.id,
		quantity: 1,
	} );
	const handoff = await callTool( page, "prepare_checkout_handoff", {
		expected_cart_revision: add.result.cart.cart_revision,
	} );
	const feedback = await callTool( page, "report_agent_feedback", {
		evidence_event_ids: [ zeroSearch.event_id, search.event_id, handoff.event_id ],
		feedback_type: "constraint_encountered",
		outcome: "partial",
		ratings: {
			effort: "medium",
			evidence_quality: "sufficient",
			handoff_quality: "smooth",
			policy_clarity: "clear",
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
	const storefrontScreenshot = await captureViewport(
		page,
		outputDirectory,
		"04-agent-feedback-handoff.png",
		'[data-wmcp-panel="feedback"]'
	);

	await page.goto( handoff.result.checkout_url );
	await page.locator( "form.checkout" ).waitFor( { state: "visible", timeout: 20_000 } );
	await page.locator( "#payment_method_wmcp_agentops_demo" ).check();
	await Promise.all( [
		page.waitForURL( /\/checkout\/order-received\//, { timeout: 20_000 } ),
		page.locator( "#place_order" ).click(),
	] );
	const orderMatch = page.url().match( /\/order-received\/(\d+)\// );
	if ( ! orderMatch ) {
		throw new Error( "The showcase order ID was not found." );
	}
	const orderId = Number.parseInt( orderMatch[ 1 ], 10 );
	const orderScreenshot = await captureViewport(
		page,
		outputDirectory,
		"05-human-order-confirmation.png"
	);

	await page.goto( "/agentops-demo/" );
	await waitForTools( page, AGENT_SNR_TOOL_COUNT );
	const verifiedSignals = await callTool( page, "get_opportunity_signals" );
	const corroboratedSignal = verifiedSignals.result.items.find(
		( item ) => item.sources?.site_observed === true && item.sources?.agent_reported === true
	);
	if ( ! corroboratedSignal ) {
		throw new Error( "The combined observed and agent-reported signal was not found." );
	}
	await page.locator( "[data-wmcp-load-dashboard]" ).click();
	await page.locator( "[data-wmcp-workflows] [data-explain-workflow]" ).first().waitFor( {
		state: "visible",
		timeout: 20_000,
	} );
	const monitorScreenshot = await captureViewport(
		page,
		outputDirectory,
		"06-agent-snr-monitor.png",
		"#wmcp-overview"
	);
	const signalsScreenshot = await captureViewport(
		page,
		outputDirectory,
		"07-opportunity-signals.png",
		"#wmcp-gaps",
		-90
	);
	const workflowButton = page.locator( `[data-workflow-id="${ handoff.workflow_id }"] [data-explain-workflow]` );
	await workflowButton.click();
	await page.locator( "#wmcp-timeline-title" ).filter( { hasText: handoff.workflow_id.slice( 0, 8 ) } ).waitFor( {
		state: "visible",
		timeout: 20_000,
	} );
	const replayScreenshot = await captureViewport(
		page,
		outputDirectory,
		"08-workflow-replay.png",
		"#wmcp-workflows"
	);

	await callTool( page, "set_tool_enabled", {
		enabled: false,
		reason: "Showcase session governance demonstration",
		scope: "demo_session",
		tool_name: "compare_products",
	} );
	const controlsScreenshot = await captureViewport(
		page,
		outputDirectory,
		"09-session-controls.png",
		"#wmcp-governance",
		45
	);

	await refundOrder( page, orderId, Number( add.result.cart.subtotal ) );
	await page.goto( "/agentops-demo/" );
	await waitForTools( page, AGENT_SNR_TOOL_COUNT );
	await page.locator( "[data-wmcp-load-dashboard]" ).click();
	await waitForRefundEvidence( page );
	const refundScreenshot = await captureViewport(
		page,
		outputDirectory,
		"10-refund-net-outcome.png",
		"#wmcp-overview"
	);
	if ( consoleErrors.length ) {
		throw new Error(
			`Showcase captured with ${ consoleErrors.length } unexpected console error(s) at: ${ [ ...new Set( consoleErrors ) ].join( ", " ) }`
		);
	}

	const summary = {
		baseUrl: BASE_URL,
		workflowId: handoff.workflow_id,
		orderId,
		product: { id: harborLite.id, name: harborLite.name, price: harborLite.price },
		agentGuide: {
			version: guide.result.version,
			maxFeedbackReportsPerWorkflow: guide.result.feedback.max_reports_per_workflow,
		},
		opportunitySignal: {
			id: zeroSearch.result.opportunity_signal.id,
			evidenceStatus: zeroSearch.result.opportunity_signal.evidence_status,
			signalCode: zeroSearch.result.opportunity_signal.signal_code,
			source: zeroSearch.result.opportunity_signal.source,
		},
		agentFeedback: {
			feedbackId: feedback.result.feedback_id,
			evidenceStatus: feedback.result.evidence_status,
			measuredContext: feedback.result.measured_context,
			requestedMetrics: [
				"eligible_product_count",
				"highest_matching_water_rating",
				"search_refinement_count",
				"checkout_conversion",
				"paid_order_value",
			],
			trust: feedback.result.trust,
			verifiedContextAfterOrder: corroboratedSignal.measured_context,
		},
		screenshots: {
			landingScreenshot,
			guideScreenshot,
			opportunityScreenshot,
			storefrontScreenshot,
			orderScreenshot,
			monitorScreenshot,
			signalsScreenshot,
			replayScreenshot,
			controlsScreenshot,
			refundScreenshot,
		},
		consoleErrors: [],
	};
	await fs.writeFile(
		path.join( outputDirectory, "showcase-summary.json" ),
		JSON.stringify( summary, null, 2 )
	);
	return summary;
}

async function promoteCaptureDirectory( stagingDirectory, outputDirectory ) {
	const backupDirectory = `${ outputDirectory }.backup-${ randomUUID() }`;
	let hasBackup = false;

	try {
		await fs.rename( outputDirectory, backupDirectory );
		hasBackup = true;
	} catch ( error ) {
		if ( error.code !== "ENOENT" ) {
			throw error;
		}
	}

	try {
		await fs.rename( stagingDirectory, outputDirectory );
	} catch ( error ) {
		if ( hasBackup ) {
			await fs.rename( backupDirectory, outputDirectory );
		}
		throw error;
	}

	if ( hasBackup ) {
		try {
			await fs.rm( backupDirectory, { force: true, recursive: true } );
		} catch {
			// The complete new set is already live; retaining its backup is safer than rolling back.
		}
	}
}

async function main() {
	await validateCaptureOutputDirectory( OUTPUT_DIR );
	const outputParent = path.dirname( OUTPUT_DIR );
	await fs.mkdir( outputParent, { recursive: true } );
	const stagingDirectory = await fs.mkdtemp(
		path.join( outputParent, ".agent-snr-capture-" )
	);
	await fs.writeFile(
		path.join( stagingDirectory, CAPTURE_OUTPUT_MARKER ),
		CAPTURE_OUTPUT_MARKER_CONTENT
	);
	let promoted = false;
	let browser;
	try {
		browser = await chromium.launch( { headless: true } );
		const summary = await runShowcase( browser, stagingDirectory );
		await validateCaptureOutputDirectory( OUTPUT_DIR );
		await promoteCaptureDirectory( stagingDirectory, OUTPUT_DIR );
		promoted = true;
		console.log( JSON.stringify( summary, null, 2 ) );
	} finally {
		if ( browser ) {
			await browser.close();
		}
		if ( ! promoted ) {
			await fs.rm( stagingDirectory, { force: true, recursive: true } );
		}
	}
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href ) {
	main().catch( ( error ) => {
		console.error( error );
		process.exitCode = 1;
	} );
}
