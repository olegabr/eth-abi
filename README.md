# olegabr/eth-abi

Solidity ABI types, values and bounds. The read-only base for `olegabr/web3.php`.

**What it does:** turns the text of a form field into a value an ABI encoder
accepts, and explains a refusal as a stable code plus the values a message needs.

**What it never does:** sign, send, hold a key, talk to a node, or know what
language your site speaks. There is no `Account` here, no `send`, no private key
and no transport.

## Install

```bash
composer require olegabr/eth-abi
```

Requires PHP 8.1+. `brick/math ^0.13` is the only dependency — arithmetic is not
written here, only the bounds check, and the bounds are constants of the
specification (`2^k` is looked up, never computed: ~60 ns instead of ~470 µs for
building `2^256` on every call).

> **0.14+ needs PHP 8.2.** The line is pinned to `^0.13` while the floor is 8.1.

## Use

```php
use Ethereumico\EthAbi\Type;

Type::problem('uint8', '256');       // Problem(code: 'int.too_big', params: ['uint8', '256'])
Type::problem('uint8', '255');       // null - the value is fine
Type::parse('address', '1111…1111'); // '0x1111111111111111111111111111111111111111'
Type::isSupported('uint8[][]');      // false - one text field cannot describe it
```

A refusal is a `Problem`:

```php
$problem->code();    // 'int.too_big'
$problem->params();  // ['uint8', '256']
$problem->flatten(); // ['int.too_big', [3, 'uint8', '256']] for the third list element
```

The library builds no sentences. A caller that shows the refusal to a person
formats it in the site's language (`sprintf` + the translation functions); a
caller that logs it wants the code.

## Encoding and decoding

```php
use Ethereumico\EthAbi\Abi\Ethabi;

$abi = new Ethabi();

$abi->encodeFunctionSignature('balanceOf(address)');   // 0x70a08231
$abi->encodeEventSignature('Transfer(address,address,uint256)');
$abi->encodeParameter('uint256', '69');                // 0x00…45
$abi->encodeParameters(['uint256', 'bool'], ['7', '1']);
$abi->decodeParameter('uint256', $hex);
```

Answers are `0x`-prefixed hex. A dynamic argument carries its offset word first,
then its length, then the data — the shape the specification defines, so the
result can be used as calldata as is.

Supported: every static type, `string`, `bytes`, `T[]`, `T[k]`, and tuples in both
spellings, `(uint256,bool)` and `tuple(uint256,bool)`.

`Ethabi` is the encoder taken from `olegabr/web3.php` and moved here, with three
changes found by testing it:

* **two's complement for negatives** — `int256 -1` is 64 `f` nibbles; appending the
  magnitude to `f` padding produced `-15` on the way back;
* **`tuple(…)` no longer hangs** — the tuple branch fed itself the string it had
  been given until PHP ran out of memory; now both spellings are one type;
* **no `mbstring`** — the byte-oriented `mb_substr`/`mb_strlen` calls that needed a
  112 KB polyfill are `substr`/`strlen` (see `olegabr/keccak`).

`tests/DifferentialTest.php` encodes 24 values (256-bit ends, a negative `int128`,
an address of digits only, empty `bytes`, arrays, a tuple) with both this encoder
and the original one and asserts they agree — the oracle is a dev dependency, and
nothing in `src/` knows `Web3\` exists.

## Codes

`type.unknown`, `int.bad_width`, `int.not_integer`, `int.negative`,
`int.too_big`, `int.too_small`, `address.length`, `bytes.hex`,
`bytes.odd_length`, `bytes.length`, `bool.value`, `array.count`,
`array.nested`, `array.element`, `value.not_scalar`.

Adding a code is not a breaking change.

## Test

```bash
composer install
vendor/bin/phpunit
```
