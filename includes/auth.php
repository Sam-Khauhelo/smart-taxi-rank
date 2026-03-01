<?php
// includes/auth.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return boolean
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

/**
 * Check if user has specific role
 * @param string|array $roles Allowed role(s)
 * @return boolean
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return $_SESSION['role'] === $roles;
}

/**
 * Require specific role(s) to access page
 * @param string|array $roles Allowed role(s)
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        // Unauthorized - redirect to appropriate dashboard or error page
        switch($_SESSION['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'marshal':
                header('Location: ../marshal/dashboard.php');
                break;
            case 'owner':
                header('Location: ../owner/dashboard.php');
                break;
            case 'driver':
                header('Location: ../driver/portal.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();
    }
}

/**
 * Get current user's data
 * @param PDO $pdo Database connection
 * @return array|false User data or false if not logged in
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is admin
 * @return boolean
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is marshal
 * @return boolean
 */
function isMarshal() {
    return hasRole('marshal');
}

/**
 * Check if user is owner
 * @return boolean
 */
function isOwner() {
    return hasRole('owner');
}

/**
 * Check if user is driver
 * @return boolean
 */
function isDriver() {
    return hasRole('driver');
}

/**
 * Redirect to appropriate dashboard based on role
 */
function redirectToDashboard() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
    
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        case 'marshal':
            header('Location: ../marshal/dashboard.php');
            break;
        case 'owner':
            header('Location: ../owner/dashboard.php');
            break;
        case 'driver':
            header('Location: ../driver/portal.php');
            break;
        default:
            header('Location: ../index.php');
    }
    exit();
}

/**
 * Log out user
 */
function logout() {
    $_SESSION = array();
    session_destroy();
    header('Location: ../index.php');
    exit();
}