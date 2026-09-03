<?php

/**
 * Lexicographically sortable identifiers and request UUIDs.
 *
 * @package WPWebMCP\AgentSNR
 */

declare(strict_types=1);

namespace WPWebMCP\AgentSNR\Support;

final class IdGenerator
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function ulid(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1000);
        $time         = '';

        for ($index = 0; $index < 10; ++$index) {
            $time         = self::CROCKFORD[$milliseconds % 32] . $time;
            $milliseconds = intdiv($milliseconds, 32);
        }

        $random = random_bytes(10);
        $bits   = '';
        foreach (str_split($random) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $entropy = '';
        for ($offset = 0; $offset < 80; $offset += 5) {
            $entropy .= self::CROCKFORD[bindec(substr($bits, $offset, 5))];
        }

        return $time . $entropy;
    }

    public static function event(): string
    {
        return 'evt_' . self::ulid();
    }

    public static function uuid(): string
    {
        $bytes     = random_bytes(16);
        $bytes[6]  = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8]  = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex       = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
