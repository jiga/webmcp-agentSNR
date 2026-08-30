import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";

const LOCAL_HOSTS = new Set( [ "localhost", "127.0.0.1", "::1", "[::1]" ] );
export const CAPTURE_OUTPUT_MARKER = ".agent-snr-capture-output";
export const CAPTURE_OUTPUT_MARKER_CONTENT = "agent-snr-capture-output-v1\n";

export function resolveShowcaseBaseUrl( environment = process.env ) {
	const rawBaseUrl = environment.WMCP_BASE_URL || "http://localhost:18084";
	let parsed;

	try {
		parsed = new URL( rawBaseUrl );
	} catch {
		throw new Error( "WMCP_BASE_URL must be an absolute HTTP or HTTPS URL." );
	}

	if ( ! [ "http:", "https:" ].includes( parsed.protocol ) ) {
		throw new Error( "WMCP_BASE_URL must use HTTP or HTTPS." );
	}
	if ( parsed.username || parsed.password ) {
		throw new Error( "WMCP_BASE_URL must not contain credentials." );
	}
	if ( parsed.pathname !== "/" || parsed.search || parsed.hash ) {
		throw new Error( "WMCP_BASE_URL must be an origin without a path, query, or fragment." );
	}

	const isLocal = LOCAL_HOSTS.has( parsed.hostname );
	if ( ! isLocal && parsed.protocol !== "https:" ) {
		throw new Error( "Remote showcase capture requires HTTPS." );
	}
	if ( ! isLocal && environment.WMCP_ALLOW_REMOTE_SHOWCASE !== "1" ) {
		throw new Error(
			"Remote showcase capture is disabled. Use localhost or set WMCP_ALLOW_REMOTE_SHOWCASE=1 explicitly."
		);
	}
	if (
		! isLocal &&
		( ! environment.WMCP_ADMIN_USER || ! environment.WMCP_ADMIN_PASSWORD )
	) {
		throw new Error(
			"Remote showcase capture requires explicit WMCP_ADMIN_USER and WMCP_ADMIN_PASSWORD values."
		);
	}

	return { baseUrl: parsed.origin, isLocal };
}

export function assertShowcaseOrigin( candidateUrl, baseUrl, label = "Showcase page" ) {
	const candidate = new URL( candidateUrl );
	const expected = new URL( baseUrl );
	if ( candidate.origin !== expected.origin ) {
		throw new Error( `${ label } left the configured showcase origin.` );
	}
	return candidate;
}

export function sanitizeShowcaseConsoleLocation( locationUrl, baseUrl ) {
	if ( ! locationUrl ) {
		return "unknown";
	}
	try {
		const location = new URL( locationUrl );
		const expected = new URL( baseUrl );
		return location.origin === expected.origin ? location.pathname : "external-origin";
	} catch {
		return "unknown";
	}
}

export async function validateCaptureOutputDirectory( outputDirectory, {
	homeDirectory = os.homedir(),
	lstat = fs.lstat,
	readFile = fs.readFile,
	workspaceDirectory = process.cwd(),
} = {} ) {
	const target = path.resolve( outputDirectory );
	const workspace = path.resolve( workspaceDirectory );
	const broadTargets = new Set( [
		path.parse( target ).root,
		path.resolve( homeDirectory ),
		workspace,
	] );
	const workspaceFromTarget = path.relative( target, workspace );
	const targetContainsWorkspace =
		workspaceFromTarget === "" ||
		( ! workspaceFromTarget.startsWith( ".." ) && ! path.isAbsolute( workspaceFromTarget ) );
	if ( broadTargets.has( target ) || targetContainsWorkspace ) {
		throw new Error( "WMCP_SHOWCASE_OUTPUT must be a dedicated generated subdirectory." );
	}

	let stats;
	try {
		stats = await lstat( target );
	} catch ( error ) {
		if ( error.code === "ENOENT" ) {
			return target;
		}
		throw error;
	}
	if ( stats.isSymbolicLink() || ! stats.isDirectory() ) {
		throw new Error( "WMCP_SHOWCASE_OUTPUT must be a real directory, not a file or symlink." );
	}

	let marker;
	try {
		marker = await readFile( path.join( target, CAPTURE_OUTPUT_MARKER ), "utf8" );
	} catch ( error ) {
		if ( error.code === "ENOENT" ) {
			throw new Error(
				"An existing WMCP_SHOWCASE_OUTPUT directory must contain the Agent SNR capture marker.",
				{ cause: error }
			);
		}
		throw error;
	}
	if ( marker !== CAPTURE_OUTPUT_MARKER_CONTENT ) {
		throw new Error( "The WMCP_SHOWCASE_OUTPUT marker is invalid." );
	}

	return target;
}

export async function loadShowcaseAdminCredentials( {
	credentialsFile = path.resolve( ".release-test/showcase-runtime/operator-credentials" ),
	environment = process.env,
	readFile = fs.readFile,
} = {} ) {
	const environmentUser = environment.WMCP_ADMIN_USER;
	const environmentPassword = environment.WMCP_ADMIN_PASSWORD;
	if ( environmentUser || environmentPassword ) {
		if ( ! environmentUser || ! environmentPassword ) {
			throw new Error( "Set both WMCP_ADMIN_USER and WMCP_ADMIN_PASSWORD." );
		}
		return { password: environmentPassword, user: environmentUser };
	}

	let credentials;
	try {
		credentials = await readFile( credentialsFile, "utf8" );
	} catch ( error ) {
		if ( error.code !== "ENOENT" ) {
			throw error;
		}
		throw new Error(
			"Showcase operator credentials were not found. Run ./bin/start-showcase.sh start or set both WMCP_ADMIN_USER and WMCP_ADMIN_PASSWORD.",
			{ cause: error }
		);
	}

	const user = credentials.match( /^user=(.+)$/m )?.[ 1 ];
	const password = credentials.match( /^password=(.+)$/m )?.[ 1 ];
	if ( ! user || ! password ) {
		throw new Error( "The showcase operator credentials file is incomplete." );
	}

	return { password, user };
}
