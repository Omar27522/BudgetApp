<?php
// app/database/db.php - SQLite PDO Database Helper and Auto-Migration

class Db {
    private static ?PDO $pdo = null;

    /**
     * Resolves the secure database path located outside the web root.
     */
    public static function getDatabasePath(): string {
        // 1. Environment variable if explicitly defined
        $envPath = getenv('BUDGET_DB_PATH');
        if (!empty($envPath)) {
            return $envPath;
        }

        // 2. Candidate database paths outside web root
        $candidates = [
            // Local development: BudgetApp/data/budget_db/budget.sqlite
            dirname(__DIR__, 2) . '/data/budget_db/budget.sqlite',
            dirname(__DIR__) . '/../data/budget_db/budget.sqlite',

            // cPanel Subdomain root: /home4/latinspc/data/budget_db/budget.sqlite
            dirname(__DIR__, 3) . '/data/budget_db/budget.sqlite',
            dirname(__DIR__, 4) . '/data/budget_db/budget.sqlite',

            // DOCUMENT_ROOT relative (e.g. public_html/../data/budget_db)
            !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/../data/budget_db/budget.sqlite' : '',
            !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/../../data/budget_db/budget.sqlite' : '',

            // Direct hosting paths for latinspc cPanel
            '/home4/latinspc/data/budget_db/budget.sqlite',
            '/home/latinspc/data/budget_db/budget.sqlite',
        ];

        foreach ($candidates as $cand) {
            if (!empty($cand) && file_exists($cand)) {
                return $cand;
            }
        }

        // Fallback: If not found yet, default to standard data/budget_db location
        $defaultPath = dirname(__DIR__, 2) . '/data/budget_db/budget.sqlite';
        $dir = dirname($defaultPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $defaultPath;
    }

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dbPath = self::getDatabasePath();
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            self::$pdo = new PDO('sqlite:' . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable foreign key support & WAL mode for SQLite
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
            self::$pdo->exec('PRAGMA journal_mode = WAL;');

            self::initSchema();
        }

        return self::$pdo;
    }

    private static function initSchema(): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS months (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            year INTEGER NOT NULL,
            month INTEGER NOT NULL,
            label TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(year, month)
        );

        CREATE TABLE IF NOT EXISTS income_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            source_name TEXT NOT NULL,
            estimated_amount REAL NOT NULL DEFAULT 0.0,
            actual_amount REAL NOT NULL DEFAULT 0.0,
            is_received INTEGER NOT NULL DEFAULT 0,
            is_recurring INTEGER NOT NULL DEFAULT 1,
            received_date TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS monthly_bills (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            due_day INTEGER DEFAULT 1,
            due_date_label TEXT NOT NULL,
            name TEXT NOT NULL,
            category TEXT DEFAULT 'Bill',
            estimated_amount REAL NOT NULL DEFAULT 0.0,
            actual_amount REAL DEFAULT 0.0,
            is_paid INTEGER NOT NULL DEFAULT 0,
            paid_date TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS bill_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bill_id INTEGER NOT NULL REFERENCES monthly_bills(id) ON DELETE CASCADE,
            amount REAL NOT NULL DEFAULT 0.0,
            payment_date TEXT NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS budget_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            estimated_amount REAL NOT NULL DEFAULT 0.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            category_id INTEGER REFERENCES budget_categories(id) ON DELETE SET NULL,
            purchase_date TEXT NOT NULL,
            item TEXT NOT NULL,
            place TEXT DEFAULT NULL,
            amount REAL NOT NULL DEFAULT 0.0,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS gas_fillups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            fill_date TEXT NOT NULL,
            station TEXT NOT NULL,
            fuel_type TEXT DEFAULT 'Regular',
            amount REAL NOT NULL DEFAULT 0.0,
            gallons REAL NOT NULL DEFAULT 0.0,
            price_per_gal REAL NOT NULL DEFAULT 0.0,
            odometer REAL DEFAULT NULL,
            miles_driven REAL DEFAULT NULL,
            mpg REAL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS lapc_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            job_date TEXT NOT NULL,
            client_contact TEXT NOT NULL,
            description TEXT DEFAULT NULL,
            pay_amount REAL NOT NULL DEFAULT 0.0,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS lapc_expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            expense_date TEXT NOT NULL,
            title TEXT NOT NULL,
            place TEXT DEFAULT NULL,
            amount REAL NOT NULL DEFAULT 0.0,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS delivery_tips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            month_id INTEGER NOT NULL REFERENCES months(id) ON DELETE CASCADE,
            work_date TEXT NOT NULL,
            platform TEXT DEFAULT 'Delivery',
            tips_amount REAL NOT NULL DEFAULT 0.0,
            hours REAL NOT NULL DEFAULT 0.0,
            orders_count INTEGER DEFAULT 0,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            display_name TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            username TEXT DEFAULT NULL,
            attempt_count INTEGER DEFAULT 1,
            last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address);
        CREATE INDEX IF NOT EXISTS idx_income_month ON income_items(month_id);
        CREATE INDEX IF NOT EXISTS idx_bills_month ON monthly_bills(month_id);
        CREATE INDEX IF NOT EXISTS idx_cat_month ON budget_categories(month_id);
        CREATE INDEX IF NOT EXISTS idx_purchases_month ON purchases(month_id);
        CREATE INDEX IF NOT EXISTS idx_purchases_cat ON purchases(category_id);
        CREATE INDEX IF NOT EXISTS idx_gas_month ON gas_fillups(month_id);
        CREATE INDEX IF NOT EXISTS idx_lapc_jobs_month ON lapc_jobs(month_id);
        CREATE INDEX IF NOT EXISTS idx_lapc_exp_month ON lapc_expenses(month_id);
        CREATE INDEX IF NOT EXISTS idx_tips_month ON delivery_tips(month_id);
        ";

        self::$pdo->exec($sql);

        // Migrations for existing database
        try {
            self::$pdo->exec("ALTER TABLE income_items ADD COLUMN is_received INTEGER NOT NULL DEFAULT 0");
        } catch (PDOException $e) {}

        try {
            self::$pdo->exec("ALTER TABLE income_items ADD COLUMN is_recurring INTEGER NOT NULL DEFAULT 1");
        } catch (PDOException $e) {}
    }
}
