<?php
require_once '../config/config.php';
requireRole('admin');

$user = getCurrentUser();

// Get all courses with statistics
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as lecturer_name,
           (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
    FROM courses c 
    JOIN users u ON c.lecturer_id = u.id 
    ORDER BY c.created_at DESC
");
$stmt->execute();
$courses = $stmt->fetchAll();

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

if ($search || $filter !== 'all') {
    $sql = "
        SELECT c.*, u.full_name as lecturer_name,
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
        FROM courses c 
        JOIN users u ON c.lecturer_id = u.id 
        WHERE 1=1
    ";
    $params = [];

    if ($search) {
        $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }

    if ($filter !== 'all') {
        $sql .= " AND c.status = ?";
        $params[] = $filter;
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
}

// Get system statistics
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
$stats['total_courses'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'lecturer'");
$stats['total_lecturers'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollments");
$stats['total_enrollments'] = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Courses - Admin Dashboard</title>
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-shield me-2"></i>LMS - Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>All Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Users
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
        <!-- System Statistics -->
        <div class="stats-card">
            <div class="row text-center">
                <div class="col-md-3">
                    <i class="fas fa-book fa-2x mb-2"></i>
                    <h3><?php echo $stats['total_courses']; ?></h3>
                    <p class="mb-0">Total Courses</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-user-graduate fa-2x mb-2"></i>
                    <h3><?php echo $stats['total_students']; ?></h3>
                    <p class="mb-0">Students</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                    <h3><?php echo $stats['total_lecturers']; ?></h3>
                    <p class="mb-0">Lecturers</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h3><?php echo $stats['total_enrollments']; ?></h3>
                    <p class="mb-0">Enrollments</p>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-md-8">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" placeholder="Search courses, lecturers..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="filter" class="form-select me-2" style="width: auto;">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <a href="create_course.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Add Course
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-refresh me-1"></i>Reset
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-book me-2 text-primary"></i>All Courses</h2>
            <span class="badge bg-info"><?php echo count($courses); ?> courses</span>
        </div>

        <!-- Courses Grid -->
        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Courses Found</h4>
                <p class="text-muted">
                    <?php echo $search || $filter !== 'all' ? 'No courses match your search criteria.' : 'No courses have been created yet.'; ?>
                </p>
                <?php if ($search || $filter !== 'all'): ?>
                    <a href="dashboard.php" class="btn btn-primary">View All Courses</a>
                <?php else: ?>
                    <a href="create_course.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Create First Course
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($courses as $course): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card course-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </div>

                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...</p>

                                <div class="course-stats mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="fas fa-users me-1"></i><?php echo $course['student_count']; ?> students</span>
                                        <span><i class="fas fa-book me-1"></i><?php echo $course['material_count']; ?> materials</span>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Created <?php echo formatDate($course['created_at']); ?></small>
                                    <div class="btn-group">
                                        <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_course.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($course['student_count'] == 0): ?>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCourse(courseId) {
            if (confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
                window.location.href = 'delete_course.php?id=' + courseId;
            }
        }
    </script>
</body>
</html>