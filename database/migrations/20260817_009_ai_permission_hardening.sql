INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT DISTINCT rp.role_id,view_permission.id
FROM role_permissions rp
JOIN permissions manage_permission ON manage_permission.id=rp.permission_id AND manage_permission.slug='ai.manage'
JOIN permissions view_permission ON view_permission.slug='ai.view';
