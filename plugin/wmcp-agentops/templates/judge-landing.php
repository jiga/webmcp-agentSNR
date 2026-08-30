<?php
/**
 * Judge landing surface.
 *
 * @var array<string, mixed> $view
 * @package WPWebMCP\AgentOps
 */

defined('ABSPATH') || exit;

$shopper_prompt = 'Find a waterproof backpack under $120, compare the two best choices, confirm that the return policy is at least 30 days, and add the best-value option to my cart.';
$merchant_prompt = 'Monitor my current agent session, replay its tool and commerce timeline, identify the slowest or failed invocation, connect it to the commerce outcome, show any capability signals this store does not support, and summarize current controls.';
?>
<a class="wmcp-skip-link" href="#wmcp-field-main"><?php esc_html_e('Skip to field report', 'wmcp-agentops'); ?></a>
<div id="wmcp-field-main" class="wmcp-field wmcp-landing alignfull" data-wmcp-surface="landing" tabindex="-1">
	<header class="wmcp-masthead">
		<a class="wmcp-wordmark" href="<?php echo esc_url((string) $view['landing_url']); ?>" aria-label="<?php esc_attr_e('WebMCP Field Lab home', 'wmcp-agentops'); ?>">
			<span class="wmcp-wordmark-mark" aria-hidden="true">W//</span>
			<span>WebMCP<br><em>Field Lab</em></span>
		</a>
		<div class="wmcp-masthead-meta">
			<span><?php esc_html_e('Report 01', 'wmcp-agentops'); ?></span>
			<span><?php esc_html_e('Store → agent → operator', 'wmcp-agentops'); ?></span>
		</div>
		<div class="wmcp-status-chip" data-wmcp-status-chip data-state="checking">
			<span class="wmcp-status-light" aria-hidden="true"></span>
			<span data-wmcp-status><?php esc_html_e('Checking WebMCP', 'wmcp-agentops'); ?></span>
		</div>
	</header>

	<section class="wmcp-hero" aria-labelledby="wmcp-landing-title">
		<div class="wmcp-hero-copy">
			<p class="wmcp-kicker"><?php esc_html_e('Closed-loop commerce telemetry', 'wmcp-agentops'); ?></p>
			<h1 id="wmcp-landing-title"><?php esc_html_e('The storefront speaks.', 'wmcp-agentops'); ?><br><em><?php esc_html_e('The operator sees what happened.', 'wmcp-agentops'); ?></em></h1>
			<p class="wmcp-deck"><?php esc_html_e('A WordPress field layer where browser agents can use a real shop, recorded tool and commerce steps become inspectable evidence, and the merchant can govern what happens next.', 'wmcp-agentops'); ?></p>
			<div class="wmcp-actions">
				<a class="wmcp-button wmcp-button-primary" href="<?php echo esc_url((string) $view['storefront_url']); ?>"><?php esc_html_e('Open storefront', 'wmcp-agentops'); ?> <span aria-hidden="true">↗</span></a>
				<a class="wmcp-button wmcp-button-quiet" href="<?php echo esc_url((string) $view['agentops_url']); ?>"><?php esc_html_e('Open Agent Monitor', 'wmcp-agentops'); ?></a>
			</div>
		</div>

		<aside class="wmcp-readiness-card" aria-labelledby="wmcp-readiness-title">
			<div class="wmcp-panel-label"><span><?php esc_html_e('Live inspection', 'wmcp-agentops'); ?></span><span>01—07</span></div>
			<h2 id="wmcp-readiness-title"><?php esc_html_e('Ready for a field run?', 'wmcp-agentops'); ?></h2>
			<ul class="wmcp-check-list" data-wmcp-readiness-list>
				<li data-check="secure_context"><span><?php esc_html_e('Secure context', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="top_level"><span><?php esc_html_e('Top-level document', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="webmcp_api"><span><?php esc_html_e('WebMCP API', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="manifest"><span><?php esc_html_e('Storefront manifest', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="woocommerce"><span><?php esc_html_e('WooCommerce', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="session"><span><?php esc_html_e('Demo session', 'wmcp-agentops'); ?></span><strong data-check-status><?php echo ! empty($view['demo_mode']) ? esc_html__('Isolated', 'wmcp-agentops') : esc_html__('Production', 'wmcp-agentops'); ?></strong></li>
				<li data-check="database"><span><?php esc_html_e('Workflow ledger', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
				<li data-check="attribution"><span><?php esc_html_e('Order attribution', 'wmcp-agentops'); ?></span><strong data-check-status><?php esc_html_e('Checking', 'wmcp-agentops'); ?></strong></li>
			</ul>
			<p class="wmcp-live-message" role="status" aria-live="polite" data-wmcp-announcer><?php esc_html_e('Running browser and server checks…', 'wmcp-agentops'); ?></p>
			<a class="wmcp-text-link" href="<?php echo esc_url((string) $view['health_url']); ?>"><?php esc_html_e('Open complete readiness report', 'wmcp-agentops'); ?> <span aria-hidden="true">→</span></a>
		</aside>
	</section>

	<section class="wmcp-loop" aria-labelledby="wmcp-loop-title">
		<div class="wmcp-section-heading">
			<p class="wmcp-kicker"><?php esc_html_e('One signal, one evidence trail', 'wmcp-agentops'); ?></p>
			<h2 id="wmcp-loop-title"><?php esc_html_e('A closed loop instead of a black box.', 'wmcp-agentops'); ?></h2>
		</div>
		<ol class="wmcp-loop-rail">
			<li><span class="wmcp-loop-index">01</span><strong><?php esc_html_e('Signal', 'wmcp-agentops'); ?></strong><p><?php esc_html_e('A shopper describes a goal in plain language.', 'wmcp-agentops'); ?></p></li>
			<li><span class="wmcp-loop-index">02</span><strong><?php esc_html_e('Structured action', 'wmcp-agentops'); ?></strong><p><?php esc_html_e('WebMCP tools search, compare, cite policy, and prepare the cart.', 'wmcp-agentops'); ?></p></li>
			<li><span class="wmcp-loop-index">03</span><strong><?php esc_html_e('Proof', 'wmcp-agentops'); ?></strong><p><?php esc_html_e('The workflow ledger ties tool calls to checkout and revenue outcomes.', 'wmcp-agentops'); ?></p></li>
			<li><span class="wmcp-loop-index">04</span><strong><?php esc_html_e('Control', 'wmcp-agentops'); ?></strong><p><?php esc_html_e('The operator finds gaps and changes the next agent workflow safely.', 'wmcp-agentops'); ?></p></li>
		</ol>
	</section>

	<section class="wmcp-prompts" aria-labelledby="wmcp-prompts-title">
		<div class="wmcp-section-heading wmcp-section-heading-narrow">
			<p class="wmcp-kicker"><?php esc_html_e('Two runs / one session', 'wmcp-agentops'); ?></p>
			<h2 id="wmcp-prompts-title"><?php esc_html_e('Use the supplied field prompts.', 'wmcp-agentops'); ?></h2>
		</div>
		<article class="wmcp-prompt-card wmcp-prompt-shopper">
			<div class="wmcp-panel-label"><span><?php esc_html_e('Shopper run', 'wmcp-agentops'); ?></span><span>Prompt A</span></div>
			<blockquote data-copy-source="shopper"><?php echo esc_html($shopper_prompt); ?></blockquote>
			<button class="wmcp-copy-button" type="button" data-copy-target="shopper"><span data-copy-label><?php esc_html_e('Copy shopper prompt', 'wmcp-agentops'); ?></span><span aria-hidden="true">⌁</span></button>
		</article>
		<article class="wmcp-prompt-card wmcp-prompt-merchant">
			<div class="wmcp-panel-label"><span><?php esc_html_e('Operator run', 'wmcp-agentops'); ?></span><span>Prompt B</span></div>
			<blockquote data-copy-source="merchant"><?php echo esc_html($merchant_prompt); ?></blockquote>
			<button class="wmcp-copy-button" type="button" data-copy-target="merchant"><span data-copy-label><?php esc_html_e('Copy operator prompt', 'wmcp-agentops'); ?></span><span aria-hidden="true">⌁</span></button>
		</article>
	</section>

	<section class="wmcp-access" aria-labelledby="wmcp-access-title">
		<div>
			<p class="wmcp-kicker"><?php esc_html_e('Reproduce the evidence', 'wmcp-agentops'); ?></p>
			<h2 id="wmcp-access-title"><?php esc_html_e('Run here, inspect elsewhere.', 'wmcp-agentops'); ?></h2>
			<p><?php esc_html_e('Every judge gets a cookie-scoped demo session. A reset rotates only this browser’s scope; it does not erase another judge’s work.', 'wmcp-agentops'); ?></p>
		</div>
		<div class="wmcp-access-grid">
			<button class="wmcp-action-tile" type="button" data-wmcp-reset data-reset-surface="storefront" <?php disabled(empty($view['demo_mode'])); ?>>
				<span class="wmcp-action-number">01</span><strong><?php esc_html_e('Start fresh demo', 'wmcp-agentops'); ?></strong><small><?php echo ! empty($view['demo_mode']) ? esc_html__('Rotate this browser session', 'wmcp-agentops') : esc_html__('Available only in demo mode', 'wmcp-agentops'); ?></small>
			</button>
			<?php if (! empty($view['playground_url'])) : ?>
				<a class="wmcp-action-tile" href="<?php echo esc_url((string) $view['playground_url']); ?>"><span class="wmcp-action-number">02</span><strong><?php esc_html_e('WordPress Playground', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Open a disposable reproduction', 'wmcp-agentops'); ?></small></a>
			<?php else : ?>
				<div class="wmcp-action-tile wmcp-owner-action"><span class="wmcp-action-number">02</span><strong><?php esc_html_e('WordPress Playground', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Owner action: attach tested bundle URL', 'wmcp-agentops'); ?></small></div>
			<?php endif; ?>
			<?php if (! empty($view['repository_url'])) : ?>
				<a class="wmcp-action-tile" href="<?php echo esc_url((string) $view['repository_url']); ?>"><span class="wmcp-action-number">03</span><strong><?php esc_html_e('Public repository', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Inspect the GPL source', 'wmcp-agentops'); ?></small></a>
			<?php else : ?>
				<div class="wmcp-action-tile wmcp-owner-action"><span class="wmcp-action-number">03</span><strong><?php esc_html_e('Public repository', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Owner action: publish and attach URL', 'wmcp-agentops'); ?></small></div>
			<?php endif; ?>
			<?php if (! empty($view['release_url'])) : ?>
				<a class="wmcp-action-tile" href="<?php echo esc_url((string) $view['release_url']); ?>"><span class="wmcp-action-number">04</span><strong><?php esc_html_e('Installable release', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Download the verified plugin ZIP', 'wmcp-agentops'); ?></small></a>
			<?php else : ?>
				<div class="wmcp-action-tile wmcp-owner-action"><span class="wmcp-action-number">04</span><strong><?php esc_html_e('Installable release', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Owner action: attach tagged release URL', 'wmcp-agentops'); ?></small></div>
			<?php endif; ?>
		</div>
		<p class="wmcp-reset-feedback" role="status" aria-live="polite" data-wmcp-reset-feedback></p>
	</section>

	<footer class="wmcp-field-footer">
		<p><strong><?php esc_html_e('Privacy boundary:', 'wmcp-agentops'); ?></strong> <?php esc_html_e('This public board shows only the current demo session. It records redacted operational events, never raw prompts, customer addresses, or payment details.', 'wmcp-agentops'); ?></p>
		<p class="wmcp-mono"><?php echo esc_html((string) $view['site_name']); ?> · GPL-2.0-or-later · <?php esc_html_e('WebMCP progressive enhancement', 'wmcp-agentops'); ?></p>
	</footer>
</div>
