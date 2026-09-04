import { execFile } from "node:child_process";
import { createHash } from "node:crypto";
import fs from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.resolve( process.env.AGENT_SNR_NARRATION_SOURCE || path.join( root, "dist/agent-snr-devpost-demo-v4-story-visual.mp4" ) );
const output = path.resolve( process.env.AGENT_SNR_NARRATION_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-final.mp4" ) );
const work = path.join( root, ".release-test/natural-narration-v4" );
const model = "gpt-4o-mini-tts";
const voice = process.env.AGENT_SNR_TTS_VOICE || "marin";
const reuseGeneratedSpeech = process.env.AGENT_SNR_REUSE_TTS === "1";
const regeneratedScenes = new Set(
	( process.env.AGENT_SNR_REGENERATE_SCENES || "" )
		.split( "," )
		.map( ( value ) => Number.parseInt( value, 10 ) )
		.filter( Number.isInteger )
);

const scenes = [
	{
		duration: 4.5,
		text: "Agent SNR is outcome monitoring for the agentic web.",
	},
	{
		duration: 5.5,
		text: "Everyone will have an agent to browse and shop for them.",
	},
	{
		duration: 6.5,
		text: "Successful calls can hide shopper intent, recovery, and unmet demand.",
	},
	{
		duration: 7,
		text: "Agent SNR connects WebMCP journeys to verified outcomes.",
	},
	{
		duration: 16,
		text: "A shopper asks for a compact IPX5 backpack under one hundred dollars. Their agent discovers twelve tools and reads the guide first. Search and cart stay reversible, while checkout remains human-reviewed. With those boundaries clear, the agent begins searching.",
	},
	{
		duration: 16,
		text: "The request uses the shopper's exact constraints and succeeds, but returns zero matches. Agent SNR records the missed IPX5 demand without storing the raw prompt. A normal success log would miss it.",
	},
	{
		duration: 20,
		text: "The agent explains the constraint and relaxes only the water rating. A second search finds two compact IPX4 options. It compares product facts and checks returns before recommending HarborLite. Missing facts stay missing, and the unmet IPX5 requirement remains visible.",
	},
	{
		duration: 20,
		text: "With the shopper's choice, the agent adds HarborLite to a reversible cart. It prepares checkout and reports the missing IPX5 requirement with linked evidence. The handoff remains linked to the workflow. Checkout sits outside the WebMCP toolset, so the shopper reviews and confirms the order.",
	},
	{
		duration: 16,
		text: "The shopper reviews checkout and places this no-charge demo order. That click verifies the outcome for the same journey. The order verifies the outcome for this workflow. Agent SNR stores neither address nor payment details.",
	},
	{
		duration: 18,
		text: "The shopper is done. Now the owner's agent asks what the site learned. It discovers eight Agent SNR tools. Analytics show the attributed order and the missed IPX5 opportunity. The owner sees both the conversion and the unmet demand behind it.",
	},
	{
		duration: 20,
		text: "Replay connects the zero result, recovery, feedback, human checkpoint, and order. Each claim keeps its source: site observed, agent reported, or site verified. The owner sees this without raw customer data. Agent opinion never becomes business fact. Lost revenue is never invented.",
	},
	{
		duration: 16,
		text: "Now the owner has a decision: add IPX5 inventory and keep the proven IPX4 recovery. Agent SNR turns silent journeys into evidence the store can trust and improve.",
	},
];

const direction = [
	"Open like a thoughtful hackathon founder introducing the project. Warm, clear, and confident, never like an announcer. Pronounce S N R as individual letters.",
	"Describe the future as believable and near. Keep the sentence simple and unhurried.",
	"Turn slightly more serious. Emphasize shopper intent and unmet demand. Finish cleanly before the next slide.",
	"Explain the solution with quiet confidence. Pronounce S N R as individual letters and WebMCP as Web M C P.",
	"Begin the live demonstration conversationally. Pronounce I P X five as letters followed by five. Make the human boundary clear without sounding legalistic.",
	"Sound matter-of-fact and let zero matches land as useful evidence. Pronounce I P X five as letters followed by five.",
	"Sound optimistic as the agent recovers. Pronounce I P X four as letters followed by four and HarborLite naturally.",
	"Sound reassuring. Give a brief pause before explaining that the commitment stays human. Pronounce WebMCP as Web M C P.",
	"Sound grounded and human. Slightly emphasize that the shopper explicitly places the order.",
	"Use an audio handoff into the owner perspective. Sound curious on the question about what the site learned. Pronounce S N R as individual letters.",
	"Speak with analytical clarity. Slightly emphasize the three evidence sources and finish the lost-revenue sentence firmly.",
	"Close with measured confidence. Make the inventory decision concrete, then land the final sentence sincerely. Pronounce S N R as individual letters.",
];

async function readKey() {
	if ( process.env.OPENAI_API_KEY ) {
		return process.env.OPENAI_API_KEY;
	}
	const env = await fs.readFile( path.join( root, ".env" ), "utf8" );
	const line = env.split( /\r?\n/u ).find( ( item ) => item.startsWith( "OPENAI_API_KEY=" ) );
	if ( ! line ) {
		throw new Error( "OPENAI_API_KEY is unavailable." );
	}
	return line.slice( "OPENAI_API_KEY=".length ).trim().replace( /^(?:"(.*)"|'(.*)')$/u, "$1$2" );
}

async function duration( file ) {
	const result = await execFileAsync( "ffprobe", [
		"-v", "error", "-show_entries", "format=duration", "-of", "default=nw=1:nk=1", file,
	] );
	return Number.parseFloat( result.stdout.trim() );
}

async function sha256( file ) {
	return createHash( "sha256" ).update( await fs.readFile( file ) ).digest( "hex" );
}

export function sceneCacheKey( scene, index ) {
	return createHash( "sha256" )
		.update( JSON.stringify( {
			direction: direction[ index ],
			duration: scene.duration,
			model,
			text: scene.text,
			voice,
		} ) )
		.digest( "hex" );
}

async function generateSpeech( key, scene, index, file ) {
	const response = await fetch( "https://api.openai.com/v1/audio/speech", {
		method: "POST",
		headers: {
			Authorization: `Bearer ${ key }`,
			"Content-Type": "application/json",
		},
		body: JSON.stringify( {
			model,
			voice,
			input: scene.text,
			instructions: `${ direction[ index ] } Aim to finish comfortably within ${ scene.duration - 1 } seconds.`,
			response_format: "wav",
		} ),
	} );
	if ( ! response.ok ) {
		throw new Error( `Speech generation failed for scene ${ index + 1 }: ${ response.status } ${ await response.text() }` );
	}
	await fs.writeFile( file, Buffer.from( await response.arrayBuffer() ) );
	return file;
}

async function fitScene( raw, scene, index ) {
	const rawDuration = await duration( raw );
	const speakingBudget = scene.duration - 0.6;
	const naturalTempo = rawDuration / speakingBudget;
	const tempo = naturalTempo > 1 ? naturalTempo : 1;
	if ( tempo > 1.03 ) {
		throw new Error( `Scene ${ index + 1 } requires unnatural ${ tempo.toFixed( 3 ) }x acceleration.` );
	}
	const fitted = path.join( work, `scene-${ String( index + 1 ).padStart( 2, "0" ) }.wav` );
	const filter = `${ Math.abs( tempo - 1 ) > 0.001 ? `atempo=${ tempo.toFixed( 6 ) },` : "" }adelay=300|300,apad,atrim=0:${ scene.duration }`;
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-i", raw,
		"-af", filter, "-ar", "48000", "-ac", "2", fitted,
	] );
	return { fitted, rawDuration, tempo };
}

async function main() {
	await fs.access( source );
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
	const sourceDuration = await duration( source );
	if ( ! reuseGeneratedSpeech ) {
		await fs.rm( work, { force: true, recursive: true } );
	}
	await fs.mkdir( work, { recursive: true } );
	let key;
	const fitted = [];
	const report = [];
	for ( const [ index, scene ] of scenes.entries() ) {
		const cacheKey = sceneCacheKey( scene, index );
		const cached = path.join(
			work,
			`scene-${ String( index + 1 ).padStart( 2, "0" ) }-${ cacheKey.slice( 0, 16 ) }-raw.wav`
		);
		const raw = reuseGeneratedSpeech && ! regeneratedScenes.has( index + 1 ) && await fs.access( cached ).then( () => true, () => false )
			? cached
			: await generateSpeech( key ||= await readKey(), scene, index, cached );
		const result = await fitScene( raw, scene, index );
		fitted.push( result.fitted );
		report.push( {
			scene: index + 1,
			budgetSeconds: scene.duration,
			cacheKey,
			rawSeconds: Number( result.rawDuration.toFixed( 3 ) ),
			tempo: Number( result.tempo.toFixed( 3 ) ),
		} );
	}
	const concat = path.join( work, "concat.txt" );
	await fs.writeFile( concat, fitted.map( ( file ) => `file '${ file.replaceAll( "'", "'\\''" ) }'` ).join( "\n" ) );
	const narration = path.join( work, "narration.wav" );
	const totalDuration = scenes.reduce( ( total, scene ) => total + scene.duration, 0 );
	const sourceDrift = sourceDuration - totalDuration;
	if ( Math.abs( sourceDrift ) > 0.1 ) {
		throw new Error( `Source video differs from the narration timeline by ${ sourceDrift.toFixed( 3 ) } seconds.` );
	}
	const startupDelay = Math.max( 0, sourceDrift );
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-f", "concat", "-safe", "0", "-i", concat,
		"-af", `loudnorm=I=-16:TP=-1.5:LRA=11,adelay=${ Math.round( startupDelay * 1000 ) }|${ Math.round( startupDelay * 1000 ) },apad,atrim=0:${ sourceDuration }`, "-ar", "48000", "-ac", "2", narration,
	] );
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-i", source, "-i", narration,
		"-map", "0:v:0", "-map", "1:a:0", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k",
		"-t", String( sourceDuration ), "-movflags", "+faststart", output,
	] );
	const provenance = {
		model,
		outputSha256: await sha256( output ),
		scenes: report,
		sourceSha256: await sha256( source ),
		voice,
	};
	await fs.writeFile( path.join( work, "timing-report.json" ), `${ JSON.stringify( provenance, null, 2 ) }\n` );
	console.log( JSON.stringify( { output, ...provenance }, null, 2 ) );
}

if ( process.argv[ 1 ] && import.meta.url === pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href ) {
	await main();
}
