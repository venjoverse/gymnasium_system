<?php
require 'config.php';

staff_only();

$error = "";

if (isset($_POST['save'])) {

    $eventName = trim($_POST['event_name']);
    $description = trim($_POST['description']);
    $eventDate = $_POST['event_date'];
    $venue = trim($_POST['venue']);
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    if ($endTime <= $startTime) {

        $error = "End time must be after start time.";

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO Events
            (EventName, EventDescription, EventDate,
             Venue, StartTime, EndTime, EventStatus)
            VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')"
        );

        $stmt->execute([
            $eventName,
            $description,
            $eventDate,
            $venue,
            $startTime,
            $endTime
        ]);

        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="topbar">

    <a href="dashboard.php" class="brand">
        <span class="logo small">GE</span>
        Gymnasium Events
    </a>

    <a href="dashboard.php" class="outline-btn">
        Back
    </a>

</header>

<main class="container form-container">

    <div class="form-panel">

        <p class="eyebrow">EVENT MANAGEMENT</p>

        <h1>Create Event</h1>

        <p class="muted">
            Add a new gymnasium event.
        </p>

        <?php if ($error): ?>

            <div class="alert">
                <?= h($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" class="form-grid">

            <div class="full">

                <label>Event Name</label>

                <input
                    type="text"
                    name="event_name"
                    maxlength="150"
                    required
                >

            </div>

            <div class="full">

                <label>Description</label>

                <textarea
                    name="description"
                    maxlength="500"
                    rows="3"
                ></textarea>

            </div>

            <div>

                <label>Event Date</label>

                <input
                    type="date"
                    name="event_date"
                    required
                >

            </div>

            <div>

                <label>Venue</label>

                <input
                    type="text"
                    name="venue"
                    value="School Gymnasium"
                >

            </div>

            <div>

                <label>Start Time</label>

                <input
                    type="time"
                    name="start_time"
                    required
                >

            </div>

            <div>

                <label>End Time</label>

                <input
                    type="time"
                    name="end_time"
                    required
                >

            </div>

            <div class="full">

                <button type="submit" name="save">
                    Save Event
                </button>

            </div>

        </form>

    </div>

</main>

</body>
</html>