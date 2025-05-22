<?php
session_start();
$conn = new mysqli("localhost", "root", "", "role_task_tracker");

// Auth Helpers
function isLoggedIn() {
    return isset($_SESSION['user']);
}
function isCreator($task, $userId) {
    return $task['creator_id'] == $userId;
}

// Signup
if (isset($_POST['signup'])) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $_POST['name'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role']);
    $stmt->execute();
    echo "<div class='alert alert-success'>Signup successful. Please log in.</div>";
}

// Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    $user = $res->fetch_assoc();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user'] = $user;
    } else {
        echo "<div class='alert alert-danger'>Invalid login credentials.</div>";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Create/Update Task
if (isset($_POST['create_task'])) {
    if (!empty($_POST['task_id'])) {
        $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, assignee_id=? WHERE id=? AND creator_id=?");
        $stmt->bind_param("ssiii", $_POST['title'], $_POST['description'], $_POST['assignee_id'], $_POST['task_id'], $_SESSION['user']['id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO tasks (title, description, assignee_id, creator_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $_POST['title'], $_POST['description'], $_POST['assignee_id'], $_SESSION['user']['id']);
        $stmt->execute();
    }
}

// Update Status
if (isset($_POST['update_status'])) {
    $stmt = $conn->prepare("UPDATE tasks SET status=? WHERE id=?");
    $stmt->bind_param("si", $_POST['status'], $_POST['task_id']);
    $stmt->execute();
}

// Delete Task
if (isset($_GET['delete_task'])) {
    $task_id = $_GET['delete_task'];
    $task = $conn->query("SELECT * FROM tasks WHERE id=$task_id")->fetch_assoc();
    if (isCreator($task, $_SESSION['user']['id'])) {
        $conn->query("DELETE FROM tasks WHERE id=$task_id");
    }
}

// Fetch task to edit
$editTask = null;
if (isset($_GET['edit_task'])) {
    $editTask = $conn->query("SELECT * FROM tasks WHERE id=" . intval($_GET['edit_task']))->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 40px;
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center mb-4">Role-Based Task Tracker</h2>

    <?php if (!isLoggedIn()): ?>
        <div class="row">
            <div class="col-md-6">
                <h4>Signup</h4>
                <form method="POST" class="card p-3">
                    <input name="name" class="form-control mb-2" placeholder="Name" required>
                    <input name="email" class="form-control mb-2" placeholder="Email" required>
                    <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
                    <select name="role" class="form-select mb-2">
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button name="signup" class="btn btn-primary">Signup</button>
                </form>
            </div>

            <div class="col-md-6">
                <h4>Login</h4>
                <form method="POST" class="card p-3">
                    <input name="email" class="form-control mb-2" placeholder="Email" required>
                    <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
                    <button name="login" class="btn btn-success">Login</button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Welcome, <?= $_SESSION['user']['name'] ?> (<?= $_SESSION['user']['role'] ?>)</h4>
            <a href="?logout" class="btn btn-danger">Logout</a>
        </div>

        <div class="card p-3 mb-4">
            <h5><?= $editTask ? "Edit Task" : "Create Task" ?></h5>
            <form method="POST">
                <input name="title" class="form-control mb-2" placeholder="Title" value="<?= $editTask['title'] ?? '' ?>" required>
                <textarea name="description" class="form-control mb-2" placeholder="Description"><?= $editTask['description'] ?? '' ?></textarea>
                <select name="assignee_id" class="form-select mb-2">
                    <?php
                    $uid = $_SESSION['user']['id'];
                    $users = $conn->query("SELECT * FROM users WHERE id != $uid");
                    while ($u = $users->fetch_assoc()) {
                        $selected = isset($editTask) && $editTask['assignee_id'] == $u['id'] ? "selected" : "";
                        echo "<option value='{$u['id']}' $selected>{$u['name']} ({$u['role']})</option>";
                    }
                    ?>
                </select>
                <?php if ($editTask): ?>
                    <input type="hidden" name="task_id" value="<?= $editTask['id'] ?>">
                <?php endif; ?>
                <button name="create_task" class="btn btn-<?= $editTask ? "warning" : "primary" ?>">
                    <?= $editTask ? "Update Task" : "Create Task" ?>
                </button>
            </form>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5>Tasks Assigned to Me</h5>
                <?php
                $tasks = $conn->query("SELECT t.*, u.name as creator_name FROM tasks t JOIN users u ON t.creator_id = u.id WHERE t.assignee_id=$uid");
                while ($t = $tasks->fetch_assoc()): ?>
                    <div class="card p-3">
                        <h6><?= $t['title'] ?></h6>
                        <p><?= $t['description'] ?></p>
                        <small class="text-muted">Created by: <?= $t['creator_name'] ?></small>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <select name="status" class="form-select mb-2">
                                <?php foreach (['Pending', 'In Progress', 'Done'] as $status): ?>
                                    <option <?= $t['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button name="update_status" class="btn btn-sm btn-secondary">Update Status</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="col-md-6">
                <h5>Tasks I Assigned</h5>
                <?php
                $tasks = $conn->query("SELECT t.*, u.name as assignee_name FROM tasks t JOIN users u ON t.assignee_id = u.id WHERE t.creator_id=$uid");
                while ($t = $tasks->fetch_assoc()): ?>
                    <div class="card p-3">
                        <h6><?= $t['title'] ?></h6>
                        <p><?= $t['description'] ?></p>
                        <p><small>Assigned to: <?= $t['assignee_name'] ?></small></p>
                        <p>Status: <b><?= $t['status'] ?></b></p>
                        <a href="?edit_task=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <a href="?delete_task=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
