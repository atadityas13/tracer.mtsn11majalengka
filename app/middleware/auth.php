<?php

declare(strict_types=1);

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        redirect('index.php?page=login');
    }
}

function require_role(array $roles): void
{
    require_login();
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        set_flash('error', 'Akses ditolak untuk menu ini.');
        redirect('index.php?page=dashboard');
    }
}
