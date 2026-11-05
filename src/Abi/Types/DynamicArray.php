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
use Olegabr\EthAbi\Abi\Types\BaseArray;
use Olegabr\EthAbi\Support\Uint;

class DynamicArray extends BaseArray
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
        return (preg_match('/(\[\])/', $name) === 1);
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
        $results = $this->encodeElements($value, $abiType);
        $encodeSize = Uint::toHexWord(count($value));
        return $encodeSize . implode('', $results);
    }

    /**
     * outputFormat
     * 
     * @param string $value
     * @param array $abiType
     * @return array
     */
    public function outputFormat($value, $abiType)
    {
        if (!is_array($abiType)) {
            throw new InvalidArgumentException('Invalid abiType to decode sized array, expected: array');
        }
        $lengthHex = substr($value, 0, 64);
        $length = (int) Hex::hexToNumber($lengthHex);
        $offset = 0;
        $value = substr($value, 64);
        $results = [];
        $decoder = $abiType['coders'];
        for ($i = 0; $i < $length; $i++) {
            $decodeValueOffset = $offset;
            if ($decoder['dynamic']) {
                $decodeValueOffsetHex = substr($value, $offset, 64);
                $decodeValueOffset = (int) Hex::hexToNumber($decodeValueOffsetHex) * 2;
            }
            $results[] = $decoder['solidityType']->decode($value, $decodeValueOffset, $decoder);
            $offset += 64;
        }
        return $results;
    }
}