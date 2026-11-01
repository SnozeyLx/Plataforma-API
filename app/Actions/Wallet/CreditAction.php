<?php

namespace App\Actions\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditAction
{
    public function handle(User $user, float $amount) {
        $amount = round($amount, 2);
        DB::transaction(function () use ($user, $amount) {
            $balance = User::lockForUpdate()->select("balance")->where("id", $user->id)->first()->balance;
            User::where("id",$user->id)->update(["balance" => $balance + $amount]);
        });
    }
}
