<?php

namespace App\Exceptions\Wallet;

use Exception;

class InsufficientBalance extends Exception
{
    public function __construct()
    {
        parent::__construct("Insufficient balance for the operation");
    }
}
