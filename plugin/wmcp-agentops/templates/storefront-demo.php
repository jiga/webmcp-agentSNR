<?php
/**
 * Progressive-enhancement storefront demo.
 *
 * @var array<string, mixed> $view
 * @package WPWebMCP\AgentOps
 */

defined('ABSPATH') || exit;

$cart_count = isset($view['cart_count']) && is_int($view['cart_count']) ? $view['cart_count'] : null;
$cart_label = __('Cart count not loaded', 'wmcp-agentops');
if (null !== $cart_count) {
	/* translators: %d is the number of items in the current WooCommerce cart. */
	$cart_label = sprintf(_n('%d item in cart', '%d items in cart', $cart_count, 'wmcp-agentops'), $cart_count);
}

$agent_guide = isset($view['agent_guide']) && is_array($view['agent_guide']) ? $view['agent_guide'] : array();
if (array() === $agent_guide && class_exists(\WPWebMCP\AgentOps\Guidance\AgentGuide::class)) {
	$agent_guide = (new \WPWebMCP\AgentOps\Guidance\AgentGuide())->guide();
}
$guide_version = isset($agent_guide['version']) && is_string($agent_guide['version'])
	? $agent_guide['version']
	: '1.1';
$guide_journeys = isset($agent_guide['supported_journeys']) && is_array($agent_guide['supported_journeys'])
	? $agent_guide['supported_journeys']
	: array();
$guide_steps = isset($guide_journeys[0]['steps']) && is_array($guide_journeys[0]['steps'])
	? $guide_journeys[0]['steps']
	: array();
if (array() === $guide_steps) {
	$guide_steps = array(
		array('id' => 'discover', 'effect' => 'read'),
		array('id' => 'compare', 'effect' => 'read'),
		array('id' => 'verify_policy', 'effect' => 'read'),
		array('id' => 'prepare_cart', 'effect' => 'reversible_session_write'),
		array('id' => 'handoff', 'effect' => 'human_checkpoint'),
	);
}
$guide_feedback = isset($agent_guide['feedback']) && is_array($agent_guide['feedback'])
	? $agent_guide['feedback']
	: array();
$guide_boundaries = isset($agent_guide['human_boundaries']) && is_array($agent_guide['human_boundaries'])
	? $agent_guide['human_boundaries']
	: array();
$guide_privacy = isset($agent_guide['privacy']) && is_array($agent_guide['privacy'])
	? $agent_guide['privacy']
	: array();
$wmcp_guide_execution = isset($agent_guide['execution']) && is_array($agent_guide['execution'])
	? $agent_guide['execution']
	: array();
$wmcp_guide_sensitive_actions = isset($agent_guide['sensitive_actions']) && is_array($agent_guide['sensitive_actions'])
	? $agent_guide['sensitive_actions']
	: array();
$wmcp_guide_pricing = isset($agent_guide['pricing_boundary']) && is_array($agent_guide['pricing_boundary'])
	? $agent_guide['pricing_boundary']
	: array();
$guide_human_must = isset($guide_boundaries[0]['human_must']) && is_array($guide_boundaries[0]['human_must'])
	? $guide_boundaries[0]['human_must']
	: array('review_details', 'submit_customer_data', 'accept_terms', 'place_order');
$guide_excluded_data = isset($guide_privacy['excluded']) && is_array($guide_privacy['excluded'])
	? $guide_privacy['excluded']
	: array('raw_prompt', 'identity', 'address', 'payment_data', 'raw_payload');
$guide_feedback_triggers = isset($guide_feedback['recommended_when']) && is_array($guide_feedback['recommended_when'])
	? $guide_feedback['recommended_when']
	: array('journey_completed', 'journey_blocked', 'zero_results', 'human_handoff');
$guide_step_copy = array(
	'understand'    => array(__('Start', 'wmcp-agentops'), __('Read the site journey and boundaries', 'wmcp-agentops')),
	'discover'      => array(__('Search', 'wmcp-agentops'), __('Read public product facts', 'wmcp-agentops')),
	'evaluate'      => array(__('Evaluate', 'wmcp-agentops'), __('Inspect and compare stored facts', 'wmcp-agentops')),
	'compare'       => array(__('Compare', 'wmcp-agentops'), __('Keep missing facts honest', 'wmcp-agentops')),
	'verify_policy' => array(__('Verify', 'wmcp-agentops'), __('Use published policy evidence', 'wmcp-agentops')),
	'prepare_cart'  => array(__('Prepare', 'wmcp-agentops'), __('Cart changes stay reversible', 'wmcp-agentops')),
	'handoff'       => array(__('Hand off', 'wmcp-agentops'), __('A person completes checkout', 'wmcp-agentops')),
	'feedback'      => array(__('Report', 'wmcp-agentops'), __('Optional structured journey feedback', 'wmcp-agentops')),
);
?>
<a class="wmcp-skip-link" href="#wmcp-storefront-main"><?php esc_html_e('Skip to storefront', 'wmcp-agentops'); ?></a>
<div id="wmcp-storefront-main" class="wmcp-field wmcp-storefront alignfull" data-wmcp-surface="storefront" tabindex="-1">
	<header class="wmcp-store-header">
		<a class="wmcp-wordmark" href="<?php echo esc_url((string) $view['landing_url']); ?>" aria-label="<?php esc_attr_e('Return to Agent SNR', 'wmcp-agentops'); ?>">
			<span class="wmcp-wordmark-mark" aria-hidden="true">SNR</span>
			<span>Agent SNR<br><em>Demo Store</em></span>
		</a>
		<nav class="wmcp-store-nav" aria-label="<?php esc_attr_e('Storefront navigation', 'wmcp-agentops'); ?>">
			<a href="#wmcp-agent-guide"><?php esc_html_e('Agent guide', 'wmcp-agentops'); ?></a>
			<a href="#wmcp-specimens"><?php esc_html_e('Field specimens', 'wmcp-agentops'); ?></a>
			<a href="<?php echo esc_url((string) $view['shop_url']); ?>"><?php esc_html_e('Standard shop', 'wmcp-agentops'); ?></a>
			<a class="wmcp-cart-link" href="<?php echo esc_url((string) $view['cart_url']); ?>"><span><?php esc_html_e('Cart', 'wmcp-agentops'); ?></span><strong data-wmcp-cart-count aria-label="<?php echo esc_attr($cart_label); ?>"><?php echo esc_html(null === $cart_count ? '—' : (string) $cart_count); ?></strong></a>
		</nav>
	</header>

	<section class="wmcp-store-hero" aria-labelledby="wmcp-store-title">
		<div>
			<p class="wmcp-kicker"><?php esc_html_e('Weatherproof carry systems / test batch 06', 'wmcp-agentops'); ?></p>
			<h1 id="wmcp-store-title"><?php esc_html_e('Carry less doubt.', 'wmcp-agentops'); ?><br><em><?php esc_html_e('Bring better evidence.', 'wmcp-agentops'); ?></em></h1>
			<p class="wmcp-deck"><?php esc_html_e('Twelve fictional field products, structured for honest comparison. Shop normally or ask a browser agent to find, verify, and prepare the best fit.', 'wmcp-agentops'); ?></p>
		</div>
		<div class="wmcp-store-prompt">
			<span class="wmcp-panel-label"><span><?php esc_html_e('Suggested field brief', 'wmcp-agentops'); ?></span><span>01</span></span>
			<p data-copy-source="storefront">Start with this site’s Agent Guide. Find a waterproof backpack under $100 with IPX5 protection. If none match, show the closest options, explain the constraint, prepare the compact choice for checkout, and follow the guide’s feedback instructions.</p>
			<button type="button" class="wmcp-copy-button" data-copy-target="storefront"><span data-copy-label><?php esc_html_e('Copy prompt', 'wmcp-agentops'); ?></span><span aria-hidden="true">⌁</span></button>
		</div>
	</section>

	<section class="wmcp-signal-strip" aria-label="<?php esc_attr_e('Live WebMCP workflow status', 'wmcp-agentops'); ?>">
		<div class="wmcp-status-chip" data-wmcp-status-chip data-state="checking">
			<span class="wmcp-status-light" aria-hidden="true"></span>
			<span data-wmcp-status><?php esc_html_e('Checking WebMCP', 'wmcp-agentops'); ?></span>
		</div>
		<div><span><?php esc_html_e('Workflow', 'wmcp-agentops'); ?></span><strong class="wmcp-mono" data-wmcp-workflow>—</strong></div>
		<div><span><?php esc_html_e('Latest signal', 'wmcp-agentops'); ?></span><strong class="wmcp-mono" data-wmcp-latest-tool><?php esc_html_e('Awaiting tool call', 'wmcp-agentops'); ?></strong></div>
		<div><span><?php esc_html_e('Registered', 'wmcp-agentops'); ?></span><strong class="wmcp-mono"><span data-wmcp-tool-count>—</span> <?php esc_html_e('tools', 'wmcp-agentops'); ?></strong></div>
	</section>

	<section id="wmcp-agent-guide" class="wmcp-agent-guide" data-wmcp-agent-guide data-state="published" aria-labelledby="wmcp-agent-guide-title">
		<div class="wmcp-agent-guide-head">
			<p class="wmcp-kicker"><?php esc_html_e('Site field guide', 'wmcp-agentops'); ?></p>
			<h2 id="wmcp-agent-guide-title"><?php esc_html_e('How agents can use this store.', 'wmcp-agentops'); ?></h2>
			<p class="wmcp-agent-guide-purpose"><?php echo esc_html((string) ($agent_guide['purpose'] ?? __('Use public evidence, keep reversible actions reversible, and stop when a person must decide.', 'wmcp-agentops'))); ?></p>
			<p class="wmcp-agent-guide-stamp"><span><?php esc_html_e('Guide', 'wmcp-agentops'); ?> <strong data-wmcp-guide-version><?php echo esc_html($guide_version); ?></strong></span><span data-wmcp-guide-status><?php esc_html_e('Start here', 'wmcp-agentops'); ?></span></p>
		</div>
		<ol data-wmcp-guide-steps>
			<?php foreach ($guide_steps as $index => $step) : ?>
				<?php
				$step_id = isset($step['id']) && is_string($step['id']) ? $step['id'] : '';
				$step_copy = $guide_step_copy[$step_id] ?? array(ucwords(str_replace('_', ' ', $step_id)), __('Follow the published site guidance', 'wmcp-agentops'));
				$step_effect = isset($step['effect']) && is_string($step['effect']) ? $step['effect'] : 'read';
				?>
				<li data-guide-effect="<?php echo esc_attr($step_effect); ?>"><span><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span><strong><?php echo esc_html((string) $step_copy[0]); ?></strong><small><?php echo esc_html((string) $step_copy[1]); ?></small></li>
			<?php endforeach; ?>
		</ol>
		<div class="wmcp-agent-guide-foot">
			<dl data-wmcp-guide-boundaries>
				<div><dt><?php esc_html_e('Execution mode', 'wmcp-agentops'); ?></dt><dd><?php
					echo esc_html(
						'top_level_co_browsing' === ($wmcp_guide_execution['supported_mode'] ?? '')
							? __('Top-level co-browsing supported; unattended remote execution unsupported.', 'wmcp-agentops')
							: __('Use this guide with a person present in the top-level storefront.', 'wmcp-agentops')
					);
				?></dd></div>
				<div><dt><?php esc_html_e('Human boundary', 'wmcp-agentops'); ?></dt><dd><?php echo esc_html(implode(', ', array_map(static fn ($value): string => str_replace('_', ' ', (string) $value), $guide_human_must))); ?>.</dd></div>
				<div><dt><?php esc_html_e('Sensitive actions', 'wmcp-agentops'); ?></dt><dd><?php
					/* translators: %d is the number of sensitive WebMCP tools exposed by the storefront. */
					echo esc_html(sprintf(__('%d WebMCP tools; order placement and payment stay with the person.', 'wmcp-agentops'), (int) ($wmcp_guide_sensitive_actions['tool_count'] ?? 0)));
				?></dd></div>
				<div><dt><?php esc_html_e('Price boundary', 'wmcp-agentops'); ?></dt><dd><?php
					echo esc_html(
						'cart_subtotal_or_estimate' === ($wmcp_guide_pricing['before_checkout'] ?? '')
							? __('Cart subtotal or estimate before checkout; final tax, shipping, fees, and total appear at human checkout.', 'wmcp-agentops')
							: __('A person reviews the final amount at checkout.', 'wmcp-agentops')
					);
				?></dd></div>
				<div><dt><?php esc_html_e('Data boundary', 'wmcp-agentops'); ?></dt><dd><?php echo esc_html(implode(', ', array_map(static fn ($value): string => str_replace('_', ' ', (string) $value), $guide_excluded_data))); ?>.</dd></div>
			</dl>
			<p data-wmcp-feedback-policy><strong><?php esc_html_e('Optional feedback:', 'wmcp-agentops'); ?></strong> <?php echo esc_html(implode(', ', array_map(static fn ($value): string => str_replace('_', ' ', (string) $value), $guide_feedback_triggers))); ?>. <?php
				/* translators: %d is the maximum feedback reports permitted in one workflow. */
				echo esc_html(sprintf(__('Maximum %d reports per workflow.', 'wmcp-agentops'), (int) ($guide_feedback['max_reports_per_workflow'] ?? 2)));
			?></p>
		</div>
	</section>

	<div class="wmcp-store-layout">
		<section id="wmcp-specimens" class="wmcp-specimen-catalog" aria-labelledby="wmcp-specimen-title">
			<div class="wmcp-section-heading">
				<p class="wmcp-kicker"><?php esc_html_e('Catalog / deterministic demo set', 'wmcp-agentops'); ?></p>
				<h2 id="wmcp-specimen-title"><?php esc_html_e('Field specimens', 'wmcp-agentops'); ?></h2>
				<p><?php esc_html_e('Each illustration is original, local SVG artwork. Product links remain ordinary WooCommerce links when WebMCP is unavailable.', 'wmcp-agentops'); ?></p>
			</div>

			<div class="wmcp-product-grid" data-wmcp-product-grid>
				<?php foreach ((array) $view['products'] as $index => $product) : ?>
					<article class="wmcp-product-card" data-product-id="<?php echo esc_attr((string) $product['id']); ?>" data-product-slug="<?php echo esc_attr((string) $product['slug']); ?>">
						<div class="wmcp-product-figure">
							<span class="wmcp-product-index"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
							<img src="<?php echo esc_url((string) $product['image_url']); ?>" alt="" width="480" height="480" loading="lazy">
							<span class="wmcp-match-flag" data-match-flag hidden><?php esc_html_e('Agent match', 'wmcp-agentops'); ?></span>
						</div>
						<div class="wmcp-product-copy">
							<div><h3><?php echo esc_html((string) $product['name']); ?></h3><strong class="wmcp-product-price"><?php echo esc_html((string) $product['price']); ?></strong></div>
							<dl>
								<div><dt><?php esc_html_e('Water', 'wmcp-agentops'); ?></dt><dd><?php echo esc_html((string) $product['water']); ?></dd></div>
								<div><dt><?php esc_html_e('Volume', 'wmcp-agentops'); ?></dt><dd><?php echo esc_html((string) $product['capacity']); ?></dd></div>
								<div><dt><?php esc_html_e('Returns', 'wmcp-agentops'); ?></dt><dd><?php echo esc_html((string) $product['return_days']); ?>d</dd></div>
							</dl>
							<p class="wmcp-stock-line"><?php echo esc_html((string) $product['stock']); ?></p>
							<a class="wmcp-text-link" href="<?php echo esc_url((string) $product['url']); ?>"><?php echo 0 < (int) $product['id'] ? esc_html__('Inspect product', 'wmcp-agentops') : esc_html__('Browse standard shop', 'wmcp-agentops'); ?> <span aria-hidden="true">→</span></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<aside class="wmcp-operation-dock" aria-labelledby="wmcp-operation-title">
			<div class="wmcp-operation-head">
				<div>
					<p class="wmcp-kicker"><?php esc_html_e('Visible shared state', 'wmcp-agentops'); ?></p>
					<h2 id="wmcp-operation-title"><?php esc_html_e('Workflow rail', 'wmcp-agentops'); ?></h2>
				</div>
				<a href="<?php echo esc_url((string) $view['agentops_url']); ?>" class="wmcp-text-link"><?php esc_html_e('Inspect trace', 'wmcp-agentops'); ?> <span aria-hidden="true">↗</span></a>
			</div>

			<ol class="wmcp-progress-rail" data-wmcp-progress-rail>
				<li data-stage="search"><span>01</span><strong><?php esc_html_e('Search', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Awaiting signal', 'wmcp-agentops'); ?></small></li>
				<li data-stage="comparison"><span>02</span><strong><?php esc_html_e('Compare', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Awaiting evidence', 'wmcp-agentops'); ?></small></li>
				<li data-stage="policy"><span>03</span><strong><?php esc_html_e('Policy', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Awaiting citation', 'wmcp-agentops'); ?></small></li>
				<li data-stage="cart"><span>04</span><strong><?php esc_html_e('Cart', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Awaiting reversible write', 'wmcp-agentops'); ?></small></li>
				<li data-stage="checkout"><span>05</span><strong><?php esc_html_e('Handoff', 'wmcp-agentops'); ?></strong><small><?php esc_html_e('Human confirmation required', 'wmcp-agentops'); ?></small></li>
			</ol>

			<div class="wmcp-operation-panels">
				<section class="wmcp-operation-panel" data-wmcp-panel="search" aria-labelledby="wmcp-search-results-title">
					<div class="wmcp-panel-label"><span><?php esc_html_e('Result tray', 'wmcp-agentops'); ?></span><span data-wmcp-result-count>—</span></div>
					<h3 id="wmcp-search-results-title" tabindex="-1"><?php esc_html_e('No agent search yet', 'wmcp-agentops'); ?></h3>
					<div class="wmcp-empty-state" data-wmcp-search-results><?php esc_html_e('Matching products will appear here while the complete catalog remains usable at left.', 'wmcp-agentops'); ?></div>
					<aside class="wmcp-opportunity-notice" data-wmcp-search-opportunity aria-label="<?php esc_attr_e('Recorded opportunity signal', 'wmcp-agentops'); ?>" hidden>
						<div><span><?php esc_html_e('Site observed', 'wmcp-agentops'); ?></span><strong><?php esc_html_e('Opportunity signal', 'wmcp-agentops'); ?></strong></div>
						<p data-wmcp-opportunity-summary></p>
						<p data-wmcp-feedback-hint hidden></p>
					</aside>
				</section>

				<section class="wmcp-operation-panel" data-wmcp-panel="comparison" aria-labelledby="wmcp-comparison-title">
					<div class="wmcp-panel-label"><span><?php esc_html_e('Evidence matrix', 'wmcp-agentops'); ?></span><span>02</span></div>
					<h3 id="wmcp-comparison-title" tabindex="-1"><?php esc_html_e('Comparison waiting', 'wmcp-agentops'); ?></h3>
					<div class="wmcp-table-scroll" data-wmcp-comparison><p class="wmcp-empty-state"><?php esc_html_e('A two-to-four product matrix will preserve missing facts instead of inventing them.', 'wmcp-agentops'); ?></p></div>
				</section>

				<section class="wmcp-operation-panel" data-wmcp-panel="policy" aria-labelledby="wmcp-policy-title">
					<div class="wmcp-panel-label"><span><?php esc_html_e('Published evidence', 'wmcp-agentops'); ?></span><span>03</span></div>
					<h3 id="wmcp-policy-title" tabindex="-1"><?php esc_html_e('Return policy not checked', 'wmcp-agentops'); ?></h3>
					<div data-wmcp-policy><p class="wmcp-empty-state"><?php esc_html_e('The agent can retrieve the merchant’s published policy and product-specific return window.', 'wmcp-agentops'); ?></p></div>
				</section>

				<section class="wmcp-operation-panel" data-wmcp-panel="cart" aria-labelledby="wmcp-cart-title">
					<div class="wmcp-panel-label"><span><?php esc_html_e('Session cart', 'wmcp-agentops'); ?></span><span>04</span></div>
					<h3 id="wmcp-cart-title" tabindex="-1"><?php esc_html_e('No cart signal yet', 'wmcp-agentops'); ?></h3>
					<div data-wmcp-cart><p class="wmcp-empty-state"><?php esc_html_e('Agent cart changes are reversible and share the normal WooCommerce session.', 'wmcp-agentops'); ?></p></div>
					<a class="wmcp-text-link" href="<?php echo esc_url((string) $view['cart_url']); ?>"><?php esc_html_e('Open normal cart', 'wmcp-agentops'); ?> <span aria-hidden="true">→</span></a>
				</section>

				<section class="wmcp-operation-panel wmcp-checkout-panel" data-wmcp-panel="checkout" aria-labelledby="wmcp-checkout-title">
					<div class="wmcp-panel-label"><span><?php esc_html_e('Human checkpoint', 'wmcp-agentops'); ?></span><span>05</span></div>
					<h3 id="wmcp-checkout-title" tabindex="-1"><?php esc_html_e('Checkout remains locked', 'wmcp-agentops'); ?></h3>
					<p data-wmcp-checkout-message><?php esc_html_e('The agent can prepare a handoff, but only a person can review details, accept terms, and place the demo order.', 'wmcp-agentops'); ?></p>
					<a class="wmcp-button wmcp-button-primary" data-wmcp-checkout-link href="<?php echo esc_url((string) $view['cart_url']); ?>" hidden><?php esc_html_e('Continue to demo checkout', 'wmcp-agentops'); ?> <span aria-hidden="true">↗</span></a>
				</section>

				<section class="wmcp-operation-panel wmcp-gap-panel" data-wmcp-panel="gap" aria-labelledby="wmcp-gap-title" hidden>
					<div class="wmcp-panel-label"><span><?php esc_html_e('Capability gap', 'wmcp-agentops'); ?></span><span>!</span></div>
					<h3 id="wmcp-gap-title" tabindex="-1"><?php esc_html_e('Unsupported request recorded', 'wmcp-agentops'); ?></h3>
					<p data-wmcp-gap-message></p>
				</section>

				<section class="wmcp-operation-panel wmcp-feedback-panel" data-wmcp-panel="feedback" aria-labelledby="wmcp-feedback-title" hidden>
					<div class="wmcp-panel-label"><span data-wmcp-feedback-trust><?php esc_html_e('Agent reported', 'wmcp-agentops'); ?></span><span data-wmcp-feedback-evidence-status><?php esc_html_e('Evidence pending', 'wmcp-agentops'); ?></span></div>
					<h3 id="wmcp-feedback-title" tabindex="-1"><?php esc_html_e('Agent feedback recorded', 'wmcp-agentops'); ?></h3>
					<p data-wmcp-feedback-message></p>
					<dl class="wmcp-feedback-evidence">
						<div><dt><?php esc_html_e('Site measured', 'wmcp-agentops'); ?></dt><dd data-wmcp-feedback-metrics>—</dd></div>
						<div><dt><?php esc_html_e('Suggested action', 'wmcp-agentops'); ?></dt><dd data-wmcp-feedback-action>—</dd></div>
					</dl>
				</section>
			</div>

			<p class="wmcp-live-message" role="status" aria-live="polite" aria-atomic="true" data-wmcp-announcer><?php esc_html_e('The field rail is ready for a tool call.', 'wmcp-agentops'); ?></p>
			<div class="wmcp-error-message" role="alert" data-wmcp-error hidden></div>
		</aside>
	</div>

	<footer class="wmcp-field-footer">
		<p><strong><?php esc_html_e('Progressive enhancement:', 'wmcp-agentops'); ?></strong> <?php esc_html_e('Every product, cart, and checkout route remains an ordinary WooCommerce link. WebMCP adds structured actions and visible evidence; it does not replace the human store.', 'wmcp-agentops'); ?></p>
		<a href="<?php echo esc_url((string) $view['landing_url']); ?>" class="wmcp-text-link"><?php esc_html_e('Return to field brief', 'wmcp-agentops'); ?> <span aria-hidden="true">←</span></a>
	</footer>
</div>
