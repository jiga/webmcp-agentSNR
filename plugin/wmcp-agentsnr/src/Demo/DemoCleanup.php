<?php

/**
 * Bounded garbage collection; authorization never depends on cron timing.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Demo;

use Closure;
use Throwable;
use WPWebMCP\AgentSNR\Analytics\WorkflowService;
use WPWebMCP\AgentSNR\Policy\SessionPolicyStore;
use WPWebMCP\AgentSNR\Policy\RateLimiter;
use WPWebMCP\AgentSNR\Support\Logger;
use WPWebMCP\AgentSNR\WebMCP\IdempotencyStore;

final class DemoCleanup
{
    private const RETENTION_SECONDS = DAY_IN_SECONDS;
    private const ORDER_BATCH_SIZE = 20;

    /** @var Closure(array<string, mixed>): mixed|null */
    private readonly ?Closure $order_query;

    /**
     * @param callable(array<string, mixed>):mixed|null $order_query Optional WooCommerce query seam for focused tests.
     */
    public function __construct(
        private readonly WorkflowService $workflows,
        private readonly IdempotencyStore $idempotency,
        private readonly ?DemoSession $sessions = null,
        private readonly ?RateLimiter $rate_limiter = null,
        ?callable $order_query = null
    ) {
        $this->order_query = null === $order_query ? null : Closure::fromCallable($order_query);
    }

    public function hooks(): void
    {
        add_action('wmcp_agentsnr_cleanup', array($this, 'run'));
        add_action('wmcp_agentsnr_cleanup_session', array($this, 'cleanup_session'), 10, 1);
    }

    /**
     * @return array{workflows_expired: int, idempotency_deleted: int, rate_limits_deleted: int, sessions_cleaned: int}
     */
    public function run(): array
    {
        $sessions_cleaned = 0;
        foreach ($this->expired_session_hashes() as $session_hash) {
            $result = $this->cleanup_session($session_hash);
            if ($result['completed']) {
                ++$sessions_cleaned;
            }
        }

        return array(
            'workflows_expired'   => $this->workflows->expire_stale(),
            'idempotency_deleted' => $this->idempotency->cleanup(),
            'rate_limits_deleted' => ($this->rate_limiter ?? new RateLimiter())->cleanup(),
            'sessions_cleaned'    => $sessions_cleaned,
        );
    }

    /**
     * Delete only positively scoped, expired demo data in bounded batches.
     *
     * @return array{
     *     completed:bool,
     *     order_cleanup_status:string,
     *     orders_found:int,
     *     orders_deleted:int,
     *     orders_remaining:int,
     *     workflows_deleted:int
     * }
     */
    public function cleanup_session(string $session_hash_hex): array
    {
        if (1 !== preg_match('/\A[a-f0-9]{64}\z/', $session_hash_hex)) {
            return $this->blocked_result('invalid_session');
        }

        $sessions = $this->sessions ?? new DemoSession();
        if ($sessions->hash_active($session_hash_hex)) {
            return $this->blocked_result('active_session');
        }

        $order_cleanup = $this->cleanup_demo_orders($session_hash_hex);
        if ('confirmed_empty' !== $order_cleanup['status']) {
            $this->reschedule($session_hash_hex);

            return array(
                'completed'            => false,
                'order_cleanup_status' => $order_cleanup['status'],
                'orders_found'         => $order_cleanup['found'],
                'orders_deleted'       => $order_cleanup['deleted'],
                'orders_remaining'     => $order_cleanup['remaining'],
                'workflows_deleted'    => 0,
            );
        }

        global $wpdb;
        $workflow_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wmcp_workflows WHERE demo_session_hash = %s ORDER BY id ASC LIMIT 500",
                $session_hash_hex
            )
        );
        if (! is_array($workflow_ids)) {
            $this->reschedule($session_hash_hex);

            return $this->cleanup_result(false, $order_cleanup, 0);
        }

        $workflows_deleted = 0;
        foreach ($workflow_ids as $workflow_id) {
            $links_deleted = $wpdb->delete(
                $wpdb->prefix . 'wmcp_order_links',
                array('workflow_id' => (string) $workflow_id),
                array('%s')
            );
            if (false === $links_deleted) {
                $this->reschedule($session_hash_hex);

                return $this->cleanup_result(false, $order_cleanup, $workflows_deleted);
            }

            $events_deleted = $wpdb->delete(
                $wpdb->prefix . 'wmcp_events',
                array('workflow_id' => (string) $workflow_id),
                array('%s')
            );
            if (false === $events_deleted) {
                $this->reschedule($session_hash_hex);

                return $this->cleanup_result(false, $order_cleanup, $workflows_deleted);
            }

            $workflow_deleted = $wpdb->delete(
                $wpdb->prefix . 'wmcp_workflows',
                array('id' => (string) $workflow_id, 'demo_session_hash' => $session_hash_hex),
                array('%s', '%s')
            );
            if (false === $workflow_deleted) {
                $this->reschedule($session_hash_hex);

                return $this->cleanup_result(false, $order_cleanup, $workflows_deleted);
            }
            $workflows_deleted += (int) $workflow_deleted;
        }
        if (500 === count($workflow_ids)) {
            $this->reschedule($session_hash_hex);

            return $this->cleanup_result(false, $order_cleanup, $workflows_deleted);
        }
        $gaps_deleted = $wpdb->delete(
            $wpdb->prefix . 'wmcp_capability_gaps',
            array('demo_session_hash' => $session_hash_hex),
            array('%s')
        );
        if (false === $gaps_deleted) {
            $this->reschedule($session_hash_hex);

            return $this->cleanup_result(false, $order_cleanup, $workflows_deleted);
        }

        (new SessionPolicyStore())->clear($session_hash_hex);
        delete_transient('wmcp_demo_orders_' . $session_hash_hex);
        $sessions->expire_hash($session_hash_hex);

        return $this->cleanup_result(true, $order_cleanup, $workflows_deleted);
    }

    /** @return list<string> */
    private function expired_session_hashes(): array
    {
        global $wpdb;

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT demo_session_hash, MIN(last_event_at) AS oldest_event
                 FROM {$wpdb->prefix}wmcp_workflows
                 WHERE demo_session_hash IS NOT NULL
                 GROUP BY demo_session_hash
                 HAVING MAX(last_event_at) < %s
                 ORDER BY oldest_event ASC
                 LIMIT 20",
                gmdate('Y-m-d H:i:s', time() - self::RETENTION_SECONDS)
            )
        );

        return array_values(
            array_filter(
                array_map('strval', is_array($rows) ? $rows : array()),
                static fn (string $hash): bool => 1 === preg_match('/\A[a-f0-9]{64}\z/', $hash)
            )
        );
    }

    /**
     * Delete one bounded order batch, then independently prove the scoped query is empty.
     *
     * Workflow and session data must remain intact unless WooCommerce can answer both
     * queries, every attempted deletion succeeds, and the confirmation query is empty.
     *
     * @return array{status:string,found:int,deleted:int,remaining:int}
     */
    private function cleanup_demo_orders(string $session_hash_hex): array
    {
        $batch = $this->query_demo_orders($session_hash_hex, self::ORDER_BATCH_SIZE);
        if (! $batch['available']) {
            return array('status' => 'woocommerce_unavailable', 'found' => 0, 'deleted' => 0, 'remaining' => 0);
        }

        $found         = count($batch['orders']);
        $deleted       = 0;
        $delete_failed = false;

        foreach ($batch['orders'] as $order) {
            try {
                if (
                    ! is_object($order)
                    || ! method_exists($order, 'get_meta')
                    || ! method_exists($order, 'delete')
                    || 'yes' !== (string) $order->get_meta('_wmcp_demo_order', true)
                    || ! hash_equals($session_hash_hex, (string) $order->get_meta('_wmcp_demo_session_hash', true))
                ) {
                    $delete_failed = true;
                    continue;
                }

                $result = $order->delete(true);
            } catch (Throwable $error) {
                unset($error);
                Logger::error('demo_cleanup_order_delete_failed');
                $delete_failed = true;
                continue;
            }

            if (false === $result) {
                $delete_failed = true;
            } else {
                ++$deleted;
            }
        }

        $confirmation = $this->query_demo_orders($session_hash_hex, 1);
        if (! $confirmation['available']) {
            return array(
                'status'    => 'verification_unavailable',
                'found'     => $found,
                'deleted'   => $deleted,
                'remaining' => 0,
            );
        }

        $remaining = count($confirmation['orders']);
        if ($delete_failed) {
            return array(
                'status'    => 'delete_failed',
                'found'     => $found,
                'deleted'   => $deleted,
                'remaining' => $remaining,
            );
        }
        if (0 < $remaining) {
            return array(
                'status'    => 'orders_remaining',
                'found'     => $found,
                'deleted'   => $deleted,
                'remaining' => $remaining,
            );
        }

        return array('status' => 'confirmed_empty', 'found' => $found, 'deleted' => $deleted, 'remaining' => 0);
    }

    /**
     * @return array{available:bool,orders:list<mixed>}
     */
    private function query_demo_orders(string $session_hash_hex, int $limit): array
    {
        if (null === $this->order_query && ! function_exists('wc_get_orders')) {
            return array('available' => false, 'orders' => array());
        }

        $arguments = array(
            'limit'      => $limit,
            'return'     => 'objects',
            'orderby'    => 'ID',
            'order'      => 'ASC',
            'meta_query' => array(
                'relation' => 'AND',
                array('key' => '_wmcp_demo_order', 'value' => 'yes'),
                array('key' => '_wmcp_demo_session_hash', 'value' => $session_hash_hex),
            ),
        );

        try {
            $orders = null === $this->order_query
                ? wc_get_orders($arguments)
                : ($this->order_query)($arguments);
        } catch (Throwable $error) {
            unset($error);
            Logger::error('demo_cleanup_order_query_failed');

            return array('available' => false, 'orders' => array());
        }

        return is_array($orders)
            ? array('available' => true, 'orders' => array_values($orders))
            : array('available' => false, 'orders' => array());
    }

    /**
     * @return array{
     *     completed:bool,
     *     order_cleanup_status:string,
     *     orders_found:int,
     *     orders_deleted:int,
     *     orders_remaining:int,
     *     workflows_deleted:int
     * }
     */
    private function blocked_result(string $status): array
    {
        return array(
            'completed'            => false,
            'order_cleanup_status' => $status,
            'orders_found'         => 0,
            'orders_deleted'       => 0,
            'orders_remaining'     => 0,
            'workflows_deleted'    => 0,
        );
    }

    /**
     * @param array{status:string,found:int,deleted:int,remaining:int} $order_cleanup Order cleanup state.
     * @return array{
     *     completed:bool,
     *     order_cleanup_status:string,
     *     orders_found:int,
     *     orders_deleted:int,
     *     orders_remaining:int,
     *     workflows_deleted:int
     * }
     */
    private function cleanup_result(bool $completed, array $order_cleanup, int $workflows_deleted): array
    {
        return array(
            'completed'            => $completed,
            'order_cleanup_status' => $order_cleanup['status'],
            'orders_found'         => $order_cleanup['found'],
            'orders_deleted'       => $order_cleanup['deleted'],
            'orders_remaining'     => $order_cleanup['remaining'],
            'workflows_deleted'    => $workflows_deleted,
        );
    }

    private function reschedule(string $session_hash_hex): void
    {
        $scheduled = wp_schedule_single_event(
            time() + MINUTE_IN_SECONDS,
            'wmcp_agentsnr_cleanup_session',
            array($session_hash_hex),
            true
        );
        if (is_wp_error($scheduled)) {
            Logger::error('demo_cleanup_reschedule_failed', array('error_code' => $scheduled->get_error_code()));
        }
    }
}
