<?php

/**
 * Non-sensitive compatibility and readiness diagnostics.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use WPWebMCP\AgentOps\Activation\DatabaseMigrator;
use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Contract\Versions;
use WPWebMCP\AgentOps\Demo\DemoMode;
use WPWebMCP\AgentOps\Policy\KillSwitch;
use WPWebMCP\AgentOps\Policy\PolicyStore;

final class DiagnosticsService
{
    public function __construct(
        private readonly ?ToolCatalog $catalog = null,
        private readonly ?PolicyStore $policies = null,
        private readonly ?KillSwitch $kill_switch = null
    ) {
    }

    /**
     * @param list<string> $requested Requested check identifiers.
     * @return array<string, mixed>
     */
    public function run(array $requested = array()): array
    {
        $checks = array(
            'database'    => array(
                'status'  => Versions::DATABASE === (string) get_option('wmcp_agentops_db_version', '')
                    && DatabaseMigrator::schema_ready() ? 'passed' : 'failed',
                'version' => (string) get_option('wmcp_agentops_db_version', ''),
            ),
            'manifest'    => array(
                'status'           => $this->manifest_enabled() ? 'passed' : 'disabled',
                'storefront_tools' => $this->tool_count('storefront'),
                'agentops_tools'   => $this->tool_count('agentops'),
            ),
            'rest'        => array(
                'status'    => 'passed',
                'namespace' => 'wmcp-agentops/v1',
            ),
            'session'     => array(
                'status' => DemoMode::enabled() ? 'passed' : 'not_demo',
            ),
            'woocommerce' => array(
                'status'  => class_exists('WooCommerce') ? 'passed' : 'unavailable',
                'version' => defined('WC_VERSION') ? WC_VERSION : null,
            ),
            'hpos'        => array(
                'status'  => $this->hpos_enabled() ? 'enabled' : 'disabled_or_unavailable',
            ),
            'headers'     => array(
                'status'   => is_ssl() ? 'server_ready' : 'requires_https',
                'expected' => array(
                    'Origin-Agent-Cluster' => '?1',
                    'Permissions-Policy'   => 'tools=(self)',
                ),
            ),
        );

        if (array() !== $requested) {
            $checks = array_intersect_key($checks, array_fill_keys($requested, true));
        }

        return array(
            'plugin_version'          => Versions::PLUGIN,
            'schema_version'          => Versions::SCHEMA,
            'wordpress_version'       => get_bloginfo('version'),
            'php_version'             => PHP_VERSION,
            'checks'                  => $checks,
            'browser_checks_required' => array('secure_context', 'top_level', 'origin_agent_cluster', 'webmcp_api', 'registration'),
        );
    }

    private function hpos_enabled(): bool
    {
        if (! class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            return false;
        }

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    private function manifest_enabled(): bool
    {
        $policies    = $this->policies ?? new PolicyStore();
        $kill_switch = $this->kill_switch ?? new KillSwitch();

        return $policies->plugin_enabled() && ! $kill_switch->active();
    }

    private function tool_count(string $surface): int
    {
        if (! $this->manifest_enabled()) {
            return 0;
        }

        $catalog  = $this->catalog ?? new ToolCatalog();
        $policies = $this->policies ?? new PolicyStore();
        $count    = 0;
        foreach ($catalog->public_surface($surface) as $definition) {
            if ($definition['requires_woocommerce'] && ! class_exists('WooCommerce')) {
                continue;
            }
            if ($policies->enabled((string) $definition['name'])) {
                ++$count;
            }
        }

        return $count;
    }
}
