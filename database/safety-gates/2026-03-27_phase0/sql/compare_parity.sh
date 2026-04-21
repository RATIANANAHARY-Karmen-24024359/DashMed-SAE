#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST_DIR="${ROOT_DIR}/manifests"
LOG_DIR="${ROOT_DIR}/logs"
REPORT="${ROOT_DIR}/parity_report.md"

{
  echo "# Phase 0 Restore Parity Report"
  echo
  echo "Generated at: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  echo

  check_file_pair() {
    local label="$1"
    local src="$2"
    local dst="$3"
    if diff -u "$src" "$dst" > "${LOG_DIR}/${label}.diff"; then
      echo "- ${label}: PASS"
      rm -f "${LOG_DIR}/${label}.diff"
    else
      echo "- ${label}: FAIL (see ${LOG_DIR}/${label}.diff)"
    fi
  }

  check_file_pair "table_counts" "${MANIFEST_DIR}/source_table_counts.tsv" "${MANIFEST_DIR}/restore_table_counts.tsv"
  check_file_pair "patient_data_core" "${MANIFEST_DIR}/source_patient_data_core.tsv" "${MANIFEST_DIR}/restore_patient_data_core.tsv"
  check_file_pair "window_checks" "${MANIFEST_DIR}/source_patient_data_window_checks.tsv" "${MANIFEST_DIR}/restore_patient_data_window_checks.tsv"
  check_file_pair "patient8_top5_tail2000" "${MANIFEST_DIR}/source_patient8_top5_tail2000_checks.tsv" "${MANIFEST_DIR}/restore_patient8_top5_tail2000_checks.tsv"

  echo
  echo "## DB Identity"
  echo "Source:  $(tr '\t' '|' < "${MANIFEST_DIR}/source_db_identity.tsv")"
  echo "Restore: $(tr '\t' '|' < "${MANIFEST_DIR}/restore_db_identity.tsv")"
} > "$REPORT"

echo "$REPORT"
