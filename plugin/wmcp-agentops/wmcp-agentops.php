<?php

/**
 * Plugin Name:       Agent SNR
 * Description:       Agent outcome monitoring for WordPress with privacy-safe replay, signals, and governance.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * WC requires at least: 10.9
 * WC tested up to:     11.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wmcp-agentops
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WMCP_AGENTOPS_VERSION', '0.1.0');
define('WMCP_AGENTOPS_FILE', __FILE__);
define('WMCP_AGENTOPS_PATH', plugin_dir_path(__FILE__));
define('WMCP_AGENTOPS_URL', plugin_dir_url(__FILE__));

require_once WMCP_AGENTOPS_PATH . 'src/Autoloader.php';

WPWebMCP\AgentOps\Autoloader::register(WMCP_AGENTOPS_PATH . 'src');

add_action(
    'before_woocommerce_init',
    array(WPWebMCP\AgentOps\WooCommerce\WooIntegration::class, 'declare_hpos_compatibility')
);

register_activation_hook(__FILE__, array(WPWebMCP\AgentOps\Activation\Activator::class, 'activate'));
register_deactivation_hook(__FILE__, array(WPWebMCP\AgentOps\Activation\Deactivator::class, 'deactivate'));

add_action(
    'plugins_loaded',
    static function (): void {
        WPWebMCP\AgentOps\Plugin::instance()->boot();
    }
);
