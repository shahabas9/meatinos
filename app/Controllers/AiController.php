<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AiContextService;
use MeatinOS\Services\AiInsightService;
use MeatinOS\Services\AiSettingsService;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\OpenAIService;
use PDO;

final class AiController
{
    public function index(): void
    {
        Auth::requirePermission('ai.view');
        $pdo = Database::connection();
        $contextService = new AiContextService();
        $assistants = $contextService->allowedAssistants();
        $assistant = preg_replace('/[^a-z_]/', '', (string) ($_GET['assistant'] ?? array_key_first($assistants) ?? ''));
        if (!isset($assistants[$assistant])) $assistant = (string) (array_key_first($assistants) ?? '');
        $conversationId = max(0, (int) ($_GET['conversation'] ?? 0));
        $messages = [];
        if ($conversationId > 0) {
            $statement = $pdo->prepare('SELECT m.* FROM ai_messages m JOIN ai_conversations c ON c.id=m.conversation_id WHERE m.conversation_id=? AND c.user_id=? ORDER BY m.id');
            $statement->execute([$conversationId,Auth::user()['id']]);
            $messages = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $conversations = $pdo->prepare('SELECT id,title,assistant_type,updated_at FROM ai_conversations WHERE user_id=? AND status=\'active\' ORDER BY updated_at DESC LIMIT 12');
        $conversations->execute([Auth::user()['id']]);
        View::render('ai/index', [
            'title'=>'Meatin AI','aiSettings'=>(new AiSettingsService())->get(),'assistants'=>$assistants,'assistant'=>$assistant,
            'insights'=>(new AiInsightService())->refresh(),'conversations'=>$conversations->fetchAll(PDO::FETCH_ASSOC),
            'conversationId'=>$conversationId,'messages'=>$messages,'canConfigure'=>Auth::can('ai.manage'),
        ]);
    }

    public function ask(): void
    {
        Auth::requirePermission('ai.view');
        Csrf::verify($_POST['_token'] ?? null);
        $question = mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 2000);
        $assistant = preg_replace('/[^a-z_]/', '', (string) ($_POST['assistant'] ?? 'executive'));
        if (mb_strlen($question) < 3) {
            flash('warning','Enter a question with at least three characters.');
            redirect('ai',['assistant'=>$assistant]);
        }
        $settingsService = new AiSettingsService();
        $settings = $settingsService->get();
        $key = $settingsService->apiKey();
        if (!$settings['enabled'] || $key === null) {
            flash('warning','Meatin AI is disabled. An administrator must add an OpenAI API key and enable it in System Settings.');
            redirect('ai',['assistant'=>$assistant]);
        }
        $contextService = new AiContextService();
        $snapshot = $contextService->snapshot($assistant);
        $pdo = Database::connection();
        $rate = $pdo->prepare("SELECT COUNT(*) FROM ai_messages WHERE user_id=? AND role='user' AND created_at>=NOW()-INTERVAL 5 MINUTE");
        $rate->execute([Auth::user()['id']]);
        if ((int) $rate->fetchColumn() >= 10) throw new \RuntimeException('AI request limit reached. Wait a few minutes and try again.',429);
        $conversationId = max(0,(int) ($_POST['conversation_id'] ?? 0));
        if ($conversationId > 0) {
            $owned = $pdo->prepare('SELECT COUNT(*) FROM ai_conversations WHERE id=? AND user_id=? AND status=\'active\'');
            $owned->execute([$conversationId,Auth::user()['id']]);
            if ((int) $owned->fetchColumn() !== 1) throw new \RuntimeException('Conversation not found.',404);
        } else {
            $title = mb_substr(preg_replace('/\s+/', ' ', $question), 0, 100);
            $pdo->prepare('INSERT INTO ai_conversations (user_id,title,assistant_type) VALUES (?,?,?)')->execute([Auth::user()['id'],$title,$assistant]);
            $conversationId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO ai_messages (conversation_id,user_id,role,content,status) VALUES (?,?,'user',?,'completed')")->execute([$conversationId,Auth::user()['id'],$question]);
        try {
            $input = "AUTHORIZED ERP SNAPSHOT (JSON):\n" . json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n\nUSER QUESTION:\n" . $question;
            $result = (new OpenAIService())->respond($key,(string) $settings['model'],$contextService->instructions($assistant),$input,(int) $settings['max_output_tokens']);
            $statement = $pdo->prepare("INSERT INTO ai_messages (conversation_id,user_id,role,content,model,provider_request_id,input_tokens,output_tokens,latency_ms,status) VALUES (?,NULL,'assistant',?,?,?,?,?,?,'completed')");
            $statement->execute([$conversationId,$result['text'],$settings['model'],$result['request_id'],$result['input_tokens'],$result['output_tokens'],$result['latency_ms']]);
            $pdo->prepare('UPDATE ai_conversations SET updated_at=NOW() WHERE id=?')->execute([$conversationId]);
            AuditService::log('ai_advice_generated','ai',$conversationId,'Role-authorized advisory response generated.',null,['assistant'=>$assistant,'model'=>$settings['model'],'input_tokens'=>$result['input_tokens'],'output_tokens'=>$result['output_tokens']]);
        } catch (\Throwable $exception) {
            $message = mb_substr($exception->getMessage(),0,240);
            $pdo->prepare("INSERT INTO ai_messages (conversation_id,user_id,role,content,model,status,error_code) VALUES (?,NULL,'assistant',?,?,'failed','provider_error')")->execute([$conversationId,'AI request could not be completed. ' . $message,$settings['model']]);
            AuditService::log('ai_request_failed','ai',$conversationId,'AI provider request failed.');
            flash('danger',$message);
        }
        redirect('ai',['assistant'=>$assistant,'conversation'=>$conversationId]);
    }

    public function feedback(): void
    {
        Auth::requirePermission('ai.view');
        Csrf::verify($_POST['_token'] ?? null);
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $rating = (string) ($_POST['rating'] ?? '');
        if (!in_array($rating,['helpful','unhelpful'],true)) throw new \InvalidArgumentException('Invalid feedback value.');
        $pdo = Database::connection();
        $owned = $pdo->prepare("SELECT COUNT(*) FROM ai_messages m JOIN ai_conversations c ON c.id=m.conversation_id WHERE m.id=? AND m.role='assistant' AND c.user_id=?");
        $owned->execute([$messageId,Auth::user()['id']]);
        if ((int) $owned->fetchColumn() !== 1) throw new \RuntimeException('AI message not found.',404);
        $pdo->prepare('INSERT INTO ai_feedback (message_id,user_id,rating) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),created_at=CURRENT_TIMESTAMP')->execute([$messageId,Auth::user()['id'],$rating]);
        flash('success','Feedback recorded.');
        redirect('ai',['conversation'=>(int) ($_POST['conversation_id'] ?? 0),'assistant'=>(string) ($_POST['assistant'] ?? 'executive')]);
    }

    public function insightStatus(): void
    {
        Auth::requirePermission('ai.view');
        Csrf::verify($_POST['_token'] ?? null);
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status,['acknowledged','dismissed'],true)) throw new \InvalidArgumentException('Invalid insight status.');
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT module FROM ai_insights WHERE id=?');
        $statement->execute([$id]);
        $module = (string) $statement->fetchColumn();
        if (!(new AiInsightService())->canManageModule($module)) throw new \RuntimeException('You do not have access to this insight.',403);
        $pdo->prepare('UPDATE ai_insights SET status=?,acknowledged_by=?,acknowledged_at=NOW() WHERE id=?')->execute([$status,Auth::user()['id'],$id]);
        AuditService::log('ai_insight_' . $status,'ai_insights',$id,'Operational insight ' . $status . '.');
        flash('success','Insight ' . $status . '.');
        redirect('ai');
    }
}
