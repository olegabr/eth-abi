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

class Boolean extends SolidityType implements IType
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
        return (preg_match('/^bool/', $name) === 1);
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
        // A form field produces a string, a wallet produces a bool: both are
        // the same value, and refusing one of them only moves the conversion
        // into every caller.
        if (is_bool($value)) {
            $bit = $value ? 1 : 0;
        } elseif (is_int($value) && (0 === $value || 1 === $value)) {
            $bit = $value;
        } elseif (is_string($value) && in_array(strtolower(trim($value)), ['1', '0', 'true', 'false'], true)) {
            $bit = in_array(strtolower(trim($value)), ['1', 'true'], true) ? 1 : 0;
        } else {
            throw new InvalidArgumentException('The value to inputFormat function must be a boolean.');
        }

        return '000000000000000000000000000000000000000000000000000000000000000' . $bit;
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
        $value = (int) substr($value, 63, 1);

        return (bool) $value;
    }
}