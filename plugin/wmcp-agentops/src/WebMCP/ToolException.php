<?php

/**
 * Stable public tool failure without internal exception leakage.
 *
 * @package WPWebMCP\AgentOps
 */

declare(strict_types=1);

namespace WPWebMCP\AgentOps\WebMCP;

use RuntimeException;

final class ToolException extends RuntimeException
{
    public function __construct(
        private readonly string $error_code,
        string $safe_message,
        private readonly int $http_status = 400,
        private readonly bool $retryable = false,
        private readonly string $recovery = ''
    ) {
        parent::__construct($safe_message);
    }

    public function error_code(): string
    {
        return $this->error_code;
    }

    public function http_status(): int
    {
        return $this->http_status;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }

    public function recovery(): string
    {
        return $this->recovery;
    }
}
