import assert from "node:assert/strict";
import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { promisify } from "node:util";
import test from "node:test";
import { sceneCacheKey } from "../../demo/remaster-natural-narration.mjs";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, "../.." );

async function temporaryDirectory() {
	return fs.mkdtemp( path.join( os.tmpdir(), "agent-snr-video-test-" ) );
}

async function expectFailure( script, env ) {
	try {
		await execFileAsync( process.execPath, [ path.join( root, "demo", script ) ], {
			cwd: root,
			env: { ...process.env, ...env },
		} );
		assert.fail( `${ script } unexpectedly succeeded.` );
	} catch ( error ) {
		return `${ error.stderr || "" }${ error.stdout || "" }`;
	}
}

test( "final-video preflight rejects downstream output before hosted capture starts", async () => {
	const directory = await temporaryDirectory();
	const recording = path.join( directory, "recording.mp4" );
	const final = path.join( directory, "already-exists.mp4" );
	await fs.writeFile( final, "occupied" );

	const output = await expectFailure( "build-final-video.mjs", {
		AGENT_SNR_NARRATION_OUTPUT: final,
		AGENT_SNR_STORY_CARDS_DIR: path.join( directory, "cards" ),
		AGENT_SNR_STORY_VISUAL_OUTPUT: path.join( directory, "visual.mp4" ),
		AGENT_SNR_TIMELINE_OUTPUT: path.join( directory, "timeline.json" ),
		AGENT_SNR_TRANSCRIPT_OUTPUT: path.join( directory, "transcript.txt" ),
		AGENT_SNR_VIDEO_OUTPUT: recording,
	} );

	assert.match( output, /output already exists/u );
	await assert.rejects( fs.access( recording ) );
} );

test( "story compositor rejects an over-budget measured scene before rendering", async () => {
	const directory = await temporaryDirectory();
	const source = path.join( directory, "source.mp4" );
	const timeline = path.join( directory, "timeline.json" );
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-f", "lavfi", "-i", "color=c=black:s=16x16:r=25",
		"-t", "10", "-pix_fmt", "yuv420p", source,
	] );
	const ids = [ "guide", "zero-result", "recovery", "handoff", "checkout", "monitor", "replay", "decision" ];
	await fs.writeFile( timeline, `${ JSON.stringify( {
		coldOpenStartSeconds: 1,
		liveDemoEndSeconds: 8,
		liveDemoStartSeconds: 0,
		scenes: ids.map( ( id, index ) => ( {
			actualDurationSeconds: index === 3 ? 2 : 1,
			endSeconds: index + 1,
			id,
			plannedDurationSeconds: 1,
			startSeconds: index,
		} ) ),
	} ) }\n` );

	const output = await expectFailure( "build-future-story-visual.mjs", {
		AGENT_SNR_STORY_CARDS_DIR: path.join( directory, "cards" ),
		AGENT_SNR_STORY_SOURCE: source,
		AGENT_SNR_STORY_TIMELINE: timeline,
		AGENT_SNR_STORY_VISUAL_OUTPUT: path.join( directory, "visual.mp4" ),
	} );

	assert.match( output, /handoff drifted beyond its planned recording budget/u );
} );

test( "TTS cache identity changes when the scene duration changes", () => {
	const original = sceneCacheKey( { duration: 5.5, text: "The search worked." }, 0 );
	const extended = sceneCacheKey( { duration: 6.5, text: "The search worked." }, 0 );
	assert.notEqual( original, extended );
} );
