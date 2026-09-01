import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { readFile } from "node:fs/promises";
import path from "node:path";
import { describe, it } from "node:test";
import { fileURLToPath } from "node:url";

import { runSmokeTest } from "webmcp-evals/dist/evaluator/smokeEvaluator.js";
import { functionCallOutcome } from "webmcp-evals/dist/utils.js";

import {
	parseReportCheckerArguments,
	validateWebmcpEvalReport,
} from "../../bin/check-webmcp-eval-report.mjs";
import {
	assertNoProviderCredentials,
	assertSuccessfulWebmcpSmoke,
	buildSmokeRuns,
	parseLocalWebmcpBaseUrl,
} from "../../bin/run-webmcp-smoke.mjs";

const REPOSITORY_ROOT = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	"..",
	".."
);
const EVAL_ROOT = path.join( REPOSITORY_ROOT, "evals" );

const SURFACES = {
	agentops: {
		expectedNames: [
			"get_agent_analytics_overview",
			"get_agent_conversion_funnel",
			"query_agent_workflows",
			"explain_agent_workflow",
			"get_tool_health",
			"get_opportunity_signals",
			"run_webmcp_diagnostics",
			"set_tool_enabled",
		],
		safeSmokeNames: new Set( [
			"get_agent_analytics_overview",
			"get_agent_conversion_funnel",
			"get_opportunity_signals",
			"get_tool_health",
			"query_agent_workflows",
			"run_webmcp_diagnostics",
		] ),
	},
	storefront: {
		expectedNames: [
			"get_storefront_context",
			"get_agent_guide",
			"search_products",
			"get_product",
			"compare_products",
			"get_store_policy",
			"get_cart",
			"add_to_cart",
			"remove_from_cart",
			"update_cart_quantity",
			"prepare_checkout_handoff",
			"report_agent_feedback",
		],
		safeSmokeNames: new Set( [
			"get_agent_guide",
			"get_cart",
			"get_store_policy",
			"get_storefront_context",
			"search_products",
		] ),
	},
};

async function readJsonFixture( relativePath ) {
	return JSON.parse( await readFile( path.join( EVAL_ROOT, relativePath ), "utf8" ) );
}

function expectedFunctionCalls( nodes ) {
	const calls = [];
	for ( const node of nodes || [] ) {
		if ( node && typeof node === "object" && Array.isArray( node.ordered ) ) {
			calls.push( ...expectedFunctionCalls( node.ordered ) );
		} else if ( node && typeof node === "object" && Array.isArray( node.unordered ) ) {
			calls.push( ...expectedFunctionCalls( node.unordered ) );
		} else {
			calls.push( node );
		}
	}
	return calls;
}

function assertEvalSuiteShape( suite, allowedTools ) {
	assert.ok( Array.isArray( suite ) && suite.length > 0 );
	for ( const testCase of suite ) {
		assert.equal( typeof testCase.name, "string" );
		assert.ok( testCase.name.length > 0 );
		assert.ok( Array.isArray( testCase.messages ) && testCase.messages.length > 0 );
		for ( const message of testCase.messages ) {
			assert.ok( [ "model", "user" ].includes( message.role ) );
			assert.ok( [ "functioncall", "functionresponse", "message" ].includes( message.type ) );
			if ( message.type === "message" ) {
				assert.equal( typeof message.content, "string" );
			} else {
				assert.equal( typeof message.name, "string" );
				assert.ok( allowedTools.has( message.name ), `${ message.name } is not public` );
			}
		}

		assert.ok( testCase.expectedCall === null || Array.isArray( testCase.expectedCall ) );
		for ( const call of expectedFunctionCalls( testCase.expectedCall ) ) {
			assert.equal( typeof call.functionName, "string" );
			assert.ok( allowedTools.has( call.functionName ), `${ call.functionName } is not public` );
			assert.ok(
				call.arguments === null ||
					call.arguments === undefined ||
					( typeof call.arguments === "object" && ! Array.isArray( call.arguments ) )
			);
			if ( call.optional !== undefined ) {
				assert.equal( typeof call.optional, "boolean" );
			}
		}
	}
}

function assertExpectedArgumentsUseSchema( suite, schema ) {
	const tools = new Map( schema.tools.map( ( tool ) => [ tool.name, tool ] ) );
	for ( const testCase of suite ) {
		for ( const call of expectedFunctionCalls( testCase.expectedCall ) ) {
			if ( call.arguments === undefined || call.arguments === null ) {
				continue;
			}

			const inputSchema = tools.get( call.functionName ).inputSchema;
			const propertyNames = new Set( Object.keys( inputSchema.properties || {} ) );
			for ( const argumentName of Object.keys( call.arguments ) ) {
				assert.ok(
					propertyNames.has( argumentName ),
					`${ testCase.name }: ${ call.functionName }.${ argumentName } is not in inputSchema`
				);
			}
			for ( const requiredName of inputSchema.required || [] ) {
				assert.ok(
					Object.hasOwn( call.arguments, requiredName ),
					`${ testCase.name }: ${ call.functionName } is missing required ${ requiredName }`
				);
			}
		}
	}
}

function hasConstraintKey( value ) {
	if ( Array.isArray( value ) ) {
		return value.some( hasConstraintKey );
	}
	if ( value === null || typeof value !== "object" ) {
		return false;
	}
	return Object.entries( value ).some(
		( [ key, child ] ) => key.startsWith( "$" ) || hasConstraintKey( child )
	);
}

const REPORT_FIXTURE_PATH = "/workspace/evals/release.json";
const REPORT_SCHEMA_PATH = "/workspace/evals/schema.json";
const REPORT_MODEL = "provider:release-model";
const REPORT_FIXTURE = [
	{
		expectedCall: [ { arguments: {}, functionName: "first_tool" } ],
		messages: [ { content: "First case", role: "user", type: "message" } ],
		name: "First release case",
	},
	{
		expectedCall: [ { arguments: {}, functionName: "second_tool" } ],
		messages: [ { content: "Second case", role: "user", type: "message" } ],
		name: "Second release case",
	},
];
const REPORT_FIXTURE_CONTENTS = JSON.stringify( REPORT_FIXTURE );
const REPORT_FIXTURE_SHA256 = createHash( "sha256" )
	.update( REPORT_FIXTURE_CONTENTS )
	.digest( "hex" );

function reportRows( fixture = REPORT_FIXTURE, runs = 1 ) {
	const rows = [];
	for ( let runIndex = 1; runIndex <= runs; runIndex += 1 ) {
		for ( const testCase of fixture ) {
			rows.push( {
				browserConsoleErrors: [],
				outcome: "pass",
				runIndex,
				stepIndex: 1,
				test: {
					expectedCall: testCase.expectedCall,
					messages: testCase.messages,
					name: testCase.name,
				},
			} );
		}
	}
	return rows;
}

function passingReport( {
	config = {},
	fixture = REPORT_FIXTURE,
	results = {},
	rows = reportRows( fixture, config.runs || 1 ),
} = {} ) {
	const passCount = rows.filter( ( row ) => row.outcome === "pass" ).length;
	const failCount = rows.filter( ( row ) => row.outcome === "fail" ).length;
	const errorCount = rows.filter( ( row ) => row.outcome === "error" ).length;
	return {
		config: {
			backend: "vercel",
			evalsFile: REPORT_FIXTURE_PATH,
			model: REPORT_MODEL,
			runs: 1,
			toolSchemaFile: REPORT_SCHEMA_PATH,
			...config,
		},
		results: {
			errorCount,
			failCount,
			passCount,
			results: rows,
			testCount: fixture.length * ( config.runs || 1 ),
			...results,
		},
	};
}

function reportValidationOptions( overrides = {} ) {
	return {
		expectedBackend: "vercel",
		expectedMode: "local",
		expectedModel: REPORT_MODEL,
		expectedRuns: 1,
		expectedSchemaPath: REPORT_SCHEMA_PATH,
		fixture: REPORT_FIXTURE,
		fixtureContents: REPORT_FIXTURE_CONTENTS,
		fixturePath: REPORT_FIXTURE_PATH,
		...overrides,
	};
}

describe( "WebMCP eval fixtures", () => {
	it( "keeps generated schema fixtures limited to the canonical public surfaces", async () => {
		for ( const [ surface, contract ] of Object.entries( SURFACES ) ) {
			const schema = await readJsonFixture( `schemas/${ surface }-tools.json` );
			assert.ok( Array.isArray( schema.tools ) );
			assert.deepEqual(
				schema.tools.map( ( tool ) => tool.name ),
				contract.expectedNames
			);
			assert.equal( new Set( contract.expectedNames ).size, schema.tools.length );

			for ( const tool of schema.tools ) {
				assert.equal( typeof tool.description, "string" );
				assert.ok( tool.description.length > 0 );
				assert.equal( tool.inputSchema.type, "object" );
				assert.equal( tool.inputSchema.additionalProperties, false );
				assert.equal( tool.outputSchema.type, "object" );
			}
		}
	} );

	it( "uses strict CLI-compatible JSON and covers every required selection class", async () => {
		for ( const surface of Object.keys( SURFACES ) ) {
			const schema = await readJsonFixture( `schemas/${ surface }-tools.json` );
			const allowedTools = new Set( schema.tools.map( ( tool ) => tool.name ) );
			const suite = await readJsonFixture( `${ surface }-selection.json` );
			assertEvalSuiteShape( suite, allowedTools );
			assertExpectedArgumentsUseSchema( suite, schema );

			const names = suite.map( ( testCase ) => testCase.name.toLowerCase() );
			for ( const requiredClass of [
				"direct:",
				"paraphrase:",
				"recovery:",
				"ambiguous no-call:",
				"feedback vs gap",
				"safety no-call:",
			] ) {
				assert.ok(
					names.some( ( name ) => name.includes( requiredClass ) ),
					`${ surface } is missing ${ requiredClass } coverage`
				);
			}

			for ( const testCase of suite.filter( ( candidate ) =>
				candidate.name.startsWith( "Safety no-call:" )
			) ) {
				assert.equal( testCase.expectedCall, null );
			}
		}
	} );

	it( "keeps deterministic smoke concrete and read-only", async () => {
		for ( const [ surface, contract ] of Object.entries( SURFACES ) ) {
			const schema = await readJsonFixture( `schemas/${ surface }-tools.json` );
			const suite = await readJsonFixture( `${ surface }-smoke.json` );
			assertEvalSuiteShape( suite, new Set( schema.tools.map( ( tool ) => tool.name ) ) );
			assertExpectedArgumentsUseSchema( suite, schema );

			for ( const testCase of suite ) {
				for ( const call of expectedFunctionCalls( testCase.expectedCall ) ) {
					assert.ok( contract.safeSmokeNames.has( call.functionName ) );
					assert.equal( call.optional, undefined );
					assert.equal( hasConstraintKey( call.arguments ), false );
				}
			}
		}
	} );

	it( "defines a protected live journey that stops at the human handoff", async () => {
		const schema = await readJsonFixture( "schemas/storefront-tools.json" );
		const suite = await readJsonFixture( "browser-journeys.json" );
		assertEvalSuiteShape( suite, new Set( schema.tools.map( ( tool ) => tool.name ) ) );
		assertExpectedArgumentsUseSchema( suite, schema );
		const calls = expectedFunctionCalls( suite[ 0 ].expectedCall );
		for ( const call of calls ) {
			assert.deepEqual( call.result, { ok: true } );
		}
		assert.ok( calls.some( ( call ) => call.functionName === "prepare_checkout_handoff" ) );
		const feedbackCalls = calls.filter(
			( call ) => call.functionName === "report_agent_feedback"
		);
		assert.equal( feedbackCalls.length, 2 );
		assert.equal( calls[ 1 ].functionName, "search_products" );
		assert.equal( calls[ 2 ], feedbackCalls[ 0 ] );
		assert.equal( feedbackCalls[ 0 ].arguments.reason_code, "zero_results" );
		assert.equal( feedbackCalls[ 0 ].optional, true );
		assert.equal( feedbackCalls[ 1 ].arguments.reason_code, "smooth_handoff" );
		assert.equal( calls.at( -1 ).functionName, "report_agent_feedback" );
		assert.equal( calls.at( -1 ).optional, true );

		const failedResult = {
			error: { code: "application_failure", message: "The write failed." },
			ok: false,
		};
		const actualArguments = {
			add_to_cart: {
				expected_cart_revision: "cartrev_0123456789abcdef01234567",
				product_id: 42,
				quantity: 1,
			},
			prepare_checkout_handoff: {
				expected_cart_revision: "cartrev_0123456789abcdef01234567",
			},
		};
		for ( const functionName of [ "add_to_cart", "prepare_checkout_handoff" ] ) {
			const expected = calls.find( ( call ) => call.functionName === functionName );
			assert.equal(
				functionCallOutcome( expected, {
					args: actualArguments[ functionName ],
					functionName,
					result: failedResult,
				} ),
				"fail"
			);
		}
	} );

	it( "never defines legacy or sensitive-action function calls", async () => {
		const fixtureNames = [
			"agentops-selection.json",
			"agentops-smoke.json",
			"browser-journeys.json",
			"storefront-selection.json",
			"storefront-smoke.json",
		];
		const forbidden = [
			"checkout_handoff",
			"get_capability_gaps",
			"report_capability_gap",
			"place_order",
			"refund_order",
			"cancel_order",
		];

		for ( const fixtureName of fixtureNames ) {
			const suite = await readJsonFixture( fixtureName );
			const callNames = suite.flatMap( ( testCase ) =>
				expectedFunctionCalls( testCase.expectedCall ).map( ( call ) => call.functionName )
			);
			for ( const forbiddenName of forbidden ) {
				assert.equal(
					callNames.includes( forbiddenName ),
					false,
					`${ fixtureName }: ${ forbiddenName }`
				);
			}
		}
	} );
} );

describe( "WebMCP eval report checker", () => {
	it( "accepts only provenance-bound, complete, all-pass reports", () => {
		assert.deepEqual(
			validateWebmcpEvalReport( passingReport(), reportValidationOptions() ),
			{
			backend: "vercel",
			browserConsoleErrorCount: 0,
			caseRunCount: 2,
			fixturePath: REPORT_FIXTURE_PATH,
			mode: "local",
			model: REPORT_MODEL,
			passCount: 2,
			resultCount: 2,
			runs: 1,
			schemaPath: REPORT_SCHEMA_PATH,
			suiteSha256: REPORT_FIXTURE_SHA256,
			}
		);
	} );

	it( "rejects failed write rows, inconsistent counts, and empty reports", () => {
		const writeFixture = [
			{
				expectedCall: [
					{ arguments: {}, functionName: "add_to_cart", result: { ok: true } },
				],
				messages: [ { content: "Add", role: "user", type: "message" } ],
				name: "Failed add must not pass",
			},
			{
				expectedCall: [
					{
						arguments: {},
						functionName: "prepare_checkout_handoff",
						result: { ok: true },
					},
				],
				messages: [ { content: "Handoff", role: "user", type: "message" } ],
				name: "Failed handoff must not pass",
			},
		];
		const failedRows = reportRows( writeFixture ).map( ( row ) => ( {
			...row,
			outcome: "fail",
			response: {
				functionName: row.test.name.startsWith( "Failed add" )
					? "add_to_cart"
					: "prepare_checkout_handoff",
				result: { error: { code: "write_failed" }, ok: false },
			},
		} ) );
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { fixture: writeFixture, rows: failedRows } ),
					reportValidationOptions( {
						fixture: writeFixture,
						fixtureContents: JSON.stringify( writeFixture ),
					} )
				),
			/must be 100% all-pass/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { results: { passCount: 1 } } ),
					reportValidationOptions()
				),
			/summary counts do not match/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( {
						results: { passCount: 0 },
						rows: [],
					} ),
					reportValidationOptions()
				),
			/must contain at least one eval row/
		);
	} );

	it( "rejects backend, model, run, fixture, schema, and case-count mismatches", () => {
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( { expectedBackend: "other-backend" } )
				),
			/backend mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( { expectedModel: "provider:other-model" } )
				),
			/model mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( { expectedRuns: 3 } )
				),
			/run-count mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( { fixturePath: "/workspace/evals/other.json" } )
				),
			/fixture mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( {
						expectedSchemaPath: "/workspace/evals/other-schema.json",
					} )
				),
			/schema mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { results: { testCount: 1 } } ),
					reportValidationOptions()
				),
			/fixture cases × runs/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport(),
					reportValidationOptions( { fixtureContents: JSON.stringify( [] ) } )
				),
			/fixtureContents do not match/
		);
	} );

	it( "binds browser reports to an exact loopback URL and Chrome channel", () => {
		const browserUrl = "http://localhost:18080/storefront-demo/";
		const report = passingReport( {
			config: {
				chromeChannel: "chrome",
				url: browserUrl,
			},
		} );
		const options = reportValidationOptions( {
			expectedChromeChannel: "chrome",
			expectedMode: "browser",
			expectedSchemaPath: undefined,
			expectedUrl: browserUrl,
		} );
		const summary = validateWebmcpEvalReport( report, options );
		assert.equal( summary.browserUrl, browserUrl );
		assert.equal( summary.chromeChannel, "chrome" );

		assert.throws(
			() =>
				validateWebmcpEvalReport( report, {
					...options,
					expectedUrl: "https://demo.example.invalid/storefront-demo/",
				} ),
			/must use a loopback host/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( {
						config: {
							chromeChannel: "chrome",
							url: "http://127.0.0.1:18080/storefront-demo/",
						},
					} ),
					options
				),
			/browser URL mismatch/
		);
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( {
						config: { chromeChannel: "chrome-canary", url: browserUrl },
					} ),
					options
				),
			/Chrome channel mismatch/
		);
	} );

	it( "rejects missing, duplicate, and altered case identities or expectations", () => {
		const missingRows = reportRows().slice( 0, 1 );
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { rows: missingRows } ),
					reportValidationOptions()
				),
			/missing result identity/
		);

		const duplicateRows = [ ...reportRows(), { ...reportRows()[ 0 ] } ];
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { rows: duplicateRows } ),
					reportValidationOptions()
				),
			/duplicate result identity/
		);

		const alteredRows = reportRows();
		alteredRows[ 0 ] = {
			...alteredRows[ 0 ],
			test: {
				...alteredRows[ 0 ].test,
				messages: [ { content: "Altered prompt", role: "user", type: "message" } ],
			},
		};
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { rows: alteredRows } ),
					reportValidationOptions()
				),
			/metadata\/expectedCall order does not match/
		);

		const staleExpectationRows = reportRows();
		staleExpectationRows[ 0 ] = {
			...staleExpectationRows[ 0 ],
			test: {
				...staleExpectationRows[ 0 ].test,
				expectedCall: [ { arguments: {}, functionName: "stale_tool_contract" } ],
			},
		};
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { rows: staleExpectationRows } ),
					reportValidationOptions()
				),
			/metadata\/expectedCall order does not match/
		);
	} );

	it( "requires every recursive non-optional fixture step for each case and run", () => {
		const fixture = [
			{
				expectedCall: [
					{
						ordered: [
							{ arguments: {}, functionName: "first_tool", result: { ok: true } },
							{ arguments: {}, functionName: "second_tool", result: { ok: true } },
							{
								arguments: {},
								functionName: "optional_tool",
								optional: true,
								result: { ok: true },
							},
						],
					},
				],
				messages: [ { content: "Multi-step", role: "user", type: "message" } ],
				name: "Recursive multi-step case",
			},
		];
		const rows = [
			{
				browserConsoleErrors: [],
				outcome: "pass",
				runIndex: 1,
				stepIndex: 1,
				test: {
					expectedCall: [
						{ arguments: {}, functionName: "first_tool", result: { ok: true } },
					],
					messages: fixture[ 0 ].messages,
					name: fixture[ 0 ].name,
				},
			},
		];
		const fixtureContents = JSON.stringify( fixture );
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { fixture, rows } ),
					reportValidationOptions( {
						fixture,
						fixtureContents,
					} )
				),
			/missing 1 required step/
		);

		const reversedRows = [
			{
				browserConsoleErrors: [],
				outcome: "pass",
				runIndex: 1,
				stepIndex: 1,
				test: {
					expectedCall: [
						{ arguments: {}, functionName: "second_tool", result: { ok: true } },
					],
					messages: fixture[ 0 ].messages,
					name: fixture[ 0 ].name,
				},
			},
			{
				browserConsoleErrors: [],
				outcome: "pass",
				runIndex: 1,
				stepIndex: 2,
				test: {
					expectedCall: [
						{ arguments: {}, functionName: "first_tool", result: { ok: true } },
					],
					messages: fixture[ 0 ].messages,
					name: fixture[ 0 ].name,
				},
			},
		];
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { fixture, rows: reversedRows } ),
					reportValidationOptions( { fixture, fixtureContents } )
				),
			/expectedCall order does not match/
		);
	} );

	it( "rejects browser console errors even when every identified row passed", () => {
		const rows = reportRows();
		rows[ 1 ] = {
			...rows[ 1 ],
			browserConsoleErrors: [ { kind: "console", message: "boom" } ],
		};
		assert.throws(
			() =>
				validateWebmcpEvalReport(
					passingReport( { rows } ),
					reportValidationOptions()
				),
			/browser evaluation recorded 1/
		);
	} );

	it( "requires explicit common and mode-specific CLI provenance", () => {
		assert.deepEqual(
			parseReportCheckerArguments( [
				"--backend",
				"vercel",
				"--report",
				"report.json",
				"--fixture",
				"evals/storefront-selection.json",
				"--mode",
				"local",
				"--model",
				REPORT_MODEL,
				"--runs",
				"3",
				"--schema",
				"evals/schemas/storefront-tools.json",
			] ),
			{
				expectedBackend: "vercel",
				expectedMode: "local",
				expectedModel: REPORT_MODEL,
				expectedRuns: 3,
				expectedSchemaPath: "evals/schemas/storefront-tools.json",
				fixturePath: "evals/storefront-selection.json",
				reportPath: "report.json",
			}
		);
		assert.deepEqual(
			parseReportCheckerArguments( [
				"--backend",
				"vercel",
				"--report",
				"report.json",
				"--fixture",
				"evals/browser-journeys.json",
				"--mode",
				"browser",
				"--model",
				REPORT_MODEL,
				"--runs",
				"1",
				"--url",
				"http://localhost:18080/storefront-demo/",
				"--chrome-channel",
				"chrome",
			] ),
			{
				expectedBackend: "vercel",
				expectedChromeChannel: "chrome",
				expectedMode: "browser",
				expectedModel: REPORT_MODEL,
				expectedRuns: 1,
				expectedUrl: "http://localhost:18080/storefront-demo/",
				fixturePath: "evals/browser-journeys.json",
				reportPath: "report.json",
			}
		);
		assert.throws(
			() => parseReportCheckerArguments( [ "--report", "report.json" ] ),
			/Usage:/
		);
		assert.throws(
			() =>
				parseReportCheckerArguments( [
					"--backend",
					"vercel",
					"--report",
					"report.json",
					"--fixture",
					"fixture.json",
					"--mode",
					"local",
					"--model",
					REPORT_MODEL,
					"--runs",
					"0",
					"--schema",
					"schema.json",
				] ),
			/positive integer/
		);
		assert.throws(
			() =>
				parseReportCheckerArguments( [
					"--backend",
					"vercel",
					"--report",
					"report.json",
					"--fixture",
					"fixture.json",
					"--mode",
					"browser",
					"--model",
					REPORT_MODEL,
					"--runs",
					"1",
					"--url",
					"https://demo.example.invalid/",
					"--chrome-channel",
					"chrome",
				] ),
			/must use a loopback host/
		);
	} );
} );

describe( "WebMCP smoke runner safety", () => {
	it( "accepts loopback origins and builds only the two canonical surface runs", () => {
		const baseUrl = parseLocalWebmcpBaseUrl( "http://127.0.0.1:18080" );
		assert.equal( baseUrl.origin, "http://127.0.0.1:18080" );
		assert.deepEqual(
			buildSmokeRuns( baseUrl ).map( ( run ) => ( { name: run.name, url: run.url } ) ),
			[
				{ name: "storefront", url: "http://127.0.0.1:18080/storefront-demo/" },
				{ name: "agentops", url: "http://127.0.0.1:18080/agentops-demo/" },
			]
		);
	} );

	it( "rejects remote, credentialed, and path-bearing targets", () => {
		assert.throws(
			() => parseLocalWebmcpBaseUrl( "https://demo.example.invalid" ),
			/restricted to localhost/
		);
		assert.throws(
			() => parseLocalWebmcpBaseUrl( "http://user:secret@localhost:18080" ),
			/must not contain credentials/
		);
		assert.throws(
			() => parseLocalWebmcpBaseUrl( "http://localhost:18080/storefront-demo/" ),
			/origin without a path/
		);
	} );

	it( "fails closed when a keyless smoke process contains provider credentials", () => {
		assert.doesNotThrow( () =>
			assertNoProviderCredentials( {
				PATH: "/usr/bin",
				WMCP_BASE_URL: "http://localhost:18080",
			} )
		);
		assert.throws(
			() => assertNoProviderCredentials( { OPENAI_API_KEY: "openai-secret" } ),
			/refuses provider credentials: OPENAI_API_KEY/
		);
	} );

	it( "adapts the v0.0.4 ok:false smoke false-pass into a hard failure", async () => {
		const applicationFailure = {
			error: {
				code: "stale_cart_revision",
				message: "Read the latest cart before retrying.",
			},
			ok: false,
		};
		const upstreamResults = await runSmokeTest(
			{
				name: "Package false-pass contract",
				steps: [
					{
						arguments: {},
						functionName: "add_to_cart",
						stepIndex: 1,
					},
				],
				testIndex: 0,
			},
			{
				executeToolChecked: async () => ( {
					result: applicationFailure,
					success: true,
				} ),
				getCurrentTools: () => [ { functionName: "add_to_cart" } ],
			},
			1000,
			false
		);

		assert.equal( upstreamResults[ 0 ].outcome, "pass" );
		assert.deepEqual( upstreamResults[ 0 ].result, applicationFailure );
		assert.throws(
			() =>
				assertSuccessfulWebmcpSmoke(
					{
						errorCount: 0,
						passCount: 1,
						results: upstreamResults,
						testCount: 1,
						totalExpectedSteps: 1,
					},
					"storefront"
				),
			/returned ok:false \(stale_cart_revision:/
		);
	} );
} );
