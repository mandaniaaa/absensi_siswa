<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mendapatkan data user termasuk foto profil
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Mendapatkan data statistik user
$stmt = $pdo->prepare("SELECT COUNT(*) as total_hadir FROM absensi WHERE user_id = ? AND status = 'hadir'");
$stmt->execute([$user_id]);
$total_hadir = $stmt->fetch()['total_hadir'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_terlambat FROM absensi WHERE user_id = ? AND status = 'terlambat'");
$stmt->execute([$user_id]);
$total_terlambat = $stmt->fetch()['total_terlambat'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_alpha FROM absensi WHERE user_id = ? AND status = 'alpha'");
$stmt->execute([$user_id]);
$total_alpha = $stmt->fetch()['total_alpha'];

// Cek apakah sudah absen hari ini
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$user_id, $today]);
$absen_hari_ini = $stmt->fetch();

// Proses absensi masuk
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_masuk'])) {
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Dapatkan IP address dan info perangkat
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $perangkat = $_SERVER['HTTP_USER_AGENT'];
    
    $jam_sekarang = date('H:i:s');
    $jam_batas_terlambat = '10:00:00';
    
    // Tentukan status berdasarkan waktu
    if ($status == 'hadir') {
        if (strtotime($jam_sekarang) > strtotime($jam_batas_terlambat)) {
            $status = 'terlambat';
        }
    }
    
    // Insert absensi baru
    $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, status, keterangan, ip_address, perangkat) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $today, $jam_sekarang, $status, $keterangan, $ip_address, $perangkat]);
    
    header("Location: user_dashboard.php");
    exit();
}

// Proses absensi keluar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_keluar'])) {
    $jam_keluar = date('H:i:s');
    
    // Update absensi keluar
    $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ? WHERE user_id = ? AND tanggal = ?");
    $stmt->execute([$jam_keluar, $user_id, $today]);
    
    header("Location: user_dashboard.php");
    exit();
}


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Sistem Absensi PKL</title>
    <style>
        :root {
            /* Light Theme Colors */
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            
            /* Light Theme Backgrounds */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --bg-sidebar: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bg-card: #ffffff;
            --bg-overlay: rgba(0, 0, 0, 0.1);
            
            /* Light Theme Text */
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --text-white: #ffffff;
            
            /* Light Theme Borders */
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            
            /* Layout */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            --transition-speed: 0.3s;
            --border-radius: 16px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Dark Theme Colors */
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --bg-sidebar: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            --bg-card: #1e293b;
            --bg-overlay: rgba(255, 255, 255, 0.1);
            
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            
            --border-color: #334155;
            --border-light: #475569;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Enhanced Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            color: var(--text-white);
            height: 100vh;
            position: fixed;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            overflow-x: hidden;
            z-index: 1000;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-xl);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-header {
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid var(--bg-overlay);
            height: var(--header-height);
            position: relative;
        }
        
        .sidebar-header h3 {
            white-space: nowrap;
            margin-bottom: 8px;
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(45deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
            font-weight: 500;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 16px 0;
            margin-top: 20px;
        }
        
        .sidebar-menu li {
            margin: 4px 16px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            color: var(--text-white);
            text-decoration: none;
            transition: all var(--transition-speed) ease;
            white-space: nowrap;
            border-radius: 12px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .sidebar-menu a:hover::before {
            left: 100%;
        }
        
        .sidebar-menu a:hover, .sidebar-menu li.active a {
            background: var(--bg-overlay);
            transform: translateX(8px);
            box-shadow: var(--shadow-md);
        }
        
        .sidebar-menu i {
            margin-right: 16px;
            min-width: 24px;
            text-align: center;
            font-size: 20px;
            transition: transform var(--transition-speed) ease;
        }
        
        .sidebar-menu a:hover i {
            transform: scale(1.1);
        }
        
        .sidebar.collapsed .sidebar-menu span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-menu i {
            margin-right: 0;
        }
        
        .sidebar-toggle {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: var(--bg-overlay);
            border: none;
            color: var(--text-white);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: absolute;
            bottom: 80px;
            right: 20px;
            background: var(--bg-overlay);
            border: none;
            color: var(--text-white);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            backdrop-filter: blur(10px);
            font-size: 18px;
        }
        
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        /* Enhanced Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .header {
            background: var(--bg-primary);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            height: var(--header-height);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header h1 {
            font-size: 28px;
            color: var(--text-primary);
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-speed) ease;
        }
        
        .profile-img:hover {
            transform: scale(1.1);
        }
        
        .dashboard-content {
            padding: 32px;
            background: var(--bg-secondary);
            min-height: calc(100vh - var(--header-height));
        }
        
        /* Enhanced Real-time Clock */
        .real-time-clock {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            margin-bottom: 32px;
            color: var(--text-white);
            position: relative;
            overflow: hidden;
        }
        
        .real-time-clock::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .real-time-clock h2 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 20px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        
        .real-time-clock .time {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .real-time-clock .date {
            font-size: 18px;
            opacity: 0.9;
            font-weight: 500;
        }
        
        /* Enhanced Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 28px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all var(--transition-speed) ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.2;
            border-radius: inherit;
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
        }
        
        .stat-info h3 {
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 800;
        }
        
        .stat-info p {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Enhanced Absen Form */
        .absen-form {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-bottom: 32px;
        }
        
        /* Time Info Section */
        .time-info {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .current-time,
        .current-date {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .time-label,
        .date-label {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .time-value,
        .date-value {
            font-size: 24px;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .time-value {
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        @media (max-width: 768px) {
            .time-info {
                flex-direction: column;
                gap: 16px;
            }
        }
        
        .absen-form h2 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .absen-form h2::before {
            content: '📅';
            font-size: 28px;
        }
        
        .absen-info {
            padding: 20px;
            background: var(--bg-tertiary);
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin-left: 8px;
        }
        
        .status-badge.hadir {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .status-badge.terlambat {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
        }
        
        .status-badge.izin, .status-badge.sakit {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .radio-group {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 12px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            transition: all var(--transition-speed) ease;
            background: var(--bg-primary);
        }
        
        .radio-label:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .radio-label input[type="radio"] {
            display: none;
        }
        
        .radio-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 50%;
            margin-right: 12px;
            position: relative;
            transition: all var(--transition-speed) ease;
        }
        
        .radio-label input[type="radio"]:checked + .radio-custom {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }
        
        .radio-label input[type="radio"]:checked + .radio-custom::after {
            content: '';
            width: 8px;
            height: 8px;
            background-color: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .radio-label input[type="radio"]:checked ~ span {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-family: inherit;
            font-size: 16px;
            resize: vertical;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
        }
        
        textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-absen {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            margin-right: 16px;
            box-shadow: var(--shadow-md);
        }
        
        .btn-absen:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-keluar {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-keluar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: var(--shadow-md);
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
        /* Upload Foto Profil */
        .upload-form {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .upload-form h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .profile-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-preview img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
            margin-bottom: 15px;
        }
        
        .file-input {
            margin-bottom: 15px;
        }
        
        .file-input input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px dashed #ddd;
            border-radius: 5px;
        }
        
        .btn-upload {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .btn-upload:hover {
            background-color: #5a6fd5;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
            }
            
            .sidebar .sidebar-menu span {
                display: none;
            }
            
            .sidebar .sidebar-menu i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }
            
            .dashboard-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 16px;
            }
            
            .btn-absen, .btn-keluar, .btn-logout {
                width: 100%;
                margin-bottom: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .real-time-clock {
                padding: 24px;
            }
            
            .real-time-clock .time {
                font-size: 36px;
            }
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body data-theme="light">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Sistem Absensi PKL</h3>
                <p>User Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="user_dashboard.php"><i>📊</i> <span>Dashboard</span></a></li>
                <li><a href="rekap_absen.php"><i>📋</i> <span>Rekap Absen Saya</span></a></li>
                <li><a href="profil.php"><i>👤</i> <span>Profil</span></a></li>
                <li><a href="login.php"><i>🚪</i> <span>Logout Akun</span></a></li>
            </ul>
            <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                <i>🌙</i>
            </button>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i>☰</i>
            </button>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <div class="header">
                <h1>Dashboard User</h1>
                <div class="user-info">
                    <span>Halo, <?php echo $_SESSION['nama_lengkap']; ?></span>
                    <?php if (!empty($user['foto_profil'])): ?>
                        <img src="<?php echo $user['foto_profil']; ?>" alt="Profile" class="profile-img">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nama_lengkap']); ?>&background=random" alt="Profile" class="profile-img">
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Jam Real-time -->
                <div class="real-time-clock">
                    <h2>Waktu Sekarang</h2>
                    <div class="time" id="real-time"><?php echo date('H:i:s'); ?></div>
                    <div class="date" id="real-date"><?php echo date('d F Y'); ?></div>
                </div>
                

                
                <!-- Statistik -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>✓</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_hadir; ?></h3>
                            <p>Total Hadir</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>⏰</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_terlambat; ?></h3>
                            <p>Total Terlambat</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>✗</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_alpha; ?></h3>
                            <p>Total Alpha</p>
                        </div>
                    </div>
                </div>

                <!-- Form Absensi -->
                <div class="absen-form">
                    <h2>Absensi Hari Ini (<?php echo date('d F Y'); ?>)</h2>
                    
                    <?php if ($absen_hari_ini): ?>
                        <div class="absen-info">
                            <p>Anda sudah absen masuk hari ini pada pukul <?php echo $absen_hari_ini['jam_masuk']; ?> dengan status <span class="status-badge <?php echo $absen_hari_ini['status']; ?>"><?php echo ucfirst($absen_hari_ini['status']); ?></span></p>
                            <?php if ($absen_hari_ini['keterangan']): ?>
                                <p>Keterangan: <?php echo $absen_hari_ini['keterangan']; ?></p>
                            <?php endif; ?>
                            
                            <?php if ($absen_hari_ini['jam_keluar']): ?>
                                <p>Anda sudah absen keluar pada pukul <?php echo $absen_hari_ini['jam_keluar']; ?></p>
                            <?php else: ?>
                                <p>Anda belum absen keluar hari ini.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <!-- Informasi Waktu Real-time -->
                            <div class="time-info">
                                <div class="current-time">
                                    <span class="time-label">Waktu Sekarang:</span>
                                    <span class="time-value" id="current-time"><?php echo date('H:i:s'); ?></span>
                                </div>
                                <div class="current-date">
                                    <span class="date-label">Tanggal:</span>
                                    <span class="date-value" id="current-date"><?php echo date('d F Y'); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Status Kehadiran:</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="status" value="hadir" checked>
                                        <span class="radio-custom"></span>
                                        Hadir
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="status" value="izin">
                                        <span class="radio-custom"></span>
                                        Izin
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="status" value="sakit">
                                        <span class="radio-custom"></span>
                                        Sakit
                                    </label>
                                </div>
                            </div>
                            <div class="form-group" id="keterangan-group" style="display: none;">
                                <label for="keterangan">Keterangan (wajib jika tidak hadir):</label>
                                <textarea id="keterangan" name="keterangan" rows="3"></textarea>
                            </div>
                            <button type="submit" name="absen_masuk" class="btn-absen">Absen Masuk</button>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Tombol Aksi -->
                    <div class="action-buttons">
                        <?php if ($absen_hari_ini && !$absen_hari_ini['jam_keluar']): ?>
                            <form method="POST">
                                <button type="submit" name="absen_keluar" class="btn-keluar">Absen Keluar</button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="login.php" class="btn-logout">Logout Akun</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');
        
        // Load saved theme from localStorage
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        // Theme toggle functionality
        themeToggle.addEventListener('click', function() {
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
        
        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.textContent = '☀️';
                themeIcon.title = 'Switch to Light Mode';
            } else {
                themeIcon.textContent = '🌙';
                themeIcon.title = 'Switch to Dark Mode';
            }
        }
        
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
        
        // Tampilkan textarea keterangan jika status tidak hadir dipilih
        document.querySelectorAll('input[name="status"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const keteranganGroup = document.getElementById('keterangan-group');
                if (this.value === 'izin' || this.value === 'sakit') {
                    keteranganGroup.style.display = 'block';
                } else {
                    keteranganGroup.style.display = 'none';
                }
            });
        });
        
        // Jam real-time
        function updateClock() {
            const now = new Date();
            const timeElement = document.getElementById('real-time');
            const dateElement = document.getElementById('real-date');
            
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const day = days[now.getDay()];
            const date = now.getDate();
            const month = months[now.getMonth()];
            const year = now.getFullYear();
            
            timeElement.textContent = `${hours}:${minutes}:${seconds}`;
            dateElement.textContent = `${day}, ${date} ${month} ${year}`;
        }
        
        // Update jam setiap detik
        setInterval(updateClock, 1000);
        updateClock(); // Panggil pertama kali
        
        // Update waktu real-time di form absen
        function updateCurrentTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            
            if (timeElement && dateElement) {
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                
                const day = days[now.getDay()];
                const date = now.getDate();
                const month = months[now.getMonth()];
                const year = now.getFullYear();
                
                timeElement.textContent = `${hours}:${minutes}:${seconds}`;
                dateElement.textContent = `${day}, ${date} ${month} ${year}`;
            }
        }
        
        // Update waktu form absen setiap detik
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime(); // Panggil pertama kali
        
        // Add smooth animations to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        statCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>