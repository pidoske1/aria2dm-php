<?php
session_start();

require_once __DIR__ . '/AriaManager.php';
$manager = new AriaManager();

if (isset($_POST['change_lang_login'])) {
    $selected_lang = $_POST['lang_code'];
    $_SESSION['lang'] = $selected_lang;
    $manager->updateLanguage($selected_lang);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$settings = $manager->getSettings();
$current_lang = $_SESSION['lang'] ?? $settings['language'] ?? 'en';
$available_langs = $manager->getAvailableLanguages();

$i18n = [
    'id' => [
        'login_title' => 'Login',
        'invalid_password' => 'Password salah!',
        'enter_password' => 'Masukkan Password',
        'btn_login' => 'Masuk',
        'sys_security' => 'Keamanan Sistem',
        'sec_desc' => 'Anda masuk menggunakan password bawaan sistem. Harap buat password baru yang aman untuk melanjutkan ke Dashboard.',
        'err_empty' => 'Password tidak boleh kosong!',
        'err_match' => 'Konfirmasi password tidak cocok!',
        'new_pass' => 'Password Baru:',
        'type_new' => 'Ketik password baru',
        'confirm_pass' => 'Konfirmasi Password Baru:',
        'type_confirm' => 'Ulangi password baru',
        'btn_save' => 'Simpan & Lanjutkan',
        'language' => 'Bahasa:'
    ],
    'en' => [
        'login_title' => 'Login',
        'invalid_password' => 'Invalid password!',
        'enter_password' => 'Enter Password',
        'btn_login' => 'Login',
        'sys_security' => 'System Security',
        'sec_desc' => 'You logged in using the default system password. Please create a new secure password to proceed to the Dashboard.',
        'err_empty' => 'Password cannot be empty!',
        'err_match' => 'Password confirmation does not match!',
        'new_pass' => 'New Password:',
        'type_new' => 'Type new password',
        'confirm_pass' => 'Confirm New Password:',
        'type_confirm' => 'Repeat new password',
        'btn_save' => 'Save & Continue',
        'language' => 'Language:'
    ]
];

$t = $i18n[$current_lang] ?? $i18n['en'];

$auth_file = __DIR__ . '/auth.json';
$default_hash = '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918';

$current_hash = $default_hash;
if (file_exists($auth_file)) {
    $auth_data = json_decode(file_get_contents($auth_file), true);
    if (isset($auth_data['password_hash'])) {
        $current_hash = $auth_data['password_hash'];
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_POST['login'])) {
    $password = $_POST['password'];
    
    if (hash('sha256', $password) === $current_hash) {
        $_SESSION['logged_in'] = true;
        
        if ($current_hash === $default_hash) {
            $_SESSION['require_password_change'] = true;
        } else {
            $_SESSION['require_password_change'] = false;
        }
        
        header("Location: index.php");
        exit;
    } else {
        $error = $t['invalid_password'];
    }
}

if (isset($_POST['change_password']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password)) {
        $change_error = $t['err_empty'];
    } elseif ($new_password !== $confirm_password) {
        $change_error = $t['err_match'];
    } else {
        $new_hash = hash('sha256', $new_password);
        file_put_contents($auth_file, json_encode(['password_hash' => $new_hash]));
        
        $_SESSION['require_password_change'] = false;
        header("Location: index.php");
        exit;
    }
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="<?= htmlspecialchars($current_lang) ?>">
    <head>
        <meta charset="UTF-8">
        <title><?= $t['login_title'] ?> - Aria2 Download Manager</title>
        <script src="https://cdn.tailwindcss.com"></script>
		<link rel="icon" type="image/png" href="icon.png">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-[#18181c] text-white flex items-center justify-center h-screen">
        <div class="bg-[#202026] p-8 rounded-xl shadow-lg w-96 border border-gray-800">
            <h2 class="text-2xl mb-6 text-center font-semibold"><i class="fa-solid fa-cloud-arrow-down text-cyan-500 mr-2"></i><?= $t['login_title'] ?></h2>
            
            <?php if(isset($error)) echo "<p class='text-red-400 mb-4 text-sm bg-red-950/40 border border-red-800 p-2.5 rounded flex items-center'><i class='fa-solid fa-circle-exclamation mr-2'></i> $error</p>"; ?>
            
            <form method="POST">
                <input type="password" name="password" placeholder="<?= $t['enter_password'] ?>" 
                       class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 mb-5 text-white" required>
                <button type="submit" name="login" 
                        class="w-full p-3 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-lg font-bold hover:opacity-90 shadow-lg transition-opacity">
                    <?= $t['btn_login'] ?>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-gray-700/50 flex justify-center">
                <form method="POST" class="flex items-center space-x-2">
                    <label class="text-xs text-gray-400 font-semibold"><i class="fa-solid fa-globe mr-1 text-cyan-400"></i> <?= $t['language'] ?></label>
                    <select name="lang_code" onchange="this.form.submit()" class="bg-[#18181c] text-gray-300 text-xs px-2 py-1 rounded border border-gray-700 focus:outline-none focus:border-cyan-500 font-semibold cursor-pointer">
                        <?php foreach($available_langs as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $code === $current_lang ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="change_lang_login" value="1">
                </form>
            </div>
        </div>
    </body>
    </html>
<?php
    exit;
}

if (isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true) {
?>
    <!DOCTYPE html>
    <html lang="<?= htmlspecialchars($current_lang) ?>">
    <head>
        <meta charset="UTF-8">
        <title><?= $t['sys_security'] ?> - Aria2 Download Manager</title>
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
                <h2 class="text-xl font-bold text-white"><?= $t['sys_security'] ?></h2>
                <p class="text-xs text-gray-400 mt-2 leading-relaxed"><?= $t['sec_desc'] ?></p>
            </div>
            
            <?php if(isset($change_error)) echo "<p class='text-red-400 mb-4 text-sm bg-red-950/40 border border-red-800 p-3 rounded-lg flex items-center'><i class='fa-solid fa-triangle-exclamation mr-2'></i> $change_error</p>"; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= $t['new_pass'] ?></label>
                    <input type="password" name="new_password" placeholder="<?= $t['type_new'] ?>" 
                           class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 text-white text-sm transition-colors" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= $t['confirm_pass'] ?></label>
                    <input type="password" name="confirm_password" placeholder="<?= $t['type_confirm'] ?>" 
                           class="w-full p-3 bg-[#18181c] border border-gray-700 rounded-lg focus:outline-none focus:border-cyan-500 text-white text-sm transition-colors" required>
                </div>
                <button type="submit" name="change_password" 
                        class="w-full p-3 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-lg font-bold hover:opacity-90 mt-2 shadow-lg transition-opacity">
                    <?= $t['btn_save'] ?> <i class="fa-solid fa-arrow-right ml-1"></i>
                </button>
            </form>
        </div>
    </body>
    </html>
<?php
    exit;
}

require 'dashboard.php';
?>
