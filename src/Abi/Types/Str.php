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

class Str extends SolidityType implements IType
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
        return (preg_match('/^string/', $name) === 1);
    }

    /**
     * isDynamicType
     * 
     * @return bool
     */
    public function isDynamicType()
    {
        return true;
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
        $value = Hex::toHex($value);
        $prefix = Uint::toHexWord(strlen($value) / 2);
        $l = floor((strlen($value) + 63) / 64);
        $padding = (($l * 64 - strlen($value) + 1) >= 0) ? $l * 64 - strlen($value) : 0;

        return $prefix . $value . implode('', array_fill(0, $padding, '0'));
    }

    /**
     * outputFormat
     * 
     * @param mixed $value
     * @param array $abiType
     * @return string
     */
    public function outputFormat($value, $abiType)
    {
        $strLen = substr($value, 0, 64);
        $strValue = substr($value, 64);
        $match = [];

        if (preg_match('/^[0]+([a-f0-9]+)$/', $strLen, $match) === 1) {
            $strLen = Uint::fromHex('0x' . $match[1]);
        }

        $strValue = substr($strValue, 0, (int) $strLen * 2);

        return Hex::hexToBin($strValue);
    }
}
