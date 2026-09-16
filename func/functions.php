<?php

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
}


function checkRole($allowedRoles) {
    $currentRole = $_SESSION['role'] ?? '';
    $allowedLower = array_map('strtolower', $allowedRoles);

    if (!in_array(strtolower($currentRole), $allowedLower, true)) {
        // there's no index.php in this project — the public landing page is index.html
        header('Location: ../index.html');
        exit();
    }
}

// General-purpose text cleanup for form input that gets echoed back later
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Turns 'pending_operation' into 'Pending Operation', etc. — used on every status badge
function status_label($status) {
    return ucwords(str_replace('_', ' ', $status));
}

// Shared ₱ currency formatting for reports, billing, and dashboard stat cards
function format_currency($amount) {
    return '₱' . number_format((float) $amount, 2);
}

// Compute real available time slots for a dentist on a given date, sized to a service's
// duration — not a fixed 30-min grid. A slot only counts if the *entire* duration fits
// before closing time and doesn't overlap any existing (non-cancelled) booking for that
// dentist. Used by both the patient booking flow and the receptionist walk-in booking flow.
function get_available_slots(mysqli $conn, int $dentist_id, string $date, int $duration_minutes): array {
    $clinic_hours = [];
    $res = mysqli_query($conn, "SELECT day_of_week, open_time, close_time, is_closed FROM clinic_hours");
    while ($row = mysqli_fetch_assoc($res)) {
        $clinic_hours[(int) $row['day_of_week']] = $row;
    }

    $dow = (int) (new DateTime($date))->format('w');
    if (!isset($clinic_hours[$dow]) || (int) $clinic_hours[$dow]['is_closed'] === 1) { return []; }

    $stmt = mysqli_prepare($conn, "SELECT 1 FROM clinic_closures WHERE closure_date = ?");
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    $closed = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);
    if ($closed) { return []; }

    $stmt = mysqli_prepare($conn, "SELECT start_time, end_time FROM appointments WHERE dentist_id = ? AND appointment_date = ? AND status != 'cancelled'");
    mysqli_stmt_bind_param($stmt, "is", $dentist_id, $date);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $booked = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $open  = DateTime::createFromFormat('Y-m-d H:i:s', "$date " . $clinic_hours[$dow]['open_time']);
    $close = DateTime::createFromFormat('Y-m-d H:i:s', "$date " . $clinic_hours[$dow]['close_time']);
    $now   = new DateTime();

    $slots = [];
    $cursor = clone $open;
    while (true) {
        $slot_end = (clone $cursor)->modify("+{$duration_minutes} minutes");
        if ($slot_end > $close) { break; }
        if ($cursor < $now) { $cursor->modify('+30 minutes'); continue; }

        $start_str = $cursor->format('H:i:s');
        $end_str   = $slot_end->format('H:i:s');
        $conflict  = false;
        foreach ($booked as $b) {
            if ($start_str < $b['end_time'] && $end_str > $b['start_time']) { $conflict = true; break; }
        }
        if (!$conflict) {
            $slots[] = ['start' => $start_str, 'end' => $end_str, 'display' => $cursor->format('g:i A')];
        }
        $cursor->modify('+30 minutes');
    }
    return $slots;
}
?>