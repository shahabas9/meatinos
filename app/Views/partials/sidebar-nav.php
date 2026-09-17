<?php
use MeatinOS\Core\Auth;

$currentRoute = $currentRoute ?? (string) ($_GET['route'] ?? 'dashboard');
$currentModule = $currentModule ?? (string) ($_GET['name'] ?? '');
$user = $user ?? (Auth::check() ? Auth::user() : null);

$groups = [
    'Command Center' => [
        ['dashboard',null,'Executive Dashboard','bi-grid-1x2-fill','dashboard'],
        ['ai',null,'Meatin AI','bi-stars','ai'],
        ['reports',null,'Report Center','bi-bar-chart-line','reports'],
        ['commercial',null,'Commercial Analytics','bi-graph-up-arrow','reports'],
        ['reports.scheduled',null,'Generated Reports','bi-file-earmark-spreadsheet','reports'],
        ['intelligence',null,'Operational Intelligence','bi-stars','reports'],
        ['traceability',null,'Batch 360°','bi-upc-scan','reports'],
    ],
    'Procurement' => [
        ['module','purchase_requisitions','Purchase Requisitions','bi-clipboard-plus','purchase'],
        ['module','suppliers','Vendors','bi-buildings','purchase'],
        ['module','farms','Farms','bi-geo-alt','purchase'],
        ['module','purchase_orders','Purchase Orders','bi-bag-check','purchase'],
        ['module','goods_receipts','Goods Receipts (GRN)','bi-box-arrow-in-down','purchase'],
    ],
    'Production' => [
        ['module','bird_receipts','Live Bird Receiving','bi-clipboard2-pulse','production'],
        ['module','production_batches','Production Batches','bi-diagram-3','production'],
        ['module','production_stage_measurements','Stage Measurements','bi-speedometer','production'],
        ['production.wastage',null,'Wastage Tracking','bi-recycle','production'],
        ['module','production_damaged_birds','Damaged Bird Wastage','bi-exclamation-octagon','production'],
        ['module','production_batch_costs','Batch Costing','bi-calculator-fill','production'],
        ['module','production_requirements','Production Requirements','bi-exclamation-diamond','production'],
        ['module','production_lines','Production Lines','bi-bezier2','production'],
        ['module','yield_records','Yield Management','bi-percent','production'],
        ['module','carcass_allotments','Carcass Allocation','bi-distribute-vertical','production'],
        ['module','deboning_records','Deboning & Cutting','bi-scissors','production'],
    ],
    'Quality & Compliance' => [
        ['module','quality_checks','Quality Control','bi-shield-check','quality'],
        ['module','quality_holds','Quality Holds','bi-pause-circle','quality'],
        ['module','customer_complaints','Complaints','bi-chat-left-text','quality'],
        ['module','capa_actions','CAPA Register','bi-clipboard2-check','quality'],
        ['module','compliance_records','HACCP / ISO / Halal','bi-patch-check','quality'],
    ],
    'Inventory & Cold Storage' => [
        ['module','products','Products & Materials','bi-box-seam','inventory'],
        ['module','item_categories','Item Categories','bi-tags','inventory'],
        ['module','warehouses','Warehouses','bi-house-door','inventory'],
        ['module','inventory_lots','Lots & Cold Storage','bi-snow2','inventory'],
        ['module','stock_transfers','Stock Transfers','bi-arrow-left-right','inventory'],
        ['module','packaging_specs','Packaging BOM','bi-box2-heart','inventory'],
        ['module','tray_assets','Tray Tracking','bi-inboxes','inventory'],
    ],
    'Sales & CRM' => [
        ['sales.tracking',null,'Salesman Tracking','bi-geo-alt-fill','sales'],
        ['module','customers','Customers & CRM','bi-people','crm'],
        ['module','customer_prices','Customer Pricing','bi-tags','sales'],
        ['module','sales_quotations','Quotations','bi-file-earmark-text','sales'],
        ['module','sales_quotation_items','Quotation Items','bi-list-columns-reverse','sales'],
        ['module','rate_cards','Rate Cards','bi-card-list','sales'],
        ['rate-card.bulk',null,'Bulk Rate Entry','bi-table','sales'],
        ['module','rate_card_items','Rate Card Items','bi-currency-exchange','sales'],
        ['module','market_prices','Market Benchmarks','bi-activity','sales'],
        ['module','customer_bank_accounts','Customer Bank Links','bi-bank','finance'],
        ['module','sales_orders','Sales Orders','bi-cart3','sales'],
        ['module','sales_returns','Sales Returns','bi-arrow-return-left','sales'],
        ['module','sales_targets','Sales Targets','bi-bullseye','sales'],
        ['module','sales_commission_accruals','Commission Accruals','bi-percent','sales'],
    ],
    'Dispatch & Logistics' => [
        ['module','picking_lists','Picking Lists','bi-list-check','logistics'],
        ['module','packaging_records','Packaging Records','bi-box2','logistics'],
        ['module','dispatches','Dispatch & Delivery','bi-truck','logistics'],
        ['module','route_masters','Route Masters','bi-signpost-split','logistics'],
        ['module','vehicles','Fleet','bi-truck-front','logistics'],
        ['module','fuel_logs','Fuel Logs','bi-fuel-pump','logistics'],
        ['module','eway_bills','E-Way Bills','bi-file-earmark-check','logistics'],
        ['module','vehicle_gate_logs','Vehicle Entry / Exit','bi-sign-stop-lights','logistics'],
        ['documents',null,'Logistics Evidence','bi-camera','logistics'],
    ],
    'Finance & Accounts' => [
        ['module','invoices','Invoices & Receivables','bi-wallet2','finance'],
        ['module','payments','Customer Receipts','bi-cash-coin','finance'],
        ['module','supplier_invoices','Supplier Invoices','bi-receipt','finance'],
        ['module','supplier_payments','Supplier Payments','bi-bank','finance'],
        ['module','company_bank_accounts','Company Bank Accounts','bi-bank2','finance'],
        ['module','cost_centers','Cost Centers','bi-diagram-3-fill','finance'],
        ['module','operating_expenses','Operating Expenses','bi-receipt-cutoff','finance'],
        ['module','chart_of_accounts','Chart of Accounts','bi-journal-bookmark-fill','finance'],
        ['module','journal_entries','General Ledger Journals','bi-journal-check','finance'],
        ['module','asset_depreciation_entries','Asset Depreciation','bi-graph-down-arrow','finance'],
        ['partners',null,'Partners','bi-people-fill','finance'],
        ['module','government_loans','Government Loans','bi-cash-coin','finance'],
        ['documents',null,'Financial Documents','bi-paperclip','finance'],
    ],
    'HR & Payroll' => [
        ['workforce.tasks',null,'Daily Employee Tasks','bi-list-check','dashboard'],
        ['module','employees','Employees','bi-person-badge','hr'],
        ['module','attendance','Attendance','bi-calendar2-check','hr'],
        ['module','leave_requests','Leave Management','bi-calendar2-heart','hr'],
        ['module','employee_bank_accounts','Employee Bank Accounts','bi-credit-card-2-front','hr'],
        ['module','employee_uniform_allocations','Uniform Register','bi-person-bounding-box','hr'],
        ['module','employee_memos','Memos','bi-envelope-paper','hr'],
        ['module','employee_appointments','Employment Letters','bi-file-earmark-person','hr'],
        ['module','employee_performance_reviews','Performance & Ranking','bi-trophy','hr'],
        ['module','salary_advances','Salary Advances','bi-cash-stack','hr'],
        ['module','employee_resignations','Resignations','bi-person-dash','hr'],
        ['payroll',null,'Payroll','bi-calculator','hr'],
        ['documents',null,'Employee Documents','bi-folder2-open','hr'],
    ],
    'Plant Operations' => [
        ['module','assets','Plant Assets','bi-gear-wide-connected','maintenance'],
        ['module','maintenance_tickets','Maintenance','bi-tools','maintenance'],
        ['module','hygiene_checks','Hygiene & Sanitation','bi-stars','hygiene'],
        ['module','sensors','IoT Monitoring','bi-broadcast-pin','iot'],
    ],
    'Administration' => [
        ['module','companies','Companies','bi-building','settings'],
        ['module','plants','Plants','bi-houses','settings'],
        ['module','departments','Departments','bi-diagram-2','settings'],
        ['module','legal_cases','Legal Case Register','bi-bank','settings'],
        ['module','roc_filings','ROC Filings','bi-building-check','settings'],
        ['module','company_certificates','Company Certifications','bi-patch-check-fill','settings'],
        ['module','office_file_register','Office File Register','bi-folder-fill','settings'],
        ['module','calendar_events','Corporate Calendar','bi-calendar-event','settings'],
        ['module','approval_workflows','Approval Workflows','bi-signpost-split','settings'],
        ['module','approval_workflow_steps','Approval Steps','bi-list-ol','settings'],
        ['module','approval_requests','Approval Queue','bi-check2-square','settings'],
        ['module','report_schedules','Automatic Reports','bi-clock-history','reports'],
        ['documents',null,'Document Management','bi-folder2-open','settings'],
        ['audit',null,'Audit Trail','bi-clock-history','audit'],
        ['users',null,'Users & Roles','bi-person-gear','users'],
        ['settings',null,'System Settings','bi-sliders','settings'],
        ['system.update',null,'System Update','bi-cloud-arrow-up','settings'],
    ],
];
?>
<nav class="sidebar-nav" aria-label="Main navigation">
    <?php foreach ($groups as $groupLabel => $links): ?>
        <?php
        $visible = array_values(array_filter($links, static function (array $link) use ($user): bool {
            [$route, $moduleName, , , $permission] = $link;
            if (!Auth::can($permission . '.view')) return false;
            if ($route === 'rate-card.bulk' && !Auth::can('sales.manage')) return false;
            if ($route === 'system.update' && !Auth::can('settings.manage')) return false;
            if (in_array(($user['role_slug'] ?? ''), ['customer','driver'], true) && in_array($moduleName, ['vehicles','route_masters','fuel_logs'], true)) return false;
            if (($user['role_slug'] ?? '') === 'customer' && in_array($moduleName, ['sales_targets','customer_prices','sales_returns'], true)) return false;
            return true;
        }));
        if (!$visible) continue;
        ?>
        <div class="nav-group-label"><?= e($groupLabel) ?></div>
        <?php foreach ($visible as [$route,$moduleName,$label,$icon,$permission]): ?>
            <?php $active = $currentRoute === $route && ($route !== 'module' || $currentModule === $moduleName); ?>
            <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= e(url($route, $moduleName ? ['name'=>$moduleName] : [])) ?>">
                <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ($active): ?><i class="bi bi-chevron-right ms-auto nav-arrow"></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
</nav>
