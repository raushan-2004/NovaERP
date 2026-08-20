<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NovaERP Application Configuration
    |--------------------------------------------------------------------------
    |
    | This file is the application-level configuration for NovaERP.
    | All env() calls for NovaERP-specific values are made here.
    | Application code must use config() — never env() directly.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Development Seed Configuration
    |--------------------------------------------------------------------------
    |
    | The seed admin password is used only for local development.
    | Set NOVA_ADMIN_PASSWORD in your local .env file.
    | This value must NEVER be hardcoded, logged, or committed.
    |
    */
    'seed_admin_password' => env('NOVA_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Allow Negative Stock
    |--------------------------------------------------------------------------
    |
    | When false (default), InventoryService will reject any stock operation
    | that would result in a negative balance. The operation will throw an
    | InsufficientStockException which maps to a 422 API response.
    |
    | Enabling this is a DELIBERATE business/inventory configuration decision
    | (e.g. pre-order fulfilment, consignment models). It should NOT be enabled
    | without understanding the full operational implications for your inventory
    | accuracy, financial reporting, and supplier reconciliation processes.
    |
    | This setting is NOT a user permission — it is an operational toggle.
    |
    */
    'allow_negative_stock' => env('NOVA_ALLOW_NEGATIVE_STOCK', false),
];

