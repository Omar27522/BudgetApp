<?php
// index.php - Main Controller & Router (Modular Architecture)
require_once __DIR__ . '/src/Auth/AuthGuard.php';
AuthGuard::enforce();

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/src/Helpers/Format.php';
require_once __DIR__ . '/src/Models/Month.php';
require_once __DIR__ . '/src/Models/Bill.php';
require_once __DIR__ . '/src/Models/Income.php';
require_once __DIR__ . '/src/Models/Category.php';
require_once __DIR__ . '/src/Models/Purchase.php';
require_once __DIR__ . '/src/Models/Gas.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $monthId = (int)($_POST['month_id'] ?? 0);
    $tab = $_POST['tab'] ?? 'bills';
    $msg = '';

    switch ($action) {
        case 'create_month':
            $targetId = Month::create(
                (int)($_POST['year'] ?? date('Y')),
                (int)($_POST['month'] ?? date('n')),
                !empty($_POST['copy_from_id']) ? (int)$_POST['copy_from_id'] : null,
                !empty($_POST['copy_income']),
                !empty($_POST['copy_categories'])
            );
            header("Location: index.php?month_id={$targetId}&tab={$tab}&msg=" . urlencode('Month ready'));
            exit;

        case 'add_bill':
            Bill::add($monthId, $_POST['due_date_label'] ?? '1st', $_POST['name'] ?? '', $_POST['category'] ?? 'Bill', (float)($_POST['estimated_amount'] ?? 0.0), $_POST['notes'] ?? '');
            $msg = 'Bill added';
            break;

        case 'update_bill':
            Bill::update((int)($_POST['bill_id'] ?? 0), $_POST['due_date_label'] ?? '1st', $_POST['name'] ?? '', $_POST['category'] ?? 'Bill', (float)($_POST['estimated_amount'] ?? 0.0), (float)($_POST['actual_amount'] ?? 0.0), !empty($_POST['is_paid']), !empty($_POST['paid_date']) ? $_POST['paid_date'] : null, $_POST['notes'] ?? '');
            $msg = 'Bill updated';
            break;

        case 'toggle_bill':
            Bill::togglePaid((int)($_POST['bill_id'] ?? 0));
            $msg = 'Bill status updated';
            break;

        case 'update_paid_date':
            Bill::updatePaidDate((int)($_POST['bill_id'] ?? 0), !empty($_POST['paid_date']) ? trim($_POST['paid_date']) : null);
            $msg = 'Date paid saved';
            break;

        case 'add_bill_payment':
            $bId = (int)($_POST['bill_id'] ?? 0);
            Bill::addPayment($bId, (float)($_POST['amount'] ?? 0.0), $_POST['payment_date'] ?? date('Y-m-d'), $_POST['notes'] ?? '');
            header("Location: index.php?month_id={$monthId}&tab={$tab}&edit_bill={$bId}&msg=" . urlencode('Payment recorded') . "#bill-{$bId}");
            exit;

        case 'update_bill_payment':
            $bId = (int)($_POST['bill_id'] ?? 0);
            $pId = (int)($_POST['payment_id'] ?? 0);
            Bill::updatePayment($pId, $bId, (float)($_POST['amount'] ?? 0.0), $_POST['payment_date'] ?? date('Y-m-d'), $_POST['notes'] ?? '');
            header("Location: index.php?month_id={$monthId}&tab={$tab}&edit_bill={$bId}&msg=" . urlencode('Payment updated') . "#bill-{$bId}");
            exit;

        case 'delete_bill_payment':
            $bId = (int)($_POST['bill_id'] ?? 0);
            Bill::deletePayment((int)($_POST['payment_id'] ?? 0), $bId);
            header("Location: index.php?month_id={$monthId}&tab={$tab}&edit_bill={$bId}&msg=" . urlencode('Payment removed') . "#bill-{$bId}");
            exit;

        case 'delete_bill':
            Bill::delete((int)($_POST['bill_id'] ?? 0));
            $msg = 'Bill deleted';
            break;

        case 'copy_bills':
            if (!empty($_POST['source_month_id'])) {
                Bill::copyFromMonth($monthId, (int)$_POST['source_month_id']);
                $msg = 'Bills copied';
            }
            break;

        case 'add_income':
            $isRec = isset($_POST['is_recurring']) ? (bool)$_POST['is_recurring'] : true;
            Income::add($monthId, $_POST['source_name'] ?? '', (float)($_POST['estimated_amount'] ?? 0.0), (float)($_POST['actual_amount'] ?? 0.0), $isRec, $_POST['received_date'] ?? null, $_POST['notes'] ?? '');
            $msg = 'Income stream added';
            break;

        case 'update_income':
            $isRec = isset($_POST['is_recurring']) ? (bool)$_POST['is_recurring'] : true;
            $isRecv = isset($_POST['is_received']) ? (bool)$_POST['is_received'] : null;
            Income::update((int)($_POST['income_id'] ?? 0), $_POST['source_name'] ?? '', (float)($_POST['estimated_amount'] ?? 0.0), (float)($_POST['actual_amount'] ?? 0.0), $isRec, $isRecv, $_POST['received_date'] ?? null, $_POST['notes'] ?? '');
            $msg = 'Income updated';
            break;

        case 'toggle_income':
            Income::toggleReceived((int)($_POST['income_id'] ?? 0));
            $msg = 'Income status updated';
            break;

        case 'toggle_income_recurring':
            Income::toggleRecurring((int)($_POST['income_id'] ?? 0));
            $msg = 'Income recurrence updated';
            break;

        case 'update_income_date':
            Income::updateReceivedDate((int)($_POST['income_id'] ?? 0), !empty($_POST['received_date']) ? trim($_POST['received_date']) : null);
            $msg = 'Date received saved';
            break;

        case 'delete_income':
            Income::delete((int)($_POST['income_id'] ?? 0));
            $msg = 'Income deleted';
            break;

        case 'add_category':
            Category::add($monthId, $_POST['name'] ?? '', (float)($_POST['estimated_amount'] ?? 0.0));
            $msg = 'Category added';
            break;

        case 'update_category':
            Category::update((int)($_POST['category_id'] ?? 0), $monthId, $_POST['name'] ?? '', (float)($_POST['estimated_amount'] ?? 0.0));
            $msg = 'Category updated';
            break;

        case 'delete_category':
            Category::delete((int)($_POST['category_id'] ?? 0), $monthId);
            $msg = 'Category deleted';
            break;

        case 'seed_standard_categories':
            Category::seedStandard($monthId);
            $msg = 'Standard categories created';
            break;

        case 'add_purchase':
            Purchase::add($monthId, $_POST['purchase_date'] ?? date('Y-m-d'), $_POST['item'] ?? '', $_POST['place'] ?? '', (float)($_POST['amount'] ?? 0.0), !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null, $_POST['notes'] ?? '');
            $msg = 'Purchase logged';
            break;

        case 'update_purchase':
            Purchase::update((int)($_POST['purchase_id'] ?? 0), $monthId, $_POST['purchase_date'] ?? date('Y-m-d'), $_POST['item'] ?? '', $_POST['place'] ?? '', (float)($_POST['amount'] ?? 0.0), !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null, $_POST['notes'] ?? '');
            $msg = 'Purchase updated';
            break;

        case 'delete_purchase':
            Purchase::delete((int)($_POST['purchase_id'] ?? 0), $monthId);
            $msg = 'Purchase deleted';
            break;

        case 'add_gas_fillup':
            Gas::add($monthId, $_POST['fill_date'] ?? date('Y-m-d'), $_POST['station'] ?? '', $_POST['fuel_type'] ?? 'Regular', (float)($_POST['amount'] ?? 0.0), (float)($_POST['gallons'] ?? 0.0), !empty($_POST['odometer']) ? (float)$_POST['odometer'] : null, !empty($_POST['miles_driven']) ? (float)$_POST['miles_driven'] : null, $_POST['notes'] ?? '', !empty($_POST['sync_to_purchases']));
            $msg = 'Gas fill-up recorded';
            break;

        case 'update_gas_fillup':
            Gas::update((int)($_POST['gas_id'] ?? 0), $monthId, $_POST['fill_date'] ?? date('Y-m-d'), $_POST['station'] ?? '', $_POST['fuel_type'] ?? 'Regular', (float)($_POST['amount'] ?? 0.0), (float)($_POST['gallons'] ?? 0.0), !empty($_POST['odometer']) ? (float)$_POST['odometer'] : null, !empty($_POST['miles_driven']) ? (float)$_POST['miles_driven'] : null, $_POST['notes'] ?? '');
            $msg = 'Gas fill-up updated';
            break;

        case 'delete_gas_fillup':
            Gas::delete((int)($_POST['gas_id'] ?? 0), $monthId);
            $msg = 'Gas fill-up deleted';
            break;
    }

    header("Location: index.php?month_id={$monthId}&tab={$tab}&msg=" . urlencode($msg));
    exit;
}

// 1. Month Resolution & Year Grouping
$allMonths = Month::getOrCreateCurrent();

// Group months by year
$monthsByYear = [];
foreach ($allMonths as $m) {
    $y = (int)$m['year'];
    $monthsByYear[$y][] = $m;
}
$availableYears = array_keys($monthsByYear);
rsort($availableYears);

$selectedMonthId = isset($_GET['month_id']) ? (int)$_GET['month_id'] : 0;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;

if ($selectedMonthId > 0) {
    $currentMonth = Month::getById($selectedMonthId) ?? $allMonths[0];
} elseif ($selectedYear > 0 && isset($monthsByYear[$selectedYear])) {
    $currentMonth = $monthsByYear[$selectedYear][0];
} else {
    // Default to the current real-world month if it exists
    $realY = (int)date('Y');
    $realM = (int)date('n');
    $currentMonth = null;
    foreach ($allMonths as $m) {
        if ((int)$m['year'] === $realY && (int)$m['month'] === $realM) {
            $currentMonth = $m;
            break;
        }
    }
    if (!$currentMonth) {
        $currentMonth = $allMonths[0];
    }
}

$selectedMonthId = (int)$currentMonth['id'];
$activeYear = (int)$currentMonth['year'];
$monthsForActiveYear = $monthsByYear[$activeYear] ?? [];

// Calculate Prev / Next chronological months
$currentIdx = -1;
foreach ($allMonths as $idx => $m) {
    if ((int)$m['id'] === $selectedMonthId) {
        $currentIdx = $idx;
        break;
    }
}
// Note: allMonths is sorted year DESC, month DESC
// $currentIdx - 1 is chronologically later (Next)
// $currentIdx + 1 is chronologically earlier (Prev)
$nextChronMonth = ($currentIdx > 0) ? $allMonths[$currentIdx - 1] : null;
$prevChronMonth = ($currentIdx >= 0 && $currentIdx < count($allMonths) - 1) ? $allMonths[$currentIdx + 1] : null;

$activeTab = $_GET['tab'] ?? 'bills';
$statusMessage = $_GET['msg'] ?? '';
$showNewMonthForm = isset($_GET['view']) && $_GET['view'] === 'new_month';

// 2. Active Editing IDs
$editBillId = isset($_GET['edit_bill']) ? (int)$_GET['edit_bill'] : null;
$editIncomeId = isset($_GET['edit_income']) ? (int)$_GET['edit_income'] : null;
$editCatId = isset($_GET['edit_cat']) ? (int)$_GET['edit_cat'] : null;
$editPurchaseId = isset($_GET['edit_purchase']) ? (int)$_GET['edit_purchase'] : null;

// 3. Load Data & Summaries via Models
$bills = Bill::getForMonth($selectedMonthId);
$billsSummary = Bill::getSummary($bills);
$allBillPayments = Bill::getAllPaymentsForMonth($selectedMonthId);

$incomes = Income::getForMonth($selectedMonthId);
$incomeSummary = Income::getSummary($incomes);

$categories = Category::getForMonth($selectedMonthId);
$catSummary = Category::getSummary($categories);

$purchases = Purchase::getForMonth($selectedMonthId);
$totalPurchases = Purchase::getTotalSpent($purchases);

// 4. Net Cashflow Calculations
$netEstimated = $incomeSummary['total_estimated'] - $billsSummary['total_estimated'] - $catSummary['total_estimated'];
$netActual = $incomeSummary['total_actual'] - $billsSummary['total_paid'] - $totalPurchases;

// 5. Render Views
require __DIR__ . '/views/layout/header.php';
require __DIR__ . '/views/layout/kpi_banner.php';

if ($activeTab === 'purchases') {
    require __DIR__ . '/views/tabs/purchases_budget.php';
} elseif ($activeTab === 'gas') {
    $fillups = Gas::getForMonth($selectedMonthId);
    $gasSummary = Gas::getSummary($fillups);
    $editGasId = isset($_GET['edit_gas']) ? (int)$_GET['edit_gas'] : null;
    require __DIR__ . '/views/tabs/gas_tracker.php';
} else {
    require __DIR__ . '/views/tabs/bills_income.php';
}

require __DIR__ . '/views/layout/footer.php';