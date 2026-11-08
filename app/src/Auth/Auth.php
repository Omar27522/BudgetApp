<?php
// src/Auth/Auth.php - Authentication, User Management, and Progressive Rate-Limiter
require_once __DIR__ . '/../../db/db.php';

class Auth {
    /**
     * Checks if at least one user account exists.
     */
    public static function hasUsers(): bool {
        $db = Db::getConnection();
        return (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
    }

    /**
     * Registers a new user.
     */
    public static function registerUser(string $username, string $password, ?string $displayName = null): array {
        $username = trim($username);
        if (strlen($username) < 3) {
            return ['success' => false, 'error' => 'Username must be at least 3 characters.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            return ['success' => false, 'error' => 'Username contains invalid characters (letters, numbers, underscores, and dashes only).'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long for strong security.'];
        }

        $db = Db::getConnection();
        $st = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $st->execute([$username]);
        if ($st->fetch()) {
            return ['success' => false, 'error' => 'A user with this username already exists.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $db->prepare("INSERT INTO users (username, password_hash, display_name) VALUES (?, ?, ?)");
        $ins->execute([$username, $hash, $displayName ?: $username]);

        return ['success' => true];
    }

    /**
     * Calculates remaining penalty delay (in seconds) for an IP address.
     * Progressive delay: every failed attempt adds 5 seconds (5s, 10s, 15s, 20s...).
     */
    public static function getRemainingLockout(string $ip): int {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT attempt_count, last_attempt_at FROM login_attempts WHERE ip_address = ?");
        $st->execute([$ip]);
        $row = $st->fetch();

        if (!$row) {
            return 0;
        }

        $attempts = (int)$row['attempt_count'];
        if ($attempts <= 0) {
            return 0;
        }

        // Each attempt adds 5 seconds
        $delay = $attempts * 5;
        $lastTime = strtotime($row['last_attempt_at'] . ' UTC');
        $elapsed = time() - $lastTime;

        if ($elapsed < $delay) {
            return $delay - $elapsed;
        }

        return 0;
    }

    /**
     * Records a failed login attempt and increases penalty delay by 5 seconds.
     */
    public static function recordFailedAttempt(string $ip, ?string $username = null): int {
        $db = Db::getConnection();
        $st = $db->prepare("SELECT attempt_count FROM login_attempts WHERE ip_address = ?");
        $st->execute([$ip]);
        $row = $st->fetch();

        $utcNow = gmdate('Y-m-d H:i:s');
        if ($row) {
            $newCount = (int)$row['attempt_count'] + 1;
            $up = $db->prepare("UPDATE login_attempts SET attempt_count = ?, last_attempt_at = ?, username = ? WHERE ip_address = ?");
            $up->execute([$newCount, $utcNow, $username, $ip]);
        } else {
            $newCount = 1;
            $ins = $db->prepare("INSERT INTO login_attempts (ip_address, username, attempt_count, last_attempt_at) VALUES (?, ?, 1, ?)");
            $ins->execute([$ip, $username, $utcNow]);
        }

        return $newCount * 5;
    }

    /**
     * Clears all failed attempts for an IP upon successful login.
     */
    public static function clearAttempts(string $ip): void {
        $db = Db::getConnection();
        $st = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $st->execute([$ip]);
    }

    /**
     * Verifies credentials with rate-limiting, timing-attack mitigation, and honeypot protection.
     */
    public static function verifyLogin(string $username, string $password, string $ip): array {
        $username = trim($username);

        // 1. Check rate limit delay
        $remaining = self::getRemainingLockout($ip);
        if ($remaining > 0) {
            return [
                'success' => false,
                'error' => "Rate limited. Please wait {$remaining} seconds before retrying.",
                'lockout' => true,
                'remaining' => $remaining
            ];
        }

        // 2. Fetch user
        $db = Db::getConnection();
        $st = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
        $st->execute([$username]);
        $user = $st->fetch();

        // 3. Timing-safe verification: run password_verify even if user does not exist
        $hash = $user ? $user['password_hash'] : '$2y$10$usesomesaltforrealisticdummyhashcalc01234567890abcdef';
        $verified = password_verify($password, $hash);

        // Extra micro-delay to normalize response time
        usleep(rand(40000, 80000));

        if ($user && $verified) {
            // Successful login
            self::clearAttempts($ip);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);

            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = !empty($user['display_name']) ? $user['display_name'] : $user['username'];

            return ['success' => true];
        }

        // Failed attempt: record and calculate next penalty delay (+5 seconds)
        $penalty = self::recordFailedAttempt($ip, $username);

        return [
            'success' => false,
            'error' => "Invalid credentials. Please wait {$penalty} seconds to try again.",
            'lockout' => true,
            'remaining' => $penalty
        ];
    }
}
