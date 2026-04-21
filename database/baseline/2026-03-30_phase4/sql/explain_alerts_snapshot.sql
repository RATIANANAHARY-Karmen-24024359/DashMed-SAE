EXPLAIN FORMAT=JSON
SELECT pdl.parameter_id, pdl.value, pdl.timestamp,
       r.display_name, r.unit,
       COALESCE(pat.normal_min,   r.normal_min)   AS normal_min,
       COALESCE(pat.normal_max,   r.normal_max)   AS normal_max,
       COALESCE(pat.critical_min, r.critical_min) AS critical_min,
       COALESCE(pat.critical_max, r.critical_max) AS critical_max
FROM patient_data_latest pdl
JOIN parameter_reference r ON r.parameter_id = pdl.parameter_id
LEFT JOIN patient_alert_threshold pat
    ON pat.parameter_id = pdl.parameter_id AND pat.id_patient = pdl.id_patient
WHERE pdl.id_patient = 8
  AND pdl.archived = 0
  AND pdl.value IS NOT NULL
  AND (
        (COALESCE(pat.normal_min, r.normal_min) IS NOT NULL AND pdl.value <= COALESCE(pat.normal_min, r.normal_min))
     OR (COALESCE(pat.normal_max, r.normal_max) IS NOT NULL AND pdl.value >= COALESCE(pat.normal_max, r.normal_max))
  )
ORDER BY
    CASE WHEN (COALESCE(pat.critical_min, r.critical_min) IS NOT NULL AND pdl.value <= COALESCE(pat.critical_min, r.critical_min))
           OR (COALESCE(pat.critical_max, r.critical_max) IS NOT NULL AND pdl.value >= COALESCE(pat.critical_max, r.critical_max)) THEN 0 ELSE 1 END,
    pdl.timestamp DESC;
