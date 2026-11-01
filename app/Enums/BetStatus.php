<?php

namespace App\Enums;

enum BetStatus: string
{
    case Pending = "pending";
    case Win = "win";
    case Loss = "loss";
    case Canceled = "canceled";
    case Waiting = "waiting";
}
