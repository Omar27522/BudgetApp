<?php
// src/Helpers/Format.php - Common formatting utilities

class Format {
    public static function money(float $amount): string {
        $formatted = number_format(abs($amount), 2);
        return $amount < 0 ? '-$' . $formatted : '$' . $formatted;
    }

    public static function extractDueDay(string $label): int {
        if (preg_match('/(\d{1,2})/', $label, $m)) {
            return (int)$m[1];
        }
        if (stripos($label, 'check') !== false) {
            return 98;
        }
        return 99;
    }

    public static function monthNames(): array {
        return [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
    }

    /**
     * Compact date like "Nov 7". The year is only shown when it differs
     * from the current year (e.g. "Dec 30, 2025").
     */
    public static function shortDate(?string $date): string {
        if (empty($date) || ($ts = strtotime($date)) === false) {
            return '';
        }
        return date('Y', $ts) === date('Y') ? date('M j', $ts) : date('M j, Y', $ts);
    }
}

