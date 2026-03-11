-- DashMed migration: add robust monotonic cursor to patient_data
-- Date: 2026-03-10
--
-- Purpose:
--  - Add `seq` BIGINT AUTO_INCREMENT as a monotonic cursor for exact chunk pagination.
--  - Keep existing composite PRIMARY KEY (id_patient, parameter_id, timestamp) for compatibility.
--
-- WARNING:
--  - On large tables (100M+ rows), this ALTER may take a long time and lock/impact performance.
--  - For dev, prefer recreating the DB volume from scratch with updated schema.

ALTER TABLE patient_data
  ADD COLUMN seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD UNIQUE KEY uq_patient_data_seq (seq),
  ADD INDEX ix_patient_param_seq (id_patient, parameter_id, seq);
