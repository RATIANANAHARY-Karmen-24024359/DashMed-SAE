SET @min_seq := (SELECT MIN(seq) FROM dashmed_data.patient_data);
SET @max_seq := (SELECT MAX(seq) FROM dashmed_data.patient_data);
SET @mid_seq := FLOOR((@min_seq + @max_seq) / 2);

SELECT
  'W1_HEAD_100K' AS sample_id,
  @min_seq AS from_seq,
  LEAST(@min_seq + 99999, @max_seq) AS to_seq,
  COUNT(*) AS row_count,
  SUM(CRC32(CONCAT_WS('|', seq, id_patient, parameter_id, DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s.%f'), value, IFNULL(alert_flag,''), archived))) AS checksum_crc32_sum
FROM dashmed_data.patient_data
WHERE seq BETWEEN @min_seq AND LEAST(@min_seq + 99999, @max_seq)
UNION ALL
SELECT
  'W2_MID_100K' AS sample_id,
  GREATEST(@mid_seq - 50000, @min_seq) AS from_seq,
  LEAST(@mid_seq + 49999, @max_seq) AS to_seq,
  COUNT(*) AS row_count,
  SUM(CRC32(CONCAT_WS('|', seq, id_patient, parameter_id, DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s.%f'), value, IFNULL(alert_flag,''), archived))) AS checksum_crc32_sum
FROM dashmed_data.patient_data
WHERE seq BETWEEN GREATEST(@mid_seq - 50000, @min_seq) AND LEAST(@mid_seq + 49999, @max_seq)
UNION ALL
SELECT
  'W3_TAIL_100K' AS sample_id,
  GREATEST(@max_seq - 99999, @min_seq) AS from_seq,
  @max_seq AS to_seq,
  COUNT(*) AS row_count,
  SUM(CRC32(CONCAT_WS('|', seq, id_patient, parameter_id, DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s.%f'), value, IFNULL(alert_flag,''), archived))) AS checksum_crc32_sum
FROM dashmed_data.patient_data
WHERE seq BETWEEN GREATEST(@max_seq - 99999, @min_seq) AND @max_seq;
