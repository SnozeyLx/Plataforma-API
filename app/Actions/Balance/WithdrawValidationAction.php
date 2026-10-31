<?php

namespace App\Actions\Balance;

use App\Enums\BalanceStatementType;
use App\Enums\WithdrawErrorPolicies;
use App\Exceptions\WithdrawException;
use App\Models\BalanceStatement;
use App\Models\User;
use App\Settings\WithdrawSetting;

class WithdrawValidationAction
{
    public function handle(User $user, float $amount) {
        $withdrawSetting = new WithdrawSetting;

        $withdraw_day = BalanceStatement::where("user_id", $user->id)->where("type", BalanceStatementType::Withdraw)->whereBetween("created_at", [date("Y-m-d") . " 00:00:00", date("Y-m-d") . "23:59:59"])->sum("value");
        $withdraw_month = BalanceStatement::where("user_id", $user->id)->where("type", BalanceStatementType::Withdraw)->whereBetween("created_at", [date("Y-m") . "-01 00:00:00", date("Y-m-t") . "23:59:59"])->sum("value");

        if(empty($user->pix)) {
            throw new WithdrawException("Falha no saque, faltando chave pix", WithdrawErrorPolicies::missing_pix_key);
        }
        if(empty($user->pix_type)) {
            throw new WithdrawException("Falha no saque, faltando tipo de chave pix", WithdrawErrorPolicies::missing_pix_key_type);
        }
        if($amount < 10 ) { //$withdrawSetting->minimum_per_operation
            throw new WithdrawException("Falha no saque, valor menor que o minimo para saque", WithdrawErrorPolicies::minimum_per_operation);
        }
        if($amount > 10000) {
            throw new WithdrawException("Falha no saque, valor maior que o permitido", WithdrawErrorPolicies::maximum_per_operation);
        }
        if(($amount + $withdraw_day) > $withdrawSetting->maximum_per_day) {
            throw new WithdrawException("Falha no saque, valor maior que o permitido", WithdrawErrorPolicies::maximum_per_day);
        }
        if(($amount + $withdraw_month) > $withdrawSetting->maximum_per_month) {
            throw new WithdrawException("Falha no saque, valor maior que o permitido", WithdrawErrorPolicies::maximum_per_month);
        }
        if($withdrawSetting->active !== true) {
            throw new WithdrawException("Falha no saque, ocorreu um erro. Tente novamente mais tarde.", WithdrawErrorPolicies::withdraw_active);
        }
    }
}
