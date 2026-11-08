<?php
// app/backup.php - On-Demand Full System & Data Backup Generator
require_once __DIR__ . '/src/Auth/AuthGuard.php';
AuthGuard::enforce();

require_once __DIR__ . '/database/db.php';

try {
    $db = Db::getConnection();
    $dbPath = Db::getDatabasePath();

    if (!file_exists($dbPath)) {
        throw new RuntimeException("Database file not found at: {$dbPath}");
    }

    // Helper to generate a CSV string in memory
    function arrayToCsv(array $headers, array $rows): string {
        $fp = fopen('php://memory', 'r+');
        // UTF-8 BOM for Excel compatibility
        fputs($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $formattedRow = [];
            foreach ($row as $cell) {
                if ($cell === null) {
                    $formattedRow[] = '';
                } elseif (is_bool($cell)) {
                    $formattedRow[] = $cell ? '1' : '0';
                } else {
                    $formattedRow[] = (string)$cell;
                }
            }
            fputcsv($fp, $formattedRow);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    // Prepare temp zip file
    $timestamp = date('Y-m-d_H-i');
    $zipFilename = "backup_{$timestamp}.zip";
    $tempZip = tempnam(sys_get_temp_dir(), 'budget_bkp_') . '.zip';

    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create temporary zip archive.");
    }

    // 1. Add Raw SQLite Database File
    $zip->addFile($dbPath, 'database/budget.sqlite');

    // 2. Fetch All Months
    $months = $db->query("SELECT * FROM months ORDER BY year DESC, month DESC")->fetchAll(PDO::FETCH_ASSOC);

    // Master Months Summary
    $monthsSummaryRows = [];
    foreach ($months as $m) {
        $mId = (int)$m['id'];
        $incEst = (float)$db->query("SELECT COALESCE(SUM(estimated_amount), 0) FROM income_items WHERE month_id = {$mId}")->fetchColumn();
        $incAct = (float)$db->query("SELECT COALESCE(SUM(actual_amount), 0) FROM income_items WHERE month_id = {$mId}")->fetchColumn();
        $billsEst = (float)$db->query("SELECT COALESCE(SUM(estimated_amount), 0) FROM monthly_bills WHERE month_id = {$mId}")->fetchColumn();
        $billsPaid = (float)$db->query("SELECT COALESCE(SUM(actual_amount), 0) FROM monthly_bills WHERE month_id = {$mId} AND is_paid = 1")->fetchColumn();
        $varBudget = (float)$db->query("SELECT COALESCE(SUM(estimated_amount), 0) FROM budget_categories WHERE month_id = {$mId}")->fetchColumn();
        $varSpent = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM purchases WHERE month_id = {$mId}")->fetchColumn();
        $gasSpent = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM gas_fillups WHERE month_id = {$mId}")->fetchColumn();

        $monthsSummaryRows[] = [
            $m['id'],
            $m['year'],
            $m['month'],
            $m['label'],
            number_format($incEst, 2, '.', ''),
            number_format($incAct, 2, '.', ''),
            number_format($billsEst, 2, '.', ''),
            number_format($billsPaid, 2, '.', ''),
            number_format($varBudget, 2, '.', ''),
            number_format($varSpent, 2, '.', ''),
            number_format($gasSpent, 2, '.', ''),
            number_format($incEst - $billsEst - $varBudget, 2, '.', ''),
            number_format($incAct - $billsPaid - $varSpent, 2, '.', ''),
            $m['created_at'] ?? ''
        ];
    }

    $zip->addFromString('csv/master_months_summary.csv', arrayToCsv([
        'ID', 'Year', 'Month', 'Label', 
        'Income_Expected', 'Income_Actual', 
        'Bills_Expected', 'Bills_Paid', 
        'Variable_Budget', 'Variable_Spent', 'Gas_Total',
        'Net_Estimated', 'Net_Actual', 'Created_At'
    ], $monthsSummaryRows));

    // 3. Generate Per-Month CSV files
    foreach ($months as $m) {
        $mId = (int)$m['id'];
        $folderName = sprintf("csv/%04d-%02d_%s", $m['year'], $m['month'], preg_replace('/[^a-zA-Z0-9_-]/', '_', $m['label']));

        // Bills
        $st = $db->prepare("SELECT id, due_date_label, due_day, name, category, estimated_amount, actual_amount, is_paid, paid_date, notes, created_at FROM monthly_bills WHERE month_id = ? ORDER BY due_day ASC, id ASC");
        $st->execute([$mId]);
        $bills = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/bills.csv", arrayToCsv([
            'ID', 'Due_Date_Label', 'Due_Day', 'Bill_Name', 'Category', 'Estimated_Amount', 'Actual_Amount', 'Is_Paid', 'Paid_Date', 'Notes', 'Created_At'
        ], $bills));

        // Bill Partial Payments
        $st = $db->prepare("SELECT bp.id, bp.bill_id, b.name as bill_name, bp.amount, bp.payment_date, bp.notes, bp.created_at FROM bill_payments bp JOIN monthly_bills b ON bp.bill_id = b.id WHERE b.month_id = ? ORDER BY bp.payment_date ASC");
        $st->execute([$mId]);
        $payments = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/bill_payments.csv", arrayToCsv([
            'Payment_ID', 'Bill_ID', 'Bill_Name', 'Amount', 'Payment_Date', 'Notes', 'Created_At'
        ], $payments));

        // Income Items
        $st = $db->prepare("SELECT id, source_name, estimated_amount, actual_amount, is_received, received_date, is_recurring, notes, created_at FROM income_items WHERE month_id = ? ORDER BY id ASC");
        $st->execute([$mId]);
        $income = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/income_streams.csv", arrayToCsv([
            'ID', 'Source_Name', 'Estimated_Amount', 'Actual_Amount', 'Is_Received', 'Date_Hits', 'Is_Recurring', 'Notes', 'Created_At'
        ], $income));

        // Budget Categories
        $st = $db->prepare("SELECT id, name, estimated_amount, created_at FROM budget_categories WHERE month_id = ? ORDER BY id ASC");
        $st->execute([$mId]);
        $cats = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/budget_categories.csv", arrayToCsv([
            'ID', 'Category_Name', 'Budgeted_Amount', 'Created_At'
        ], $cats));

        // Purchases
        $st = $db->prepare("SELECT p.id, p.purchase_date, p.item, p.place, c.name as category_name, p.amount, p.notes, p.created_at FROM purchases p LEFT JOIN budget_categories c ON p.category_id = c.id WHERE p.month_id = ? ORDER BY p.purchase_date DESC, p.id DESC");
        $st->execute([$mId]);
        $purchases = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/purchases.csv", arrayToCsv([
            'ID', 'Purchase_Date', 'Item', 'Place_Store', 'Category', 'Amount', 'Notes', 'Created_At'
        ], $purchases));

        // Gas Fill-ups
        $st = $db->prepare("SELECT id, fill_date, station, fuel_type, amount, gallons, price_per_gal, odometer, miles_driven, mpg, notes, created_at FROM gas_fillups WHERE month_id = ? ORDER BY fill_date DESC, id DESC");
        $st->execute([$mId]);
        $gas = $st->fetchAll(PDO::FETCH_ASSOC);
        $zip->addFromString("{$folderName}/gas_tracker.csv", arrayToCsv([
            'ID', 'Fill_Date', 'Station', 'Fuel_Type', 'Amount', 'Gallons', 'Price_Per_Gal', 'Odometer', 'Miles_Driven', 'MPG', 'Notes', 'Created_At'
        ], $gas));
    }

    // 4. Master Global Exports (All Time)
    $allBills = $db->query("SELECT b.id, m.label as month_label, b.due_date_label, b.due_day, b.name, b.category, b.estimated_amount, b.actual_amount, b.is_paid, b.paid_date, b.notes FROM monthly_bills b JOIN months m ON b.month_id = m.id ORDER BY m.year DESC, m.month DESC, b.due_day ASC")->fetchAll(PDO::FETCH_ASSOC);
    $zip->addFromString('csv/master/all_bills_history.csv', arrayToCsv(['ID', 'Month', 'Due_Label', 'Due_Day', 'Name', 'Category', 'Estimated', 'Actual', 'Is_Paid', 'Paid_Date', 'Notes'], $allBills));

    $allIncome = $db->query("SELECT i.id, m.label as month_label, i.source_name, i.estimated_amount, i.actual_amount, i.is_received, i.received_date, i.is_recurring, i.notes FROM income_items i JOIN months m ON i.month_id = m.id ORDER BY m.year DESC, m.month DESC, i.id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $zip->addFromString('csv/master/all_income_history.csv', arrayToCsv(['ID', 'Month', 'Source', 'Estimated', 'Actual', 'Is_Received', 'Date_Hits', 'Is_Recurring', 'Notes'], $allIncome));

    $allPurchases = $db->query("SELECT p.id, m.label as month_label, p.purchase_date, p.item, p.place, c.name as category, p.amount, p.notes FROM purchases p JOIN months m ON p.month_id = m.id LEFT JOIN budget_categories c ON p.category_id = c.id ORDER BY p.purchase_date DESC, p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $zip->addFromString('csv/master/all_purchases_history.csv', arrayToCsv(['ID', 'Month', 'Date', 'Item', 'Place', 'Category', 'Amount', 'Notes'], $allPurchases));

    $allGas = $db->query("SELECT g.id, m.label as month_label, g.fill_date, g.station, g.fuel_type, g.amount, g.gallons, g.price_per_gal, g.odometer, g.notes FROM gas_fillups g JOIN months m ON g.month_id = m.id ORDER BY g.fill_date DESC, g.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $zip->addFromString('csv/master/all_gas_history.csv', arrayToCsv(['ID', 'Month', 'Date', 'Station', 'Fuel_Type', 'Amount', 'Gallons', 'Price_Per_Gal', 'Odometer', 'Notes'], $allGas));

    // 5. Restore Instructions & System Metadata
    $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $readme = "================================================================================\n"
            . "PERSONAL FINANCE APP - COMPLETE SYSTEM & DATA BACKUP\n"
            . "================================================================================\n"
            . "Created: " . date('Y-m-d H:i:s T') . "\n"
            . "Archive Name: {$zipFilename}\n"
            . "Months Archived: " . count($months) . "\n"
            . "Database File: database/budget.sqlite\n"
            . "Total User Accounts: {$userCount}\n\n"
            . "ARCHIVE CONTENTS:\n"
            . "-----------------\n"
            . "1. /database/budget.sqlite\n"
            . "   The complete SQLite 3 database file with all schemas, tables, and records.\n"
            . "   To restore on your server, simply place this file in your 'data/budget_db/' folder.\n\n"
            . "2. /csv/master_months_summary.csv\n"
            . "   High-level month-by-month financial metrics (Income, Bills, Variable Spending, Net).\n\n"
            . "3. /csv/<YYYY-MM_MonthName>/\n"
            . "   Full breakdown of data for each individual month in clean UTF-8 CSV format:\n"
            . "   - bills.csv\n"
            . "   - bill_payments.csv\n"
            . "   - income_streams.csv\n"
            . "   - purchases.csv\n"
            . "   - budget_categories.csv\n"
            . "   - gas_tracker.csv\n\n"
            . "4. /csv/master/\n"
            . "   Complete multi-month historical archives for easy import into Excel, Google Sheets,\n"
            . "   or other databases (PostgreSQL, MySQL, Notion, etc.).\n\n"
            . "================================================================================\n";
    $zip->addFromString('README_RESTORE.txt', $readme);

    $zip->close();

    // Clear output buffers to ensure clean binary delivery
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Deliver ZIP download
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tempZip));

    readfile($tempZip);
    @unlink($tempZip);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Backup Generation Failed</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

