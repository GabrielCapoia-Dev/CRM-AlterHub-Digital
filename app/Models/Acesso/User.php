<?php

namespace App\Models\Acesso;

use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeMovimentacao;
use App\Models\OportunidadeTarefa;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'email_approved',
        'email_verified_at',
        'password',
    ];
    protected $guard_name = 'web';
    
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_approved' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (User $user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid();
            }
        });
    }

    private function emailAprovado(): bool
    {
        return (bool) $this->email_approved;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasVerifiedEmail() && $this->emailAprovado();
    }

    public function oportunidades(): HasMany
    {
        return $this->hasMany(
            Oportunidade::class,
            'user_id',
        );
    }

    public function oportunidadeInteracoes(): HasMany
    {
        return $this->hasMany(
            OportunidadeInteracao::class,
            'user_id',
        );
    }

    public function oportunidadeTarefas(): HasMany
    {
        return $this->hasMany(
            OportunidadeTarefa::class,
            'user_id',
        );
    }

    public function oportunidadeMovimentacoes(): HasMany
    {
        return $this->hasMany(
            OportunidadeMovimentacao::class,
            'user_id',
        );
    }
}
