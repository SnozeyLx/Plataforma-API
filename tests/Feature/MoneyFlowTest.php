<?php

namespace Tests\Feature;

use App\Enums\BalanceStatementStatus;
use App\Enums\BalanceStatementType;
use App\Models\BalanceStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class MoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.novus.url' => 'https://novus.test',
            'services.novus.token' => 'token',
            'services.novus.webhook_token' => 'webhook-token',
            'jwt.secret' => str_repeat('t', 64),
        ]);

        // Configuração de saque (spatie/laravel-settings)
        foreach ([
            'minimum_per_operation' => 10,
            'maximum_per_operation' => 10000,
            'maximum_per_day' => 100000,
            'maximum_per_month' => 1000000,
            'active' => true,
        ] as $name => $value) {
            DB::table('settings')->insert([
                'group' => 'withdraw',
                'name' => "withdraw.$name",
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function userWithBalance(float $balance): User
    {
        $user = User::factory()->create([
            'balance' => $balance,
            'pix' => 'chave@teste.com',
            'pix_type' => 'EMAIL',
        ]);

        return $user;
    }

    private function withdraw(User $user, float $value, ?string $idempotencyKey = null)
    {
        $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $this->withHeaders($headers)->postJson('/balance/withdraw', ['value' => $value]);
    }

    public function test_saque_debita_o_valor_uma_unica_vez(): void
    {
        Http::fake(['*/api/v2/payout' => Http::response(['data' => ['payout_id' => 'p-1']])]);

        $user = $this->userWithBalance(100);

        $this->withdraw($user, 30)->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(70, $user->fresh()->balance);
    }

    public function test_mesma_chave_de_idempotencia_nao_paga_nem_debita_duas_vezes(): void
    {
        Http::fake(['*/api/v2/payout' => Http::response(['data' => ['payout_id' => 'p-2']])]);

        $user = $this->userWithBalance(100);

        $first = $this->withdraw($user, 30, 'chave-1')->json();
        $second = $this->withdraw($user, 30, 'chave-1')->json();

        $this->assertEquals('p-2', $first['external_id']);
        $this->assertEquals('p-2', $second['external_id']);
        $this->assertEquals(70, $user->fresh()->balance);
        Http::assertSentCount(1);
    }

    public function test_webhook_de_reembolso_repetido_nao_devolve_saldo_de_novo(): void
    {
        $user = $this->userWithBalance(70);

        BalanceStatement::create([
            'uuid' => 'uuid-saque-1',
            'type' => BalanceStatementType::Withdraw,
            'value' => 30,
            'invoice_id' => 'p-3',
            'external_id' => 'p-3',
            'status' => BalanceStatementStatus::Processing,
            'user_id' => $user->id,
        ]);

        $payload = ['data' => ['id' => 'p-3', 'status' => 'canceled']];

        $this->postJson('/webhook/novus/pixout?token=webhook-token', $payload)->assertOk();
        $this->postJson('/webhook/novus/pixout?token=webhook-token', $payload)->assertOk();
        $this->postJson('/webhook/novus/pixout?token=webhook-token', ['data' => ['id' => 'p-3', 'status' => 'failed']])->assertOk();

        $this->assertEquals(100, $user->fresh()->balance);
    }
}
