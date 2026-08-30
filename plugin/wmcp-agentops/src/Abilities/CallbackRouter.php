<?php

/**
 * Maps catalog callback IDs to first-party service methods.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\Abilities;

use InvalidArgumentException;
use WPWebMCP\AgentOps\WebMCP\ToolException;

final class CallbackRouter
{
    /** @var array<string, callable> */
    private array $callbacks = array();

    public function register(string $callback_id, callable $callback): void
    {
        if (isset($this->callbacks[$callback_id])) {
            throw new InvalidArgumentException('A callback is already registered for this identifier.');
        }

        $this->callbacks[$callback_id] = $callback;
    }

    /**
     * @param array<string, mixed> $input Validated Ability input.
     * @return array<string, mixed>
     */
    public function execute(string $callback_id, array $input): array
    {
        if (! isset($this->callbacks[$callback_id])) {
            throw new ToolException(
                'tool_dependency_unavailable',
                'This tool is temporarily unavailable.',
                503,
                true,
                'Refresh the available site tools and try again.'
            );
        }

        $result = ($this->callbacks[$callback_id])($input);
        if (! is_array($result)) {
            throw new ToolException('invalid_tool_output', 'The tool returned an invalid result.', 500);
        }

        return $result;
    }
}
