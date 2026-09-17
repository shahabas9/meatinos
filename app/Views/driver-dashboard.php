<?php

use MeatinOS\Core\Csrf;

$driverTransitions = [
    'scheduled' => ['scheduled', 'loading', 'delayed'],
    'loading' => ['loading', 'in_transit', 'delayed'],
    'in_transit' => ['in_transit', 'delivered', 'delayed'],
    'delayed' => ['delayed', 'in_transit', 'delivered'],
    'delivered' => ['delivered'],
    'cancelled' => ['cancelled'],
];
?>
<div class="report-hero driver-portal-hero">
    <div>
        <p class="eyebrow">Mobile delivery workspace</p>
        <h2>Good day, <?= e($driver['full_name']) ?></h2>
        <p>Assigned routes, reefer temperature, delivery status, and proof of delivery are isolated to your account.</p>
    </div>
    <a class="btn btn-danger" href="<?= e(url('module', ['name' => 'dispatches'])) ?>"><i class="bi bi-truck me-2"></i>Open my dispatches</a>
</div>

<div class="report-kpi-grid customer-kpi-grid">
    <div class="report-kpi"><span>Assigned routes</span><strong><?= e($cards['assigned']) ?></strong><small>Your delivery history</small></div>
    <div class="report-kpi"><span>Active</span><strong><?= e($cards['active']) ?></strong><small>Scheduled, loading, or in transit</small></div>
    <div class="report-kpi"><span>Delivered</span><strong><?= e($cards['delivered']) ?></strong><small>Proof of delivery completed</small></div>
    <div class="report-kpi"><span>Delayed</span><strong><?= e($cards['delayed']) ?></strong><small>Requires dispatch attention</small></div>
</div>

<article class="card-panel table-panel mt-4">
    <div class="panel-heading p-3 mb-0"><div><h2>My Delivery Routes</h2><p>Update status, temperature, GPS position, delivery notes, and POD from the secure driver action.</p></div></div>
    <div class="table-responsive">
        <table class="table data-table">
            <thead><tr><th>Dispatch</th><th>Order / customer</th><th>Destination</th><th>Vehicle</th><th>Departure</th><th>Cold chain</th><th>Status</th><th>POD</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($dispatches as $row): ?>
                <tr>
                    <td><strong><?= e($row['dispatch_number']) ?></strong></td>
                    <td><?= e($row['order_number']) ?><small class="d-block text-secondary"><?= e($row['customer_name']) ?></small></td>
                    <td><?= e($row['route_name']) ?><small class="d-block text-secondary"><?= e($row['delivery_address']) ?></small></td>
                    <td><?= e($row['registration_number']) ?><small class="d-block text-secondary"><?= e($row['make_model']) ?></small></td>
                    <td><?= e(date('j M Y, g:i A', strtotime((string) $row['planned_departure']))) ?></td>
                    <td><strong><?= e(number_format((float) $row['temperature_c'], 1)) ?>&deg;C</strong></td>
                    <td><span class="badge rounded-pill text-bg-<?= e(status_class((string) $row['status'])) ?>"><?= e(human_status((string) $row['status'])) ?></span></td>
                    <td><?= e($row['pod_reference'] ?: 'Pending') ?></td>
                    <td><button class="btn btn-sm btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#driverDispatch<?= e($row['id']) ?>" aria-label="Update <?= e($row['dispatch_number']) ?>"><i class="bi bi-pencil"></i></button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$dispatches): ?><tr><td colspan="9"><div class="table-empty"><i class="bi bi-truck"></i><strong>No assigned routes</strong><span>Dispatch will appear when a route is assigned to you.</span></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</article>

<?php foreach ($dispatches as $row): ?>
    <div class="modal fade" id="driverDispatch<?= e($row['id']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <form class="modal-content" method="post" action="<?= e(url('driver.dispatch.update')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="dispatch_id" value="<?= e($row['id']) ?>">
                <div class="modal-header"><div><p class="eyebrow mb-1"><?= e($row['dispatch_number']) ?></p><h2 class="modal-title fs-4">Update delivery</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><div class="row g-3">
                    <div class="col-6"><label class="form-label" for="driverStatus<?= e($row['id']) ?>">Status</label><select class="form-select" id="driverStatus<?= e($row['id']) ?>" name="status" required><?php foreach ($driverTransitions[$row['status']] ?? [$row['status']] as $option): ?><option value="<?= e($option) ?>" <?= $option === $row['status'] ? 'selected' : '' ?>><?= e(human_status($option)) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6"><label class="form-label" for="driverTemp<?= e($row['id']) ?>">Reefer temperature (C)</label><input class="form-control" id="driverTemp<?= e($row['id']) ?>" type="number" step="0.1" min="-40" max="30" name="temperature_c" value="<?= e($row['temperature_c']) ?>" required></div>
                    <div class="col-6"><label class="form-label" for="driverLat<?= e($row['id']) ?>">GPS latitude</label><input class="form-control" id="driverLat<?= e($row['id']) ?>" type="number" step="0.0000001" name="gps_latitude" value="<?= e($row['gps_latitude']) ?>"></div>
                    <div class="col-6"><label class="form-label" for="driverLng<?= e($row['id']) ?>">GPS longitude</label><input class="form-control" id="driverLng<?= e($row['id']) ?>" type="number" step="0.0000001" name="gps_longitude" value="<?= e($row['gps_longitude']) ?>"></div>
                    <div class="col-12"><label class="form-label" for="driverPod<?= e($row['id']) ?>">Proof of delivery reference</label><input class="form-control" id="driverPod<?= e($row['id']) ?>" name="pod_reference" value="<?= e($row['pod_reference']) ?>" maxlength="100"><div class="form-text">Required when marking the route delivered.</div></div>
                    <div class="col-12"><label class="form-label" for="driverNotes<?= e($row['id']) ?>">Delivery notes / return reason</label><textarea class="form-control" id="driverNotes<?= e($row['id']) ?>" name="delivery_notes" rows="3" maxlength="1500"><?= e($row['delivery_notes']) ?></textarea></div>
                </div></div>
                <div class="modal-footer"><button class="btn btn-light border" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit">Save delivery update</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
