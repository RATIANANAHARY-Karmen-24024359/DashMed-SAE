EXPLAIN FORMAT=JSON
SELECT
    pr.parameter_id,
    pdl.value,
    pdl.`timestamp`,
    pdl.alert_flag,
    pr.display_name,
    pr.category,
    pr.unit,
    pr.description,
    COALESCE(pat.normal_min, pr.normal_min) as normal_min,
    COALESCE(pat.normal_max, pr.normal_max) as normal_max,
    COALESCE(pat.critical_min, pr.critical_min) as critical_min,
    COALESCE(pat.critical_max, pr.critical_max) as critical_max,
    pr.display_min,
    pr.display_max
FROM parameter_reference pr
LEFT JOIN patient_alert_threshold pat
  ON pat.parameter_id = pr.parameter_id
 AND pat.id_patient = 8
LEFT JOIN patient_data_latest pdl
  ON pdl.parameter_id = pr.parameter_id
 AND pdl.id_patient = 8
 AND pdl.archived = 0
ORDER BY pr.display_name ASC;
