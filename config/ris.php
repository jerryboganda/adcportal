<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application version
    |--------------------------------------------------------------------------
    |
    | The build identifier reported by /api/v1/health and by the platform
    | observability dashboard (§80: "which deployment versions exist?"). One
    | source of truth, so an operator reading the dashboard sees exactly the
    | version the health endpoint reports.
    |
    */

    'app_version' => env('RIS_APP_VERSION', 'v2-saas'),

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
            'infrastructure.manage', 'integrations.manage', 'operations.manage',
        ],
        'billing' => [
            'tenants.view', 'plans.manage', 'subscriptions.manage', 'usage.view', 'audit.view',
        ],
        'support' => [
            'tenants.view', 'support.manage', 'health.view', 'audit.view', 'operations.manage',
        ],
        'auditor' => [
            'tenants.view', 'usage.view', 'health.view', 'audit.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment topology (control-plane metadata)
    |--------------------------------------------------------------------------
    |
    | Per-tenant region / stamp / isolation metadata so one control plane can
    | manage pooled, isolated and dedicated deployments consistently. These are
    | operator-owned infrastructure descriptors: the platform console offers
    | exactly the values declared here and the API validates against them, so a
    | tenant can never be pinned to a region, stamp or isolation profile the
    | operator has not declared. Adding a region/stamp is a config change (and
    | therefore a deploy) — never an untracked database edit.
    |
    */

    'regions' => [
        'default' => ['label' => 'Default region', 'storage' => 'default'],
    ],

    'deployment_stamps' => [
        'stamp-a' => ['label' => 'Shared stamp A'],
    ],

    'isolation_profiles' => [
        'pooled' => 'Pooled (shared database + tenant-scoped rows)',
        'schema' => 'Schema isolation',
        'database' => 'Database per tenant',
        'dedicated' => 'Dedicated deployment',
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
        // White-label entitlements (master-prompt §18/§37/§38). Both default on
        // so existing tenants keep their current behaviour; a plan or platform
        // override can withhold them from lower tiers.
        'branding' => true,
        'custom_domains' => true,
        // Healthcare interoperability + tenant messaging integrations (§33).
        'interop' => true,
        'notifications' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant integration registry (§33/§44)
    |--------------------------------------------------------------------------
    |
    | The integration types this product actually implements, each mapped to the
    | entitlement that unlocks it, the keys it must be configured with, and how
    | its health is checked:
    |
    |   tcp    — real socket reachability probe to host:port
    |   http   — real HTTP request to the configured URL
    |   config — configuration completeness check only (no live send is faked)
    |
    | `secrets` are stored encrypted at rest (Laravel `encrypted:array`) and are
    | never returned by the API — only their presence and a mask.
    |
    */

    'integrations' => [
        'dicom' => [
            'label' => 'PACS / DICOM node',
            'feature' => 'dicom',
            'probe' => 'tcp',
            'required' => ['host', 'port', 'aeTitle'],
            'secrets' => [],
        ],
        'hl7' => [
            'label' => 'HL7 v2 / MLLP endpoint',
            'feature' => 'interop',
            'probe' => 'tcp',
            'required' => ['host', 'port'],
            'secrets' => [],
        ],
        'fhir' => [
            'label' => 'FHIR server',
            'feature' => 'interop',
            'probe' => 'fhir',
            'required' => ['baseUrl'],
            'secrets' => ['clientId', 'clientSecret'],
        ],
        'webhook' => [
            'label' => 'Outbound webhook',
            'feature' => 'interop',
            'probe' => 'http',
            'required' => ['url'],
            'secrets' => ['signingSecret'],
        ],
        'sms' => [
            'label' => 'SMS gateway',
            'feature' => 'notifications',
            'probe' => 'config',
            'required' => ['provider', 'endpoint'],
            'secrets' => ['apiKey', 'senderId'],
        ],
        'email' => [
            'label' => 'Outbound email (SMTP)',
            'feature' => 'notifications',
            'probe' => 'smtp',
            'required' => ['host', 'port', 'fromAddress'],
            'secrets' => ['username', 'password'],
        ],
        'whatsapp' => [
            'label' => 'WhatsApp Business',
            'feature' => 'notifications',
            'probe' => 'whatsapp',
            'required' => ['phoneNumberId'],
            'secrets' => ['accessToken'],
        ],
        /*
         * The clinic's OWN speech-to-text service.
         *
         * Browser speech recognition may hand clinical audio to a vendor's
         * cloud service, which some clinics cannot accept. Configuring one of
         * these makes the reporting editor dictate through a service the clinic
         * runs itself instead: it records locally and posts the audio to
         * `baseUrl`, which must answer JSON with the transcript.
         *
         * Most self-hosted engines run inside the clinic's own network without
         * authentication, so only `baseUrl` is required. The optional secrets
         * cover engines behind a gateway that expects an API token (`apiKey`)
         * or basic auth, but are enforced only when the deployment's
         * transcription service is actually called.
         */
        'dictation' => [
            'label' => 'Self-hosted dictation (speech-to-text)',
            'feature' => 'interop',
            'probe' => 'http',
            'required' => ['baseUrl'],
            'secrets' => [],
            // Accepted but not required — see the note above.
            'optionalSecrets' => ['apiKey', 'username', 'password'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform brand (white-label fallback)
    |--------------------------------------------------------------------------
    |
    | Used whenever a tenant has no branding row, and on the login screen when
    | the request host matches no tenant domain. Purely presentational.
    |
    */

    'platform_brand' => [
        'app_name' => env('RIS_PLATFORM_APP_NAME', 'PolytronX - Enterprise PACS & RIS'),
        'primary_color' => '#0e7490',
        'accent_color' => '#06b6d4',
        'logo_url' => null,
        'favicon_url' => null,
        'email_from_address' => env('MAIL_FROM_ADDRESS'),
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

    /*
    |--------------------------------------------------------------------------
    | Per-tenant API rate limit (noisy-neighbor guard)
    |--------------------------------------------------------------------------
    |
    | Aggregate requests/minute budget shared by ALL users of one active
    | tenant (switched members and support sessions count against the
    | tenant being operated). Exceeding it returns 429 for that tenant
    | only. The per-user `api` limiter (60/min) still applies underneath.
    |
    */

    'tenant_api_rate_limit_per_minute' => env('RIS_TENANT_API_RATE_LIMIT_PER_MINUTE', 2400),

    /*
    |--------------------------------------------------------------------------
    | TypeSafe System One (AI safety-screening triage)
    |--------------------------------------------------------------------------
    |
    | The screening triage service asks TypeSafe's System One evaluation model
    | (Jev) for typed judgments over a submitted safety-screening form and
    | stores an ADVISORY triage decision on the study. The deterministic
    | flagsRisk() gate stays authoritative; this only helps staff prioritise
    | review.
    |
    | Requests go through the Vercel AI Gateway (OpenAI-compatible host with a
    | dedicated evaluation surface at POST /v1/evaluate; there the yes/no
    | primitive is named "boolean" — TypeSafe's own docs call it "noul").
    | Read via config() — never env() directly — so config:cache keeps working.
    |
    | Fail-open by contract: when the key is missing or the gateway is down,
    | screening continues exactly as before with no triage payload.
    |
    */

    'typesafe' => [
        'enabled' => env('TYPESAFE_ENABLED', true),
        'api_key' => env('AI_GATEWAY_API_KEY'),
        'base_url' => env('AI_GATEWAY_BASE_URL', 'https://ai-gateway.vercel.sh'),
        // Optional absolute path to a PEM CA bundle. Leave empty for the
        // default strict system verification (CI/production). Only needed in
        // constrained environments whose PHP lacks a configured CA store
        // (e.g. a bare Windows PHP install), which would otherwise fail every
        // TLS handshake with cURL error 60.
        'ca_bundle' => env('TYPESAFE_CA_BUNDLE'),
        'model' => env('TYPESAFE_MODEL', 'typesafe-ai/jev'),
        'timeout_seconds' => env('TYPESAFE_TIMEOUT_SECONDS', 6),
        'max_attempts' => env('TYPESAFE_MAX_ATTEMPTS', 2),
        'max_backoff_ms' => env('TYPESAFE_MAX_BACKOFF_MS', 2000),
        // Confidence gates (docs.typesafe.ai/confidence). Calibrate on real
        // screening outcomes before trusting higher automation.
        'escalate_confidence' => env('TYPESAFE_ESCALATE_CONFIDENCE', 0.6),
        'clear_confidence' => env('TYPESAFE_CLEAR_CONFIDENCE', 0.85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Control-plane step-up re-authentication
    |--------------------------------------------------------------------------
    |
    | Mutating platform actions (suspend, terminate, branding, deployment...)
    | require a fresh password confirmation, standard for SaaS control
    | planes: a borrowed/attended-but-unlocked session must not be able to
    | take a clinic offline. `window` is how long one confirmation stays
    | valid. 428 responses carry `error: step_up_required`.
    |
    */

    'platform_step_up' => [
        'enabled' => env('RIS_PLATFORM_STEP_UP_ENABLED', true),
        'window_minutes' => env('RIS_PLATFORM_STEP_UP_WINDOW', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Printing & document rendering
    |--------------------------------------------------------------------------
    |
    | Two PDF engines exist and the difference is stated, never hidden:
    |
    |   dompdf   — always available, pure PHP, CSS 2.1 only. The archival
    |              fallback, driven from the same blade markup + token sheet.
    |   chromium — headless Chromium via Playwright. The pixel-true path: the
    |              same CSS the browser print dialog uses, rendered off the web
    |              tier by a queued job.
    |
    | `auto` prefers Chromium when it is actually usable (node + playwright +
    | chromium present) and silently falls back to DomPDF otherwise, recording
    | which engine produced each stored document. A clinic must never lose the
    | ability to print because a browser binary is missing.
    |
    | `archive` stores every finalized document PDF once and reuses it, so a
    | reprinted receipt or an archived report is byte-identical to the original
    |    filing.
    |
    */

    'print' => [
        'pdf_driver' => env('RIS_PRINT_PDF_DRIVER', 'auto'), // auto|dompdf|chromium
        'archive' => env('RIS_PRINT_ARCHIVE', true),
        'chromium' => [
            'enabled' => env('RIS_PRINT_CHROMIUM', true),
            'node' => env('RIS_PRINT_NODE_BINARY', 'node'),
            'script' => env('RIS_PRINT_CHROMIUM_SCRIPT', 'scripts/print-pdf.mjs'),
            'timeout' => (int) env('RIS_PRINT_CHROMIUM_TIMEOUT', 45),

            // Extra Chromium launch flags, comma separated. Deployment policy,
            // not application behaviour: a container needs `--no-sandbox`
            // (no user namespaces) and `--disable-dev-shm-usage` (a 64 MB
            // /dev/shm truncates tall receipts), while a workstation must keep
            // its sandbox. The production image sets these; nothing else does.
            'args' => env('RIS_PRINT_CHROMIUM_ARGS', ''),
        ],
    ],

];
