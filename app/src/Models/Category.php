<?php
// src/Models/Category.php - Variable Budget Categories
require_once __DIR__ . '/../../db/db.php';

class Category {
    public static function getForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("
            SELECT c.*,
                COALESCE((SELECT SUM(amount) FROM purchases WHERE month_id = c.month_id AND category_id = c.id), 0.0) AS actual_spent,
                (SELECT COUNT(*) FROM purchases WHERE month_id = c.month_id AND category_id = c.id) AS purchase_count
            FROM budget_categories c
            WHERE c.month_id = ?
            ORDER BY c.id ASC
        ");
        $st->execute([$monthId]);
        return $st->fetchAll();
    }

    public static function getById(int $id, int $monthId): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM budget_categories WHERE id = ? AND month_id = ?");
        $st->execute([$id, $monthId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function add(int $monthId, string $name, float $est): void {
        if (empty(trim($name))) return;
        $db = Db::getConnection();
        $st = $db->prepare("INSERT INTO budget_categories (month_id, name, estimated_amount) VALUES (?, ?, ?)");
        $st->execute([$monthId, trim($name), $est]);
    }

    public static function update(int $id, int $monthId, string $name, float $est): void {
        $db = Db::getConnection();
        $st = $db->prepare("UPDATE budget_categories SET name = ?, estimated_amount = ? WHERE id = ? AND month_id = ?");
        $st->execute([trim($name), $est, $id, $monthId]);
    }

    public static function delete(int $id, int $monthId): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM budget_categories WHERE id = ? AND month_id = ?")->execute([$id, $monthId]);
    }

    public static function seedStandard(int $monthId): void {
        $db = Db::getConnection();
        $standards = [
            ['Gas', 250.0],
            ['Food', 50.0],
            ['Clothing', 35.0],
            ['Repairs & maintenance', 90.0],
            ['Entertainment', 50.0],
            ['Misc.', 110.0],
            ['Personal & Gifts', 100.0],
            ['Tithe', 28.5],
            ['Tax 12%', 0.0],
        ];
        $ins = $db->prepare("INSERT INTO budget_categories (month_id, name, estimated_amount) VALUES (?, ?, ?)");
        foreach ($standards as $s) {
            $ins->execute([$monthId, $s[0], $s[1]]);
        }
    }

    public static function getSummary(array $categories): array {
        $totalEst = 0.0;
        $totalAct = 0.0;
        foreach ($categories as $cat) {
            $totalEst += (float)$cat['estimated_amount'];
            $totalAct += (float)$cat['actual_spent'];
        }
        return [
            'total_estimated' => $totalEst,
            'total_actual' => $totalAct,
            'remaining' => $totalEst - $totalAct
        ];
    }
}

