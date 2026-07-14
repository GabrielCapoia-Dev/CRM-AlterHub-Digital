<?php

use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/painel/operacao/dashboard-bi', '/painel/dashboard/visao-geral');
Route::permanentRedirect('/painel/operacao/resultado', '/painel/dashboard/dre');
Route::permanentRedirect('/painel/operacao/lucro-por-produto', '/painel/dashboard/produtos');

Route::get('/', function () {
    return view('welcome');
});
