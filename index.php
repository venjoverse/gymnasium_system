
<?php
require_once __DIR__ . '/config.php';

$error = "";
$success = "";

$isLocal = in_array(
    $_SERVER['REMOTE_ADDR'] ?? '',
    ['127.0.0.1', '::1'],
    true
);

$resetMode = isset($_GET['reset_admin']) && $_GET['reset_admin'] === '1';

if ($resetMode && !$isLocal) {
    http_response_code(403);
    exit('Admin password reset is only available on this computer.');
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = "Admin password updated. You can now sign in.";
}

if ($resetMode && isset($_POST['reset_admin_submit'])) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "SELECT UserID FROM admin_table WHERE Username = ? LIMIT 1"
            );
            $stmt->execute(['admin']);
            $admin = $stmt->fetch();

            if ($admin) {
                $stmt = $pdo->prepare(
                    "UPDATE admin_table
                     SET PasswordHash = ?,
                         Role = 'Admin',
                         AccountStatus = 'Active'
                     WHERE Username = 'admin'"
                );
                $stmt->execute([$passwordHash]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO admin_table
                     (FullName, Username, PasswordHash, Role, AccountStatus)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    'System Administrator',
                    'admin',
                    $passwordHash,
                    'Admin',
                    'Active'
                ]);
            }

            header("Location: index.php?reset=success");
            exit;

        } catch (PDOException $e) {
            $error = "Could not reset the Admin password. Check your database.";
        }
    }
}

if (!$resetMode && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Please enter your username or student number and password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM admin_table
                 WHERE Username = ?
                 AND AccountStatus = 'Active'
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (
                $admin &&
                password_verify($password, $admin['PasswordHash'])
            ) {
                session_regenerate_id(true);
                $_SESSION['user'] = $admin;

                header("Location: dashboard.php");
                exit;
            }

            $stmt = $pdo->prepare(
                "SELECT *
                 FROM Attendees
                 WHERE StudentNumber = ?
                 LIMIT 1"
            );
            $stmt->execute([$username]);
            $student = $stmt->fetch();

            if (
                $student &&
                !empty($student['StudentPassword']) &&
                password_verify($password, $student['StudentPassword'])
            ) {
                session_regenerate_id(true);

                $_SESSION['attendee_id'] = $student['AttendeeID'];
                $_SESSION['role'] = 'Student';
                $_SESSION['student_name'] = $student['FullName'];

                header("Location: dashboard.php");
                exit;
            }

            $error = "Invalid username, student number, or password.";

        } catch (PDOException $e) {
            $error = "Login failed. Check your database connection and tables.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gymnasium Events</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<div class="auth-card">

    <div class="logo">GE</div>

    <?php if ($resetMode): ?>

        <h1>Reset Admin Password</h1>
        <p class="muted">Set a new password for the admin account.</p>

        <?php if ($error !== ''): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="?reset_admin=1">
            <label>New Password</label>
            <input
                type="password"
                name="new_password"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label>Confirm Password</label>
            <input
                type="password"
                name="confirm_password"
                minlength="8"
                autocomplete="new-password"
                required
            >
        </form>

        <p><a href="index.php" class="text-link">Back to Login</a></p>

    <?php else: ?>

        <h1>Gymnasium Events</h1>
        <p class="muted">Event attendance management system</p>

        <?php if ($error !== ''): ?>
            <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <label>Username or Student Number</label>
            <input
                type="text"
                name="username"
                autocomplete="username"
                required
            >

            <label>Password</label>
            <input
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit" name="login" value="1">
                Sign In
            </button>
        </form>

        <p class="muted">Don't have a student account?</p>

        <a href="register.php" class="text-link">Register here</a>

    <?php endif; ?>

</div>

</body>
</html>
