<?php

/**
 * Semantic commerce-event recording for tool callbacks.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\WooCommerce;

use WPWebMCP\AgentSNR\Analytics\EventRecorder;

final class CommerceTelemetry
{
    public function __construct(
        private readonly SessionCorrelator $correlator,
        private readonly EventRecorder $events
    ) {
    }

    /**
     * @param list<int>            $product_ids Product IDs.
     * @param array<string, mixed> $properties Allowlisted event properties.
     * @return array<string, mixed>|null
     */
    public function record(
        string $event_name,
        string $activity,
        array $product_ids = array(),
        array $properties = array(),
        ?string $currency = null,
        ?float $value = null
    ): ?array {
        $this->correlator->touch($activity, $product_ids);
        $current = $this->correlator->current();
        if (null === $current) {
            return null;
        }

        $data = array(
            'product_ids' => array_values(array_unique(array_filter(array_map('intval', $product_ids)))),
            'properties'  => $properties,
        );
        if (is_string($current['request_id'])) {
            $data['request_id'] = $current['request_id'];
        }
        if (is_string($current['tool_name']) && '' !== $current['tool_name']) {
            $data['tool'] = array('name' => $current['tool_name']);
        }
        if (null !== $currency && '' !== $currency) {
            $data['currency'] = strtoupper($currency);
        }
        if (null !== $value) {
            $data['value'] = $value;
        }

        $dedupe = is_string($current['request_id']) ? 'semantic:' . $current['request_id'] : null;

        return $this->events->record($current['workflow_id'], $event_name, $data, $dedupe);
    }
}
