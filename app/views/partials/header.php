<?php
/**
 * ========================================================
 * TRACER MTSN 11 MAJALENGKA
 * ========================================================
 * 
 * Sistem Manajemen Data Nilai Siswa
 * MTsN 11 Majalengka, Kabupaten Majalengka, Jawa Barat
 * 
 * File: Header Partial Template
 * Deskripsi: Template header yang digunakan di setiap halaman aplikasi (authenticated pages)
 * 
 * @package    TRACER-MTSN11
 * @author     MTsN 11 Majalengka Development Team
 * @copyright  2026 MTsN 11 Majalengka. All rights reserved.
 * @license    Proprietary License
 * @version    1.0.0
 * @since      2026-01-01
 * @created    2026-03-06
 * @modified   2026-03-06
 * 
 * DISCLAIMER:
 * Software ini dikembangkan khusus untuk MTsN 11 Majalengka.
 * Dilarang keras menyalin, memodifikasi, atau mendistribusikan
 * tanpa izin tertulis dari MTsN 11 Majalengka.
 * 
 * CONTACT:
 * Website: https://mtsn11majalengka.sch.id
 * Email: mtsn11majalengka@gmail.com
 * Phone: (0233) 8319182
 * 
 * ========================================================
 */
$user = current_user();
$flash = get_flash();
$page = $_GET['page'] ?? 'dashboard';
$isAdmin = ($user['role'] ?? '') === 'admin';
$isKurikulum = ($user['role'] ?? '') === 'kurikulum';
$canManageTokens = $isAdmin || $isKurikulum;
$isSettingPage = in_array($page, ['mapel', 'semester-control', 'users', 'db-tools', 'upload-token-management'], true);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="apple-touch-icon" href="assets/logo-tracer-mtsn11majalengka.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="sidebar">
            <button type="button" class="brand-wrap border-0" data-bs-toggle="modal" data-bs-target="#aboutTracerModal" aria-label="Tentang Aplikasi TRACER">
                <img src="assets/logo-tracer-mtsn11majalengka.png" alt="TRACER Logo">
            </button>

            <div class="menu-label">Menu Utama</div>
            <nav class="nav flex-column gap-1 mb-3">
                <a class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <?php if (in_array(($user['role'] ?? ''), ['admin', 'kurikulum'], true)): ?>
                    <a class="sidebar-link <?= $page === 'siswa' ? 'active' : '' ?>" href="index.php?page=siswa">
                        <i class="bi bi-person-vcard"></i> Data Siswa
                    </a>
                <?php endif; ?>
                <a class="sidebar-link <?= in_array($page, ['data-nilai', 'nilai-import'], true) ? 'active' : '' ?>" href="index.php?page=data-nilai">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Data Nilai
                </a>
                <?php if (in_array(($user['role'] ?? ''), ['admin', 'kurikulum'], true)): ?>
                    <a class="sidebar-link <?= $page === 'finalisasi' ? 'active' : '' ?>" href="index.php?page=finalisasi">
                        <i class="bi bi-check2-square"></i> Finalisasi
                    </a>
                <?php endif; ?>
                <?php if (in_array(($user['role'] ?? ''), ['admin', 'kurikulum'], true)): ?>
                    <a class="sidebar-link <?= $page === 'kelulusan' ? 'active' : '' ?>" href="index.php?page=kelulusan">
                        <i class="bi bi-mortarboard"></i> Kelulusan
                    </a>
                <?php endif; ?>
                <a class="sidebar-link <?= $page === 'alumni' ? 'active' : '' ?>" href="index.php?page=alumni">
                    <i class="bi bi-people-fill"></i> Alumni
                </a>
                <a class="sidebar-link <?= in_array($page, ['ekspor-cetak', 'laporan'], true) ? 'active' : '' ?>" href="index.php?page=ekspor-cetak">
                    <i class="bi bi-printer"></i> Ekspor dan Cetak
                </a>
                <?php if ($canManageTokens): ?>
                    <a class="sidebar-link <?= $page === 'upload-token-management' ? 'active' : '' ?>" href="index.php?page=upload-token-management">
                        <i class="bi bi-key"></i> Manajemen Token Upload
                    </a>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <button class="sidebar-link sidebar-toggle w-100 border-0 <?= $isSettingPage ? 'active' : '' ?>"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#pengaturanMenu"
                            aria-expanded="<?= $isSettingPage ? 'true' : 'false' ?>"
                            aria-controls="pengaturanMenu">
                        <span><i class="bi bi-gear me-2"></i>Pengaturan</span>
                        <i class="bi bi-chevron-down sidebar-toggle-icon"></i>
                    </button>
                    <div id="pengaturanMenu" class="collapse <?= $isSettingPage ? 'show' : '' ?>">
                        <a class="sidebar-link sidebar-sub <?= $page === 'mapel' ? 'active' : '' ?>" href="index.php?page=mapel">
                            <i class="bi bi-journal-bookmark"></i> Mapel
                        </a>
                        <a class="sidebar-link sidebar-sub <?= $page === 'users' ? 'active' : '' ?>" href="index.php?page=users">
                            <i class="bi bi-people"></i> User
                        </a>
                        <a class="sidebar-link sidebar-sub <?= $page === 'semester-control' ? 'active' : '' ?>" href="index.php?page=semester-control">
                            <i class="bi bi-calendar2-week"></i> Semester
                        </a>
                        <a class="sidebar-link sidebar-sub <?= $page === 'db-tools' ? 'active' : '' ?>" href="index.php?page=db-tools">
                            <i class="bi bi-database-gear"></i> Database Tools
                        </a>
                    </div>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer small">
                <div>© <?= date('Y') ?> MTsN 11 Majalengka</div>
                <div>• TRACER v1.0.0 •<br>Developed by <a href="https://www.instagram.com/atadityas_13/" class="link-clean" target="_blank" rel="noopener noreferrer">A.T. Aditya</a></div>
            </div>
        </aside>

        <div class="modal fade" id="aboutTracerModal" tabindex="-1" aria-labelledby="aboutTracerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header" style="background: linear-gradient(135deg, #064e3b 0%, #10b981 100%); color: #fff;">
                        <h5 class="modal-title" id="aboutTracerModalLabel">Tentang Aplikasi TRACER</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <div style="display: inline-block; padding: 0.55rem 0.9rem; border-radius: 0.85rem; background: linear-gradient(135deg, #064e3b 0%, #10b981 100%); box-shadow: 0 6px 18px rgba(6, 78, 59, 0.18);">
                                <img src="assets/logo-tracer-mtsn11majalengka.png" alt="TRACER Logo" style="height: 86px; width: auto; object-fit: contain;">
                            </div>
                        </div>
                        <p class="mb-2 fw-semibold">TRACER MTsN 11 Majalengka</p>
                        <p class="mb-2 small text-secondary">Transkrip &amp; Academic Ledger</p>
                        <p class="mb-2 text-secondary"><em>Tracing Progress, Graduating Success.</em></p>
                        <p class="small text-secondary mb-3">
                            Sistem manajemen pelacakan progres nilai siswa hingga menjadi transkrip nilai akhir dengan fitur verifikasi QR, kontrol semester, finalisasi nilai, dan proses kelulusan. Dikembangkan khusus untuk MTsN 11 Majalengka guna mendukung proses akademik yang efisien, transparan, dan akurat.
                        </p>
                        <hr>
                        <div class="small">
                            <div><strong>Published:</strong> March 6, 2026</div>
                            <div><strong>Version:</strong> TRACER v1.0.0</div>
                            <div><strong>Developer:</strong> <a href="https://www.instagram.com/atadityas_13/" class="link-clean" target="_blank" rel="noopener noreferrer">A.T. Aditya</a></div>
                            <div class="mt-2"><strong>Special Honor and Thanks:</strong> Ibu Nia Haryati, S.Pd., Admin Kurikulum yang menginspirasi dan menginisiasi pengembangan aplikasi ini.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <main class="main-content">
        <?php if ($user): ?>
            <header class="topbar card shadow-sm border-0 mb-3">
                <?php $set = setting_akademik(); ?>
                <div class="topbar-left">
                    <div class="fw-semibold text-white">TRACER<br>Transkrip & Academic Ledger</div>
                    <small class="text-white-50">Tracing Progress, Graduating Success.</small>
                </div>
                <div class="topbar-center">
                    <div class="fw-semibold fs-5 text-white">TA: <?= e($set['tahun_ajaran']) ?></div>
                    <small class="text-white-50">Semester <?= e($set['semester_aktif']) ?></small>
                </div>
                <div class="topbar-right">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e($user['nama_lengkap']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="px-3 pt-2 pb-1">
                                <div class="fw-semibold mb-1"><?= e($user['nama_lengkap']) ?></div>
                                <div class="small text-secondary">Role: <?= e(strtoupper($user['role'])) ?></div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="index.php?page=profile">
                                    <i class="bi bi-person-gear me-2"></i>Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="index.php?page=logout" data-confirm="Anda yakin ingin keluar dari aplikasi?" data-confirm-title="Konfirmasi Keluar">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div id="swal-flash"
                 data-type="<?= e($flash['type'] ?? 'success') ?>"
                 data-message="<?= $flash['message'] ?? '' ?>"
                 hidden></div>
        <?php endif; ?>
