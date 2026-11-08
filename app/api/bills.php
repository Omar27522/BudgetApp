<?php
// api/bills.php - Monthly recurring payments ("Cash Out") CRUD & Paid toggle
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Auth/AuthGuard.php';
AuthGuard::enforce();

require_once __DIR__ . '/../db/db.php';

// Helper to extract numeric day from labels like "1st", "14th", "24", "Day 5"
function extractDueDay(string $label): int {
    if (preg_match('/(\d{1,2})/', $label, $m)) {
        return (int)$m[1];
    }
    // For non-numeric like "Friday", "check day", place at the end of the list
    return 99;
}

try {
    $db = Db::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($method === 'GET') {
        $monthId = (int)($_GET['month_id'] ?? 0);
        if (!$monthId) {
            throw new Exception('Month ID required');
        }

        $stmt = $db->prepare("
            SELECT * FROM monthly_bills 
            WHERE month_id = ? 
            ORDER BY due_day ASC, id ASC
        ");
        $stmt->execute([$monthId]);
        $bills = $stmt->fetchAll();

        $sumEst = 0.0;
        $sumPaid = 0.0;
        $sumRemaining = 0.0;
        $paidCount = 0;

        foreach ($bills as $b) {
            $est = (float)$b['estimated_amount'];
            $act = (float)$b['actual_amount'];
            $isPaid = (int)$b['is_paid'];

            $sumEst += $est;
            if ($isPaid === 1) {
                $sumPaid += ($act > 0 ? $act : $est);
                $paidCount++;
            } else {
                $sumRemaining += $est;
            }
        }

        echo json_encode([
            'success' => true,
            'bills' => $bills,
            'summary' => [
                'total_bills_count' => count($bills),
                'paid_count' => $paidCount,
                'unpaid_count' => count($bills) - $paidCount,
                'total_estimated' => $sumEst,
                'total_paid' => $sumPaid,
                'total_remaining' => $sumRemaining
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        $action = $input['action'] ?? 'add';

        if ($action === 'add') {
            $monthId = (int)($input['month_id'] ?? 0);
            $dueDateLabel = trim($input['due_date_label'] ?? '1st');
            $dueDay = extractDueDay($dueDateLabel);
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? 'Bill');
            $estimated = (float)($input['estimated_amount'] ?? 0.0);
            $actual = isset($input['actual_amount']) ? (float)$input['actual_amount'] : 0.0;
            $isPaid = !empty($input['is_paid']) ? 1 : 0;
            $paidDate = !empty($input['paid_date']) ? trim($input['paid_date']) : null;
            $notes = !empty($input['notes']) ? trim($input['notes']) : null;

            if (!$monthId || empty($name)) {
                throw new Exception('Month ID and Bill Name are required');
            }

            if ($isPaid && empty($paidDate)) {
                $paidDate = date('Y-m-d');
            }

            $stmt = $db->prepare("
                INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, paid_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$monthId, $dueDay, $dueDateLabel, $name, $category, $estimated, $actual, $isPaid, $paidDate, $notes]);

            echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
            exit;
        }

        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            $dueDateLabel = trim($input['due_date_label'] ?? '1st');
            $dueDay = extractDueDay($dueDateLabel);
            $name = trim($input['name'] ?? '');
            $category = trim($input['category'] ?? 'Bill');
            $estimated = (float)($input['estimated_amount'] ?? 0.0);
            $actual = (float)($input['actual_amount'] ?? 0.0);
            $isPaid = !empty($input['is_paid']) ? 1 : 0;
            $paidDate = !empty($input['paid_date']) ? trim($input['paid_date']) : null;
            $notes = !empty($input['notes']) ? trim($input['notes']) : null;

            if (!$id || empty($name)) {
                throw new Exception('Bill ID and Name are required');
            }

            $stmt = $db->prepare("
                UPDATE monthly_bills 
                SET due_day = ?, due_date_label = ?, name = ?, category = ?, estimated_amount = ?, actual_amount = ?, is_paid = ?, paid_date = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$dueDay, $dueDateLabel, $name, $category, $estimated, $actual, $isPaid, $paidDate, $notes, $id]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'toggle_paid') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('Bill ID required');
            }

            // Fetch current state
            $curStmt = $db->prepare("SELECT * FROM monthly_bills WHERE id = ?");
            $curStmt->execute([$id]);
            $bill = $curStmt->fetch();
            if (!$bill) {
                throw new Exception('Bill not found');
            }

            $currentPaid = (int)$bill['is_paid'];
            $newPaid = $currentPaid === 1 ? 0 : 1;

            if ($newPaid === 1) {
                // If marking paid and actual_amount is 0 or null, set to estimated_amount unless passed
                $act = isset($input['actual_amount']) && (float)$input['actual_amount'] > 0 
                    ? (float)$input['actual_amount'] 
                    : ((float)$bill['actual_amount'] > 0 ? (float)$bill['actual_amount'] : (float)$bill['estimated_amount']);
                $pDate = !empty($input['paid_date']) ? trim($input['paid_date']) : date('Y-m-d');
            } else {
                // If unmarking, clear actual paid amount and date
                $act = 0.0;
                $pDate = null;
            }

            $upd = $db->prepare("
                UPDATE monthly_bills 
                SET is_paid = ?, actual_amount = ?, paid_date = ? 
                WHERE id = ?
            ");
            $upd->execute([$newPaid, $act, $pDate, $id]);

            echo json_encode([
                'success' => true,
                'is_paid' => $newPaid,
                'actual_amount' => $act,
                'paid_date' => $pDate
            ]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('Bill ID required');
            }

            $stmt = $db->prepare("DELETE FROM monthly_bills WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'copy_from_month') {
            $targetMonthId = (int)($input['target_month_id'] ?? 0);
            $sourceMonthId = (int)($input['source_month_id'] ?? 0);

            if (!$targetMonthId || !$sourceMonthId) {
                throw new Exception('Target and Source Month IDs are required');
            }

            $stmt = $db->prepare("
                INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, paid_date, notes)
                SELECT ?, due_day, due_date_label, name, category, estimated_amount, 0.0, 0, NULL, notes
                FROM monthly_bills
                WHERE month_id = ?
            ");
            $stmt->execute([$targetMonthId, $sourceMonthId]);

            echo json_encode(['success' => true, 'copied_count' => $stmt->rowCount()]);
            exit;
        }
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

