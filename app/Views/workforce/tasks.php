<?php use MeatinOS\Core\Csrf; ?>
<div class="module-toolbar card-panel">
    <div><p class="eyebrow">Workforce productivity</p><h2>Daily Employee Task Tracking</h2><p>Assigned, active, pending, and completed work is visible to each employee and their manager.</p></div>
    <form method="get" class="d-flex gap-2 align-items-end" novalidate><input type="hidden" name="route" value="workforce.tasks"><div><label class="form-label" for="task_filter_date">Task date</label><input class="form-control" id="task_filter_date" type="date" name="date" value="<?= e($taskDate) ?>"></div><button class="btn btn-danger" type="submit">View day</button></form>
</div>

<?php if (!empty($activity['last_reminder_at']) && strtotime((string)$activity['last_reminder_at']) > time()-86400): ?><div class="alert alert-warning"><i class="bi bi-bell me-2"></i>An office-hours inactivity reminder was raised. Updating a task or using the system records activity automatically.</div><?php endif; ?>

<div class="row g-3 mb-4">
<?php foreach (['assigned'=>'Assigned','in_progress'=>'In progress','pending'=>'Pending','completed'=>'Completed'] as $key=>$label): ?><div class="col-6 col-xl-3"><div class="card-panel task-stat"><span><?= e($label) ?></span><strong><?= e($counts[$key]) ?></strong></div></div><?php endforeach; ?>
</div>

<?php if ($canAssign): ?>
<section class="card-panel mb-4">
    <div class="panel-heading"><div><p class="eyebrow">Manager action</p><h3>Assign daily task</h3></div><i class="bi bi-person-check"></i></div>
    <form action="<?= e(url('workforce.tasks.save')) ?>" method="post" class="row g-3" novalidate data-prevent-double-submit><?= Csrf::field() ?>
        <div class="col-md-4"><label class="form-label" for="task_employee">Employee *</label><select class="form-select" id="task_employee" name="employee_id" required><option value="">Select employee</option><?php foreach($employees as $person): ?><option value="<?= e($person['id']) ?>"><?= e($person['employee_number'].' · '.$person['full_name'].' · '.$person['department']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label" for="task_date">Task date *</label><input class="form-control" id="task_date" type="date" name="task_date" value="<?= e($taskDate) ?>" required></div>
        <div class="col-md-4"><label class="form-label" for="task_title">Task title *</label><input class="form-control" id="task_title" name="title" maxlength="180" required></div>
        <div class="col-md-2"><label class="form-label" for="task_priority">Priority</label><select class="form-select" id="task_priority" name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></div>
        <div class="col-md-9"><label class="form-label" for="task_instructions">Instructions</label><textarea class="form-control" id="task_instructions" name="description" rows="2" maxlength="5000"></textarea></div>
        <div class="col-md-3"><label class="form-label" for="task_due_at">Due time</label><input class="form-control" id="task_due_at" type="datetime-local" name="due_at"></div>
        <div class="col-12 text-end"><button class="btn btn-danger" type="submit"><i class="bi bi-plus-lg me-1"></i>Assign task</button></div>
    </form>
</section>
<?php endif; ?>

<section class="card-panel table-panel">
    <div class="panel-heading"><div><p class="eyebrow"><?= e(date('j M Y',strtotime($taskDate))) ?></p><h3>Task register</h3></div><span><?= e(count($tasks)) ?> tasks</span></div>
    <div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Task</th><th>Employee</th><th>Manager</th><th>Priority</th><th>Due</th><th>Status</th><th>Update</th></tr></thead><tbody>
    <?php if (!$tasks): ?><tr><td colspan="7"><div class="table-empty"><i class="bi bi-list-check"></i><strong>No tasks recorded for this date</strong><span>Managers can assign work above; employees will see it here immediately.</span></div></td></tr><?php endif; ?>
    <?php foreach($tasks as $task): ?><tr><td><strong><?= e($task['task_number'].' · '.$task['title']) ?></strong><?php if ($task['description']): ?><small class="d-block text-muted"><?= e($task['description']) ?></small><?php endif; ?><?php if ($task['completion_notes']): ?><small class="d-block text-success">Result: <?= e($task['completion_notes']) ?></small><?php endif; ?></td><td><?= e($task['employee_number'].' · '.$task['full_name']) ?></td><td><?= e($task['manager_name'] ?: '—') ?></td><td><span class="badge text-bg-<?= e(in_array($task['priority'],['urgent','high'],true)?'danger':'secondary') ?>"><?= e(human_status($task['priority'])) ?></span></td><td><?= e($task['due_at'] ? date('j M, g:i A',strtotime($task['due_at'])) : '—') ?></td><td><span class="badge rounded-pill text-bg-<?= e(status_class($task['status'])) ?>"><?= e(human_status($task['status'])) ?></span></td><td>
        <?php $assignee=(int)$task['employee_id']===$employeeId; $manager=$canSeeAll || (int)($task['manager_id']??0)===$employeeId; ?>
        <?php if (($assignee || $manager) && !in_array($task['status'],['cancelled'],true)): ?><form action="<?= e(url('workforce.tasks.action')) ?>" method="post" class="task-action-form" novalidate data-prevent-double-submit><?= Csrf::field() ?><input type="hidden" name="id" value="<?= e($task['id']) ?>"><input type="hidden" name="task_date" value="<?= e($taskDate) ?>"><label class="visually-hidden" for="task_note_<?= e($task['id']) ?>">Progress or completion note</label><input class="form-control form-control-sm mb-1" id="task_note_<?= e($task['id']) ?>" name="completion_notes" maxlength="1500" placeholder="Progress / completion note"><div class="d-flex flex-wrap gap-1"><?php if ($assignee && $task['status']!=='in_progress'): ?><button class="btn btn-sm btn-outline-primary" name="task_action" value="start">Start</button><?php endif; ?><?php if ($assignee && $task['status']!=='completed'): ?><button class="btn btn-sm btn-success" name="task_action" value="complete">Complete</button><button class="btn btn-sm btn-outline-warning" name="task_action" value="pending">Pending</button><?php endif; ?><?php if ($manager && in_array($task['status'],['completed','cancelled'],true)): ?><button class="btn btn-sm btn-outline-primary" name="task_action" value="reopen">Reopen</button><?php endif; ?><?php if ($manager && $task['status']!=='cancelled'): ?><button class="btn btn-sm btn-outline-danger" name="task_action" value="cancel">Cancel</button><?php endif; ?></div></form><?php endif; ?>
    </td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
