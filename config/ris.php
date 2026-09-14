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

    /*
    |--------------------------------------------------------------------------
    | Platform control plane
    |--------------------------------------------------------------------------
    |
    | Platform staff (users.type = super_admin or platform_admin) operate the
    | SaaS vendor side: tenants, plans, subscriptions, support sessions and
    | audit. `platform_roles` maps each platform_role value to the platform
    | capabilities it holds; super_admin implicitly holds every capability.
    |
    */

    'platform_roles' => [
        'ops' => [
            'tenants.view', 'tenants.manage', 'tenants.lifecycle', 'provisioning.manage',
            'usage.view', 'health.view', 'audit.view', 'platform.users.view',
        ],
        'billing' => [
            'tenants.view', 'plans.manage', 'subscriptions.manage', 'usage.view', 'audit.view',
        ],
        'support' => [
            'tenants.view', 'support.manage', 'health.view', 'audit.view',
        ],
        'auditor' => [
            'tenants.view', 'usage.view', 'health.view', 'audit.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module feature flags (server-enforced entitlements)
    |--------------------------------------------------------------------------
    |
    | Resolution precedence (deterministic, tested): an explicit per-tenant
    | platform override wins → then the tenant's plan `features` JSON → then
    | the platform default below. A false anywhere below the override layer
    | disables the module for that tenant on BOTH the API and the SPA.
    |
    */

    'features' => [
        'inventory' => true,
        'dicom' => true,
        'dispatch' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant offboarding / retention
    |--------------------------------------------------------------------------
    |
    | Terminated tenants keep their data until `data_retention_until`
    | (now + retention_days) passes. Destruction of retained clinical data is a
    | separate, explicitly-confirmed operator action — never automatic.
    |
    */

    'retention_days' => env('RIS_TERMINATED_RETENTION_DAYS', 90),

    'support_session_max_minutes' => 240,

];
