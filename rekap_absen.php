<?php
include 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fungsi untuk format waktu yang konsisten
function formatWaktu($waktu) {
    if (empty($waktu)) return '-';
    
    // Jika format sudah HH:MM:SS, tampilkan apa adanya
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $waktu)) {
        return $waktu;
    }
    
    // Jika format HH:MM, tambahkan :00
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $waktu)) {
        return $waktu . ':00';
    }
    
    // Jika format lain, coba parse dan format ulang
    $timestamp = strtotime($waktu);
    if ($timestamp !== false) {
        return date('H:i:s', $timestamp);
    }
    
    return $waktu;
}

// Jika user adalah admin, tampilkan semua data, jika user biasa hanya data sendiri
if ($_SESSION['role'] == 'admin') {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.nama_lengkap, u.asal_sekolah, u.ttl 
        FROM absensi a 
        JOIN users u ON a.user_id = u.id 
        ORDER BY a.tanggal DESC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.nama_lengkap, u.asal_sekolah, u.ttl 
        FROM absensi a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.user_id = ? 
        ORDER BY a.tanggal DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
}

$absensi = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absen - Sistem Absensi PKL</title>
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
        
        /* Enhanced Table Styles */
        .rekap-table {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .rekap-table h2 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 32px 32px 0 32px;
        }
        
        .rekap-table h2::before {
            content: '📋';
            font-size: 28px;
        }
        
        .table-container {
            overflow-x: auto;
            padding: 0 32px 32px 32px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--border-radius);
        }
        
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        tr:hover {
            background: var(--bg-tertiary);
            transition: background var(--transition-speed) ease;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-hadir {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .status-terlambat {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
        }
        
        .status-izin {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        .status-sakit {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
        }
        
        .status-alpha {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-size: 18px;
        }
        
        .no-data::before {
            content: '📊';
            font-size: 48px;
            display: block;
            margin-bottom: 16px;
        }
        
        /* Jam Real-time */
        .real-time-clock {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 24px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
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
        
        .real-time-clock h2 {
            margin: 0 0 16px 0;
            font-size: 18px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .real-time-clock .time {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .real-time-clock .date {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
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
            
            .rekap-table h2 {
                padding: 24px 24px 0 24px;
            }
            
            .table-container {
                padding: 0 24px 24px 24px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 12px;
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
                <p><?php echo $_SESSION['role'] == 'admin' ? 'Admin Dashboard' : 'User Dashboard'; ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo $_SESSION['role'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>"><i>📊</i> <span>Dashboard</span></a></li>
                <li class="active"><a href="rekap_absen.php"><i>📋</i> <span>Rekap Absen</span></a></li>
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
                <h1>Rekap Absensi</h1>
                <div class="user-info">
                    <span>Halo, <?php echo $_SESSION['nama_lengkap']; ?></span>
                    <?php if (!empty($_SESSION['foto_profil'])): ?>
                        <img src="<?php echo $_SESSION['foto_profil']; ?>" alt="Profile" class="profile-img">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nama_lengkap']); ?>&background=random" alt="Profile" class="profile-img">
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Jam Real-time untuk Admin -->
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="real-time-clock">
                    <h2>Waktu Sekarang</h2>
                    <div class="time" id="real-time"><?php echo date('H:i:s'); ?></div>
                    <div class="date" id="real-date"><?php echo date('d F Y'); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="rekap-table">
                    <h2>Data Absensi <?php echo $_SESSION['role'] == 'admin' ? 'Semua User' : 'Saya'; ?></h2>
                    
                    <div class="table-container">
                        <?php if (empty($absensi)): ?>
                            <div class="no-data">
                                Belum ada data absensi
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <?php if ($_SESSION['role'] == 'admin'): ?>
                                            <th>Nama</th>
                                            <th>Username</th>
                                            <th>Asal Sekolah</th>
                                        <?php endif; ?>
                                        <th>Tanggal</th>
                                        <th>Jam Masuk</th>
                                        <th>Jam Keluar</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                        <th>IP Address</th>
                                        <th>Perangkat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absensi as $index => $absen): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                                <td><?php echo htmlspecialchars($absen['nama_lengkap']); ?></td>
                                                <td><?php echo htmlspecialchars($absen['username']); ?></td>
                                                <td><?php echo htmlspecialchars($absen['asal_sekolah']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo date('d/m/Y', strtotime($absen['tanggal'])); ?></td>
                                            <td><?php echo formatWaktu($absen['jam_masuk']); ?></td>
                                            <td><?php echo formatWaktu($absen['jam_keluar']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $absen['status']; ?>">
                                                    <?php echo ucfirst($absen['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $absen['keterangan'] ? htmlspecialchars($absen['keterangan']) : '-'; ?></td>
                                            <td><?php echo $absen['ip_address']; ?></td>
                                            <td><?php echo htmlspecialchars(substr($absen['perangkat'], 0, 50)) . (strlen($absen['perangkat']) > 50 ? '...' : ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
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
        
        // Jam real-time untuk admin
        <?php if ($_SESSION['role'] == 'admin'): ?>
        function updateClock() {
            const now = new Date();
            const timeElement = document.getElementById('real-time');
            const dateElement = document.getElementById('real-date');
            
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
        
        // Update jam setiap detik
        setInterval(updateClock, 1000);
        updateClock(); // Panggil pertama kali
        <?php endif; ?>
    </script>
</body>
</html>