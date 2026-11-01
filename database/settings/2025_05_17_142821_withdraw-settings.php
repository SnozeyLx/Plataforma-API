<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('withdraw.minimum_per_operation', 10);
        $this->migrator->add('withdraw.maximum_per_operation', 100);
        $this->migrator->add('withdraw.maximum_per_day', 200);
        $this->migrator->add('withdraw.maximum_per_month', 1000);
        $this->migrator->add('withdraw.active', true);
    }
};
