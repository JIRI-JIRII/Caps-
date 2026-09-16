<?php

date_default_timezone_set('Asia/Manila');

$host     = 'localhost';
$dbname   = 'alberba_dental_clinic'; // matches CREATE DATABASE in the .sql file
$username = 'root';
$password = '';

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Keep PHP <-> MySQL charset in sync with the utf8mb4 database created in the .sql file
mysqli_set_charset($conn, 'utf8mb4');
?>