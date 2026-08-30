<?php
/**
 * Plugin composition root.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps;

final class Plugin
{
    private static ?self $instance = null;
    private bool $booted = false;

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        add_action('admin_notices', array($this, 'render_dependency_notice'));
    }

    public function render_dependency_notice(): void
    {
        if (function_exists('wp_register_ability')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WP WebMCP AgentOps requires the WordPress Abilities API provided by WordPress 6.9 or newer.', 'wmcp-agentops');
        echo '</p></div>';
    }
}

