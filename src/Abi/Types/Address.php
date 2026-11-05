<?php

/**
 * This file is part of web3.php package.
 * 
 * (c) Kuan-Cheng,Lai <alk03073135@gmail.com>
 * 
 * @author Peter Lai <alk03073135@gmail.com>
 * @license MIT
 */

namespace Olegabr\EthAbi\Abi\Types;

use InvalidArgumentException;
use Olegabr\EthAbi\Abi\SolidityType;
use Olegabr\EthAbi\Abi\Types\IType;
use Olegabr\EthAbi\Support\Hex;
use Olegabr\EthAbi\Support\Uint;

class Address extends SolidityType implements IType
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
        return (preg_match('/^address/', $name) === 1);
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
     * to do: iban
     * 
     * @param mixed $value
     * @param array $abiType
     * @return string
     */
    public function inputFormat($value, $abiType)
    {
        $value = (string) $value;

        if (Hex::isAddress($value)) {
            $value = strtolower($value);

            if (Hex::isZeroPrefixed($value)) {
                $value = Hex::stripZero($value);
            }
            // an address is a 20 byte value, so its digits are always hex.
            // Uint::toHexWord() would read an address made of digits
            // only (0x111…1111) as a decimal number, so pad it here instead.
            return str_pad($value, 64, '0', STR_PAD_LEFT);
        }
        $value = Uint::toHexWord($value);

        return $value;
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
        return '0x' . substr($value, 24, 40);
    }
}