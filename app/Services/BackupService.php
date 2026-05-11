<?php

class BackupService
{
    private string $backupDir;
    private string $user;
    private string $pass;
    private string $db;
    private string $socket;
    private string $mysqldump;

    public function __construct()
    {
        $this->backupDir  = APP_ROOT . '/storage/backups';
        $this->user       = 'root';
        $this->pass       = '';
        $this->db         = 'lumina_pos';
        $this->socket     = '/opt/lampp/var/mysql/mysql.sock';
        $this->mysqldump  = '/opt/lampp/bin/mysqldump';

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function createBackup(): array
    {
        $filename = 'lumina_backup_' . date('Ymd_His') . '.sql';
        $filepath = $this->backupDir . '/' . $filename;

        $user   = escapeshellarg($this->user);
        $db     = escapeshellarg($this->db);
        $socket = escapeshellarg($this->socket);
        $bin    = escapeshellarg($this->mysqldump);

        $passFlag = $this->pass !== '' ? ' -p' . escapeshellarg($this->pass) : '';

        $cmd = "{$bin} --user={$user}{$passFlag}"
             . " --socket={$socket}"
             . " --routines --triggers --single-transaction"
             . " {$db}";

        $output   = shell_exec($cmd . ' 2>/dev/null');

        if (empty($output)) {
            // Capture stderr for diagnosis
            $err = shell_exec($cmd . ' 2>&1 1>/dev/null');
            return ['success' => false, 'error' => 'mysqldump produced no output. ' . trim($err ?? '')];
        }

        if (file_put_contents($filepath, $output, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Could not write backup file to storage/backups/'];
        }

        return ['success' => true, 'filename' => $filename, 'size' => filesize($filepath)];
    }

    public function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $files = glob($this->backupDir . '/lumina_backup_*.sql') ?: [];

        $backups = array_map(function (string $path): array {
            $name = basename($path);
            // Parse date from filename: lumina_backup_YYYYMMDD_HHMMSS.sql
            $created = null;
            if (preg_match('/lumina_backup_(\d{8})_(\d{6})\.sql$/', $name, $m)) {
                $created = DateTime::createFromFormat('Ymd His', $m[1] . ' ' . $m[2]);
                $created = $created ? $created->format('Y-m-d H:i:s') : null;
            }
            return [
                'filename'   => $name,
                'created_at' => $created ?? date('Y-m-d H:i:s', filemtime($path)),
                'size'       => filesize($path),
                'size_human' => $this->formatBytes(filesize($path)),
            ];
        }, $files);

        usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
        return $bytes . ' B';
    }
}
