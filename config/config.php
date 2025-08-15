<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'lms_db');

// Application Configuration
define('APP_URL', 'http://localhost/lms');
define('UPLOAD_PATH', 'uploads/');

// Session Configuration
session_start();

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper Functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        redirect('login.php');
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function calculateProgress($studentId, $courseId) {
    global $pdo;

    // Get total materials in course
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM materials WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $total = $stmt->fetch()['total'];

    if ($total == 0) return 0;

    // Get completed materials (those with test results)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.id) as completed 
        FROM materials m 
        LEFT JOIN test_results tr ON m.id = tr.material_id AND tr.student_id = ?
        WHERE m.course_id = ? AND tr.id IS NOT NULL
    ");
    $stmt->execute([$studentId, $courseId]);
    $completed = $stmt->fetch()['completed'];

    return round(($completed / $total) * 100, 2);
}
?>