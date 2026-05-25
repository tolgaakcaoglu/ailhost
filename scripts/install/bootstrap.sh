#!/usr/bin/env bash
set -euo pipefail

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

SYSTEM_CONFIG_PATH="${SYSTEM_CONFIG_PATH:-/etc/ailhost}"
SYSTEM_STATE_PATH="${SYSTEM_STATE_PATH:-/var/lib/ailhost}"
SYSTEM_LOG_PATH="${SYSTEM_LOG_PATH:-/var/log/ailhost}"

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOCAL_VAR_PATH="${LOCAL_VAR_PATH:-${PROJECT_ROOT}/var}"
ENV_FILE="${SYSTEM_CONFIG_PATH}/paths.env"

run_cmd() {
  if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "[dry-run] $*"
    return 0
  fi
  "$@"
}

require_root() {
  if [[ "${DRY_RUN}" -eq 1 ]]; then
    return 0
  fi
  if [[ "$(id -u)" -ne 0 ]]; then
    echo "Bu script root olarak calistirilmalidir." >&2
    exit 1
  fi
}

write_env_file() {
  local tmp_file
  tmp_file="$(mktemp)"
  cat > "${tmp_file}" <<EOF
SYSTEM_CONFIG_PATH=${SYSTEM_CONFIG_PATH}
SYSTEM_STATE_PATH=${SYSTEM_STATE_PATH}
SYSTEM_LOG_PATH=${SYSTEM_LOG_PATH}
LOCAL_VAR_PATH=${LOCAL_VAR_PATH}
PROJECT_ROOT=${PROJECT_ROOT}
EOF

  if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "[dry-run] ${ENV_FILE} yazilacak:"
    cat "${tmp_file}"
    rm -f "${tmp_file}"
    return 0
  fi

  install -m 0640 "${tmp_file}" "${ENV_FILE}"
  rm -f "${tmp_file}"
}

echo "ailhost bootstrap basliyor..."
require_root

run_cmd mkdir -p "${SYSTEM_CONFIG_PATH}" "${SYSTEM_STATE_PATH}" "${SYSTEM_LOG_PATH}" "${LOCAL_VAR_PATH}"
write_env_file

echo "Bootstrap tamamlandi."
echo "- SYSTEM_CONFIG_PATH: ${SYSTEM_CONFIG_PATH}"
echo "- SYSTEM_STATE_PATH: ${SYSTEM_STATE_PATH}"
echo "- SYSTEM_LOG_PATH: ${SYSTEM_LOG_PATH}"
echo "- LOCAL_VAR_PATH: ${LOCAL_VAR_PATH}"
