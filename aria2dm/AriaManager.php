<?php
class AriaManager {
    private $settingsFile = __DIR__ . '/settings.json';
    private $downloadsFile = __DIR__ . '/downloads.json';
    private $ariaConfigFile = __DIR__ . '/aria2.json';
    private $langDir = __DIR__ . '/lang';

    public function __construct() {
        if (!file_exists($this->settingsFile)) $this->getSettings();
        if (!file_exists($this->ariaConfigFile)) $this->getAriaConfig();
    }

    public function getSettings() {
        $default = [
            'rpc_url' => 'http://127.0.0.1:6800/jsonrpc',
            'secret' => 'Token123',
            'max_download_limit' => '0',
            'language' => 'en'
        ];
        if (!file_exists($this->settingsFile)) {
            file_put_contents($this->settingsFile, json_encode($default, JSON_PRETTY_PRINT));
            return $default;
        }
        $existing = json_decode(file_get_contents($this->settingsFile), true);
        return array_merge($default, is_array($existing) ? $existing : []);
    }

    public function updateSettings($rpcUrl, $secret) {
        $settings = $this->getSettings();
        $settings['rpc_url'] = $rpcUrl;
        $settings['secret'] = $secret;
        return @file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
    }

    public function updateGlobalLimit($maxLimit) {
        $settings = $this->getSettings();
        $settings['max_download_limit'] = $maxLimit;
        @file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

        if ($this->isServiceRunning()) {
            $this->rpc('aria2.changeGlobalOption', [
                ['max-overall-download-limit' => $maxLimit]
            ]);
        }
    }

    public function updateLanguage($langCode) {
        $settings = $this->getSettings();
        $settings['language'] = $langCode;
        @file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    public function getAvailableLanguages() {
        $langs = [];
        if (is_dir($this->langDir)) {
            $files = glob($this->langDir . '/*.json');
            foreach ($files as $file) {
                $filename = basename($file, '.json');
                $code = preg_replace('/^lang_/', '', $filename);
                $langs[$code] = strtoupper($code);
            }
        }
        return empty($langs) ? ['en' => 'EN'] : $langs;
    }

    public function getLanguageData($langCode) {
        $fileWithPrefix = $this->langDir . '/lang_' . $langCode . '.json';
        $fileWithoutPrefix = $this->langDir . '/' . $langCode . '.json';
        
        if (file_exists($fileWithPrefix)) return json_decode(file_get_contents($fileWithPrefix), true) ?? [];
        if (file_exists($fileWithoutPrefix)) return json_decode(file_get_contents($fileWithoutPrefix), true) ?? [];
        
        $fallback = $this->langDir . '/lang_en.json';
        if (file_exists($fallback)) return json_decode(file_get_contents($fallback), true) ?? [];
        
        return [];
    }

    public function getAriaConfig() {
    $confPath = __DIR__ . '/aria2.conf';
    $sessionPath = __DIR__ . '/aria2.session';

    if (!file_exists($this->ariaConfigFile)) {
        $defaultContent = "dir=/mnt/Downloads\n\ncontinue=true\nmax-concurrent-downloads=5\nsplit=16\nmax-connection-per-server=16\n\nforce-save=true\n\nenable-rpc=true\nrpc-listen-all=true\nrpc-listen-port=6800\nrpc-secret=Token123\nrpc-allow-origin-all=true\n\ninput=$sessionPath\nsave-session=$sessionPath\nsave-session-interval=60";
        
        $default = [
            'conf_path' => $confPath,
            'conf_content' => $defaultContent
        ];
        file_put_contents($this->ariaConfigFile, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }
    
    $data = json_decode(file_get_contents($this->ariaConfigFile), true);
    $data['conf_path'] = $confPath; 
    return $data;
}

    public function updateAriaConfig($path, $content) {
        $config = ['conf_path' => $path, 'conf_content' => $content];
        return @file_put_contents($this->ariaConfigFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }

    public function isServiceRunning() {
        $settings = $this->getSettings();
        if (empty($settings['rpc_url'])) return false;

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 'status_check',
            'method' => 'aria2.getVersion',
            'params' => ['token:' . $settings['secret']]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $settings['rpc_url'],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3, 
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode === 200 && !empty($response));
    }

    public function startAria2Service() {
        $ariaConfig = $this->getAriaConfig();
        $confPath = $ariaConfig['conf_path'];
        $confContent = $ariaConfig['conf_content'];

        $settings = $this->getSettings();
        $globalLimit = !empty($settings['max_download_limit']) ? $settings['max_download_limit'] : '0';

        $confDir = dirname($confPath);
        if (!is_dir($confDir)) @mkdir($confDir, 0777, true);

        @file_put_contents($confPath, $confContent);

        if (preg_match('/^input=(.+)$/m', $confContent, $matches)) {
            $sess = trim($matches[1]);
            $sDir = dirname($sess);
            if (!is_dir($sDir)) @mkdir($sDir, 0777, true);
            if (!file_exists($sess)) @touch($sess);
        }
        if (preg_match('/^save-session=(.+)$/m', $confContent, $matches)) {
            $sess = trim($matches[1]);
            $sDir = dirname($sess);
            if (!is_dir($sDir)) @mkdir($sDir, 0777, true);
            if (!file_exists($sess)) @touch($sess);
        }
        if (preg_match('/^dir=(.+)$/m', $confContent, $matches)) {
            $dlDir = trim($matches[1]);
            if (!is_dir($dlDir)) @mkdir($dlDir, 0777, true);
        }

        $aria2cPath = exec('which aria2c') ?: '/usr/bin/aria2c';
        
        $command = sprintf('%s --conf-path=%s --max-overall-download-limit=%s -D > /dev/null 2>&1 &', 
            escapeshellarg($aria2cPath), 
            escapeshellarg($confPath),
            escapeshellarg($globalLimit)
        );
        
        exec($command);
        
        for ($i = 0; $i < 3; $i++) {
            sleep(1);
            if ($this->isServiceRunning()) return true;
        }
        return false;
    }

    public function stopAria2Service() {
        if ($this->isServiceRunning()) {
            $this->rpc('aria2.saveSession'); 
            $this->rpc('aria2.shutdown');    
            sleep(1);
        }
        
        exec('killall aria2c || pkill -f aria2c');
        
        for ($i = 0; $i < 3; $i++) {
            sleep(1);
            if (!$this->isServiceRunning()) return true;
        }
        return false;
    }

    private function rpc($method, $params = []) {
        $settings = $this->getSettings();
        array_unshift($params, 'token:' . $settings['secret']);

        $payload = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params
        ];

        $json = json_encode($payload);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $settings['rpc_url'],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($json)],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response === false) return null; 
        
        return json_decode($response, true);
    }

    public function getLocalDownloadsData() {
        if (!file_exists($this->downloadsFile)) return [];
        return json_decode(file_get_contents($this->downloadsFile), true) ?? [];
    }

    public function saveLocalDownloadsData($data) {
        @file_put_contents($this->downloadsFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getDownloads() {
        $localData = $this->getLocalDownloadsData();
        $localDataMap = [];
        
        foreach ($localData as $item) {
            $localDataMap[$item['gid']] = $item;
        }

        if ($this->isServiceRunning()) {
            $active = $this->rpc('aria2.tellActive')['result'] ?? [];
            $waiting = $this->rpc('aria2.tellWaiting', [0, 100])['result'] ?? [];
            $stopped = $this->rpc('aria2.tellStopped', [0, 100])['result'] ?? [];

            $allTasks = array_merge($active, $waiting, $stopped);

            foreach ($allTasks as $item) {
                $gid = $item['gid'];
                $fileName = 'Unknown File';
                if (!empty($item['files'][0]['path'])) {
                    $fileName = basename($item['files'][0]['path']);
                } elseif (!empty($item['files'][0]['uris'][0]['uri'])) {
                    $fileName = basename(parse_url($item['files'][0]['uris'][0]['uri'], PHP_URL_PATH));
                }

                $total = max(1, (float)($item['totalLength'] ?? 1));
                $completed = (float)($item['completedLength'] ?? 0);
                $progress = round(($completed / $total) * 100, 2);
                
                $size = ($total > 1073741824) ? round($total / 1073741824, 2) . ' GB' : round($total / 1048576, 2) . ' MB';
                if ($total == 1) $size = 'Unknown';

                $speedStr = '';
                $etaStr = '';
                $speedBytes = (float)($item['downloadSpeed'] ?? 0);
                
                $isCreating = ($item['status'] === 'active' && $completed == 0 && $total > 1 && $speedBytes == 0);
                
                if ($item['status'] === 'active' && $speedBytes > 0) {
                    if ($speedBytes >= 1048576) {
                        $speedStr = round($speedBytes / 1048576, 2) . ' MB/s';
                    } else {
                        $speedStr = round($speedBytes / 1024, 2) . ' KB/s';
                    }
                    
                    $remainingBytes = $total - $completed;
                    $etaSeconds = $remainingBytes / $speedBytes;
                    if ($etaSeconds > 86400) {
                        $etaStr = '> 1 Hari';
                    } else {
                        $etaStr = gmdate(($etaSeconds >= 3600 ? "H:i:s" : "i:s"), (int)$etaSeconds);
                    }
                }

                $statusLabel = $isCreating ? 'Creating File' : $this->mapStatus($item['status']);

                $originalUrl = $item['files'][0]['uris'][0]['uri'] ?? '';
                $category = 'General';
                if (isset($localDataMap[$gid])) {
                    if (!empty($localDataMap[$gid]['original_url'])) $originalUrl = $localDataMap[$gid]['original_url'];
                    if (!empty($localDataMap[$gid]['category'])) $category = $localDataMap[$gid]['category'];
                }

                $localDataMap[$gid] = [
                    'id' => $gid,
                    'gid' => $gid,
                    'name' => $fileName,
                    'url' => $item['files'][0]['uris'][0]['uri'] ?? '',
                    'original_url' => $originalUrl,
                    'category' => $category,
                    'status' => $statusLabel,
                    'raw_status' => $item['status'],
                    'is_creating' => $isCreating,
                    'size' => $size,
                    'progress' => $progress,
                    'speed' => $speedStr,
                    'eta' => $etaStr,
                    'error_message' => $item['errorMessage'] ?? '',
                    'date' => $localDataMap[$gid]['date'] ?? date('Y-m-d H:i:s')
                ];
            }
            $this->saveLocalDownloadsData(array_values($localDataMap));
        } else {
            foreach ($localDataMap as &$ld) {
                if (isset($ld['raw_status']) && $ld['raw_status'] === 'active') {
                    $ld['raw_status'] = 'paused';
                    $ld['status'] = 'Offline/Paused';
                    $ld['speed'] = '';
                    $ld['eta'] = '';
                    $ld['is_creating'] = false;
                }
            }
        }

        $result = array_values($localDataMap);
        usort($result, function($a, $b) {
            return strtotime($b['date'] ?? '0') - strtotime($a['date'] ?? '0');
        });
        
        return $result;
    }

    private function mapStatus($status) {
        $map = ['active' => 'Downloading', 'waiting' => 'Waiting', 'paused' => 'Paused', 'error' => 'Error', 'complete' => 'Completed', 'removed' => 'Removed'];
        return $map[$status] ?? ucfirst($status);
    }

    public function addDownload($url, $category = 'Auto', $customOptions = []) {
        $url = trim($url);
        if (empty($url)) return "URL tidak boleh kosong.";

        if (empty($category) || $category === 'Auto') {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'iso'])) $category = 'Compressed';
            elseif (in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'flv', 'webm'])) $category = 'Videos';
            elseif (in_array($ext, ['mp3', 'wav', 'flac', 'ogg', 'm4a'])) $category = 'Music';
            elseif (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'ppt'])) $category = 'Documents';
            else $category = 'General';
        }

        $ariaConfig = $this->getAriaConfig();
        $targetDir = '/mnt/Downloads';
        if (preg_match('/^dir=(.+)$/m', $ariaConfig['conf_content'] ?? '', $matches)) {
            $targetDir = trim($matches[1]);
        }
        
        if (!isset($customOptions['dir']) || empty($customOptions['dir'])) {
            $customOptions['dir'] = $targetDir;
        } else {
            $targetDir = $customOptions['dir'];
        }

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0777, true)) {
                return "Permission Denied: Buka menu Settings dan klik Save untuk melihat solusi perbaikan akses.";
            }
        }
        if (!is_writable($targetDir)) {
            return "Permission Denied: Buka menu Settings dan klik Save untuk melihat solusi perbaikan akses.";
        }

        $res = $this->rpc('aria2.addUri', [ [$url], $customOptions ]);
        
        if ($res === null) return "Gagal terhubung ke service RPC Aria2.";
        if (isset($res['error'])) return "Aria2 Error: " . ($res['error']['message'] ?? 'Unknown');
        
        if (!empty($res['result'])) {
            $gid = $res['result'];
            $localData = $this->getLocalDownloadsData();
            
            $localData[] = [
                'gid' => $gid, 
                'id' => $gid, 
                'name' => 'Pending...', 
                'url' => $url, 
                'original_url' => $url, 
                'category' => $category, 
                'status' => 'Waiting', 
                'raw_status' => 'waiting', 
                'is_creating' => false, 
                'size' => 'Unknown', 
                'progress' => 0, 
                'speed' => '', 
                'eta' => '', 
                'error_message' => '', 
                'date' => date('Y-m-d H:i:s')
            ];
            
            $this->saveLocalDownloadsData($localData);
            return true;
        }
        return false;
    }

    public function resumeDownload($gid) {
        $res = $this->rpc('aria2.unpause', [$gid]);
        
        if (!empty($res['result'])) {
            return true;
        }

        $localData = $this->getLocalDownloadsData();
        $taskData = null;
        foreach ($localData as $item) {
            if ($item['gid'] === $gid) {
                $taskData = $item; break;
            }
        }

        if ($taskData) {
            $url = $taskData['url'] ?: $taskData['original_url'];
            if (empty($url)) return "URL tidak valid untuk dilanjutkan.";
            
            $ariaConfig = $this->getAriaConfig();
            $targetDir = '/mnt/Downloads';
            if (preg_match('/^dir=(.+)$/m', $ariaConfig['conf_content'] ?? '', $matches)) {
                $targetDir = trim($matches[1]);
            }

            $newRes = $this->rpc('aria2.addUri', [ [$url], ['dir' => $targetDir] ]);
            
            if (!empty($newRes['result'])) {
                $newGid = $newRes['result'];
                
                foreach ($localData as &$ld) {
                    if ($ld['gid'] === $gid) {
                        $ld['gid'] = $newGid;
                        $ld['id'] = $newGid;
                        $ld['status'] = 'Waiting';
                        $ld['raw_status'] = 'waiting';
                        $ld['error_message'] = '';
                        break;
                    }
                }
                $this->saveLocalDownloadsData($localData);
                return true;
            } else {
                return "Aria2 Error: " . ($newRes['error']['message'] ?? 'Gagal me-resume otomatis.');
            }
        }
        
        return "Gagal: Data tidak ditemukan di database history.";
    }

    public function stopDownload($gid) {
        $res = $this->rpc('aria2.pause', [$gid]);
        if(empty($res['result'])) {
             $this->rpc('aria2.forcePause', [$gid]);
        }
        
        $localData = $this->getLocalDownloadsData();
        foreach ($localData as &$ld) {
            if ($ld['gid'] === $gid) {
                $ld['status'] = 'Paused';
                $ld['raw_status'] = 'paused';
                $ld['speed'] = '';
                $ld['eta'] = '';
            }
        }
        $this->saveLocalDownloadsData($localData);
        return true;
    }

    public function editDownloadUrl($gid, $newUrl) {
        $newUrl = trim($newUrl);
        if (empty($newUrl)) return "URL tidak boleh kosong.";

        $oldTask = $this->rpc('aria2.tellStatus', [$gid]);
        $options = new stdClass();

        if (!empty($oldTask['result'])) {
            $task = $oldTask['result'];
            if (!empty($task['dir'])) $options->dir = $task['dir'];
            if (!empty($task['out'])) $options->out = $task['out'];

            if (($task['status'] ?? '') === 'active') {
                $this->rpc('aria2.forceRemove', [$gid]);
            } else {
                $this->rpc('aria2.removeDownloadResult', [$gid]);
            }
        } else {
            $ariaConfig = $this->getAriaConfig();
            if (preg_match('/^dir=(.+)$/m', $ariaConfig['conf_content'] ?? '', $matches)) {
                $options->dir = trim($matches[1]);
            }
        }

        $params = [ [$newUrl], $options ];
        $newTask = $this->rpc('aria2.addUri', $params);
        
        if ($newTask === null) return "Gagal terhubung ke service RPC Aria2.";
        if (isset($newTask['error'])) return "Aria2 Error: " . ($newTask['error']['message'] ?? 'Format URL tidak didukung');
        
        if (!empty($newTask['result'])) {
            $newGid = $newTask['result'];
            $localData = $this->getLocalDownloadsData();
            $updated = false;
            foreach ($localData as &$ld) {
                if ($ld['gid'] === $gid) {
                    $ld['gid'] = $newGid; 
                    $ld['id'] = $newGid;
                    $ld['url'] = $newUrl; 
                    $ld['status'] = 'Waiting';
                    $ld['raw_status'] = 'waiting';
                    $ld['speed'] = '';
                    $ld['eta'] = '';
                    $ld['error_message'] = '';
                    $ld['date'] = date('Y-m-d H:i:s');
                    $updated = true; break;
                }
            }
            if (!$updated) {
                $localData[] = [
                    'gid' => $newGid, 'id' => $newGid, 'name' => 'Pending...', 'url' => $newUrl, 
                    'original_url' => $newUrl, 'category' => 'General', 'status' => 'Waiting', 
                    'raw_status' => 'waiting', 'is_creating' => false, 'size' => 'Unknown', 
                    'progress' => 0, 'speed' => '', 'eta' => '', 'error_message' => '', 
                    'date' => date('Y-m-d H:i:s')
                ];
            }
            $this->saveLocalDownloadsData($localData);
            return true;
        }
        return false;
    }

    public function deleteDownload($gid, $deletePhysical) {
        if ($this->isServiceRunning()) {
            $info = $this->rpc('aria2.tellStatus', [$gid]);
            $path = $info['result']['files'][0]['path'] ?? '';
            $status = $info['result']['status'] ?? '';

            if (in_array($status, ['active', 'waiting', 'paused'])) {
                $this->rpc('aria2.forceRemove', [$gid]);
            } else {
                $this->rpc('aria2.removeDownloadResult', [$gid]);
            }

            if ($deletePhysical && !empty($path) && file_exists($path)) {
                @unlink($path); @unlink($path . '.aria2');
            }
        }

        $localData = $this->getLocalDownloadsData();
        $filteredData = array_filter($localData, function($item) use ($gid) { 
            return $item['gid'] !== $gid; 
        });
        $this->saveLocalDownloadsData(array_values($filteredData));
    }

    public function changeDownloadSpeed($gid, $speed) {
        $speed = trim($speed);
        if ($speed === '') $speed = '0';
        
        $res = $this->rpc('aria2.changeOption', [$gid, ['max-download-limit' => $speed]]);
        if ($res === null) return "Gagal terhubung ke service RPC Aria2.";
        if (isset($res['error'])) return "Aria2 Error: " . ($res['error']['message'] ?? 'Unknown');
        return true;
    }
}
?>
