#!/usr/bin/env node

/* global process, URL */

import { readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

import { executeSmokeEvals } from "webmcp-evals/dist/evaluator/smokeEvaluator.js";

const REPOSITORY_ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), ".." );
const EVAL_ROOT = path.join( REPOSITORY_ROOT, "evals" );
const PROVIDER_ENVIRONMENT_KEYS = [
	"ANTHROPIC_API_KEY",
	"GEMINI_API_KEY",
	"GOOGLE_AI",
	"GOOGLE_GENERATIVE_AI_API_KEY",
	"OPENAI_API_KEY",
];

export function parseLocalWebmcpBaseUrl( value ) {
	let url;
	try {
		url = new URL( value );
	} catch {
		throw new Error( "WMCP_BASE_URL must be a valid localhost origin." );
	}

	const localHosts = new Set( [ "localhost", "127.0.0.1", "[::1]", "::1" ] );
	if ( ! [ "http:", "https:" ].includes( url.protocol ) || ! localHosts.has( url.hostname ) ) {
		throw new Error( "WebMCP smoke tests are restricted to localhost." );
	}
	if ( url.username || url.password ) {
		throw new Error( "WMCP_BASE_URL must not contain credentials." );
	}
	if ( url.pathname !== "/" || url.search || url.hash ) {
		throw new Error( "WMCP_BASE_URL must be an origin without a path, query, or fragment." );
	}

	return url;
}

export function assertNoProviderCredentials( environment ) {
	const presentKeys = PROVIDER_ENVIRONMENT_KEYS.filter(
		( key ) => typeof environment[ key ] === "string" && environment[ key ].length > 0
	);
	if ( presentKeys.length > 0 ) {
		throw new Error(
			`Keyless WebMCP smoke refuses provider credentials: ${ presentKeys.join( ", " ) }.`
		);
	}
}

export function buildSmokeRuns( baseUrl ) {
	return [
		{
			fixture: path.join( EVAL_ROOT, "storefront-smoke.json" ),
			name: "storefront",
			url: new URL( "/storefront-demo/", baseUrl ).href,
		},
		{
			fixture: path.join( EVAL_ROOT, "agentops-smoke.json" ),
			name: "agentops",
			url: new URL( "/agentops-demo/", baseUrl ).href,
		},
	];
}

function failureDetail( failure ) {
	if ( failure === null || typeof failure !== "object" || Array.isArray( failure ) ) {
		return "unspecified application failure";
	}
	const code = typeof failure.code === "string" ? failure.code : "application_error";
	const message = typeof failure.message === "string" ? failure.message : "tool returned ok:false";
	return `${ code }: ${ message }`;
}

export function applicationFailureFromSmokeStep( step ) {
	const result = step?.result;
	if ( result === null || typeof result !== "object" || Array.isArray( result ) ) {
		return null;
	}
	if ( result.ok === false ) {
		return failureDetail( result.error );
	}
	return null;
}

export function assertSuccessfulWebmcpSmoke( summary, surface ) {
	if ( summary === null || typeof summary !== "object" || ! Array.isArray( summary.results ) ) {
		throw new Error( `${ surface } smoke returned an invalid result summary.` );
	}
	if (
		! Number.isInteger( summary.totalExpectedSteps ) ||
		summary.totalExpectedSteps <= 0 ||
		summary.results.length !== summary.totalExpectedSteps
	) {
		throw new Error( `${ surface } smoke did not return every expected step.` );
	}

	for ( const step of summary.results ) {
		if ( step.outcome !== "pass" ) {
			throw new Error( step.error || `${ surface } smoke step did not pass.` );
		}
		const applicationFailure = applicationFailureFromSmokeStep( step );
		if ( applicationFailure ) {
			throw new Error(
				`${ surface } smoke tool ${ step.functionName } returned ok:false (${ applicationFailure }).`
			);
		}
	}

	if ( summary.errorCount !== 0 || summary.passCount !== summary.totalExpectedSteps ) {
		throw new Error(
			`${ surface } smoke summary was not all-pass (${ summary.passCount }/${ summary.totalExpectedSteps }).`
		);
	}

	return {
		passCount: summary.passCount,
		testCount: summary.testCount,
		totalExpectedSteps: summary.totalExpectedSteps,
	};
}

async function readSmokeFixture( fixturePath ) {
	let parsed;
	try {
		parsed = JSON.parse( await readFile( fixturePath, "utf8" ) );
	} catch ( error ) {
		const message = error instanceof Error ? error.message : String( error );
		throw new Error( `${ fixturePath }: unable to read valid smoke JSON (${ message }).`, {
			cause: error,
		} );
	}
	if ( ! Array.isArray( parsed ) || parsed.length === 0 ) {
		throw new Error( `${ fixturePath }: smoke fixture must contain at least one case.` );
	}
	return parsed;
}

export async function runWebmcpSmoke( environment = process.env ) {
	assertNoProviderCredentials( environment );
	const baseUrl = parseLocalWebmcpBaseUrl(
		environment.WMCP_BASE_URL || "http://localhost:18080"
	);

	for ( const run of buildSmokeRuns( baseUrl ) ) {
		process.stdout.write( `Running native WebMCP smoke for ${ run.name } at ${ run.url }\n` );
		const fixture = await readSmokeFixture( run.fixture );
		const summary = await executeSmokeEvals( fixture, {
			chromeChannel: "chrome",
			timeoutMs: 30000,
			url: run.url,
			verbose: false,
		} );
		const checked = assertSuccessfulWebmcpSmoke( summary, run.name );
		process.stdout.write(
			`${ run.name } native smoke passed ${ checked.passCount }/${ checked.totalExpectedSteps } steps across ${ checked.testCount } cases.\n`
		);
	}
}

const invokedPath = process.argv[ 1 ] ? pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href : "";
if ( invokedPath === import.meta.url ) {
	runWebmcpSmoke().catch( ( error ) => {
		process.stderr.write( `${ error instanceof Error ? error.message : String( error ) }\n` );
		process.exitCode = 1;
	} );
}
