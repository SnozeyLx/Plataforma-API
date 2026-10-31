<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class DepositSetting extends Settings
{
    public float $minimum_value;

    public static function group(): string
    {
        return 'deposit';
    }
}
