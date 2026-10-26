<?php
require 'config.php';

$error = "";
$success = "";

if (isset($_POST['register'])) {

    $studentNumber = trim($_POST['student_number']);
    $fullName = trim($_POST['full_name']);
    $course = trim($_POST['course']);
    $yearLevel = (int)$_POST['year_level'];
    $contactNumber = trim($_POST['contact_number']);
    $studentPassword = $_POST['studentpassword'];

    if (
        $studentNumber === "" ||
        $fullName === "" ||
        $course === "" ||
        $yearLevel < 1 ||
        $yearLevel > 4 ||
        $contactNumber === "" ||
        $studentPassword === ""
    ) {
        $error = "Please complete all required fields.";
    } else {

        try {

            $check = $pdo->prepare(
                "SELECT AttendeeID FROM Attendees WHERE StudentNumber = ?"
            );

            $check->execute([$studentNumber]);

            if ($check->fetch()) {

                $error = "Student number already exists.";

            } else {

                $hashedPassword = password_hash(
                    $studentPassword,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO Attendees
                    (StudentNumber, FullName, Course, YearLevel, ContactNumber, StudentPassword)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $studentNumber,
                    $fullName,
                    $course,
                    $yearLevel,
                    $contactNumber,
                    $hashedPassword
                ]);

                $success = "Registration successful. You can now log in.";
            }

        } catch (PDOException $e) {

            $error = "Registration failed. Please check your database settings.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<div class="auth-card">

    <div class="logo">GE</div>

    <h1>Create Account</h1>
    <p class="muted">Register for gymnasium event attendance.</p>

    <?php if ($error): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Student Number</label>
        <input type="text" name="student_number" required>

        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Course</label>
        <input type="text" name="course" required>

        <label>Year Level</label>
        <select name="year_level" required>
            <option value="">Select year</option>
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3">3rd Year</option>
            <option value="4">4th Year</option>
        </select>

        <label>Contact Number</label>
        <input type="text" name="contact_number" required>

        <label>Password</label>
        <input type="password" name="studentpassword" required>

        <button type="submit" name="register">
            Register
        </button>

    </form>

    <a href="index.php" class="text-link">
        Already registered? Sign in
    </a>

</div>

</body>
</html>