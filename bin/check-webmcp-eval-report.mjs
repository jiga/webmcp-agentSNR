#!/usr/bin/env node

/* global process, URL */

import { createHash } from "node:crypto";
import { readFile } from "node:fs/promises";
import path from "node:path";
import { isDeepStrictEqual } from "node:util";
import { pathToFileURL } from "node:url";

const CLI_OPTIONS = new Map( [
	[ "--backend", "expectedBackend" ],
	[ "--chrome-channel", "expectedChromeChannel" ],
	[ "--fixture", "fixturePath" ],
	[ "--mode", "expectedMode" ],
	[ "--model", "expectedModel" ],
	[ "--report", "reportPath" ],
	[ "--runs", "expectedRuns" ],
	[ "--schema", "expectedSchemaPath" ],
	[ "--url", "expectedUrl" ],
] );

const USAGE =
	"Usage: node bin/check-webmcp-eval-report.mjs --report <report.json> --fixture <evals.json> --model <exact-model-id> --runs <positive-integer> --backend <exact-backend> --mode <local|browser> [--schema <tools.json> | --url <loopback-url> --chrome-channel <channel>]";

export function parseReleaseBrowserUrl( value, field = "browser URL" ) {
	let url;
	try {
		url = new URL( value );
	} catch {
		throw new Error( `${ field } must be a valid loopback URL.` );
	}
	const localHosts = new Set( [ "localhost", "127.0.0.1", "[::1]", "::1" ] );
	if ( ! [ "http:", "https:" ].includes( url.protocol ) || ! localHosts.has( url.hostname ) ) {
		throw new Error( `${ field } must use a loopback host for this release.` );
	}
	if ( url.username || url.password || url.search || url.hash ) {
		throw new Error( `${ field } must not contain credentials, a query, or a fragment.` );
	}
	return url;
}

function nonNegativeInteger( value, field, source ) {
	if ( ! Number.isInteger( value ) || value < 0 ) {
		throw new Error( `${ source }: ${ field } must be a non-negative integer.` );
	}
}

function positiveInteger( value, field, source ) {
	if ( ! Number.isInteger( value ) || value <= 0 ) {
		throw new Error( `${ source }: ${ field } must be a positive integer.` );
	}
}

function requireObject( value, field, source ) {
	if ( value === null || typeof value !== "object" || Array.isArray( value ) ) {
		throw new Error( `${ source }: ${ field } must be an object.` );
	}
	return value;
}

function fixtureCasesByName( fixture, fixtureSource ) {
	if ( ! Array.isArray( fixture ) || fixture.length === 0 ) {
		throw new Error( `${ fixtureSource }: fixture must contain at least one eval case.` );
	}

	const cases = new Map();
	for ( const [ index, testCase ] of fixture.entries() ) {
		requireObject( testCase, `case ${ index + 1 }`, fixtureSource );
		if ( typeof testCase.name !== "string" || testCase.name.length === 0 ) {
			throw new Error( `${ fixtureSource}: every eval case must have a non-empty name.` );
		}
		if ( cases.has( testCase.name ) ) {
			throw new Error( `${ fixtureSource }: duplicate eval case name "${ testCase.name }".` );
		}
		if ( ! Array.isArray( testCase.messages ) || testCase.messages.length === 0 ) {
			throw new Error( `${ fixtureSource }: ${ testCase.name } must contain messages.` );
		}
		cases.set( testCase.name, testCase );
	}
	return cases;
}

function expectedLeafTest( testCase, call ) {
	return {
		expectedCall: [ call ],
		messages: testCase.messages,
		name: testCase.name,
	};
}

function requiredExpectedRowCount( nodes ) {
	return nodes.reduce( ( count, node ) => {
		if ( node && typeof node === "object" && Array.isArray( node.ordered ) ) {
			return count + requiredExpectedRowCount( node.ordered );
		}
		if ( node && typeof node === "object" && Array.isArray( node.unordered ) ) {
			return count + requiredExpectedRowCount( node.unordered );
		}
		return count + ( node?.optional === true ? 0 : 1 );
	}, 0 );
}

function matchExpectedSequence( nodes, reportedTests, startIndex, testCase ) {
	let positions = new Set( [ startIndex ] );
	for ( const node of nodes ) {
		const nextPositions = new Set();
		for ( const position of positions ) {
			for ( const next of matchExpectedNode( node, reportedTests, position, testCase ) ) {
				nextPositions.add( next );
			}
		}
		positions = nextPositions;
		if ( positions.size === 0 ) {
			break;
		}
	}
	return positions;
}

function matchUnorderedNodes( nodes, reportedTests, startIndex, testCase ) {
	const endings = new Set();
	function visit( remaining, position ) {
		if ( remaining.length === 0 ) {
			endings.add( position );
			return;
		}
		for ( let index = 0; index < remaining.length; index += 1 ) {
			const node = remaining[ index ];
			const rest = [ ...remaining.slice( 0, index ), ...remaining.slice( index + 1 ) ];
			for ( const next of matchExpectedNode( node, reportedTests, position, testCase ) ) {
				visit( rest, next );
			}
		}
	}
	visit( nodes, startIndex );
	return endings;
}

function matchExpectedNode( node, reportedTests, startIndex, testCase ) {
	if ( node && typeof node === "object" && Array.isArray( node.ordered ) ) {
		return matchExpectedSequence( node.ordered, reportedTests, startIndex, testCase );
	}
	if ( node && typeof node === "object" && Array.isArray( node.unordered ) ) {
		return matchUnorderedNodes( node.unordered, reportedTests, startIndex, testCase );
	}

	const endings = new Set();
	if ( node?.optional === true ) {
		endings.add( startIndex );
	}
	if (
		startIndex < reportedTests.length &&
		isDeepStrictEqual( reportedTests[ startIndex ], expectedLeafTest( testCase, node ) )
	) {
		endings.add( startIndex + 1 );
	}
	return endings;
}

function reportedTestsMatchFixtureTree( testCase, reportedTests ) {
	if ( testCase.expectedCall === null || testCase.expectedCall?.length === 0 ) {
		return reportedTests.length === 1 && isDeepStrictEqual( reportedTests[ 0 ], testCase );
	}
	if ( ! Array.isArray( testCase.expectedCall ) ) {
		return false;
	}
	return matchExpectedSequence( testCase.expectedCall, reportedTests, 0, testCase ).has(
		reportedTests.length
	);
}

function caseRunIdentity( name, runIndex ) {
	return JSON.stringify( [ name, runIndex ] );
}

function resultRowIdentity( name, runIndex, stepIndex ) {
	return JSON.stringify( [ name, runIndex, stepIndex ] );
}

export function validateWebmcpEvalReport(
	report,
	{
		expectedBackend,
		expectedChromeChannel,
		expectedMode,
		expectedModel,
		expectedRuns,
		expectedSchemaPath,
		expectedUrl,
		fixture,
		fixtureContents,
		fixturePath,
		fixtureSource = "fixture",
		source = "report",
	}
) {
	if ( typeof expectedBackend !== "string" || expectedBackend.length === 0 ) {
		throw new Error( `${ source }: expectedBackend must be an explicit non-empty backend.` );
	}
	if ( ! [ "browser", "local" ].includes( expectedMode ) ) {
		throw new Error( `${ source }: expectedMode must be local or browser.` );
	}
	if ( typeof expectedModel !== "string" || expectedModel.length === 0 ) {
		throw new Error( `${ source }: expectedModel must be an explicit non-empty model ID.` );
	}
	positiveInteger( expectedRuns, "expectedRuns", source );
	if ( typeof fixturePath !== "string" || fixturePath.length === 0 ) {
		throw new Error( `${ source }: fixturePath is required for suite provenance.` );
	}
	if ( typeof fixtureContents !== "string" || fixtureContents.length === 0 ) {
		throw new Error( `${ source }: fixtureContents is required for suite provenance.` );
	}
	let parsedFixtureContents;
	try {
		parsedFixtureContents = JSON.parse( fixtureContents );
	} catch ( error ) {
		throw new Error( `${ fixtureSource }: fixtureContents must be valid JSON.`, { cause: error } );
	}
	if ( ! isDeepStrictEqual( parsedFixtureContents, fixture ) ) {
		throw new Error( `${ source }: fixtureContents do not match the selected fixture object.` );
	}
	const fixtureSha256 = createHash( "sha256" ).update( fixtureContents ).digest( "hex" );

	requireObject( report, "report", source );
	const config = requireObject( report.config, "config", source );
	if ( config.backend !== expectedBackend ) {
		throw new Error(
			`${ source }: backend mismatch (expected "${ expectedBackend }", got "${ String( config.backend ) }").`
		);
	}
	if ( config.model !== expectedModel ) {
		throw new Error(
			`${ source }: model mismatch (expected "${ expectedModel }", got "${ String( config.model ) }").`
		);
	}
	if ( config.runs !== expectedRuns ) {
		throw new Error(
			`${ source }: run-count mismatch (expected ${ expectedRuns }, got ${ String( config.runs ) }).`
		);
	}
	if ( typeof config.evalsFile !== "string" || config.evalsFile.length === 0 ) {
		throw new Error( `${ source }: config.evalsFile is required for suite provenance.` );
	}
	const configuredFixturePath = path.resolve( config.evalsFile );
	const expectedFixturePath = path.resolve( fixturePath );
	if ( configuredFixturePath !== expectedFixturePath ) {
		throw new Error(
			`${ source }: fixture mismatch (report used ${ configuredFixturePath }, expected ${ expectedFixturePath }).`
		);
	}

	let browserUrl;
	let chromeChannel;
	let schemaPath;
	if ( expectedMode === "local" ) {
		if ( typeof expectedSchemaPath !== "string" || expectedSchemaPath.length === 0 ) {
			throw new Error( `${ source }: local mode requires expectedSchemaPath.` );
		}
		if ( typeof config.toolSchemaFile !== "string" || config.toolSchemaFile.length === 0 ) {
			throw new Error( `${ source }: local report config.toolSchemaFile is required.` );
		}
		schemaPath = path.resolve( expectedSchemaPath );
		const configuredSchemaPath = path.resolve( config.toolSchemaFile );
		if ( configuredSchemaPath !== schemaPath ) {
			throw new Error(
				`${ source }: schema mismatch (report used ${ configuredSchemaPath }, expected ${ schemaPath }).`
			);
		}
	} else {
		if ( typeof expectedChromeChannel !== "string" || expectedChromeChannel.length === 0 ) {
			throw new Error( `${ source }: browser mode requires expectedChromeChannel.` );
		}
		const expectedBrowserUrl = parseReleaseBrowserUrl( expectedUrl, "expected browser URL" );
		const configuredBrowserUrl = parseReleaseBrowserUrl(
			config.url,
			"report config.url"
		);
		if ( configuredBrowserUrl.href !== expectedBrowserUrl.href ) {
			throw new Error(
				`${ source }: browser URL mismatch (report used ${ configuredBrowserUrl.href }, expected ${ expectedBrowserUrl.href }).`
			);
		}
		if ( config.chromeChannel !== expectedChromeChannel ) {
			throw new Error(
				`${ source }: Chrome channel mismatch (expected "${ expectedChromeChannel }", got "${ String( config.chromeChannel ) }").`
			);
		}
		browserUrl = expectedBrowserUrl.href;
		chromeChannel = expectedChromeChannel;
	}

	const cases = fixtureCasesByName( fixture, fixtureSource );
	const expectedCaseRuns = cases.size * expectedRuns;
	const summary = requireObject( report.results, "results summary", source );
	for ( const field of [ "testCount", "passCount", "failCount", "errorCount" ] ) {
		nonNegativeInteger( summary[ field ], `results.${ field }`, source );
	}
	if ( summary.testCount !== expectedCaseRuns ) {
		throw new Error(
			`${ source }: results.testCount must equal fixture cases × runs (${ expectedCaseRuns }).`
		);
	}
	if ( ! Array.isArray( summary.results ) || summary.results.length === 0 ) {
		throw new Error( `${ source }: results.results must contain at least one eval row.` );
	}

	const derived = { error: 0, fail: 0, pass: 0 };
	const seenRows = new Set();
	const caseRunRows = new Map();
	let browserConsoleErrorCount = 0;

	for ( const [ index, result ] of summary.results.entries() ) {
		requireObject( result, `results.results[${ index }]`, source );
		if ( ! Object.hasOwn( derived, result.outcome ) ) {
			throw new Error(
				`${ source }: results.results[${ index }].outcome must be pass, fail, or error.`
			);
		}
		derived[ result.outcome ] += 1;

		const reportedTest = requireObject(
			result.test,
			`results.results[${ index }].test`,
			source
		);
		if ( typeof reportedTest.name !== "string" || ! cases.has( reportedTest.name ) ) {
			throw new Error(
				`${ source }: results.results[${ index }] has an unknown case name "${ String( reportedTest.name ) }".`
			);
		}
		positiveInteger( result.runIndex, `results.results[${ index }].runIndex`, source );
		if ( result.runIndex > expectedRuns ) {
			throw new Error( `${ source }: ${ reportedTest.name } has an unexpected runIndex.` );
		}
		positiveInteger( result.stepIndex, `results.results[${ index }].stepIndex`, source );

		const rowIdentity = resultRowIdentity(
			reportedTest.name,
			result.runIndex,
			result.stepIndex
		);
		if ( seenRows.has( rowIdentity ) ) {
			throw new Error(
				`${ source }: duplicate result identity for ${ reportedTest.name }, run ${ result.runIndex }, step ${ result.stepIndex }.`
			);
		}
		seenRows.add( rowIdentity );

		const groupIdentity = caseRunIdentity( reportedTest.name, result.runIndex );
		const rows = caseRunRows.get( groupIdentity ) || [];
		rows.push( { reportedTest, stepIndex: result.stepIndex } );
		caseRunRows.set( groupIdentity, rows );

		if ( result.browserConsoleErrors !== undefined ) {
			if ( ! Array.isArray( result.browserConsoleErrors ) ) {
				throw new Error(
					`${ source }: results.results[${ index }].browserConsoleErrors must be an array.`
				);
			}
			browserConsoleErrorCount += result.browserConsoleErrors.length;
		}
	}

	for ( const caseName of cases.keys() ) {
		for ( let runIndex = 1; runIndex <= expectedRuns; runIndex += 1 ) {
			const identity = caseRunIdentity( caseName, runIndex );
			if ( ! caseRunRows.has( identity ) ) {
				throw new Error( `${ source }: missing result identity for ${ caseName }, run ${ runIndex }.` );
			}
			const rows = [ ...caseRunRows.get( identity ) ].sort(
				( left, right ) => left.stepIndex - right.stepIndex
			);
			for ( const [ index, row ] of rows.entries() ) {
				if ( row.stepIndex !== index + 1 ) {
					throw new Error(
						`${ source }: ${ caseName }, run ${ runIndex } has missing or non-contiguous steps.`
					);
				}
			}

			const fixtureCase = cases.get( caseName );
			const requiredRows =
				fixtureCase.expectedCall === null || fixtureCase.expectedCall?.length === 0
					? 1
					: requiredExpectedRowCount( fixtureCase.expectedCall );
			if ( rows.length < requiredRows ) {
				throw new Error(
					`${ source }: ${ caseName }, run ${ runIndex } is missing ${ requiredRows - rows.length } required step(s).`
				);
			}
			if (
				! reportedTestsMatchFixtureTree(
					fixtureCase,
					rows.map( ( row ) => row.reportedTest )
				)
			) {
				throw new Error(
					`${ source }: ${ caseName }, run ${ runIndex } reported test metadata/expectedCall order does not match the selected fixture tree.`
				);
			}
		}
	}

	if (
		derived.pass !== summary.passCount ||
		derived.fail !== summary.failCount ||
		derived.error !== summary.errorCount
	) {
		throw new Error( `${ source }: summary counts do not match result outcomes.` );
	}
	if ( summary.failCount > 0 || summary.errorCount > 0 ) {
		throw new Error(
			`${ source }: release evaluation must be 100% all-pass (${ summary.failCount } failed, ${ summary.errorCount } errored).`
		);
	}
	if ( summary.passCount !== summary.results.length ) {
		throw new Error(
			`${ source }: release evaluation must pass every result row (${ summary.passCount }/${ summary.results.length }).`
		);
	}
	if ( browserConsoleErrorCount > 0 ) {
		throw new Error(
			`${ source }: browser evaluation recorded ${ browserConsoleErrorCount } console/page error(s).`
		);
	}

	return {
		backend: expectedBackend,
		browserConsoleErrorCount,
		caseRunCount: expectedCaseRuns,
		fixturePath: expectedFixturePath,
		mode: expectedMode,
		model: expectedModel,
		passCount: summary.passCount,
		resultCount: summary.results.length,
		runs: expectedRuns,
		suiteSha256: fixtureSha256,
		...( expectedMode === "browser"
			? { browserUrl, chromeChannel }
			: { schemaPath } ),
	};
}

async function readJson( filePath, label ) {
	let contents;
	try {
		contents = await readFile( filePath, "utf8" );
		return { contents, parsed: JSON.parse( contents ) };
	} catch ( error ) {
		const message = error instanceof Error ? error.message : String( error );
		throw new Error( `${ filePath }: unable to read valid ${ label } JSON (${ message }).`, {
			cause: error,
		} );
	}
}

export async function checkWebmcpEvalReport( {
	expectedBackend,
	expectedChromeChannel,
	expectedMode,
	expectedModel,
	expectedRuns,
	expectedSchemaPath,
	expectedUrl,
	fixturePath,
	reportPath,
} ) {
	for ( const [ label, filePath ] of [
		[ "fixture", fixturePath ],
		[ "report", reportPath ],
	] ) {
		if ( typeof filePath !== "string" || path.extname( filePath ).toLowerCase() !== ".json" ) {
			throw new Error( `${ label } path must explicitly name a JSON file.` );
		}
	}

	const absoluteFixturePath = path.resolve( fixturePath );
	const absoluteReportPath = path.resolve( reportPath );
	const [ fixtureFile, reportFile ] = await Promise.all( [
		readJson( absoluteFixturePath, "fixture" ),
		readJson( absoluteReportPath, "report" ),
	] );
	return validateWebmcpEvalReport( reportFile.parsed, {
		expectedBackend,
		expectedChromeChannel,
		expectedMode,
		expectedModel,
		expectedRuns,
		expectedSchemaPath,
		expectedUrl,
		fixture: fixtureFile.parsed,
		fixtureContents: fixtureFile.contents,
		fixturePath: absoluteFixturePath,
		fixtureSource: absoluteFixturePath,
		source: absoluteReportPath,
	} );
}

export function parseReportCheckerArguments( argumentsList ) {
	const parsed = {};
	for ( let index = 0; index < argumentsList.length; index += 2 ) {
		const option = argumentsList[ index ];
		const key = CLI_OPTIONS.get( option );
		const value = argumentsList[ index + 1 ];
		if ( ! key || value === undefined || value.startsWith( "--" ) ) {
			throw new Error( USAGE );
		}
		if ( Object.hasOwn( parsed, key ) ) {
			throw new Error( `${ option } may be provided only once.` );
		}
		parsed[ key ] = value;
	}

	for ( const requiredKey of [
		"expectedBackend",
		"expectedMode",
		"expectedModel",
		"expectedRuns",
		"fixturePath",
		"reportPath",
	] ) {
		if ( ! Object.hasOwn( parsed, requiredKey ) ) {
			throw new Error( USAGE );
		}
	}
	if ( ! /^\d+$/.test( parsed.expectedRuns ) ) {
		throw new Error( "--runs must be a positive integer." );
	}
	parsed.expectedRuns = Number( parsed.expectedRuns );
	positiveInteger( parsed.expectedRuns, "--runs", "arguments" );
	if ( parsed.expectedMode === "local" ) {
		if (
			! parsed.expectedSchemaPath ||
			parsed.expectedUrl ||
			parsed.expectedChromeChannel
		) {
			throw new Error( "Local mode requires --schema and rejects --url/--chrome-channel." );
		}
	} else if ( parsed.expectedMode === "browser" ) {
		if (
			! parsed.expectedUrl ||
			! parsed.expectedChromeChannel ||
			parsed.expectedSchemaPath
		) {
			throw new Error(
				"Browser mode requires --url and --chrome-channel and rejects --schema."
			);
		}
		parseReleaseBrowserUrl( parsed.expectedUrl, "--url" );
	} else {
		throw new Error( "--mode must be local or browser." );
	}
	return parsed;
}

async function main( argumentsList ) {
	const options = parseReportCheckerArguments( argumentsList );
	const summary = await checkWebmcpEvalReport( options );
	process.stdout.write(
		`WebMCP eval report passed 100%: ${ options.reportPath } (${ summary.passCount }/${ summary.resultCount } rows; ${ summary.caseRunCount } case-runs; ${ summary.mode }/${ summary.backend }; model ${ summary.model }; suite sha256:${ summary.suiteSha256 }).\n`
	);
}

const invokedPath = process.argv[ 1 ] ? pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href : "";
if ( invokedPath === import.meta.url ) {
	main( process.argv.slice( 2 ) ).catch( ( error ) => {
		process.stderr.write( `${ error instanceof Error ? error.message : String( error ) }\n` );
		process.exitCode = 1;
	} );
}
