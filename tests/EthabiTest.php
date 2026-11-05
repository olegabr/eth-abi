<?php

/**
 * Encoding and decoding, checked against values that exist outside this code.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Olegabr\EthAbi\Tests;

use Olegabr\EthAbi\Abi\Ethabi;
use PHPUnit\Framework\TestCase;

final class EthabiTest extends TestCase
{
    private Ethabi $ethabi;

    protected function setUp(): void
    {
        $this->ethabi = new Ethabi();
    }

    /**
     * Selectors and event topics are hashes of the signature, so they can be
     * checked against numbers the ecosystem publishes - not against ourselves.
     */
    public function test_known_selectors_and_event_topics(): void
    {
        $this->assertSame('0x70a08231', $this->ethabi->encodeFunctionSignature('balanceOf(address)'));
        $this->assertSame('0xa9059cbb', $this->ethabi->encodeFunctionSignature('transfer(address,uint256)'));
        $this->assertSame('0x18160ddd', $this->ethabi->encodeFunctionSignature('totalSupply()'));
        $this->assertSame('0x313ce567', $this->ethabi->encodeFunctionSignature('decimals()'));

        $this->assertSame(
            '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
            $this->ethabi->encodeEventSignature('Transfer(address,address,uint256)')
        );
        $this->assertSame(
            '0x8c5be1e5ebec7d5bd14f71427d1e84f3dd0314c0f7b2291e5b200ac8c7c3b925',
            $this->ethabi->encodeEventSignature('Approval(address,address,uint256)')
        );
    }

    /**
     * Every static value is one 32-byte word, right-aligned.
     */
    public function test_static_values_are_one_word_each(): void
    {
        $this->assertSame(self::words('45'), $this->ethabi->encodeParameter('uint256', '69'));
        $this->assertSame(self::words('1'), $this->ethabi->encodeParameter('bool', '1'));
        $this->assertSame(
            self::words(str_repeat('11', 20)),
            $this->ethabi->encodeParameter('address', '0x1111111111111111111111111111111111111111')
        );

        // bytes3 is a static type: fixed length, padded on the right.
        $this->assertSame(
            '0x' . bin2hex('abc') . str_repeat('0', 58),
            $this->ethabi->encodeParameter('bytes3', '616263')
        );
    }

    /**
     * A negative integer is two's complement over the whole word: `-1` is 64 `f`
     * nibbles, not `f` padding with a magnitude appended.
     */
    public function test_negative_integers_are_twos_complement(): void
    {
        $this->assertSame(
            '0x' . str_repeat('f', 64),
            $this->ethabi->encodeParameter('int256', '-1')
        );
    }

    /**
     * The regression that made this package exist: an address written with digits
     * only must stay an address, not become the decimal number 1111…1111.
     */
    public function test_an_address_of_digits_only_stays_an_address(): void
    {
        $word = $this->ethabi->encodeParameter('address', '0x1111111111111111111111111111111111111111');

        $this->assertSame(self::words(str_repeat('11', 20)), $word);
        // The decimal reading would leave its mark as `7d…` nibbles.
        $this->assertStringNotContainsString('7d', $word);
    }

    /**
     * A dynamic value is an offset word at the head, then the length word and the
     * padded data - the shape the specification defines for `string` and `bytes`.
     */
    public function test_dynamic_values_are_offset_then_length_then_data(): void
    {
        $this->assertSame(
            self::words('20', '4') . bin2hex('dave') . str_repeat('0', 56),
            $this->ethabi->encodeParameter('string', 'dave')
        );
    }

    /**
     * A static element array is its words, concatenated: no length, no offset.
     */
    public function test_a_static_array_is_its_words(): void
    {
        $this->assertSame(
            self::words('1', '2'),
            $this->ethabi->encodeParameter('uint256[2]', ['1', '2'])
        );
    }

    /**
     * A dynamic array is an offset, then the length, then the elements.
     */
    public function test_a_dynamic_array_carries_an_offset_and_a_length(): void
    {
        $this->assertSame(
            self::words('20', '2', '1', '2'),
            $this->ethabi->encodeParameter('uint256[]', ['1', '2'])
        );
    }

    /**
     * A tuple is the concatenation of its components. Both spellings of the type
     * mean the same thing, and the second one used to hang the process: the tuple
     * branch fed itself the string it had just been given until memory ran out.
     */
    public function test_tuples_in_both_spellings(): void
    {
        $expected = self::words('7', '1');

        $this->assertSame($expected, $this->ethabi->encodeParameter('(uint256,bool)', ['7', '1']));
        $this->assertSame($expected, $this->ethabi->encodeParameter('tuple(uint256,bool)', ['7', '1']));
    }

    /**
     * Two parameters are two words: `encodeParameters` is the shape a call uses.
     */
    public function test_two_parameters_are_two_words(): void
    {
        $this->assertSame(
            self::words('7', '1'),
            $this->ethabi->encodeParameters(['uint256', 'bool'], ['7', '1'])
        );
    }

    /**
     * The property that has to hold for every value: what was encoded can be read
     * back unchanged.
     *
     * @dataProvider roundTripCases
     */
    public function test_round_trip(string $type, mixed $value, string $expected): void
    {
        $encoded = $this->ethabi->encodeParameter($type, $value);
        $decoded = $this->ethabi->decodeParameter($type, $encoded);

        $this->assertSame($expected, $this->asString($decoded));
    }

    /**
     * @return array<string,array{0:string,1:mixed,2:string}>
     */
    public static function roundTripCases(): array
    {
        return [
            'uint256 max' => [
                'uint256',
                '115792089237316195423570985008687907853269984665640564039457584007913129639935',
                '115792089237316195423570985008687907853269984665640564039457584007913129639935',
            ],
            'uint8' => ['uint8', '255', '255'],
            'uint256 small' => ['uint256', '69', '69'],
            'int8 negative' => ['int8', '-128', '-128'],
            'int256 negative' => ['int256', '-1', '-1'],
            'bool true' => ['bool', '1', '1'],
            'bool false' => ['bool', '0', '0'],
            'address' => [
                'address',
                '0x476bb28bc6d0e9de04db5e19912c392f9a76535d',
                '0x476bb28bc6d0e9de04db5e19912c392f9a76535d',
            ],
            'string' => ['string', 'hello, world', 'hello, world'],
            'empty string' => ['string', '', ''],
            'bytes' => ['bytes', 'deadbeef', '0xdeadbeef'],
            'bytes32' => ['bytes32', str_repeat('ab', 32), '0x' . str_repeat('ab', 32)],
        ];
    }

    /**
     * A right-aligned 32-byte word per argument, `0x`-prefixed once: the shape
     * every encoder entry point returns.
     */
    private static function words(string ...$hexes): string
    {
        $out = '';

        foreach ($hexes as $hex) {
            $out .= str_pad($hex, 64, '0', STR_PAD_LEFT);
        }

        return '0x' . $out;
    }

    private function asString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return implode(',', array_map(fn ($item): string => (string) $item, $value));
        }

        return (string) $value;
    }
}
