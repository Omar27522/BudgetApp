<?php
// api/months.php - Month management & overview
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Auth/AuthGuard.php';
AuthGuard::enforce();

require_once __DIR__ . '/../db/db.php';

try {
    $db = Db::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // Read JSON payload if available
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($method === 'GET') {
        // Fetch all months with aggregated metrics
        $stmt = $db->query("
            SELECT m.*,
                COALESCE((SELECT SUM(estimated_amount) FROM income_items WHERE month_id = m.id), 0) AS total_income_est,
                COALESCE((SELECT SUM(actual_amount) FROM income_items WHERE month_id = m.id), 0) AS total_income_act,
                COALESCE((SELECT SUM(estimated_amount) FROM monthly_bills WHERE month_id = m.id), 0) AS total_bills_est,
                COALESCE((SELECT SUM(actual_amount) FROM monthly_bills WHERE month_id = m.id AND is_paid = 1), 0) AS total_bills_act,
                (SELECT COUNT(*) FROM monthly_bills WHERE month_id = m.id) AS bills_count,
                (SELECT COUNT(*) FROM monthly_bills WHERE month_id = m.id AND is_paid = 1) AS bills_paid_count
            FROM months m
            ORDER BY m.year DESC, m.month DESC
        ");
        $months = $stmt->fetchAll();

        // If no months exist yet, auto-initialize the current month
        if (empty($months)) {
            $curYear = (int)date('Y');
            $curMonth = (int)date('n');
            $monthNames = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
            $label = $monthNames[$curMonth] . ' ' . $curYear;

            $ins = $db->prepare("INSERT INTO months (year, month, label) VALUES (?, ?, ?)");
            $ins->execute([$curYear, $curMonth, $label]);
            $newId = (int)$db->lastInsertId();

            $months = [[
                'id' => $newId,
                'year' => $curYear,
                'month' => $curMonth,
                'label' => $label,
                'total_income_est' => 0,
                'total_income_act' => 0,
                'total_bills_est' => 0,
                'total_bills_act' => 0,
                'bills_count' => 0,
                'bills_paid_count' => 0,
            ]];
        }

        echo json_encode(['success' => true, 'months' => $months]);
        exit;
    }

    if ($method === 'POST') {
        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $year = (int)($input['year'] ?? date('Y'));
            $month = (int)($input['month'] ?? date('n'));
            $monthNames = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
            $label = $monthNames[$month] . ' ' . $year;

            // Check if month already exists
            $check = $db->prepare("SELECT id FROM months WHERE year = ? AND month = ?");
            $check->execute([$year, $month]);
            $existing = $check->fetch();

            if ($existing) {
                echo json_encode(['success' => true, 'month_id' => $existing['id'], 'message' => 'Month already exists']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO months (year, month, label) VALUES (?, ?, ?)");
            $stmt->execute([$year, $month, $label]);
            $newMonthId = (int)$db->lastInsertId();

            // Check if user requested cloning bills from a prior month
            $copyFromId = isset($input['copy_from_id']) ? (int)$input['copy_from_id'] : null;
            if ($copyFromId) {
                $copyStmt = $db->prepare("
                    INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, notes)
                    SELECT ?, due_day, due_date_label, name, category, estimated_amount, 0.0, 0, notes
                    FROM monthly_bills
                    WHERE month_id = ?
                ");
                $copyStmt->execute([$newMonthId, $copyFromId]);

                // Also copy standard recurring income sources if requested
                if (!empty($input['copy_income'])) {
                    $copyInc = $db->prepare("
                        INSERT INTO income_items (month_id, source_name, estimated_amount, actual_amount, notes)
                        SELECT ?, source_name, estimated_amount, 0.0, notes
                        FROM income_items
                        WHERE month_id = ?
                    ");
                    $copyInc->execute([$newMonthId, $copyFromId]);
                }
            }

            echo json_encode(['success' => true, 'month_id' => $newMonthId, 'label' => $label]);
            exit;
        }

        if ($action === 'delete') {
            $monthId = (int)($input['month_id'] ?? 0);
            if (!$monthId) {
                throw new Exception('Month ID required');
            }
            $stmt = $db->prepare("DELETE FROM months WHERE id = ?");
            $stmt->execute([$monthId]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

