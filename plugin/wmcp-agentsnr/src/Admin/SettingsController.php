<?php

/**
 * Authenticated persistent policy settings handler.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Admin;

use Throwable;
use WPWebMCP\AgentSNR\Abilities\ToolCatalog;
use WPWebMCP\AgentSNR\Analytics\EventRecorder;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Policy\KillSwitch;
use WPWebMCP\AgentSNR\Policy\PolicyStore;
use WPWebMCP\AgentSNR\Privacy\ActorHasher;
use WPWebMCP\AgentSNR\Support\IdGenerator;
use WPWebMCP\AgentSNR\Support\Logger;

final class SettingsController
{
    private ToolCatalog $catalog;
    private PolicyStore $policies;
    private KillSwitch $kill_switch;
    private ?WorkflowService $workflows;
    private ?EventRecorder $events;
    private ActorHasher $hasher;

    public function __construct(
        ?ToolCatalog $catalog = null,
        ?PolicyStore $policies = null,
        ?KillSwitch $kill_switch = null,
        ?WorkflowService $workflows = null,
        ?EventRecorder $events = null,
        ?ActorHasher $hasher = null
    ) {
        $this->catalog     = $catalog ?? new ToolCatalog();
        $this->policies    = $policies ?? new PolicyStore();
        $this->kill_switch = $kill_switch ?? new KillSwitch();
        $this->workflows   = $workflows;
        $this->events      = $events;
        $this->hasher      = $hasher ?? new ActorHasher();
    }

    public function hooks(): void
    {
        add_action('admin_post_wmcp_agentsnr_save_settings', array($this, 'save'));
    }

    public function save(): void
    {
        if (! current_user_can('manage_wmcp_policies')) {
            wp_die(esc_html__('You do not have permission to manage WebMCP policies.', 'wmcp-agentsnr'), '', array('response' => 403));
        }

        check_admin_referer('wmcp_agentsnr_save_settings');

        $enabled_tools = isset($_POST['enabled_tools']) && is_array($_POST['enabled_tools'])
            ? array_map('sanitize_key', wp_unslash($_POST['enabled_tools']))
            : array();

        foreach ($this->catalog->all() as $definition) {
            $name = (string) $definition['name'];
            $before = $this->policies->enabled($name);
            $after  = in_array($name, $enabled_tools, true);
            $this->policies->set($name, $after);
            if ($before !== $after) {
                $this->audit(EventName::POLICY_CHANGED, $name, $before, $after);
            }
        }

        $plugin_before = $this->policies->plugin_enabled();
        $plugin_after  = isset($_POST['webmcp_enabled']);
        update_option('wmcp_agentsnr_enabled', $plugin_after, false);
        if ($plugin_before !== $plugin_after) {
            $this->audit(EventName::POLICY_CHANGED, 'global_webmcp', $plugin_before, $plugin_after);
        }

        $kill_before = $this->kill_switch->active();
        $kill_after  = isset($_POST['kill_switch']);
        $this->kill_switch->set($kill_after);
        if ($kill_before !== $kill_after) {
            $this->audit(EventName::KILL_SWITCH_CHANGED, 'global_kill_switch', $kill_before, $kill_after);
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                => 'wmcp-agentsnr',
                    'wmcp-settings-saved' => '1',
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tools = array();
        foreach ($this->catalog->all() as $definition) {
            $name    = (string) $definition['name'];
            $tools[] = array(
                'name'       => $name,
                'title'      => (string) $definition['title'],
                'surface'    => (string) $definition['surface'],
                'risk_class' => (string) $definition['risk_class'],
                'version'    => (string) $definition['version'],
                'rate_limit' => (int) $definition['rate_limit'],
                'enabled'    => $this->policies->enabled($name),
            );
        }

        return array(
            'plugin_enabled' => $this->policies->plugin_enabled(),
            'kill_switch'    => $this->kill_switch->active(),
            'tools'          => $tools,
        );
    }

    private function audit(string $event_name, string $target, bool $before, bool $after): void
    {
        if (null === $this->workflows || null === $this->events) {
            return;
        }

        try {
            $scope_hash = $this->hasher->hex('wp-admin-user:' . get_current_user_id());
            $workflow   = $this->workflows->current($scope_hash, 'agentsnr');
            $this->events->record(
                (string) $workflow['id'],
                $event_name,
                array(
                    'properties' => array(
                        'actor_type'      => 'authenticated_admin',
                        'enabled'         => $after,
                        'previous_enabled' => $before,
                        'reason_code'     => 'admin_settings_form',
                        'scope'           => 'site',
                        'target_tool'     => $target,
                    ),
                ),
                'admin-policy:' . IdGenerator::uuid()
            );
        } catch (Throwable $exception) {
            Logger::error('admin_policy_audit_failed', array('exception' => get_class($exception)));
        }
    }
}
