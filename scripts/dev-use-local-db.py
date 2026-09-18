#!/usr/bin/env python
"""Switch local dev back to its own database.

Restores the most recent .env.pre-prod-* backup made by
scripts/dev-use-prod-db.py, so localhost returns to the local MySQL
database (or whatever preceded the prod link).
"""
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
BACKUP_DIR = ROOT / "storage" / "logs" / "deploy" / "env-backups"


def main() -> None:
    backups = sorted(BACKUP_DIR.glob(".env.pre-prod-*"))
    if not backups:
        sys.exit("no .env.pre-prod-* backups found; nothing to restore")
    latest = backups[-1]
    (ROOT / ".env").write_bytes(latest.read_bytes())
    print(f".env restored from {latest.name} (local database)")
    print("restart `php artisan serve` / vite so the change takes effect")


if __name__ == "__main__":
    main()
