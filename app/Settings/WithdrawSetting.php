<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WithdrawSetting extends Settings
{

    public float $minimum_per_operation;
    public float $maximum_per_operation;
    public float $maximum_per_day;
    public float $maximum_per_month;
    public bool $active;

    public static function group(): string
    {
        return 'withdraw';
    }
}
