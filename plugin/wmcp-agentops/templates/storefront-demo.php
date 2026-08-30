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
?>
<a class="wmcp-skip-link" href="#wmcp-storefront-main"><?php esc_html_e('Skip to storefront', 'wmcp-agentops'); ?></a>
<div id="wmcp-storefront-main" class="wmcp-field wmcp-storefront alignfull" data-wmcp-surface="storefront" tabindex="-1">
	<header class="wmcp-store-header">
		<a class="wmcp-wordmark" href="<?php echo esc_url((string) $view['landing_url']); ?>" aria-label="<?php esc_attr_e('Return to Agent SNR', 'wmcp-agentops'); ?>">
			<span class="wmcp-wordmark-mark" aria-hidden="true">TF</span>
			<span>TrailForge<br><em>Field Supply</em></span>
		</a>
		<nav class="wmcp-store-nav" aria-label="<?php esc_attr_e('Storefront navigation', 'wmcp-agentops'); ?>">
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
			<p data-copy-source="storefront">Find a waterproof backpack under $120, compare the two best choices, confirm that the return policy is at least 30 days, and add the best-value option to my cart.</p>
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
