<?php

namespace App\Http\Controllers;

use App\Enums\BalanceStatementStatus;
use App\Enums\BalanceStatementType;
use App\Models\BalanceStatement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{


    public function PixIn(Request $request)
    {
        $token = (string) $request->query('token', '');
        if (!$token || !hash_equals((string) config('services.novus.webhook_token'), $token)) {
            Log::warning('PIX-IN token inválido', ['token' => $token]);
            return ["success" => true];
        }

        $payload = $request->all();
        $data = $payload['data'] ?? $payload;
        Log::info('Novus PIX-IN webhook recebido', ['data' => $data]);

        $raw = strtolower((string)($data['status'] ?? ''));
        $status = match ($raw) {
            'paid', 'success', 'succeeded', 'completed' => 'completed',
            'canceled', 'cancelled' => 'canceled',
            default => $raw,
        };
        if (!$status) {
            return ["success" => true];
        }

        $novusId = (string)($data['id'] ?? '');
        if ($novusId === '' && isset($data['invoice_id'])) {
            $novusId = (string) $data['invoice_id'];
        }
        if (!$novusId) {
            Log::warning('Webhook sem id', ['data' => $data]);
            return ["success" => true];
        }

        try {
            DB::transaction(function () use ($novusId, $status) {

                $deposit = BalanceStatement::where('type', BalanceStatementType::Deposit)
                    ->where(function ($q) use ($novusId) {
                        $q->where('invoice_id', $novusId)->orWhere('external_id', $novusId);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$deposit) {
                    Log::warning('Deposit não encontrado', ['invoice_id' => $novusId]);
                    return;
                }

                $current = strtolower((string)(is_object($deposit->status) ? $deposit->status->value : $deposit->status));
                if ($current === 'paid') {
                    return;
                }

                if (in_array($current, ['canceled', 'expired', 'failed'], true) && in_array($status, ['pending', 'processing'], true)) {
                    Log::info('PIX-IN ignorado por transição não permitida', ['invoice_id' => $novusId, 'current' => $current, 'incoming' => $status]);
                    return;
                }

                switch ($status) {

                    case 'pending':
                        $deposit->status = BalanceStatementStatus::Pending;
                        $deposit->save();
                        break;

                    case 'processing':
                        $deposit->status = BalanceStatementStatus::Processing;
                        $deposit->save();
                        break;

                    case 'expired':
                        $deposit->status = BalanceStatementStatus::Expired;
                        $deposit->save();
                        break;

                    case 'failed':
                        $deposit->status = BalanceStatementStatus::Failed;
                        $deposit->save();
                        break;

                    case 'canceled':
                        $deposit->status = BalanceStatementStatus::Canceled;
                        $deposit->save();
                        break;

                    case 'completed':

                        $user = User::where('id', $deposit->user_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$user) return;

                        // Credita saldo uma única vez
                        $user->increment('balance', $deposit->value);

                        $deposit->status = BalanceStatementStatus::Paid;
                        $deposit->save();

                        break;
                }
            }, 5);
        } catch (\Throwable $e) {
            Log::error('Webhook Novus Error', [
                'error' => $e->getMessage(),
                'invoice_id' => $novusId
            ]);

            return abort(200);
        }

        return ["success" => true];
    }
    // Woovi (OpenPix): credita depósito quando a cobrança é paga
    public function openpix(Request $request)
    {
        $token = (string) $request->query('token', '');
        if (!$token || !hash_equals((string) config('services.openpix.webhook_token'), $token)) {
            Log::warning('OPENPIX token inválido');
            return ["success" => true];
        }

        $event = (string) $request->input('event', '');
        $correlationId = (string) $request->input('charge.correlationID', '');

        if ($event !== 'OPENPIX:CHARGE_COMPLETED' || $correlationId === '') {
            return ["success" => true];
        }

        try {
            DB::transaction(function () use ($correlationId) {

                $deposit = BalanceStatement::where('type', BalanceStatementType::Deposit)
                    ->where('invoice_id', $correlationId)
                    ->lockForUpdate()
                    ->first();

                if (!$deposit) {
                    Log::warning('OPENPIX depósito não encontrado', ['correlationID' => $correlationId]);
                    return;
                }

                // ✅ idempotência: webhook repetido não credita de novo
                $current = strtolower((string)(is_object($deposit->status) ? $deposit->status->value : $deposit->status));
                if ($current === 'paid') {
                    return;
                }

                $user = User::where('id', $deposit->user_id)->lockForUpdate()->first();
                if (!$user) return;

                $user->increment('balance', $deposit->value);

                $deposit->status = BalanceStatementStatus::Paid;
                $deposit->save();
            }, 5);
        } catch (\Throwable $e) {
            Log::error('Webhook OpenPix Error', [
                'error' => $e->getMessage(),
                'correlationID' => $correlationId
            ]);
            return abort(200);
        }

        return ["success" => true];
    }

    public function PixOut(Request $request)
    {
        $token = (string) $request->query('token', '');
        $hasValidToken = $token && hash_equals((string) config('services.novus.webhook_token'), $token);

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $secret = (string) config('services.novus.webhook_secret');
        $rawBody = (string) $request->getContent();
        $computed = $secret ? hash_hmac('sha256', $rawBody, $secret) : '';
        $hasValidSignature = $signature && $secret && hash_equals($computed, $signature);

        if (!$hasValidToken && !$hasValidSignature) {
            Log::warning('PIX-OUT autenticação inválida', [
                'token_present' => (bool)$token,
                'signature_present' => (bool)$signature,
            ]);
            return ["success" => true];
        }

        $payload = json_decode($rawBody, true) ?? $request->all();
        $data = $payload['data'] ?? $payload;

        $id = (string)($data['id'] ?? '');
        $raw = strtolower((string)($data['status'] ?? ''));

        if ($id === '' || $raw === '') {
            Log::warning('PIX-OUT payload inválido (sem id/status)', ['payload' => $payload]);
            return ["success" => true];
        }

        $status = match ($raw) {
            'paid', 'success', 'succeeded', 'completed' => 'completed',
            'canceled', 'cancelled' => 'canceled',
            default => $raw,
        };

        try {
            DB::transaction(function () use ($id, $status, $data) {

                $withdraw = BalanceStatement::where('type', BalanceStatementType::Withdraw)
                    ->where(function ($q) use ($id) {
                        $q->where('invoice_id', $id)->orWhere('external_id', $id);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$withdraw) {
                    Log::warning('PIX-OUT withdraw não encontrado', ['id' => $id]);
                    return;
                }

                $current = strtolower((string)(is_object($withdraw->status) ? $withdraw->status->value : $withdraw->status));

                // ✅ idempotência: se já paid, não faz nada (evita debitar 2x)
                if ($current === 'paid') {
                    return;
                }

                // ✅ já reembolsado: webhook repetido ou fora de ordem não devolve saldo de novo
                if (in_array($current, ['failed', 'expired', 'canceled'], true)) {
                    return;
                }

                // ✅ status que NÃO mexem no saldo
                if ($status === 'pending') {
                    $withdraw->status = BalanceStatementStatus::Pending;
                    $withdraw->save();
                    return;
                }
                if ($status === 'failed') {
                    $user = User::where('id', $withdraw->user_id)->lockForUpdate()->first();
                    if (!$user) return;
                    $user->increment('balance', $withdraw->value);
                    $withdraw->status = BalanceStatementStatus::Failed;
                    $withdraw->save();
                    return;
                }
                if ($status === 'expired') {
                    $user = User::where('id', $withdraw->user_id)->lockForUpdate()->first();
                    if (!$user) return;
                    $user->increment('balance', $withdraw->value);
                    $withdraw->status = BalanceStatementStatus::Expired;
                    $withdraw->save();
                    return;
                }
                if ($status === 'canceled') {
                    $user = User::where('id', $withdraw->user_id)->lockForUpdate()->first();
                    if (!$user) return;
                    $user->increment('balance', $withdraw->value);
                    $withdraw->status = BalanceStatementStatus::Canceled;
                    $withdraw->save();
                    return;
                }

                // ✅ ÚNICO lugar que muda saldo: completed
                if ($status === 'completed') {
                    $withdraw->status = BalanceStatementStatus::Paid;
                    $withdraw->save();
                    return;
                }


                Log::warning('PIX-OUT status desconhecido', ['id' => $id, 'status' => $status]);
            }, 5);
        } catch (\Throwable $e) {
            Log::error('Webhook Novus PIX-OUT Error', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return abort(200);
        }

        return ["success" => true];
    }
}
