<?php
if (!isset($_SESSION['logged_in'])) { header("Location: index.php"); exit; }
require_once 'AriaManager.php';
$manager = new AriaManager();

$error_msg = "";
$success_msg = "";

$settings = $manager->getSettings();
$current_lang_code = $_SESSION['lang'] ?? $settings['language'] ?? 'en';
$lang = $manager->getLanguageData($current_lang_code);
$available_langs = $manager->getAvailableLanguages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_lang'])) {
        $_SESSION['lang'] = $_POST['lang'];
        $manager->updateLanguage($_POST['lang']);
        header("Location: index.php"); exit;
    }
    elseif (isset($_POST['update_limit'])) {
        $manager->updateGlobalLimit($_POST['max_download_limit']);
        $success_msg = $lang['success_limit'] ?? "Limit Global berhasil diperbarui.";
    }
    elseif (isset($_POST['submit_new_download'])) {
        $url = $_POST['new_dl_url'];
        $category = $_POST['new_dl_category'];
        $action = $_POST['submit_new_download']; 
        
        $customOptions = [];
        if (!empty($_POST['new_dl_dir'])) $customOptions['dir'] = $_POST['new_dl_dir'];
        if (!empty($_POST['new_dl_out'])) $customOptions['out'] = $_POST['new_dl_out'];
        if (!empty($_POST['new_dl_speed'])) $customOptions['max-download-limit'] = $_POST['new_dl_speed'];
        
        if ($action === 'add') {
            $customOptions['pause'] = 'true';
        }

        $res = $manager->addDownload($url, $category, $customOptions);
        if ($res === true) $success_msg = $lang['success_add'] ?? "Berhasil menambah antrean download.";
        else $error_msg = is_string($res) ? $res : ($lang['error_add'] ?? "Gagal menambah unduhan.");
    } 
    elseif (isset($_POST['edit_url_action'])) {
        $res = $manager->editDownloadUrl($_POST['edit_gid'], $_POST['new_url']);
        if ($res === true) $success_msg = $lang['success_edit'] ?? "URL berhasil diperbarui.";
        else $error_msg = is_string($res) ? $res : ($lang['error_edit'] ?? "Gagal memperbarui URL.");
    }
    elseif (isset($_POST['change_speed_action'])) {
        $speed = $_POST['item_speed_limit'];
        $hasError = false;
        if (!empty($_POST['speed_gids'])) {
            foreach ($_POST['speed_gids'] as $gid) {
                $res = $manager->changeDownloadSpeed($gid, $speed);
                if ($res !== true) $hasError = $res;
            }
        }
        if ($hasError) $error_msg = is_string($hasError) ? $hasError : ($lang['error_speed'] ?? "Gagal merubah speed.");
        else $success_msg = $lang['success_speed'] ?? "Speed limit file diperbarui.";
    }
    elseif (isset($_POST['save_settings'])) {
        $confPath = $_POST['conf_path'];
        $confContent = $_POST['conf_content'];
        $confDir = dirname($confPath);
        
        preg_match('/\/home\/([^\/]+)\//', $confPath, $u_match);
        $cmd_user = $u_match[1] ?? (exec('whoami') ?: 'stb');
        
        $dlDir = '';
        if (preg_match('/^dir=(.+)$/m', $confContent, $matches)) {
            $dlDir = trim($matches[1]);
        }

        $save_set = $manager->updateSettings($_POST['rpc_url'], $_POST['secret']);
        $save_conf = $manager->updateAriaConfig($_POST['conf_path'], $_POST['conf_content']);
        $_SESSION['open_modal'] = 'settings-modal';

        $permission_error = false;

        if (!is_dir($confDir) && !@mkdir($confDir, 0777, true)) { $permission_error = true; }
        elseif (@file_put_contents($confPath, $confContent) === false) { $permission_error = true; }

        if (!$permission_error && preg_match('/^input=(.+)$/m', $confContent, $matches)) {
            $sess = trim($matches[1]); $sDir = dirname($sess);
            if (!is_dir($sDir) && !@mkdir($sDir, 0777, true)) { $permission_error = true; }
            elseif (!file_exists($sess) && @touch($sess) === false) { $permission_error = true; }
        }

        if (!$permission_error && !empty($dlDir)) {
            if (!is_dir($dlDir) && !@mkdir($dlDir, 0777, true)) { $permission_error = true; }
            elseif (!is_writable($dlDir)) { $permission_error = true; }
        }

        if (!$save_conf || !$save_set) {
            $webDir = __DIR__;
            $error_msg = "<div class='font-bold mb-1'>Gagal menyimpan file aria2.json internal!</div>" . 
                         "<div class='text-xs text-gray-300 mb-2'>Web Server (www-data) tidak memiliki izin tulis di folder web. Jalankan perintah berikut:</div>" . 
                         "<textarea readonly class='w-full bg-[#0a0a0c] p-3 rounded text-left font-mono text-[11px] leading-relaxed text-yellow-400 border border-yellow-800/50 outline-none resize-none mt-2' rows='2' onclick='this.select()'>sudo chown -R www-data:www-data " . $webDir . "</textarea>";
        } elseif ($permission_error) {
            $fix_script = "sudo groupadd aria2cfg\n";
            $fix_script .= "sudo usermod -aG aria2cfg www-data\n";
            $fix_script .= "sudo usermod -aG aria2cfg " . $cmd_user . "\n\n";
            
            $fix_script .= "sudo mkdir -p " . $confDir . "\n";
            $fix_script .= "sudo chgrp -R aria2cfg " . $confDir . "\n";
            $fix_script .= "sudo chmod -R 2775 " . $confDir . "\n\n";
            
            if (!empty($dlDir)) {
                $fix_script .= "sudo mkdir -p " . $dlDir . "\n";
                $fix_script .= "sudo chgrp -R aria2cfg " . $dlDir . "\n";
                $fix_script .= "sudo chmod -R 2775 " . $dlDir;
            }

            $error_msg = "<div class='font-bold mb-1'>" . ($lang['error_permission'] ?? "Akses Ditolak (Folder Target)!") . "</div>" . 
                         "<textarea readonly class='w-full bg-[#0a0a0c] p-3 rounded text-left font-mono text-[11px] leading-relaxed text-yellow-400 border border-yellow-800/50 outline-none resize-none mt-2' rows='11' onclick='this.select()'>" . 
                         htmlspecialchars($fix_script) . "</textarea>";
        } else {
            $success_msg = $lang['success_settings'] ?? "Konfigurasi tersimpan dan folder/file sukses dibuat.";
        }
    }
    elseif (isset($_POST['start_service'])) {
        if ($manager->startAria2Service()) {
            $success_msg = $lang['success_start'] ?? "Service Aria2c berhasil dihidupkan.";
        } else {
            $error_msg = $lang['error_start'] ?? "Gagal menjalankan service. Cek config port / path aria2.";
        }
        $_SESSION['open_modal'] = 'settings-modal'; 
    }
    elseif (isset($_POST['stop_service'])) {
        if ($manager->stopAria2Service()) {
            $success_msg = $lang['success_stop'] ?? "Service Aria2c berhasil dimatikan.";
        } else {
            $error_msg = $lang['error_stop'] ?? "Gagal mematikan service aria2c.";
        }
        $_SESSION['open_modal'] = 'settings-modal'; 
    }
    // PERBAIKAN NOTIFIKASI BAHASA UNTUK AKSI MASAL (RESUME/STOP/DELETE)
    elseif (isset($_POST['action']) && isset($_POST['selected_ids'])) {
        $action = $_POST['action'];
        $count = count($_POST['selected_ids']);
        $hasError = false;

        foreach ($_POST['selected_ids'] as $gid) {
            if ($action === 'resume') {
                $res = $manager->resumeDownload($gid);
                if ($res !== true) $hasError = $res;
            }
            if ($action === 'stop') {
                $manager->stopDownload($gid);
            }
            if ($action === 'delete') {
                $deletePhysical = isset($_POST['delete_physical']) && $_POST['delete_physical'] === 'yes';
                $manager->deleteDownload($gid, $deletePhysical);
            }
        }

        // Menggunakan str_replace untuk memasukkan angka ($count) ke dalam teks terjemahan JSON
        if ($hasError && $action === 'resume') {
            $error_msg = is_string($hasError) ? $hasError : str_replace('{count}', $count, $lang['error_resume_bulk'] ?? "Gagal me-resume {count} item.");
        } else {
            if ($action === 'resume') $success_msg = str_replace('{count}', $count, $lang['success_resume_bulk'] ?? "{count} item berhasil dilanjutkan.");
            if ($action === 'stop') $success_msg = str_replace('{count}', $count, $lang['success_stop_bulk'] ?? "{count} item berhasil dihentikan.");
            if ($action === 'delete') $success_msg = str_replace('{count}', $count, $lang['success_delete_bulk'] ?? "{count} item berhasil dihapus.");
        }
    }
    
    if (!empty($success_msg)) $_SESSION['toast_success'] = $success_msg;
    if (!empty($error_msg)) $_SESSION['toast_error'] = $error_msg;
    
    header("Location: index.php");
    exit;
}

$success_msg = $_SESSION['toast_success'] ?? "";
$error_msg = $_SESSION['toast_error'] ?? "";
$open_modal_id = $_SESSION['open_modal'] ?? "";

unset($_SESSION['toast_success'], $_SESSION['toast_error'], $_SESSION['open_modal']);

$is_running = $manager->isServiceRunning();
$downloads = $manager->getDownloads();
$ariaConfig = $manager->getAriaConfig();

$current_conf_path = $ariaConfig['conf_path'] ?? '';
preg_match('/\/home\/([^\/]+)\//', $current_conf_path, $u_match);
$conf_user = $u_match[1] ?? (exec('whoami') ?: 'stb');

preg_match('/^dir=(.+)$/m', $ariaConfig['conf_content'] ?? '', $d_match);
$conf_dir = trim($d_match[1] ?? '/mnt/Downloads');

function __($key) {
    global $lang; return $lang[$key] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang_code) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aria2 Download Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #121216; color: #9ca3af; }
        .panel { background-color: #1a1a20; }
        .gradient-btn { background: linear-gradient(90deg, #06b6d4 0%, #a855f7 100%); }
        .progress-bar-fill { background: linear-gradient(90deg, #06b6d4 0%, #a855f7 100%); }
        .modal { display: none; background: rgba(0, 0, 0, 0.7); }
    </style>
    <script>
        let currentCategory = 'All';

        function filterCategory(cat, element) {
            currentCategory = cat;
            document.querySelectorAll('.sidebar-cat').forEach(el => {
                el.classList.remove('bg-[#26262e]', 'text-white');
                el.classList.add('text-gray-400', 'hover:bg-[#26262e]', 'hover:text-white');
            });
            element.classList.add('bg-[#26262e]', 'text-white');
            element.classList.remove('text-gray-400', 'hover:bg-[#26262e]', 'hover:text-white');
            applyCategoryFilter();
        }

        function applyCategoryFilter() {
            const searchTerm = document.getElementById('searchInput') ? document.getElementById('searchInput').value.toLowerCase() : '';
            const rows = document.querySelectorAll('tr.task-row');
            
            rows.forEach(row => {
                const fileName = row.querySelector('.file-name-col').innerText.toLowerCase();
                const matchCat = (currentCategory === 'All' || row.dataset.category === currentCategory);
                const matchSearch = fileName.includes(searchTerm);
                
                row.style.display = (matchCat && matchSearch) ? '' : 'none';
            });
        }

        setInterval(() => {
            const checkedBoxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            const hasModal = document.querySelector('.modal[style*="display: flex"]');
            
            if(checkedBoxes.length === 0 && !hasModal) {
                fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTbody = doc.querySelector('#table-body');
                    if(newTbody) {
                        document.querySelector('#table-body').innerHTML = newTbody.innerHTML;
                        applyCategoryFilter(); 
                    }
                }).catch(err => console.log(err));
            }
        }, 3000); 

        function toggleModal(id, show) {
            document.getElementById(id).style.display = show ? 'flex' : 'none';
        }

        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            for (let i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function submitBulkAction(action) {
            const form = document.getElementById('bulk-form');
            const checked = form.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checked.length === 0) return alert('Pilih minimal satu item!');
            
            document.getElementById('action_input').value = action;
            if (action === 'delete') {
                toggleModal('delete-modal', true);
            } else {
                form.submit();
            }
        }

        function confirmDelete() {
            const form = document.getElementById('bulk-form');
            const physicalCheck = document.getElementById('modal_delete_physical');
            if (physicalCheck && physicalCheck.checked) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_physical';
                hiddenInput.value = 'yes';
                form.appendChild(hiddenInput);
            }
            form.submit();
        }

        function triggerEditModal() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checked.length !== 1) return alert('Pilih tepat satu item download!');
            
            document.getElementById('edit_gid').value = checked[0].value;
            document.getElementById('new_url').value = checked[0].getAttribute('data-current-url');
            toggleModal('edit-url-modal', true);
        }

        function triggerSpeedModal() {
            const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checked.length === 0) return alert('Pilih minimal satu item!');
            
            const container = document.getElementById('speed_gids_container');
            container.innerHTML = '';
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'speed_gids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            toggleModal('speed-modal', true);
        }

        function updateConfigContent() {
            const user = document.getElementById('config_user').value || 'stb';
            const dir = document.getElementById('config_dir').value || '/mnt/Downloads';
            const basePath = `/home/${user}/.config/aria2`;
            const fullPath = `${basePath}/aria2.conf`;
            
            document.getElementById('conf_path').value = fullPath;
            let content = document.getElementById('conf_content').value;
            
            content = content.replace(/^dir=.*$/gm, `dir=${dir}`);
            if (!content.match(/^dir=/m)) content = `dir=${dir}\n` + content;
            
            content = content.replace(/^input=.*$/gm, `input=${basePath}/aria2.session`);
            if (!content.match(/^input=/m)) content += `\ninput=${basePath}/aria2.session`;
            
            content = content.replace(/^save-session=.*$/gm, `save-session=${basePath}/aria2.session`);
            if (!content.match(/^save-session=/m)) content += `\nsave-session=${basePath}/aria2.session`;
            
            content = content.replace(/^force-save=.*$/gm, `force-save=true`);
            if (!content.match(/^force-save=/m)) content += `\nforce-save=true`;
            
            document.getElementById('conf_content').value = content;
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden">

    <!-- TOAST NOTIFICATION (MENGAMBANG DI ATAS MODAL Z-[70]) -->
    <div id="toast-container" class="fixed top-6 right-6 z-[70] flex flex-col space-y-3 w-[450px] pointer-events-none transition-opacity duration-500">
        <?php if(!empty($success_msg)): ?>
            <div class="bg-green-950 border border-green-800 text-green-300 px-5 py-4 rounded-xl text-sm flex items-start shadow-2xl pointer-events-auto relative">
                <i class="fa-solid fa-circle-check mr-3 mt-0.5 text-green-400 text-xl"></i> 
                <div class="flex-1 pr-6"><?= $success_msg ?></div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="absolute top-4 right-4 text-green-600 hover:text-green-400 transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
        <?php endif; ?>
        <?php if(!empty($error_msg)): ?>
            <div class="bg-red-950 border border-red-800 text-red-300 px-5 py-4 rounded-xl text-sm flex items-start shadow-2xl pointer-events-auto relative">
                <i class="fa-solid fa-circle-exclamation mr-3 mt-0.5 text-red-400 text-xl"></i> 
                <div class="flex-1 w-full pr-6"><?= $error_msg ?></div>
                <button type="button" onclick="this.parentElement.style.display='none'" class="absolute top-4 right-4 text-red-600 hover:text-red-400 transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL NEW DOWNLOAD -->
    <div id="add-download-modal" class="fixed inset-0 modal items-center justify-center z-50 overflow-y-auto pt-10 pb-10">
        <div class="bg-[#202026] p-6 rounded-xl w-[550px] border border-gray-700 my-auto shadow-2xl shadow-purple-500/10">
            <h3 class="text-white text-lg font-bold mb-5"><i class="fa-solid fa-cloud-arrow-down text-cyan-400 mr-2"></i><?= __('new_download') ?></h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('dl_url') ?>:</label>
                    <textarea name="new_dl_url" rows="2" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500 placeholder-gray-600" placeholder="http://..." required></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('rename_opt') ?>:</label>
                    <input type="text" name="new_dl_out" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500 placeholder-gray-600">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('save_to') ?>:</label>
                        <input type="text" name="new_dl_dir" value="<?= htmlspecialchars($conf_dir) ?>" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('category') ?>:</label>
                        <select name="new_dl_category" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500 font-semibold cursor-pointer">
                            <option value="Auto"><?= __('auto_detect') ?></option>
                            <option value="General"><?= __('general') ?></option>
                            <option value="Compressed"><?= __('compressed') ?></option>
                            <option value="Videos"><?= __('videos') ?></option>
                            <option value="Music"><?= __('music') ?></option>
                            <option value="Documents"><?= __('documents') ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="pt-2">
                    <button type="button" onclick="document.getElementById('adv-settings').classList.toggle('hidden')" class="text-cyan-400 text-xs font-semibold hover:text-cyan-300 transition-colors flex items-center">
                        <i class="fa-solid fa-gear mr-1.5"></i> <?= __('adv_settings') ?>
                    </button>
                    <div id="adv-settings" class="hidden mt-3 p-4 bg-[#18181c] border border-gray-700 rounded-lg">
                        <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('max_dl_speed') ?> (K/M):</label>
                        <input type="text" name="new_dl_speed" placeholder="Maksimal: 500K atau 2M" class="w-full bg-[#202026] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500 placeholder-gray-600">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t border-gray-800 mt-2">
                    <button type="button" onclick="toggleModal('add-download-modal', false)" class="px-5 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors font-semibold"><?= __('cancel') ?></button>
                    <button type="submit" name="submit_new_download" value="add" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-bold shadow-lg transition-colors"><?= __('add_list') ?></button>
                    <button type="submit" name="submit_new_download" value="download" class="px-5 py-2 gradient-btn text-white rounded-lg text-sm font-bold shadow-lg hover:opacity-90"><?= __('download') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SPEED INDIVIDUAL LIMIT -->
    <div id="speed-modal" class="fixed inset-0 modal items-center justify-center z-50">
        <div class="bg-[#202026] p-6 rounded-xl w-[400px] border border-gray-700 shadow-2xl shadow-purple-500/10">
            <h3 class="text-white text-lg font-bold mb-4"><i class="fa-solid fa-gauge-high text-cyan-400 mr-2"></i><?= __('speed_modal_title') ?></h3>
            <form method="POST" class="space-y-4">
                <div id="speed_gids_container"></div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('max_dl_speed') ?>:</label>
                    <input type="text" name="item_speed_limit" placeholder="Misal: 500K, 2M, atau 0 (Unlimited)" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" required>
                </div>
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-800 mt-2">
                    <button type="button" onclick="toggleModal('speed-modal', false)" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm"><?= __('cancel') ?></button>
                    <button type="submit" name="change_speed_action" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-sm font-bold"><?= __('update') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- SETTINGS MODAL -->
    <div id="settings-modal" class="fixed inset-0 modal items-center justify-center z-50 overflow-y-auto pt-10 pb-10">
        <div class="bg-[#202026] p-6 rounded-xl w-[550px] border border-gray-700 my-auto shadow-2xl shadow-cyan-500/10">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-white text-lg font-bold"><i class="fa-solid fa-gear text-cyan-400 mr-2"></i><?= __('settings') ?></h3>
                <button type="button" onclick="toggleModal('settings-modal', false)" class="text-gray-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <div class="border border-gray-800 p-4 rounded-lg bg-[#18181c] space-y-3">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">1. <?= __('engine_settings') ?></h4>
                    
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('config_path') ?> (Edit User):</label>
                        <div class="flex items-center w-full bg-[#202026] border border-gray-700 rounded px-3 py-1.5 focus-within:border-cyan-500 transition-colors">
                            <span class="text-gray-400 text-sm">/home/</span>
                            <input type="text" id="config_user" value="<?= htmlspecialchars($conf_user) ?>" class="bg-transparent text-cyan-400 text-sm font-bold w-20 text-center mx-1 focus:outline-none" onkeyup="updateConfigContent()">
                            <span class="text-gray-400 text-sm">/.config/aria2/aria2.conf</span>
                        </div>
                        <input type="hidden" name="conf_path" id="conf_path" value="<?= htmlspecialchars($ariaConfig['conf_path']) ?>">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1">Download Directory (dir=):</label>
                        <input type="text" id="config_dir" value="<?= htmlspecialchars($conf_dir) ?>" class="w-full bg-[#202026] text-white p-2 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" onkeyup="updateConfigContent()">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('config_content') ?>:</label>
                        <textarea name="conf_content" id="conf_content" rows="6" class="w-full bg-[#202026] text-green-400 p-2.5 rounded border border-gray-700 text-xs font-mono focus:outline-none focus:border-cyan-500 leading-relaxed" required><?= htmlspecialchars($ariaConfig['conf_content']) ?></textarea>
                    </div>
                </div>

                <div class="border border-gray-800 p-4 rounded-lg bg-[#18181c] space-y-3">
                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">2. <?= __('rpc_settings') ?></h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('rpc_url') ?>:</label>
                            <input type="text" name="rpc_url" value="<?= htmlspecialchars($settings['rpc_url']) ?>" class="w-full bg-[#202026] text-white p-2 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('secret_token') ?>:</label>
                            <input type="text" name="secret" value="<?= htmlspecialchars($settings['secret']) ?>" class="w-full bg-[#202026] text-white p-2 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" required>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-800 mt-2">
                    <button type="button" onclick="toggleModal('settings-modal', false)" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm transition-colors"><?= __('cancel') ?></button>
                    <button type="submit" name="save_settings" class="px-4 py-2 gradient-btn text-white rounded text-sm font-bold shadow-lg hover:opacity-90"><?= __('save') ?></button>
                </div>
            </form>

            <div class="mt-5 p-4 rounded-lg flex items-center justify-between border <?= $is_running ? 'bg-green-950/40 border-green-800 text-green-400' : 'bg-red-950/40 border-red-800 text-red-400' ?>">
                <div class="flex items-center space-x-2 text-sm font-semibold">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 <?= $is_running ? 'bg-green-400' : 'bg-red-400' ?>"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 <?= $is_running ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                    </span>
                    <span>3. Status Service: <?= $is_running ? __('status_online') : __('status_offline') ?></span>
                </div>
                <form method="POST">
                    <?php if ($is_running): ?>
                        <button type="submit" name="stop_service" class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded text-xs font-bold transition-colors shadow" onclick="return confirm('<?= __('confirm_stop') ?>')"><i class="fa-solid fa-stop mr-1"></i> <?= __('stop_service') ?></button>
                    <?php else: ?>
                        <button type="submit" name="start_service" class="px-4 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-bold transition-colors shadow"><i class="fa-solid fa-play mr-1"></i> <?= __('start_service') ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit URL Modal -->
    <div id="edit-url-modal" class="fixed inset-0 modal items-center justify-center z-50">
        <div class="bg-[#202026] p-6 rounded-xl w-[500px] border border-gray-700">
            <h3 class="text-white text-lg font-bold mb-4"><i class="fa-solid fa-pen-to-square text-cyan-400 mr-2"></i><?= __('edit_url_title') ?></h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('target_gid') ?>:</label>
                    <input type="text" name="edit_gid" id="edit_gid" readonly class="w-full bg-[#18181c] text-gray-400 p-2.5 rounded border border-gray-800 text-sm font-mono focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 mb-1"><?= __('new_url') ?>:</label>
                    <textarea name="new_url" id="new_url" rows="3" class="w-full bg-[#18181c] text-white p-2.5 rounded border border-gray-700 text-sm focus:outline-none focus:border-cyan-500" required></textarea>
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="toggleModal('edit-url-modal', false)" class="px-4 py-2 bg-gray-600 text-white rounded text-sm"><?= __('cancel') ?></button>
                    <button type="submit" name="edit_url_action" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-sm font-bold"><?= __('update') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="delete-modal" class="fixed inset-0 modal items-center justify-center z-50">
        <div class="bg-[#202026] p-6 rounded-xl w-96 border border-gray-700">
            <h3 class="text-red-400 text-lg font-bold mb-3"><i class="fa-solid fa-triangle-exclamation"></i> <?= __('confirm_delete') ?></h3>
            <p class="text-sm mb-4 text-gray-300">Yakin menghapus item terpilih?</p>
            <label class="flex items-center space-x-2 mb-6 text-white cursor-pointer select-none">
                <input type="checkbox" id="modal_delete_physical" value="yes" class="rounded bg-gray-800 border-gray-700 accent-red-500 w-4 h-4">
                <span class="text-sm text-gray-300"><?= __('delete_physical') ?></span>
            </label>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="toggleModal('delete-modal', false)" class="px-4 py-2 bg-gray-600 text-white rounded text-sm"><?= __('cancel') ?></button>
                <button type="button" onclick="confirmDelete()" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded text-sm font-bold"><?= __('delete') ?></button>
            </div>
        </div>
    </div>

    <aside class="w-64 panel flex flex-col justify-between border-r border-gray-800">
        <div class="p-6">
            <h1 class="text-xl font-bold text-white mb-8"><i class="fa-solid fa-cloud-arrow-down mr-2"></i> <?= __('tasks') ?></h1>
            <nav class="space-y-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4"><?= __('categories') ?></div>
                <a href="#" onclick="filterCategory('All', this)" class="sidebar-cat block px-4 py-2 bg-[#26262e] text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-list w-6 text-cyan-400"></i> <?= __('all_files') ?></a>
                <a href="#" onclick="filterCategory('Compressed', this)" class="sidebar-cat block px-4 py-2 text-gray-400 hover:bg-[#26262e] hover:text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-file-zipper w-6"></i> <?= __('compressed') ?></a>
                <a href="#" onclick="filterCategory('Videos', this)" class="sidebar-cat block px-4 py-2 text-gray-400 hover:bg-[#26262e] hover:text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-video w-6"></i> <?= __('videos') ?></a>
                <a href="#" onclick="filterCategory('Music', this)" class="sidebar-cat block px-4 py-2 text-gray-400 hover:bg-[#26262e] hover:text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-music w-6"></i> <?= __('music') ?></a>
                <a href="#" onclick="filterCategory('Documents', this)" class="sidebar-cat block px-4 py-2 text-gray-400 hover:bg-[#26262e] hover:text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-file-lines w-6"></i> <?= __('documents') ?></a>
                <a href="#" onclick="filterCategory('General', this)" class="sidebar-cat block px-4 py-2 text-gray-400 hover:bg-[#26262e] hover:text-white rounded-lg flex items-center text-sm transition-colors"><i class="fa-solid fa-file w-6"></i> <?= __('general') ?></a>
            </nav>
        </div>
    </aside>

    <main class="flex-1 flex flex-col bg-[#121216] relative">
        <header class="h-20 panel flex items-center justify-between px-8 border-b border-gray-800">
            <div class="flex items-center space-x-4">
                <button onclick="toggleModal('add-download-modal', true)" class="gradient-btn text-white px-5 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 flex items-center shadow-lg" <?= !$is_running ? 'disabled opacity-40' : '' ?>><i class="fa-solid fa-plus mr-2"></i> <?= __('new_download') ?></button>
                
                <div class="flex items-center bg-[#22222a] border border-gray-700 rounded-full px-4 py-2 w-80 focus-within:border-cyan-500 transition-colors">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input type="text" id="searchInput" onkeyup="applyCategoryFilter()" placeholder="<?= __('search_placeholder') ?>" class="bg-transparent border-none outline-none text-white text-sm w-full ml-3 placeholder-gray-500">
                </div>
            </div>
            
            <div class="flex items-center space-x-5 text-xs">
                <button onclick="triggerEditModal()" class="hover:text-cyan-300 flex flex-col items-center text-cyan-400 transition-colors font-semibold" <?= !$is_running ? 'disabled opacity-30' : '' ?>><i class="fa-solid fa-pen-to-square mb-1 text-sm"></i> <?= __('edit_url_btn') ?></button>
                
                <button onclick="triggerSpeedModal()" class="hover:text-purple-300 flex flex-col items-center text-purple-400 transition-colors font-semibold" <?= !$is_running ? 'disabled opacity-30' : '' ?>><i class="fa-solid fa-gauge-high mb-1 text-sm"></i> <?= __('change_speed_btn') ?></button>
                
                <div class="w-px h-8 bg-gray-700 mx-1"></div>
                <button onclick="submitBulkAction('resume')" class="hover:text-white flex flex-col items-center text-gray-400 transition-colors" <?= !$is_running ? 'disabled opacity-30' : '' ?>><i class="fa-solid fa-play mb-1 text-sm text-green-400"></i> <?= __('resume') ?></button>
                <button onclick="submitBulkAction('stop')" class="hover:text-white flex flex-col items-center text-gray-400 transition-colors" <?= !$is_running ? 'disabled opacity-30' : '' ?>><i class="fa-solid fa-stop mb-1 text-sm text-yellow-400"></i> <?= __('stop') ?></button>
                <button onclick="submitBulkAction('delete')" class="hover:text-red-500 flex flex-col items-center text-gray-400 transition-colors" <?= !$is_running ? 'disabled opacity-30' : '' ?>><i class="fa-solid fa-trash mb-1 text-sm text-red-400"></i> <?= __('delete') ?></button>
                <div class="w-px h-8 bg-gray-700 mx-1"></div>
                <button onclick="toggleModal('settings-modal', true)" class="hover:text-white flex flex-col items-center text-gray-400 transition-colors"><i class="fa-solid fa-gear mb-1 text-sm text-cyan-400"></i> <?= __('settings') ?></button>
                <a href="?logout=1" class="hover:text-white flex flex-col items-center text-gray-400"><i class="fa-solid fa-power-off mb-1 text-sm text-red-500"></i> <?= __('logout') ?></a>
            </div>
        </header>

        <div class="flex-1 p-8 overflow-y-auto mb-16">
            <form id="bulk-form" method="POST">
                <input type="hidden" name="action" id="action_input" value="">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-800 uppercase text-xs tracking-wider font-semibold">
                            <th class="pb-4 w-10 text-center">
                                <input type="checkbox" id="selectAll" class="rounded bg-gray-800 border-gray-700 accent-purple-500 w-4 h-4 cursor-pointer" onclick="toggleSelectAll(this)" title="<?= __('select_all') ?>">
                            </th>
                            <th class="pb-4"><?= __('file_name_url') ?></th>
                            <th class="pb-4 w-32"><?= __('category') ?></th>
                            <th class="pb-4 w-32"><?= __('size') ?></th>
                            <th class="pb-4 w-64"><?= __('status_progress') ?></th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php foreach ($downloads as $file): ?>
                        <?php 
                            $badgeColor = 'bg-gray-700 text-gray-300';
                            
                            // SET WARNA LENCANA STATUS
                            if ($file['is_creating']) {
                                $badgeColor = 'bg-blue-950 text-blue-400 border border-blue-800';
                            } else {
                                if ($file['raw_status'] === 'active') $badgeColor = 'bg-cyan-950 text-cyan-400 border border-cyan-800';
                                if ($file['raw_status'] === 'complete') $badgeColor = 'bg-green-950 text-green-400 border border-green-800';
                                if ($file['raw_status'] === 'error') $badgeColor = 'bg-red-950 text-red-400 border border-red-800';
                                if ($file['raw_status'] === 'paused') $badgeColor = 'bg-yellow-950 text-yellow-400 border border-yellow-800';
                            }
                        ?>
                        <tr class="task-row border-b border-gray-800 hover:bg-[#1a1a20] transition-colors group" data-category="<?= htmlspecialchars($file['category']) ?>">
                            <td class="py-4 text-center">
                                <input type="checkbox" name="selected_ids[]" value="<?= $file['id'] ?>" data-current-url="<?= htmlspecialchars($file['url']) ?>" class="rounded bg-gray-800 border-gray-700 accent-purple-500 w-4 h-4 cursor-pointer">
                            </td>
                            <td class="py-4 flex items-center text-white file-name-col">
                                <div class="w-9 h-9 rounded-xl bg-[#22222a] flex items-center justify-center mr-3 text-gray-400 border border-gray-700">
                                    <i class="fa-solid fa-file-arrow-down text-sm"></i>
                                </div>
                                <div class="max-w-[350px] md:max-w-[500px]">
                                    <div class="font-semibold text-white truncate text-sm" title="<?= htmlspecialchars($file['name']) ?>"><?= htmlspecialchars($file['name']) ?></div>
                                    <div class="text-xs text-gray-500 truncate" title="<?= htmlspecialchars($file['original_url']) ?>"><?= htmlspecialchars($file['url']) ?></div>
                                    
                                    <?php if ($file['raw_status'] === 'error' && !empty($file['error_message'])): ?>
                                        <div class="text-[10px] text-red-400 mt-1 truncate" title="<?= htmlspecialchars($file['error_message']) ?>">
                                            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($file['error_message']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-4"><span class="text-xs text-gray-400 bg-[#22222a] px-2.5 py-1 rounded-full border border-gray-800"><?= htmlspecialchars($file['category']) ?></span></td>
                            <td class="py-4 font-medium text-gray-300 text-xs"><?= htmlspecialchars($file['size']) ?></td>
                            <td class="py-4">
                                <div class="flex flex-col mb-1.5">
                                    <div class="flex justify-between items-center w-full mb-1">
                                        <div class="flex items-center space-x-2">
                                            
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $badgeColor ?>">
                                                <?php if($file['is_creating']): ?><i class="fa-solid fa-spinner fa-spin mr-1"></i><?php endif; ?>
                                                <?= htmlspecialchars($file['status']) ?>
                                            </span>
                                            
                                            <?php if(!empty($file['speed'])): ?>
                                                <span class="text-cyan-400 text-[10px] font-mono" title="Kecepatan"><i class="fa-solid fa-arrow-down"></i> <?= $file['speed'] ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if(!empty($file['eta'])): ?>
                                                <span class="text-purple-400 text-[10px] font-mono ml-1" title="Sisa Waktu"><i class="fa-regular fa-clock"></i> <?= $file['eta'] ?></span>
                                            <?php endif; ?>

                                        </div>
                                        <span class="text-white text-xs font-semibold"><?= $file['progress'] ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-800 rounded-full h-1.5 border border-gray-900 mt-1">
                                        <div class="<?= $file['progress'] == 100 ? 'bg-green-500' : 'progress-bar-fill' ?> h-full rounded-full transition-all duration-300" style="width: <?= $file['progress'] ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($downloads)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-12 text-gray-500 text-sm">
                                <i class="fa-solid fa-folder-open text-3xl block mb-3 text-gray-600"></i>
                                <?= $is_running ? __('no_downloads') : __('engine_offline') ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <footer class="h-16 panel border-t border-gray-800 flex items-center justify-between px-8 absolute bottom-0 w-full z-10">
            <div class="flex items-center space-x-4">
               <form method="POST" class="flex items-center space-x-3">
                   <label class="text-xs text-gray-400 font-semibold cursor-help"><i class="fa-solid fa-gauge-high mr-1 text-cyan-400"></i> <?= __('global_limit') ?> (K/M):</label>
                   <div class="flex">
                       <input type="text" name="max_download_limit" value="<?= htmlspecialchars($settings['max_download_limit']) ?>" class="bg-[#22222a] text-white px-3 py-1.5 rounded-l-md border border-gray-700 text-sm focus:outline-none focus:border-cyan-500 w-28" <?= !$is_running ? 'disabled' : '' ?> placeholder="Misal: 500K">
                       <button type="submit" name="update_limit" class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1.5 rounded-r-md text-xs font-bold transition-colors" <?= !$is_running ? 'disabled opacity-50' : '' ?>><?= __('save') ?></button>
                   </div>
               </form>
            </div>
            <div class="flex items-center space-x-2">
               <form method="POST" class="flex items-center space-x-2">
                   <label class="text-xs text-gray-400 font-semibold"><i class="fa-solid fa-globe mr-1 text-cyan-400"></i> <?= __('language') ?>:</label>
                   <select name="lang" onchange="this.form.submit()" class="bg-[#22222a] text-gray-300 text-xs px-3 py-1.5 rounded border border-gray-700 focus:outline-none font-semibold">
                       <?php foreach($available_langs as $key => $name): ?>
                           <option value="<?= $key ?>" <?= $key === $current_lang_code ? 'selected' : '' ?>><?= $name ?></option>
                       <?php endforeach; ?>
                   </select>
                   <input type="hidden" name="change_lang" value="1">
               </form>
            </div>
        </footer>
    </main>
    
    <!-- SCRIPT AUTO-HIDE TOAST (2 DETIK) & AUTO-OPEN MODAL -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($open_modal_id)): ?>
                toggleModal('<?= $open_modal_id ?>', true);
            <?php endif; ?>

            const toast = document.getElementById('toast-container');
            if (toast && toast.innerHTML.trim() !== '' && !toast.querySelector('textarea')) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.style.display = 'none', 500); 
                }, 2000); 
            }
        });
    </script>
</body>
</html>