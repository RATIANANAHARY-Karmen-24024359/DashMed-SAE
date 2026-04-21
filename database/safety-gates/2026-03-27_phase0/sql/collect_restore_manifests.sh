#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TS="$(cat "${ROOT_DIR}/logs/timestamp.txt")"
CONTAINER="dashmed-restore-${TS}"
MANIFEST_DIR="${ROOT_DIR}/manifests"

# DB identity

docker exec "$CONTAINER" mariadb -N -uroot -prootsecret -e "SELECT NOW() AS captured_at, @@version AS mariadb_version, @@hostname AS host, @@server_id AS server_id" > "${MANIFEST_DIR}/restore_db_identity.tsv"

# Exact per-table counts

docker exec "$CONTAINER" sh -lc 'mariadb -N -uroot -prootsecret -e "SELECT table_name FROM information_schema.tables WHERE table_schema=\"dashmed_data\" ORDER BY table_name" | while read t; do mariadb -N -uroot -prootsecret -e "SELECT \"$t\" AS table_name, COUNT(*) AS row_count FROM dashmed_data.\`$t\`;"; done' > "${MANIFEST_DIR}/restore_table_counts.tsv"

# Core invariants

docker exec "$CONTAINER" mariadb -N -uroot -prootsecret -e "SELECT MIN(seq) AS min_seq, MAX(seq) AS max_seq, COUNT(*) AS total_rows, COUNT(DISTINCT parameter_id) AS parameter_count FROM dashmed_data.patient_data" > "${MANIFEST_DIR}/restore_patient_data_core.tsv"

# Window checks

docker exec -i "$CONTAINER" mariadb -N -uroot -prootsecret < "${ROOT_DIR}/sql/patient_data_window_checks.sql" > "${MANIFEST_DIR}/restore_patient_data_window_checks.tsv"

# Top 5 patient-8 tail checks using source-selected parameter list
> "${MANIFEST_DIR}/restore_patient8_top5_tail2000_checks.tsv"
while IFS= read -r p; do
  docker exec "$CONTAINER" mariadb -N -uroot -prootsecret -e "SELECT '$p' AS parameter_id, COUNT(*) AS sample_rows, MIN(seq) AS min_seq, MAX(seq) AS max_seq, SUM(CRC32(CONCAT_WS('|', seq, id_patient, parameter_id, DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s.%f'), value, IFNULL(alert_flag,''), archived))) AS checksum_crc32_sum FROM (SELECT seq,id_patient,parameter_id,timestamp,value,alert_flag,archived FROM dashmed_data.patient_data WHERE id_patient=8 AND parameter_id='$p' ORDER BY seq DESC LIMIT 2000) s" < /dev/null >> "${MANIFEST_DIR}/restore_patient8_top5_tail2000_checks.tsv"
done < "${MANIFEST_DIR}/source_patient8_top5_parameters.txt"
