<?php

/**
 * Integers as hex words, on top of brick/math.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Olegabr\EthAbi\Support;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use InvalidArgumentException;

/**
 * The two integer transformations ABI encoding needs.
 *
 * Written as a thin layer over `brick/math` rather than as arithmetic: the
 * measured reason is in the package README - a home-grown decimal-to-hex loop
 * cost 30.9 µs per word where `brick/math` costs 0.69 µs with gmp present, and
 * even without gmp the library is not the slow part.
 *
 * @since 0.1.0
 */
final class Uint
{
    /**
     * A value as a 32-byte (or `$digits`-nibble) hex word, right-aligned.
     *
     * A negative value is padded with `f`, which is the two's complement for the
     * width - that is what the encoder needs for `int<M>`.
     *
     * @since 0.1.0
     *
     * @param mixed $value  Decimal string, hex string or integer.
     * @param int   $digits Width in hex digits, 64 for one ABI word.
     *
     * @throws InvalidArgumentException When the value is not a number.
     *
     * @return string Hex digits without `0x`.
     */
    public static function toHexWord(mixed $value, int $digits = 64): string
    {
        $number = self::toBigInteger($value);

        if ($number->isNegative()) {
            // Two's complement over the word: `-1` is `f` repeated, not `f…f1`.
            // Appending the magnitude to `f` padding is the mistake this guards.
            $number = BigInteger::of(2)->power($digits * 4)->plus($number);
        }

        $hex = $number->toBase(16);
        $length = strlen($hex);

        if ($length > $digits) {
            // A value wider than the word: the low `$digits` nibbles are what the
            // word can hold, and that is what the original encoder returned.
            $hex = substr($hex, -$digits);
            $length = strlen($hex);
        }

        return str_repeat('0', $digits - $length) . $hex;
    }

    /**
     * A hex word as a decimal string.
     *
     * @since 0.1.0
     *
     * @param string $hex Hex, with or without `0x`.
     *
     * @throws InvalidArgumentException When the value is not hex.
     *
     * @return string Decimal digits, `-`-prefixed when the word's top bit is set
     *                and `$signed` is true.
     */
    public static function fromHex(string $hex, bool $signed = false): string
    {
        $hex = Hex::stripZero($hex);

        if ('' === $hex) {
            return '0';
        }

        if (!Hex::isHex($hex)) {
            throw new InvalidArgumentException('fromHex needs hex, got: ' . $hex);
        }

        $number = BigInteger::fromBase($hex, 16);

        if (!$signed) {
            return (string) $number;
        }

        $bits = strlen($hex) * 4;

        if ($bits > 0 && hexdec($hex[0]) >= 8) {
            $number = $number->minus(BigInteger::of(2)->power($bits));
        }

        return (string) $number;
    }

    /**
     * A decimal string, hex string, integer or BigInteger as a BigInteger.
     *
     * @since 0.1.0
     *
     * @param mixed $value Value.
     *
     * @throws InvalidArgumentException When the value is not a number.
     */
    public static function toBigInteger(mixed $value): BigInteger
    {
        if ($value instanceof BigInteger) {
            return $value;
        }

        if (is_int($value)) {
            return BigInteger::of($value);
        }

        if (is_bool($value)) {
            return BigInteger::of($value ? 1 : 0);
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('not a number: ' . get_debug_type($value));
        }

        $text = trim($value);

        if ('' === $text) {
            return BigInteger::zero();
        }

        $negative = str_starts_with($text, '-');

        if ($negative) {
            $text = substr($text, 1);
        }

        try {
            // A numeric string is decimal, whatever it looks like: `'10'` is ten,
            // not sixteen, and `'255'` is two hundred and fifty five. Only a
            // `0x` prefix or a string that cannot be a number is read as hex.
            $isDecimal = is_numeric($text);
            $number = !$isDecimal && (Hex::isZeroPrefixed($text) || 1 === preg_match('/^[0-9a-f]+$/i', $text))
                ? BigInteger::fromBase(Hex::stripZero($text), 16)
                : BigInteger::of($text);
        } catch (MathException $error) {
            throw new InvalidArgumentException('not a number: ' . $value, 0, $error);
        }

        return $negative ? $number->negated() : $number;
    }

    /**
     * A value as a decimal string, for the value objects the encoder returns.
     *
     * @since 0.1.0
     *
     * @param mixed $value Value.
     */
    public static function toDecimalString(mixed $value): string
    {
        return (string) self::toBigInteger($value);
    }
}
