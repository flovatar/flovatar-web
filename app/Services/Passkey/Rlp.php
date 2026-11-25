<?php

namespace App\Services\Passkey;

use InvalidArgumentException;

/**
 * Minimal RLP encoder (Ethereum yellow paper, appendix B) as used by Flow's transaction encoding.
 * PHP ints are encoded as big-endian unsigned integers without leading zeros (0 => empty string),
 * PHP strings as raw byte strings, PHP lists as RLP lists.
 */
final class Rlp
{
    public static function encode(mixed $item): string
    {
        if (is_array($item)) {
            $body = '';
            foreach ($item as $child) {
                $body .= self::encode($child);
            }

            return self::prefix(strlen($body), 0xc0).$body;
        }
        if (is_int($item)) {
            if ($item < 0) {
                throw new InvalidArgumentException('RLP cannot encode negative integers');
            }
            $item = self::intToBytes($item);
        }
        if (! is_string($item)) {
            throw new InvalidArgumentException('RLP can only encode strings, ints and lists');
        }
        if (strlen($item) === 1 && ord($item) < 0x80) {
            return $item;
        }

        return self::prefix(strlen($item), 0x80).$item;
    }

    public static function intToBytes(int $n): string
    {
        $out = '';
        while ($n > 0) {
            $out = chr($n & 0xff).$out;
            $n >>= 8;
        }

        return $out;
    }

    private static function prefix(int $len, int $offset): string
    {
        if ($len < 56) {
            return chr($offset + $len);
        }
        $bin = self::intToBytes($len);

        return chr($offset + 55 + strlen($bin)).$bin;
    }
}
