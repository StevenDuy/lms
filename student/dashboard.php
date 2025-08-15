<?php
require_once '../config/config.php';
requireRole('student');

$user = getCurrentUser();

// Get available courses (not enrolled)
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'assignment') as assignment_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'exam') as exam_count
    FROM courses c 
    JOIN users u ON c.lecturer_id = u.id 
    WHERE c.status = 'active' 
    AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
    ORDER BY c.created_at DESC
");
$stmt->execute([$user['id']]);
$available_courses = $stmt->fetchAll();

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

if ($search) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as lecturer_name,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
               (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'assignment') as assignment_count,
               (SELECT COUNT(*) FROM materials WHERE course_id = c.id AND material_type = 'exam') as exam_count
        FROM courses c 
        JOIN users u ON c.lecturer_id = u.id 
        WHERE c.status = 'active' 
        AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
        AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)
        ORDER BY " . ($sort === 'oldest' ? 'c.created_at ASC' : 'c.created_at DESC')
    );
    $searchTerm = "%$search%";
    $stmt->execute([$user['id'], $searchTerm, $searchTerm, $searchTerm]);
    $available_courses = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .course-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .navbar-brand {
            font-weight: bold;
        }
        .course-stats {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>LMS - Student
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_courses.php">
                            <i class="fas fa-user-graduate me-1"></i>My Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">
                            <i class="fas fa-chart-bar me-1"></i>Grades
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-md-8">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="sort" class="form-select me-2" style="width: auto;">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-refresh me-1"></i>Reset
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-book me-2 text-primary"></i>Available Courses</h2>
            <span class="badge bg-info"><?php echo count($available_courses); ?> courses available</span>
        </div>

        <!-- Courses Grid -->
        <?php if (empty($available_courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No courses available</h4>
                <p class="text-muted">
                    <?php echo $search ? 'No courses match your search criteria.' : 'All available courses have been enrolled.'; ?>
                </p>
                <?php if ($search): ?>
                    <a href="dashboard.php" class="btn btn-primary">View All Courses</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($available_courses as $course): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card course-card h-100">
                            <div class="card-body">
                                <h5 class="card-title text-primary"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>

                                <div class="course-stats mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="fas fa-user me-1"></i><?php echo $course['student_count']; ?> students</span>
                                        <span><i class="fas fa-book me-1"></i><?php echo $course['material_count']; ?> lessons</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span><i class="fas fa-tasks me-1"></i><?php echo $course['assignment_count']; ?> assignments</span>
                                        <span><i class="fas fa-clipboard-check me-1"></i><?php echo $course['exam_count']; ?> exams</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">by <?php echo htmlspecialchars($course['lecturer_name']); ?></small>
                                    <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>