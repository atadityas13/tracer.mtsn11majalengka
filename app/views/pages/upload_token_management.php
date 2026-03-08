<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Upload Token Management Page
 * Deskripsi: Admin/Kurikulum dapat manage token harian untuk verifikasi upload
 * 
 * @package    TRACER-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2026 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2026-03-09
 * 
 * Features:
 * - Toggle verifikasi token aktif/non-aktif
 * - Pilih mode token: manual, daily auto, atau disabled
 * - Generate token manual on-demand
 * - Auto-generate daily token
 * - Tampilkan history token (generated, used, expired)
 * - Copy-to-clipboard untuk token
 * 
 * ========================================================
 */

require dirname(__DIR__) . '/partials/header.php';

// Hanya admin/kurikulum yang bisa akses
require_login();
if (!in_array(current_user()['role'] ?? '', ['admin', 'kurikulum'])) {
    set_flash('error', 'Anda tidak memiliki akses ke halaman ini.');
    redirect('index.php?page=dashboard');
}

enforce_csrf('upload_token_management');

$setting = setting_akademik();
$action = strtolower(trim((string) ($_POST['action'] ?? '')));

// Get current settings
$stmt = db()->query("SELECT require_upload_token, token_mode FROM pengaturan_akademik WHERE is_aktif=1 LIMIT 1");
$tokenSetting = $stmt->fetch();
$requireToken = $tokenSetting['require_upload_token'] == 1;
$tokenMode = $tokenSetting['token_mode'] ?? 'daily';

// Get current valid token
$currentToken = get_current_upload_token();

// Handle toggle require_upload_token
if ($action === 'toggle_require') {
    $newState = (int) !$requireToken;
    $stmt = db()->prepare("UPDATE pengaturan_akademik SET require_upload_token = ? WHERE is_aktif=1");
    $stmt->execute([$newState]);
    
    set_flash('success', $newState ? 'Token verifikasi DIAKTIFKAN.' : 'Token verifikasi DINONAKTIFKAN.');
    redirect('index.php?page=upload-token-management');
}

// Handle change token mode
if ($action === 'change_mode') {
    $newMode = strtolower(trim((string) ($_POST['mode'] ?? '')));
    if (in_array($newMode, ['manual', 'daily', 'disabled'])) {
        $stmt = db()->prepare("UPDATE pengaturan_akademik SET token_mode = ? WHERE is_aktif=1");
        $stmt->execute([$newMode]);
        
        set_flash('success', "Mode token diubah ke: $newMode");
        redirect('index.php?page=upload-token-management');
    }
}

// Handle manual token generation
if ($action === 'generate_manual') {
    $token = generate_upload_token('manual', current_user()['username'] ?? 'system', 24);
    if ($token) {
        set_flash('success', "Token manual berhasil dibuat: $token");
    } else {
        set_flash('error', 'Gagal membuat token manual. Silakan coba lagi.');
    }
    redirect('index.php?page=upload-token-management');
}

// Handle auto-generate daily token
if ($action === 'generate_daily') {
    // Revoke old daily token for today
    $today = date('Y-m-d');
    $stmt = db()->prepare("
        UPDATE upload_token 
        SET is_used = 1
        WHERE created_tahun_ajaran = :ta
        AND created_semester_aktif = :sem
        AND token_type = 'daily'
        AND DATE(created_at) = :today
        AND is_used = 0
    ");
    $stmt->execute([
        'ta' => $setting['tahun_ajaran'],
        'sem' => $setting['semester_aktif'],
        'today' => $today
    ]);
    
    $token = generate_upload_token('daily', current_user()['username'] ?? 'system', 24);
    if ($token) {
        set_flash('success', "Token harian otomatis dibuat: $token (berlaku 24 jam)");
    } else {
        set_flash('error', 'Gagal membuat token harian. Silakan coba lagi.');
    }
    redirect('index.php?page=upload-token-management');
}

// Get token history
$stmt = db()->prepare("
    SELECT 
        id, token, token_type, created_by, created_at, expires_at, 
        is_used, used_by, used_at
    FROM upload_token
    WHERE created_tahun_ajaran = :ta
    AND created_semester_aktif = :sem
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([
    'ta' => $setting['tahun_ajaran'],
    'sem' => $setting['semester_aktif']
]);
$tokenHistory = $stmt->fetchAll();
?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h3 class="mb-0">⚙️ Pengaturan Verifikasi Token</h3>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-semibold">Status Verifikasi Token</div>
                        <div class="small text-secondary">
                            <?php if ($requireToken): ?>
                                <span class="badge bg-success">AKTIF</span> - Token diperlukan untuk upload
                            <?php else: ?>
                                <span class="badge bg-secondary">NONAKTIF</span> - Token tidak diperlukan
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" style="display: inline;">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="toggle_require">
                        <button type="submit" class="btn btn-sm <?= $requireToken ? 'btn-danger' : 'btn-success' ?>" onclick="return confirm('Ubah status verifikasi token?')">
                            <?= $requireToken ? 'NONAKTIFKAN' : 'AKTIFKAN' ?>
                        </button>
                    </form>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold mb-2">Mode Token</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post" style="display: inline;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="change_mode">
                            <input type="hidden" name="mode" value="manual">
                            <button type="submit" class="btn btn-outline-primary btn-sm <?= $tokenMode === 'manual' ? 'active btn-primary' : '' ?>">
                                🔧 Manual
                            </button>
                        </form>
                        <form method="post" style="display: inline;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="change_mode">
                            <input type="hidden" name="mode" value="daily">
                            <button type="submit" class="btn btn-outline-primary btn-sm <?= $tokenMode === 'daily' ? 'active btn-primary' : '' ?>">
                                📅 Harian Otomatis
                            </button>
                        </form>
                        <form method="post" style="display: inline;">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="change_mode">
                            <input type="hidden" name="mode" value="disabled">
                            <button type="submit" class="btn btn-outline-danger btn-sm <?= $tokenMode === 'disabled' ? 'active btn-danger' : '' ?>">
                                ❌ Disabled
                            </button>
                        </form>
                    </div>
                    <div class="small text-secondary mt-2">
                        <?php if ($tokenMode === 'manual'): ?>
                            Mode Manual: Anda dapat membuat token kapan saja sesuai kebutuhan.
                        <?php elseif ($tokenMode === 'daily'): ?>
                            Mode Harian: Sistem secara otomatis membuat token baru setiap hari.
                        <?php else: ?>
                            Mode Disabled: Tidak ada token yang digunakan.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h3 class="mb-0">🔐 Token Saat Ini</h3>
            </div>
            <div class="card-body">
                <?php if ($currentToken): ?>
                    <div class="p-3 bg-light rounded mb-3 text-center">
                        <div class="small text-secondary mb-2">Token Aktif</div>
                        <div class="fs-5 font-monospace fw-bold mb-2"><?= e($currentToken) ?></div>
                        <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?= e($currentToken) ?>')">
                            📋 Copy Token
                        </button>
                    </div>
                    <div class="small text-secondary text-center">
                        Guru/Homeroom dapat menggunakan token ini untuk verifikasi upload.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <strong>✓ Belum ada token aktif</strong><br>
                        Buat token baru menggunakan tombol di bawah ini.
                    </div>
                <?php endif; ?>

                <div class="mt-3 pt-3 border-top">
                    <div class="d-grid gap-2">
                        <?php if ($tokenMode !== 'disabled'): ?>
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="generate_manual">
                                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Buat token manual baru?')">
                                    + Buat Token Manual
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($tokenMode === 'daily' || $tokenMode === 'manual'): ?>
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="generate_daily">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Generate token harian baru?')">
                                    📅 Generate Token Harian
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white border-0 pt-3">
        <h3 class="mb-0">📋 History Token</h3>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Type</th>
                        <th>dibuat oleh</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tokenHistory)): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-3">Belum ada history token</td></tr>
                    <?php else: ?>
                        <?php foreach ($tokenHistory as $t): 
                            $isExpired = $t['expires_at'] && strtotime($t['expires_at']) < time();
                            $isUsed = $t['is_used'] == 1;
                            if ($isExpired) {
                                $status = '<span class="badge bg-secondary">Expired</span>';
                            } elseif ($isUsed) {
                                $status = '<span class="badge bg-info">Used</span>';
                            } else {
                                $status = '<span class="badge bg-success">Active</span>';
                            }
                        ?>
                            <tr>
                                <td><code><?= e($t['token']) ?></code></td>
                                <td>
                                    <?php if ($t['token_type'] === 'daily'): ?>
                                        <span class="badge bg-primary">Harian</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($t['created_by']) ?></td>
                                <td><?= e(substr($t['created_at'], 0, 19)) ?></td>
                                <td><?= $status ?></td>
                                <td>
                                    <?php if ($t['expires_at']): ?>
                                        <?= e(substr($t['expires_at'], 0, 19)) ?>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Token copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy:', err);
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('Token copied to clipboard!');
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
