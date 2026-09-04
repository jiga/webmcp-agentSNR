import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );

const targets = {
	cards: path.resolve( process.env.AGENT_SNR_STORY_CARDS_DIR || path.join( root, ".release-test/future-story-cards" ) ),
	final: path.resolve( process.env.AGENT_SNR_NARRATION_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-final.mp4" ) ),
	recording: path.resolve( process.env.AGENT_SNR_VIDEO_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-v4-system.mp4" ) ),
	timeline: path.resolve( process.env.AGENT_SNR_TIMELINE_OUTPUT || path.join( root, "dist/agent-snr-devpost-timeline-v4.json" ) ),
	transcript: path.resolve( process.env.AGENT_SNR_TRANSCRIPT_OUTPUT || path.join( root, "dist/agent-snr-devpost-narration-v4.txt" ) ),
	visual: path.resolve( process.env.AGENT_SNR_STORY_VISUAL_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-v4-story-visual.mp4" ) ),
};

async function assertAbsent( file ) {
	await fs.access( file ).then(
		() => {
			throw new Error( `Refusing to start because an output already exists: ${ file }` );
		},
		( error ) => {
			if ( error.code !== "ENOENT" ) {
				throw error;
			}
		}
	);
}

await Promise.all( [
	targets.recording,
	targets.timeline,
	targets.transcript,
	targets.visual,
	targets.final,
	path.join( targets.cards, "live-frame.png" ),
	path.join( targets.cards, "card-1.png" ),
	path.join( targets.cards, "card-2.png" ),
	path.join( targets.cards, "card-3.png" ),
	path.join( targets.cards, "card-4.png" ),
].map( assertAbsent ) );

async function run( script, env = {} ) {
	const result = await execFileAsync( process.execPath, [ path.join( root, "demo", script ) ], {
		cwd: root,
		env: { ...process.env, ...env },
		maxBuffer: 16 * 1024 * 1024,
	} );
	return result.stdout.trim();
}

const recording = await run( "record-hosted-demo.mjs", {
	AGENT_SNR_NARRATION_RATE: process.env.AGENT_SNR_NARRATION_RATE || "225",
	AGENT_SNR_TIMELINE_OUTPUT: targets.timeline,
	AGENT_SNR_TRANSCRIPT_OUTPUT: targets.transcript,
	AGENT_SNR_VIDEO_OUTPUT: targets.recording,
} );
const storyEnvironment = {
	AGENT_SNR_STORY_CARDS_DIR: targets.cards,
	AGENT_SNR_STORY_SOURCE: targets.recording,
	AGENT_SNR_STORY_TIMELINE: targets.timeline,
};
const cards = await run( "render-future-story-cards.mjs", storyEnvironment );
const visual = await run( "build-future-story-visual.mjs", {
	...storyEnvironment,
	AGENT_SNR_STORY_VISUAL_OUTPUT: targets.visual,
} );
const final = await run( "remaster-natural-narration.mjs", {
	AGENT_SNR_NARRATION_OUTPUT: targets.final,
	AGENT_SNR_NARRATION_SOURCE: targets.visual,
} );

console.log( JSON.stringify( {
	cards: JSON.parse( cards ),
	final: JSON.parse( final ),
	recording: JSON.parse( recording ),
	visual: JSON.parse( visual ),
}, null, 2 ) );
