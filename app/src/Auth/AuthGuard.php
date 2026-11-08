<?php
// src/Auth/AuthGuard.php - Access Control Guard
require_once __DIR__ . '/Auth.php';

class AuthGuard {
    public static function enforce(): void {
        if (php_sapi_name() === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If no user exists yet in the database, redirect to login for initial setup
        if (!Auth::hasUsers()) {
            if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'login.php') {
                header("Location: login.php?setup=1");
                exit;
            }
            return;
        }

        // Authenticated check
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            return;
        }

        // Handle API/AJAX requests
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false);

        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit;
        }

        $currentUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header("Location: login.php?return_url=" . urlencode($currentUri));
        exit;
    }
}
