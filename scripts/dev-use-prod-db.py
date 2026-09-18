#!/usr/bin/env python
"""Point the local Laravel .env at the production shared PostgreSQL.

Reads the house-provisioned ris DB credentials on the VPS (server-side,
never printed), backs up .env, then rewrites the DB_* keys to go through
the SSH tunnel started by scripts/dev-tunnel.sh.

Re-run any time to re-sync credentials (e.g. after a password rotation).
Rollback: copy the printed .env.pre-prod-* backup back over .env.
"""
import pathlib
import re
import subprocess
import sys
import time

ROOT = pathlib.Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / ".env"

WANTED = {
    "DB_CONNECTION": "pgsql",
    "DB_HOST": "127.0.0.1",
    "DB_PORT": "15433",
    "DB_DATABASE": "ris",
    "DB_USERNAME": "ris",
    # DB_PASSWORD is fetched from the VPS below
}


def vps_pg_password() -> str:
    out = subprocess.run(
        [
            "ssh", "-o", "ConnectTimeout=10", "vps",
            "grep '^PLATFORM_PG_PASSWORD=' /opt/platform/projects/ris.env | cut -d= -f2-",
        ],
        capture_output=True,
        text=True,
        check=True,
    )
    pw = out.stdout.strip()
    if not pw:
        sys.exit("ERROR: could not read PLATFORM_PG_PASSWORD from the VPS")
    return pw


def main() -> None:
    wanted = dict(WANTED)
    wanted["DB_PASSWORD"] = vps_pg_password()

    ts = time.strftime("%Y%m%d-%H%M%S")
    backup_dir = ROOT / "storage" / "logs" / "deploy" / "env-backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    backup = backup_dir / f".env.pre-prod-{ts}"
    backup.write_bytes(ENV_PATH.read_bytes())
    print(f"env backed up -> {backup}")

    lines = ENV_PATH.read_text().splitlines()
    seen: set[str] = set()
    out: list[str] = []
    for line in lines:
        m = re.match(r"^([A-Z0-9_]+)=", line)
        if m and m.group(1) in wanted:
            key = m.group(1)
            out.append(f"{key}={wanted[key]}")
            seen.add(key)
        else:
            out.append(line)
    for key, val in wanted.items():
        if key not in seen:
            out.append(f"{key}={val}")
    ENV_PATH.write_text("\n".join(out) + "\n")

    print(".env now points at PRODUCTION Postgres db 'ris' via tunnel localhost:15433")
    print(f"rollback: copy {backup.name} from storage/logs/deploy/env-backups back over .env")


if __name__ == "__main__":
    main()
