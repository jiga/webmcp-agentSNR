<?php

/**
 * Plugin Name:       Agent SNR
 * Description:       Agent outcome monitoring for WordPress with privacy-safe replay, opportunity signals, agent feedback, and governance.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * WC requires at least: 10.9
 * WC tested up to:     11.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wmcp-agentsnr
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WMCP_AGENTSNR_VERSION', '0.1.0');
define('WMCP_AGENTSNR_FILE', __FILE__);
define('WMCP_AGENTSNR_PATH', plugin_dir_path(__FILE__));
define('WMCP_AGENTSNR_URL', plugin_dir_url(__FILE__));

require_once WMCP_AGENTSNR_PATH . 'src/Autoloader.php';

WPWebMCP\AgentSNR\Autoloader::register(WMCP_AGENTSNR_PATH . 'src');

add_action(
    'before_woocommerce_init',
    array(WPWebMCP\AgentSNR\WooCommerce\WooIntegration::class, 'declare_hpos_compatibility')
);

register_activation_hook(__FILE__, array(WPWebMCP\AgentSNR\Activation\Activator::class, 'activate'));
register_deactivation_hook(__FILE__, array(WPWebMCP\AgentSNR\Activation\Deactivator::class, 'deactivate'));

add_action(
    'plugins_loaded',
    static function (): void {
        WPWebMCP\AgentSNR\Plugin::instance()->boot();
    }
);
