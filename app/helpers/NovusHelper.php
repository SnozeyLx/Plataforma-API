<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Actions\Balance\WithdrawValidationAction;
use App\Enums\BalanceStatementStatus;
use App\Enums\BalanceStatementType;
use App\Exceptions\Wallet\InsufficientBalance;
use App\Exceptions\WithdrawException;
use App\Models\BalanceStatement;
use App\Models\User;
use App\Services\OpenPix;
use App\Services\Novus;
use App\Settings\DepositSetting;
use App\Settings\WithdrawSetting;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// ✅ endroid/qr-code v4+ (corrige o erro do create())
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class BalanceController extends Controller
{
public function deposit(DepositSetting $depositSetting, Request $request)
{
    $dataReq = $request->validate([
        'value' => 'required|numeric'
    ]);

    if ($dataReq["value"] < $depositSetting->minimum_value) {
        return response()->json([
            "success" => false,
            "message" => 'Falha no depósito',
            "policy" => 'minimum_value'
        ], 440);
    }

    try {
        $uuid = (string) Str::uuid();
        $total_price_cents = (int) round($dataReq["value"] * 100);

        // ✅ postback_url obrigatório
        $postbackUrl = config('services.novus.postback_url');
        if (!$postbackUrl) {
            return response()->json([
                "success" => false,
                "message" => "postback_url não configurado (services.novus.postback_url)."
            ], 450);
        }

        if (config('services.pix_provider') === 'openpix') {
            return $this->depositOpenPix($request, (float) $dataReq["value"], $uuid);
        }

        $op = new Novus;

        // ✅ createPix precisa aceitar postback_url
        $resp = $op->createPix($total_price_cents, $uuid, $postbackUrl)->json();

        $apiData = $resp['data'] ?? null;
        if (!$apiData) {
            Log::error('NOVUS resposta sem data', ['response' => $resp]);
            return response()->json([
                "success" => false,
                "message" => "Resposta inválida da Novus."
            ], 460);
        }

        // ✅ No seu caso, o identificador que casa com o webhook (payload.id)
        // é o invoice_id retornado no create.
        $invoiceId = $apiData['invoice_id'] ?? null;
        if (!$invoiceId) {
            Log::error('NOVUS sem invoice_id no retorno do create', ['response' => $resp]);
            return response()->json([
                "success" => false,
                "message" => "Novus não retornou invoice_id."
            ], 470);
        }

        $brCode = $apiData['qr_code_pix'] ?? null;
        if (!$brCode) {
            Log::error('NOVUS sem qr_code_pix', ['response' => $resp]);
            return response()->json([
                "success" => false,
                "message" => "Novus não retornou qr_code_pix."
            ], 400);
        }

        $svg = QrCode::format('svg')
            ->size(400)
            ->margin(2)
            ->generate($brCode);

        $qrCodeImageBase64 = 'data:image/svg+xml;base64,' . base64_encode($svg);

        $bs = BalanceStatement::create([
            "uuid" => $uuid,
            "type" => BalanceStatementType::Deposit,
            "value" => $dataReq["value"],

            // ✅ vai bater com $data['id'] no webhook
            "invoice_id" => $invoiceId,

            // ✅ pode vir null, então não dependa disso no webhook
            "external_id" => $apiData["external_id"] ?? null,

            "qr_code" => $brCode,
            "qr_code_image" => $qrCodeImageBase64,
            "payment_link" => $apiData["payment_link"] ?? null,
            "expiration" => $apiData["expiration"] ?? null,
            "status" => BalanceStatementStatus::Pending,
            "user_id" => $request->user()->id
        ]);

        return response()->json([
            "success" => true,
            "message" => $resp["message"] ?? "Deposito criado com sucesso",
            "qrCode" => $bs->qr_code,
            "qrCodeImage" => $bs->qr_code_image,
            "statement_id" => $uuid,
            "invoice_id" => $invoiceId, // ✅ útil pro debug
            "status" => $apiData["status"] ?? "pending",
        ]);

    } catch (\Throwable $e) {
        Log::error('Deposito ERROR: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return response()->json([
            "success" => false,
            "message" => $e->getMessage()
        ], 410);
    }
}

private function depositOpenPix(Request $request, float $value, string $uuid)
{
    // Woovi (OpenPix): correlationID é o uuid do depósito e volta no webhook
    $resp = (new OpenPix)->createPix($uuid, $value)->json();

    $charge = $resp['charge'] ?? null;
    $brCode = $charge['brCode'] ?? null;
    if (!$brCode) {
        Log::error('OPENPIX sem brCode', ['response' => $resp]);
        return response()->json([
            "success" => false,
            "message" => "Woovi não retornou o código PIX."
        ], 470);
    }

    $svg = QrCode::format('svg')
        ->size(400)
        ->margin(2)
        ->generate($brCode);

    $bs = BalanceStatement::create([
        "uuid" => $uuid,
        "type" => BalanceStatementType::Deposit,
        "value" => $value,
        "invoice_id" => $uuid,
        "external_id" => $uuid,
        "qr_code" => $brCode,
        "qr_code_image" => 'data:image/svg+xml;base64,' . base64_encode($svg),
        "payment_link" => $charge['paymentLinkUrl'] ?? null,
        "status" => BalanceStatementStatus::Pending,
        "user_id" => $request->user()->id
    ]);

    return response()->json([
        "success" => true,
        "message" => "Deposito criado com sucesso",
        "qrCode" => $bs->qr_code,
        "qrCodeImage" => $bs->qr_code_image,
        "statement_id" => $uuid,
        "invoice_id" => $uuid,
        "status" => "pending",
    ]);
}

public function withdraw(Request $request, WithdrawSetting $settings)
{
    $data = $request->validate([
        'value' => 'required|numeric'
    ]);

    try {
        (new WithdrawValidationAction)->handle($request->user(), $data['value']);
    } catch (WithdrawException $e) {
        return response()->json([
            "success" => false,
            "message" => $e->getMessage(),
            "policy" => $e->policy->value
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            "success" => false,
            "message" => 'Falha no saque, ocorreu um erro interno',
        ], 200);
    }

    try {
        // Com header Idempotency-Key, a mesma chave do mesmo usuário nunca paga duas vezes
        $clientKey = $request->header('Idempotency-Key');
        $amount = (float) $data["value"];
        $userReq = $request->user();
        $uuid = $clientKey
            ? hash('sha256', $userReq->id . ':' . $clientKey)
            : (string) Str::uuid();

        $external_id = DB::transaction(function () use ($amount, $userReq, $uuid, $clientKey) {

            $user = User::lockForUpdate()
                ->select("id", "balance", "pix", "pix_type")
                ->where("id", $userReq->id)
                ->firstOrFail();

            if ($clientKey) {
                $existing = BalanceStatement::where("uuid", $uuid)->first();
                if ($existing) {
                    return $existing->external_id;
                }
            }

            if ((float)$user->balance < (float)$amount) {
                throw new InsufficientBalance();
            }

            // ✅ defina UMA regra. Aqui: Novus em centavos
            $payloadAmount = (int) round($amount * 100);

            $pixKey = (string) $user->pix;
            $pixType = is_object($user->pix_type)
                ? $user->pix_type->value
                : (string) $user->pix_type;

            $op = new Novus();

            $resp = $op->payout($payloadAmount, $pixKey, $pixType, null, $uuid)->json();
            $apiData = $resp['data'] ?? null;

            $providerId = data_get($apiData, 'payout_id')
                ?? data_get($apiData, 'id')
                ?? data_get($resp, 'payout_id')
                ?? data_get($resp, 'id');

            if (!$providerId) {
                Log::error('NOVUS payout sem id/payout_id', ['response' => $resp]);
                throw new \RuntimeException('Novus não retornou id do payout.');
            }

            // ✅ cria como Processing (não como paid)
          BalanceStatement::create([
    "uuid"       => $uuid,
    "type"       => BalanceStatementType::Withdraw,
    "value"      => $amount,
    "invoice_id" => $providerId,   // ✅ bate com payload.id
    "external_id"=> $providerId,   // ✅ fallback
    "status"     => BalanceStatementStatus::Processing,
    "user_id"    => $user->id,
]);

            // ✅ reserva o saldo agora
            $user->decrement("balance", $amount);

            return $providerId;
        }, 5);

        return [
            "success" => true,
            "message" => "Saque solicitado com sucesso",
            "external_id" => $external_id
        ];
    } catch (InsufficientBalance $e) {
        return response()->json([
            "success" => false,
            "message" => 'Não foi possível fazer o saque, saldo insuficiente.',
            "policy" => "insufficient_balance"
        ], 420);
    } catch (\Throwable $e) {
        Log::emergency("Saque ERROR");
        Log::emergency($e);

        return response()->json([
            "success" => false,
            "message" => 'Não foi possível fazer o saque'
        ], 430);
    }
}



    public function statements(Request $request)
    {
        $statements = BalanceStatement::where("user_id", $request->user()->id)
            ->orderBy("id", "desc")
            ->paginate();

        return [
            "success" => true,
            "statements" => $statements
        ];
    }

    public function statement(Request $request, $id)
    {
        $statement = BalanceStatement::where("user_id", $request->user()->id)
            ->where("uuid", $id)
            ->firstOrFail();

        return [
            "success" => true,
            "statement" => $statement
        ];
    }
}
