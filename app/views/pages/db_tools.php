<?php
require_role(['admin']);

$tablesAllowed = [
    'siswa' => 'Data Siswa',
    'nilai_rapor' => 'Nilai Rapor',
    'nilai_uam' => 'Nilai UAM',
    'alumni' => 'Data Alumni',
    'pengaturan_akademik' => 'Pengaturan Akademik'
];

// Handle Truncate Table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'truncate') {
    enforce_csrf('db-tools');
    
    $table = $_POST['table'] ?? '';
    if (!isset($tablesAllowed[$table])) {
        set_flash('error', 'Tabel tidak valid atau tidak diizinkan untuk dihapus.');
        redirect('index.php?page=db-tools');
    }
    
    try {
        db()->exec("TRUNCATE TABLE `{$table}`");
        set_flash('success', "Tabel {$tablesAllowed[$table]} berhasil dikosongkan dan AUTO_INCREMENT direset.");
    } catch (Exception $e) {
        set_flash('error', 'Gagal mengosongkan tabel: ' . $e->getMessage());
    }
    redirect('index.php?page=db-tools');
}

// Handle Backup Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    enforce_csrf('db-tools');
    
    try {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $name = $_ENV['DB_NAME'] ?? 'e_leger_mtsn11';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        
        $backupFile = 'backup_' . date('Y-m-d_His') . '.sql';
        $backupPath = dirname(__DIR__, 3) . '/database/backups';
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        $fullPath = $backupPath . '/' . $backupFile;
        
        // Get all tables
        $tables = [];
        $result = db()->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: {$name}\n\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";
        
        foreach ($tables as $table) {
            // Get table structure
            $sql .= "-- --------------------------------------------------------\n";
            $sql .= "-- Table structure for table `{$table}`\n";
            $sql .= "-- --------------------------------------------------------\n\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            $createTable = db()->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $sql .= $createTable['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = db()->query("SELECT * FROM `{$table}`")->fetchAll();
            
            if (count($rows) > 0) {
                // Get column names
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $sql .= "-- Dumping data for table `{$table}`\n\n";
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        file_put_contents($fullPath, $sql);
        
        set_flash('success', "Backup berhasil dibuat: {$backupFile}");
    } catch (Exception $e) {
        set_flash('error', 'Gagal membuat backup: ' . $e->getMessage());
    }
    redirect('index.php?page=db-tools');
}

// Handle Restore Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    enforce_csrf('db-tools');
    
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'File SQL tidak valid atau gagal diupload.');
        redirect('index.php?page=db-tools');
    }
    
    $tmpFile = $_FILES['sql_file']['tmp_name'];
    $fileName = $_FILES['sql_file']['name'];
    
    if (strtolower(substr($fileName, -4)) !== '.sql') {
        set_flash('error', 'File harus berformat .sql');
        redirect('index.php?page=db-tools');
    }
    
    try {
        // Get database credentials
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? 'e_leger_mtsn11';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        
        // Create mysqli connection for multi_query support
        $mysqli = new mysqli($host, $user, $pass, $name, (int)$port);
        
        if ($mysqli->connect_error) {
            throw new Exception('Connection failed: ' . $mysqli->connect_error);
        }
        
        $sql = file_get_contents($tmpFile);
        
        // Execute multi-query
        $mysqli->multi_query($sql);
        
        // Clear all results
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());
        
        if ($mysqli->error) {
            throw new Exception($mysqli->error);
        }
        
        $mysqli->close();
        
        set_flash('success', 'Database berhasil direstore dari file: ' . e($fileName));
    } catch (Exception $e) {
        set_flash('error', 'Gagal restore database: ' . $e->getMessage());
    }
    redirect('index.php?page=db-tools');
}

// Handle Delete Backup File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_backup') {
    enforce_csrf('db-tools');
    
    $filename = $_POST['filename'] ?? '';
    $backupPath = dirname(__DIR__, 3) . '/database/backups/' . basename($filename);
    
    if (file_exists($backupPath) && substr(basename($filename), 0, 7) === 'backup_') {
        unlink($backupPath);
        set_flash('success', 'File backup berhasil dihapus.');
    } else {
        set_flash('error', 'File backup tidak ditemukan.');
    }
    redirect('index.php?page=db-tools');
}

// Get existing backups
$backupPath = dirname(__DIR__, 3) . '/database/backups';
$backups = [];
if (is_dir($backupPath)) {
    $files = scandir($backupPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strtolower(substr($file, -4)) === '.sql') {
            $fullPath = $backupPath . '/' . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($fullPath),
                'time' => filemtime($fullPath)
            ];
        }
    }
    // Sort by time descending
    usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);
}

// Get table row counts
$tableCounts = [];
foreach ($tablesAllowed as $table => $label) {
    $count = db()->query("SELECT COUNT(*) as c FROM `{$table}`")->fetch()['c'] ?? 0;
    $tableCounts[$table] = (int)$count;
}

require dirname(__DIR__) . '/partials/header.php';
?>

<div class="container-fluid">
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h3 class="mb-0">Database Tools</h3>
        </div>
    </div>

    <!-- Truncate Table -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-warning bg-opacity-10">
            <h5 class="mb-0"><i class="bi bi-trash"></i> Kosongkan Tabel</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning border">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Peringatan!</strong> Operasi ini akan menghapus SEMUA data dari tabel yang dipilih dan mereset AUTO_INCREMENT ke 0. 
                Tabel <strong>users</strong> dan <strong>mapel</strong> tidak dapat dikosongkan melalui tool ini.
            </div>
            
            <form method="post" onsubmit="return confirm('YAKIN ingin mengosongkan tabel ini? Data yang dihapus TIDAK DAPAT dikembalikan!');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="truncate">
                
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Tabel</label>
                        <select name="table" class="form-select" required>
                            <option value="">-- Pilih Tabel --</option>
                            <?php foreach ($tablesAllowed as $table => $label): ?>
                                <option value="<?= e($table) ?>">
                                    <?= e($label) ?> (<?= e(number_format($tableCounts[$table])) ?> baris)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Kosongkan Tabel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Backup Database -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-success bg-opacity-10">
            <h5 class="mb-0"><i class="bi bi-download"></i> Backup Database</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info border">
                <i class="bi bi-info-circle"></i> 
                Backup akan menyimpan seluruh struktur tabel dan data ke file .sql di folder <code>database/backups/</code>
            </div>
            
            <form method="post" onsubmit="return confirm('Buat backup database sekarang?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="backup">
                
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-download"></i> Buat Backup Sekarang
                </button>
            </form>
            
            <?php if (count($backups) > 0): ?>
                <hr>
                <h6 class="mb-3">File Backup Tersedia</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><code><?= e($backup['name']) ?></code></td>
                                    <td><?= e(number_format($backup['size'] / 1024, 2)) ?> KB</td>
                                    <td><?= e(date('d/m/Y H:i:s', $backup['time'])) ?></td>
                                    <td>
                                        <a href="database/backups/<?= e($backup['name']) ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           download>
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus file backup ini?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?= e($backup['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <hr>
                <p class="text-muted mb-0">Belum ada file backup.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Restore Database -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-danger bg-opacity-10">
            <h5 class="mb-0"><i class="bi bi-upload"></i> Restore Database</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger border">
                <i class="bi bi-exclamation-octagon"></i> 
                <strong>BAHAYA!</strong> Restore akan menimpa SELURUH database dengan data dari file SQL yang diupload. 
                Pastikan Anda memiliki backup terbaru sebelum melakukan restore.
            </div>
            
            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('YAKIN ingin restore database? Seluruh data saat ini akan ditimpa!');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="restore">
                
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Upload File SQL</label>
                        <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-upload"></i> Restore Database
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
