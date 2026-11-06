<?php

/**
 * The type system: what a value of an ABI type may be, and why a value is not.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Olegabr\EthAbi\Tests;

use InvalidArgumentException;
use Olegabr\EthAbi\InvalidValue;
use Olegabr\EthAbi\Problem;
use Olegabr\EthAbi\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    /**
     * An address is 20 bytes. Pasted without `0x` it is accepted and normalised,
     * because two spellings of one address must not be two different values.
     */
    public function test_an_address_is_normalised_to_one_spelling(): void
    {
        $expected = '0x1111111111111111111111111111111111111111';

        $this->assertNull(Type::problem('address', '0x1111111111111111111111111111111111111111'));
        $this->assertNull(Type::problem('address', '0X1111111111111111111111111111111111111111'));
        $this->assertNull(Type::problem('address', '1111111111111111111111111111111111111111'));

        $this->assertSame($expected, Type::normalise('address', '0x1111111111111111111111111111111111111111'));
        $this->assertSame($expected, Type::normalise('address', '1111111111111111111111111111111111111111'));
        $this->assertSame($expected, Type::normalise('address', '0X1111111111111111111111111111111111111111'));

        $mixed = '0x476Bb28Bc6D0e9De04dB5E19912C392F9a76535d';
        $this->assertSame(strtolower($mixed), Type::normalise('address', $mixed));

        // not 20 bytes
        $this->assertSame('address.length', Type::problem('address', '0x1111')?->code());
        $this->assertSame('address.length', Type::problem('address', '')?->code());
    }

    /**
     * `uint<M>` holds 0 … 2^M - 1, `int<M>` holds -2^(M-1) … 2^(M-1) - 1. Both
     * ends are checked, and the bounds are exact at 256 bits where a PHP int
     * cannot help.
     */
    public function test_integers_follow_their_width(): void
    {
        $this->assertNull(Type::problem('uint8', '255'));
        $this->assertSame('int.too_big', Type::problem('uint8', '256')?->code());
        $this->assertNull(Type::problem('uint8', '0'));

        $this->assertNull(Type::problem('int8', '-128'));
        $this->assertNull(Type::problem('int8', '127'));
        $this->assertSame('int.too_small', Type::problem('int8', '-129')?->code());
        $this->assertSame('int.too_big', Type::problem('int8', '128')?->code());

        $this->assertSame('int.negative', Type::problem('uint8', '-1')?->code());
        $this->assertSame('int.not_integer', Type::problem('uint256', '12.5')?->code());
        $this->assertSame('int.not_integer', Type::problem('uint256', '')?->code());
        $this->assertSame('int.bad_width', Type::problem('uint7', '1')?->code());
        $this->assertSame('int.bad_width', Type::problem('uint264', '1')?->code());

        $max = str_repeat('9', 77);
        $this->assertNull(Type::problem('uint256', $max));

        // 2^256 - 1 is the largest uint256, 2^256 is one too many.
        $limit = '11579208923731619542357098500868790785326998466564056403945758400791312963993';
        $this->assertNull(Type::problem('uint256', $limit . '5'));
        $this->assertSame('int.too_big', Type::problem('uint256', $limit . '6')?->code());
    }

    /**
     * `bytes<M>` is exactly M bytes, `bytes` is any even number of hex digits.
     */
    public function test_bytes_have_a_length(): void
    {
        $this->assertNull(Type::problem('bytes32', '0x' . str_repeat('ab', 32)));
        $this->assertSame('bytes.length', Type::problem('bytes32', '0xab')?->code());
        $this->assertSame('bytes.hex', Type::problem('bytes32', '0x' . str_repeat('zz', 32))?->code());
        $this->assertSame('bytes.odd_length', Type::problem('bytes', '0xabc')?->code());
        $this->assertNull(Type::problem('bytes', '0xabcd'));
        $this->assertSame('0xabcd', Type::normalise('bytes', '0xABCD'));
    }

    /**
     * A bool accepts the two words and the two digits, because that is what
     * people type in a text field.
     */
    public function test_booleans_accept_true_false_one_zero(): void
    {
        foreach (['true', 'false', '1', '0', 'TRUE'] as $value) {
            $this->assertNull(Type::problem('bool', $value));
        }

        $this->assertSame('true', Type::normalise('bool', '1'));
        $this->assertSame('false', Type::normalise('bool', '0'));
        $this->assertSame('bool.value', Type::problem('bool', 'maybe')?->code());
        $this->assertNull(Type::problem('bool', true));
        $this->assertSame('false', Type::normalise('bool', false));
    }

    /**
     * A list comes from JSON or from one line of comma-separated values, and
     * `T[k]` insists on exactly k entries - the count is part of the interface.
     */
    public function test_lists_are_read_from_json_or_on_a_line(): void
    {
        $this->assertNull(Type::problem('uint8[]', '1, 2, 3'));
        $this->assertNull(Type::problem('uint8[]', '[1,2,3]'));
        $this->assertNull(Type::problem('address[]', '0x1111111111111111111111111111111111111111'));

        // Normalised to a JSON array of strings, which is what the encoder
        // reads: one spelling for both input shapes.
        $this->assertSame('["1","2","3"]', Type::normalise('uint8[]', '1, 2, 3'));
        $this->assertSame('["1","2","3"]', Type::normalise('uint8[]', '[1,2,3]'));

        $problem = Type::problem('uint8[]', '1, 256');
        $this->assertSame('array.element', $problem?->code());

        // The position of the wrong element is the innermost fact, and the
        // message a form shows needs it first.
        [$code, $params] = $problem->flatten();
        $this->assertSame('int.too_big', $code);
        $this->assertSame(2, $params[0]);
        $this->assertSame('uint8', $params[1]);

        // A list that starts like JSON and is not JSON is a typo, not an empty
        // list: `[1,2` must not be read as "no arguments".
        $this->assertSame('array.not_json', Type::problem('uint8[]', '[1,2')?->code());
        $this->assertSame('array.not_json', Type::problem('uint8[]', '[]trailing')?->code());
        $this->assertNull(Type::problem('uint8[]', '[]'));
        $this->assertNull(Type::problem('uint8[]', '1,2,3'));

        $this->assertSame('array.count', Type::problem('uint8[3]', '1,2')?->code());
        $this->assertNull(Type::problem('uint8[2]', '1,2'));
        $this->assertSame('array.nested', Type::problem('uint8[][]', '1,2')?->code());
    }

    /**
     * `parse()` refuses with an exception that carries the code, and the message
     * is the code: no sentence is built here.
     */
    public function test_parse_throws_with_the_code(): void
    {
        try {
            Type::parse('bool', 'maybe');
            $this->fail('parse() accepted a value it should refuse');
        } catch (InvalidValue $exception) {
            $this->assertSame('bool.value', $exception->getMessage());
            $this->assertSame('bool.value', $exception->problem()->code());
        }

        $this->assertSame('42', Type::parse('uint8', '42'));
    }

    /**
     * `uint` means `uint256`, `byte` means `bytes1`, and a tuple is refused
     * instead of guessed.
     */
    public function test_aliases_and_unsupported_types(): void
    {
        $this->assertSame('uint256', Type::canonical('uint'));
        $this->assertSame('int256', Type::canonical('int'));
        $this->assertSame('bytes1', Type::canonical('byte'));
        $this->assertNull(Type::problem('uint', '5'));

        $this->assertTrue(Type::isSupported('uint256'));
        $this->assertTrue(Type::isSupported('address[]'));
        $this->assertFalse(Type::isSupported('uint8[][]'));
        $this->assertFalse(Type::isSupported('tuple'));
        $this->assertFalse(Type::isSupported('tuple[]'));
        $this->assertSame('type.unknown', Type::problem('tuple', '1,2')?->code());
        $this->assertSame('type.unknown', Type::problem('made-up-type', '1')?->code());
    }

    /**
     * The bounds are constant of the specification, so a narrow check after a
     * wide one must not borrow the wide limit.
     *
     * The regression this guards is real: computing `2^k` by doubling leaves
     * every intermediate power behind, and a cache that resumed from "the
     * highest known power" would answer `int8` with `2^256`.
     */
    public function test_limits_survive_a_narrow_check_after_a_wide_one(): void
    {
        $this->assertNull(Type::problem('uint256', str_repeat('9', 77)));
        $this->assertSame('int.too_big', Type::problem('uint256', '1' . str_repeat('0', 78))?->code());

        $this->assertNull(Type::problem('uint8', '255'));
        $this->assertSame('int.too_big', Type::problem('uint8', '256')?->code());
        $this->assertNull(Type::problem('int8', '-128'));
        $this->assertSame('int.too_small', Type::problem('int8', '-129')?->code());

        $this->assertNull(Type::problem('uint32', '4294967295'));
        $this->assertSame('int.too_big', Type::problem('uint32', '4294967296')?->code());
    }

    /**
     * A problem is a value, not a string: it can be passed up to a caller that
     * knows the language of the site.
     */
    public function test_a_problem_carries_the_values_a_message_needs(): void
    {
        $problem = Type::problem('uint8', '256');

        $this->assertInstanceOf(Problem::class, $problem);
        $this->assertSame(['uint8', '256'], $problem->params());
        $this->assertFalse($problem->hasNested());

        $nested = Type::problem('uint8[]', '1,256');
        $this->assertTrue($nested?->hasNested());
        $this->assertInstanceOf(InvalidArgumentException::class, new InvalidValue($problem));
    }
}
