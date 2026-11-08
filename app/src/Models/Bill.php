<?php
// src/Models/Bill.php - Monthly Recurring Bills ("Cash Out")
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../Helpers/Format.php';

class Bill {
    public static function getForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("
            SELECT b.*,
                (SELECT COUNT(*) FROM bill_payments WHERE bill_id = b.id) AS payment_count
            FROM monthly_bills b 
            WHERE b.month_id = ? 
            ORDER BY b.due_day ASC, b.id ASC
        ");
        $st->execute([$monthId]);
        return $st->fetchAll();
    }

    public static function getById(int $id, int $monthId): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("
            SELECT b.*,
                (SELECT COUNT(*) FROM bill_payments WHERE bill_id = b.id) AS payment_count
            FROM monthly_bills b
            WHERE b.id = ? AND b.month_id = ?
        ");
        $st->execute([$id, $monthId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function getPayments(int $billId): array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM bill_payments WHERE bill_id = ? ORDER BY payment_date ASC, id ASC");
        $st->execute([$billId]);
        return $st->fetchAll();
    }

    public static function getAllPaymentsForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("
            SELECT bp.* 
            FROM bill_payments bp
            JOIN monthly_bills b ON b.id = bp.bill_id
            WHERE b.month_id = ?
            ORDER BY bp.payment_date ASC, bp.id ASC
        ");
        $st->execute([$monthId]);
        $rows = $st->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(int)$r['bill_id']][] = $r;
        }
        return $grouped;
    }

    public static function addPayment(int $billId, float $amount, string $paymentDate, string $notes = ''): void {
        if ($amount <= 0 || empty(trim($paymentDate))) return;
        $db = Db::getConnection();

        $st = $db->prepare("INSERT INTO bill_payments (bill_id, amount, payment_date, notes) VALUES (?, ?, ?, ?)");
        $st->execute([$billId, $amount, trim($paymentDate), trim($notes ?: null)]);

        self::syncBillPaymentsTotals($billId);
    }

    public static function updatePayment(int $paymentId, int $billId, float $amount, string $paymentDate, string $notes = ''): void {
        if ($amount <= 0 || empty(trim($paymentDate))) return;
        $db = Db::getConnection();

        $st = $db->prepare("UPDATE bill_payments SET amount = ?, payment_date = ?, notes = ? WHERE id = ? AND bill_id = ?");
        $st->execute([$amount, trim($paymentDate), trim($notes ?: null), $paymentId, $billId]);

        self::syncBillPaymentsTotals($billId);
    }

    public static function deletePayment(int $paymentId, int $billId): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM bill_payments WHERE id = ? AND bill_id = ?")->execute([$paymentId, $billId]);

        self::syncBillPaymentsTotals($billId);
    }

    public static function syncBillPaymentsTotals(int $billId): void {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT SUM(amount) AS total, MAX(payment_date) AS latest_date, COUNT(*) AS cnt FROM bill_payments WHERE bill_id = ?");
        $st->execute([$billId]);
        $res = $st->fetch();

        $cnt = (int)($res['cnt'] ?? 0);
        if ($cnt > 0) {
            $total = (float)$res['total'];
            $latest = $res['latest_date'];
            $isPaid = ($total > 0) ? 1 : 0;

            $db->prepare("UPDATE monthly_bills SET actual_amount = ?, paid_date = ?, is_paid = ? WHERE id = ?")
               ->execute([$total, $latest, $isPaid, $billId]);
        } else {
            $db->prepare("UPDATE monthly_bills SET actual_amount = 0.0, paid_date = NULL, is_paid = 0 WHERE id = ?")
               ->execute([$billId]);
        }
    }

    public static function add(int $monthId, string $dueLabel, string $name, string $category, float $est, string $notes = ''): void {
        if (empty(trim($name))) return;
        $db = Db::getConnection();
        $dueDay = Format::extractDueDay($dueLabel);
        $st = $db->prepare("
            INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, notes)
            VALUES (?, ?, ?, ?, ?, ?, 0.0, 0, ?)
        ");
        $st->execute([$monthId, $dueDay, trim($dueLabel), trim($name), trim($category ?: 'Bill'), $est, trim($notes)]);
    }

    public static function update(int $id, string $dueLabel, string $name, string $category, float $est, float $act, bool $isPaid, ?string $paidDate, string $notes = ''): void {
        $db = Db::getConnection();
        $dueDay = Format::extractDueDay($dueLabel);

        $cnt = (int)$db->query("SELECT COUNT(*) FROM bill_payments WHERE bill_id = {$id}")->fetchColumn();

        if ($cnt > 0) {
            // Bill has tracked payments: update bill info, keep actual_amount & paid_date synchronized with payments
            $st = $db->prepare("
                UPDATE monthly_bills 
                SET due_day = ?, due_date_label = ?, name = ?, category = ?, estimated_amount = ?, notes = ?
                WHERE id = ?
            ");
            $st->execute([$dueDay, trim($dueLabel), trim($name), trim($category ?: 'Bill'), $est, trim($notes), $id]);
            self::syncBillPaymentsTotals($id);
        } else {
            // No tracked payments yet: update all fields
            $st = $db->prepare("
                UPDATE monthly_bills 
                SET due_day = ?, due_date_label = ?, name = ?, category = ?, estimated_amount = ?, actual_amount = ?, is_paid = ?, paid_date = ?, notes = ?
                WHERE id = ?
            ");
            $st->execute([$dueDay, trim($dueLabel), trim($name), trim($category ?: 'Bill'), $est, $act, $isPaid ? 1 : 0, $paidDate ?: null, trim($notes), $id]);

            if ($act > 0 || !empty($paidDate) || $isPaid) {
                $pDate = !empty($paidDate) ? trim($paidDate) : date('Y-m-d');
                $pAmt = $act > 0 ? $act : $est;
                $db->prepare("INSERT INTO bill_payments (bill_id, amount, payment_date, notes) VALUES (?, ?, ?, 'Payment')")
                   ->execute([$id, $pAmt, $pDate]);
                self::syncBillPaymentsTotals($id);
            }
        }
    }

    public static function togglePaid(int $id): void {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM monthly_bills WHERE id = ?");
        $st->execute([$id]);
        $bill = $st->fetch();
        if (!$bill) return;

        if ((int)$bill['is_paid'] === 1) {
            $db->prepare("DELETE FROM bill_payments WHERE bill_id = ?")->execute([$id]);
            $db->prepare("UPDATE monthly_bills SET is_paid = 0, actual_amount = 0.0, paid_date = NULL WHERE id = ?")->execute([$id]);
        } else {
            $act = (float)$bill['actual_amount'] > 0 ? (float)$bill['actual_amount'] : (float)$bill['estimated_amount'];
            $today = date('Y-m-d');
            $db->prepare("DELETE FROM bill_payments WHERE bill_id = ?")->execute([$id]);
            $db->prepare("INSERT INTO bill_payments (bill_id, amount, payment_date, notes) VALUES (?, ?, ?, 'Paid in full')")
               ->execute([$id, $act, $today]);
            self::syncBillPaymentsTotals($id);
        }
    }

    public static function updatePaidDate(int $id, ?string $paidDate): void {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM monthly_bills WHERE id = ?");
        $st->execute([$id]);
        $bill = $st->fetch();
        if (!$bill) return;

        if (!empty($paidDate)) {
            $pDate = trim($paidDate);
            $cnt = (int)$db->query("SELECT COUNT(*) FROM bill_payments WHERE bill_id = {$id}")->fetchColumn();
            if ($cnt <= 1) {
                $act = (float)$bill['actual_amount'] > 0 ? (float)$bill['actual_amount'] : (float)$bill['estimated_amount'];
                $db->prepare("DELETE FROM bill_payments WHERE bill_id = ?")->execute([$id]);
                $db->prepare("INSERT INTO bill_payments (bill_id, amount, payment_date, notes) VALUES (?, ?, ?, 'Payment')")
                   ->execute([$id, $act, $pDate]);
                self::syncBillPaymentsTotals($id);
            } else {
                $db->prepare("UPDATE monthly_bills SET paid_date = ? WHERE id = ?")->execute([$pDate, $id]);
            }
        } else {
            $db->prepare("DELETE FROM bill_payments WHERE bill_id = ?")->execute([$id]);
            $db->prepare("UPDATE monthly_bills SET paid_date = NULL, is_paid = 0, actual_amount = 0.0 WHERE id = ?")->execute([$id]);
        }
    }

    public static function delete(int $id): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM monthly_bills WHERE id = ?")->execute([$id]);
    }

    public static function copyFromMonth(int $targetMonthId, int $sourceMonthId): void {
        $db = Db::getConnection();
        $db->prepare("
            INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, notes)
            SELECT ?, due_day, due_date_label, name, category, estimated_amount, 0.0, 0, notes
            FROM monthly_bills WHERE month_id = ?
        ")->execute([$targetMonthId, $sourceMonthId]);
    }

    public static function getSummary(array $bills): array {
        $totalEst = 0.0;
        $totalPaid = 0.0;
        $totalRemaining = 0.0;
        $paidCount = 0;

        foreach ($bills as $b) {
            $est = (float)$b['estimated_amount'];
            $act = (float)$b['actual_amount'];
            $isPaid = (int)$b['is_paid'] === 1;

            $totalEst += $est;
            if ($isPaid) {
                $totalPaid += ($act > 0 ? $act : $est);
                $paidCount++;
                if ($act < $est) {
                    $totalRemaining += ($est - $act);
                }
            } else {
                $totalRemaining += $est;
            }
        }

        return [
            'total_count' => count($bills),
            'paid_count' => $paidCount,
            'unpaid_count' => count($bills) - $paidCount,
            'total_estimated' => $totalEst,
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining
        ];
    }
}

