<?php

/**
 * The hex helpers the ABI encoding needs, and nothing else.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Olegabr\EthAbi\Support;

use InvalidArgumentException;
use kornrunner\Keccak;
use stdClass;

/**
 * Hex and JSON helpers, taken from the encoder this package inherits.
 *
 * These are the eleven helpers `Web3\Utils` provided for ABI encoding, with the
 * same behaviour and without the wallet-facing surface (`toWei`, `toEther`, the
 * unit table, the key helpers) that has no business in a read-only base.
 *
 * @since 0.1.0
 */
final class Hex
{
    /**
     * @since 0.1.0
     *
     * @param string $value Binary or `0x`-prefixed hex.
     *
     * @return string `0x` + 64 hex digits, or null for the empty-hash sentinel.
     */
    public static function sha3(string $value): ?string
    {
        if (str_starts_with($value, '0x')) {
            $value = self::hexToBin($value);
        }

        $hash = Keccak::hash($value, 256);

        if ('c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470' === $hash) {
            // keccak256('') - the fork used to return null here; a caller that
            // hashes an empty string almost always means "no data", and a null
            // is easier to check than a constant nobody remembers.
            return null;
        }

        return '0x' . $hash;
    }

    /**
     * @since 0.1.0
     *
     * @param string $value Hex without `0x`.
     *
     * @return string Binary.
     */
    public static function hexToBin(string $value): string
    {
        if (self::isZeroPrefixed($value)) {
            $value = self::stripZero($value);
        }

        if (0 !== strlen($value) % 2) {
            $value = '0' . $value;
        }

        return (string) pack('H*', $value);
    }

    /**
     * @since 0.1.0
     */
    public static function isZeroPrefixed(string $value): bool
    {
        return str_starts_with($value, '0x');
    }

    /**
     * @since 0.1.0
     */
    public static function stripZero(string $value): string
    {
        return self::isZeroPrefixed($value) ? substr($value, 2) : $value;
    }

    /**
     * @since 0.1.0
     */
    public static function isHex(string $value): bool
    {
        return 1 === preg_match('/^(0x)?[a-f0-9]*$/', $value);
    }

    /**
     * @since 0.1.0
     */
    public static function isAddress(string $value): bool
    {
        return 1 === preg_match('/^(0x)?[a-f0-9]{40}$/', $value)
            || self::isAddressChecksum($value);
    }

    /**
     * @since 0.1.0
     */
    public static function isAddressChecksum(string $value): bool
    {
        if (1 !== preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $value)) {
            return false;
        }

        $address = self::stripZero($value);

        if (strtoupper($address) === $address || strtolower($address) === $address) {
            return true;
        }

        $hash = Keccak::hash(strtolower($address), 256);

        for ($i = 0; $i < 40; $i++) {
            $char = $address[$i];

            if (1 !== preg_match('/[a-f]/i', $char)) {
                continue;
            }

            $nibble = hexdec($hash[$i]);

            if ($nibble >= 8 && strtoupper($char) !== $char) {
                return false;
            }

            if ($nibble < 8 && strtolower($char) !== $char) {
                return false;
            }
        }

        return true;
    }

    /**
     * @since 0.1.0
     *
     * @param string $value Hex digits, with or without `0x`.
     *
     * @return string Binary.
     */
    public static function toBin(string $value): string
    {
        return self::hexToBin($value);
    }

    /**
     * A value as hex digits.
     *
     * Binary text is hex-encoded; an integer or a decimal string is its hex
     * value. This is the shape the encoder needs for `string` and `bytes`.
     *
     * @since 0.1.0
     *
     * @param mixed $value Binary, integer or decimal string.
     * @param bool  $isPrefix Add `0x` to the result.
     *
     * @throws InvalidArgumentException When the value is not one of those.
     *
     * @return string
     */
    public static function toHex(mixed $value, bool $isPrefix = false): string
    {
        if (is_int($value)) {
            $hex = dechex($value);
        } elseif (is_string($value)) {
            // A decimal string is a number, everything else is binary text -
            // the same rule the rest of the package follows.
            $hex = is_numeric($value) && !self::isZeroPrefixed($value)
                ? Uint::toHexWord($value, 0)
                : implode('', unpack('H*', $value));
        } else {
            throw new InvalidArgumentException('toHex cannot read ' . get_debug_type($value));
        }

        return $isPrefix ? '0x' . $hex : $hex;
    }

    /**
     * A JSON method or event item as `name(type,type)`.
     *
     * @since 0.1.0
     *
     * @param array|stdClass $json ABI item.
     *
     * @return string Signature, or the name when it already carries one.
     */
    public static function jsonMethodToString(array|stdClass $json): string
    {
        if ($json instanceof stdClass) {
            $json = (array) $json;
            $types = [];

            foreach ((array) ($json['inputs'] ?? []) as $param) {
                if (isset($param->type)) {
                    $types[] = $param->type;
                }
            }

            return (string) ($json['name'] ?? '') . '(' . implode(',', $types) . ')';
        }

        if (isset($json['name']) && str_contains((string) $json['name'], '(')) {
            return (string) $json['name'];
        }

        $types = [];

        foreach ((array) ($json['inputs'] ?? []) as $param) {
            if (isset($param['type'])) {
                $types[] = $param['type'];
            }
        }

        return (string) ($json['name'] ?? '') . '(' . implode(',', $types) . ')';
    }

    /**
     * A decoded ABI (objects) as arrays, all the way down.
     *
     * @since 0.1.0
     *
     * @param mixed $json ABI item.
     *
     * @return mixed
     */
    public static function jsonToArray(mixed $json): mixed
    {
        if ($json instanceof stdClass) {
            $json = (array) $json;
        }

        if (!is_array($json)) {
            return $json;
        }

        foreach ($json as $key => $param) {
            if (is_array($param) || $param instanceof stdClass) {
                $json[$key] = self::jsonToArray($param);
            }
        }

        return $json;
    }

    /**
     * @since 0.1.0
     *
     * @param string $hexNumber Hex, with or without `0x`.
     *
     * @throws InvalidArgumentException When the value is not hex.
     *
     * @return int
     */
    public static function hexToNumber(string $hexNumber): int
    {
        if (!self::isHex($hexNumber)) {
            throw new InvalidArgumentException('hexToNumber needs hex, got: ' . $hexNumber);
        }

        $hex = self::stripZero($hexNumber);

        return '' === $hex ? 0 : (int) hexdec($hex);
    }
}
