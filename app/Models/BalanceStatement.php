<?php

namespace App\Models;

use App\Enums\BalanceStatementStatus;
use App\Enums\BalanceStatementType;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BalanceStatement extends Model
{
    protected $fillable = [
        "uuid",
        "type",
        "value",
        "external_id",
        "invoice_id",
        "description",
        "qr_code",
        "qr_code_image",
        "payment_link",
        "expiration",
        "status",
        "user_id"
    ];

    protected $hidden = [
        "id"
    ];

    protected $casts = [
        "type" => BalanceStatementType::class
    ];

    /**
     * Mutator para o atributo status.
     * Automaticamente define como 'expired' se estiver 'pending' e created_at > 23:59 horas
     */
    public function getStatusAttribute($value)
    {
        // Verifica se o valor é válido
        if (empty($value)) {
            return null;
        }

        // Se o status atual é pending, verifica se já expirou
        if ($value === BalanceStatementStatus::Pending->value) {
            // Verifica se created_at existe
            if (isset($this->attributes['created_at'])) {
                $createdAt = Carbon::parse($this->attributes['created_at']);
                $now = Carbon::now();

                // Verifica se passou mais de 23 horas e 59 minutos (1439 minutos)
                if ($createdAt->diffInMinutes($now) > 1439) {
                    // Atualiza o status no banco de dados
                    $this->updateQuietly(['status' => BalanceStatementStatus::Expired]);
                    return BalanceStatementStatus::Expired;
                }
            }
        }

        // Tenta converter para enum, retorna null se falhar
        try {
            return BalanceStatementStatus::from($value);
        } catch (\ValueError $e) {
            return null;
        }
    }
}
