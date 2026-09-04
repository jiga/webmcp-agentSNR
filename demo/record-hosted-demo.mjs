import { execFile } from "node:child_process";
import { randomUUID } from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";
import { chromium } from "playwright";

const execFileAsync = promisify( execFile );
const REPO_ROOT = path.resolve( import.meta.dirname, ".." );
const BASE_URL = new URL( process.env.AGENT_SNR_DEMO_URL || "https://agent-snr.onrender.com/" );
const OUTPUT_PATH = path.resolve(
	process.env.AGENT_SNR_VIDEO_OUTPUT || path.join( REPO_ROOT, "dist/agent-snr-devpost-demo.mp4" )
);
const TRANSCRIPT_PATH = path.resolve(
	process.env.AGENT_SNR_TRANSCRIPT_OUTPUT ||
		path.join( REPO_ROOT, "dist/agent-snr-devpost-narration.txt" )
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
		id: "intro",
		minimumSeconds: 11,
		title: "AGENT SNR",
		subtitle: "Verified outcomes over raw agent noise",
		narration:
			"Most websites can expose actions to agents. Agent SNR shows what happened after those actions: the evidence used, the human checkpoint, the business outcome, and what the site should improve next.",
	},
	{
		id: "guide",
		minimumSeconds: 14,
		title: "1 · THE SITE TEACHES THE AGENT",
		subtitle: "Guide 1.1 · reversible actions · human-owned checkout",
		narration:
			"The live storefront publishes twelve WebMCP tools and a plain-language Agent Guide. It tells the browser agent which journeys are supported, which actions are reversible, what data is excluded, and exactly where checkout returns to a person.",
	},
	{
		id: "zero-result",
		minimumSeconds: 15,
		title: "2 · MISSED DEMAND BECOMES A SIGNAL",
		subtitle: "IPX5 under $100 · zero matches · no raw prompt stored",
		narration:
			"The shopper wants an in-stock waterproof backpack under one hundred dollars with IPX5 protection. No product matches. Instead of hiding that failure, the site records a privacy-safe, site-observed opportunity without storing the shopper's raw prompt.",
	},
	{
		id: "recovery",
		minimumSeconds: 17,
		title: "3 · RECOVER WITH EVIDENCE",
		subtitle: "Relax one constraint · compare stored facts · verify policy",
		narration:
			"The agent relaxes only the water rating and finds two compact IPX4 options. It compares stored product facts and checks the published returns policy. Missing information stays missing; the tool never invents a recommendation or a final total.",
	},
	{
		id: "handoff",
		minimumSeconds: 19,
		title: "4 · PREPARE, REPORT, HAND OFF",
		subtitle: "Reversible cart · structured feedback · no order yet",
		narration:
			"HarborLite is added to the session cart. The agent reports the constraint using linked site evidence, while Agent SNR keeps testimony separate from verified measurements. Checkout preparation validates the cart and stops. No WebMCP tool can place an order or process payment.",
	},
	{
		id: "checkout",
		minimumSeconds: 15,
		title: "5 · THE PERSON COMMITS",
		subtitle: "Normal WooCommerce checkout · fictional data · no charge",
		narration:
			"The person reviews the normal WooCommerce checkout, accepts the fictional demo details, and explicitly places the no-charge order. The human remains responsible for customer data, terms, and the final commitment.",
	},
	{
		id: "monitor",
		minimumSeconds: 17,
		title: "6 · VERIFY THE BUSINESS OUTCOME",
		subtitle: "Same browser scope · paid order · deterministic attribution",
		narration:
			"On the separate Agent SNR surface, eight operator tools read the same browser scope. The monitor connects the original tool journey to the paid WooCommerce order, while keeping raw prompts, addresses, payment details, cookies, and payloads out of the ledger.",
	},
	{
		id: "replay",
		minimumSeconds: 20,
		title: "7 · REPLAY, SIGNALS, AND RECOVERY",
		subtitle: "Site observed · Agent reported · Site verified",
		narration:
			"Workflow Replay shows terminal tool outcomes, latency, recovery, product evidence, feedback, and commerce attribution. Signals preserve three trust classes: what the site observed, what the agent reported, and what the catalog and WooCommerce verified. Lost revenue is never invented.",
	},
	{
		id: "control",
		minimumSeconds: 15,
		title: "8 · CLOSE THE LOOP",
		subtitle: "Session-safe governance · reproducible evidence · open source",
		narration:
			"Finally, the merchant disables comparison for only this demo session. The server enforces the restriction and refreshes the browser's tool catalog without affecting another judge. Agent SNR turns agent activity into evidence, decisions, and a safer next journey.",
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
		scene.duration = Math.max( scene.minimumSeconds, scene.audioDuration + 0.9 );
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

async function callTool( page, name, input = {} ) {
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

async function runScene( page, scene, action ) {
	const startedAt = Date.now();
	await action();
	const remaining = scene.duration * 1000 - ( Date.now() - startedAt );
	if ( remaining > 0 ) {
		await page.waitForTimeout( remaining );
	}
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
		await showOverlay( page, scenes[ 0 ].title, scenes[ 0 ].subtitle );
	} );

	await runScene( page, scenes[ 1 ], async () => {
		await page.goto( "/storefront-demo/", { waitUntil: "domcontentloaded" } );
		await waitForTools( page, STOREFRONT_TOOL_COUNT );
		state.guide = await callTool( page, "get_agent_guide" );
		state.initialCart = await callTool( page, "get_cart" );
		await scrollTo( page, "#wmcp-agent-guide", -25 );
		await showOverlay( page, scenes[ 1 ].title, scenes[ 1 ].subtitle );
	} );

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
		await scrollTo( page, '[data-wmcp-panel="search"]', -80 );
		await showOverlay( page, scenes[ 2 ].title, scenes[ 2 ].subtitle );
	} );

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
		await callTool( page, "compare_products", {
			criteria: [ "price", "capacity", "water_rating", "weight", "return_days" ],
			product_ids: [ state.harborLite.id, state.rainTrail.id ],
		} );
		await callTool( page, "get_store_policy", {
			policy_type: "returns",
			product_id: state.harborLite.id,
		} );
		await scrollTo( page, '[data-wmcp-panel="comparison"]', -60 );
		await showOverlay( page, scenes[ 3 ].title, scenes[ 3 ].subtitle );
	} );

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
		await scrollTo( page, '[data-wmcp-panel="feedback"]', -60 );
		await showOverlay( page, scenes[ 4 ].title, scenes[ 4 ].subtitle );
	} );

	await runScene( page, scenes[ 5 ], async () => {
		await page.goto( state.handoff.result.checkout_url, { waitUntil: "domcontentloaded" } );
		await page.locator( "form.checkout" ).waitFor( { state: "visible", timeout: 30_000 } );
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
	} );

	await runScene( page, scenes[ 6 ], async () => {
		await page.goto( "/agentsnr-demo/", { waitUntil: "domcontentloaded" } );
		await waitForTools( page, AGENT_SNR_TOOL_COUNT );
		await callTool( page, "get_agent_analytics_overview" );
		await callTool( page, "get_agent_conversion_funnel" );
		await callTool( page, "get_tool_health" );
		state.workflows = await callTool( page, "query_agent_workflows", { limit: 20 } );
		await callTool( page, "get_opportunity_signals", { limit: 8 } );
		await page.locator( "[data-wmcp-load-dashboard]" ).click();
		await scrollTo( page, "#wmcp-overview", -10 );
		await showOverlay( page, scenes[ 6 ].title, scenes[ 6 ].subtitle );
	} );

	await runScene( page, scenes[ 7 ], async () => {
		await scrollTo( page, "#wmcp-gaps", -80 );
		await showOverlay( page, scenes[ 7 ].title, scenes[ 7 ].subtitle );
		await page.waitForTimeout( 5_000 );
		await callTool( page, "explain_agent_workflow", {
			workflow_id: state.handoff.workflow_id,
		} );
		await scrollTo( page, "#wmcp-workflows", -70 );
		await page.waitForTimeout( 5_000 );
	} );

	await runScene( page, scenes[ 8 ], async () => {
		await callTool( page, "set_tool_enabled", {
			enabled: false,
			reason: "Devpost demo session governance",
			scope: "demo_session",
			tool_name: "compare_products",
		} );
		await scrollTo( page, "#wmcp-governance", -40 );
		await showOverlay( page, scenes[ 8 ].title, scenes[ 8 ].subtitle );
	} );

	if ( consoleErrors.length ) {
		throw new Error( `Unexpected browser console errors: ${ consoleErrors.join( " | " ) }` );
	}

	await page.close();
	await context.close();
	const rawVideoPath = await video.path();
	await browser.close();
	return {
		rawVideoPath,
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
	try {
		await fs.access( OUTPUT_PATH );
		throw new Error( `Refusing to overwrite existing output: ${ OUTPUT_PATH }` );
	} catch ( error ) {
		if ( error.code !== "ENOENT" ) {
			throw error;
		}
	}
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
