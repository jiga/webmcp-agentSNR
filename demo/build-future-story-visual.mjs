import { execFile } from "node:child_process";
import { createHash } from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.resolve( process.env.AGENT_SNR_STORY_SOURCE || path.join( root, "dist/agent-snr-devpost-demo-v4-system.mp4" ) );
const timelinePath = path.resolve( process.env.AGENT_SNR_STORY_TIMELINE || path.join( root, "dist/agent-snr-devpost-timeline-v4.json" ) );
const cards = path.resolve( process.env.AGENT_SNR_STORY_CARDS_DIR || path.join( root, ".release-test/future-story-cards" ) );
const output = path.resolve( process.env.AGENT_SNR_STORY_VISUAL_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-v4-story-visual.mp4" ) );

async function duration( file ) {
	const result = await execFileAsync( "ffprobe", [
		"-v", "error", "-show_entries", "format=duration", "-of", "default=nw=1:nk=1", file,
	] );
	return Number.parseFloat( result.stdout.trim() );
}

async function sha256( file ) {
	return createHash( "sha256" ).update( await fs.readFile( file ) ).digest( "hex" );
}

const sourceDuration = await duration( source );
const timeline = JSON.parse( await fs.readFile( timelinePath, "utf8" ) );
const liveStart = timeline.liveDemoStartSeconds;
const liveScenes = timeline.scenes?.filter( ( scene ) => scene.id !== "intro" ) || [];
const liveEnd = timeline.liveDemoEndSeconds || liveScenes.at( -1 )?.endSeconds;
if ( ! Number.isFinite( liveStart ) || ! Number.isFinite( liveEnd ) || liveScenes.length !== 8 ) {
	throw new Error( "Timeline manifest is missing the expected live-demo boundaries." );
}
if ( liveEnd > sourceDuration + 0.1 ) {
	throw new Error( "Timeline manifest extends beyond the recorded source video." );
}
if ( timeline.renderedSha256 && timeline.renderedSha256 !== await sha256( source ) ) {
	throw new Error( "Timeline manifest does not match the recorded source video." );
}
const excessiveDrift = liveScenes.find(
	( scene ) => Math.abs( scene.actualDurationSeconds - scene.plannedDurationSeconds ) > 0.25
);
if ( excessiveDrift ) {
	throw new Error( `Scene ${ excessiveDrift.id } drifted beyond its planned recording budget.` );
}
const plannedLiveDuration = liveScenes.reduce(
	( total, scene ) => total + scene.plannedDurationSeconds,
	0
);
const renderedLiveDuration = liveEnd - liveStart;
if ( Math.abs( renderedLiveDuration - plannedLiveDuration ) > 0.25 ) {
	throw new Error( `Rendered live section drifted by ${ ( renderedLiveDuration - plannedLiveDuration ).toFixed( 3 ) } seconds.` );
}
const cardPaths = [ 1, 2, 3, 4 ].map( ( number ) => path.join( cards, `card-${ number }.png` ) );
await Promise.all( [ source, timelinePath, ...cardPaths ].map( ( file ) => fs.access( file ) ) );
await fs.access( output ).then(
	() => {
		throw new Error( `Refusing to overwrite existing output: ${ output }` );
	},
	( error ) => {
		if ( error.code !== "ENOENT" ) {
			throw error;
		}
	}
);

const filter = [
	"[1:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=4.5,setpts=PTS-STARTPTS[title]",
	"[2:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=6,setpts=PTS-STARTPTS[future]",
	"[3:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=7,setpts=PTS-STARTPTS[problem]",
	"[4:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=8,setpts=PTS-STARTPTS[solution]",
	`[0:v]trim=start=${ liveStart.toFixed( 3 ) }:end=${ liveEnd.toFixed( 3 ) },setpts=PTS-STARTPTS,scale=1920:1080:flags=lanczos,fps=25[live]`,
	"[title][future]xfade=transition=fade:duration=0.5:offset=4[x1]",
	"[x1][problem]xfade=transition=fade:duration=0.5:offset=9.5[x2]",
	"[x2][solution]xfade=transition=fade:duration=0.5:offset=16[x3]",
	"[x3][live]xfade=transition=fade:duration=0.5:offset=23.5,format=yuv420p[v]",
].join( ";" );

await fs.mkdir( path.dirname( output ), { recursive: true } );
await execFileAsync( "ffmpeg", [
	"-hide_banner", "-loglevel", "error", "-i", source,
	"-loop", "1", "-t", "4.5", "-i", cardPaths[ 0 ],
	"-loop", "1", "-t", "6", "-i", cardPaths[ 1 ],
	"-loop", "1", "-t", "7", "-i", cardPaths[ 2 ],
	"-loop", "1", "-t", "8", "-i", cardPaths[ 3 ],
	"-filter_complex", filter,
	"-map", "[v]", "-an", "-c:v", "libx264", "-preset", "medium", "-crf", "20",
	"-movflags", "+faststart", output,
] );

console.log( JSON.stringify( { liveEnd, liveStart, output }, null, 2 ) );
