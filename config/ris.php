<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo environment
    |--------------------------------------------------------------------------
    |
    | Demo data (the DEMO tenant, fake patients/studies) must never exist in a
    | production database. Everything demo-related is inert unless
    | RIS_DEMO_MODE=true is set explicitly — local development and the CI e2e
    | job only. Read these values via config(), never env(), so they keep
    | working after `config:cache`.
    |
    */

    'demo_mode' => env('RIS_DEMO_MODE', false),

    'demo_tenant_code' => env('RIS_DEMO_TENANT_CODE', 'DEMO-2026'),

    'demo_admin_email' => env('RIS_DEMO_ADMIN_EMAIL'),

    'demo_password' => env('RIS_DEMO_PASSWORD'),

    'super_admin_email' => env('RIS_SUPER_ADMIN_EMAIL'),

    'super_admin_password' => env('RIS_SUPER_ADMIN_PASSWORD'),

];
