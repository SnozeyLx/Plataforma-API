<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenPix
{
    public function createPix($id, $value)
    {
        return Http::withHeader("Authorization", config("services.openpix.appid"))->
            post(config("services.openpix.url") . "/api/v1/charge?return_existing=true", [
                "value" => (int) round($value * 100),
                "correlationID" => $id
            ])->throw();
    }

    public function withdrawPix(string $id, float $value, string $pix_key, string $pix_key_type)
    {
        Http::withHeader("Authorization", config("services.openpix.appid"))->
            post(config("services.openpix.url") . "/api/v1/payment", [
                "value" => $value * 100,
                "correlationID" => $id,
                "destinationAlias" => $pix_key,
                "destinationAliasType" => $pix_key_type
            ])->throw();

        return Http::withHeader("Authorization", config("services.openpix.appid"))->
            post(config("services.openpix.url") . "/api/v1/payment/approve", [
                "correlationID" => $id
            ])->throw();
    }
}
