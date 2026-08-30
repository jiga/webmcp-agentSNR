<?php
/**
 * Public session-scoped and authenticated Agent SNR shell.
 *
 * @var array<string, mixed> $view
 * @package WPWebMCP\AgentOps
 */

defined('ABSPATH') || exit;

$is_admin = ! empty($view['is_admin']);
$tools    = isset($view['tools']) && is_array($view['tools']) ? $view['tools'] : array();
$governance = isset($view['governance']) && is_array($view['governance']) ? $view['governance'] : array();
$dashboard_available = ! $is_admin || ! empty($view['demo_mode']);
?>
<?php if (! $is_admin) : ?>
	<a class="wmcp-skip-link" href="#wmcp-agentops-main"><?php esc_html_e('Skip to Agent SNR', 'wmcp-agentops'); ?></a>
	<div id="wmcp-agentops-main" class="wmcp-field wmcp-agentops alignfull" data-wmcp-surface="agentops" tabindex="-1">
<?php else : ?>
	<div id="wmcp-agentops-main" class="wrap wmcp-field wmcp-agentops wmcp-admin-wrap" data-wmcp-surface="agentops">
		<?php if (! empty($view['settings_saved'])) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e('WebMCP policy settings saved.', 'wmcp-agentops'); ?></p></div>
		<?php endif; ?>
<?php endif; ?>
	<header class="wmcp-ops-header">
		<div>
			<p class="wmcp-kicker"><?php echo $is_admin ? esc_html__('Authenticated agent operations', 'wmcp-agentops') : esc_html__('Current browser evidence / redacted', 'wmcp-agentops'); ?></p>
			<h1><?php esc_html_e('Agent SNR', 'wmcp-agentops'); ?></h1>
			<p><?php echo $is_admin
				? esc_html__('Agent outcome monitoring for WordPress: inspect workflows, verified commerce outcomes, and server-authoritative policies.', 'wmcp-agentops')
				: esc_html__('Agent outcome monitoring for WordPress: trace a shopping journey from first tool call to human checkpoint and verified commerce outcome.', 'wmcp-agentops'); ?></p>
		</div>
		<div class="wmcp-ops-header-actions">
			<div class="wmcp-status-chip" <?php if ($dashboard_available) : ?>data-wmcp-status-chip data-state="checking"<?php else : ?>data-state="passed"<?php endif; ?>><span class="wmcp-status-light" aria-hidden="true"></span><span <?php if ($dashboard_available) : ?>data-wmcp-status<?php endif; ?>><?php echo $dashboard_available ? esc_html__('Checking agent tools', 'wmcp-agentops') : esc_html__('Authenticated policy shell', 'wmcp-agentops'); ?></span></div>
			<a class="wmcp-button wmcp-button-quiet" href="<?php echo esc_url((string) $view['storefront_url']); ?>"><?php esc_html_e('Open storefront', 'wmcp-agentops'); ?> <span aria-hidden="true">↗</span></a>
		</div>
	</header>

	<nav class="wmcp-board-nav" aria-label="<?php esc_attr_e('Agent SNR sections', 'wmcp-agentops'); ?>">
		<a href="#wmcp-overview"><?php esc_html_e('Monitor', 'wmcp-agentops'); ?></a>
		<a href="#wmcp-workflows"><?php esc_html_e('Agent Sessions', 'wmcp-agentops'); ?></a>
		<a href="#wmcp-funnel"><?php esc_html_e('Journey', 'wmcp-agentops'); ?></a>
		<a href="#wmcp-tools"><?php esc_html_e('Tools', 'wmcp-agentops'); ?></a>
		<a href="#wmcp-gaps"><?php esc_html_e('Signals', 'wmcp-agentops'); ?></a>
		<a href="#wmcp-governance"><?php esc_html_e('Controls', 'wmcp-agentops'); ?></a>
	</nav>

	<section class="wmcp-board-command" aria-labelledby="wmcp-board-command-title">
		<div>
			<span class="wmcp-panel-label"><span><?php esc_html_e('Monitor scope', 'wmcp-agentops'); ?></span><span>SNAPSHOT</span></span>
			<h2 id="wmcp-board-command-title"><?php esc_html_e('Load the current evidence window.', 'wmcp-agentops'); ?></h2>
			<p><?php echo $is_admin
				? esc_html__('The authenticated shell exposes persistent controls. In demo mode, browser analytics remain scoped to this session.', 'wmcp-agentops')
				: esc_html__('Only this browser’s storefront workflows can appear here. Sample history is never silently mixed into the monitor.', 'wmcp-agentops'); ?></p>
			<?php if (! $dashboard_available) : ?><p class="wmcp-admin-scope-note"><?php esc_html_e('Site-wide authenticated analytics execution is not connected in production mode in this build. Persistent policy controls below remain available.', 'wmcp-agentops'); ?></p><?php endif; ?>
		</div>
		<div class="wmcp-board-command-actions">
			<button class="wmcp-button wmcp-button-primary" type="button" data-wmcp-load-dashboard <?php disabled(! $dashboard_available); ?>><?php echo $dashboard_available ? esc_html__('Load monitor', 'wmcp-agentops') : esc_html__('Analytics loader unavailable', 'wmcp-agentops'); ?> <span aria-hidden="true">↻</span></button>
			<button class="wmcp-button wmcp-button-quiet" type="button" data-wmcp-reset data-reset-surface="agentops" <?php disabled(empty($view['demo_mode'])); ?>><?php esc_html_e('Start fresh session', 'wmcp-agentops'); ?></button>
		</div>
		<p class="wmcp-live-message" role="status" aria-live="polite" data-wmcp-announcer><?php esc_html_e('Monitor ready. Load this scope or run the merchant prompt through your browser agent.', 'wmcp-agentops'); ?></p>
		<p class="wmcp-reset-feedback" role="status" aria-live="polite" data-wmcp-reset-feedback></p>
		<div class="wmcp-error-message" role="alert" data-wmcp-error hidden></div>
	</section>

	<section id="wmcp-overview" class="wmcp-board-section" aria-labelledby="wmcp-overview-title">
		<div class="wmcp-board-section-head">
			<div><p class="wmcp-kicker"><?php esc_html_e('01 / Operating picture', 'wmcp-agentops'); ?></p><h2 id="wmcp-overview-title"><?php esc_html_e('Monitor', 'wmcp-agentops'); ?></h2></div>
			<div class="wmcp-scope-stamp"><span><?php echo $is_admin ? esc_html__('Authenticated shell', 'wmcp-agentops') : esc_html__('Demo-session only', 'wmcp-agentops'); ?></span><strong class="wmcp-mono" data-wmcp-workflow>—</strong></div>
		</div>
		<div class="wmcp-agent-journey" aria-labelledby="wmcp-agent-journey-title">
			<div class="wmcp-agent-journey-head">
				<p class="wmcp-kicker"><?php esc_html_e('The monitored path', 'wmcp-agentops'); ?></p>
				<h3 id="wmcp-agent-journey-title"><?php esc_html_e('Agent journey model', 'wmcp-agentops'); ?></h3>
			</div>
			<ol>
				<li><span>01</span><strong><?php esc_html_e('Visitor session', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('One redacted browser scope', 'wmcp-agentops'); ?></small></li>
				<li><span>02</span><strong><?php esc_html_e('Agent workflow', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('A goal-directed run', 'wmcp-agentops'); ?></small></li>
				<li><span>03</span><strong><?php esc_html_e('Tool invocation', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Allowed call and result', 'wmcp-agentops'); ?></small></li>
				<li><span>04</span><strong><?php esc_html_e('Human checkpoint', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Checkout stays with the person', 'wmcp-agentops'); ?></small></li>
				<li><span>05</span><strong><?php esc_html_e('Verified outcome', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Recorded commerce evidence', 'wmcp-agentops'); ?></small></li>
			</ol>
		</div>
		<div class="wmcp-metric-grid" data-wmcp-overview>
			<article class="wmcp-metric wmcp-metric-lead"><span><?php esc_html_e('Agent workflows', 'wmcp-agentops'); ?></span><strong data-metric="workflows.total">—</strong><small><span data-metric="workflows.completed">—</span> <?php esc_html_e('completed', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Tool calls', 'wmcp-agentops'); ?></span><strong data-metric="tool_calls.total">—</strong><small><span data-metric="tool_calls.success_rate">—</span> <?php esc_html_e('ok', 'wmcp-agentops'); ?> · <span data-metric="tool_calls.failure_rate">—</span> <?php esc_html_e('fail', 'wmcp-agentops'); ?> · <span data-metric="tool_calls.denial_rate">—</span> <?php esc_html_e('denied', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('p95 latency', 'wmcp-agentops'); ?></span><strong><span data-metric="tool_calls.p95_duration_ms">—</span><i>ms</i></strong><small><span data-metric="tool_calls.p50_duration_ms">—</span> <?php esc_html_e('ms p50', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Product searches', 'wmcp-agentops'); ?></span><strong data-metric="commerce.product_searches">—</strong><small><?php esc_html_e('catalog signals', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Cart mutations', 'wmcp-agentops'); ?></span><strong data-metric="commerce.cart_mutations">—</strong><small><?php esc_html_e('reversible writes', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Checkout handoffs', 'wmcp-agentops'); ?></span><strong data-metric="commerce.checkout_handoffs">—</strong><small><?php esc_html_e('human checkpoints', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Paid orders', 'wmcp-agentops'); ?></span><strong data-metric="commerce.orders_paid">—</strong><small><?php esc_html_e('same-session outcomes', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric wmcp-metric-revenue"><span><?php esc_html_e('Net attributed', 'wmcp-agentops'); ?></span><strong data-metric="revenue.net">—</strong><small><span data-metric="revenue.orders">—</span> <?php esc_html_e('orders', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Refund value', 'wmcp-agentops'); ?></span><strong data-metric="revenue.refunds">—</strong><small><?php esc_html_e('attributed by currency', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Capability signals', 'wmcp-agentops'); ?></span><strong data-metric="capability_gaps.requests">—</strong><small><?php esc_html_e('unsupported goals', 'wmcp-agentops'); ?></small></article>
			<article class="wmcp-metric"><span><?php esc_html_e('Control changes', 'wmcp-agentops'); ?></span><strong data-metric="policy_changes">—</strong><small><?php esc_html_e('policy signals', 'wmcp-agentops'); ?></small></article>
		</div>
		<div class="wmcp-attribution-strip" aria-label="<?php esc_attr_e('Attributed revenue classes', 'wmcp-agentops'); ?>">
			<?php foreach (array('direct' => __('Agent direct', 'wmcp-agentops'), 'assisted' => __('Agent assisted', 'wmcp-agentops'), 'influenced' => __('Agent influenced', 'wmcp-agentops')) as $class => $label) : ?>
				<article data-attribution="<?php echo esc_attr($class); ?>"><span><?php echo esc_html($label); ?></span><strong data-attribution-orders>—</strong><dl><div><dt><?php esc_html_e('Gross', 'wmcp-agentops'); ?></dt><dd data-attribution-gross>—</dd></div><div><dt><?php esc_html_e('Refunds', 'wmcp-agentops'); ?></dt><dd data-attribution-refunds>—</dd></div><div><dt><?php esc_html_e('Net', 'wmcp-agentops'); ?></dt><dd data-attribution-net>—</dd></div></dl></article>
			<?php endforeach; ?>
		</div>
	</section>

	<section id="wmcp-workflows" class="wmcp-board-section" aria-labelledby="wmcp-workflows-title">
		<div class="wmcp-board-section-head"><div><p class="wmcp-kicker"><?php esc_html_e('02 / Structured workflow replay', 'wmcp-agentops'); ?></p><h2 id="wmcp-workflows-title"><?php esc_html_e('Agent sessions', 'wmcp-agentops'); ?></h2></div><p><?php esc_html_e('Replay redacted tool and commerce events from this browser scope. No raw prompts, payloads, addresses, or payment data.', 'wmcp-agentops'); ?></p></div>
		<div class="wmcp-workflow-layout">
			<div class="wmcp-table-scroll">
				<table class="wmcp-data-table wmcp-workflow-table">
					<caption class="wmcp-screen-reader-text"><?php esc_html_e('Agent workflows in the current browser session', 'wmcp-agentops'); ?></caption>
					<thead><tr><th scope="col"><?php esc_html_e('Agent workflow', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Status', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Calls', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Last signal', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Net', 'wmcp-agentops'); ?></th></tr></thead>
					<tbody data-wmcp-workflows><tr><td colspan="5" class="wmcp-table-empty"><?php esc_html_e('Load the monitor to inspect agent workflows in this browser session.', 'wmcp-agentops'); ?></td></tr></tbody>
				</table>
			</div>
			<article class="wmcp-timeline-card" aria-labelledby="wmcp-timeline-title">
				<div class="wmcp-panel-label"><span><?php esc_html_e('Workflow replay', 'wmcp-agentops'); ?></span><span data-wmcp-timeline-count>—</span></div>
				<h3 id="wmcp-timeline-title" tabindex="-1"><?php esc_html_e('Select an agent workflow', 'wmcp-agentops'); ?></h3>
				<p data-wmcp-explanation><?php esc_html_e('The structured timeline shows terminal outcomes, latency, first problem, recovery, commerce evidence, and capability signals.', 'wmcp-agentops'); ?></p>
				<dl class="wmcp-workflow-evidence" data-wmcp-workflow-evidence>
					<div><dt><?php esc_html_e('Status', 'wmcp-agentops'); ?></dt><dd data-evidence="status">—</dd></div>
					<div><dt><?php esc_html_e('Products', 'wmcp-agentops'); ?></dt><dd data-evidence="products">—</dd></div>
					<div><dt><?php esc_html_e('Orders / attribution', 'wmcp-agentops'); ?></dt><dd data-evidence="orders">—</dd></div>
					<div><dt><?php esc_html_e('Capability signals', 'wmcp-agentops'); ?></dt><dd data-evidence="gaps">—</dd></div>
				</dl>
				<ol class="wmcp-timeline" data-wmcp-timeline></ol>
			</article>
		</div>
	</section>

	<section id="wmcp-funnel" class="wmcp-board-section wmcp-funnel-section" aria-labelledby="wmcp-funnel-title">
		<div class="wmcp-board-section-head"><div><p class="wmcp-kicker"><?php esc_html_e('03 / Recorded progression', 'wmcp-agentops'); ?></p><h2 id="wmcp-funnel-title"><?php esc_html_e('Journey & outcomes', 'wmcp-agentops'); ?></h2></div><p><?php esc_html_e('Stage counts and conversion use recorded evidence, never inferred intent.', 'wmcp-agentops'); ?></p></div>
		<ol class="wmcp-funnel" data-wmcp-funnel aria-label="<?php esc_attr_e('Recorded commerce journey', 'wmcp-agentops'); ?>">
			<?php
			$stages = array(
				'workflow_started' => __('Workflow started', 'wmcp-agentops'),
				'product_search' => __('Product search', 'wmcp-agentops'),
				'product_viewed' => __('Product viewed', 'wmcp-agentops'),
				'comparison' => __('Comparison', 'wmcp-agentops'),
				'cart_changed' => __('Cart mutation', 'wmcp-agentops'),
				'checkout_handoff' => __('Human checkout handoff', 'wmcp-agentops'),
				'order_created' => __('Order created', 'wmcp-agentops'),
				'order_paid' => __('Order paid', 'wmcp-agentops'),
				'retained_after_refunds' => __('Retained after refunds', 'wmcp-agentops'),
			);
			foreach ($stages as $key => $label) :
				?>
				<li data-funnel-stage="<?php echo esc_attr($key); ?>"><span class="wmcp-funnel-marker" aria-hidden="true"></span><div><strong><?php echo esc_html($label); ?></strong><small data-funnel-reason><?php esc_html_e('Awaiting evidence', 'wmcp-agentops'); ?></small></div><b data-funnel-count>—</b><em><span data-funnel-rate>—</span><small><?php esc_html_e('start', 'wmcp-agentops'); ?></small><span data-funnel-previous>—</span><small><?php esc_html_e('prior', 'wmcp-agentops'); ?></small></em></li>
			<?php endforeach; ?>
		</ol>
	</section>

	<section id="wmcp-tools" class="wmcp-board-section" aria-labelledby="wmcp-tools-title">
		<div class="wmcp-board-section-head"><div><p class="wmcp-kicker"><?php esc_html_e('04 / Tool performance', 'wmcp-agentops'); ?></p><h2 id="wmcp-tools-title"><?php esc_html_e('Tools', 'wmcp-agentops'); ?></h2></div><p><?php esc_html_e('Availability, latency, and outcome rates use terminal tool events in the authorized scope.', 'wmcp-agentops'); ?></p></div>
		<div class="wmcp-table-scroll">
			<table class="wmcp-data-table">
				<caption class="wmcp-screen-reader-text"><?php esc_html_e('WebMCP tool performance and state', 'wmcp-agentops'); ?></caption>
				<thead><tr><th scope="col"><?php esc_html_e('Tool', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Calls / workflows', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Success / failure / denied', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('p50 / p95', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Top error', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Cart / handoff', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('Orders / attributed net', 'wmcp-agentops'); ?></th><th scope="col"><?php esc_html_e('State', 'wmcp-agentops'); ?></th></tr></thead>
				<tbody data-wmcp-tool-health><tr><td colspan="8" class="wmcp-table-empty"><?php esc_html_e('No tool-health query has been run for this scope.', 'wmcp-agentops'); ?></td></tr></tbody>
			</table>
		</div>
	</section>

	<section id="wmcp-gaps" class="wmcp-board-section" aria-labelledby="wmcp-gaps-title">
		<div class="wmcp-board-section-head"><div><p class="wmcp-kicker"><?php esc_html_e('05 / Actionable evidence', 'wmcp-agentops'); ?></p><h2 id="wmcp-gaps-title"><?php esc_html_e('Signals', 'wmcp-agentops'); ?></h2></div><p><?php esc_html_e('Signals come from recorded failures, denials, and unsupported goals—not inferred sentiment.', 'wmcp-agentops'); ?></p></div>
		<div class="wmcp-signal-feed" data-wmcp-signals></div>
		<div class="wmcp-capability-signal-head"><span><?php esc_html_e('Capability requests', 'wmcp-agentops'); ?></span><small><?php esc_html_e('Viewed-product value is opportunity context, not a claim of lost revenue.', 'wmcp-agentops'); ?></small></div>
		<div class="wmcp-gap-list" data-wmcp-gaps><p class="wmcp-empty-state"><?php esc_html_e('No unsupported-goal signals have been recorded for this scope.', 'wmcp-agentops'); ?></p></div>
	</section>

	<section id="wmcp-governance" class="wmcp-board-section wmcp-governance" aria-labelledby="wmcp-governance-title">
		<div class="wmcp-board-section-head"><div><p class="wmcp-kicker"><?php esc_html_e('06 / Close the loop', 'wmcp-agentops'); ?></p><h2 id="wmcp-governance-title"><?php esc_html_e('Controls', 'wmcp-agentops'); ?></h2></div><p><?php echo $is_admin ? esc_html__('Persistent site policy. Changes are authenticated and enforced server-side.', 'wmcp-agentops') : esc_html__('Public controls can only restrict storefront tools for this demo session.', 'wmcp-agentops'); ?></p></div>

		<?php if ($is_admin && ! empty($view['can_manage_policies']) && isset($view['settings']) && is_array($view['settings'])) : ?>
			<form class="wmcp-policy-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
				<input type="hidden" name="action" value="wmcp_agentops_save_settings">
				<?php wp_nonce_field('wmcp_agentops_save_settings'); ?>
				<div class="wmcp-global-controls">
					<label class="wmcp-switch-row"><span><strong><?php esc_html_e('WebMCP site layer', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Advertise and execute allowlisted tools.', 'wmcp-agentops'); ?></small></span><input type="checkbox" name="webmcp_enabled" value="1" <?php checked(! empty($view['settings']['plugin_enabled'])); ?>><i aria-hidden="true"></i></label>
					<label class="wmcp-switch-row wmcp-switch-danger"><span><strong><?php esc_html_e('Emergency stop', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Immediately deny every WebMCP execution.', 'wmcp-agentops'); ?></small></span><input type="checkbox" name="kill_switch" value="1" <?php checked(! empty($view['settings']['kill_switch'])); ?>><i aria-hidden="true"></i></label>
				</div>
				<div class="wmcp-policy-list">
					<?php foreach ($tools as $tool) : ?>
						<label class="wmcp-policy-row">
							<span><strong class="wmcp-mono"><?php echo esc_html((string) $tool['name']); ?></strong><small><?php echo esc_html((string) $tool['title']); ?></small></span>
							<span class="wmcp-policy-meta"><em><?php echo esc_html((string) $tool['surface']); ?></em><em><?php echo esc_html((string) $tool['risk_class']); ?></em><em><?php echo esc_html((string) $tool['rate_limit']); ?>/min</em></span>
							<input type="checkbox" name="enabled_tools[]" value="<?php echo esc_attr((string) $tool['name']); ?>" <?php checked(! empty($tool['enabled'])); ?>><i aria-hidden="true"></i>
						</label>
					<?php endforeach; ?>
				</div>
				<button class="button button-primary wmcp-admin-save" type="submit"><?php esc_html_e('Save server policy', 'wmcp-agentops'); ?></button>
			</form>
		<?php elseif ($is_admin && isset($view['settings']) && is_array($view['settings'])) : ?>
			<div class="wmcp-public-policy">
				<div class="wmcp-global-status"><span><?php esc_html_e('Persistent site policy', 'wmcp-agentops'); ?></span><strong><?php esc_html_e('Read only for your role', 'wmcp-agentops'); ?></strong></div>
				<div class="wmcp-public-tool-list">
					<?php foreach ($tools as $tool) : ?>
						<div class="wmcp-public-tool <?php echo esc_attr(empty($tool['enabled']) ? 'wmcp-policy-disabled' : ''); ?>"><strong><?php echo esc_html((string) $tool['name']); ?></strong><span><?php echo ! empty($tool['enabled']) ? esc_html__('Enabled', 'wmcp-agentops') : esc_html__('Disabled', 'wmcp-agentops'); ?></span><small><?php echo esc_html((string) $tool['risk_class']); ?></small></div>
					<?php endforeach; ?>
				</div>
				<p class="wmcp-governance-note"><?php esc_html_e('Ask an administrator with the manage WebMCP policies capability to change these controls.', 'wmcp-agentops'); ?></p>
			</div>
		<?php else : ?>
			<div class="wmcp-public-policy">
				<div class="wmcp-global-status"><span><?php esc_html_e('Global site policy', 'wmcp-agentops'); ?></span><strong data-policy-global><?php
					$global_status = (string) ($governance['global_status'] ?? 'webmcp_disabled');
					echo esc_html(match ($global_status) {
						'emergency_stop' => __('Emergency stop active', 'wmcp-agentops'),
						'ready' => __('WebMCP site layer enabled', 'wmcp-agentops'),
						default => __('WebMCP site layer disabled', 'wmcp-agentops'),
					});
				?></strong></div>
				<div class="wmcp-public-tool-list" data-wmcp-policy-tools>
					<?php foreach ($tools as $tool) : ?>
						<div class="wmcp-public-tool <?php echo esc_attr(empty($tool['enabled']) ? 'wmcp-policy-disabled' : ''); ?>" data-policy-tool="<?php echo esc_attr((string) $tool['name']); ?>">
							<strong><?php echo esc_html((string) $tool['name']); ?></strong>
							<span data-policy-state><?php
								if (empty($tool['site_enabled'])) {
									esc_html_e('Blocked by global or site policy', 'wmcp-agentops');
								} else {
									esc_html_e('Site enabled · session state updates after governance calls', 'wmcp-agentops');
								}
							?></span>
							<small><?php echo esc_html((string) $tool['risk_class']); ?></small>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="wmcp-governance-note"><strong><?php esc_html_e('Safety invariant:', 'wmcp-agentops'); ?></strong> <?php esc_html_e('A public session can disable a storefront tool or clear its own override. It cannot elevate a site-level denial or change another session.', 'wmcp-agentops'); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<footer class="wmcp-field-footer">
		<p><strong><?php esc_html_e('Data boundary:', 'wmcp-agentops'); ?></strong> <?php esc_html_e('Operational events are redacted at write and read time. Revenue uses explicit same-session WooCommerce evidence and transparent direct, assisted, or influenced attribution.', 'wmcp-agentops'); ?></p>
		<?php if (! $is_admin) : ?><a href="<?php echo esc_url((string) $view['landing_url']); ?>" class="wmcp-text-link"><?php esc_html_e('Return to field brief', 'wmcp-agentops'); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
	</footer>
<?php if (! $is_admin) : ?>
	</div>
<?php else : ?>
	</div>
<?php endif; ?>
