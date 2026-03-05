<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';

$publicPages = ['login'];
if (!in_array($page, $publicPages, true)) {
    require_login();
}

switch ($page) {
    case 'login':
        require dirname(__DIR__) . '/app/views/pages/login.php';
        break;
    case 'logout':
        session_destroy();
        redirect('index.php?page=login');
        break;
    case 'dashboard':
        require dirname(__DIR__) . '/app/views/pages/dashboard.php';
        break;
    case 'users':
        require_role(['admin']);
        require dirname(__DIR__) . '/app/views/pages/users.php';
        break;
    case 'mapel':
        require_role(['admin']);
        require dirname(__DIR__) . '/app/views/pages/mapel.php';
        break;
    case 'siswa':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/siswa.php';
        break;
    case 'data-nilai':
    case 'nilai-import':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/nilai_import.php';
        break;
    case 'semester-control':
        require_role(['admin']);
        require dirname(__DIR__) . '/app/views/pages/semester_control.php';
        break;
    case 'kelulusan':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/kelulusan.php';
        break;
    case 'alumni':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/alumni.php';
        break;
    case 'ekspor-cetak':
    case 'laporan':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/laporan.php';
        break;
    case 'profile':
        require_role(['admin', 'kurikulum']);
        require dirname(__DIR__) . '/app/views/pages/profile.php';
        break;
    default:
        set_flash('error', 'Halaman tidak ditemukan.');
        redirect('index.php?page=dashboard');
}
