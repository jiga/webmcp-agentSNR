import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.resolve( process.env.AGENT_SNR_NARRATION_SOURCE || path.join( root, "dist/agent-snr-devpost-demo.mp4" ) );
const output = path.resolve( process.env.AGENT_SNR_NARRATION_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-natural.mp4" ) );
const work = path.join( root, ".release-test/natural-narration" );
const model = "gpt-4o-mini-tts";
const voice = process.env.AGENT_SNR_TTS_VOICE || "marin";

const scenes = [
	{
		duration: 16,
		text: "Agent SNR is a WordPress plugin for agent outcome monitoring. It registers WebMCP tools on shopper and owner pages. Calls run through same-origin PHP, while a redacted ledger connects tool activity to verified WooCommerce outcomes.",
	},
	{
		duration: 16,
		text: "A shopper asks their browser agent for an IPX5 backpack under one hundred dollars. The live panel shows its decisions: discover twelve tools, read the Agent Guide, and preserve checkout for the person.",
	},
	{
		duration: 18,
		text: "The agent calls search products with the budget, stock requirement, and IPX5 rating. The structured result has zero matches. Agent SNR records the unmet constraint as a site-observed opportunity—without storing the raw prompt.",
	},
	{
		duration: 24,
		text: "The agent explains the constraint and relaxes only the water rating. A second call finds two compact IPX4 alternatives. It compares stored product facts and checks the published returns policy. The shopper sees the real evidence and the agent's next decision—without invented specifications.",
	},
	{
		duration: 22,
		text: "The agent adds HarborLite to the cart, reports the constraint with linked evidence, and prepares checkout. Feedback stays agent-reported; catalog counts stay site-verified. The handoff validates the cart and stops. No tool can place the order, accept terms, or process payment.",
	},
	{
		duration: 18,
		text: "Now the shopper takes over. They review the ordinary checkout and the fictional demo details. Only the person clicks Place order. That no-charge order becomes a verified outcome for the same agent journey.",
	},
	{
		duration: 18,
		text: "Next, the owner asks their agent what happened. It discovers eight Agent SNR tools and calls analytics, conversion, health, workflows, and opportunity signals. The paid order is attributed to the exact shopper workflow—without exposing addresses or payment data.",
	},
	{
		duration: 22,
		text: "The owner agent opens the missed IPX5 opportunity and explains the converted workflow. Replay shows tool outcomes, recovery, feedback, and attribution. Agent SNR separates site-observed demand, agent-reported experience, and site-verified catalog and order facts. It never invents lost revenue.",
	},
	{
		duration: 14,
		text: "Finally, the owner agent disables comparison for this session. The server enforces it and refreshes the tool catalog without affecting another judge. Agent SNR turns real agent journeys into trustworthy business action.",
	},
];

const direction = [
	"Speak like a thoughtful founder opening a live product demo. Natural, warm, confident, and conversational. Use varied intonation and brief meaningful pauses. Never sound like an announcer or read a list. Pronounce S N R as individual letters.",
	"Continue the same warm founder voice. Sound genuinely engaged as the shopper request appears and the agent begins acting. Emphasize that the visible panel contains real decisions.",
	"Sound engaged and matter-of-fact. Let the zero result feel like an interesting discovery, not a failure. Pause naturally after 'No product matches.' Pronounce I P X five as letters followed by five.",
	"Sound optimistic and clear, as if walking through a smart recovery. Keep technical terms light. Pronounce I P X four as letters followed by four.",
	"Sound reassuring and trustworthy. Give a subtle pause before explaining that no tool can place an order. Pronounce Web M C P as 'Web M C P'.",
	"Sound human and grounded, with a calm emphasis on the person making the final decision. Avoid sales energy.",
	"Shift clearly into the store-owner story. Sound quietly impressed as the agent discovers the operator tools and the attributed outcome becomes visible. Pronounce S N R as individual letters.",
	"Speak with analytical clarity and natural rhythm. Slightly emphasize the three trust classes. End the final sentence firmly, without melodrama.",
	"Close with warm confidence and a small lift of energy. Make the last sentence memorable but sincere, like the end of a strong live demo. Pronounce S N R as individual letters.",
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

async function generateSpeech( key, scene, index ) {
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
	const file = path.join( work, `scene-${ String( index + 1 ).padStart( 2, "0" ) }-raw.wav` );
	await fs.writeFile( file, Buffer.from( await response.arrayBuffer() ) );
	return file;
}

async function fitScene( raw, scene, index ) {
	const rawDuration = await duration( raw );
	const speakingBudget = scene.duration - 0.8;
	const naturalTempo = rawDuration / speakingBudget;
	const tempo = naturalTempo > 1 ? naturalTempo : Math.max( naturalTempo, 0.88 );
	if ( tempo > 1.18 ) {
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
	const sourceDuration = await duration( source );
	await fs.rm( work, { force: true, recursive: true } );
	await fs.mkdir( work, { recursive: true } );
	const key = await readKey();
	const fitted = [];
	const report = [];
	for ( const [ index, scene ] of scenes.entries() ) {
		const raw = await generateSpeech( key, scene, index );
		const result = await fitScene( raw, scene, index );
		fitted.push( result.fitted );
		report.push( {
			scene: index + 1,
			budgetSeconds: scene.duration,
			rawSeconds: Number( result.rawDuration.toFixed( 3 ) ),
			tempo: Number( result.tempo.toFixed( 3 ) ),
		} );
	}
	const concat = path.join( work, "concat.txt" );
	await fs.writeFile( concat, fitted.map( ( file ) => `file '${ file.replaceAll( "'", "'\\''" ) }'` ).join( "\n" ) );
	const narration = path.join( work, "narration.wav" );
	const totalDuration = scenes.reduce( ( total, scene ) => total + scene.duration, 0 );
	const startupDelay = Math.max( 0, sourceDuration - totalDuration );
	if ( startupDelay > 8 ) {
		throw new Error( `Unexpected ${ startupDelay.toFixed( 3 ) } second startup delay in source video.` );
	}
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-f", "concat", "-safe", "0", "-i", concat,
		"-af", `loudnorm=I=-16:TP=-1.5:LRA=11,adelay=${ Math.round( startupDelay * 1000 ) }|${ Math.round( startupDelay * 1000 ) },apad,atrim=0:${ sourceDuration }`, "-ar", "48000", "-ac", "2", narration,
	] );
	await fs.rm( output, { force: true } );
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-i", source, "-i", narration,
		"-map", "0:v:0", "-map", "1:a:0", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k",
		"-t", String( sourceDuration ), "-movflags", "+faststart", output,
	] );
	await fs.writeFile( path.join( work, "timing-report.json" ), `${ JSON.stringify( { model, voice, scenes: report }, null, 2 ) }\n` );
	console.log( JSON.stringify( { output, model, voice, scenes: report }, null, 2 ) );
}

await main();
