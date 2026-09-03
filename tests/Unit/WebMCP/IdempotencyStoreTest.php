<?php

/**
 * Request identity and replay-state tests.
 *
 * @package WPWebMCP\AgentSNR\Tests
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Tests\Unit\WebMCP;

use PHPUnit\Framework\TestCase;
use WPWebMCP\AgentSNR\WebMCP\IdempotencyStore;

final class IdempotencyStoreTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wmcp_test_options'] = array();
        $GLOBALS['wmcp_test_fail_option_update'] = false;
    }

    protected function tearDown(): void
    {
        $GLOBALS['wmcp_test_fail_option_update'] = false;
        unset($GLOBALS['wpdb']);
    }

    public function test_request_is_bound_once_to_workflow_tool_and_input_fingerprint(): void
    {
        $store       = new IdempotencyStore();
        $session     = str_repeat('a', 64);
        $workflow    = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request     = 'req_' . str_repeat('b', 32);
        $fingerprint = hash('sha256', 'search_products|{"query":"pack"}');

        self::assertTrue($store->begin($session, $workflow, $request, 'search_products', $fingerprint));
        self::assertFalse($store->begin($session, $workflow, $request, 'get_cart', hash('sha256', 'get_cart|{}')));

        $state = $store->get($session, $workflow, $request);
        self::assertSame('search_products', $state['tool_name']);
        self::assertSame($fingerprint, $state['fingerprint']);
        self::assertSame('pending', $state['state']);
    }

    public function test_completed_replay_retains_original_fingerprint_and_response(): void
    {
        $store       = new IdempotencyStore();
        $session     = str_repeat('c', 64);
        $workflow    = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request     = 'req_' . str_repeat('d', 32);
        $fingerprint = hash('sha256', 'get_cart|{}');
        $body        = array('ok' => true, 'result' => array('item_count' => 0));

        $store->begin($session, $workflow, $request, 'get_cart', $fingerprint);
        $store->complete($session, $workflow, $request, 200, $body);
        $state = $store->get($session, $workflow, $request);

        self::assertSame('complete', $state['state']);
        self::assertSame($fingerprint, $state['fingerprint']);
        self::assertSame(200, $state['status']);
        self::assertSame($body, $state['body']);
    }

    public function test_expired_state_is_deleted_on_read(): void
    {
        $store    = new IdempotencyStore();
        $session  = str_repeat('e', 64);
        $workflow = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request  = 'req_' . str_repeat('f', 32);
        $store->begin($session, $workflow, $request, 'get_cart', hash('sha256', 'get_cart|{}'));

        $key = array_key_first($GLOBALS['wmcp_test_options']);
        $GLOBALS['wmcp_test_options'][$key]['expires_at'] = time() - 1;

        self::assertNull($store->get($session, $workflow, $request));
        self::assertArrayNotHasKey($key, $GLOBALS['wmcp_test_options']);
    }

    public function test_execution_guard_survives_ability_return_state_write_failure(): void
    {
        $store       = new IdempotencyStore();
        $session     = str_repeat('1', 64);
        $workflow    = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request     = 'req_' . str_repeat('2', 32);
        $fingerprint = hash('sha256', 'add_to_cart|{"product_id":7}');

        self::assertTrue($store->begin($session, $workflow, $request, 'add_to_cart', $fingerprint));
        self::assertTrue($store->mark_executing($session, $workflow, $request));

        $executing = $store->get($session, $workflow, $request);
        self::assertSame('executing', $executing['state']);
        self::assertArrayNotHasKey('expires_at', $executing);
        self::assertSame($executing['created_at'] + (2 * DAY_IN_SECONDS), $executing['gc_after']);

        $GLOBALS['wmcp_test_fail_option_update'] = true;
        self::assertFalse($store->mark_executed($session, $workflow, $request));
        self::assertSame('executing', $store->get($session, $workflow, $request)['state']);
        self::assertFalse($store->begin($session, $workflow, $request, 'add_to_cart', $fingerprint));
    }

    public function test_failed_response_cache_write_keeps_executed_request_reserved(): void
    {
        $store       = new IdempotencyStore();
        $session     = str_repeat('3', 64);
        $workflow    = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request     = 'req_' . str_repeat('4', 32);
        $fingerprint = hash('sha256', 'checkout_handoff|{"expected_cart_revision":"rev_1"}');

        self::assertTrue($store->begin($session, $workflow, $request, 'checkout_handoff', $fingerprint));
        self::assertTrue($store->mark_executing($session, $workflow, $request));
        self::assertTrue($store->mark_executed($session, $workflow, $request));

        $GLOBALS['wmcp_test_fail_option_update'] = true;
        self::assertFalse($store->complete($session, $workflow, $request, 200, array('ok' => true)));
        self::assertSame('executed', $store->get($session, $workflow, $request)['state']);
        self::assertFalse($store->release($session, $workflow, $request));
        self::assertFalse($store->begin($session, $workflow, $request, 'checkout_handoff', $fingerprint));

        $GLOBALS['wmcp_test_fail_option_update'] = false;
        self::assertTrue($store->seal($session, $workflow, $request, 'response_cache_failed'));
        self::assertSame('sealed', $store->get($session, $workflow, $request)['state']);
    }

    public function test_expired_response_is_compacted_to_tombstone_instead_of_released(): void
    {
        $store       = new IdempotencyStore();
        $session     = str_repeat('5', 64);
        $workflow    = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $request     = 'req_' . str_repeat('6', 32);
        $fingerprint = hash('sha256', 'update_cart_quantity|{"quantity":2}');
        $body        = array('ok' => true, 'result' => array('quantity' => 2));

        self::assertTrue($store->begin($session, $workflow, $request, 'update_cart_quantity', $fingerprint));
        self::assertTrue($store->mark_executing($session, $workflow, $request));
        self::assertTrue($store->mark_executed($session, $workflow, $request));
        self::assertTrue($store->complete($session, $workflow, $request, 200, $body));

        $key = array_key_first($GLOBALS['wmcp_test_options']);
        $GLOBALS['wmcp_test_options'][$key]['expires_at'] = time() - 1;
        $state = $store->get($session, $workflow, $request);

        self::assertSame('sealed', $state['state']);
        self::assertArrayNotHasKey('body', $state);
        self::assertArrayNotHasKey('status', $state);
        self::assertFalse($store->begin($session, $workflow, $request, 'update_cart_quantity', $fingerprint));
    }

    public function test_cleanup_removes_guard_only_after_demo_session_grace(): void
    {
        $store    = new IdempotencyStore();
        $session  = str_repeat('7', 64);
        $workflow = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $expired  = 'req_' . str_repeat('8', 32);
        $active   = 'req_' . str_repeat('9', 32);

        self::assertTrue($store->begin($session, $workflow, $expired, 'add_to_cart', hash('sha256', 'expired')));
        self::assertTrue($store->mark_executing($session, $workflow, $expired));
        self::assertTrue($store->begin($session, $workflow, $active, 'add_to_cart', hash('sha256', 'active')));
        self::assertTrue($store->mark_executing($session, $workflow, $active));

        $keys = array_keys($GLOBALS['wmcp_test_options']);
        $GLOBALS['wmcp_test_options'][$keys[0]]['gc_after'] = time() - 1;
        $GLOBALS['wpdb'] = new class {
            public string $options = 'wp_options';

            public function esc_like(string $value): string
            {
                return $value;
            }

            /** @param mixed ...$args Query arguments. */
            public function prepare(string $query, ...$args): string
            {
                unset($args);

                return $query;
            }

            /** @return list<string> */
            public function get_col(string $query): array
            {
                unset($query);

                return array_keys($GLOBALS['wmcp_test_options']);
            }
        };

        self::assertSame(1, $store->cleanup());
        self::assertNull($store->get($session, $workflow, $expired));
        self::assertSame('executing', $store->get($session, $workflow, $active)['state']);
    }
}
