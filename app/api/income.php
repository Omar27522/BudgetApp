<?php
// api/income.php - Income items CRUD
header('Content-Type: application/json');
require_once __DIR__ . '/../src/Auth/AuthGuard.php';
AuthGuard::enforce();

require_once __DIR__ . '/../db/db.php';

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
            SELECT * FROM income_items 
            WHERE month_id = ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$monthId]);
        $items = $stmt->fetchAll();

        // Calculate summary for this month
        $sumEst = 0.0;
        $sumAct = 0.0;
        foreach ($items as $item) {
            $sumEst += (float)$item['estimated_amount'];
            $sumAct += (float)$item['actual_amount'];
        }

        echo json_encode([
            'success' => true,
            'items' => $items,
            'summary' => [
                'total_estimated' => $sumEst,
                'total_actual' => $sumAct,
                'difference' => $sumAct - $sumEst
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        $action = $input['action'] ?? 'add';

        if ($action === 'add') {
            $monthId = (int)($input['month_id'] ?? 0);
            $sourceName = trim($input['source_name'] ?? '');
            $estimated = (float)($input['estimated_amount'] ?? 0.0);
            $actual = (float)($input['actual_amount'] ?? 0.0);
            $receivedDate = !empty($input['received_date']) ? trim($input['received_date']) : null;
            $notes = !empty($input['notes']) ? trim($input['notes']) : null;

            if (!$monthId || empty($sourceName)) {
                throw new Exception('Month ID and Source Name are required');
            }

            $stmt = $db->prepare("
                INSERT INTO income_items (month_id, source_name, estimated_amount, actual_amount, received_date, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$monthId, $sourceName, $estimated, $actual, $receivedDate, $notes]);

            echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
            exit;
        }

        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);
            $sourceName = trim($input['source_name'] ?? '');
            $estimated = (float)($input['estimated_amount'] ?? 0.0);
            $actual = (float)($input['actual_amount'] ?? 0.0);
            $receivedDate = !empty($input['received_date']) ? trim($input['received_date']) : null;
            $notes = !empty($input['notes']) ? trim($input['notes']) : null;

            if (!$id || empty($sourceName)) {
                throw new Exception('ID and Source Name are required');
            }

            $stmt = $db->prepare("
                UPDATE income_items 
                SET source_name = ?, estimated_amount = ?, actual_amount = ?, received_date = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$sourceName, $estimated, $actual, $receivedDate, $notes, $id]);

            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID is required');
            }

            $stmt = $db->prepare("DELETE FROM income_items WHERE id = ?");
            $stmt->execute([$id]);

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

