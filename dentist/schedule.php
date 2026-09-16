<?php
session_start();
require_once '../config/database.php';
require_once '../func/functions.php';

checkLogin();
checkRole(['dentist']);
/** @var mysqli $conn */
// Get dentist info
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM dentists WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dentist = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Get schedule for next 7 days
$schedules = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day_name = date('l', strtotime($date));
    
    $query = "
        SELECT a.*, p.first_name as patient_fname, p.last_name as patient_lname, p.phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.dentist_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $dentist['dentist_id'], $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointments = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    $schedules[] = [
        'date' => $date,
        'day_name' => $day_name,
        'appointments' => $appointments
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>🦷 Alberba Dentist</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="dentist-index.php">Dashboard</a>
                <a href="appointments.php">Appointments</a>
                <a href="patients.php">Patients</a>
                <a href="schedule.php" class="active">My Schedule</a>
                <a href="../logout.php">Logout</a>
            </nav>
        </aside>
        <main class="main-content">
            <div class="content-header">
                <h1>My Schedule - Next 7 Days</h1>
            </div>

            <?php foreach($schedules as $schedule): ?>
                <div class="card">
                    <h2>
                        <?php echo $schedule['day_name']; ?> - 
                        <?php echo date('F j, Y', strtotime($schedule['date'])); ?>
                        <?php if($schedule['date'] == date('Y-m-d')): ?>
                            <span class="badge badge-confirmed">Today</span>
                        <?php endif; ?>
                    </h2>
                    
                    <?php if($schedule['appointments']): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($schedule['appointments'] as $apt): ?>
                                    <tr>
                                        <td><strong><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($apt['patient_fname'] . ' ' . $apt['patient_lname']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['phone']); ?></td>
                                        <td><span class="badge badge-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($apt['notes'] ?: 'No notes'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No appointments scheduled for this day.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </main>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>
