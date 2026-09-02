#!/usr/bin/env node

/* global console, process */

import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const EXPECTED_VERSION = "0.0.4";
const PATCH_MARKER = "WMCP_EVAL_PATCH: disable-local-parallel-tool-calls";
const COMMANDS_BEFORE = "        maxSteps: opts.maxSteps,\n        outputDir: opts.outputDir,";
const COMMANDS_AFTER = `        maxSteps: opts.maxSteps,
        parallelToolCalls: false, // ${ PATCH_MARKER }
        outputDir: opts.outputDir,`;
const BACKEND_FIELD_BEFORE = "    maxSteps;\n    logger;";
const BACKEND_FIELD_AFTER = `    maxSteps;
    parallelToolCalls; // ${ PATCH_MARKER }
    logger;`;
const BACKEND_CONSTRUCTOR_BEFORE =
	"        this.maxSteps = config.maxSteps ?? DEFAULT_MAX_STEPS;\n        this.logger = new ConsoleLogger();";
const BACKEND_CONSTRUCTOR_AFTER = `        this.maxSteps = config.maxSteps ?? DEFAULT_MAX_STEPS;
        this.parallelToolCalls = config.parallelToolCalls;
        this.logger = new ConsoleLogger();`;
const BACKEND_GENERATION_BEFORE =
	"            tools: executableTools,\n            // Enables multi-step trajectories.";
const BACKEND_GENERATION_AFTER = `            tools: executableTools,
            providerOptions: this.modelName.startsWith("openai:") && this.parallelToolCalls === false
                ? { openai: { parallelToolCalls: false } }
                : undefined,
            // Enables multi-step trajectories.`;

function replaceRequired( source, before, after, label ) {
	if ( ! source.includes( before ) ) {
		throw new Error( `webmcp-evals ${ label } no longer matches the reviewed ${ EXPECTED_VERSION } source.` );
	}

	return source.replace( before, after );
}

function requirePatchedSnippets( source, snippets, label ) {
	for ( const snippet of snippets ) {
		if ( ! source.includes( snippet ) ) {
			throw new Error( `webmcp-evals ${ label } contains an incomplete or altered local-selection patch.` );
		}
	}

	return source;
}

export function patchCommandsSource( source ) {
	return source.includes( PATCH_MARKER )
		? requirePatchedSnippets( source, [ COMMANDS_AFTER ], "local command" )
		: replaceRequired( source, COMMANDS_BEFORE, COMMANDS_AFTER, "local command" );
}

export function patchVercelBackendSource( source ) {
	if ( source.includes( PATCH_MARKER ) ) {
		return requirePatchedSnippets(
			source,
			[ BACKEND_FIELD_AFTER, BACKEND_CONSTRUCTOR_AFTER, BACKEND_GENERATION_AFTER ],
			"Vercel backend"
		);
	}

	let patched = replaceRequired(
		source,
		BACKEND_FIELD_BEFORE,
		BACKEND_FIELD_AFTER,
		"Vercel backend field"
	);
	patched = replaceRequired(
		patched,
		BACKEND_CONSTRUCTOR_BEFORE,
		BACKEND_CONSTRUCTOR_AFTER,
		"Vercel backend constructor"
	);
	patched = replaceRequired(
		patched,
		BACKEND_GENERATION_BEFORE,
		BACKEND_GENERATION_AFTER,
		"Vercel local generation"
	);

	return patched;
}

export async function patchWebmcpEvals( packageRoot = path.resolve( "node_modules/webmcp-evals" ) ) {
	const packagePath = path.join( packageRoot, "package.json" );
	const packageJson = JSON.parse( await readFile( packagePath, "utf8" ) );
	if ( packageJson.version !== EXPECTED_VERSION ) {
		throw new Error(
			`Expected webmcp-evals@${ EXPECTED_VERSION }, found ${ String( packageJson.version ) }.`
		);
	}

	const commandsPath = path.join( packageRoot, "dist/commands/index.js" );
	const backendPath = path.join( packageRoot, "dist/backends/vercel.js" );
	const commands = await readFile( commandsPath, "utf8" );
	const backend = await readFile( backendPath, "utf8" );
	const patchedCommands = patchCommandsSource( commands );
	const patchedBackend = patchVercelBackendSource( backend );

	if ( patchedCommands !== commands ) {
		await writeFile( commandsPath, patchedCommands );
	}
	if ( patchedBackend !== backend ) {
		await writeFile( backendPath, patchedBackend );
	}

	return { changed: patchedCommands !== commands || patchedBackend !== backend };
}

if ( process.argv[ 1 ] && path.resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	patchWebmcpEvals()
		.then( ( result ) => {
			console.log(
				result.changed
					? `Patched webmcp-evals@${ EXPECTED_VERSION } for single-call local selection.`
					: `webmcp-evals@${ EXPECTED_VERSION } local-selection patch is already applied.`
			);
		} )
		.catch( ( error ) => {
			console.error( error instanceof Error ? error.message : String( error ) );
			process.exitCode = 1;
		} );
}
