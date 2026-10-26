<?php
require_once __DIR__ . '/config.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$isStaff = isset($_SESSION['user'])
    && isset($_SESSION['user']['Role'])
    && in_array($_SESSION['user']['Role'], ['Admin', 'Staff'], true);
$isStudent = isset($_SESSION['attendee_id']);

if (!$isStaff && !$isStudent) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$allowedPages = $isStaff ? ['dashboard', 'events', 'students', 'attendance'] : ['events', 'my_attendance'];
$page = $_GET['page'] ?? ($isStaff ? 'dashboard' : 'events');
if (!in_array($page, $allowedPages, true)) {
    $page = $isStaff ? 'dashboard' : 'events';
}

$flash = $_SESSION['dashboard_flash'] ?? '';
unset($_SESSION['dashboard_flash']);
$error = '';

function dashboard_redirect($page, $message) {
    $_SESSION['dashboard_flash'] = $message;
    header('Location: dashboard.php?page=' . urlencode($page));
    exit;
}

function require_staff_action($isStaff) {
    if (!$isStaff) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(403);
        exit('Your session token is invalid. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if (in_array($action, ['create_event', 'update_event', 'delete_event', 'update_student', 'delete_student'], true)) {
            require_staff_action($isStaff);
        }

        if ($action === 'create_event') {
            $eventName = trim($_POST['event_name'] ?? '');
            $description = trim($_POST['event_description'] ?? '');
            $eventDate = $_POST['event_date'] ?? '';
            $venue = trim($_POST['venue'] ?? 'Gymnasium');
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';

            if ($eventName === '' || $eventDate === '' || $startTime === '' || $endTime === '') {
                dashboard_redirect('events', 'Please complete all required event fields.');
            }
            if ($endTime <= $startTime) {
                dashboard_redirect('events', 'The event end time must be later than the start time.');
            }

            $stmt = $pdo->prepare('INSERT INTO Events (EventName, EventDescription, EventDate, Venue, StartTime, EndTime, EventStatus) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$eventName, $description, $eventDate, $venue !== '' ? $venue : 'Gymnasium', $startTime, $endTime, 'Scheduled']);
            dashboard_redirect('events', 'Event created successfully.');
        }

        if ($action === 'update_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $eventName = trim($_POST['event_name'] ?? '');
            $description = trim($_POST['event_description'] ?? '');
            $eventDate = $_POST['event_date'] ?? '';
            $venue = trim($_POST['venue'] ?? 'Gymnasium');
            $startTime = $_POST['start_time'] ?? '';
            $endTime = $_POST['end_time'] ?? '';
            $status = $_POST['event_status'] ?? 'Scheduled';

            if ($eventId < 1 || $eventName === '' || $eventDate === '' || $startTime === '' || $endTime === '') {
                dashboard_redirect('events', 'Please complete all required event fields.');
            }
            if ($endTime <= $startTime) {
                dashboard_redirect('events', 'The event end time must be later than the start time.');
            }
            if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) {
                $status = 'Scheduled';
            }

            $stmt = $pdo->prepare('UPDATE Events SET EventName = ?, EventDescription = ?, EventDate = ?, Venue = ?, StartTime = ?, EndTime = ?, EventStatus = ? WHERE EventID = ?');
            $stmt->execute([$eventName, $description, $eventDate, $venue !== '' ? $venue : 'Gymnasium', $startTime, $endTime, $status, $eventId]);
            dashboard_redirect('events', $stmt->rowCount() ? 'Event updated successfully.' : 'No event details changed.');
        }

        if ($action === 'delete_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId < 1) {
                dashboard_redirect('events', 'Invalid event selected.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM Attendance WHERE EventID = ?');
            $stmt->execute([$eventId]);
            $stmt = $pdo->prepare('DELETE FROM Events WHERE EventID = ?');
            $stmt->execute([$eventId]);
            $deleted = $stmt->rowCount();
            $pdo->commit();
            dashboard_redirect('events', $deleted ? 'Event and its attendance records were deleted.' : 'Event was not found.');
        }

        if ($action === 'update_student') {
            $attendeeId = (int)($_POST['attendee_id'] ?? 0);
            $studentNumber = trim($_POST['student_number'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $course = trim($_POST['course'] ?? '');
            $yearLevelInput = trim((string)($_POST['year_level'] ?? ''));
            $contactNumber = trim($_POST['contact_number'] ?? '');

            if ($attendeeId < 1 || $studentNumber === '' || $fullName === '') {
                dashboard_redirect('students', 'Student number and full name are required.');
            }
            $yearLevel = $yearLevelInput === '' ? null : filter_var($yearLevelInput, FILTER_VALIDATE_INT);
            if ($yearLevelInput !== '' && ($yearLevel === false || $yearLevel < 1 || $yearLevel > 8)) {
                dashboard_redirect('students', 'Enter a valid year level.');
            }

            $stmt = $pdo->prepare('UPDATE Attendees SET StudentNumber = ?, FullName = ?, Course = ?, YearLevel = ?, ContactNumber = ? WHERE AttendeeID = ?');
            $stmt->execute([$studentNumber, $fullName, $course !== '' ? $course : null, $yearLevel, $contactNumber !== '' ? $contactNumber : null, $attendeeId]);
            dashboard_redirect('students', $stmt->rowCount() ? 'Student details updated successfully.' : 'No student details changed.');
        }

        if ($action === 'delete_student') {
            $attendeeId = (int)($_POST['attendee_id'] ?? 0);
            if ($attendeeId < 1) {
                dashboard_redirect('students', 'Invalid student selected.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM Attendance WHERE AttendeeID = ?');
            $stmt->execute([$attendeeId]);
            $stmt = $pdo->prepare('DELETE FROM Attendees WHERE AttendeeID = ?');
            $stmt->execute([$attendeeId]);
            $deleted = $stmt->rowCount();
            $pdo->commit();
            dashboard_redirect('students', $deleted ? 'Student and their attendance records were deleted.' : 'Student was not found.');
        }

        if ($action === 'checkin' && $isStudent) {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $attendeeId = (int)$_SESSION['attendee_id'];
            $stmt = $pdo->prepare("SELECT EventID FROM Events WHERE EventID = ? AND EventStatus = 'Scheduled' AND EventDate = CURDATE()");
            $stmt->execute([$eventId]);
            if (!$stmt->fetch()) {
                dashboard_redirect('events', 'You can only check in to a scheduled event taking place today.');
            }
            $stmt = $pdo->prepare("INSERT INTO Attendance (AttendeeID, EventID, TimeIn, AttendanceStatus) VALUES (?, ?, NOW(), 'Present')");
            $stmt->execute([$attendeeId, $eventId]);
            dashboard_redirect('events', 'Checked in successfully.');
        }

        if ($action === 'checkout' && $isStudent) {
            $attendanceId = (int)($_POST['attendance_id'] ?? 0);
            $returnPage = $_POST['return_page'] ?? 'my_attendance';
            if (!in_array($returnPage, ['events', 'my_attendance'], true)) {
                $returnPage = 'my_attendance';
            }

            $stmt = $pdo->prepare(
                'UPDATE Attendance SET TimeOut = NOW()
                 WHERE AttendanceID = ? AND AttendeeID = ? AND TimeOut IS NULL'
            );
            $stmt->execute([$attendanceId, (int)$_SESSION['attendee_id']]);
            dashboard_redirect(
                $returnPage,
                $stmt->rowCount() ? 'Checked out successfully.' : 'No active check-in was found for this event.'
            );
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($action === 'update_student') {
            dashboard_redirect('students', 'Could not update student. The student number may already be in use.');
        }
        if ($action === 'checkin') {
            dashboard_redirect('events', 'You are already checked in to this event.');
        }
        dashboard_redirect($isStaff ? 'events' : 'my_attendance', 'The operation could not be completed. Please check the data and try again.');
    }
}

$displayName = $isStaff
    ? ($_SESSION['user']['FullName'] ?? 'Staff')
    : ($_SESSION['student_name'] ?? 'Student');
$role = $isStaff ? ($_SESSION['user']['Role'] ?? 'Staff') : 'Student';

$events = $pdo->query('SELECT * FROM Events ORDER BY EventDate DESC, StartTime DESC')->fetchAll();
$totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM Attendees')->fetchColumn();
$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM Events')->fetchColumn();
$insideNow = (int)$pdo->query('SELECT COUNT(*) FROM Attendance WHERE TimeOut IS NULL')->fetchColumn();

$students = [];
$staffAttendance = [];
$myAttendance = [];
if ($isStaff) {
    $students = $pdo->query('SELECT * FROM Attendees ORDER BY FullName')->fetchAll();
    $staffAttendance = $pdo->query('SELECT a.AttendanceID, a.TimeIn, a.TimeOut, a.AttendanceStatus, s.StudentNumber, s.FullName, e.EventName FROM Attendance a JOIN Attendees s ON s.AttendeeID = a.AttendeeID JOIN Events e ON e.EventID = a.EventID ORDER BY a.TimeIn DESC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT a.AttendanceID, a.TimeIn, a.TimeOut, a.AttendanceStatus, e.EventID, e.EventName, e.EventDate, e.Venue FROM Attendance a JOIN Events e ON e.EventID = a.EventID WHERE a.AttendeeID = ? ORDER BY a.TimeIn DESC');
    $stmt->execute([(int)$_SESSION['attendee_id']]);
    $myAttendance = $stmt->fetchAll();

    // Map each event to the current student's attendance record so actions
    // can be shown on the matching event card.
    $attendanceByEvent = [];
    foreach ($myAttendance as $attendanceRecord) {
        $attendanceByEvent[(int)$attendanceRecord['EventID']] = $attendanceRecord;
    }
}

$editingEvent = null;
$editingStudent = null;
if ($isStaff && isset($_GET['edit_event'])) {
    $stmt = $pdo->prepare('SELECT * FROM Events WHERE EventID = ?');
    $stmt->execute([(int)$_GET['edit_event']]);
    $editingEvent = $stmt->fetch() ?: null;
    $page = 'events';
}
if ($isStaff && isset($_GET['edit_student'])) {
    $stmt = $pdo->prepare('SELECT * FROM Attendees WHERE AttendeeID = ?');
    $stmt->execute([(int)$_GET['edit_student']]);
    $editingStudent = $stmt->fetch() ?: null;
    $page = 'students';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gymnasium Event Management</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand"><div class="brand-mark">GE</div><div><strong>Gymnasium Events</strong><small>Attendance management</small></div></div>
        <nav class="nav">
            <?php if ($isStaff): ?>
                <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="dashboard.php?page=dashboard">Dashboard</a>
                <a class="<?= $page === 'events' ? 'active' : '' ?>" href="dashboard.php?page=events">Manage Events</a>
                <a class="<?= $page === 'students' ? 'active' : '' ?>" href="dashboard.php?page=students">Manage Students</a>
                <a class="<?= $page === 'attendance' ? 'active' : '' ?>" href="dashboard.php?page=attendance">Attendance</a>
            <?php else: ?>
                <a class="<?= $page === 'events' ? 'active' : '' ?>" href="dashboard.php?page=events">Available Events</a>
                <a class="<?= $page === 'my_attendance' ? 'active' : '' ?>" href="dashboard.php?page=my_attendance">My Attendance</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom"><div class="user"><strong><?= h($displayName) ?></strong><small><?= h($role) ?></small></div><a class="logout-link" href="dashboard.php?logout=1">Log out</a></div>
    </aside>

    <main class="main">
        <header class="topbar"><div><div class="eyebrow"><?= $isStaff ? 'ADMINISTRATION' : 'STUDENT PORTAL' ?></div><h1><?php
            $titles = ['dashboard' => 'Dashboard', 'events' => $isStaff ? 'Manage Events' : 'Available Events', 'students' => 'Manage Students', 'attendance' => 'Attendance Records', 'my_attendance' => 'My Attendance'];
            echo h($titles[$page] ?? 'Dashboard');
        ?></h1></div><div class="date"><?= date('F j, Y') ?></div></header>

        <?php if ($flash !== ''): ?><div class="notice"><?= h($flash) ?></div><?php endif; ?>

        <?php if ($isStaff && $page === 'dashboard'): ?>
            <section class="stats">
                <article class="stat"><small>Total registered students</small><strong><?= $totalStudents ?></strong></article>
                <article class="stat"><small>Total events</small><strong><?= $totalEvents ?></strong></article>
                <article class="stat"><small>Currently checked in</small><strong><?= $insideNow ?></strong></article>
            </section>
            <section class="panel"><div class="panel-heading"><div><h2>Event management</h2><p class="subtext">Create, edit, or remove gymnasium events.</p></div><a class="btn" href="dashboard.php?page=events">Manage events</a></div>
                <div class="event-card"><div class="event-date"><b>+</b><small>EVENT</small></div><div class="event-detail"><b>Manage all events</b><small>Update dates, venues, times, and event status, or delete an event.</small></div><a class="btn secondary" href="dashboard.php?page=events">Open</a></div>
                <div class="event-card"><div class="event-date"><b>♙</b><small>STUDENT</small></div><div class="event-detail"><b>Manage students</b><small>Edit student details or remove a student record.</small></div><a class="btn secondary" href="dashboard.php?page=students">Open</a></div>
                <div class="event-card"><div class="event-date"><b>↔</b><small>LOG</small></div><div class="event-detail"><b>Attendance records</b><small>Review student check-in and check-out history.</small></div><a class="btn secondary" href="dashboard.php?page=attendance">Open</a></div>
            </section>

        <?php elseif ($isStaff && $page === 'events'): ?>
            <div class="grid-2">
                <section class="panel">
                    <div class="panel-heading"><div><h2><?= $editingEvent ? 'Edit event' : 'Create event' ?></h2><p class="subtext"><?= $editingEvent ? 'Update the selected event.' : 'Only Admin and Staff accounts can create events.' ?></p></div></div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="<?= $editingEvent ? 'update_event' : 'create_event' ?>">
                        <?php if ($editingEvent): ?><input type="hidden" name="event_id" value="<?= (int)$editingEvent['EventID'] ?>"><?php endif; ?>
                        <label class="field full">Event name<input name="event_name" maxlength="150" required value="<?= h($editingEvent['EventName'] ?? '') ?>"></label>
                        <label class="field full">Description<textarea name="event_description" maxlength="500"><?= h($editingEvent['EventDescription'] ?? '') ?></textarea></label>
                        <label class="field full">Venue<input name="venue" maxlength="100" value="<?= h($editingEvent['Venue'] ?? 'School Gymnasium') ?>"></label>
                        <label class="field">Event date<input type="date" name="event_date" required value="<?= h($editingEvent['EventDate'] ?? '') ?>"></label>
                        <label class="field">Start time<input type="time" name="start_time" required value="<?= h(isset($editingEvent['StartTime']) ? substr($editingEvent['StartTime'], 0, 5) : '') ?>"></label>
                        <label class="field">End time<input type="time" name="end_time" required value="<?= h(isset($editingEvent['EndTime']) ? substr($editingEvent['EndTime'], 0, 5) : '') ?>"></label>
                        <?php if ($editingEvent): ?><label class="field">Status<select name="event_status"><?php foreach (['Scheduled', 'Completed', 'Cancelled'] as $status): ?><option value="<?= h($status) ?>" <?= ($editingEvent['EventStatus'] ?? 'Scheduled') === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                        <button class="btn full" type="submit"><?= $editingEvent ? 'Save changes' : 'Create event' ?></button>
                        <?php if ($editingEvent): ?><a class="btn muted full" href="dashboard.php?page=events">Cancel editing</a><?php endif; ?>
                    </form>
                </section>
                <section class="panel"><div class="panel-heading"><div><h2>All events</h2><p class="subtext"><?= count($events) ?> event(s)</p></div></div>
                    <?php if (!$events): ?><div class="empty">No events have been created.</div><?php endif; ?>
                    <?php foreach ($events as $event): ?><div class="event-card"><div class="event-date"><b><?= date('d', strtotime($event['EventDate'])) ?></b><small><?= date('M', strtotime($event['EventDate'])) ?></small></div><div class="event-detail"><b><?= h($event['EventName']) ?></b><small><?= h($event['Venue']) ?> · <?= date('g:i A', strtotime($event['StartTime'])) ?>–<?= date('g:i A', strtotime($event['EndTime'])) ?></small><small><?= h($event['EventStatus']) ?></small><div class="actions" style="margin-top:9px"><a class="btn secondary" href="dashboard.php?page=events&amp;edit_event=<?= (int)$event['EventID'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this event and all attendance records linked to it?');"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?= (int)$event['EventID'] ?>"><button class="btn danger" type="submit">Delete</button></form></div></div></div><?php endforeach; ?>
                </section>
            </div>

        <?php elseif ($isStaff && $page === 'students'): ?>
            <?php if ($editingStudent): ?><section class="panel"><div class="panel-heading"><div><h2>Edit student</h2><p class="subtext">Update the student's information.</p></div></div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="update_student"><input type="hidden" name="attendee_id" value="<?= (int)$editingStudent['AttendeeID'] ?>">
                    <label class="field">Student number<input name="student_number" maxlength="30" required value="<?= h($editingStudent['StudentNumber']) ?>"></label>
                    <label class="field">Full name<input name="full_name" maxlength="100" required value="<?= h($editingStudent['FullName']) ?>"></label>
                    <label class="field">Course<input name="course" maxlength="50" value="<?= h($editingStudent['Course']) ?>"></label>
                    <label class="field">Year level<input type="number" name="year_level" min="1" max="8" value="<?= h($editingStudent['YearLevel']) ?>"></label>
                    <label class="field">Contact number<input name="contact_number" maxlength="20" value="<?= h($editingStudent['ContactNumber']) ?>"></label>
                    <div class="actions full"><button class="btn" type="submit">Save changes</button><a class="btn muted" href="dashboard.php?page=students">Cancel</a></div>
                </form>
            </section><?php endif; ?>
            <section class="panel"><div class="panel-heading"><div><h2>Registered students</h2><p class="subtext">Edit student information or remove records.</p></div><span class="tag"><?= count($students) ?> student(s)</span></div>
                <input class="search" id="studentSearch" placeholder="Search by student name, number, course..." oninput="filterStudents()" aria-label="Search students">
                <div class="table-wrap"><table id="studentsTable"><thead><tr><th>Student</th><th>Course</th><th>Year</th><th>Contact</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($students as $student): ?><tr><td><strong><?= h($student['FullName']) ?></strong><span class="muted-text"><?= h($student['StudentNumber']) ?></span></td><td><?= h($student['Course'] ?? '') ?></td><td><?= h($student['YearLevel'] ?? '') ?></td><td><?= h($student['ContactNumber'] ?? '') ?></td><td><div class="actions"><a class="btn secondary" href="dashboard.php?page=students&amp;edit_student=<?= (int)$student['AttendeeID'] ?>">Edit</a><form method="post" onsubmit="return confirm('Remove this student and all of their attendance records?');"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="attendee_id" value="<?= (int)$student['AttendeeID'] ?>"><button class="btn danger" type="submit">Remove</button></form></div></td></tr><?php endforeach; ?>
                <?php if (!$students): ?><tr><td colspan="5" class="empty">No students found.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>

        <?php elseif ($isStaff && $page === 'attendance'): ?>
            <section class="panel"><div class="panel-heading"><div><h2>Attendance records</h2><p class="subtext">All student check-ins and check-outs.</p></div><span class="tag"><?= count($staffAttendance) ?> record(s)</span></div><div class="table-wrap"><table><thead><tr><th>Student</th><th>Event</th><th>Time in</th><th>Time out</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($staffAttendance as $record): ?><tr><td><strong><?= h($record['FullName']) ?></strong><span class="muted-text"><?= h($record['StudentNumber']) ?></span></td><td><?= h($record['EventName']) ?></td><td><?= $record['TimeIn'] ? date('M j, Y g:i A', strtotime($record['TimeIn'])) : '—' ?></td><td><?= $record['TimeOut'] ? date('M j, Y g:i A', strtotime($record['TimeOut'])) : '—' ?></td><td><span class="tag <?= $record['TimeOut'] ? 'completed' : '' ?>"><?= $record['TimeOut'] ? 'Checked out' : 'Inside' ?></span></td></tr><?php endforeach; ?>
                <?php if (!$staffAttendance): ?><tr><td colspan="5" class="empty">No attendance records yet.</td></tr><?php endif; ?></tbody></table></div></section>

        <?php elseif (!$isStaff && $page === 'my_attendance'): ?>
            <section class="panel"><div class="panel-heading"><div><h2>My attendance</h2><p class="subtext">Your event entry and exit records.</p></div></div><div class="table-wrap"><table><thead><tr><th>Event</th><th>Date / Venue</th><th>Time in</th><th>Time out</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($myAttendance as $record): ?><tr><td><strong><?= h($record['EventName']) ?></strong></td><td><?= h($record['EventDate']) ?><span class="muted-text"><?= h($record['Venue']) ?></span></td><td><?= $record['TimeIn'] ? date('M j, g:i A', strtotime($record['TimeIn'])) : '—' ?></td><td><?= $record['TimeOut'] ? date('M j, g:i A', strtotime($record['TimeOut'])) : '—' ?></td><td><?php if (!$record['TimeOut']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="checkout"><input type="hidden" name="attendance_id" value="<?= (int)$record['AttendanceID'] ?>"><input type="hidden" name="return_page" value="my_attendance"><button class="btn" type="submit">Check out</button></form><?php else: ?><span class="tag completed">Completed</span><?php endif; ?></td></tr><?php endforeach; ?>
                <?php if (!$myAttendance): ?><tr><td colspan="5" class="empty">You do not have attendance records yet.</td></tr><?php endif; ?></tbody></table></div></section>

        <?php else: ?>
            <section class="panel"><div class="panel-heading"><div><h2>Available events</h2><p class="subtext">Check in or check out directly on the event you are attending. Check-in is available for scheduled events happening today.</p></div></div>
                <?php foreach ($events as $event): ?>
                    <?php
                        $eventId = (int)$event['EventID'];
                        $eventAttendance = $attendanceByEvent[$eventId] ?? null;
                        $eventIsToday = $event['EventDate'] === date('Y-m-d');
                        $eventCanCheckIn = !$eventAttendance && $eventIsToday && $event['EventStatus'] === 'Scheduled';
                    ?>
                    <div class="event-card">
                        <div class="event-date"><b><?= date('d', strtotime($event['EventDate'])) ?></b><small><?= date('M', strtotime($event['EventDate'])) ?></small></div>
                        <div class="event-detail">
                            <b><?= h($event['EventName']) ?></b>
                            <small><?= h($event['Venue']) ?> · <?= date('g:i A', strtotime($event['StartTime'])) ?>–<?= date('g:i A', strtotime($event['EndTime'])) ?></small>
                            <?php if (!empty($event['EventDescription'])): ?><small><?= h($event['EventDescription']) ?></small><?php endif; ?>
                            <small>Status: <?= h($event['EventStatus']) ?></small>
                            <?php if ($eventAttendance): ?>
                                <small class="attendance-state">Your attendance: <?= $eventAttendance['TimeOut'] ? 'Checked out' : 'Checked in' ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="event-attendance-action">
                            <?php if ($eventAttendance && empty($eventAttendance['TimeOut'])): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="attendance_id" value="<?= (int)$eventAttendance['AttendanceID'] ?>">
                                    <input type="hidden" name="return_page" value="events">
                                    <button class="btn danger" type="submit" onclick="return confirm('Check out from this event?');">Check out</button>
                                </form>
                            <?php elseif ($eventAttendance && !empty($eventAttendance['TimeOut'])): ?>
                                <span class="tag completed">Completed</span>
                            <?php elseif ($eventCanCheckIn): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="action" value="checkin">
                                    <input type="hidden" name="event_id" value="<?= $eventId ?>">
                                    <button class="btn" type="submit">Check in</button>
                                </form>
                            <?php else: ?>
                                <span class="tag"><?= $event['EventStatus'] === 'Scheduled' ? ($eventIsToday ? 'Unavailable' : 'Not today') : h($event['EventStatus']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$events): ?><div class="empty">There are no events available.</div><?php endif; ?>
            </section>
        <?php endif; ?>
        <footer>Gymnasium Event In & Out Management</footer>
    </main>
</div>
<script>
function filterStudents(){
    const input=document.getElementById('studentSearch');
    const table=document.getElementById('studentsTable');
    if(!input||!table)return;
    const query=input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row=>{
        row.style.display=row.innerText.toLowerCase().includes(query)?'':'none';
    });
}
</script>
</body>
</html>
