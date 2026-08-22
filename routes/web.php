<?php

use App\Http\Controllers\Auth\RequiredPasswordChangeController;
use App\Http\Controllers\Documentos\PedidoPdfController;
use App\Http\Controllers\Documentos\RomaneioPdfController;
use App\Http\Controllers\Documentos\VendaPedidoFotoController;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/painel/operacao/dashboard-bi', '/painel/dashboard/visao-geral');
Route::permanentRedirect('/painel/operacao/resultado', '/painel/dashboard/dre');
Route::permanentRedirect('/painel/operacao/lucro-por-produto', '/painel/dashboard/produtos');

Route::redirect('/', '/painel/login');

Route::middleware(['auth', AuthenticateSession::class])
    ->prefix('painel')
    ->name('password.change-required.')
    ->group(function (): void {
        Route::get('nova-senha', [RequiredPasswordChangeController::class, 'edit'])
            ->name('edit');
        Route::post('nova-senha', [RequiredPasswordChangeController::class, 'update'])
            ->name('update');
    });

Route::middleware('auth')
    ->prefix('documentos')
    ->name('documentos.')
    ->group(function (): void {
        Route::get('pedidos/{pedido}/pdf', PedidoPdfController::class)
            ->name('pedidos.pdf');
        Route::get('pedidos/{pedido}/fotos/{foto}', [VendaPedidoFotoController::class, 'visualizar'])
            ->name('pedidos.fotos.visualizar');
        Route::get('pedidos/{pedido}/fotos/{foto}/baixar', [VendaPedidoFotoController::class, 'baixar'])
            ->name('pedidos.fotos.baixar');
        Route::get('romaneios/{romaneio}/pdf', RomaneioPdfController::class)
            ->name('romaneios.pdf');
    });
