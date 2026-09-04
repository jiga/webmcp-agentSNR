import { execFile } from "node:child_process";
import fs from "node:fs/promises";
import path from "node:path";
import { promisify } from "node:util";

const execFileAsync = promisify( execFile );
const root = path.resolve( import.meta.dirname, ".." );
const source = path.resolve( process.env.AGENT_SNR_PITCH_SOURCE || path.join( root, "dist/agent-snr-devpost-demo-v3-base.mp4" ) );
const output = path.resolve( process.env.AGENT_SNR_PITCH_OUTPUT || path.join( root, "dist/agent-snr-devpost-demo-v3.mp4" ) );
const slideDirectory = process.env.AGENT_SNR_PITCH_SLIDES_DIR;

if ( ! slideDirectory ) {
	throw new Error( "AGENT_SNR_PITCH_SLIDES_DIR must point to a rendered copy of the hackathon deck." );
}

const slides = [ 1, 2, 4 ].map( ( number ) => path.resolve( slideDirectory, `slide-${ number }.png` ) );
await Promise.all( [ source, ...slides ].map( ( file ) => fs.access( file ) ) );
await fs.access( output ).then(
	() => {
		throw new Error( `Refusing to overwrite existing output: ${ output }` );
	},
	() => {}
);

const filter = [
	"[1:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=3.5,setpts=PTS-STARTPTS[s1]",
	"[2:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=3.5,setpts=PTS-STARTPTS[s2]",
	"[3:v]scale=1920:1080:flags=lanczos,setsar=1,fps=25,trim=duration=3.5,setpts=PTS-STARTPTS[s3]",
	"[s1][s2][s3]concat=n=3:v=1:a=0[intro]",
	"[0:v]trim=start=10.5,setpts=PTS-STARTPTS[demo]",
	"[intro][demo]concat=n=2:v=1:a=0,format=yuv420p[v]",
].join( ";" );

await fs.mkdir( path.dirname( output ), { recursive: true } );
await execFileAsync( "ffmpeg", [
	"-hide_banner", "-loglevel", "error", "-i", source,
	"-loop", "1", "-t", "3.5", "-i", slides[ 0 ],
	"-loop", "1", "-t", "3.5", "-i", slides[ 1 ],
	"-loop", "1", "-t", "3.5", "-i", slides[ 2 ],
	"-filter_complex", filter,
	"-map", "[v]", "-map", "0:a:0",
	"-c:v", "libx264", "-preset", "medium", "-crf", "20",
	"-c:a", "copy", "-movflags", "+faststart", output,
] );

console.log( JSON.stringify( { output, slides, source }, null, 2 ) );
