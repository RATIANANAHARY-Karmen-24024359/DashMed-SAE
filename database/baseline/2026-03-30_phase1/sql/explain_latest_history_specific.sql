EXPLAIN FORMAT=JSON
SELECT parameter_id, value, `timestamp`, alert_flag
FROM (
    SELECT
        parameter_id, value, `timestamp`, alert_flag,
        ROW_NUMBER() OVER(PARTITION BY parameter_id ORDER BY `timestamp` DESC) AS rn
    FROM patient_data
    WHERE id_patient = 8
      AND archived = 0
      AND parameter_id IN ('VT_m','FiO2_m','GCS','Gly','FR_m')
) ranked
WHERE rn <= 1000
ORDER BY parameter_id, `timestamp` ASC;
