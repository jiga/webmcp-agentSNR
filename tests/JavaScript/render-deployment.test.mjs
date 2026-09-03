import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { describe, it } from "node:test";

import { load as loadYaml } from "js-yaml";

const projectFile = ( path ) => new URL( `../../${ path }`, import.meta.url );
const readProjectFile = ( path ) => readFile( projectFile( path ), "utf8" );

const blueprintSource = await readProjectFile( "render.yaml" );
const blueprint = loadYaml( blueprintSource );
const webService = blueprint.services.find( ( service ) => service.type === "web" );
const databaseService = blueprint.services.find( ( service ) => service.type === "pserv" );

const environment = ( service ) =>
	Object.fromEntries( service.envVars.map( ( variable ) => [ variable.key, variable ] ) );

const requiredStartupEnvironment = [
	"WORDPRESS_DB_HOST",
	"WORDPRESS_DB_NAME",
	"WORDPRESS_DB_USER",
	"WORDPRESS_DB_PASSWORD",
	"WORDPRESS_AUTH_KEY",
	"WORDPRESS_SECURE_AUTH_KEY",
	"WORDPRESS_LOGGED_IN_KEY",
	"WORDPRESS_NONCE_KEY",
	"WORDPRESS_AUTH_SALT",
	"WORDPRESS_SECURE_AUTH_SALT",
	"WORDPRESS_LOGGED_IN_SALT",
	"WORDPRESS_NONCE_SALT",
	"WMCP_ADMIN_USER",
	"WMCP_ADMIN_PASSWORD",
	"WMCP_ADMIN_EMAIL",
	"WMCP_REPOSITORY_URL",
];

const validStartupEnvironment = Object.fromEntries(
	requiredStartupEnvironment.map( ( key ) => [ key, `test-value-for-${ key.toLowerCase() }` ] )
);
validStartupEnvironment.WORDPRESS_DB_HOST = "database.internal:3306";
validStartupEnvironment.WMCP_ADMIN_EMAIL = "admin@example.invalid";
validStartupEnvironment.WMCP_PUBLIC_URL = "https://demo.example.invalid";
validStartupEnvironment.WMCP_REPOSITORY_URL = "https://github.com/jiga/wp-webmcp";

const runEntrypointValidation = ( environmentOverrides = {}, omittedKey = null ) => {
	const childEnvironment = {
		PATH: process.env.PATH ?? "",
		...validStartupEnvironment,
		...environmentOverrides,
	};
	if ( omittedKey ) {
		delete childEnvironment[ omittedKey ];
	}

	return spawnSync(
		"bash",
		[ fileURLToPath( projectFile( "deploy/render/render-entrypoint.sh" ) ) ],
		{ encoding: "utf8", env: childEnvironment }
	);
};

describe( "Render deployment", () => {
	it( "uses two fixed, single-instance Docker services with previews and auto-deploys off", () => {
		assert.equal( blueprint.previews.generation, "off" );
		assert.equal( blueprint.services.length, 2 );

		for ( const service of blueprint.services ) {
			assert.equal( service.runtime, "docker" );
			assert.equal( service.region, "oregon" );
			assert.equal( service.plan, "1c-2g" );
			assert.equal( service.numInstances, 1 );
			assert.equal( service.autoDeployTrigger, "off" );
			assert.equal( service.dockerContext, "." );
			assert.equal( "repo" in service, false );
		}
	} );

	it( "persists only uploads on the web service and MariaDB data on the private service", () => {
		assert.deepEqual( webService.disk, {
			mountPath: "/var/www/html/wp-content/uploads",
			name: "agent-snr-uploads",
			sizeGB: 1,
		} );
		assert.deepEqual( databaseService.disk, {
			mountPath: "/var/lib/mysql",
			name: "agent-snr-mariadb-data",
			sizeGB: 10,
		} );
	} );

	it( "uses the Agent SNR health route and Apache port", () => {
		const webEnvironment = environment( webService );
		assert.equal( webService.healthCheckPath, "/wp-json/wmcp-agentops/v1/health" );
		assert.equal( webEnvironment.PORT.value, "80" );
		assert.equal( webEnvironment.WMCP_REPOSITORY_URL.value, "https://github.com/jiga/wp-webmcp" );
		assert.equal( webEnvironment.WMCP_RELEASE_URL, undefined );
		assert.equal( webService.dockerfilePath, "./deploy/render/Dockerfile" );
		assert.equal( databaseService.dockerfilePath, "./deploy/render/mariadb.Dockerfile" );
	} );

	it( "wires every WordPress database value from the private service", () => {
		const webEnvironment = environment( webService );
		const expectedReferences = {
			WORDPRESS_DB_HOST: { name: "agent-snr-mariadb", property: "hostport", type: "pserv" },
			WORDPRESS_DB_NAME: {
				envVarKey: "MARIADB_DATABASE",
				name: "agent-snr-mariadb",
				type: "pserv",
			},
			WORDPRESS_DB_PASSWORD: {
				envVarKey: "MARIADB_PASSWORD",
				name: "agent-snr-mariadb",
				type: "pserv",
			},
			WORDPRESS_DB_USER: {
				envVarKey: "MARIADB_USER",
				name: "agent-snr-mariadb",
				type: "pserv",
			},
		};

		for ( const [ key, reference ] of Object.entries( expectedReferences ) ) {
			assert.deepEqual( webEnvironment[ key ].fromService, reference );
		}
	} );

	it( "generates all passwords and the eight stable WordPress secrets", () => {
		const webEnvironment = environment( webService );
		const databaseEnvironment = environment( databaseService );
		const generatedWebSecrets = [
			"WMCP_ADMIN_PASSWORD",
			"WORDPRESS_AUTH_KEY",
			"WORDPRESS_SECURE_AUTH_KEY",
			"WORDPRESS_LOGGED_IN_KEY",
			"WORDPRESS_NONCE_KEY",
			"WORDPRESS_AUTH_SALT",
			"WORDPRESS_SECURE_AUTH_SALT",
			"WORDPRESS_LOGGED_IN_SALT",
			"WORDPRESS_NONCE_SALT",
		];

		for ( const key of generatedWebSecrets ) {
			assert.deepEqual( webEnvironment[ key ], { generateValue: true, key } );
		}
		assert.deepEqual( databaseEnvironment.MARIADB_PASSWORD, {
			generateValue: true,
			key: "MARIADB_PASSWORD",
		} );
		assert.deepEqual( databaseEnvironment.MARIADB_ROOT_PASSWORD, {
			generateValue: true,
			key: "MARIADB_ROOT_PASSWORD",
		} );
	} );

	it( "sets proxy-aware HTTPS, dynamic origins, demo gates, and immutable production settings", () => {
		const config = environment( webService ).WORDPRESS_CONFIG_EXTRA.value;
		const requiredFragments = [
			"HTTP_X_FORWARDED_PROTO",
			"WMCP_PUBLIC_URL",
			"RENDER_EXTERNAL_URL",
			"define('WP_HOME', $wmcp_public_url)",
			"define('WP_SITEURL', $wmcp_public_url)",
			"define('FORCE_SSL_ADMIN', true)",
			"define('WP_ENVIRONMENT_TYPE', 'production')",
			"define('WMCP_AGENTOPS_DEMO_MODE', true)",
			"define('DISALLOW_FILE_EDIT', true)",
			"define('DISALLOW_FILE_MODS', true)",
			"define('AUTOMATIC_UPDATER_DISABLED', true)",
			"define('WP_AUTO_UPDATE_CORE', false)",
			"define('WP_MEMORY_LIMIT', '256M')",
			"define('WP_MAX_MEMORY_LIMIT', '512M')",
		];

		for ( const fragment of requiredFragments ) {
			assert.match( config, new RegExp( fragment.replace( /[.*+?^${}()|[\]\\]/g, "\\$&" ) ) );
		}
	} );

	it( "pins every base image and verifies the official WooCommerce archive before extraction", async () => {
		const dockerfile = await readProjectFile( "deploy/render/Dockerfile" );
		const databaseDockerfile = await readProjectFile( "deploy/render/mariadb.Dockerfile" );
		const digestLines = [
			"wordpress:7.1-php8.3-apache@sha256:5a93c470ae8220fddf71f6ebe3bc94e615ddc2ae4d9810f795b830fb11c41a17",
			"wordpress:cli-2.12-php8.3@sha256:2b5e9d4d3e51909dca1aaa4732e9f5e5bf0377c2114dbd8ff39f060bff202586",
		];

		for ( const image of digestLines ) {
			assert.match( dockerfile, new RegExp( image.replace( /[.*+?^${}()|[\]\\]/g, "\\$&" ) ) );
		}
		assert.match(
			databaseDockerfile,
			/mariadb:11\.8@sha256:2439dcd7d14010ecd1ff7a4e1c5abe8e208c34fe35290744deeeaac3569043c3/
		);
		assert.match( dockerfile, /https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\$\{WOOCOMMERCE_VERSION\}\.zip/ );
		assert.match( dockerfile, /curl --fail --location --silent --show-error --proto '=https' --tlsv1\.2 --retry 3/ );
		assert.match( dockerfile, /da189b6616c610d15a2106f93151dab81b78f83e075bcefce221ac0d00b4fa21/ );
		assert.ok( dockerfile.indexOf( "sha256sum --check --strict" ) < dockerfile.indexOf( "unzip -q" ) );
		assert.match( dockerfile, /ARG RENDER_GIT_COMMIT=unknown/ );
		assert.match( dockerfile, /org\.opencontainers\.image\.revision="\$\{RENDER_GIT_COMMIT\}"/ );
		assert.match(
			dockerfile,
			/COPY --chown=www-data:www-data LICENSE THIRD_PARTY_NOTICES\.md \/usr\/src\/wordpress\/wp-content\/plugins\/wmcp-agentops\//
		);
	} );

	it( "declares bounded, idempotent reconciliation as the web user before official Apache", async () => {
		const entrypoint = await readProjectFile( "deploy/render/render-entrypoint.sh" );
		const requiredFragments = [
			"docker-ensure-installed.sh true",
			"runuser -u www-data",
			"DB_WAIT_ATTEMPTS=90",
			"if ! wp_cli core is-installed",
			"WMCP_PUBLIC_URL:-${RENDER_EXTERNAL_URL:-}",
			"WMCP_REPOSITORY_URL",
			"wp_cli option update home",
			"wp_cli option update siteurl",
			"wp_cli option update wmcp_agentops_repository_url",
			"wp_cli plugin activate woocommerce",
			"wp_cli plugin activate wmcp-agentops",
			"wp_cli eval-file /opt/agent-snr/bin/seed-demo.php",
			"wp_cli option update woocommerce_coming_soon no",
			"wp_cli rewrite structure '/%postname%/' --hard",
			"verify_version \"WordPress\"",
			"verify_version \"WooCommerce\"",
			"verify_version \"Agent SNR\"",
			"exec /usr/local/bin/docker-entrypoint.sh \"$@\"",
		];

		for ( const fragment of requiredFragments ) {
			assert.ok( entrypoint.includes( fragment ), `missing startup step: ${ fragment }` );
		}
		assert.match( entrypoint, /--title="Agent SNR Demo Store"/ );
		assert.match( entrypoint, /attempt == DB_WAIT_ATTEMPTS/ );
		assert.match( entrypoint, /MariaDB was not ready before the bounded startup deadline/ );
		assert.equal( entrypoint.includes( "wmcp_agentops_release_url" ), false );
	} );

	it( "fails before touching WordPress when any required setting is absent", () => {
		for ( const key of requiredStartupEnvironment ) {
			const result = runEntrypointValidation( {}, key );
			assert.equal( result.status, 1, `missing ${ key } should fail` );
			assert.match( result.stderr, new RegExp( `Required Render setting is missing: ${ key }` ) );
		}
	} );

	it( "rejects non-origin public URLs and malformed repository URLs", () => {
		for ( const publicUrl of [
			"http://demo.example.invalid",
			"https://demo.example.invalid/path",
			"https://user@demo.example.invalid",
		] ) {
			const result = runEntrypointValidation( { WMCP_PUBLIC_URL: publicUrl } );
			assert.equal( result.status, 1 );
			assert.match( result.stderr, /must be an HTTPS origin without a path/ );
		}

		for ( const repositoryUrl of [
			"http://github.com/jiga/wp-webmcp",
			"https://github.com/jiga",
			"https://github.com/jiga/wp-webmcp/",
			"https://github.com/jiga/wp-webmcp?tab=readme",
			"https://user@github.com/jiga/wp-webmcp",
		] ) {
			const result = runEntrypointValidation( { WMCP_REPOSITORY_URL: repositoryUrl } );
			assert.equal( result.status, 1 );
			assert.match( result.stderr, /must be an HTTPS GitHub repository URL/ );
		}
	} );

	it( "keeps build context on an explicit allowlist", async () => {
		const dockerignore = await readProjectFile( ".dockerignore" );
		assert.equal( dockerignore.split( "\n" )[ 0 ], "*" );
		const allowed = [
			"!.dockerignore",
			"!LICENSE",
			"!THIRD_PARTY_NOTICES.md",
			"!bin/",
			"!bin/seed-demo.php",
			"!deploy/",
			"!deploy/render/",
			"!deploy/render/**",
			"!plugin/",
			"!plugin/wmcp-agentops/",
			"!plugin/wmcp-agentops/**",
		];
		assert.deepEqual(
			dockerignore.split( "\n" ).filter( ( line ) => line.startsWith( "!" ) ),
			allowed
		);
		for ( const excluded of [
			"plugin/wmcp-agentops/node_modules",
			"plugin/wmcp-agentops/tests",
			"plugin/wmcp-agentops/vendor",
			"plugin/wmcp-agentops/**/.env*",
			"plugin/wmcp-agentops/**/*.log",
			"plugin/wmcp-agentops/**/*.map",
			"plugin/wmcp-agentops/**/*.zip",
		] ) {
			assert.match( dockerignore, new RegExp( `^${ excluded.replace( /[.*+?^${}()|[\]\\]/g, "\\$&" ) }$`, "m" ) );
		}
	} );

	it( "contains no development credentials, debug mode, or enabled destructive reset", async () => {
		const deploymentSources = await Promise.all( [
			readProjectFile( "render.yaml" ),
			readProjectFile( "deploy/render/Dockerfile" ),
			readProjectFile( "deploy/render/mariadb.Dockerfile" ),
			readProjectFile( "deploy/render/render-entrypoint.sh" ),
		] );
		const combined = deploymentSources.join( "\n" );

		for ( const forbidden of [
			"local-demo-password",
			"local-demo-root-password",
			"local-demo-admin-password",
			"WORDPRESS_DEBUG",
			"WMCP_AGENTOPS_ALLOW_DESTRUCTIVE_RESET', true",
		] ) {
			assert.equal( combined.includes( forbidden ), false, `forbidden deployment value: ${ forbidden }` );
		}
	} );
} );
