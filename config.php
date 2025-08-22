<?php
$host = 'localhost';
$dbname = 'absensi_pkl';
$username = 'root';
$password = '';

// Set timezone aplikasi ke Asia/Jakarta agar waktu real-time konsisten
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Jakarta');
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set timezone sesi MySQL agar fungsi waktu di DB (jika ada) konsisten
    try {
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        // Abaikan jika server MySQL tidak mendukung pengaturan ini
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

session_start();
?>