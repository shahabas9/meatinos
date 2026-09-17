-- MeatinOS Migration: Employee fields, nullability and department linkage
-- Date: 2026-09-17

ALTER TABLE employees MODIFY overtime_rate DECIMAL(10,2) NULL DEFAULT 0.00;

UPDATE employees e
JOIN departments d ON LOWER(TRIM(d.name)) = LOWER(TRIM(e.department))
SET e.department_id = d.id
WHERE e.department_id IS NULL;

UPDATE employees e
SET e.plant_id = (SELECT id FROM plants WHERE status='active' ORDER BY id LIMIT 1)
WHERE e.plant_id IS NULL;
