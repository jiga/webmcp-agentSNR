<?php

/**
 * Validation and allowlist normalization for analytics events.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Analytics;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use WPWebMCP\AgentSNR\Contract\EventName;
use WPWebMCP\AgentSNR\Contract\RiskClass;
use WPWebMCP\AgentSNR\Privacy\Redactor;

final class EventSchema
{
    private const EVENTS = array(
        EventName::WORKFLOW_STARTED,
        EventName::WORKFLOW_COMPLETED,
        EventName::WORKFLOW_ABANDONED,
        EventName::WORKFLOW_EXPIRED,
        EventName::TOOL_CALL_STARTED,
        EventName::TOOL_CALL_SUCCEEDED,
        EventName::TOOL_CALL_FAILED,
        EventName::TOOL_CALL_CANCELLED,
        EventName::TOOL_CALL_DENIED,
        EventName::POLICY_EVALUATED,
        EventName::POLICY_CHANGED,
        EventName::KILL_SWITCH_CHANGED,
        EventName::PRODUCT_SEARCHED,
        EventName::PRODUCT_VIEWED,
        EventName::PRODUCTS_COMPARED,
        EventName::POLICY_VIEWED,
        EventName::CART_CHANGED,
        EventName::CHECKOUT_HANDOFF,
        EventName::ORDER_CREATED,
        EventName::ORDER_PAID,
        EventName::ORDER_CANCELLED,
        EventName::ORDER_REFUNDED,
        EventName::CAPABILITY_GAP_REPORTED,
        EventName::OPPORTUNITY_DETECTED,
        EventName::AGENT_FEEDBACK_REPORTED,
        EventName::DIAGNOSTICS_COMPLETED,
        EventName::DEMO_RESET,
    );

    private const COMMON_PROPERTIES = array(
        'actor_type',
        'agent_surface',
        'analytics_consent',
        'cart_item_count',
        'cart_revision',
        'checkout_ready',
        'client_name',
        'client_version',
        'confidence',
        'diagnostic_count',
        'demand_key',
        'enabled',
        'evidence_count',
        'evidence_status',
        'feedback_id',
        'feedback_outcome',
        'feedback_type',
        'gap_id',
        'guide_version',
        'highest_water_rating',
        'in_stock_match_count',
        'manifest_revision',
        'mutation',
        'metric_count',
        'missing_fact_count',
        'order_status',
        'out_of_stock_match_count',
        'policy_type',
        'previous_enabled',
        'protocol',
        'quantity',
        'reason_code',
        'recovery_event',
        'refund_id',
        'related_product_id',
        'requested_capability',
        'result_count',
        'signal_category',
        'signal_code',
        'signal_id',
        'signal_key',
        'signal_source',
        'scope',
        'source',
        'status',
        'step_id',
        'suggested_action',
        'target_tool',
        'tool_count',
    );

    private Redactor $redactor;

    public function __construct(?Redactor $redactor = null)
    {
        $this->redactor = $redactor ?? new Redactor();
    }

    public function workflow_id(string $workflow_id): string
    {
        $workflow_id = strtoupper(trim($workflow_id));
        if (1 !== preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $workflow_id)) {
            throw new InvalidArgumentException('Workflow ID must be a 26-character ULID.');
        }

        return $workflow_id;
    }

    public function event_id(string $event_id): string
    {
        $event_id = trim($event_id);
        if (1 !== preg_match('/\Aevt_[0-9A-HJKMNP-TV-Z]{26}\z/', $event_id)) {
            throw new InvalidArgumentException('Event ID must use the evt_ prefix followed by a ULID.');
        }

        return $event_id;
    }

    public function request_id(string $request_id): string
    {
        $request_id = strtolower(trim($request_id));
        if (1 !== preg_match('/\A(?:req_[0-9a-f]{32}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\z/', $request_id)) {
            throw new InvalidArgumentException('Request ID must be a supported 36-character identifier.');
        }

        return $request_id;
    }

    public function hash_hex(string $hash): string
    {
        $hash = strtolower(trim($hash));
        if (1 !== preg_match('/\A[0-9a-f]{64}\z/', $hash)) {
            throw new InvalidArgumentException('Scope hashes must be 64-character hexadecimal strings.');
        }

        return $hash;
    }

    public function surface(string $surface): string
    {
        $surface = strtolower(trim($surface));
        if (! in_array($surface, array('storefront', 'agentsnr'), true)) {
            throw new InvalidArgumentException('Unknown workflow surface.');
        }

        return $surface;
    }

    public function event_name(string $event_name): string
    {
        if (! in_array($event_name, self::EVENTS, true)) {
            throw new InvalidArgumentException('Unknown analytics event name.');
        }

        return $event_name;
    }

    public function terminal_event(string $event_name): string
    {
        if (! in_array($event_name, EventName::tool_terminal(), true)) {
            throw new InvalidArgumentException('The event is not a tool-call terminal event.');
        }

        return $event_name;
    }

    /**
     * @param array<string, mixed> $tool Raw tool metadata.
     * @return array{name: string, version: string, risk_class: string}
     */
    public function tool(array $tool): array
    {
        $name = isset($tool['name']) && is_string($tool['name']) ? trim($tool['name']) : '';
        if (1 !== preg_match('/\A[a-z][a-z0-9_]{0,127}\z/', $name)) {
            throw new InvalidArgumentException('Tool name is invalid.');
        }

        $version = isset($tool['version']) && is_string($tool['version']) ? trim($tool['version']) : '1.0.0';
        if ('' === $version || strlen($version) > 32 || 1 !== preg_match('/\A[0-9A-Za-z][0-9A-Za-z._+-]*\z/', $version)) {
            throw new InvalidArgumentException('Tool version is invalid.');
        }

        $risk = isset($tool['risk_class']) && is_string($tool['risk_class']) ? $tool['risk_class'] : RiskClass::READ;
        if (! in_array($risk, RiskClass::all(), true)) {
            throw new InvalidArgumentException('Tool risk class is invalid.');
        }

        return array(
            'name'       => $name,
            'version'    => $version,
            'risk_class' => $risk,
        );
    }

    /**
     * @param array<string, mixed> $outcome Raw event outcome and commerce facts.
     * @return array<string, mixed>
     */
    public function outcome(array $outcome, ?string $terminal_event = null): array
    {
        $normalized = array(
            'outcome'         => null,
            'duration_ms'     => null,
            'error_code'      => null,
            'http_status'     => null,
            'product_ids'     => array(),
            'currency'        => null,
            'value'           => null,
        );

        if (null !== $terminal_event) {
            $terminal_event        = $this->terminal_event($terminal_event);
            $normalized['outcome'] = $this->terminal_status($terminal_event);
        } elseif (isset($outcome['status']) && is_string($outcome['status'])) {
            $status = strtolower($outcome['status']);
            if (! in_array($status, array('success', 'failed', 'cancelled', 'denied'), true)) {
                throw new InvalidArgumentException('Event outcome status is invalid.');
            }
            $normalized['outcome'] = $status;
        }

        if (array_key_exists('duration_ms', $outcome) && null !== $outcome['duration_ms']) {
            if (! is_int($outcome['duration_ms']) || $outcome['duration_ms'] < 0 || $outcome['duration_ms'] > 86400000) {
                throw new InvalidArgumentException('Duration must be an integer between 0 and 86400000 milliseconds.');
            }
            $normalized['duration_ms'] = $outcome['duration_ms'];
        }

        if (isset($outcome['error_code']) && null !== $outcome['error_code']) {
            if (! is_string($outcome['error_code'])) {
                throw new InvalidArgumentException('Error code must be a string.');
            }
            $error_code = strtolower(trim($outcome['error_code']));
            if (1 !== preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/', $error_code)) {
                throw new InvalidArgumentException('Error code is invalid.');
            }
            $normalized['error_code'] = $error_code;
        }

        if (EventName::TOOL_CALL_SUCCEEDED === $terminal_event) {
            $normalized['error_code'] = null;
        }

        if (array_key_exists('http_status', $outcome) && null !== $outcome['http_status']) {
            if (! is_int($outcome['http_status']) || $outcome['http_status'] < 100 || $outcome['http_status'] > 599) {
                throw new InvalidArgumentException('HTTP status is invalid.');
            }
            $normalized['http_status'] = $outcome['http_status'];
        }

        if (isset($outcome['product_ids'])) {
            if (! is_array($outcome['product_ids'])) {
                throw new InvalidArgumentException('Product IDs must be an array.');
            }
            foreach (array_slice($outcome['product_ids'], 0, 20) as $product_id) {
                if (! is_int($product_id) || $product_id < 1) {
                    throw new InvalidArgumentException('Product IDs must be positive integers.');
                }
                $normalized['product_ids'][] = $product_id;
            }
            $normalized['product_ids'] = array_values(array_unique($normalized['product_ids']));
        }

        if (isset($outcome['currency']) && null !== $outcome['currency']) {
            if (! is_string($outcome['currency']) || 1 !== preg_match('/\A[A-Z]{3}\z/', strtoupper($outcome['currency']))) {
                throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
            }
            $normalized['currency'] = strtoupper($outcome['currency']);
        }

        if (array_key_exists('value', $outcome) && null !== $outcome['value']) {
            if (! is_int($outcome['value']) && ! is_float($outcome['value']) && ! is_string($outcome['value'])) {
                throw new InvalidArgumentException('Commerce value must be numeric.');
            }
            if (! is_numeric($outcome['value'])) {
                throw new InvalidArgumentException('Commerce value must be numeric.');
            }
            $value = (float) $outcome['value'];
            if (! is_finite($value) || abs($value) > 999999999999.999999) {
                throw new InvalidArgumentException('Commerce value is outside the supported range.');
            }
            $normalized['value'] = number_format($value, 6, '.', '');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $properties Raw properties.
     * @return array<string, mixed>
     */
    public function properties(string $event_name, array $properties): array
    {
        $this->event_name($event_name);

        return $this->redactor->properties($properties, self::COMMON_PROPERTIES);
    }

    public function mysql_timestamp(string $timestamp): string
    {
        if (1 !== preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $timestamp)) {
            throw new InvalidArgumentException('Timestamp must be an ordinary UTC DATETIME value.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $timestamp, new DateTimeZone('UTC'));
        if (false === $date || $date->format('Y-m-d H:i:s') !== $timestamp) {
            throw new InvalidArgumentException('Timestamp is not a valid UTC DATETIME value.');
        }

        return $timestamp;
    }

    public function dedupe_key(string $purpose, string $workflow_id, string $identity): string
    {
        $workflow_id = $this->workflow_id($workflow_id);
        if ('' === trim($purpose) || '' === trim($identity)) {
            throw new InvalidArgumentException('Dedupe purpose and identity are required.');
        }

        return hash('sha256', "wmcp-agentsnr:v1\0{$purpose}\0{$workflow_id}\0{$identity}");
    }

    private function terminal_status(string $event_name): string
    {
        return match ($event_name) {
            EventName::TOOL_CALL_SUCCEEDED => 'success',
            EventName::TOOL_CALL_FAILED => 'failed',
            EventName::TOOL_CALL_CANCELLED => 'cancelled',
            EventName::TOOL_CALL_DENIED => 'denied',
            default => throw new InvalidArgumentException('Unknown terminal status.'),
        };
    }
}
