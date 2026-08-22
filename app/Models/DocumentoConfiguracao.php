<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoConfiguracao extends Model
{
    public const CHAVE_PADRAO = 'padrao';

    protected $table = 'configuracoes_documentos';

    protected $attributes = [
        'chave' => self::CHAVE_PADRAO,
        'exibir_valores_romaneio' => true,
    ];

    protected $fillable = [
        'chave',
        'updated_by',
        'logo_disk',
        'logo_path',
        'razao_social',
        'cnpj',
        'nome_comercial',
        'texto_complementar',
        'exibir_valores_romaneio',
        'smtp_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'mail_from_address',
        'mail_from_name',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    protected $casts = [
        'updated_by' => 'integer',
        'exibir_valores_romaneio' => 'boolean',
        'smtp_enabled' => 'boolean',
        'smtp_port' => 'integer',
        'smtp_password' => 'encrypted',
    ];

    public static function atual(): ?self
    {
        return self::query()->where('chave', self::CHAVE_PADRAO)->first();
    }

    /**
     * @return list<string>
     */
    public function pendencias(): array
    {
        $pendencias = [];

        if (blank($this->logo_disk) || blank($this->logo_path)) {
            $pendencias[] = 'logotipo';
        }

        if (blank($this->razao_social)) {
            $pendencias[] = 'razao_social';
        }

        if (blank($this->cnpj)) {
            $pendencias[] = 'cnpj';
        }

        return $pendencias;
    }

    public function estaValida(): bool
    {
        return $this->pendencias() === [];
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
