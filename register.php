<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Inisialisasi semua variabel dengan nilai default
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $nama_lengkap = isset($_POST['nama_lengkap']) ? $_POST['nama_lengkap'] : '';
    $asal_sekolah = isset($_POST['asal_sekolah']) ? $_POST['asal_sekolah'] : '';
    $tempat_lahir = isset($_POST['tempat_lahir']) ? $_POST['tempat_lahir'] : '';
    $tanggal_lahir = isset($_POST['ttl']) ? $_POST['ttl'] : '';
    
    // Validasi input
    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($asal_sekolah) || empty($tempat_lahir) || empty($tanggal_lahir)) {
        $error = "Semua field harus diisi!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Validasi format tanggal
        if (!strtotime($tanggal_lahir)) {
            $error = "Format tanggal lahir tidak valid!";
        } else {
            // Simpan hanya tanggal lahir saja
            $ttl = $tanggal_lahir;
            
            // Gabungkan tempat lahir dengan asal sekolah
            $asal_sekolah_with_place = $asal_sekolah . ' (Tempat Lahir: ' . $tempat_lahir . ')';
            
            // Debug: lihat nilai yang akan dikirim
            error_log("Debug - Tempat Lahir: " . $tempat_lahir);
            error_log("Debug - Tanggal Lahir: " . $tanggal_lahir);
            error_log("Debug - TTL Final: " . $ttl);
            error_log("Debug - Asal Sekolah: " . $asal_sekolah_with_place);
            
            // Cek apakah username sudah ada
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = "Username sudah digunakan!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, asal_sekolah, ttl, role) VALUES (?, ?, ?, ?, ?, 'user')");
                
                if ($stmt->execute([$username, $hashed_password, $nama_lengkap, $asal_sekolah_with_place, $ttl])) {
                    $success = "Registrasi berhasil! Silakan login.";
                } else {
                    $error = "Terjadi kesalahan saat registrasi.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sistem Absensi PKL</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            transition: all var(--transition-speed) ease;
            overflow-x: hidden;
        }
        
        .register-container {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-xl);
            width: 500px;
            padding: 48px;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            margin: 20px;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, var(--bg-overlay), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }
        
        .register-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 12px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .register-header p {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .form-group input {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            box-sizing: border-box;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
        }
        
        .form-group input[type="date"] {
            font-family: inherit;
            cursor: pointer;
        }
        
        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-md);
            position: relative;
            z-index: 1;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .error {
            background: linear-gradient(135deg, #fa709a, #fee140);
            color: white;
            text-align: center;
            margin-bottom: 20px;
            padding: 16px 20px;
            border-radius: 12px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .success {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            text-align: center;
            margin-bottom: 20px;
            padding: 16px 20px;
            border-radius: 12px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .login-link {
            text-align: center;
            margin-top: 24px;
            position: relative;
            z-index: 1;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-speed) ease;
        }
        
        .login-link a:hover {
            color: var(--secondary-color);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-overlay);
            border: none;
            color: var(--text-white);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            backdrop-filter: blur(10px);
            font-size: 20px;
            z-index: 1000;
        }
        
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .register-container {
                width: 90%;
                padding: 32px 24px;
                margin: 20px;
            }
            
            .register-header h2 {
                font-size: 28px;
            }
            
            .form-group input {
                font-size: 16px;
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
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
        <i>🌙</i>
    </button>
    
    <div class="register-container">
        <div class="register-header">
            <h2>📝 Register</h2>
            <p>Daftar Akun Baru</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="asal_sekolah">Asal Sekolah</label>
                <input type="text" id="asal_sekolah" name="asal_sekolah" value="<?php echo isset($_POST['asal_sekolah']) ? htmlspecialchars($_POST['asal_sekolah']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="tempat_lahir">Tempat Lahir</label>
                <input type="text" id="tempat_lahir" name="tempat_lahir" placeholder="Contoh: Jakarta" value="<?php echo isset($_POST['tempat_lahir']) ? htmlspecialchars($_POST['tempat_lahir']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="ttl">Tanggal Lahir</label>
                <input type="date" id="ttl" name="ttl" value="<?php echo isset($_POST['ttl']) ? htmlspecialchars($_POST['ttl']) : ''; ?>" required>
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="login-link">
            <p>Sudah punya akun? <a href="login.php">Login disini</a></p>
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
        
        // Add focus effects to form inputs
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>