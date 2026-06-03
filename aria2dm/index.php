<?php
session_start();

$auth_file = __DIR__ . '/auth.json';
// Hasil hash SHA-256 untuk 'admin'
$default_hash = '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918';

// 1. Ambil hash aktif (Dari file json jika ada, jika tidak gunakan default)
$current_hash = $default_hash;
if (file_exists($auth_file)) {
    $auth_data = json_decode(file_get_contents($auth_file), true);
    if (isset($auth_data['password_hash'])) {
        $current_hash = $auth_data['password_hash'];
    }
}

// 2. Handle aksi logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 3. Handle aksi login
if (isset($_POST['login'])) {
    $password = $_POST['password'];
    
    // Verifikasi menggunakan hash aktif
    if (hash('sha256', $password) === $current_hash) {
        $_SESSION['logged_in'] = true;
        
        // Deteksi apakah masih menggunakan password default
        if ($current_hash === $default_hash) {
            $_SESSION['require_password_change'] = true;
        } else {
            $_SESSION['require_password_change'] = false;
        }
        
        header("Location: index.php");
        exit;
    } else {
        $error = "Password salah!";
    }
}

// 4. Handle aksi ganti password baru (Pertama kali login)
if (isset($_POST['change_password']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password)) {
        $change_error = "Password tidak boleh kosong!";
    } elseif ($new_password !== $confirm_password) {
        $change_error = "Konfirmasi password tidak cocok!";
    } else {
        // Simpan hash baru ke file JSON
        $new_hash = hash('sha256', $new_password);
        file_put_contents($auth_file, json_encode(['password_hash' => $new_hash]));
        
        // Bebaskan akses untuk masuk ke Dashboard
        $_SESSION['require_password_change'] = false;
        header("Location: index.php");
        exit;
    }
}

// =========================================================================
// TAMPILAN ANTARMUKA
// =========================================================================

// A. Jika belum login -> Tampilkan Form Login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Login - Aria2 Download Manager</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-[#18181c] text-white flex items-center justify-center h-screen">
        <div class="bg-[#202026] p-8 rounded-xl shadow-lg w-96 border border-gray-800">
            <h2 class="text-2xl mb-6 text-center font-semibold"><i class="fa-solid fa-cloud-arrow-down text-cyan-500 mr-2"></i>Manager Login</h2>
            <?php if(isset($error)) echo "<p class='text-red-400 mb-4 text-sm bg-red-950/40 border border-red-800 p-2.5 rounded flex items-center'><i class='fa-solid fa-circle-exclamation mr-2'></i> $error</p>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Masukkan Password" 
                       class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 mb-5 text-white" required>
                <button type="submit" name="login" 
                        class="w-full p-3 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-lg font-bold hover:opacity-90 shadow-lg transition-opacity">
                    Login
                </button>
            </form>
        </div>
    </body>
    </html>
<?php
    exit;
}

// B. Jika sudah login TAPI masih menggunakan password default -> Paksa ganti password
if (isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true) {
?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Ganti Password - Aria2 Download Manager</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-[#121216] text-white flex items-center justify-center h-screen relative">
        <div class="absolute inset-0 bg-black/60 z-0"></div>
        
        <div class="bg-[#202026] p-8 rounded-xl shadow-2xl shadow-cyan-500/10 w-[400px] border border-gray-700 z-10 relative">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-cyan-900/30 text-cyan-400 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl border border-cyan-800 shadow-inner">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h2 class="text-xl font-bold text-white">Keamanan Sistem</h2>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed">Anda masuk menggunakan password bawaan sistem. Harap buat password baru yang aman untuk melanjutkan ke Dashboard.</p>
            </div>
            
            <?php if(isset($change_error)) echo "<p class='text-red-400 mb-4 text-sm bg-red-950/40 border border-red-800 p-3 rounded-lg flex items-center'><i class='fa-solid fa-triangle-exclamation mr-2'></i> $change_error</p>"; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1">Password Baru:</label>
                    <input type="password" name="new_password" placeholder="Ketik password baru" 
                           class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 text-white text-sm transition-colors" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1">Konfirmasi Password Baru:</label>
                    <input type="password" name="confirm_password" placeholder="Ulangi password baru" 
                           class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 text-white text-sm transition-colors" required>
                </div>
                <button type="submit" name="change_password" 
                        class="w-full p-3 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-lg font-bold hover:opacity-90 mt-2 shadow-lg transition-opacity">
                    Simpan & Lanjutkan <i class="fa-solid fa-arrow-right ml-1"></i>
                </button>
            </form>
        </div>
    </body>
    </html>
<?php
    exit;
}

// C. Jika sudah login & Password valid -> Buka Dashboard
require 'dashboard.php';
?>
