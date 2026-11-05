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
use Olegabr\EthAbi\Support\Hex;
use Olegabr\EthAbi\Support\Uint;
use Olegabr\EthAbi\Abi\SolidityType;
use Olegabr\EthAbi\Abi\Types\IType;

class DynamicBytes extends SolidityType implements IType
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
        return (preg_match('/^bytes(\[([0-9]*)\])*$/', $name) === 1);
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
        if (!Hex::isHex($value)) {
            throw new InvalidArgumentException('The value to inputFormat must be hex bytes.');
        }
        $value = Hex::stripZero($value);

        if (strlen($value) % 2 !== 0) {
            $value = "0" . $value;
            // throw new InvalidArgumentException('The value to inputFormat has invalid length.');
        }
        // The length as hex: a byte count, so plain integer to hex - no
        // arbitrary-precision detour for a number below 2^31.
        $bnHex = dechex((int) floor(strlen($value) / 2));
        $padded = substr($bnHex, 0, 1);

        if ($padded !== '0' && $padded !== 'f') {
            $padded = '0';
        }
        $l = floor((strlen($value) + 63) / 64);
        $padding = (($l * 64 - strlen($value) + 1) >= 0) ? $l * 64 - strlen($value) : 0;

        return implode('', array_fill(0, 64-strlen($bnHex), $padded)) . $bnHex . $value . implode('', array_fill(0, $padding, '0'));
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
        $checkZero = str_replace('0', '', $value);

        if (empty($checkZero)) {
            return '0';
        }
        $size = (int) Uint::fromHex('0x' . substr($value, 0, 64));
        $length = 2 * $size;
        
        return '0x' . substr($value, 64, $length);
    }
}