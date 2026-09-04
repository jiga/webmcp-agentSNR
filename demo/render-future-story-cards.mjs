import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";
import { chromium } from "playwright";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.resolve( process.env.AGENT_SNR_STORY_SOURCE || path.join( root, "dist/agent-snr-devpost-demo-v4-system.mp4" ) );
const timelinePath = path.resolve( process.env.AGENT_SNR_STORY_TIMELINE || path.join( root, "dist/agent-snr-devpost-timeline-v4.json" ) );
const outputDirectory = path.resolve( process.env.AGENT_SNR_STORY_CARDS_DIR || path.join( root, ".release-test/future-story-cards" ) );

function cardMarkup( number, liveFrameUrl ) {
	const cards = {
		1: `
			<section class="copy title-card">
				<p class="eyebrow">AGENT OUTCOME MONITORING FOR WORDPRESS</p>
				<h1>Agent<br>SNR</h1>
				<p class="title-promise">See what agents did.<br>Hear what they experienced.<br>Discover what your site is missing.</p>
			</section>`,
		2: `
			<section class="copy future">
				<p class="eyebrow">THE AGENTIC WEB</p>
				<h1>A web where every person has an agent</h1>
				<p class="support">People delegate the goal. Their agents browse and act.</p>
			</section>
			<div class="ghost">AGENT</div>`,
		3: `
			<section class="copy problem">
				<p class="eyebrow">THE OWNER BLIND SPOT</p>
				<h1>The store sees the call.<br>It misses the intent.</h1>
				<div class="questions">
					<span>What did the shopper need?</span>
					<span>Why did the agent adapt?</span>
					<span>Did the journey convert?</span>
				</div>
			</section>`,
		4: `
			<img class="live-frame" src="${ liveFrameUrl }" alt="">
			<div class="veil"></div>
			<section class="copy solution">
				<p class="eyebrow">AGENT SNR</p>
				<h1>The WordPress operations layer for website agents</h1>
				<p class="support">WebMCP journeys become redacted evidence, verified outcomes, and owner action.</p>
			</section>`,
	};
	return `<!doctype html>
	<html><head><meta charset="utf-8"><style>
		* { box-sizing: border-box; }
		html, body { margin: 0; width: 1920px; height: 1080px; overflow: hidden; }
		body { background: #f4f1e8; color: #111; font-family: Arial, Helvetica, sans-serif; position: relative; }
		body::before { background: #2167f3; content: ""; height: 100%; left: 0; position: absolute; top: 0; width: 24px; }
		.copy { left: 92px; max-width: 1420px; position: absolute; top: 78px; z-index: 3; }
		.eyebrow { color: #2167f3; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 24px; font-weight: 800; letter-spacing: .08em; margin: 0 0 54px; }
		h1 { font-size: 112px; letter-spacing: -.045em; line-height: .98; margin: 0; max-width: 1450px; }
		.support { color: #566069; font-size: 38px; font-weight: 600; line-height: 1.28; margin: 58px 0 0; max-width: 1050px; }
		.title-card h1 { font-size: 170px; line-height: .88; }
		.title-promise { font-size: 52px; font-weight: 750; line-height: 1.18; margin: 0; position: absolute; left: 850px; top: 250px; width: 850px; }
		.ghost { bottom: -90px; color: rgba(33,103,243,.09); font-size: 430px; font-weight: 900; letter-spacing: -.08em; position: absolute; right: 60px; }
		.problem h1 { font-size: 104px; }
		.questions { border-top: 2px solid #aeb6bd; display: grid; font-size: 31px; font-weight: 700; gap: 23px; margin-top: 72px; padding-top: 34px; width: 920px; }
		.live-frame { height: 1080px; object-fit: cover; position: absolute; right: 0; top: 0; width: 1920px; }
		.veil { background: linear-gradient(90deg, rgba(244,241,232,.99) 0%, rgba(244,241,232,.97) 38%, rgba(244,241,232,.35) 67%, rgba(244,241,232,0) 100%); inset: 0; position: absolute; }
		.solution { max-width: 970px; top: 82px; }
		.solution h1 { font-size: 90px; max-width: 920px; }
		.solution .support { color: #30383e; font-size: 33px; max-width: 790px; }
		.footer { bottom: 38px; color: #59636b; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 20px; font-weight: 700; left: 92px; letter-spacing: .05em; position: absolute; z-index: 4; }
	</style></head><body>${ cards[ number ] }<div class="footer">AGENT SNR · HACKATHON DEMO</div></body></html>`;
}

await Promise.all( [ source, timelinePath ].map( ( file ) => fs.access( file ) ) );
await fs.mkdir( outputDirectory, { recursive: true } );
const timeline = JSON.parse( await fs.readFile( timelinePath, "utf8" ) );
const liveStart = timeline.liveDemoStartSeconds;
if ( ! Number.isFinite( liveStart ) || liveStart < 0 ) {
	throw new Error( "Timeline manifest does not contain a valid live-demo boundary." );
}
const liveFrame = path.join( outputDirectory, "live-frame.png" );
const cardPaths = [ 1, 2, 3, 4 ].map( ( number ) => path.join( outputDirectory, `card-${ number }.png` ) );
await Promise.all( [ liveFrame, ...cardPaths ].map( async ( file ) => {
	await fs.access( file ).then(
		() => {
			throw new Error( `Refusing to overwrite existing story-card output: ${ file }` );
		},
		( error ) => {
			if ( error.code !== "ENOENT" ) {
				throw error;
			}
		}
	);
} ) );
await execFileAsync( "ffmpeg", [
	"-hide_banner", "-loglevel", "error", "-ss", liveStart.toFixed( 3 ), "-i", source,
	"-frames:v", "1", liveFrame,
] );

const browser = await chromium.launch( { headless: true } );
const page = await browser.newPage( { viewport: { height: 1080, width: 1920 } } );
const liveFrameUrl = `data:image/png;base64,${ ( await fs.readFile( liveFrame ) ).toString( "base64" ) }`;
for ( const number of [ 1, 2, 3, 4 ] ) {
	await page.setContent( cardMarkup( number, liveFrameUrl ), { waitUntil: "load" } );
	await page.screenshot( { path: cardPaths[ number - 1 ] } );
}
await browser.close();

console.log( JSON.stringify( { liveStart, outputDirectory }, null, 2 ) );
