<?php

/**
 * Two implementations of the same specification, on the same values.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Ethereumico\EthAbi\Tests;

use Ethereumico\EthAbi\Abi\Ethabi;
use PHPUnit\Framework\TestCase;

/**
 * Differential test: this encoder against the one it was taken from.
 *
 * The whole point of moving the encoder into a package is that there is one
 * implementation; until the old one is retired it is still the best oracle we
 * have, because it has been used against real chains for years. If the two
 * disagree on a value, one of them is wrong and we want to know which before a
 * contract call is built from it.
 *
 * The oracle is a **dev** dependency only: nothing in `src/` knows `Web3\` exists.
 *
 * @since 0.1.0
 */
final class DifferentialTest extends TestCase
{
    private Ethabi $ours;

    private \Web3\Contracts\Ethabi $oracle;

    protected function setUp(): void
    {
        if (!class_exists(\Web3\Contracts\Ethabi::class)) {
            $this->markTestSkipped('olegabr/web3.php is not installed: the oracle is a dev dependency.');
        }

        $this->ours = new Ethabi();
        $this->oracle = new \Web3\Contracts\Ethabi();
    }

    /**
     * Values chosen to sit on the edges: 256-bit ends, a negative int, an address
     * of digits only, an empty string, an empty bytes, arrays of both kinds, and a
     * tuple.
     *
     * @dataProvider values
     */
    public function test_the_encoders_agree(string $type, mixed $value): void
    {
        $ours = $this->ours->encodeParameter($type, $value);
        $oracle = $this->oracle->encodeParameter($type, $value);

        $this->assertSame(
            self::normalise($oracle),
            self::normalise($ours),
            'encoding of ' . $type . ' differs from the oracle'
        );
    }

    /**
     * @return array<string,array{0:string,1:mixed}>
     */
    public static function values(): array
    {
        return [
            'uint256 zero' => ['uint256', '0'],
            'uint256 small' => ['uint256', '69'],
            'uint256 max' => [
                'uint256',
                '115792089237316195423570985008687907853269984665640564039457584007913129639935',
            ],
            'uint8' => ['uint8', '255'],
            'uint32' => ['uint32', '4294967295'],
            'int8 negative' => ['int8', '-128'],
            'int128 negative' => ['int128', '-170141183460469231731687303715884105728'],
            'int256 minus one' => ['int256', '-1'],
            'bool true' => ['bool', true],
            'bool false' => ['bool', false],
            'address' => ['address', '0x476bb28bc6d0e9de04db5e19912c392f9a76535d'],
            'address of digits' => ['address', '0x1111111111111111111111111111111111111111'],
            'bytes1' => ['bytes1', 'ff'],
            'bytes32' => ['bytes32', str_repeat('ab', 32)],
            'bytes dynamic' => ['bytes', 'deadbeef'],
            'empty bytes' => ['bytes', ''],
            'string' => ['string', 'hello, world'],
            'empty string' => ['string', ''],
            'long string' => ['string', str_repeat('a', 100)],
            'uint256[]' => ['uint256[]', ['1', '2', '3']],
            'uint256[2]' => ['uint256[2]', ['1', '2']],
            'address[]' => ['address[]', ['0x1111111111111111111111111111111111111111']],
            'bool[]' => ['bool[]', [true, false]],
            'tuple' => ['(uint256,bool)', ['7', true]],
        ];
    }

    /**
     * The oracle answers some types with `0x` and others without; comparing the
     * words, not the decoration, is what makes the comparison about encoding.
     */
    private static function normalise(string $hex): string
    {
        $hex = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;

        return strtolower($hex);
    }
}
