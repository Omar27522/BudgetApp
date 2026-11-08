<?php
// src/Models/Income.php - Income Streams
require_once __DIR__ . '/../../db/db.php';

class Income {
    public static function getForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM income_items WHERE month_id = ? ORDER BY id ASC");
        $st->execute([$monthId]);
        return $st->fetchAll();
    }

    public static function getById(int $id, int $monthId): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM income_items WHERE id = ? AND month_id = ?");
        $st->execute([$id, $monthId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function add(int $monthId, string $source, float $est, float $act = 0.0, bool $isRecurring = true, ?string $receivedDate = null, string $notes = ''): void {
        if (empty(trim($source))) return;
        $db = Db::getConnection();
        $isReceived = ($act > 0 || !empty($receivedDate)) ? 1 : 0;
        $st = $db->prepare("
            INSERT INTO income_items (month_id, source_name, estimated_amount, actual_amount, is_received, is_recurring, received_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $monthId, 
            trim($source), 
            $est, 
            $act, 
            $isReceived, 
            $isRecurring ? 1 : 0, 
            $receivedDate ?: null, 
            trim($notes)
        ]);
    }

    public static function update(int $id, string $source, float $est, float $act, bool $isRecurring = true, ?bool $isReceived = null, ?string $receivedDate = null, string $notes = ''): void {
        $db = Db::getConnection();
        if ($isReceived === null) {
            $isReceived = ($act > 0 || !empty($receivedDate));
        }
        $st = $db->prepare("
            UPDATE income_items 
            SET source_name = ?, estimated_amount = ?, actual_amount = ?, is_received = ?, is_recurring = ?, received_date = ?, notes = ?
            WHERE id = ?
        ");
        $st->execute([
            trim($source), 
            $est, 
            $act, 
            $isReceived ? 1 : 0, 
            $isRecurring ? 1 : 0, 
            $receivedDate ?: null, 
            trim($notes), 
            $id
        ]);
    }

    public static function toggleReceived(int $id): void {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM income_items WHERE id = ?");
        $st->execute([$id]);
        $inc = $st->fetch();
        if (!$inc) return;

        $currentlyReceived = ((int)$inc['is_received'] === 1 || (float)$inc['actual_amount'] > 0);
        if ($currentlyReceived) {
            $db->prepare("UPDATE income_items SET is_received = 0, actual_amount = 0.0, received_date = NULL WHERE id = ?")->execute([$id]);
        } else {
            // Keep the income amount (actual matches estimated if not previously set)
            $act = (float)$inc['actual_amount'] > 0 ? (float)$inc['actual_amount'] : (float)$inc['estimated_amount'];
            $today = date('Y-m-d');
            $db->prepare("UPDATE income_items SET is_received = 1, actual_amount = ?, received_date = ? WHERE id = ?")->execute([$act, $today, $id]);
        }
    }

    public static function toggleRecurring(int $id): void {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT is_recurring FROM income_items WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row === false) return;

        $newVal = ((int)$row['is_recurring'] === 1) ? 0 : 1;
        $db->prepare("UPDATE income_items SET is_recurring = ? WHERE id = ?")->execute([$newVal, $id]);
    }

    public static function updateReceivedDate(int $id, ?string $receivedDate): void {
        $db = Db::getConnection();
        if (!empty($receivedDate)) {
            // Setting date also ensures marked as received and keeps estimated if actual was 0
            $st = $db->prepare("SELECT estimated_amount, actual_amount FROM income_items WHERE id = ?");
            $st->execute([$id]);
            $inc = $st->fetch();
            $act = ($inc && (float)$inc['actual_amount'] > 0) ? (float)$inc['actual_amount'] : (float)($inc['estimated_amount'] ?? 0.0);

            $db->prepare("UPDATE income_items SET received_date = ?, is_received = 1, actual_amount = ? WHERE id = ?")
               ->execute([$receivedDate, $act, $id]);
        } else {
            $db->prepare("UPDATE income_items SET received_date = NULL, is_received = 0, actual_amount = 0.0 WHERE id = ?")->execute([$id]);
        }
    }

    public static function delete(int $id): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM income_items WHERE id = ?")->execute([$id]);
    }

    public static function getSummary(array $incomes): array {
        $totalEst = 0.0;
        $totalAct = 0.0;
        $receivedCount = 0;
        foreach ($incomes as $inc) {
            $totalEst += (float)$inc['estimated_amount'];
            $totalAct += (float)$inc['actual_amount'];
            if ((int)($inc['is_received'] ?? 0) === 1 || (float)$inc['actual_amount'] > 0) {
                $receivedCount++;
            }
        }
        return [
            'total_estimated' => $totalEst,
            'total_actual' => $totalAct,
            'difference' => $totalAct - $totalEst,
            'received_count' => $receivedCount,
            'total_count' => count($incomes)
        ];
    }
}
