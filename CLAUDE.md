# CLAUDE.md — ADC Portal

See `AGENTS.md` for the full project guide. The critical, HARD ENFORCED rule:

**Compute placement:** All heavy compute (PHP tests, SPA builds, E2E, `composer install`, `npm ci`) MUST run in **GitHub Actions**, never on the production host (Hostinger Business plan) or local machines unless a human explicitly approves. The host only serves the live app and runs lightweight `git pull` / `composer install --no-dev` / `php artisan migrate --force` / `config:cache` commands.

**Architecture:** Laravel 11 API (`/api/v1`, Sanctum cookie sessions) + React/Vite SPA (deployed into `public/`). Multi-tenant SaaS: every table is `business_id`-scoped; `ApiShape.php` is the API contract source of truth.

**Data safety:** Never commit `.env`/secrets/logs. Never weaken server-side authorization. Payment gateways are NOT wired — never fake payment success.
