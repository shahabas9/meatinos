<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\NumberingService;

final class DocumentController
{
    private const ALLOWED = [
        'application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','text/csv'=>'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
    ];

    public function index(): void
    {
        Auth::requireLogin();
        $types=$this->allowedEntityTypes(false);
        if (!$types) throw new \RuntimeException('Access denied.',403);
        $pdo=Database::connection();
        $placeholders=implode(',',array_fill(0,count($types),'?'));
        $statement=$pdo->prepare("SELECT d.*,u.name uploaded_by_name FROM documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.entity_type IN ({$placeholders}) ORDER BY d.created_at DESC LIMIT 250");
        $statement->execute(array_keys($types));
        $rows=$statement->fetchAll();
        $manageable=$this->allowedEntityTypes(true);
        View::render('documents/index',['title'=>'Document Management','rows'=>$rows,'canManage'=>$manageable!==[],'entityOptions'=>$manageable,'prefillType'=>preg_replace('/[^a-z_]/','',(string)($_GET['entity_type'] ?? '')),'prefillId'=>max(0,(int)($_GET['entity_id'] ?? 0))]);
    }

    public function upload(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $title=mb_substr(trim((string)($_POST['title'] ?? '')),0,200);
        $category=mb_substr(trim((string)($_POST['category'] ?? '')),0,100);
        $entityType=preg_replace('/[^a-z_]/','',(string)($_POST['entity_type'] ?? 'company')) ?: 'company';
        $entityId=max(1,(int)($_POST['entity_id'] ?? 1));
        $expiry=trim((string)($_POST['expiry_date'] ?? '')) ?: null;
        $tags=mb_substr(trim((string)($_POST['tags'] ?? '')),0,500) ?: null;
        $access=in_array($_POST['access_level'] ?? '',['private','department','plant','company','customer'],true) ? $_POST['access_level'] : 'private';
        $allowedTypes=$this->allowedEntityTypes(true);
        if (!isset($allowedTypes[$entityType])) throw new \RuntimeException('You do not have permission to attach files to that record type.',403);
        $file=$_FILES['document'] ?? null;
        if ($title==='' || $category==='' || !$file || (int)$file['error']!==UPLOAD_ERR_OK) {
            flash('danger','Title, category, and a valid document are required.'); redirect('documents');
        }
        if ((int)$file['size']<1 || (int)$file['size']>10*1024*1024) {
            flash('danger','Documents must be between 1 byte and 10 MB.'); redirect('documents');
        }
        $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file((string)$file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) { flash('danger','Only PDF, JPEG, PNG, WebP, DOCX, XLSX, and CSV files are allowed.'); redirect('documents'); }
        $directory=BASE_PATH.'/storage/uploads/documents/'.date('Y/m');
        if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) throw new \RuntimeException('Unable to create the secure document directory.');
        $stored=bin2hex(random_bytes(18)).'.'.self::ALLOWED[$mime];
        $absolute=$directory.'/'.$stored;
        if (!move_uploaded_file((string)$file['tmp_name'],$absolute)) throw new \RuntimeException('The document could not be stored.');
        @chmod($absolute,0640);
        $relative=str_replace('\\','/',substr($absolute,strlen(BASE_PATH)+1));
        $pdo=Database::connection();
        try {
            $number=NumberingService::next('document',null,'DOC');
            $statement=$pdo->prepare('INSERT INTO documents (document_number,entity_type,entity_id,category,title,file_path,mime_type,file_size,expiry_date,tags,access_level,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $statement->execute([$number,$entityType,$entityId,$category,$title,$relative,$mime,(int)$file['size'],$expiry,$tags,$access,Auth::user()['id']]);
            $id=(int)$pdo->lastInsertId();
            AuditService::log('uploaded','documents',$id,'Secure document uploaded.',null,['document_number'=>$number,'entity_type'=>$entityType,'entity_id'=>$entityId,'mime_type'=>$mime,'file_size'=>(int)$file['size']]);
            flash('success','Document uploaded securely.');
        } catch (\Throwable $exception) {
            @unlink($absolute); throw $exception;
        }
        redirect('documents');
    }

    public function download(): void
    {
        Auth::requireLogin();
        $id=(int)($_GET['id'] ?? 0);
        $statement=Database::connection()->prepare('SELECT * FROM documents WHERE id=?');$statement->execute([$id]);$document=$statement->fetch();
        if (!$document) throw new \RuntimeException('Document not found.',404);
        $types=$this->allowedEntityTypes(false);
        if (!isset($types[$document['entity_type']])) throw new \RuntimeException('Access denied.',403);
        $user=Auth::user();
        if (($user['role_slug'] ?? '')==='customer' && ($document['entity_type']!=='customer' || (int)$document['entity_id']!==(int)($user['customer_id'] ?? 0))) throw new \RuntimeException('Access denied.',403);
        $root=realpath(BASE_PATH.'/storage/uploads/documents');
        $path=realpath(BASE_PATH.'/'.$document['file_path']);
        if (!$root || !$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path)) throw new \RuntimeException('Document file is unavailable.',404);
        AuditService::log('downloaded','documents',$id,'Secure document downloaded.');
        header('Content-Type: '.($document['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',($document['title'] ?: $document['document_number'])).'.'.pathinfo($path,PATHINFO_EXTENSION).'"');
        header('Cache-Control: private, no-store');
        readfile($path); exit;
    }

    /** @return array<string,string> */
    private function allowedEntityTypes(bool $manage): array
    {
        $suffix=$manage ? '.manage' : '.view';
        $map=[
            'company'=>['settings','Company'],'office_file'=>['settings','Office file'],'legal_case'=>['settings','Legal case'],'roc_filing'=>['settings','ROC filing'],'company_certificate'=>['settings','Company certification'],'calendar_event'=>['settings','Corporate / production event'],
            'employee'=>['hr','Employee'],'appointment'=>['hr','Employment letter'],'memo'=>['hr','Employee memo'],'performance_review'=>['hr','Performance review'],'resignation'=>['hr','Resignation'],'salary_advance'=>['hr','Salary advance'],'uniform'=>['hr','Uniform allocation'],
            'supplier'=>['purchase','Supplier'],'purchase_order'=>['purchase','Purchase order'],'goods_receipt'=>['purchase','Goods receipt'],
            'customer'=>['crm','Customer'],'sales_order'=>['sales','Sales order'],'invoice'=>['finance','Invoice'],'payment'=>['finance','Payment'],'expense'=>['finance','Operating expense'],'bank_account'=>['finance','Company bank account'],'shareholder'=>['finance','Partner'],'partner_transaction'=>['finance','Partner transaction'],'partner_dividend'=>['finance','Partner dividend'],'government_loan'=>['finance','Government / institutional loan'],
            'production_batch'=>['production','Production batch'],'stage_measurement'=>['production','Production stage reading'],'quality_check'=>['quality','Quality check'],'company_compliance'=>['quality','Quality / compliance evidence'],
            'inventory_lot'=>['inventory','Inventory lot'],'asset'=>['maintenance','Fixed asset'],'vehicle'=>['logistics','Vehicle'],'dispatch'=>['logistics','Dispatch / proof of delivery'],'vehicle_gate_log'=>['logistics','Vehicle gate entry'],'eway_bill'=>['logistics','E-Way bill'],
        ];
        $allowed=[];
        foreach ($map as $type=>[$permission,$label]) if (Auth::can($permission.$suffix) || Auth::can('settings'.$suffix)) $allowed[$type]=$label;
        $user=Auth::user();
        if (!$manage && ($user['role_slug'] ?? '')==='customer') return ['customer'=>'Customer document'];
        return $allowed;
    }
}
