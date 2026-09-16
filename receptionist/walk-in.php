<?php
session_start();
require_once '../config/database.php';
require_once '../func/functions.php';

checkLogin();
checkRole(['receptionist']);
/** @var mysqli $conn */
$message = '';
$error = '';

// Handle walk-in patient registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_walkin'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $dentist_id = intval($_POST['dentist_id']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    mysqli_begin_transaction($conn);
    
    try {
        // Create temporary user account for walk-in
        $username = strtolower($first_name . $last_name . rand(100, 999));
        $email = $username . '@walkin.temp';
        $password = password_hash('walkin123', PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'patient')";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sss", $username, $password, $email);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Insert patient as walk-in
        $query = "INSERT INTO patients (user_id, first_name, last_name, phone, address, is_walk_in) VALUES (?,  ?, ?, ?, ?, TRUE)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $first_name, $last_name, $phone, $address);
        mysqli_stmt_execute($stmt);
        $patient_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Create appointment for today
        $today = date('Y-m-d');
        $query = "INSERT INTO appointments (patient_id, dentist_id, appointment_date, appointment_time, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iisss", $patient_id, $dentist_id, $today, $appointment_time, $notes);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conn);
        $message = "Walk-in patient registered successfully! Patient is in queue.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to register walk-in patient. Please try again.";
    }
}

// Get all dentists
$query = "SELECT * FROM dentists ORDER BY first_name";
$result = mysqli_query($conn, $query);
$dentists = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Patient Registration </title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-modern.css">
</head>
<body>
    <div class="modern-layout">
        <header class="top-header">
            <div class="header-content">
                <h1 class="brand-title">ALBERBA <span class="brand-highlight">DENTAL CLINIC</span> - Reception</h1>
                <a href="../logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <aside class="modern-sidebar">
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item">DASHBOARD</a>
                <a href="walk-in.php" class="menu-item active">WALK-IN PATIENT</a>
                <a href="appointments.php" class="menu-item">APPOINTMENTS</a>
                <a href="patients.php" class="menu-item">PATIENTS</a>
                <a href="queue.php" class="menu-item">QUEUE</a>
            </nav>
        </aside>

        <main class="modern-content">
            <div class="page-title">Register Walk-in Patient</div>

            <?php if($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="dashboard-card">
                <h2 class="card-title">Patient Information</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number *</label>
                            <input type="tel" name="phone" placeholder="09123456789" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" placeholder="Complete address">
                    </div>

                    <hr style="margin: 2rem 0; border: none; border-top: 1px solid #e5e7eb;">

                    <h3 style="margin-bottom: 1rem; color: #1f2937;">Appointment Details</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Select Dentist *</label>
                            <select name="dentist_id" required>
                                <option value="">Choose a dentist</option>
                                <?php foreach($dentists as $dentist): ?>
                                    <option value="<?php echo $dentist['dentist_id']; ?>">
                                        Dr. <?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>
                                        <?php if($dentist['specialization']): ?>
                                            - <?php echo htmlspecialchars($dentist['specialization']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Appointment Time *</label>
                            <input type="time" name="appointment_time" value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Chief Complaint / Reason for Visit *</label>
                        <textarea name="notes" rows="4" placeholder="Describe the patient's main concern or reason for visit..." required></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="register_walkin" class="btn btn-primary">Register & Add to Queue</button>
                        <a href="index.php" class="btn" style="background: #6b7280; color: white;">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>
