# CLAUDE.md — ADC Portal

See `AGENTS.md` for the full project guide. The critical, HARD ENFORCED rule:

**Compute placement:** All heavy compute (Docker image builds, test suites, `composer install`, `npm ci`/`npm run build`, data processing, automation) MUST run in **GitHub Actions**, never on the production VPS (185.252.233.186) or local machines unless a human explicitly approves. The VPS only serves the live app and runs lightweight `docker compose pull` / `up` / `migrate` commands. Builds are produced by `.github/workflows/ci.yml` and pushed to `ghcr.io/jerryboganda/adcportal` (public).

**Data safety:** Live data is on host bind-mounts under `/opt/adc-portal-data` (outside the repo). Never delete that path or run `docker compose down -v`. Never commit `.env`/secrets/logs.
