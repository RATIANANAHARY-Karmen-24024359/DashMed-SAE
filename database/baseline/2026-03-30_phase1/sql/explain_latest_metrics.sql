EXPLAIN FORMAT=JSON
SELECT
    pr.parameter_id,
    pd.value,
    pd.`timestamp`,
    pd.alert_flag,
    pr.display_name,
    pr.category,
    pr.unit,
    pr.default_chart
FROM parameter_reference pr
LEFT JOIN patient_alert_threshold pat
  ON pat.parameter_id = pr.parameter_id
 AND pat.id_patient = 8
LEFT JOIN (
    SELECT pd1.*
    FROM patient_data pd1
    INNER JOIN (
        SELECT parameter_id, MAX(`timestamp`) AS ts
        FROM patient_data
        WHERE id_patient = 8 AND archived = 0
        GROUP BY parameter_id
    ) last
      ON last.parameter_id = pd1.parameter_id
     AND last.ts = pd1.`timestamp`
    WHERE pd1.id_patient = 8 AND pd1.archived = 0
) pd
  ON pd.parameter_id = pr.parameter_id
ORDER BY pr.display_name ASC;
