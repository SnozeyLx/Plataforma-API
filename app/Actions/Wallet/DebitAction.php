<?php

namespace App\Actions\Wallet;

use App\Exceptions\Wallet\InsufficientBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DebitAction
{
    public function handle(User $user, float $amount) {
        $amount = round($amount, 2);
        DB::transaction(function () use ($user, $amount) {
            $balance = User::lockForUpdate()->select("balance")->where("id", $user->id)->first()->balance;
            if($balance < $amount) throw new InsufficientBalance();
            User::where("id",$user->id)->update(["balance" => $balance - $amount]);
        });
    }
}
