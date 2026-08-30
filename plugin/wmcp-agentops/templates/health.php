<?php
/**
 * Public non-sensitive readiness report.
 *
 * @var array<string, mixed> $view
 * @package WPWebMCP\AgentOps
 */

defined('ABSPATH') || exit;
?>
<a class="wmcp-skip-link" href="#wmcp-health-main"><?php esc_html_e('Skip to readiness report', 'wmcp-agentops'); ?></a>
<div id="wmcp-health-main" class="wmcp-field wmcp-health alignfull" data-wmcp-surface="health" tabindex="-1">
	<header class="wmcp-masthead">
		<a class="wmcp-wordmark" href="<?php echo esc_url((string) $view['landing_url']); ?>" aria-label="<?php esc_attr_e('WebMCP Field Lab home', 'wmcp-agentops'); ?>"><span class="wmcp-wordmark-mark" aria-hidden="true">W//</span><span>WebMCP<br><em>Readiness</em></span></a>
		<div class="wmcp-masthead-meta"><span><?php esc_html_e('Compatibility sheet', 'wmcp-agentops'); ?></span><span><?php esc_html_e('Public / non-sensitive', 'wmcp-agentops'); ?></span></div>
		<div class="wmcp-status-chip" data-wmcp-status-chip data-state="checking"><span class="wmcp-status-light" aria-hidden="true"></span><span data-wmcp-status><?php esc_html_e('Running checks', 'wmcp-agentops'); ?></span></div>
	</header>

	<section class="wmcp-health-hero" aria-labelledby="wmcp-health-title">
		<div>
			<p class="wmcp-kicker"><?php esc_html_e('Before the field run', 'wmcp-agentops'); ?></p>
			<h1 id="wmcp-health-title"><?php esc_html_e('Know the browser.', 'wmcp-agentops'); ?><br><em><?php esc_html_e('Know the ground.', 'wmcp-agentops'); ?></em></h1>
			<p class="wmcp-deck"><?php esc_html_e('This report separates facts the server can prove from facts only the current browser can observe. It never exposes paths, secrets, plugin inventories, or database details.', 'wmcp-agentops'); ?></p>
		</div>
		<div class="wmcp-health-score" aria-live="polite">
			<span><?php esc_html_e('Readiness', 'wmcp-agentops'); ?></span>
			<strong data-wmcp-health-score>—</strong>
			<small data-wmcp-health-summary><?php esc_html_e('Checks in progress', 'wmcp-agentops'); ?></small>
		</div>
	</section>

	<section class="wmcp-health-grid" aria-labelledby="wmcp-browser-checks-title">
		<article class="wmcp-health-card">
			<div class="wmcp-panel-label"><span><?php esc_html_e('Browser facts', 'wmcp-agentops'); ?></span><span>CLIENT</span></div>
			<h2 id="wmcp-browser-checks-title"><?php esc_html_e('Current document', 'wmcp-agentops'); ?></h2>
			<ul class="wmcp-check-list wmcp-check-list-large" data-wmcp-browser-checks>
				<li data-check="secure_context"><span><strong><?php esc_html_e('Secure context', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('HTTPS or local development context', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="top_level"><span><strong><?php esc_html_e('Top-level document', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Required for ChatGPT site-tool discovery', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="webmcp_api"><span><strong><?php esc_html_e('WebMCP API', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('document.modelContext.registerTool', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="registration"><span><strong><?php esc_html_e('Tool registration', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Dynamic manifest accepted by this browser', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="origin_agent_cluster"><span><strong><?php esc_html_e('Origin isolation', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Expected response header observed when available', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
			</ul>
		</article>

		<article class="wmcp-health-card wmcp-health-card-dark">
			<div class="wmcp-panel-label"><span><?php esc_html_e('Server facts', 'wmcp-agentops'); ?></span><span>REST</span></div>
			<h2><?php esc_html_e('WordPress ground', 'wmcp-agentops'); ?></h2>
			<ul class="wmcp-check-list wmcp-check-list-large" data-wmcp-server-checks>
				<li data-check="database"><span><strong><?php esc_html_e('Workflow schema', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Version withheld until response', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="manifest"><span><strong><?php esc_html_e('Dynamic manifest', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Policy-filtered tool catalog', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="rest"><span><strong><?php esc_html_e('REST gateway', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Same-origin execution layer', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="woocommerce"><span><strong><?php esc_html_e('WooCommerce', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Commerce adapter availability', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="hpos"><span><strong><?php esc_html_e('HPOS mode', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('WooCommerce CRUD compatible', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="session"><span><strong><?php esc_html_e('Demo isolation', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Cookie-scoped browser state', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
				<li data-check="headers"><span><strong><?php esc_html_e('Security headers', 'wmcp-agentops'); ?></strong><small data-check-detail><?php esc_html_e('Permissions policy and origin isolation', 'wmcp-agentops'); ?></small></span><b data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></b></li>
			</ul>
		</article>
	</section>

	<section class="wmcp-health-note" aria-labelledby="wmcp-health-note-title">
		<div><p class="wmcp-kicker"><?php esc_html_e('Interpretation', 'wmcp-agentops'); ?></p><h2 id="wmcp-health-note-title"><?php esc_html_e('WebMCP is an enhancement, not a gate.', 'wmcp-agentops'); ?></h2></div>
		<div>
			<p><?php esc_html_e('If the WebMCP API is not detected, ordinary product, cart, checkout, and admin links still work. A supported top-level browser can additionally discover the structured tool catalog.', 'wmcp-agentops'); ?></p>
			<p class="wmcp-live-message" role="status" aria-live="polite" data-wmcp-announcer><?php esc_html_e('Fetching the public health response…', 'wmcp-agentops'); ?></p>
			<div class="wmcp-error-message" role="alert" data-wmcp-error hidden></div>
		</div>
	</section>

	<div class="wmcp-health-actions">
		<a class="wmcp-button wmcp-button-primary" href="<?php echo esc_url((string) $view['storefront_url']); ?>"><?php esc_html_e('Open storefront', 'wmcp-agentops'); ?> <span aria-hidden="true">↗</span></a>
		<a class="wmcp-button wmcp-button-quiet" href="<?php echo esc_url((string) $view['agentops_url']); ?>"><?php esc_html_e('Open AgentOps', 'wmcp-agentops'); ?></a>
		<button class="wmcp-button wmcp-button-quiet" type="button" data-wmcp-download-health><?php esc_html_e('Download public report', 'wmcp-agentops'); ?></button>
	</div>

	<footer class="wmcp-field-footer">
		<p><strong><?php esc_html_e('Disclosure boundary:', 'wmcp-agentops'); ?></strong> <?php esc_html_e('The downloadable report contains only the same public status shown here plus client capability booleans. It excludes server paths, secrets, database details, and full plugin lists.', 'wmcp-agentops'); ?></p>
		<a href="<?php echo esc_url((string) $view['landing_url']); ?>" class="wmcp-text-link"><?php esc_html_e('Return to field brief', 'wmcp-agentops'); ?> <span aria-hidden="true">←</span></a>
	</footer>
</div>
