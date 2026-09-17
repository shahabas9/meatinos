<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\WorkflowService;
use Throwable;

final class DriverController
{
    public function updateDispatch(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (($user['role_slug'] ?? '') !== 'driver' || empty($user['employee_id'])) {
            http_response_code(403);
            exit('Driver access required.');
        }
        Csrf::verify($_POST['_token'] ?? null);
        $id = (int) ($_POST['dispatch_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $temperature = filter_var($_POST['temperature_c'] ?? null, FILTER_VALIDATE_FLOAT);
        $latitude = ($_POST['gps_latitude'] ?? '') === '' ? null : filter_var($_POST['gps_latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = ($_POST['gps_longitude'] ?? '') === '' ? null : filter_var($_POST['gps_longitude'], FILTER_VALIDATE_FLOAT);
        $pod = trim((string) ($_POST['pod_reference'] ?? ''));
        $notes = trim((string) ($_POST['delivery_notes'] ?? ''));
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM dispatches WHERE id=? AND driver_id=?');
        $statement->execute([$id, $user['employee_id']]);
        $old = $statement->fetch();
        if (!$old) {
            throw new \RuntimeException('Assigned dispatch not found.', 404);
        }
        $transitions = [
            'scheduled' => ['scheduled','loading','delayed'],
            'loading' => ['loading','in_transit','delayed'],
            'in_transit' => ['in_transit','delivered','delayed'],
            'delayed' => ['delayed','in_transit','delivered'],
            'delivered' => ['delivered'],
            'cancelled' => ['cancelled'],
        ];
        if (!in_array($status, $transitions[$old['status']] ?? [], true)) {
            flash('danger', 'That delivery status transition is not allowed.');
            redirect('dashboard');
        }
        if ($temperature === false || $temperature < -40 || $temperature > 30) {
            flash('danger', 'Enter a valid reefer temperature between -40C and 30C.');
            redirect('dashboard');
        }
        if (($latitude !== null && ($latitude === false || $latitude < -90 || $latitude > 90)) || ($longitude !== null && ($longitude === false || $longitude < -180 || $longitude > 180))) {
            flash('danger', 'Enter a valid GPS latitude and longitude.');
            redirect('dashboard');
        }
        if ($status === 'delivered' && $pod === '') {
            flash('danger', 'Proof of delivery reference is required before marking a delivery complete.');
            redirect('dashboard');
        }
        $actualDeparture = $old['actual_departure'];
        $deliveredAt = $old['delivered_at'];
        if ($status === 'in_transit' && !$actualDeparture) $actualDeparture = date('Y-m-d H:i:s');
        if ($status === 'delivered' && !$deliveredAt) $deliveredAt = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE dispatches SET status=?,temperature_c=?,gps_latitude=?,gps_longitude=?,pod_reference=?,delivery_notes=?,actual_departure=?,delivered_at=? WHERE id=? AND driver_id=?')
                ->execute([$status, $temperature, $latitude, $longitude, $pod ?: null, $notes ?: null, $actualDeparture, $deliveredAt, $id, $user['employee_id']]);
            if ($status === 'delivered') {
                (new WorkflowService($pdo))->finalizeDispatch($id);
            }
            AuditService::log('driver_update', 'dispatches', $id, 'Driver updated assigned dispatch.', $old, ['status'=>$status,'temperature_c'=>$temperature,'gps_latitude'=>$latitude,'gps_longitude'=>$longitude,'pod_reference'=>$pod]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        flash('success', 'Delivery update saved securely.');
        redirect('dashboard');
    }
}
