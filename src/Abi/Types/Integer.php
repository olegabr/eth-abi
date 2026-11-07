<?php

/**
 * This file is part of web3.php package.
 * 
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace Ethereumico\EthAbi\Abi\Types;

use Ethereumico\EthAbi\Support\Hex;
use Ethereumico\EthAbi\Abi\SolidityType;
use Ethereumico\EthAbi\Abi\Types\IType;
use Ethereumico\EthAbi\Support\Uint;

class Integer extends SolidityType implements IType
{
    /**
     * construct
     * 
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * isType
     * 
     * @param string $name
     * @return bool
     */
    public function isType($name)
    {
        return (preg_match('/^int([0-9]{1,})?/', $name) === 1);
    }

    /**
     * isDynamicType
     * 
     * @return bool
     */
    public function isDynamicType()
    {
        return false;
    }

    /**
     * inputFormat
     * 
     * @param mixed $value
     * @param array $abiType
     * @return string
     */
    public function inputFormat($value, $abiType)
    {
        return Uint::toHexWord($value);
    }

    /**
     * outputFormat
     * 
     * @param mixed $value
     * @param array $abiType
     * @return string Decimal digits, sign-resolved: `int<M>` is signed.
     */
    public function outputFormat($value, $abiType)
    {
        return Uint::fromHex('0x' . substr($value, 0, 64), true);
    }
}