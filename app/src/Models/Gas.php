<?php
// src/Models/Gas.php - Gas & Fuel Tracker Model
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/Purchase.php';

class Gas {
    public static function getForMonth(int $monthId): array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM gas_fillups WHERE month_id = ? ORDER BY fill_date DESC, id DESC");
        $st->execute([$monthId]);
        return $st->fetchAll();
    }

    public static function getById(int $id, int $monthId): ?array {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM gas_fillups WHERE id = ? AND month_id = ?");
        $st->execute([$id, $monthId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function add(int $monthId, string $date, string $station, string $fuelType, float $amount, float $gallons, ?float $odometer, ?float $milesDriven = null, string $notes = '', bool $syncToPurchases = true): void {
        if ($amount <= 0 || empty(trim($station))) return;
        $db = Db::getConnection();

        // Auto-calculate miles driven if odometer is given and milesDriven is not
        if (!$milesDriven && $odometer) {
            $prevSt = $db->prepare("SELECT odometer FROM gas_fillups WHERE odometer IS NOT NULL AND odometer < ? ORDER BY fill_date DESC, id DESC LIMIT 1");
            $prevSt->execute([$odometer]);
            $prev = $prevSt->fetch();
            if ($prev && (float)$prev['odometer'] > 0) {
                $milesDriven = round($odometer - (float)$prev['odometer'], 1);
            }
        }

        $ppg = $gallons > 0 ? round($amount / $gallons, 3) : 0.0;
        $mpg = ($milesDriven && $gallons > 0) ? round($milesDriven / $gallons, 1) : null;

        $st = $db->prepare("
            INSERT INTO gas_fillups (month_id, fill_date, station, fuel_type, amount, gallons, price_per_gal, odometer, miles_driven, mpg, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $monthId, trim($date), trim($station), trim($fuelType ?: 'Regular'),
            $amount, $gallons, $ppg, $odometer, $milesDriven, $mpg, trim($notes ?: null)
        ]);

        if ($syncToPurchases) {
            // Find "Gas" category id if exists
            $catSt = $db->prepare("SELECT id FROM budget_categories WHERE month_id = ? AND LOWER(name) LIKE '%gas%' LIMIT 1");
            $catSt->execute([$monthId]);
            $cat = $catSt->fetch();
            $catId = $cat ? (int)$cat['id'] : null;

            Purchase::add($monthId, $date, "Gas - " . trim($station), trim($station), $amount, $catId, "Auto-logged from Gas Tracker");
        }
    }

    public static function update(int $id, int $monthId, string $date, string $station, string $fuelType, float $amount, float $gallons, ?float $odometer, ?float $milesDriven = null, string $notes = ''): void {
        $db = Db::getConnection();

        // Auto-calculate miles driven if odometer is given and milesDriven is not
        if (!$milesDriven && $odometer) {
            $prevSt = $db->prepare("SELECT odometer FROM gas_fillups WHERE id != ? AND odometer IS NOT NULL AND odometer < ? ORDER BY fill_date DESC, id DESC LIMIT 1");
            $prevSt->execute([$id, $odometer]);
            $prev = $prevSt->fetch();
            if ($prev && (float)$prev['odometer'] > 0) {
                $milesDriven = round($odometer - (float)$prev['odometer'], 1);
            }
        }

        $ppg = $gallons > 0 ? round($amount / $gallons, 3) : 0.0;
        $mpg = ($milesDriven && $gallons > 0) ? round($milesDriven / $gallons, 1) : null;

        $st = $db->prepare("
            UPDATE gas_fillups 
            SET fill_date = ?, station = ?, fuel_type = ?, amount = ?, gallons = ?, price_per_gal = ?, odometer = ?, miles_driven = ?, mpg = ?, notes = ?
            WHERE id = ? AND month_id = ?
        ");
        $st->execute([
            trim($date), trim($station), trim($fuelType ?: 'Regular'),
            $amount, $gallons, $ppg, $odometer, $milesDriven, $mpg, trim($notes ?: null),
            $id, $monthId
        ]);
    }

    public static function delete(int $id, int $monthId): void {
        $db = Db::getConnection();
        $db->prepare("DELETE FROM gas_fillups WHERE id = ? AND month_id = ?")->execute([$id, $monthId]);
    }

    public static function getSummary(array $fillups): array {
        $totalSpent = 0.0;
        $totalGallons = 0.0;
        $totalMiles = 0.0;
        $validMpgMiles = 0.0;
        $validMpgGallons = 0.0;

        foreach ($fillups as $f) {
            $amt = (float)$f['amount'];
            $gal = (float)$f['gallons'];
            $mi = (float)($f['miles_driven'] ?? 0);

            $totalSpent += $amt;
            $totalGallons += $gal;
            $totalMiles += $mi;

            if ($mi > 0 && $gal > 0) {
                $validMpgMiles += $mi;
                $validMpgGallons += $gal;
            }
        }

        $avgPpg = $totalGallons > 0 ? round($totalSpent / $totalGallons, 3) : 0.0;
        $avgMpg = $validMpgGallons > 0 ? round($validMpgMiles / $validMpgGallons, 1) : 0.0;

        return [
            'total_spent' => $totalSpent,
            'total_gallons' => $totalGallons,
            'total_miles' => $totalMiles,
            'avg_price_per_gal' => $avgPpg,
            'avg_mpg' => $avgMpg,
            'fillup_count' => count($fillups)
        ];
    }
}

