INSERT IGNORE INTO dispatch_checks (dispatch_id,check_code,label,is_mandatory,passed)
SELECT d.id,defaults.check_code,defaults.label,1,0
FROM dispatches d
CROSS JOIN (
    SELECT 'vehicle_available' AS check_code,'Vehicle available and assigned' AS label
    UNION ALL SELECT 'driver_assigned','Driver assigned and documents verified'
    UNION ALL SELECT 'vehicle_condition','Vehicle condition acceptable'
    UNION ALL SELECT 'refrigeration','Refrigeration operating'
    UNION ALL SELECT 'temperature','Load temperature within limit'
    UNION ALL SELECT 'fuel','Fuel level sufficient'
    UNION ALL SELECT 'documents','Delivery documents complete'
    UNION ALL SELECT 'cleanliness','Cargo area clean and sanitized'
    UNION ALL SELECT 'loading_condition','Load secured and loading condition accepted'
) defaults
WHERE d.status IN ('scheduled','loading','in_transit','delayed');
