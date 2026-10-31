<?php

namespace App\Enums;

enum UserPixType: string
{
    case CPF = "CPF";
    case CNPJ = "CNPJ";
    case EMAIL = "EMAIL";
    case PHONE = "PHONE";
    case RANDOM = "RANDOM";
}
