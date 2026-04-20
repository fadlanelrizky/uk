<?php
// config/database.php
session_start();

$host = '127.0.0.1';
$user = 'root';
$pass = ''; // Default XAMPP
$db = 'event_tiket';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Function helper untuk base_url
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $base_folder = '/event_tiket/'; // Sesuaikan dengan folder jika beda
        return $protocol . '://' . $host . $base_folder . ltrim($path, '/');
    }
}
?>
