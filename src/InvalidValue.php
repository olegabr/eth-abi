<?php

/**
 * A value that does not fit its ABI type.
 *
 * @package olegabr/eth-abi
 */

declare(strict_types=1);

namespace Ethereumico\EthAbi;

use InvalidArgumentException;

/**
 * Thrown when a caller asks for a value it cannot have.
 *
 * The message is the code, never a sentence: the caller that shows it to a
 * person translates it, the caller that logs it wants the code. The full
 * explanation, including the values that were refused, is {@see self::problem()}.
 *
 * @since 0.1.0
 */
final class InvalidValue extends InvalidArgumentException
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly Problem $problem)
    {
        parent::__construct($problem->code());
    }

    /**
     * @since 0.1.0
     */
    public function problem(): Problem
    {
        return $this->problem;
    }
}
