<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use PDO;

final class ApprovalService
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    public function request(string $module, int $recordId, float $amount = 0, ?int $plantId = null, ?int $departmentId = null): bool
    {
        $existing = $this->one("SELECT * FROM approval_requests WHERE module=? AND record_id=? LIMIT 1 FOR UPDATE", [$module,$recordId]);
        if ($existing) {
            if ($existing['status'] === 'pending') return true;
            throw new \RuntimeException('This transaction already has a completed approval decision.', 422);
        }
        $workflow = $this->one("SELECT aw.* FROM approval_workflows aw WHERE aw.module=? AND aw.status='active' AND (aw.plant_id IS NULL OR aw.plant_id=?) AND (aw.department_id IS NULL OR aw.department_id=?) AND (aw.min_amount IS NULL OR aw.min_amount<=?) AND (aw.max_amount IS NULL OR aw.max_amount>=?) AND EXISTS (SELECT 1 FROM approval_workflow_steps s WHERE s.workflow_id=aw.id) ORDER BY (aw.plant_id IS NOT NULL)+(aw.department_id IS NOT NULL) DESC,COALESCE(aw.min_amount,0) DESC,aw.id LIMIT 1", [$module,$plantId,$departmentId,$amount,$amount]);
        if (!$workflow) return false;
        $firstStep = (int) $this->value('SELECT MIN(step_number) FROM approval_workflow_steps WHERE workflow_id=?', [$workflow['id']]);
        $this->pdo->prepare("INSERT INTO approval_requests (workflow_id,module,record_id,amount,plant_id,department_id,current_step,status,requested_by,requested_at) VALUES (?,?,?,?,?,?,?,'pending',?,NOW())")
            ->execute([$workflow['id'],$module,$recordId,$amount,$plantId,$departmentId,$firstStep,Auth::user()['id'] ?? null]);
        $requestId=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO approval_history (approval_request_id,action,acted_by,comment,acted_at) VALUES (?,'submitted',?,'Submitted by business workflow',NOW())")
            ->execute([$requestId,Auth::user()['id'] ?? null]);
        return true;
    }

    /** Returns true when every configured approval step is complete. */
    public function approve(string $module, int $recordId, ?string $comment = null): bool
    {
        $request = $this->one("SELECT * FROM approval_requests WHERE module=? AND record_id=? AND status='pending' LIMIT 1 FOR UPDATE", [$module,$recordId]);
        if (!$request) return true;
        $step = $this->one('SELECT aws.*,r.slug role_slug FROM approval_workflow_steps aws JOIN roles r ON r.id=aws.role_id WHERE aws.workflow_id=? AND aws.step_number=?', [$request['workflow_id'],$request['current_step']]);
        if (!$step) throw new \RuntimeException('The approval workflow step is missing or inactive.',422);
        if (!Auth::hasRole('super_admin') && !Auth::hasRole((string)$step['role_slug'])) throw new \RuntimeException('This approval step requires the '.$step['label'].' role.',403);
        $this->pdo->prepare("INSERT INTO approval_history (approval_request_id,workflow_step_id,action,acted_by,comment,acted_at) VALUES (?,?,'approved',?,?,NOW())")
            ->execute([$request['id'],$step['id'],Auth::user()['id'] ?? null,mb_substr(trim((string)$comment),0,1000) ?: null]);
        $next=(int)$this->value('SELECT MIN(step_number) FROM approval_workflow_steps WHERE workflow_id=? AND step_number>?',[$request['workflow_id'],$request['current_step']]);
        if ($next>0) {
            $this->pdo->prepare('UPDATE approval_requests SET current_step=? WHERE id=?')->execute([$next,$request['id']]);
            return false;
        }
        $this->pdo->prepare("UPDATE approval_requests SET status='approved',completed_at=NOW() WHERE id=?")->execute([$request['id']]);
        return true;
    }

    public function reject(string $module, int $recordId, string $comment): void
    {
        $request=$this->one("SELECT * FROM approval_requests WHERE module=? AND record_id=? AND status='pending' LIMIT 1 FOR UPDATE",[$module,$recordId]);
        if (!$request) return;
        $step=$this->one('SELECT aws.*,r.slug role_slug FROM approval_workflow_steps aws JOIN roles r ON r.id=aws.role_id WHERE aws.workflow_id=? AND aws.step_number=?',[$request['workflow_id'],$request['current_step']]);
        if ($step && !Auth::hasRole('super_admin') && !Auth::hasRole((string)$step['role_slug'])) throw new \RuntimeException('You are not the assigned approver for this step.',403);
        $this->pdo->prepare("UPDATE approval_requests SET status='rejected',completed_at=NOW() WHERE id=?")->execute([$request['id']]);
        $this->pdo->prepare("INSERT INTO approval_history (approval_request_id,workflow_step_id,action,acted_by,comment,acted_at) VALUES (?,?,'rejected',?,?,NOW())")
            ->execute([$request['id'],$step['id'] ?? null,Auth::user()['id'] ?? null,mb_substr(trim($comment),0,1000)]);
    }

    private function one(string $sql,array $params=[]): ?array
    {
        $statement=$this->pdo->prepare($sql);$statement->execute($params);return $statement->fetch() ?: null;
    }

    private function value(string $sql,array $params=[]): mixed
    {
        $statement=$this->pdo->prepare($sql);$statement->execute($params);return $statement->fetchColumn();
    }
}
