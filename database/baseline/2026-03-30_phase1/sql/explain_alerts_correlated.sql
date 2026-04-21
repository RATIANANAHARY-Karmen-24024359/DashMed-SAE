EXPLAIN FORMAT=JSON
SELECT m.parameter_id, m.value, m.timestamp,
       r.display_name, r.unit,
       COALESCE(pat.normal_min,   r.normal_min)   AS normal_min,
       COALESCE(pat.normal_max,   r.normal_max)   AS normal_max,
       COALESCE(pat.critical_min, r.critical_min) AS critical_min,
       COALESCE(pat.critical_max, r.critical_max) AS critical_max
FROM (
    SELECT pd.parameter_id, pd.value, pd.timestamp, pd.id_patient
    FROM patient_data pd
    WHERE pd.id_patient = 8 AND pd.archived = 0 AND pd.value IS NOT NULL
      AND pd.timestamp = (
          SELECT MAX(p2.timestamp) FROM patient_data p2
          WHERE p2.parameter_id = pd.parameter_id AND p2.id_patient = pd.id_patient AND p2.archived = 0
      )
) m
JOIN parameter_reference r ON r.parameter_id = m.parameter_id
LEFT JOIN patient_alert_threshold pat
    ON pat.parameter_id = m.parameter_id AND pat.id_patient = m.id_patient
WHERE (COALESCE(pat.normal_min, r.normal_min) IS NOT NULL AND m.value <= COALESCE(pat.normal_min, r.normal_min))
   OR (COALESCE(pat.normal_max, r.normal_max) IS NOT NULL AND m.value >= COALESCE(pat.normal_max, r.normal_max))
ORDER BY
    CASE WHEN (COALESCE(pat.critical_min, r.critical_min) IS NOT NULL AND m.value <= COALESCE(pat.critical_min, r.critical_min))
           OR (COALESCE(pat.critical_max, r.critical_max) IS NOT NULL AND m.value >= COALESCE(pat.critical_max, r.critical_max)) THEN 0 ELSE 1 END,
    m.timestamp DESC;
