#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-${PROJECT_ROOT}/var/backups/upgrades}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/pre-upgrade-${TIMESTAMP}.tar.gz"

mkdir -p "${BACKUP_DIR}"
umask 077

cd "${PROJECT_ROOT}"
tar -czf "${BACKUP_FILE}" config var

echo "Upgrade oncesi backup olusturuldu:"
echo "${BACKUP_FILE}"
