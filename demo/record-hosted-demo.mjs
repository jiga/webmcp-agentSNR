import { execFile } from "node:child_process";
import { createHash, randomUUID } from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";
import { chromium } from "playwright";

const execFileAsync = promisify( execFile );
const REPO_ROOT = path.resolve( import.meta.dirname, ".." );
const BASE_URL = new URL( process.env.AGENT_SNR_DEMO_URL || "https://agent-snr.onrender.com/" );
const OUTPUT_PATH = path.resolve(
	process.env.AGENT_SNR_VIDEO_OUTPUT || path.join( REPO_ROOT, "dist/agent-snr-devpost-demo-v4-system.mp4" )
);
const TRANSCRIPT_PATH = path.resolve(
	process.env.AGENT_SNR_TRANSCRIPT_OUTPUT ||
		path.join( REPO_ROOT, "dist/agent-snr-devpost-narration-v4.txt" )
);
const TIMELINE_PATH = path.resolve(
	process.env.AGENT_SNR_TIMELINE_OUTPUT ||
		path.join( REPO_ROOT, "dist/agent-snr-devpost-timeline-v4.json" )
);
const WORK_ROOT = path.resolve( REPO_ROOT, ".release-test" );
const VIDEO_SIZE = { height: 900, width: 1600 };
const STOREFRONT_TOOL_COUNT = 12;
const AGENT_SNR_TOOL_COUNT = 8;
const VOICE = process.env.AGENT_SNR_NARRATION_VOICE || "Samantha";
const VOICE_RATE = process.env.AGENT_SNR_NARRATION_RATE || "175";

if ( BASE_URL.protocol !== "https:" || BASE_URL.hostname !== "agent-snr.onrender.com" ) {
	throw new Error( "AGENT_SNR_DEMO_URL must be the frozen Agent SNR Render origin." );
}

const scenes = [
	{
		duration: 4,
		id: "intro",
		title: "AGENT SNR",
		subtitle: "Future-world story intro replaces this capture scene",
		narration: "Agent SNR.",
	},
	{
		duration: 16,
		id: "guide",
		title: "SHOPPER + AGENT",
		subtitle: "Natural-language request → tool discovery → Agent Guide",
		narration: "A shopper asks for a compact IPX5 backpack under one hundred dollars. Their agent discovers twelve tools and reads the guide first. Search and cart stay reversible, while checkout remains human-reviewed. With those boundaries clear, the agent begins searching.",
	},
	{
		duration: 16,
		id: "zero-result",
		title: "REAL WEBMCP CALL · ZERO RESULTS",
		subtitle: "search_products → structured evidence → missed-demand signal",
		narration: "The request uses the shopper's exact constraints and succeeds, but returns zero matches. Agent SNR records the missed IPX5 demand without storing the raw prompt. A normal success log would miss it.",
	},
	{
		duration: 20,
		id: "recovery",
		title: "THE AGENT RECOVERS",
		subtitle: "IPX4 alternatives → comparison → returns evidence",
		narration: "The agent explains the constraint and relaxes only the water rating. A second search finds two compact IPX4 options. It compares product facts and checks returns before recommending HarborLite. Missing facts stay missing.",
	},
	{
		duration: 20,
		id: "handoff",
		title: "PREPARE · REPORT · STOP",
		subtitle: "Cart mutation → agent feedback → human checkout boundary",
		narration: "With the shopper's choice, the agent adds HarborLite to a reversible cart. It prepares checkout and reports the missing IPX5 requirement with linked evidence. The handoff remains linked to the workflow. Checkout sits outside the WebMCP toolset, so the shopper reviews and confirms the order.",
	},
	{
		duration: 16,
		id: "checkout",
		title: "THE SHOPPER COMMITS",
		subtitle: "Explicit human review · fictional data · no-charge order",
		narration: "The shopper reviews checkout and places this no-charge demo order. That click verifies the outcome for the same journey. The order verifies the outcome for this workflow. Agent SNR stores neither address nor payment details.",
	},
	{
		duration: 18,
		id: "monitor",
		title: "OWNER + AGENT",
		subtitle: "Natural-language question → Agent SNR operator tools",
		narration: "The shopper is done. Now the owner's agent asks what the site learned. It discovers eight Agent SNR tools. Analytics show the attributed order and the missed IPX5 opportunity behind the conversion.",
	},
	{
		duration: 20,
		id: "replay",
		title: "INVESTIGATE THE SIGNAL",
		subtitle: "Missed demand + replay + verified conversion",
		narration: "Replay connects the zero result, recovery, feedback, human checkpoint, and order. Each claim keeps its source: site observed, agent reported, or site verified. The owner sees this without raw customer data. Agent opinion never becomes business fact. Lost revenue is never invented.",
	},
	{
		duration: 16,
		id: "decision",
		title: "ONE JOURNEY · A BUSINESS DECISION",
		subtitle: "Improve IPX5 coverage · preserve the proven recovery path",
		narration: "Now the owner has a decision: add IPX5 inventory and keep the proven IPX4 recovery. Agent SNR turns silent journeys into evidence the store can trust and improve.",
	},
];

function escapeConcatPath( value ) {
	return value.replaceAll( "'", "'\\''" );
}

async function runCommand( command, args, options = {} ) {
	return execFileAsync( command, args, {
		cwd: REPO_ROOT,
		maxBuffer: 8 * 1024 * 1024,
		...options,
	} );
}

async function probeDuration( filePath ) {
	const { stdout } = await runCommand( "ffprobe", [
		"-v",
		"error",
		"-show_entries",
		"format=duration",
		"-of",
		"default=noprint_wrappers=1:nokey=1",
		filePath,
	] );
	const duration = Number.parseFloat( stdout.trim() );
	if ( ! Number.isFinite( duration ) || duration <= 0 ) {
		throw new Error( `Could not read audio/video duration for ${ filePath }.` );
	}
	return duration;
}

async function assertPathAbsent( filePath ) {
	await fs.access( filePath ).then(
		() => {
			throw new Error( `Refusing to overwrite existing output: ${ filePath }` );
		},
		( error ) => {
			if ( error.code !== "ENOENT" ) {
				throw error;
			}
		}
	);
}

async function sha256( filePath ) {
	return createHash( "sha256" ).update( await fs.readFile( filePath ) ).digest( "hex" );
}

async function generateNarration( workDirectory ) {
	for ( const [ index, scene ] of scenes.entries() ) {
		const prefix = String( index + 1 ).padStart( 2, "0" );
		scene.audioPath = path.join( workDirectory, `${ prefix }-${ scene.id }.aiff` );
		await runCommand( "/usr/bin/say", [
			"-v",
			VOICE,
			"-r",
			VOICE_RATE,
			"-o",
			scene.audioPath,
			scene.narration,
		] );
		scene.audioDuration = await probeDuration( scene.audioPath );
		if ( scene.audioDuration > scene.duration - 0.5 ) {
			throw new Error( `${ scene.id } narration exceeds its ${ scene.duration } second scene.` );
		}
	}
}

async function installModelContextMock( context ) {
	await context.addInitScript( () => {
		const state = { activeTools: new Map() };
		globalThis.__agentSnrRecording = state;
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
			globalThis.__agentSnrRecording?.activeTools?.size === expected,
		count,
		{ timeout: 30_000 }
	);
}

async function setAgentPrompt( page, persona, prompt ) {
	await page.evaluate(
		( content ) => {
			let panel = document.querySelector( "#agent-snr-live-agent" );
			if ( ! panel ) {
				panel = document.createElement( "aside" );
				panel.id = "agent-snr-live-agent";
				panel.innerHTML = `
					<div data-agent-header><span data-agent-dot></span><strong>Browser Agent · WebMCP</strong><small>LIVE</small></div>
					<div data-agent-persona></div>
					<div data-agent-prompt></div>
					<div data-agent-events></div>
				`;
				Object.assign( panel.style, {
					background: "rgba(3, 14, 25, 0.97)",
					border: "1px solid rgba(65, 238, 154, 0.72)",
					borderRadius: "14px",
					bottom: "20px",
					boxShadow: "0 20px 60px rgba(0, 0, 0, 0.46)",
					color: "#f5f8f6",
					display: "grid",
					fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif",
					gridTemplateRows: "auto auto auto 1fr",
					overflow: "hidden",
					pointerEvents: "none",
					position: "fixed",
					right: "20px",
					top: "20px",
					width: "500px",
					zIndex: "2147483646",
				} );
				const header = panel.querySelector( "[data-agent-header]" );
				Object.assign( header.style, {
					alignItems: "center",
					background: "#071f2f",
					borderBottom: "1px solid rgba(255,255,255,.1)",
					display: "flex",
					fontSize: "15px",
					gap: "9px",
					padding: "14px 16px",
				} );
				Object.assign( panel.querySelector( "[data-agent-dot]" ).style, {
					background: "#41ee9a",
					borderRadius: "999px",
					boxShadow: "0 0 12px rgba(65,238,154,.85)",
					height: "9px",
					width: "9px",
				} );
				Object.assign( header.querySelector( "small" ).style, {
					color: "#41ee9a",
					fontSize: "11px",
					fontWeight: "800",
					letterSpacing: ".12em",
					marginLeft: "auto",
				} );
				Object.assign( panel.querySelector( "[data-agent-persona]" ).style, {
					color: "#8bd9ff",
					fontSize: "12px",
					fontWeight: "800",
					letterSpacing: ".1em",
					padding: "14px 16px 4px",
					textTransform: "uppercase",
				} );
				Object.assign( panel.querySelector( "[data-agent-prompt]" ).style, {
					background: "rgba(139,217,255,.09)",
					border: "1px solid rgba(139,217,255,.24)",
					borderRadius: "9px",
					fontSize: "15px",
					lineHeight: "1.42",
					margin: "8px 16px 12px",
					padding: "12px 13px",
				} );
				Object.assign( panel.querySelector( "[data-agent-events]" ).style, {
					display: "grid",
					gap: "9px",
					overflow: "hidden",
					padding: "0 16px 16px",
				} );
				document.body.append( panel );
			}
			panel.querySelector( "[data-agent-persona]" ).textContent = content.persona;
			panel.querySelector( "[data-agent-prompt]" ).textContent = `“${ content.prompt }”`;
			panel.querySelector( "[data-agent-events]" ).replaceChildren();
		},
		{ persona, prompt }
	);
}

async function appendAgentEvent( page, kind, title, detail ) {
	await page.evaluate(
		( event ) => {
			const events = document.querySelector( "#agent-snr-live-agent [data-agent-events]" );
			if ( ! events ) {
				return;
			}
			const card = document.createElement( "div" );
			card.dataset.kind = event.kind;
			Object.assign( card.style, {
				background: event.kind === "result" ? "rgba(65,238,154,.09)" : "rgba(255,255,255,.055)",
				border: `1px solid ${ event.kind === "result" ? "rgba(65,238,154,.28)" : "rgba(255,255,255,.13)" }`,
				borderRadius: "9px",
				display: "grid",
				gap: "4px",
				padding: "10px 12px",
			} );
			const label = document.createElement( "strong" );
			label.textContent = `${ event.kind === "result" ? "✓ RESULT" : event.kind === "reasoning" ? "AGENT" : "→ TOOL" } · ${ event.title }`;
			Object.assign( label.style, {
				color: event.kind === "result" ? "#74f0ad" : event.kind === "reasoning" ? "#ffd27a" : "#8bd9ff",
				fontSize: "12px",
				letterSpacing: ".05em",
			} );
			const copy = document.createElement( "span" );
			copy.textContent = event.detail;
			Object.assign( copy.style, {
				color: "#dce7e2",
				fontFamily: event.kind === "reasoning" ? "inherit" : "ui-monospace, SFMono-Regular, Menlo, monospace",
				fontSize: "12px",
				lineHeight: "1.38",
			} );
			card.append( label, copy );
			events.append( card );
			while ( events.children.length > 4 ) {
				events.firstElementChild.remove();
			}
		},
		{ detail, kind, title }
	);
	await page.waitForTimeout( kind === "tool" ? 350 : 500 );
}

function compactToolInput( input ) {
	return JSON.stringify( input ).replaceAll( '"', "" ).slice( 0, 180 );
}

function summarizeToolResult( name, response ) {
	const result = response?.result || {};
	const summaries = {
		add_to_cart: () => `${ result.cart?.item_count ?? 1 } item in cart · reversible`,
		compare_products: () => `${ result.products?.length ?? result.comparison?.length ?? 2 } products compared from stored facts`,
		explain_agent_workflow: () => `${ result.events?.length ?? result.event_count ?? "Recorded" } workflow events · outcome ${ result.outcome || "verified" }`,
		get_agent_analytics_overview: () => `${ result.total_workflows ?? result.workflow_count ?? "Live" } workflows · ${ result.attributed_orders ?? result.order_count ?? 1 } attributed order`,
		get_agent_conversion_funnel: () => "Journey stages and verified conversion loaded",
		get_agent_guide: () => `Guide ${ result.version || "1.1" } · checkout is human-owned`,
		get_cart: () => `${ result.item_count ?? 0 } items · cart revision captured`,
		get_opportunity_signals: () => `${ result.items?.length ?? result.signals?.length ?? "Active" } evidence-backed signals`,
		get_store_policy: () => "Published returns evidence loaded",
		get_tool_health: () => `${ result.items?.length ?? result.tools?.length ?? "All" } tool-health records loaded`,
		prepare_checkout_handoff: () => "Cart validated · human checkout URL prepared · no order placed",
		query_agent_workflows: () => `${ result.items?.length ?? result.workflows?.length ?? "Recent" } workflows returned`,
		report_agent_feedback: () => "Bounded feedback receipt recorded and linked to site evidence",
		search_products: () => `${ result.result_count ?? result.products?.length ?? 0 } matching products · structured result`,
		set_tool_enabled: () => `${ result.tool_name || "compare_products" } disabled for this demo session`,
	};
	return ( summaries[ name ] || ( () => "Successful structured response" ) )();
}

async function callTool( page, name, input = {} ) {
	await appendAgentEvent( page, "tool", name, compactToolInput( input ) || "{}" );
	const response = await page.evaluate(
		async ( invocation ) => {
			const definition = globalThis.__agentSnrRecording.activeTools.get( invocation.name );
			if ( ! definition ) {
				throw new Error( `Tool ${ invocation.name } is not registered.` );
			}
			return definition.execute( invocation.input );
		},
		{ input, name }
	);
	if ( response?.ok !== true ) {
		throw new Error( `${ name } failed: ${ JSON.stringify( response ) }` );
	}
	await appendAgentEvent( page, "result", name, summarizeToolResult( name, response ) );
	return response;
}

async function showOverlay( page, title, subtitle ) {
	await page.evaluate(
		( copy ) => {
			let overlay = document.querySelector( "#agent-snr-video-overlay" );
			if ( ! overlay ) {
				overlay = document.createElement( "aside" );
				overlay.id = "agent-snr-video-overlay";
				overlay.innerHTML = '<strong data-title></strong><span data-subtitle></span>';
				Object.assign( overlay.style, {
					background: "rgba(4, 16, 28, 0.94)",
					border: "1px solid rgba(65, 238, 154, 0.78)",
					borderRadius: "10px",
					bottom: "26px",
					boxShadow: "0 16px 42px rgba(0, 0, 0, 0.34)",
					color: "#f6f8f5",
					display: "grid",
					fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif",
					gap: "5px",
					left: "28px",
					maxWidth: "720px",
					opacity: "0",
					padding: "16px 20px",
					pointerEvents: "none",
					position: "fixed",
					transform: "translateY(12px)",
					transition: "opacity 220ms ease, transform 220ms ease",
					zIndex: "2147483647",
				} );
				Object.assign( overlay.querySelector( "[data-title]" ).style, {
					fontSize: "23px",
					fontWeight: "800",
					letterSpacing: "0.04em",
				} );
				Object.assign( overlay.querySelector( "[data-subtitle]" ).style, {
					color: "#aeecc9",
					fontSize: "16px",
					lineHeight: "1.35",
				} );
				document.body.append( overlay );
			}
			overlay.querySelector( "[data-title]" ).textContent = copy.title;
			overlay.querySelector( "[data-subtitle]" ).textContent = copy.subtitle;
			requestAnimationFrame( () => {
				overlay.style.opacity = "1";
				overlay.style.transform = "translateY(0)";
			} );
		},
		{ subtitle, title }
	);
}

async function scrollTo( page, selector, offset = 0 ) {
	await page.locator( selector ).scrollIntoViewIfNeeded();
	if ( offset ) {
		await page.evaluate( ( value ) => window.scrollBy( 0, value ), offset );
	}
	await page.waitForTimeout( 450 );
}

async function runScene( page, scene, action, timeline, recordingOrigin ) {
	const startedAt = Date.now();
	await action();
	const actionCompletedAt = Date.now();
	const remaining = scene.duration * 1000 - ( actionCompletedAt - startedAt );
	if ( remaining > 0 ) {
		await page.waitForTimeout( remaining );
	}
	const endedAt = Date.now();
	timeline.push( {
		actionCompletedSeconds: ( actionCompletedAt - recordingOrigin ) / 1000,
		actualDurationSeconds: ( endedAt - startedAt ) / 1000,
		endSeconds: ( endedAt - recordingOrigin ) / 1000,
		id: scene.id,
		plannedDurationSeconds: scene.duration,
		startSeconds: ( startedAt - recordingOrigin ) / 1000,
	} );
}

async function recordVideo( workDirectory ) {
	const browser = await chromium.launch( { headless: true } );
	const context = await browser.newContext( {
		baseURL: BASE_URL.origin,
		recordVideo: { dir: workDirectory, size: VIDEO_SIZE },
		viewport: VIDEO_SIZE,
	} );
	await installModelContextMock( context );
	const page = await context.newPage();
	const video = page.video();
	try {
	const recordingStartedAt = Date.now();
	const sceneTimeline = [];
	const consoleErrors = [];
	page.on( "console", ( message ) => {
		if (
			message.type() === "error" &&
			! message.text().includes( "status of 401" ) &&
			! message.text().includes( "Creating a worker from 'blob:" ) &&
			! message.text().includes( "Loading the image 'https://secure.gravatar.com/avatar/" )
		) {
			consoleErrors.push( message.text() );
		}
	} );
	const state = {};

	await page.goto( BASE_URL.origin, { waitUntil: "domcontentloaded" } );
	await page.locator( "h1" ).first().waitFor( { state: "visible", timeout: 30_000 } );
	const startupDuration = ( Date.now() - recordingStartedAt ) / 1000;

	await runScene( page, scenes[ 0 ], async () => {
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await setAgentPrompt(
			page,
			"PROJECT ARCHITECTURE",
			"How does this WordPress plugin use WebMCP and connect agent activity to business outcomes?"
		);
		await appendAgentEvent( page, "reasoning", "PLUGIN FLOW", "Top-level page tools → same-origin PHP → commerce record + redacted outcome ledger" );
		await showOverlay( page, scenes[ 0 ].title, scenes[ 0 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 1 ], async () => {
		await page.goto( "/storefront-demo/", { waitUntil: "domcontentloaded" } );
		await waitForTools( page, STOREFRONT_TOOL_COUNT );
		await setAgentPrompt(
			page,
			"SHOPPER REQUEST",
			"Find a compact waterproof backpack under $100 with IPX5 protection. If none match, show the closest options, prepare checkout, and follow the guide's feedback instructions."
		);
		await appendAgentEvent( page, "reasoning", "DISCOVERY", "12 registered storefront tools found. Read the site guide before acting." );
		state.guide = await callTool( page, "get_agent_guide" );
		state.initialCart = await callTool( page, "get_cart" );
		await scrollTo( page, "#wmcp-agent-guide", -25 );
		await showOverlay( page, scenes[ 1 ].title, scenes[ 1 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 2 ], async () => {
		state.zeroSearch = await callTool( page, "search_products", {
			attributes: { water_rating: "IPX5" },
			in_stock_only: true,
			limit: 6,
			max_price: 100,
			query: "waterproof backpack",
		} );
		if ( state.zeroSearch.result.result_count !== 0 ) {
			throw new Error( "The hosted IPX5 search did not return the expected zero result." );
		}
		await appendAgentEvent( page, "reasoning", "DECISION", "No exact IPX5 match. Preserve budget and compact size; relax only water rating to IPX4." );
		await scrollTo( page, '[data-wmcp-panel="search"]', -80 );
		await showOverlay( page, scenes[ 2 ].title, scenes[ 2 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 3 ], async () => {
		state.search = await callTool( page, "search_products", {
			attributes: { water_rating: "IPX4" },
			in_stock_only: true,
			limit: 6,
			max_price: 100,
			query: "waterproof backpack",
		} );
		const byName = Object.fromEntries(
			state.search.result.products.map( ( product ) => [ product.name, product ] )
		);
		state.harborLite = byName[ "HarborLite 16 Pack" ];
		state.rainTrail = byName[ "RainTrail 20 Pack" ];
		if ( ! state.harborLite || ! state.rainTrail ) {
			throw new Error( "The two expected IPX4 products were not returned." );
		}
		await appendAgentEvent( page, "reasoning", "RECOVERY", "Two eligible IPX4 alternatives found. Compare the decision-relevant facts and verify returns." );
		await callTool( page, "compare_products", {
			criteria: [ "price", "capacity", "water_rating", "weight", "return_days" ],
			product_ids: [ state.harborLite.id, state.rainTrail.id ],
		} );
		await callTool( page, "get_store_policy", {
			policy_type: "returns",
			product_id: state.harborLite.id,
		} );
		await appendAgentEvent( page, "reasoning", "RECOMMENDATION", "HarborLite is the compact choice under budget; disclose the IPX4 versus IPX5 constraint." );
		await scrollTo( page, '[data-wmcp-panel="comparison"]', -60 );
		await showOverlay( page, scenes[ 3 ].title, scenes[ 3 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 4 ], async () => {
		state.add = await callTool( page, "add_to_cart", {
			expected_cart_revision: state.initialCart.result.cart_revision,
			product_id: state.harborLite.id,
			quantity: 1,
		} );
		state.handoff = await callTool( page, "prepare_checkout_handoff", {
			expected_cart_revision: state.add.result.cart.cart_revision,
		} );
		await callTool( page, "report_agent_feedback", {
			evidence_event_ids: [
				state.zeroSearch.event_id,
				state.search.event_id,
				state.handoff.event_id,
			],
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
			],
			step: "checkout_handoff",
			suggested_owner_action: "improve_product_coverage",
		} );
		await appendAgentEvent( page, "reasoning", "HUMAN HANDOFF", "Cart is ready and feedback is recorded. Stop before customer data, terms, and order placement." );
		await scrollTo( page, '[data-wmcp-panel="feedback"]', -60 );
		await showOverlay( page, scenes[ 4 ].title, scenes[ 4 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 5 ], async () => {
		await page.goto( state.handoff.result.checkout_url, { waitUntil: "domcontentloaded" } );
		await page.locator( "form.checkout" ).waitFor( { state: "visible", timeout: 30_000 } );
		await setAgentPrompt(
			page,
			"HUMAN CHECKPOINT",
			"Review the prepared cart and explicitly place this fictional, no-charge demo order."
		);
		await appendAgentEvent( page, "reasoning", "AGENT STOPPED", "No WebMCP checkout or payment tool exists. The shopper now controls the final commitment." );
		await page.locator( "#payment_method_wmcp_agentsnr_demo" ).check();
		await showOverlay( page, scenes[ 5 ].title, scenes[ 5 ].subtitle );
		await page.waitForTimeout( 4_000 );
		await Promise.all( [
			page.waitForURL( /\/checkout\/order-received\//, { timeout: 30_000 } ),
			page.locator( "#place_order" ).click(),
		] );
		await page.locator( "body" ).waitFor( { state: "visible", timeout: 30_000 } );
		await page.waitForTimeout( 1_000 );
		await showOverlay( page, "ORDER VERIFIED", "Human-confirmed · paid · no-charge demo" );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 6 ], async () => {
		await page.goto( "/agentsnr-demo/", { waitUntil: "domcontentloaded" } );
		await waitForTools( page, AGENT_SNR_TOOL_COUNT );
		await setAgentPrompt(
			page,
			"STORE OWNER REQUEST",
			"Show orders attributed to agents, surface missed product demand, and explain the shopper journey that just converted."
		);
		await appendAgentEvent( page, "reasoning", "DISCOVERY", "8 registered Agent SNR operator tools found. Start with outcomes, then investigate evidence." );
		await callTool( page, "get_agent_analytics_overview" );
		await callTool( page, "get_agent_conversion_funnel" );
		await callTool( page, "get_tool_health" );
		state.workflows = await callTool( page, "query_agent_workflows", { limit: 20 } );
		await callTool( page, "get_opportunity_signals", { limit: 8 } );
		await page.locator( "[data-wmcp-load-dashboard]" ).click();
		await scrollTo( page, "#wmcp-overview", -10 );
		await showOverlay( page, scenes[ 6 ].title, scenes[ 6 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 7 ], async () => {
		await appendAgentEvent( page, "reasoning", "INVESTIGATION", "Open the IPX5 missed-demand signal, then explain the exact converted workflow." );
		await scrollTo( page, "#wmcp-gaps", -80 );
		await showOverlay( page, scenes[ 7 ].title, scenes[ 7 ].subtitle );
		await page.waitForTimeout( 5_000 );
		await callTool( page, "explain_agent_workflow", {
			workflow_id: state.handoff.workflow_id,
		} );
		await appendAgentEvent( page, "reasoning", "OWNER INSIGHT", "Demand was real, recovery converted, and product coverage—not tool reliability—is the opportunity." );
		await scrollTo( page, "#wmcp-workflows", -70 );
		await page.waitForTimeout( 5_000 );
	}, sceneTimeline, recordingStartedAt );

	await runScene( page, scenes[ 8 ], async () => {
		await setAgentPrompt(
			page,
			"STORE OWNER DECISION",
			"What should I change for shoppers who need IPX5 protection under this budget?"
		);
		await callTool( page, "get_opportunity_signals", { limit: 8 } );
		await appendAgentEvent( page, "reasoning", "OWNER DECISION", "Add IPX5 inventory. Keep the proven IPX4 recovery path until product coverage improves." );
		await scrollTo( page, "#wmcp-gaps", -70 );
		await showOverlay( page, scenes[ 8 ].title, scenes[ 8 ].subtitle );
	}, sceneTimeline, recordingStartedAt );

	if ( consoleErrors.length ) {
		throw new Error( `Unexpected browser console errors: ${ consoleErrors.join( " | " ) }` );
	}

	await page.close();
	await context.close();
	const rawVideoPath = await video.path();
	await browser.close();
	return {
		rawVideoPath,
		sceneTimeline,
		startupDuration,
		workflowId: state.handoff.workflow_id,
	};
	} catch ( error ) {
		await context.close().catch( () => {} );
		await browser.close().catch( () => {} );
		throw error;
	}
}

async function buildAudioTrack( workDirectory, startupDuration ) {
	const silencePath = path.join( workDirectory, "00-startup-silence.wav" );
	await runCommand( "ffmpeg", [
		"-y",
		"-f",
		"lavfi",
		"-i",
		"anullsrc=r=48000:cl=stereo",
		"-t",
		startupDuration.toFixed( 3 ),
		silencePath,
	] );

	const audioParts = [ silencePath ];
	for ( const [ index, scene ] of scenes.entries() ) {
		const paddedPath = path.join(
			workDirectory,
			`${ String( index + 1 ).padStart( 2, "0" ) }-${ scene.id }-padded.wav`
		);
		await runCommand( "ffmpeg", [
			"-y",
			"-i",
			scene.audioPath,
			"-af",
			"apad",
			"-t",
			scene.duration.toFixed( 3 ),
			"-ar",
			"48000",
			"-ac",
			"2",
			paddedPath,
		] );
		audioParts.push( paddedPath );
	}

	const concatPath = path.join( workDirectory, "audio-concat.txt" );
	await fs.writeFile(
		concatPath,
		audioParts.map( ( item ) => `file '${ escapeConcatPath( item ) }'` ).join( "\n" ) + "\n"
	);
	const audioPath = path.join( workDirectory, "narration.wav" );
	await runCommand( "ffmpeg", [
		"-y",
		"-f",
		"concat",
		"-safe",
		"0",
		"-i",
		concatPath,
		"-c",
		"copy",
		audioPath,
	] );
	return audioPath;
}

async function renderFinalVideo( rawVideoPath, audioPath ) {
	await fs.mkdir( path.dirname( OUTPUT_PATH ), { recursive: true } );
	await runCommand( "ffmpeg", [
		"-y",
		"-i",
		rawVideoPath,
		"-i",
		audioPath,
		"-filter_complex",
		"[0:v]scale=1920:1080:flags=lanczos,format=yuv420p[v];[1:a]loudnorm=I=-16:TP=-1.5:LRA=11,apad[a]",
		"-map",
		"[v]",
		"-map",
		"[a]",
		"-c:v",
		"libx264",
		"-preset",
		"medium",
		"-crf",
		"20",
		"-c:a",
		"aac",
		"-b:a",
		"192k",
		"-movflags",
		"+faststart",
		"-shortest",
		OUTPUT_PATH,
	] );
}

async function writeTimeline( recording, renderedDuration ) {
	const guide = recording.sceneTimeline.find( ( scene ) => scene.id === "guide" );
	const zeroResult = recording.sceneTimeline.find( ( scene ) => scene.id === "zero-result" );
	if ( ! guide || ! zeroResult ) {
		throw new Error( "Required guide and zero-result scene boundaries were not recorded." );
	}
	await fs.mkdir( path.dirname( TIMELINE_PATH ), { recursive: true } );
	await fs.writeFile(
		TIMELINE_PATH,
		`${ JSON.stringify( {
			coldOpenStartSeconds: zeroResult.startSeconds,
			liveDemoEndSeconds: recording.sceneTimeline.at( -1 ).endSeconds,
			liveDemoStartSeconds: guide.startSeconds,
			renderedDurationSeconds: renderedDuration,
			renderedSha256: await sha256( OUTPUT_PATH ),
			scenes: recording.sceneTimeline,
			workflowId: recording.workflowId,
		}, null, 2 ) }\n`
	);
}

async function writeTranscript() {
	await fs.mkdir( path.dirname( TRANSCRIPT_PATH ), { recursive: true } );
	await fs.writeFile(
		TRANSCRIPT_PATH,
		scenes
			.map(
				( scene, index ) =>
					`${ index + 1 }. ${ scene.title }\n${ scene.narration }\n`
			)
			.join( "\n" )
	);
}

async function main() {
	await Promise.all( [ OUTPUT_PATH, TRANSCRIPT_PATH, TIMELINE_PATH ].map( assertPathAbsent ) );
	await fs.mkdir( WORK_ROOT, { recursive: true } );
	const workDirectory = await fs.mkdtemp(
		path.join( WORK_ROOT, `hosted-demo-video-${ randomUUID() }-` )
	);
	await generateNarration( workDirectory );
	const recording = await recordVideo( workDirectory );
	const audioPath = await buildAudioTrack( workDirectory, recording.startupDuration );
	await renderFinalVideo( recording.rawVideoPath, audioPath );
	await writeTranscript();
	const duration = await probeDuration( OUTPUT_PATH );
	await writeTimeline( recording, duration );
	console.log(
		JSON.stringify(
			{
				duration,
				outputPath: OUTPUT_PATH,
				scenes: scenes.map( ( scene ) => ( {
					audioDuration: scene.audioDuration,
					duration: scene.duration,
					id: scene.id,
				} ) ),
				transcriptPath: TRANSCRIPT_PATH,
				timelinePath: TIMELINE_PATH,
				workflowId: recording.workflowId,
				workDirectory,
			},
			null,
			2
		)
	);
}

main().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
