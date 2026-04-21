-- Phase 6 (DESTRUCTIVE - GATED)
-- DO NOT RUN before:
--   1) backup/restore gate PASS,
--   2) compatibility gate PASS,
--   3) API/data parity PASS on restored dataset,
--   4) explicit go/no-go approval.

-- Purpose:
-- - Make seq the clustered PRIMARY KEY.
-- - Keep business-identity uniqueness on (id_patient, parameter_id, timestamp).
-- - Remove redundant legacy index after cutover.

ALTER TABLE `patient_data`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`seq`),
    ADD UNIQUE KEY `uq_patient_data_patient_param_ts` (`id_patient`, `parameter_id`, `timestamp`),
    DROP INDEX `ix_patient_param_time`;
