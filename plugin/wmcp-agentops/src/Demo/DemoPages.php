<?php

/**
 * Public judge-demo page and asset registration.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Demo;

use WP_Post;
use WPWebMCP\AgentOps\Abilities\ToolCatalog;
use WPWebMCP\AgentOps\Policy\KillSwitch;
use WPWebMCP\AgentOps\Policy\PolicyStore;

final class DemoPages
{
    /** @var array<string, bool> */
    private array $enqueued_surfaces = array();

    private const SHORTCODES = array(
        'wmcp_judge_landing'  => 'landing',
        'wmcp_storefront_demo' => 'storefront',
        'wmcp_agentops_demo'   => 'agentops',
        'wmcp_health'          => 'health',
    );

    /**
     * Register public hooks. Call once from the plugin composition root.
     */
    public function hooks(): void
    {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_detected_surface'), 20);

        foreach (self::SHORTCODES as $shortcode => $surface) {
            add_shortcode($shortcode, array($this, 'render_' . $surface));
        }
    }

    /**
     * Page definitions consumed by the demo seeder/activation integration.
     *
     * Deliberately does not write pages during normal requests.
     *
     * @return list<array{slug:string,title:string,content:string}>
     */
    public static function page_blueprints(): array
    {
        return array(
            array(
                'slug'    => 'webmcp-field-lab',
                'title'   => 'Agent SNR — Overview',
                'content' => '[wmcp_judge_landing]',
            ),
            array(
                'slug'    => 'storefront-demo',
                'title'   => 'Agent-ready Storefront',
                'content' => '[wmcp_storefront_demo]',
            ),
            array(
                'slug'    => 'agentops-demo',
                'title'   => 'Agent SNR — Monitor',
                'content' => '[wmcp_agentops_demo]',
            ),
            array(
                'slug'    => 'webmcp-health',
                'title'   => 'WebMCP Readiness',
                'content' => '[wmcp_health]',
            ),
        );
    }

    public function register_assets(): void
    {
        $version = defined('WMCP_AGENTOPS_VERSION') ? WMCP_AGENTOPS_VERSION : '0.1.0';
        $base     = defined('WMCP_AGENTOPS_URL') ? WMCP_AGENTOPS_URL : plugin_dir_url(dirname(__DIR__, 2) . '/wmcp-agentops.php');

        wp_register_style('wmcp-field-lab', $base . 'assets/css/field-lab.css', array(), $version);
        wp_register_style('wmcp-storefront-demo', $base . 'assets/css/storefront-demo.css', array('wmcp-field-lab'), $version);
        wp_register_style('wmcp-dashboard', $base . 'assets/css/dashboard.css', array('wmcp-field-lab'), $version);
        wp_register_style('wmcp-readiness', $base . 'assets/css/readiness.css', array('wmcp-field-lab'), $version);

        wp_register_script('wmcp-webmcp-runtime', $base . 'assets/js/webmcp-runtime.js', array(), $version, true);
        wp_register_script('wmcp-surface-runtime', $base . 'assets/js/agentops-runtime.js', array('wmcp-webmcp-runtime'), $version, true);
        wp_register_script('wmcp-storefront-ui', $base . 'assets/js/storefront-ui.js', array('wmcp-surface-runtime'), $version, true);
        wp_register_script('wmcp-dashboard', $base . 'assets/js/dashboard.js', array('wmcp-surface-runtime'), $version, true);
    }

    public function enqueue_detected_surface(): void
    {
        if (! is_singular()) {
            return;
        }

        global $post;
        if (! $post instanceof WP_Post) {
            return;
        }

        foreach (self::SHORTCODES as $shortcode => $surface) {
            if (has_shortcode((string) $post->post_content, $shortcode)) {
                $this->enqueue_surface($surface);
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function render_landing(array $attributes = array()): string
    {
        unset($attributes);
        $this->enqueue_surface('landing');

        return $this->render_template('judge-landing.php', $this->base_view());
    }

    /**
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function render_storefront(array $attributes = array()): string
    {
        unset($attributes);
        $this->enqueue_surface('storefront');
        $view             = $this->base_view();
        $view['products'] = $this->products();
        $view['cart_url'] = function_exists('wc_get_cart_url') ? (string) wc_get_cart_url() : home_url('/cart/');
        $view['shop_url'] = function_exists('wc_get_page_permalink')
            ? (string) wc_get_page_permalink('shop')
            : home_url('/shop/');

        return $this->render_template('storefront-demo.php', $view);
    }

    /**
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function render_agentops(array $attributes = array()): string
    {
        unset($attributes);
        $this->enqueue_surface('agentops');
        $view               = $this->base_view();
        $governance         = $this->public_governance_snapshot();
        $view['is_admin']   = false;
        $view['tools']      = $governance['tools'];
        $view['governance'] = $governance;

        return $this->render_template('agentops-demo.php', $view);
    }

    /**
     * @param array<string, mixed> $attributes Shortcode attributes.
     */
    public function render_health(array $attributes = array()): string
    {
        unset($attributes);
        $this->enqueue_surface('health');

        return $this->render_template('health.php', $this->base_view());
    }

    private function enqueue_surface(string $surface): void
    {
        if (isset($this->enqueued_surfaces[$surface])) {
            return;
        }
        $this->enqueued_surfaces[$surface] = true;

        $style = match ($surface) {
            'storefront' => 'wmcp-storefront-demo',
            'agentops'   => 'wmcp-dashboard',
            default      => 'wmcp-readiness',
        };

        wp_enqueue_style($style);
        wp_enqueue_script('wmcp-webmcp-runtime');
        wp_enqueue_script('wmcp-surface-runtime');

        if ('storefront' === $surface) {
            wp_enqueue_script('wmcp-storefront-ui');
        } elseif ('agentops' === $surface) {
            wp_enqueue_script('wmcp-dashboard');
        }

        $runtime_surface = 'agentops' === $surface ? 'agentops' : 'storefront';
        $config          = $this->runtime_config($runtime_surface);
        $config['autoStart'] = in_array($surface, array('storefront', 'agentops'), true);
        wp_add_inline_script(
            'wmcp-webmcp-runtime',
            'window.wmcpConfig = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }

    /**
     * Runtime configuration intentionally contains only public REST locations,
     * the surface, and a non-secret site identifier. Session/CSRF material is
     * supplied by the dynamic manifest.
     *
     * @return array<string, string>
     */
    private function runtime_config(string $surface): array
    {
        $namespace = untrailingslashit(rest_url('wmcp-agentops/v1'));

        return array(
            'manifestUrl'     => add_query_arg('surface', $surface, $namespace . '/manifest'),
            'executionBaseUrl' => untrailingslashit($namespace),
            'healthUrl'       => $namespace . '/health',
            'resetUrl'        => $namespace . '/demo/reset',
            'sessionUrl'      => $namespace . '/session',
            'surface'         => $surface,
            'siteId'          => (string) get_option('wmcp_agentops_site_id', ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function base_view(): array
    {
        return array(
            'site_name'      => (string) get_bloginfo('name'),
            'landing_url'    => home_url('/webmcp-field-lab/'),
            'storefront_url' => home_url('/storefront-demo/'),
            'agentops_url'   => home_url('/agentops-demo/'),
            'health_url'     => home_url('/webmcp-health/'),
            'playground_url' => $this->optional_external_url('wmcp_agentops_playground_url'),
            'repository_url' => $this->optional_external_url('wmcp_agentops_repository_url'),
            'release_url'    => $this->optional_external_url('wmcp_agentops_release_url'),
            'demo_mode'      => DemoMode::enabled(),
        );
    }

    private function optional_external_url(string $option): string
    {
        $url = esc_url_raw((string) get_option($option, ''), array('https'));

        return is_string($url) ? $url : '';
    }

    /**
     * @param array<string, mixed> $view Escaped by the template at output time.
     */
    private function render_template(string $template, array $view): string
    {
        $path = (defined('WMCP_AGENTOPS_PATH') ? WMCP_AGENTOPS_PATH : dirname(__DIR__, 2) . '/') . 'templates/' . $template;
        if (! is_readable($path)) {
            return '';
        }

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        $specs = array(
            array('slug' => 'alpineflow-24', 'name' => 'AlpineFlow 24 Pack', 'price' => '$109', 'water' => 'IPX6', 'capacity' => '24L', 'return_days' => 45, 'stock' => 'In stock'),
            array('slug' => 'raintrail-20', 'name' => 'RainTrail 20 Pack', 'price' => '$89', 'water' => 'IPX4', 'capacity' => '20L', 'return_days' => 30, 'stock' => 'In stock'),
            array('slug' => 'coastrunner-26', 'name' => 'CoastRunner 26 Pack', 'price' => '$119', 'water' => 'IPX5', 'capacity' => '26L', 'return_days' => 30, 'stock' => 'Low stock'),
            array('slug' => 'summitshell-32', 'name' => 'SummitShell 32 Pack', 'price' => '$139', 'water' => 'IPX6', 'capacity' => '32L', 'return_days' => 45, 'stock' => 'In stock'),
            array('slug' => 'urbandry-18', 'name' => 'UrbanDry 18 Pack', 'price' => '$79', 'water' => 'Water-resistant', 'capacity' => '18L', 'return_days' => 30, 'stock' => 'In stock'),
            array('slug' => 'canyonday-22', 'name' => 'CanyonDay 22 Pack', 'price' => '$99', 'water' => 'IPX3', 'capacity' => '22L', 'return_days' => 14, 'stock' => 'In stock'),
            array('slug' => 'ridgeline-28', 'name' => 'RidgeLine 28 Pack', 'price' => '$129', 'water' => 'IPX5', 'capacity' => '28L', 'return_days' => 30, 'stock' => 'In stock'),
            array('slug' => 'harborlite-16', 'name' => 'HarborLite 16 Pack', 'price' => '$69', 'water' => 'IPX4', 'capacity' => '16L', 'return_days' => 30, 'stock' => 'In stock'),
            array('slug' => 'terraroll-25', 'name' => 'TerraRoll 25 Pack', 'price' => '$115', 'water' => 'IPX4', 'capacity' => '25L', 'return_days' => 30, 'stock' => 'Out of stock'),
            array('slug' => 'switchback-sling', 'name' => 'Switchback Sling', 'price' => '$49', 'water' => 'IPX3', 'capacity' => '8L', 'return_days' => 30, 'stock' => 'In stock'),
            array('slug' => 'drypod-organizer', 'name' => 'DryPod Organizer', 'price' => '$29', 'water' => 'IPX6', 'capacity' => '4L', 'return_days' => 45, 'stock' => 'In stock'),
            array('slug' => 'trailcover-rain-shell', 'name' => 'TrailCover Rain Shell', 'price' => '$25', 'water' => 'Waterproof cover', 'capacity' => '—', 'return_days' => 30, 'stock' => 'In stock'),
        );

        $base = defined('WMCP_AGENTOPS_URL') ? WMCP_AGENTOPS_URL : plugin_dir_url(dirname(__DIR__, 2) . '/wmcp-agentops.php');
        foreach ($specs as &$spec) {
            $product = null;
            $post    = get_page_by_path((string) $spec['slug'], OBJECT, 'product');
            if ($post instanceof WP_Post && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
            }

            $spec['id']        = is_object($product) && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
            $spec['url']       = is_object($product) && method_exists($product, 'get_permalink')
                ? (string) $product->get_permalink()
                : (function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url('/shop/'));
            $spec['image_url'] = $base . 'assets/images/products/' . $spec['slug'] . '.svg';

            if (is_object($product)) {
                $spec['name'] = method_exists($product, 'get_name')
                    ? wp_strip_all_tags((string) $product->get_name(), true)
                    : $spec['name'];
                if (method_exists($product, 'get_price') && is_numeric($product->get_price())) {
                    $price = function_exists('wc_price') ? wc_price((float) $product->get_price()) : '$' . $product->get_price();
                    $spec['price'] = html_entity_decode(wp_strip_all_tags((string) $price), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (method_exists($product, 'get_meta')) {
                    $water      = $product->get_meta('_wmcp_water_rating', true);
                    $capacity   = $product->get_meta('_wmcp_capacity_liters', true);
                    $return_days = $product->get_meta('_wmcp_return_days', true);
                    $spec['water'] = '' !== $water ? sanitize_text_field((string) $water) : $spec['water'];
                    $spec['capacity'] = is_numeric($capacity) ? (string) (float) $capacity . 'L' : $spec['capacity'];
                    $spec['return_days'] = is_numeric($return_days) ? (int) $return_days : $spec['return_days'];
                }
                if (method_exists($product, 'get_stock_status')) {
                    $spec['stock'] = match ((string) $product->get_stock_status()) {
                        'instock' => 'In stock',
                        'onbackorder' => 'Available on backorder',
                        default => 'Out of stock',
                    };
                }
            }
        }
        unset($spec);

        return $specs;
    }

    /**
     * Public policy facts omit private/session identifiers. A session override
     * is reflected client-side after the authorized governance tool returns.
     *
     * @return list<array<string, mixed>>
     */
    private function public_governance_snapshot(): array
    {
        $catalog          = new ToolCatalog();
        $policies         = new PolicyStore();
        $kill_switch      = new KillSwitch();
        $plugin_enabled   = $policies->plugin_enabled();
        $emergency_stop   = $kill_switch->active();
        $tools            = array();

        foreach ($catalog->surface('storefront') as $definition) {
            $name             = (string) $definition['name'];
            $site_enabled     = $plugin_enabled && ! $emergency_stop && $policies->enabled($name);
            $tools[] = array(
                'name'         => $name,
                'risk_class'   => (string) $definition['risk_class'],
                'enabled'      => $site_enabled,
                'site_enabled' => $site_enabled,
            );
        }

        return array(
            'global_status' => $emergency_stop
                ? 'emergency_stop'
                : ($plugin_enabled ? 'ready' : 'webmcp_disabled'),
            'tools'         => $tools,
        );
    }
}
