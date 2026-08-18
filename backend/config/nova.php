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
];
