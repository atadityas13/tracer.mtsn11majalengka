<?php
$user = current_user();
$flash = get_flash();
$page = $_GET['page'] ?? 'dashboard';
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(app_config('name')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
<div class="app-shell">
    <?php if ($user): ?>
        <aside class="sidebar">
            <div class="brand-wrap mb-3">
                <div class="small text-white-50">e-Leger</div>
                <div class="h5 mb-0 text-white fw-semibold">MTsN 11 Majalengka</div>
            </div>

            <div class="menu-label">Menu Utama</div>
            <nav class="nav flex-column gap-1 mb-3">
                <a class="sidebar-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <?php if ($isAdmin): ?>
                    <a class="sidebar-link <?= $page === 'users' ? 'active' : '' ?>" href="index.php?page=users">
                        <i class="bi bi-people"></i> Data User
                    </a>
                    <a class="sidebar-link <?= $page === 'mapel' ? 'active' : '' ?>" href="index.php?page=mapel">
                        <i class="bi bi-journal-bookmark"></i> Data Mapel
                    </a>
                    <a class="sidebar-link <?= $page === 'siswa' ? 'active' : '' ?>" href="index.php?page=siswa">
                        <i class="bi bi-person-vcard"></i> Data Siswa
                    </a>
                <?php endif; ?>

                <a class="sidebar-link <?= $page === 'nilai-import' ? 'active' : '' ?>" href="index.php?page=nilai-import">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Olah Nilai
                </a>
                <?php if ($isAdmin): ?>
                    <a class="sidebar-link <?= $page === 'semester-control' ? 'active' : '' ?>" href="index.php?page=semester-control">
                        <i class="bi bi-calendar2-check"></i> Kontrol Semester
                    </a>
                    <a class="sidebar-link <?= $page === 'kelulusan' ? 'active' : '' ?>" href="index.php?page=kelulusan">
                        <i class="bi bi-mortarboard"></i> Kelulusan
                    </a>
                <?php endif; ?>
                <a class="sidebar-link <?= $page === 'laporan' ? 'active' : '' ?>" href="index.php?page=laporan">
                    <i class="bi bi-printer"></i> Laporan & Cetak
                </a>
            </nav>
        </aside>
    <?php endif; ?>

    <main class="main-content">
        <?php if ($user): ?>
            <header class="topbar card shadow-sm border-0 mb-3">
                <?php $set = setting_akademik(); ?>
                <div>
                    <div class="fw-semibold">Sistem e-Leger</div>
                    <small class="text-secondary">Kelola data nilai, semester, dan kelulusan</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light border dropdown-toggle profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= e($user['nama_lengkap']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="px-3 pt-2 pb-1">
                            <div class="fw-semibold mb-1"><?= e($user['nama_lengkap']) ?></div>
                            <div class="small text-secondary">Role: <?= e(strtoupper($user['role'])) ?></div>
                            <div class="small text-secondary">TA: <?= e($set['tahun_ajaran']) ?> (<?= e($set['semester_aktif']) ?>)</div>
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
            </header>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div id="swal-flash"
                 data-type="<?= e($flash['type'] ?? 'success') ?>"
                 data-message="<?= e($flash['message'] ?? '') ?>"
                 hidden></div>
        <?php endif; ?>
