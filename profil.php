<?php
include 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Update profil jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_FILES['foto_profil'])) {
    $nama_lengkap = $_POST['nama_lengkap'];
    $asal_sekolah = $_POST['asal_sekolah'];
    $ttl = $_POST['ttl'];
    
    // Jika password diisi, update password juga
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, asal_sekolah = ?, ttl = ?, password = ? WHERE id = ?");
        $stmt->execute([$nama_lengkap, $asal_sekolah, $ttl, $password, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap = ?, asal_sekolah = ?, ttl = ? WHERE id = ?");
        $stmt->execute([$nama_lengkap, $asal_sekolah, $ttl, $_SESSION['user_id']]);
    }
    
    $_SESSION['nama_lengkap'] = $nama_lengkap;
    $success = "Profil berhasil diperbarui!";
    
    // Ambil data user yang sudah diupdate
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Proses upload foto profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_profil'])) {
    $target_dir = "uploads/profil/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Generate nama file unik
    $file_extension = pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION);
    $new_filename = "user_" . $_SESSION['user_id'] . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Cek ukuran file (maksimal 2MB)
    if ($_FILES["foto_profil"]["size"] > 2000000) {
        $upload_error = "Ukuran file terlalu besar. Maksimal 2MB.";
    } 
    // Cek tipe file
    else if (!in_array($file_extension, ["jpg", "jpeg", "png", "gif"])) {
        $upload_error = "Hanya file JPG, JPEG, PNG & GIF yang diizinkan.";
    } 
    // Upload file
    else if (move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $target_file)) {
        // Hapus foto lama jika ada
        if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])) {
            unlink($user['foto_profil']);
        }
        
        // Update database dengan path foto profil
        $stmt = $pdo->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
        $stmt->execute([$target_file, $_SESSION['user_id']]);
        
        $upload_success = "Foto profil berhasil diupload.";
        
        // Refresh data user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $_SESSION['foto_profil'] = $target_file;
    } else {
        $upload_error = "Terjadi kesalahan saat mengupload file.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Sistem Absensi PKL</title>
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
        
        /* Enhanced Profile Form Styles */
        .profile-form {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
            border: 1px solid var(--border-color);
        }
        
        .profile-form h2 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .profile-form h2::before {
            content: '👤';
            font-size: 28px;
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
        
        .form-group input[type="text"], .form-group input[type="password"] {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
        }
        
        .form-group input[type="text"]:focus, .form-group input[type="password"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-update {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .success {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            border: none;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .error {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
            border: none;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        /* Enhanced Photo Upload Section */
        .photo-upload-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 32px;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
            border: 1px solid var(--border-color);
        }
        
        .photo-upload-section h3 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .photo-upload-section h3::before {
            content: '📷';
            font-size: 28px;
        }
        
        .current-photo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .current-photo img {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
            transition: transform var(--transition-speed) ease;
        }
        
        .current-photo img:hover {
            transform: scale(1.05);
        }
        
        .photo-upload-form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .file-input {
            margin-bottom: 20px;
            width: 100%;
            max-width: 400px;
        }
        
        .file-input input[type="file"] {
            width: 100%;
            padding: 16px;
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
            cursor: pointer;
        }
        
        .file-input input[type="file"]:hover {
            border-color: var(--primary-color);
            background: var(--bg-tertiary);
        }
        
        .btn-upload {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
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
            
            .form-group input[type="text"], .form-group input[type="password"] {
                font-size: 16px;
            }
            
            .profile-form, .photo-upload-section {
                padding: 24px;
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
                <li><a href="rekap_absen.php"><i>📋</i> <span><?php echo $_SESSION['role'] == 'admin' ? 'Rekap Absen' : 'Rekap Absen Saya'; ?></span></a></li>
                <li class="active"><a href="profil.php"><i>👤</i> <span>Profil</span></a></li>
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
                <h1>Profil User</h1>
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
                <!-- Form Update Profil -->
                <div class="profile-form">
                    <h2>Update Profil</h2>
                    
                    <?php if (isset($success)): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="nama_lengkap">Nama Lengkap:</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="asal_sekolah">Asal Sekolah:</label>
                            <input type="text" id="asal_sekolah" name="asal_sekolah" value="<?php echo htmlspecialchars($user['asal_sekolah']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="ttl">Tempat, Tanggal Lahir:</label>
                            <input type="text" id="ttl" name="ttl" value="<?php echo htmlspecialchars($user['ttl']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password Baru (kosongkan jika tidak ingin mengubah):</label>
                            <input type="password" id="password" name="password">
                        </div>
                        
                        <button type="submit" class="btn-update">Update Profil</button>
                    </form>
                </div>

                <!-- Upload Foto Profil -->
                <div class="photo-upload-section">
                    <h3>Foto Profil</h3>
                    
                    <?php if (isset($upload_success)): ?>
                        <div class="success"><?php echo $upload_success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($upload_error)): ?>
                        <div class="error"><?php echo $upload_error; ?></div>
                    <?php endif; ?>
                    
                    <div class="current-photo">
                        <?php if (!empty($user['foto_profil'])): ?>
                            <img src="<?php echo $user['foto_profil']; ?>" alt="Foto Profil" id="preview-image">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['nama_lengkap']); ?>&background=random&size=180" alt="Foto Profil" id="preview-image">
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="photo-upload-form">
                        <div class="file-input">
                            <input type="file" name="foto_profil" id="foto_profil" accept="image/*">
                        </div>
                        <button type="submit" name="upload_photo" class="btn-upload">Upload Foto</button>
                    </form>
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
        
        // Preview image sebelum upload
        document.getElementById('foto_profil').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>