<?php

namespace App\Rules;

use App\Models\Game;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CardOnGameValidation implements ValidationRule
{

    public function __construct(
        private Game $game
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cards = [$this->game->card_left, $this->game->card_right];
        if(!in_array($value, $cards)) {
            $fail(":attribute is not a card in game");
        }
    }
}
