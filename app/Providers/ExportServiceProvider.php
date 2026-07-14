<?php

namespace App\Providers;

use App\Models\Acesso\User;
use App\Models\InsumoMovimentacao;
use App\Models\ProdutoMovimentacao;
use App\Policies\ExportPolicy;
use App\Policies\InsumoMovimentacaoPolicy;
use App\Policies\ProdutoMovimentacaoPolicy;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authenticatable::class, User::class);
    }

    public function boot(): void
    {
        Gate::policy(Export::class, ExportPolicy::class);
        Gate::policy(InsumoMovimentacao::class, InsumoMovimentacaoPolicy::class);
        Gate::policy(ProdutoMovimentacao::class, ProdutoMovimentacaoPolicy::class);
    }
}
