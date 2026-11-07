<?php

/**
 * Why a value was refused, as a code and its parameters.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Ethereumico\EthAbi;

/**
 * A refusal: a stable code and the values that go into the message.
 *
 * The library never builds a sentence. A site that speaks Russian, a CLI that
 * prints to a log and a test that asserts on the reason all need different
 * things, and the only one of them the library can know is *why*.
 *
 * Codes are `domain.reason` and adding one is not a breaking change:
 *
 * | code | params | meaning |
 * |---|---|---|
 * | `type.unknown` | `[type]` | the ABI names a type this build does not read |
 * | `int.bad_width` | `[type]` | `uint7`, `int264` - a width the ABI cannot express |
 * | `int.not_integer` | `[]` | empty, or not decimal digits |
 * | `int.negative` | `[type]` | a minus sign where an unsigned type is |
 * | `int.too_big` | `[type, value]` | above `2^M - 1` |
 * | `int.too_small` | `[type, value]` | below `-2^(M-1)` |
 * | `address.length` | `[hex_digits]` | not 20 bytes |
 * | `bytes.hex` | `[]` | characters outside `0-9a-f` |
 * | `bytes.odd_length` | `[]` | an odd number of hex digits |
 * | `bytes.length` | `[bytes, digits, actual]` | `bytes<M>` of the wrong length |
 * | `bool.value` | `[]` | not `true`/`false`/`1`/`0` |
 * | `array.count` | `[expected, type, actual]` | `T[k]` with the wrong number of entries |
 * | `array.nested` | `[type]` | a list the field cannot describe |
 * | `array.element` | `[position, Problem]` | an element was refused |
 * | `value.not_scalar` | `[]` | an object, a closure - nothing to read |
 *
 * @since 0.1.0
 */
final class Problem
{
    /**
     * @since 0.1.0
     *
     * @param string $code   Stable code, `domain.reason`.
     * @param array  $params Values for the message, scalars or a nested Problem.
     */
    public function __construct(
        private readonly string $code,
        private readonly array $params = []
    ) {
    }

    /**
     * @since 0.1.0
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * @since 0.1.0
     *
     * @return array
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Whether this problem is about a specific element of a list.
     *
     * @since 0.1.0
     */
    public function hasNested(): bool
    {
        foreach ($this->params as $param) {
            if ($param instanceof self) {
                return true;
            }
        }

        return false;
    }

    /**
     * The innermost problem with the position it was found at, e.g.
     * `['int.too_big', [3, 2, 'uint8', '256']]` for the third element.
     *
     * A form shows one message under one field; this is how the field learns
     * which of its entries is wrong without walking the tree itself.
     *
     * @since 0.1.0
     *
     * @return array{0:string,1:array}
     */
    public function flatten(): array
    {
        $positions = [];
        $problem = $this;

        while ('array.element' === $problem->code) {
            [$position, $inner] = $problem->params;

            if (!$inner instanceof self) {
                break;
            }

            $positions[] = $position;
            $problem = $inner;
        }

        return [$problem->code, array_merge($positions, $problem->params)];
    }
}
