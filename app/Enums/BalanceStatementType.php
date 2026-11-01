<?php

namespace App\Enums;

enum BalanceStatementType: string
{
    case Deposit = "deposit";
    case Withdraw = "withdraw";
}
