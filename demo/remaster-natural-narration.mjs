import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.join( root, "dist/agent-snr-devpost-demo.mp4" );
const output = path.join( root, "dist/agent-snr-devpost-demo-natural.mp4" );
const work = path.join( root, ".release-test/natural-narration" );
const model = "gpt-4o-mini-tts";
const voice = process.env.AGENT_SNR_TTS_VOICE || "marin";

const scenes = [
	{
		duration: 14,
		text: "Most websites can expose actions to agents. Agent SNR shows what happened after those actions: the evidence used, the human checkpoint, the business outcome, and what the site should improve next.",
	},
	{
		duration: 15,
		text: "The live storefront publishes twelve WebMCP tools and a plain-language Agent Guide. It tells the browser agent which journeys are supported, which actions are reversible, what data is excluded, and exactly where checkout returns to a person.",
	},
	{
		duration: 16,
		text: "The shopper wants an in-stock waterproof backpack under one hundred dollars with IPX5 protection. No product matches. Instead of hiding that failure, the site records a privacy-safe, site-observed opportunity without storing the shopper's raw prompt.",
	},
	{
		duration: 17,
		text: "The agent relaxes only the water rating and finds two compact IPX4 options. It compares stored product facts and checks the published returns policy. Missing information stays missing; the tool never invents a recommendation or a final total.",
	},
	{
		duration: 19,
		text: "HarborLite is added to the session cart. The agent reports the constraint using linked site evidence, while Agent SNR keeps testimony separate from verified measurements. Checkout preparation validates the cart and stops. No WebMCP tool can place an order or process payment.",
	},
	{
		duration: 15,
		text: "The person reviews the normal WooCommerce checkout, accepts the fictional demo details, and explicitly places the no-charge order. The human remains responsible for customer data, terms, and the final commitment.",
	},
	{
		duration: 17,
		text: "On the separate Agent SNR surface, eight operator tools read the same browser scope. The monitor connects the original tool journey to the paid WooCommerce order, while keeping raw prompts, addresses, payment details, cookies, and payloads out of the ledger.",
	},
	{
		duration: 20,
		text: "Workflow Replay shows terminal tool outcomes, latency, recovery, product evidence, feedback, and commerce attribution. Signals preserve three trust classes: what the site observed, what the agent reported, and what the catalog and WooCommerce verified. Lost revenue is never invented.",
	},
	{
		duration: 16,
		text: "Finally, the merchant disables comparison for only this demo session. The server enforces the restriction and refreshes the browser's tool catalog without affecting another judge. Agent SNR turns agent activity into evidence, decisions, and a safer next journey.",
	},
];

const direction = [
	"Speak like a thoughtful founder opening a live product demo. Natural, warm, confident, and conversational. Use varied intonation and brief meaningful pauses. Never sound like an announcer or read a list. Pronounce S N R as individual letters.",
	"Continue the same warm founder voice. Sound genuinely helpful and slightly curious, as if showing a colleague an elegant feature. Emphasize the human checkout boundary without becoming dramatic.",
	"Sound engaged and matter-of-fact. Let the zero result feel like an interesting discovery, not a failure. Pause naturally after 'No product matches.' Pronounce I P X five as letters followed by five.",
	"Sound optimistic and clear, as if walking through a smart recovery. Keep technical terms light. Pronounce I P X four as letters followed by four.",
	"Sound reassuring and trustworthy. Give a subtle pause before explaining that no tool can place an order. Pronounce Web M C P as 'Web M C P'.",
	"Sound human and grounded, with a calm emphasis on the person making the final decision. Avoid sales energy.",
	"Sound quietly impressed as the outcome becomes visible. Keep the privacy exclusions conversational rather than list-like. Pronounce S N R as individual letters.",
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
	const tempo = rawDuration > speakingBudget ? rawDuration / speakingBudget : 1;
	if ( tempo > 1.18 ) {
		throw new Error( `Scene ${ index + 1 } requires unnatural ${ tempo.toFixed( 3 ) }x acceleration.` );
	}
	const fitted = path.join( work, `scene-${ String( index + 1 ).padStart( 2, "0" ) }.wav` );
	const filter = `${ tempo > 1 ? `atempo=${ tempo.toFixed( 6 ) },` : "" }adelay=300|300,apad,atrim=0:${ scene.duration }`;
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-i", raw,
		"-af", filter, "-ar", "48000", "-ac", "2", fitted,
	] );
	return { fitted, rawDuration, tempo };
}

async function main() {
	await fs.access( source );
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
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-f", "concat", "-safe", "0", "-i", concat,
		"-af", "loudnorm=I=-16:TP=-1.5:LRA=11,apad,atrim=0:149.64", "-ar", "48000", "-ac", "2", narration,
	] );
	await fs.rm( output, { force: true } );
	await execFileAsync( "ffmpeg", [
		"-hide_banner", "-loglevel", "error", "-y", "-i", source, "-i", narration,
		"-map", "0:v:0", "-map", "1:a:0", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k",
		"-t", "149.64", "-movflags", "+faststart", output,
	] );
	await fs.writeFile( path.join( work, "timing-report.json" ), `${ JSON.stringify( { model, voice, scenes: report }, null, 2 ) }\n` );
	console.log( JSON.stringify( { output, model, voice, scenes: report }, null, 2 ) );
}

await main();
