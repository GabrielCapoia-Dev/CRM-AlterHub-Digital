<?php

$defaultExportChunkSize = (int) env('CRM_EXPORT_CHUNK_SIZE', 250);

return [
    'force_https' => filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),

    'exports' => [
        // Null values keep exports on Laravel's default queued connection/queue.
        'connection' => env('CRM_EXPORT_CONNECTION'),
        'queue' => env('CRM_EXPORT_QUEUE'),
        'chunk_size' => $defaultExportChunkSize,
        'chunk_sizes' => [
            'produtos' => (int) env('CRM_EXPORT_PRODUCTS_CHUNK_SIZE', $defaultExportChunkSize),
            'estoque_atual' => (int) env('CRM_EXPORT_STOCK_CHUNK_SIZE', $defaultExportChunkSize),
            'produto_movimentacoes' => (int) env('CRM_EXPORT_PRODUCT_MOVEMENTS_CHUNK_SIZE', $defaultExportChunkSize),
            'insumo_movimentacoes' => (int) env('CRM_EXPORT_INPUT_MOVEMENTS_CHUNK_SIZE', $defaultExportChunkSize),
            'vendas' => (int) env('CRM_EXPORT_SALES_CHUNK_SIZE', $defaultExportChunkSize),
        ],
    ],
];
