<?php

namespace App\Models;

use App\Models\Acesso\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DespesaOperacional extends Model
{
    protected $table = 'despesas_operacionais';

    protected $fillable = [
        'user_id',
        'produto_id',
        'descricao',
        'categoria',
        'subcategoria',
        'tipo',
        'valor',
        'data_competencia',
        'ano_referencia',
        'mes_referencia',
        'observacao',
    ];

    protected $casts = [
        'produto_id' => 'integer',
        'valor' => 'decimal:2',
        'data_competencia' => 'date',
        'ano_referencia' => 'integer',
        'mes_referencia' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $despesa): void {
            if (! $despesa->data_competencia) {
                return;
            }

            $data = $despesa->data_competencia instanceof Carbon
                ? $despesa->data_competencia
                : Carbon::parse($despesa->data_competencia);

            $despesa->ano_referencia = (int) $data->year;
            $despesa->mes_referencia = (int) $data->month;
        });
    }

    public static function categoriaOptions(): array
    {
        return [
            'administrativo' => 'Administrativo',
            'comercial' => 'Comercial',
            'fiscal_tributario' => 'Fiscal / tributario',
            'financeiro' => 'Financeiro',
            'logistica' => 'Logistica',
            'marketing' => 'Marketing',
            'operacao' => 'Operacao',
            'pessoal' => 'Pessoal',
            'tecnologia' => 'Tecnologia',
            'utilidades' => 'Utilidades',
        ];
    }

    public static function tipoOptions(): array
    {
        return [
            'fixa' => 'Fixa',
            'variavel' => 'Variavel',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
