import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
	assertShowcaseOrigin,
	CAPTURE_OUTPUT_MARKER,
	CAPTURE_OUTPUT_MARKER_CONTENT,
	loadShowcaseAdminCredentials,
	resolveShowcaseBaseUrl,
	sanitizeShowcaseConsoleLocation,
	validateCaptureOutputDirectory,
} from "../../demo/showcase-config.mjs";

describe( "showcase capture configuration", () => {
	it( "defaults to the isolated localhost showcase origin", () => {
		assert.deepEqual( resolveShowcaseBaseUrl( {} ), {
			baseUrl: "http://localhost:18084",
			isLocal: true,
		} );
	} );

	it( "rejects a base URL with a path", () => {
		assert.throws(
			() => resolveShowcaseBaseUrl( { WMCP_BASE_URL: "http://localhost:18084/storefront-demo/" } ),
			/origin without a path/
		);
	} );

	it( "rejects a remote host by default", () => {
		assert.throws(
			() => resolveShowcaseBaseUrl( { WMCP_BASE_URL: "https://demo.example.invalid" } ),
			/Remote showcase capture is disabled/
		);
	} );

	it( "requires explicit credentials even after remote capture is enabled", () => {
		assert.throws(
			() =>
				resolveShowcaseBaseUrl( {
					WMCP_ALLOW_REMOTE_SHOWCASE: "1",
					WMCP_BASE_URL: "https://demo.example.invalid",
				} ),
			/requires explicit WMCP_ADMIN_USER/
		);
	} );

	it( "accepts an explicitly authorized remote origin with explicit credentials", () => {
		assert.deepEqual(
			resolveShowcaseBaseUrl( {
				WMCP_ADMIN_PASSWORD: "secret",
				WMCP_ADMIN_USER: "operator",
				WMCP_ALLOW_REMOTE_SHOWCASE: "1",
				WMCP_BASE_URL: "https://demo.example.invalid",
			} ),
			{ baseUrl: "https://demo.example.invalid", isLocal: false }
		);
	} );

	it( "rejects remote HTTP even when remote capture is explicitly enabled", () => {
		assert.throws(
			() =>
				resolveShowcaseBaseUrl( {
					WMCP_ADMIN_PASSWORD: "secret",
					WMCP_ADMIN_USER: "operator",
					WMCP_ALLOW_REMOTE_SHOWCASE: "1",
					WMCP_BASE_URL: "http://demo.example.invalid",
				} ),
			/requires HTTPS/
		);
	} );

	it( "accepts a same-origin admin destination", () => {
		const url = assertShowcaseOrigin(
			"https://demo.example.invalid/wp-admin/",
			"https://demo.example.invalid",
			"Admin login"
		);
		assert.equal( url.pathname, "/wp-admin/" );
	} );

	it( "rejects a cross-origin admin redirect", () => {
		assert.throws(
			() =>
				assertShowcaseOrigin(
					"https://attacker.example.invalid/wp-login.php",
					"https://demo.example.invalid",
					"Admin login"
				),
			/left the configured showcase origin/
		);
	} );

	it( "removes query parameters from console error locations", () => {
		assert.equal(
			sanitizeShowcaseConsoleLocation(
				"https://demo.example.invalid/checkout/order-received/42/?key=secret",
				"https://demo.example.invalid"
			),
			"/checkout/order-received/42/"
		);
	} );

	it( "uses an explicit credential pair", async () => {
		const credentials = await loadShowcaseAdminCredentials( {
			environment: {
				WMCP_ADMIN_PASSWORD: "secret",
				WMCP_ADMIN_USER: "operator",
			},
		} );
		assert.deepEqual( credentials, { password: "secret", user: "operator" } );
	} );

	it( "reads the protected launcher credential file", async () => {
		const credentials = await loadShowcaseAdminCredentials( {
			environment: {},
			readFile: async () => "user=showcase-user\npassword=showcase-password\n",
		} );
		assert.deepEqual( credentials, {
			password: "showcase-password",
			user: "showcase-user",
		} );
	} );

	it( "fails closed when no credential source exists", async () => {
		const missingFile = new Error( "missing" );
		missingFile.code = "ENOENT";
		await assert.rejects(
			loadShowcaseAdminCredentials( {
				environment: {},
				readFile: async () => {
					throw missingFile;
				},
			} ),
			/Showcase operator credentials were not found/
		);
	} );

	it( "rejects broad and existing unmarked capture output directories", async () => {
		await assert.rejects(
			validateCaptureOutputDirectory( "/workspace", {
				homeDirectory: "/home/operator",
				workspaceDirectory: "/workspace/project",
			} ),
			/dedicated generated subdirectory/
		);
		await assert.rejects(
			validateCaptureOutputDirectory( "/workspace/project/submission", {
				homeDirectory: "/home/operator",
				lstat: async () => ( {
					isDirectory: () => true,
					isSymbolicLink: () => false,
				} ),
				readFile: async ( file ) => {
					assert.equal( file, `/workspace/project/submission/${ CAPTURE_OUTPUT_MARKER }` );
					const error = new Error( "missing" );
					error.code = "ENOENT";
					throw error;
				},
				workspaceDirectory: "/workspace/project",
			} ),
			/existing WMCP_SHOWCASE_OUTPUT directory/
		);
		assert.equal( CAPTURE_OUTPUT_MARKER_CONTENT, "agent-snr-capture-output-v1\n" );
	} );
} );
