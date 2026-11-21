<?php

return [
    'cod_enabled' => (bool) env('MARKETPLACE_COD_ENABLED', false),
    'webhook_secrets' => [
        'generic' => env('MARKETPLACE_GENERIC_WEBHOOK_SECRET'),
    ],
    'checkout_quote_minutes' => (int) env('MARKETPLACE_CHECKOUT_QUOTE_MINUTES', 15),
    'stock_reservation_minutes' => (int) env('MARKETPLACE_STOCK_RESERVATION_MINUTES', 30),
    'backup' => [
        'mysqldump_path' => env('MARKETPLACE_MYSQLDUMP_PATH', 'mysqldump'),
    ],
];
