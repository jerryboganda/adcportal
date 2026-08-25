# AGENTS.md — ADC Portal (Radiology Clinic Management System)

Laravel 11 Radiology Information System (RIS) for Amad Diagnostic Centre:
study workflow, screening, reporting, invoicing, patient portal, public booking.

## HARD ENFORCED RULE — Compute Placement (do not violate)

- **ALL heavy compute runs in GitHub Actions, never on the production VPS (185.252.233.186) and never on local dev machines** unless a human explicitly approves a strict technical requirement.
  - Docker image builds → `.github/workflows/ci.yml` builds & pushes to `ghcr.io/jerryboganda/adcportal` (public).
  - Test suites → run in the `test` job of that workflow.
  - `composer install`, `npm ci`/`npm run build`, data processing, batch/automation → GitHub Actions only.
- The production VPS **only serves the live app**. Keep its CPU, memory, and disk pressure minimal. It must NOT run `docker compose build`, `composer install`, `npm run build`, or test runners.
- Deploy on the VPS is limited to: `docker compose pull` + `docker compose up -d` + lightweight `php artisan migrate` / `config:clear` / `storage:link` / `key:generate` commands.
- Exception to the above requires explicit human approval.

## Deployment & Data Safety

- Live data (MySQL, `storage`, `uploads`, `.env`) lives on **host bind-mounts under `/opt/adc-portal-data`**, which is **outside the code/repo directory** (`/opt/docker/adc-portal`). Rebuilds, `rm -rf` on the repo, CI, and coding agents cannot reach it.
- **Never** `rm -rf /opt/adc-portal-data`, and **never** run `docker compose down -v`.
- Immutable backups: `scripts/backup-portal.sh` snapshots `/opt/adc-portal-data` to `/opt/adc-portal-backups` (set `chattr +i` on archives). Run via cron; do not disable.
- Domain `portal.amaddiagnosticcentre.com.pk` is routed by the shared `nginx-proxy-manager` (network `nginx-proxy-manager_default`). The app container is `adc-portal-app` (port 80, mapped `127.0.0.1:8090`).
- Never commit `.env`, secrets, or runtime logs (`serve.*`).
- DB: dedicated `adc-portal-mysql` container; data at `/opt/adc-portal-data/mysql`.
