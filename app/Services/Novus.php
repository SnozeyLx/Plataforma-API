<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Novus
{
    private function baseUrl(): string
    {
        $url = (string) config('services.novus.url');
        if (!$url) {
            throw new \RuntimeException('NOVUS url não configurada (services.novus.url).');
        }
        return rtrim($url, '/');
    }

    private function token(): string
    {
        $token = (string) config('services.novus.token');
        if (!$token) {
            throw new \RuntimeException('NOVUS token não configurado (services.novus.token).');
        }
        return $token;
    }

    public function createPix(int $total_price_cents, string $external_id, ?string $postbackUrl = null)
    {
        $user = auth()->user();

        $postbackUrl = $postbackUrl ?: (string) config('services.novus.postback_in_url');
        if (!$postbackUrl) {
            throw new \RuntimeException('NOVUS postback IN não configurado (services.novus.postback_in_url).');
        }

        $payload = [
            "method" => "pix",
            "product_fisical" => "digital",
            "total_price_cents" => $total_price_cents,
            "external_id" => $external_id,
            "correlationID" => $external_id,
            "postback_url" => $postbackUrl,
            "payer" => [
                "name" => $user->name,
                "cpf_cnpj" => $user->cpf,
                "email" => $user->email,
                "phone" => $user->phone,
            ],
            "items" => [[
                "name" => "Deposito",
                "description" => "Deposito via PIX",
                "quantity" => 1,
                "unit_price" => $total_price_cents,
            ]],
        ];

        return Http::withToken($this->token())
            ->acceptJson()
            ->post($this->baseUrl() . "/api/v2/invoices", $payload)
            ->throw();
    }

    public function payout(int $amount, string $pix_key, string $pix_key_type, ?string $postbackUrl = null, ?string $idempotencyKey = null)
    {
        $postbackUrl = $postbackUrl ?: (string) config('services.novus.postback_out_url');

        $payload = [
            "amount" => $amount,
            "pix_key" => $pix_key,
            "pix_key_type" => $pix_key_type,
        ];

        if ($postbackUrl) {
            $payload["postback_url"] = $postbackUrl;
        }

        return Http::withToken($this->token())
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey ?: (string) Str::uuid(),
            ])
            ->acceptJson()
            ->post($this->baseUrl() . "/api/v2/payout", $payload)
            ->throw();
    }
}
