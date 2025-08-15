<?php
require_once '../config/config.php';
requireRole('admin');

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    redirect('dashboard.php');
}

// Check if course exists
$stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('dashboard.php');
}

// Check if course has enrolled students
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?");
$stmt->execute([$course_id]);
$has_students = $stmt->fetch()['count'] > 0;

if ($has_students) {
    // Cannot delete course with enrolled students
    $_SESSION['error'] = 'Cannot delete course "' . $course['title'] . '" because it has enrolled students.';
    redirect('dashboard.php');
}

try {
    // Delete course (cascade will handle related records)
    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
    if ($stmt->execute([$course_id])) {
        $_SESSION['success'] = 'Course "' . $course['title'] . '" has been deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete the course. Please try again.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error deleting course: ' . $e->getMessage();
}

redirect('dashboard.php');
?>