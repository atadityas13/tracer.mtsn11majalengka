<?php
/**
 * Endpoint khusus cetak transkrip dengan URL terpisah.
 * Proses PDF didelegasikan ke logika asli laporan.php agar output tetap identik.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method tidak didukung');
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['transkrip', 'bulk_transkrip'], true)) {
    http_response_code(400);
    exit('Action tidak valid');
}

// Delegate to the original generator to keep legacy formatting and value logic.
require dirname(__DIR__) . '/app/views/pages/laporan.php';
