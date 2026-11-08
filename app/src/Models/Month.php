<?php
// src/Models/Month.php
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../Helpers/Format.php';

class Month {
    public static function getAll(): array {
        $db = Db::getConnection();
        return $db->query("SELECT * FROM months ORDER BY year DESC, month DESC")->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM months WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function getOrCreateCurrent(): array {
        $all = self::getAll();
        if (!empty($all)) {
            return $all;
        }

        $db = Db::getConnection();
        $y = (int)date('Y');
        $m = (int)date('n');
        $names = Format::monthNames();
        $label = ($names[$m] ?? 'Month') . ' ' . $y;

        $ins = $db->prepare("INSERT INTO months (year, month, label) VALUES (?, ?, ?)");
        $ins->execute([$y, $m, $label]);
        $newId = (int)$db->lastInsertId();

        return [['id' => $newId, 'year' => $y, 'month' => $m, 'label' => $label]];
    }

    public static function create(int $year, int $month, ?int $copyFromId = null, bool $copyIncome = true, bool $copyCategories = true): int {
        $db = Db::getConnection();
        $names = Format::monthNames();
        $label = ($names[$month] ?? 'Month ' . $month) . ' ' . $year;

        $chk = $db->prepare("SELECT id FROM months WHERE year = ? AND month = ?");
        $chk->execute([$year, $month]);
        $existing = $chk->fetch();

        if ($existing) {
            return (int)$existing['id'];
        }

        $stmt = $db->prepare("INSERT INTO months (year, month, label) VALUES (?, ?, ?)");
        $stmt->execute([$year, $month, $label]);
        $targetId = (int)$db->lastInsertId();

        if ($copyFromId) {
            // Copy recurring bills
            $db->prepare("
                INSERT INTO monthly_bills (month_id, due_day, due_date_label, name, category, estimated_amount, actual_amount, is_paid, notes)
                SELECT ?, due_day, due_date_label, name, category, estimated_amount, 0.0, 0, notes
                FROM monthly_bills WHERE month_id = ?
            ")->execute([$targetId, $copyFromId]);

            if ($copyIncome) {
                $db->prepare("
                    INSERT INTO income_items (month_id, source_name, estimated_amount, actual_amount, is_received, is_recurring, notes)
                    SELECT ?, source_name, estimated_amount, 0.0, 0, is_recurring, notes
                    FROM income_items WHERE month_id = ?
                ")->execute([$targetId, $copyFromId]);
            }

            if ($copyCategories) {
                $db->prepare("
                    INSERT INTO budget_categories (month_id, name, estimated_amount)
                    SELECT ?, name, estimated_amount
                    FROM budget_categories WHERE month_id = ?
                ")->execute([$targetId, $copyFromId]);
            }
        }

        return $targetId;
    }
}

