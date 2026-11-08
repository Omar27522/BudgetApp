<?php
// src/Models/Purchase.php - Daily Purchases / Expenses
require_once __DIR__ . '/../../db/db.php';

class Purchase {
    public static function getForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("
            SELECT p.*, c.name AS category_name
            FROM purchases p
            LEFT JOIN budget_categories c ON p.category_id = c.id
            WHERE p.month_id = ?
            ORDER BY p.purchase_date DESC, p.id DESC
        ");
        $st->execute([$monthId]);
        return $st->fetchAll();
    }

    public static function getById(int $id, int $monthId): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM purchases WHERE id = ? AND month_id = ?");
        $st->execute([$id, $monthId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function add(int $monthId, string $date, string $item, string $place, float $amount, ?int $categoryId, string $notes = ''): void {
        if (empty(trim($item)) || $amount <= 0) return;
        $db = Db::getConnection();
        $st = $db->prepare("
            INSERT INTO purchases (month_id, category_id, purchase_date, item, place, amount, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([$monthId, $categoryId ?: null, trim($date), trim($item), trim($place ?: null), $amount, trim($notes ?: null)]);
    }

    public static function update(int $id, int $monthId, string $date, string $item, string $place, float $amount, ?int $categoryId, string $notes = ''): void {
        $db = Db::getConnection();
        $st = $db->prepare("
            UPDATE purchases 
            SET category_id = ?, purchase_date = ?, item = ?, place = ?, amount = ?, notes = ?
            WHERE id = ? AND month_id = ?
        ");
        $st->execute([$categoryId ?: null, trim($date), trim($item), trim($place ?: null), $amount, trim($notes ?: null), $id, $monthId]);
    }

    public static function delete(int $id, int $monthId): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM purchases WHERE id = ? AND month_id = ?")->execute([$id, $monthId]);
    }

    public static function getTotalSpent(array $purchases): float {
        $total = 0.0;
        foreach ($purchases as $p) {
            $total += (float)$p['amount'];
        }
        return $total;
    }
}

