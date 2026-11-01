<?php

namespace App\Enums;

enum WithdrawErrorPolicies: string
{
    case missing_pix_key = "missing_pix_key";
    case missing_pix_key_type = "missing_pix_key_type";
    case minimum_per_operation = "minimum_per_operation";
    case maximum_per_operation = "maximum_per_operation";
    case maximum_per_day = "maximum_per_day";
    case maximum_per_month = "maximum_per_month";
    case withdraw_active = "withdraw_active";
}
