<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserPixType;
use App\Enums\UserRole;
use App\Models\Bet;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Notifications\ResetPasswordApiNotification;


class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tracker',
        'name',
        'last_name',
        'nick_name',
        'email',
        'password',
        'role',
        'birth_day',
        'phone',
        'address',
        'city',
        'state',
        'cpf',
        'post_code',
        'pix',
        'pix_type',
        'image_profile',
        'point',
        'point_daily',
        'point_weekly',
        'point_monthly',
        'roulette_coin',
        'biggest_bet',
        'biggest_win',
        'image_profile',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'pix_type' => UserPixType::class
        ];
    }

    /**
     * Retorna o identificador do usuário que será armazenado no token JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Retorna o ID do usuário
    }

    /**
     * Retorna um array de claims personalizados para serem adicionados ao token JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return []; // Você pode adicionar claims personalizados aqui, se necessário
    }


    public function bets()
    {
        return $this->hasMany(Bet::class);
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Adiciona pontos ao usuário em todos os campos (point, point_daily, point_weekly, point_monthly)
     *
     * @param float $points
     * @return void
     */
    public function addPoints(float $points): void
    {
        $this->increment('point', $points);
        $this->increment('point_daily', $points);
        $this->increment('point_weekly', $points);
        $this->increment('point_monthly', $points);
    }

    /**
     * Atualiza o maior valor apostado se o valor atual for maior
     *
     * @param float $betValue
     * @return void
     */
    public function updateBiggestBet(float $betValue): void
    {
        if ($this->biggest_bet === null || $betValue > $this->biggest_bet) {
            $this->biggest_bet = $betValue;
            $this->save();
        }
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordApiNotification($token));
    }
}
