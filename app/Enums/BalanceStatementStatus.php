<?php

namespace App\Enums;

enum BalanceStatementStatus: string
{
    case Pending = "pending";
    case Expired = "expired";
    case Processing = "processing";
    case Failed = "failed";
    case Canceled = "canceled";
    case Paid = "paid";
    case Completed = "completed";

}
