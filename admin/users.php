<?php
require_once '../config/config.php';
requireRole('admin');

$user = getCurrentUser();
$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");

                if ($stmt->execute([$username, $email, md5($password), $full_name, $role])) {
                    $message = 'User added successfully!';
                } else {
                    $error = 'Failed to add user. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];

        // Don't allow deleting self
        if ($user_id === $user['id']) {
            $error = 'You cannot delete your own account';
        } else {
            // Delete user and related data
            $pdo->beginTransaction();
            try {
                // Delete student data
                $pdo->prepare("DELETE FROM student_answers WHERE student_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM test_results WHERE student_id = ?")->execute([$user_id]);
                $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$user_id]);

                // Delete lecturer data
                $pdo->prepare("DELETE FROM student_answers WHERE question_id IN (SELECT q.id FROM questions q JOIN materials m ON q.material_id = m.id JOIN courses c ON m.course_id = c.id WHERE c.lecturer_id = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM test_results WHERE material_id IN (SELECT m.id FROM materials m JOIN courses c ON m.course_id = c.id WHERE c.lecturer_id = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM questions WHERE material_id IN (SELECT m.id FROM materials m JOIN courses c ON m.course_id = c.id WHERE c.lecturer_id = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM materials WHERE course_id IN (SELECT id FROM courses WHERE lecturer_id = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM enrollments WHERE course_id IN (SELECT id FROM courses WHERE lecturer_id = ?)")->execute([$user_id]);
                $pdo->prepare("DELETE FROM courses WHERE lecturer_id = ?")->execute([$user_id]);

                // Delete user
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

                $pdo->commit();
                $message = 'User deleted successfully!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to delete user. Please try again.';
            }
        }
    }
}

// Get all users with statistics
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$sql = "
    SELECT u.*, 
           CASE 
               WHEN u.role = 'student' THEN (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id)
               WHEN u.role = 'lecturer' THEN (SELECT COUNT(*) FROM courses WHERE lecturer_id = u.id)
               ELSE 0
           END as activity_count
    FROM users u 
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($filter !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get system statistics
$stats = [];
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['students'] = $role_counts['student'] ?? 0;
$stats['lecturers'] = $role_counts['lecturer'] ?? 0;
$stats['admins'] = $role_counts['admin'] ?? 0;
$stats['total'] = array_sum($role_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .user-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .role-badge {
            font-size: 0.8rem;
        }
        .role-student { background-color: #007bff; }
        .role-lecturer { background-color: #28a745; }
        .role-admin { background-color: #dc3545; }
        .form-card {
            border: none;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-book me-1"></i>All Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">
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
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="mb-0">Total Users</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-user-graduate fa-2x mb-2"></i>
                    <h3><?php echo $stats['students']; ?></h3>
                    <p class="mb-0">Students</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                    <h3><?php echo $stats['lecturers']; ?></h3>
                    <p class="mb-0">Lecturers</p>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-user-shield fa-2x mb-2"></i>
                    <h3><?php echo $stats['admins']; ?></h3>
                    <p class="mb-0">Administrators</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-md-8">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="filter" class="form-select me-2" style="width: auto;">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="student" <?php echo $filter === 'student' ? 'selected' : ''; ?>>Students</option>
                        <option value="lecturer" <?php echo $filter === 'lecturer' ? 'selected' : ''; ?>>Lecturers</option>
                        <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Add User
                </button>
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-refresh me-1"></i>Reset
                </a>
            </div>
        </div>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2 text-primary"></i>Users Management</h2>
            <span class="badge bg-info"><?php echo count($users); ?> users</span>
        </div>

        <!-- Users Table -->
        <div class="user-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No users found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                                <br><small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge role-<?php echo $u['role']; ?> role-badge">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'student'): ?>
                                            <i class="fas fa-book me-1"></i><?php echo $u['activity_count']; ?> courses
                                        <?php elseif ($u['role'] === 'lecturer'): ?>
                                            <i class="fas fa-chalkboard-teacher me-1"></i><?php echo $u['activity_count']; ?> courses
                                        <?php else: ?>
                                            <i class="fas fa-user-shield me-1"></i>Administrator
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo formatDate($u['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($u['id'] !== $user['id']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="student">Student</option>
                                <option value="lecturer">Lecturer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>