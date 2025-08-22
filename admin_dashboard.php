<?php
include 'config.php';

// Cek apakah user sudah login dan adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}



// Mendapatkan statistik untuk dashboard
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch()['total_users'];

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as hadir_hari_ini FROM absensi WHERE tanggal = ? AND status = 'hadir'");
$stmt->execute([$today]);
$stats['hadir_hari_ini'] = $stmt->fetch()['hadir_hari_ini'];

$stmt = $pdo->prepare("SELECT COUNT(*) as terlambat_hari_ini FROM absensi WHERE tanggal = ? AND status = 'terlambat'");
$stmt->execute([$today]);
$stats['terlambat_hari_ini'] = $stmt->fetch()['terlambat_hari_ini'];

$stmt = $pdo->prepare("SELECT COUNT(*) as alpha_hari_ini FROM absensi WHERE tanggal = ? AND status = 'alpha'");
$stmt->execute([$today]);
$stats['alpha_hari_ini'] = $stmt->fetch()['alpha_hari_ini'];

// Proses input kehadiran manual
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['input_kehadiran'])) {
	$user_id = $_POST['user_id'];
	$tanggal = $_POST['tanggal'];
	$jam_masuk = $_POST['jam_masuk'];
	$jam_keluar = $_POST['jam_keluar'] ?: null;
	$status = $_POST['status'];
	$keterangan = $_POST['keterangan'] ?: '';
	
	// Dapatkan IP address dan info perangkat admin
	$ip_address = $_SERVER['REMOTE_ADDR'];
	$perangkat = $_SERVER['HTTP_USER_AGENT'];
	
	try {
		// Cek apakah sudah ada absensi untuk user dan tanggal tersebut
		$stmt = $pdo->prepare("SELECT id FROM absensi WHERE user_id = ? AND tanggal = ?");
		$stmt->execute([$user_id, $tanggal]);
		
		if ($stmt->fetch()) {
			// Update absensi yang sudah ada
			$stmt = $pdo->prepare("UPDATE absensi SET jam_masuk = ?, jam_keluar = ?, status = ?, keterangan = ?, ip_address = ?, perangkat = ? WHERE user_id = ? AND tanggal = ?");
			$stmt->execute([$jam_masuk, $jam_keluar, $status, $keterangan, $ip_address, $perangkat, $user_id, $tanggal]);
			$success_message = "Data kehadiran berhasil diperbarui!";
		} else {
			// Insert absensi baru
			$stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, jam_keluar, status, keterangan, ip_address, perangkat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute([$user_id, $tanggal, $jam_masuk, $jam_keluar, $status, $keterangan, $ip_address, $perangkat]);
			$success_message = "Data kehadiran berhasil disimpan!";
		}
		
		// Refresh statistik
		$stmt = $pdo->prepare("SELECT COUNT(*) as hadir_hari_ini FROM absensi WHERE tanggal = ? AND status = 'hadir'");
		$stmt->execute([$today]);
		$stats['hadir_hari_ini'] = $stmt->fetch()['hadir_hari_ini'];
		
		$stmt = $pdo->prepare("SELECT COUNT(*) as terlambat_hari_ini FROM absensi WHERE tanggal = ? AND status = 'terlambat'");
		$stmt->execute([$today]);
		$stats['terlambat_hari_ini'] = $stmt->fetch()['terlambat_hari_ini'];
		
		$stmt = $pdo->prepare("SELECT COUNT(*) as alpha_hari_ini FROM absensi WHERE tanggal = ? AND status = 'alpha'");
		$stmt->execute([$today]);
		$stats['alpha_hari_ini'] = $stmt->fetch()['alpha_hari_ini'];
		
	} catch (PDOException $e) {
		$error_message = "Terjadi kesalahan: " . $e->getMessage();
	}
}

// ===== Kelola User (Tambah / Hapus) - Admin only =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_user'])) {
	$username_baru = trim($_POST['new_username'] ?? '');
	$password_baru = trim($_POST['new_password'] ?? '');
	$nama_baru = trim($_POST['new_nama_lengkap'] ?? '');
	$asal_baru = trim($_POST['new_asal_sekolah'] ?? '');
	$tanggal_lahir_baru = trim($_POST['new_tanggal_lahir'] ?? '');
	
	if ($username_baru === '' || $password_baru === '' || $nama_baru === '' || $asal_baru === '' || $tanggal_lahir_baru === '') {
		$error_message = 'Semua field user baru wajib diisi!';
	} elseif (strlen($password_baru) < 6) {
		$error_message = 'Password minimal 6 karakter!';
	} elseif (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $tanggal_lahir_baru)) {
		$error_message = 'Tanggal lahir harus berformat YYYY-MM-DD!';
	} else {
		try {
			// Cek username unik
			$cek = $pdo->prepare('SELECT id FROM users WHERE username = ?');
			$cek->execute([$username_baru]);
			if ($cek->fetch()) {
				$error_message = 'Username sudah digunakan!';
			} else {
				$hashed = password_hash($password_baru, PASSWORD_DEFAULT);
				// ttl disimpan sebagai tanggal (YYYY-MM-DD) sesuai penyesuaian
				$ins = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, asal_sekolah, ttl, role) VALUES (?, ?, ?, ?, ?, 'user')");
				$ins->execute([$username_baru, $hashed, $nama_baru, $asal_baru, $tanggal_lahir_baru]);
				$success_message = 'User baru berhasil ditambahkan!';
			}
		} catch (PDOException $e) {
			$error_message = 'Gagal menambah user: ' . $e->getMessage();
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_user'])) {
	$hapus_user_id = (int)($_POST['hapus_user_id'] ?? 0);
	try {
		// Ambil role dan pastikan bukan admin dan bukan diri sendiri
		$cek = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
		$cek->execute([$hapus_user_id]);
		$target = $cek->fetch();
		if (!$target) {
			$error_message = 'User tidak ditemukan';
		} elseif ($target['role'] !== 'user') {
			$error_message = 'Tidak boleh menghapus admin!';
		} elseif ($hapus_user_id == $_SESSION['user_id']) {
			$error_message = 'Tidak boleh menghapus akun sendiri!';
		} else {
			// Hapus data absensi user terlebih dahulu (jika ada)
			$pdo->prepare('DELETE FROM absensi WHERE user_id = ?')->execute([$hapus_user_id]);
			$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$hapus_user_id]);
			$success_message = 'User berhasil dihapus.';
		}
	} catch (PDOException $e) {
		$error_message = 'Gagal menghapus user: ' . $e->getMessage();
	}
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Absensi PKL</title>
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
        
        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
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
        
        /* Admin Actions Section */
        .admin-actions {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
            border: 1px solid var(--border-color);
        }
        
        .admin-actions h2 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .admin-actions h2::before {
            content: '⚙️';
            font-size: 28px;
        }
        
        /* Attendance Form Section */
        .attendance-form-section {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        
        .attendance-form-section h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .attendance-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-now {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all var(--transition-speed) ease;
        }
        
        .btn-now:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 16px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-clear {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-clear:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Responsive form */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-md);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .action-btn i {
            font-size: 24px;
        }
        
        /* Success/Error Messages */
        .success-message,
        .error-message {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 600;
            animation: slideIn 0.5s ease;
        }
        
        .success-message {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
        }
        
        .error-message {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
        }
        
        .success-message i,
        .error-message i {
            font-size: 20px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            
            .action-buttons {
                grid-template-columns: 1fr;
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
                <p>Admin Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li class="active"><a href="admin_dashboard.php"><i>📊</i> <span>Dashboard</span></a></li>
                <li><a href="rekap_absen.php"><i>📋</i> <span>Rekap Absen</span></a></li>
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
                <h1>Dashboard Admin</h1>
                <div class="user-info">
                    <span>Halo, <?php echo $_SESSION['nama_lengkap']; ?></span>
                    <?php if (!empty($_SESSION['foto_profil'])): ?>
                        <img src="<?php echo $_SESSION['foto_profil']; ?>" alt="Profile" class="profile-img">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nama_lengkap']); ?>&background=random" alt="Profile" class="profile-img">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pesan Sukses/Error -->
            <?php if (isset($success_message)): ?>
                <div class="success-message">
                    <i>✅</i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i>❌</i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

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
                            <i>👥</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p>Total User</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>✓</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['hadir_hari_ini']; ?></h3>
                            <p>Hadir Hari Ini</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>⏰</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['terlambat_hari_ini']; ?></h3>
                            <p>Terlambat Hari Ini</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i>✗</i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['alpha_hari_ini']; ?></h3>
                            <p>Alpha Hari Ini</p>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div class="admin-actions">
                    <h2>Admin Actions</h2>
                    
                    <!-- Form Input Kehadiran -->
                    <div class="attendance-form-section">
                        <h3>📝 Input Kehadiran Manual</h3>
                        <form method="POST" class="attendance-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="user_id">Pilih User:</label>
                                    <select name="user_id" id="user_id" required>
                                        <option value="">-- Pilih User --</option>
                                        <?php
                                        $stmt = $pdo->query("SELECT id, username, nama_lengkap FROM users WHERE role = 'user' ORDER BY nama_lengkap");
                                        while ($user = $stmt->fetch()) {
                                            echo "<option value='{$user['id']}'>{$user['nama_lengkap']} ({$user['username']})</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="tanggal">Tanggal:</label>
                                    <input type="date" name="tanggal" id="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="jam_masuk">Jam Masuk:</label>
                                    <input type="time" name="jam_masuk" id="jam_masuk" required>
                                    <button type="button" class="btn-now" onclick="setCurrentTime('jam_masuk')">Waktu Sekarang</button>
                                </div>
                                <div class="form-group">
                                    <label for="jam_keluar">Jam Keluar:</label>
                                    <input type="time" name="jam_keluar" id="jam_keluar">
                                    <button type="button" class="btn-now" onclick="setCurrentTime('jam_keluar')">Waktu Sekarang</button>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="status">Status:</label>
                                    <select name="status" id="status" required>
                                        <option value="hadir">Hadir</option>
                                        <option value="terlambat">Terlambat</option>
                                        <option value="izin">Izin</option>
                                        <option value="sakit">Sakit</option>
                                        <option value="alpha">Alpha</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="keterangan">Keterangan:</label>
                                    <input type="text" name="keterangan" id="keterangan" placeholder="Opsional">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="input_kehadiran" class="btn-submit">💾 Simpan Kehadiran</button>
                                <button type="button" class="btn-clear" onclick="clearForm()">🗑️ Bersihkan Form</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Kelola User -->
                    <div class="attendance-form-section">
                        <h3>👥 Kelola User</h3>
                        <!-- Tambah User -->
                        <form method="POST" class="attendance-form" style="margin-bottom:16px;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_username">Username</label>
                                    <input type="text" id="new_username" name="new_username" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_nama_lengkap">Nama Lengkap</label>
                                    <input type="text" id="new_nama_lengkap" name="new_nama_lengkap" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_asal_sekolah">Asal Sekolah</label>
                                    <input type="text" id="new_asal_sekolah" name="new_asal_sekolah" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_tanggal_lahir">Tanggal Lahir</label>
                                    <input type="date" id="new_tanggal_lahir" name="new_tanggal_lahir" required>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="tambah_user" class="btn-submit">➕ Tambah User</button>
                            </div>
                        </form>
                        
                        <!-- Daftar User -->
                        <div class="table-container" style="overflow:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">No</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">Nama</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">Username</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">Asal Sekolah</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">Tanggal Lahir</th>
                                        <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border-color);">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $list = $pdo->query("SELECT id, username, nama_lengkap, asal_sekolah, ttl FROM users WHERE role='user' ORDER BY nama_lengkap");
                                    $no = 1;
                                    while ($u = $list->fetch()) {
                                        echo '<tr>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">' . $no++ . '</td>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">' . htmlspecialchars($u['nama_lengkap']) . '</td>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">' . htmlspecialchars($u['username']) . '</td>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">' . htmlspecialchars($u['asal_sekolah']) . '</td>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">' . htmlspecialchars($u['ttl']) . '</td>';
                                        echo '<td style="padding:12px; border-bottom:1px solid var(--border-color);">';
                                        echo '<form method="POST" onsubmit="return confirm(\'Yakin hapus user ini?\');" style="display:inline-block;">';
                                        echo '<input type="hidden" name="hapus_user_id" value="' . (int)$u['id'] . '">';
                                        echo '<button type="submit" name="hapus_user" class="btn-clear" style="padding:8px 12px;">🗑️ Hapus</button>';
                                        echo '</form>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="rekap_absen.php" class="action-btn">
                            <i>📋</i>
                            <span>Lihat Rekap Absen</span>
                        </a>
                        <a href="profil.php" class="action-btn">
                            <i>👤</i>
                            <span>Kelola Profil</span>
                        </a>
                        <a href="login.php" class="action-btn">
                            <i>🚪</i>
                            <span>Logout</span>
                        </a>
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
        
        // Fungsi untuk set waktu sekarang ke input
        function setCurrentTime(inputId) {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            
            document.getElementById(inputId).value = `${hours}:${minutes}:${seconds}`;
        }
        
        // Auto-set waktu sekarang saat halaman dimuat
        window.addEventListener('load', function() {
            // Set default waktu untuk jam masuk (waktu sekarang)
            setCurrentTime('jam_masuk');
        });
        
        // Fungsi untuk clear form
        function clearForm() {
            document.getElementById('user_id').value = '';
            document.getElementById('tanggal').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('jam_masuk').value = '';
            document.getElementById('jam_keluar').value = '';
            document.getElementById('status').value = 'hadir';
            document.getElementById('keterangan').value = '';
            
            // Set ulang waktu sekarang
            setCurrentTime('jam_masuk');
        }
        
        // Auto-update waktu setiap detik untuk input yang sedang aktif
        setInterval(function() {
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.id === 'jam_masuk' || activeElement.id === 'jam_keluar')) {
                // Jika input waktu sedang aktif, update dengan waktu sekarang
                setCurrentTime(activeElement.id);
            }
        }, 1000);
        
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