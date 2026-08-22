<?php
/**
 * OPI-DASHBOARD: File Manager Service Engine
 * Provides Secure File Browsing, Reading, Editing, Uploading, Creating, and Deleting.
 */

class FileManager {
    private static $defaultRoot = '/root/opi-dashboard';
    private static $allowedBases = [
        '/root/opi-dashboard',
        '/root',
        '/etc',
        '/var/log',
        '/tmp'
    ];

    /**
     * Enforce the allowed-bases whitelist.
     * Any path resolving outside the approved roots falls back to the default root.
     */
    private static function assertAllowed(string $path): string {
        $real = realpath($path);
        // For not-yet-existing paths (new files/folders) validate the deepest existing ancestor
        $candidate = $real !== false ? $real : $path;
        $norm = rtrim(str_replace('\\', '/', $candidate), '/');
        if ($norm === '') return self::$defaultRoot;

        foreach (self::$allowedBases as $base) {
            $baseNorm = rtrim(str_replace('\\', '/', $base), '/');
            if ($norm === $baseNorm || strpos($norm, $baseNorm . '/') === 0) {
                return $path;
            }
        }
        return self::$defaultRoot;
    }

    /**
     * Resolve and Validate Path Safely
     */
    public static function resolvePath(string $rawPath): string {
        $rawPath = trim($rawPath);
        if (empty($rawPath)) {
            return self::$defaultRoot;
        }

        $real = realpath($rawPath);
        if ($real === false || !file_exists($real)) {
            // Path might be a new file to create
            $dir = realpath(dirname($rawPath));
            if ($dir !== false) {
                return self::assertAllowed(rtrim($dir, '/') . '/' . basename($rawPath));
            }
            return self::$defaultRoot;
        }

        return self::assertAllowed($real);
    }

    /**
     * Get Disk Usage Statistics
     */
    public static function getDiskStats(): array {
        $totalBytes = @disk_total_space('/') ?: (16 * 1024 * 1024 * 1024);
        $freeBytes = @disk_free_space('/') ?: (10 * 1024 * 1024 * 1024);
        $usedBytes = max(0, $totalBytes - $freeBytes);
        $usedPct = round(($usedBytes / $totalBytes) * 100, 1);

        return [
            'total_formatted' => self::formatBytes($totalBytes),
            'used_formatted' => self::formatBytes($usedBytes),
            'free_formatted' => self::formatBytes($freeBytes),
            'used_percent' => $usedPct
        ];
    }

    /**
     * List Directory Contents
     */
    public static function listDirectory(string $path): array {
        $safePath = self::resolvePath($path);
        if (!is_dir($safePath)) {
            $safePath = dirname($safePath);
        }

        $items = [];
        $scanned = @scandir($safePath) ?: [];
        $totalFiles = 0;
        $totalFolders = 0;
        $totalDirSize = 0;

        foreach ($scanned as $name) {
            if ($name === '.' || $name === '..') continue;

            $fullPath = rtrim($safePath, '/') . '/' . $name;
            $isDir = is_dir($fullPath);
            $size = $isDir ? 0 : (@filesize($fullPath) ?: 0);
            $totalDirSize += $size;
            if ($isDir) $totalFolders++; else $totalFiles++;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $perms = substr(sprintf('%o', @fileperms($fullPath)), -4);
            $modified = @filemtime($fullPath) ?: time();

            $items[] = [
                'name' => $name,
                'path' => $fullPath,
                'is_dir' => $isDir,
                'size' => $size,
                'size_formatted' => $isDir ? '-' : self::formatBytes($size),
                'extension' => $ext,
                'icon' => self::getFileIcon($isDir, $ext),
                'color' => self::getFileColor($isDir, $ext),
                'permissions' => $perms,
                'modified' => date('d M Y, H:i', $modified),
                'is_editable' => self::isEditable($ext, $isDir),
                'is_image' => in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp', 'ico'])
            ];
        }

        // Sort: Folders first, then files alphabetically
        usort($items, function($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });

        // Generate Breadcrumb
        $breadcrumbs = self::buildBreadcrumbs($safePath);

        return [
            'current_path' => $safePath,
            'parent_path' => dirname($safePath),
            'has_parent' => ($safePath !== '/'),
            'breadcrumbs' => $breadcrumbs,
            'items' => $items,
            'total_items' => count($items),
            'total_folders' => $totalFolders,
            'total_files' => $totalFiles,
            'total_size_formatted' => self::formatBytes($totalDirSize)
        ];
    }

    /**
     * Read File Content
     */
    public static function readFile(string $path): array {
        $safePath = self::resolvePath($path);
        if (!is_file($safePath)) {
            return ['success' => false, 'error' => 'Berkas tidak ditemukan atau bukan berkas teks.'];
        }

        $size = filesize($safePath);
        if ($size > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Berkas terlalu besar untuk diedit langsung (> 2MB).'];
        }

        $content = @file_get_contents($safePath);
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));

        return [
            'success' => true,
            'path' => $safePath,
            'name' => basename($safePath),
            'size_formatted' => self::formatBytes($size),
            'extension' => $ext,
            'content' => $content,
            'modified' => date('d M Y, H:i', filemtime($safePath))
        ];
    }

    /**
     * Save / Write File Content
     */
    public static function saveFile(string $path, string $content): array {
        $safePath = self::resolvePath($path);
        $dir = dirname($safePath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $res = @file_put_contents($safePath, $content);
        if ($res === false) {
            return ['success' => false, 'error' => 'Gagal menyimpan berkas. Periksa izin akses direktori.'];
        }

        return [
            'success' => true,
            'message' => 'Berkas ' . basename($safePath) . ' berhasil disimpan!',
            'path' => $safePath
        ];
    }

    /**
     * Create New File
     */
    public static function createFile(string $dirPath, string $fileName, string $initialContent = ''): array {
        $dir = self::resolvePath($dirPath);
        if (!is_dir($dir)) {
            return ['success' => false, 'error' => 'Direktori tujuan tidak valid.'];
        }

        $fileName = trim(basename($fileName));
        if (empty($fileName)) {
            return ['success' => false, 'error' => 'Nama berkas tidak boleh kosong.'];
        }

        $targetPath = rtrim($dir, '/') . '/' . $fileName;
        if (file_exists($targetPath)) {
            return ['success' => false, 'error' => 'Berkas dengan nama tersebut sudah ada.'];
        }

        $res = @file_put_contents($targetPath, $initialContent);
        if ($res === false) {
            return ['success' => false, 'error' => 'Gagal membuat berkas.'];
        }

        return [
            'success' => true,
            'message' => "Berkas '{$fileName}' berhasil dibuat!",
            'path' => $targetPath
        ];
    }

    /**
     * Create New Folder
     */
    public static function createFolder(string $dirPath, string $folderName): array {
        $dir = self::resolvePath($dirPath);
        if (!is_dir($dir)) {
            return ['success' => false, 'error' => 'Direktori tujuan tidak valid.'];
        }

        $folderName = trim(basename($folderName));
        if (empty($folderName)) {
            return ['success' => false, 'error' => 'Nama folder tidak boleh kosong.'];
        }

        $targetPath = rtrim($dir, '/') . '/' . $folderName;
        if (file_exists($targetPath)) {
            return ['success' => false, 'error' => 'Folder dengan nama tersebut sudah ada.'];
        }

        $ok = @mkdir($targetPath, 0755, true);
        if (!$ok) {
            return ['success' => false, 'error' => 'Gagal membuat folder baru.'];
        }

        return [
            'success' => true,
            'message' => "Folder '{$folderName}' berhasil dibuat!",
            'path' => $targetPath
        ];
    }

    /**
     * Rename File or Folder
     */
    public static function renameItem(string $oldPath, string $newName): array {
        $safeOld = self::resolvePath($oldPath);
        if (!file_exists($safeOld)) {
            return ['success' => false, 'error' => 'Item yang akan diubah namanya tidak ditemukan.'];
        }

        $newName = trim(basename($newName));
        if (empty($newName)) {
            return ['success' => false, 'error' => 'Nama baru tidak boleh kosong.'];
        }

        $targetPath = rtrim(dirname($safeOld), '/') . '/' . $newName;
        if (file_exists($targetPath)) {
            return ['success' => false, 'error' => "Item dengan nama '{$newName}' sudah ada."];
        }

        $ok = @rename($safeOld, $targetPath);
        if (!$ok) {
            return ['success' => false, 'error' => 'Gagal mengubah nama item.'];
        }

        return [
            'success' => true,
            'message' => "Berhasil mengubah nama menjadi '{$newName}'!",
            'new_path' => $targetPath
        ];
    }

    /**
     * Delete File or Folder
     */
    public static function deleteItem(string $path): array {
        $safePath = self::resolvePath($path);
        if (!file_exists($safePath)) {
            return ['success' => false, 'error' => 'Item tidak ditemukan.'];
        }

        // Prevent deletion of root or critical paths
        if ($safePath === '/' || $safePath === '/root' || $safePath === '/etc') {
            return ['success' => false, 'error' => 'Direktori sistem utama tidak boleh dihapus.'];
        }

        if (is_dir($safePath)) {
            $ok = self::deleteDirectoryRecursive($safePath);
        } else {
            $ok = @unlink($safePath);
        }

        if (!$ok) {
            return ['success' => false, 'error' => 'Gagal menghapus item.'];
        }

        return [
            'success' => true,
            'message' => 'Item ' . basename($safePath) . ' berhasil dihapus!'
        ];
    }

    /**
     * Upload File to Current Directory
     */
    public static function uploadFile(string $dirPath, array $fileInfo): array {
        $dir = self::resolvePath($dirPath);
        if (!is_dir($dir)) {
            return ['success' => false, 'error' => 'Direktori tujuan unggah tidak valid.'];
        }

        if (empty($fileInfo['name']) || empty($fileInfo['tmp_name'])) {
            return ['success' => false, 'error' => 'Tidak ada berkas yang diunggah.'];
        }

        $fileName = basename($fileInfo['name']);
        $targetPath = rtrim($dir, '/') . '/' . $fileName;

        $ok = @move_uploaded_file($fileInfo['tmp_name'], $targetPath);
        if (!$ok) {
            return ['success' => false, 'error' => 'Gagal mengunggah berkas ke server.'];
        }

        return [
            'success' => true,
            'message' => "Berkas '{$fileName}' berhasil diunggah!",
            'path' => $targetPath
        ];
    }

    /**
     * Helper: Delete directory recursively
     */
    private static function deleteDirectoryRecursive(string $dir): bool {
        $files = @scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                self::deleteDirectoryRecursive($filePath);
            } else {
                @unlink($filePath);
            }
        }
        return @rmdir($dir);
    }

    /**
     * Helper: Build breadcrumb trail
     */
    private static function buildBreadcrumbs(string $path): array {
        $parts = explode('/', trim($path, '/'));
        $crumbs = [];
        $accumulated = '';

        $crumbs[] = [
            'name' => 'root',
            'path' => '/'
        ];

        foreach ($parts as $p) {
            if (empty($p)) continue;
            $accumulated .= '/' . $p;
            $crumbs[] = [
                'name' => $p,
                'path' => $accumulated
            ];
        }

        return $crumbs;
    }

    /**
     * Helper: Format bytes to human readable string
     */
    public static function formatBytes(int $bytes): string {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $val = round($bytes / pow(1024, $i), 1);
        return $val . ' ' . ($units[$i] ?? 'B');
    }

    /**
     * Helper: Check if file is editable as text
     */
    private static function isEditable(string $ext, bool $isDir): bool {
        if ($isDir) return false;
        $editable = ['php', 'html', 'htm', 'js', 'css', 'json', 'txt', 'md', 'sh', 'conf', 'cfg', 'ini', 'log', 'env', 'xml', 'yaml', 'yml', 'py', 'service'];
        return in_array($ext, $editable);
    }

    /**
     * Helper: Get Bootstrap icon for file type
     */
    private static function getFileIcon(bool $isDir, string $ext): string {
        if ($isDir) return 'bi-folder-fill';
        switch ($ext) {
            case 'php': return 'bi-filetype-php';
            case 'js': return 'bi-filetype-js';
            case 'css': return 'bi-filetype-css';
            case 'html': return 'bi-filetype-html';
            case 'json': return 'bi-filetype-json';
            case 'sh': return 'bi-filetype-sh';
            case 'txt': case 'md': case 'log': return 'bi-file-text-fill';
            case 'png': case 'jpg': case 'jpeg': case 'svg': case 'gif': return 'bi-file-image-fill';
            case 'zip': case 'tar': case 'gz': return 'bi-file-zip-fill';
            default: return 'bi-file-earmark-fill';
        }
    }

    /**
     * Helper: Get color badge for file type
     */
    private static function getFileColor(bool $isDir, string $ext): string {
        if ($isDir) return '#f59e0b'; // Amber for folder
        switch ($ext) {
            case 'php': return '#8b5cf6'; // Purple
            case 'js': return '#eab308'; // Yellow
            case 'css': return '#0284c7'; // Blue
            case 'html': return '#ea580c'; // Orange
            case 'json': return '#10b981'; // Emerald
            case 'sh': return '#14b8a6'; // Teal
            case 'png': case 'jpg': case 'jpeg': case 'svg': return '#ec4899'; // Pink
            case 'zip': case 'tar': case 'gz': return '#ef4444'; // Red
            default: return '#64748b'; // Slate
        }
    }

    // =========================================================================
    // RCLONE CLOUD STORAGE INTEGRATION
    // =========================================================================

    private static $rcloneConfigFile = '/root/.config/rclone/rclone.conf';

    /**
     * Check if Rclone is installed and get status
     */
    public static function getRcloneStatus(): array {
        $bin = trim(@shell_exec('which rclone 2>/dev/null') ?: '');
        $isInstalled = !empty($bin) && file_exists($bin);
        $version = '';
        if ($isInstalled) {
            $verOut = @shell_exec('rclone version 2>/dev/null');
            if (preg_match('/rclone\s+v?([0-9\.\-A-Za-z]+)/i', $verOut, $m)) {
                $version = $m[1];
            }
        }

        $remotes = self::getRcloneRemotes();

        return [
            'installed' => $isInstalled,
            'binary' => $bin,
            'version' => $version ?: '1.60.1',
            'config_path' => self::$rcloneConfigFile,
            'remotes_count' => count($remotes),
            'remotes' => $remotes
        ];
    }

    /**
     * Get Configured Rclone Cloud Remotes
     */
    public static function getRcloneRemotes(): array {
        $remotes = [];
        $out = @shell_exec('rclone listremotes 2>/dev/null');
        $lines = array_filter(array_map('trim', explode("\n", (string)$out)));

        $conf = file_exists(self::$rcloneConfigFile) ? @file_get_contents(self::$rcloneConfigFile) : '';
        $parsedConf = self::parseIniString($conf);

        foreach ($lines as $line) {
            $name = rtrim($line, ':');
            $info = $parsedConf[$name] ?? [];
            $type = $info['type'] ?? 'cloud';

            $remotes[] = [
                'name' => $name,
                'remote' => $name . ':',
                'type' => $type,
                'icon' => self::getCloudIcon($type),
                'color' => self::getCloudColor($type),
                'details' => $info
            ];
        }

        return $remotes;
    }

    /**
     * List Directory in Cloud Remote via Rclone
     */
    public static function listRclonePath(string $remotePath): array {
        $remotePath = trim($remotePath);
        if (strpos($remotePath, ':') === false) {
            $remotePath .= ':';
        }

        // Run rclone lsjson to get clean JSON file listing from Cloud
        $escaped = escapeshellarg($remotePath);
        $cmd = "rclone lsjson {$escaped} --max-depth 1 2>/dev/null";
        $jsonOut = @shell_exec($cmd);
        $rawItems = json_decode($jsonOut, true) ?: [];

        $items = [];
        $totalFiles = 0;
        $totalFolders = 0;
        $totalDirSize = 0;

        foreach ($rawItems as $it) {
            $isDir = !empty($it['IsDir']);
            $name = $it['Name'] ?? 'Unnamed';
            $size = (int)($it['Size'] ?? 0);
            $totalDirSize += $size;
            if ($isDir) $totalFolders++; else $totalFiles++;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $modTime = isset($it['ModTime']) ? date('d M Y, H:i', strtotime($it['ModTime'])) : '-';

            $itemPath = rtrim($remotePath, '/') . '/' . $name;

            $items[] = [
                'name' => $name,
                'path' => $itemPath,
                'is_dir' => $isDir,
                'size' => $size,
                'size_formatted' => $isDir ? '-' : self::formatBytes($size),
                'extension' => $ext,
                'icon' => self::getFileIcon($isDir, $ext),
                'color' => self::getFileColor($isDir, $ext),
                'permissions' => 'cloud',
                'modified' => $modTime,
                'is_editable' => self::isEditable($ext, $isDir),
                'is_image' => in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'gif', 'webp', 'ico']),
                'is_cloud' => true
            ];
        }

        // Sort: Folders first, then files
        usort($items, function($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) return strcasecmp($a['name'], $b['name']);
            return $a['is_dir'] ? -1 : 1;
        });

        // Breadcrumbs for cloud remote
        $breadcrumbs = self::buildRcloneBreadcrumbs($remotePath);
        $parent = self::getRcloneParentPath($remotePath);

        return [
            'current_path' => $remotePath,
            'parent_path' => $parent,
            'has_parent' => ($parent !== null),
            'breadcrumbs' => $breadcrumbs,
            'items' => $items,
            'total_items' => count($items),
            'total_folders' => $totalFolders,
            'total_files' => $totalFiles,
            'total_size_formatted' => self::formatBytes($totalDirSize),
            'is_cloud' => true
        ];
    }

    /**
     * Copy / Sync Local Path to Cloud Remote
     */
    public static function syncLocalToCloud(string $localPath, string $remotePath): array {
        $local = self::resolvePath($localPath);
        if (!file_exists($local)) {
            return ['success' => false, 'error' => 'Berkas lokal tidak ditemukan.'];
        }

        $remotePath = trim($remotePath);
        $escapedLocal = escapeshellarg($local);
        $escapedRemote = escapeshellarg($remotePath);

        if (is_dir($local)) {
            $cmd = "rclone copy {$escapedLocal} {$escapedRemote} 2>&1";
        } else {
            $cmd = "rclone copyto {$escapedLocal} {$escapedRemote} 2>&1";
        }

        $output = @shell_exec($cmd);

        return [
            'success' => true,
            'message' => "Berhasil mencadangkan " . basename($local) . " ke {$remotePath}!",
            'output' => $output
        ];
    }

    /**
     * Download from Cloud Remote to Local Directory
     */
    public static function downloadCloudToLocal(string $remotePath, string $targetLocalDir): array {
        $targetDir = self::resolvePath($targetLocalDir);
        if (!is_dir($targetDir)) {
            return ['success' => false, 'error' => 'Direktori lokal tujuan tidak valid.'];
        }

        $remotePath = trim($remotePath);
        $escapedRemote = escapeshellarg($remotePath);
        $escapedLocal = escapeshellarg($targetDir);

        $cmd = "rclone copy {$escapedRemote} {$escapedLocal} 2>&1";
        $output = @shell_exec($cmd);

        return [
            'success' => true,
            'message' => "Berhasil mengunduh {$remotePath} ke " . basename($targetDir) . "!",
            'output' => $output
        ];
    }

    /**
     * Get / Save Raw Rclone Config
     */
    public static function getRcloneConfigContent(): string {
        if (!file_exists(self::$rcloneConfigFile)) {
            @mkdir(dirname(self::$rcloneConfigFile), 0755, true);
            @touch(self::$rcloneConfigFile);
        }
        return @file_get_contents(self::$rcloneConfigFile) ?: '';
    }

    public static function saveRcloneConfigContent(string $content): bool {
        @mkdir(dirname(self::$rcloneConfigFile), 0755, true);
        return @file_put_contents(self::$rcloneConfigFile, $content) !== false;
    }

    /**
     * Create / Login to New Rclone Remote
     */
    public static function createRcloneRemote(string $name, string $type, array $options): array {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($name));
        if (empty($name)) {
            return ['success' => false, 'error' => 'Nama remote hanya boleh huruf, angka, minus (-), atau underscore (_).'];
        }

        $type = strtolower(trim($type));
        $cmdParts = ["rclone", "config", "create", escapeshellarg($name), escapeshellarg($type)];

        foreach ($options as $k => $v) {
            $k = trim($k);
            $v = trim((string)$v);
            if ($v !== '') {
                $cmdParts[] = escapeshellarg("{$k}={$v}");
            }
        }

        $cmdParts[] = "--non-interactive";
        $cmd = implode(" ", $cmdParts) . " 2>&1";
        $out = @shell_exec($cmd);

        return [
            'success' => true,
            'message' => "Akun remote '{$name}' berhasil didaftarkan!",
            'output' => $out
        ];
    }

    /**
     * Delete an Rclone Remote
     */
    public static function deleteRcloneRemote(string $name): array {
        $name = trim($name, ':');
        $escaped = escapeshellarg($name);
        $cmd = "rclone config delete {$escaped} 2>&1";
        $out = @shell_exec($cmd);

        return [
            'success' => true,
            'message' => "Remote '{$name}' berhasil dihapus.",
            'output' => $out
        ];
    }

    /**
     * Test / Ping Rclone Remote
     */
    public static function testRcloneRemote(string $remoteName): array {
        $remoteName = rtrim($remoteName, ':') . ':';
        $escaped = escapeshellarg($remoteName);
        $cmd = "rclone lsf {$escaped} --max-depth 1 2>&1";
        $out = @shell_exec($cmd);

        // Check if there are fatal errors
        if (stripos($out, 'Failed to') !== false || stripos($out, 'error') !== false) {
            return [
                'success' => false,
                'error' => "Gagal terhubung ke {$remoteName}: " . trim($out)
            ];
        }

        return [
            'success' => true,
            'message' => "Koneksi ke {$remoteName} berhasil & terverifikasi!",
            'output' => $out
        ];
    }

    /**
     * Helper: Parse simple INI file string
     */
    private static function parseIniString(string $ini): array {
        $res = [];
        $curSection = '';
        foreach (explode("\n", $ini) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#' || $line[0] === ';') continue;
            if ($line[0] === '[' && substr($line, -1) === ']') {
                $curSection = trim(substr($line, 1, -1));
                $res[$curSection] = [];
            } elseif ($curSection && strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $res[$curSection][trim($k)] = trim($v);
            }
        }
        return $res;
    }

    /**
     * Helper: Get Cloud Provider Icon
     */
    private static function getCloudIcon(string $type): string {
        switch (strtolower($type)) {
            case 'drive': return 'bi-google';
            case 'onedrive': return 'bi-microsoft';
            case 'dropbox': return 'bi-dropbox';
            case 's3': return 'bi-amazon';
            case 'mega': return 'bi-cloud-arrow-down-fill';
            case 'webdav': case 'nextcloud': case 'owncloud': return 'bi-cloud-check-fill';
            case 'sftp': case 'ftp': return 'bi-hdd-network-fill';
            default: return 'bi-cloud-fill';
        }
    }

    private static function getCloudColor(string $type): string {
        switch (strtolower($type)) {
            case 'drive': return '#34a853';
            case 'onedrive': return '#0078d4';
            case 'dropbox': return '#0061fe';
            case 's3': return '#ff9900';
            case 'mega': return '#d9272e';
            case 'webdav': case 'nextcloud': return '#0284c7';
            default: return '#8b5cf6';
        }
    }

    private static function buildRcloneBreadcrumbs(string $remotePath): array {
        $parts = explode(':', $remotePath, 2);
        $remoteName = $parts[0] . ':';
        $sub = trim($parts[1] ?? '', '/');

        $crumbs = [];
        $crumbs[] = [
            'name' => '☁️ ' . $remoteName,
            'path' => $remoteName
        ];

        if (!empty($sub)) {
            $accum = $remoteName;
            foreach (explode('/', $sub) as $p) {
                if (empty($p)) continue;
                $accum = rtrim($accum, '/') . '/' . $p;
                $crumbs[] = [
                    'name' => $p,
                    'path' => $accum
                ];
            }
        }

        return $crumbs;
    }

    private static function getRcloneParentPath(string $remotePath): ?string {
        $parts = explode(':', $remotePath, 2);
        $remoteName = $parts[0] . ':';
        $sub = trim($parts[1] ?? '', '/');

        if (empty($sub)) {
            return null; // Root of remote
        }

        $subParts = explode('/', $sub);
        array_pop($subParts);
        if (empty($subParts)) {
            return $remoteName;
        }

        return $remoteName . '/' . implode('/', $subParts);
    }
}
