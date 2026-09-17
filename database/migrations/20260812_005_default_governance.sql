INSERT INTO settings (setting_key,setting_value,updated_by)
SELECT 'expected_yield_percent','70',NULL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key='expected_yield_percent');

INSERT INTO approval_workflows (name,module,transaction_type,min_amount,max_amount,status)
SELECT 'Standard Purchase Order Approval','purchase_orders','standard',0,99999.99,'active'
WHERE NOT EXISTS (SELECT 1 FROM approval_workflows WHERE name='Standard Purchase Order Approval');

INSERT INTO approval_workflows (name,module,transaction_type,min_amount,max_amount,status)
SELECT 'High Value Purchase Order Approval','purchase_orders','high_value',100000,NULL,'active'
WHERE NOT EXISTS (SELECT 1 FROM approval_workflows WHERE name='High Value Purchase Order Approval');

INSERT INTO approval_workflows (name,module,transaction_type,min_amount,max_amount,status)
SELECT 'Sales Order Approval','sales_orders','standard',0,NULL,'active'
WHERE NOT EXISTS (SELECT 1 FROM approval_workflows WHERE name='Sales Order Approval');

INSERT INTO approval_workflow_steps (workflow_id,step_number,role_id,label,is_required)
SELECT aw.id,1,r.id,'Purchase Manager Approval',1 FROM approval_workflows aw JOIN roles r ON r.slug='purchase_manager'
WHERE aw.name='Standard Purchase Order Approval' AND NOT EXISTS (SELECT 1 FROM approval_workflow_steps s WHERE s.workflow_id=aw.id AND s.step_number=1);

INSERT INTO approval_workflow_steps (workflow_id,step_number,role_id,label,is_required)
SELECT aw.id,1,r.id,'Purchase Manager Approval',1 FROM approval_workflows aw JOIN roles r ON r.slug='purchase_manager'
WHERE aw.name='High Value Purchase Order Approval' AND NOT EXISTS (SELECT 1 FROM approval_workflow_steps s WHERE s.workflow_id=aw.id AND s.step_number=1);

INSERT INTO approval_workflow_steps (workflow_id,step_number,role_id,label,is_required)
SELECT aw.id,2,r.id,'Finance Approval',1 FROM approval_workflows aw JOIN roles r ON r.slug='finance_manager'
WHERE aw.name='High Value Purchase Order Approval' AND NOT EXISTS (SELECT 1 FROM approval_workflow_steps s WHERE s.workflow_id=aw.id AND s.step_number=2);

INSERT INTO approval_workflow_steps (workflow_id,step_number,role_id,label,is_required)
SELECT aw.id,1,r.id,'Sales Manager Approval',1 FROM approval_workflows aw JOIN roles r ON r.slug='sales_manager'
WHERE aw.name='Sales Order Approval' AND NOT EXISTS (SELECT 1 FROM approval_workflow_steps s WHERE s.workflow_id=aw.id AND s.step_number=1);
