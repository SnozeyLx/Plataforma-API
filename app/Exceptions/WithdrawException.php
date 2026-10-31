<?php

namespace App\Exceptions;

use App\Enums\WithdrawErrorPolicies;
use Exception;

class WithdrawException extends Exception
{
    public WithdrawErrorPolicies $policy;

    public function __construct(string $message, WithdrawErrorPolicies $policy)
    {
        $this->policy = $policy;
        parent::__construct($message);
    }

    public function getPolicy() {
        return $this->policy;
    }
}
