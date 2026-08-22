<?php

namespace App\Models\Acesso;

use App\Models\Clientes\Cliente;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeMovimentacao;
use App\Models\OportunidadeTarefa;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

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
            'must_change_password' => 'boolean',
            'password_reset_at' => 'datetime',
            'password_changed_at' => 'datetime',
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

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function emailAprovado(): bool
    {
        return (bool) $this->email_approved;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // O cadastro e a liberacao de acesso sao administrados internamente.
        // Nao existe fluxo publico de verificacao de e-mail neste painel, logo
        // email_verified_at nao pode impedir o acesso de um usuario aprovado.
        return $this->emailAprovado();
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

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'vendedor_id');
    }

    public function vendasOperacaoPedidos(): HasMany
    {
        return $this->hasMany(VendaOperacaoPedido::class, 'user_id');
    }

    public function vendasOperacao(): HasMany
    {
        return $this->hasMany(VendaOperacao::class, 'user_id');
    }

    public function passwordResetBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'password_reset_by');
    }

    public function passwordResetsPerformed(): HasMany
    {
        return $this->hasMany(self::class, 'password_reset_by');
    }
}
