#!/usr/bin/env bash
#
# Verify that a database backup dump can actually be restored, without touching
# the real application database. Creates a uniquely-named scratch database,
# restores the given dump into it, runs a few sanity checks, then drops it.
#
# Usage: scripts/verify-backup-restore.sh path/to/dump.sql[.gz] [--keep]
#
# Reads DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD from .env in the project
# root. Never reads or uses DB_DATABASE from .env — the restore target is
# always a freshly generated scratch database under the mnch_backup_verify_
# prefix, and the script refuses to DROP anything outside that prefix.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 path/to/dump.sql[.gz] [--keep]" >&2
    echo "" >&2
    echo "Known dumps in database/dbsql/:" >&2
    ls -1 "$PROJECT_ROOT/database/dbsql/" 2>/dev/null | sed 's/^/  /' >&2 || true
    exit 1
fi

DUMP_FILE="$1"
KEEP_DB="${2:-}"

if [[ ! -f "$DUMP_FILE" ]]; then
    echo "ERROR: dump file not found: $DUMP_FILE" >&2
    exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: .env not found at $ENV_FILE — cannot read DB credentials" >&2
    exit 1
fi

read_env_var() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(read_env_var DB_HOST)"
DB_PORT="$(read_env_var DB_PORT)"
DB_USERNAME="$(read_env_var DB_USERNAME)"
DB_PASSWORD="$(read_env_var DB_PASSWORD)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-root}"

SAFETY_PREFIX="mnch_backup_verify_"
SCRATCH_DB="${SAFETY_PREFIX}$(date +%Y%m%d%H%M%S)_$$"

if [[ "$SCRATCH_DB" != ${SAFETY_PREFIX}* ]]; then
    echo "ERROR: refusing to continue — generated scratch DB name doesn't match safety prefix" >&2
    exit 1
fi

MYSQL_ARGS=(--host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME")
if [[ -n "$DB_PASSWORD" ]]; then
    export MYSQL_PWD="$DB_PASSWORD"
fi

cleanup() {
    if [[ "$KEEP_DB" != "--keep" ]]; then
        echo "Cleaning up: dropping scratch database $SCRATCH_DB"
        mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`${SCRATCH_DB}\`;" || true
    else
        echo "Keeping scratch database $SCRATCH_DB (--keep passed) — drop it manually when done:"
        echo "  mysql -h $DB_HOST -P $DB_PORT -u $DB_USERNAME -p -e \"DROP DATABASE \\\`${SCRATCH_DB}\\\`;\""
    fi
}
trap cleanup EXIT

echo "Creating scratch database: $SCRATCH_DB"
mysql "${MYSQL_ARGS[@]}" -e "CREATE DATABASE \`${SCRATCH_DB}\`;"

echo "Restoring $DUMP_FILE into $SCRATCH_DB ..."
if [[ "$DUMP_FILE" == *.gz ]]; then
    gunzip -c "$DUMP_FILE" | mysql "${MYSQL_ARGS[@]}" "$SCRATCH_DB"
else
    mysql "${MYSQL_ARGS[@]}" "$SCRATCH_DB" < "$DUMP_FILE"
fi

echo "Restore completed without a fatal error. Running sanity checks..."

TABLE_COUNT=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${SCRATCH_DB}';")
echo "Tables restored: $TABLE_COUNT"

if [[ "$TABLE_COUNT" -eq 0 ]]; then
    echo "ERROR: restore produced zero tables — dump is likely empty or invalid" >&2
    exit 1
fi

USERS_TABLE_EXISTS=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${SCRATCH_DB}' AND table_name = 'users';")
if [[ "$USERS_TABLE_EXISTS" -eq 1 ]]; then
    USER_COUNT=$(mysql "${MYSQL_ARGS[@]}" -N -e "SELECT COUNT(*) FROM \`${SCRATCH_DB}\`.users;")
    echo "users row count in restored dump: $USER_COUNT"
else
    echo "WARNING: restored dump has no 'users' table — may be a partial dump, review manually" >&2
fi

echo ""
echo "PASS: $DUMP_FILE restored successfully into a scratch database ($TABLE_COUNT tables)."
