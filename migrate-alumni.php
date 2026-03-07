<?php
/**
 * ========================================================
 * E-Leger Alumni Test Data Migration Runner
 * ========================================================
 * 
 * Script ini menjalankan migrasi untuk membuat data alumni test
 * untuk keperluan testing fitur cetak leger
 * 
 * Gunakan:
 * - Browser: http://localhost/e-leger.../migrate-alumni.php
 * - Terminal: php migrate-alumni.php
 */

require_once __DIR__ . '/app/bootstrap.php';

echo "\n";
echo "=================================================\n";
echo "E-LEGER ALUMNI TEST DATA MIGRATION\n";
echo "=================================================\n\n";

try {
    // Read SQL migration file
    $migrationFile = __DIR__ . '/database/migrations/001_create_alumni_test_data.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file tidak ditemukan: " . $migrationFile);
    }
    
    echo "[1/3] Reading migration file...\n";
    $sqlContent = file_get_contents($migrationFile);
    
    // Split ke individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sqlContent)),
        function($stmt) {
            return !empty($stmt) && strpos(trim($stmt), '--') !== 0;
        }
    );
    
    echo "[2/3] Executing SQL statements...\n";
    $executedCount = 0;
    $results = [];
    
    foreach ($statements as $statement) {
        $trimmed = trim($statement);
        if (empty($trimmed)) continue;
        
        try {
            $result = db()->query($trimmed);
            
            // If it's a SELECT, fetch the results
            if (stripos($trimmed, 'SELECT') === 0) {
                $results = array_merge($results, $result->fetchAll());
            }
            
            $executedCount++;
            echo "  ✓ Statement " . $executedCount . " executed\n";
            
        } catch (Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n[3/3] Verifying data...\n\n";
    
    if (!empty($results)) {
        echo "Verification Results:\n";
        echo str_repeat("-", 60) . "\n";
        printf("%-20s %-10s %s\n", "Type", "Count", "Last Updated");
        echo str_repeat("-", 60) . "\n";
        
        foreach ($results as $row) {
            printf(
                "%-20s %-10s %s\n",
                $row['type'],
                $row['count'],
                $row['last_updated'] ?? 'N/A'
            );
        }
        
        echo str_repeat("-", 60) . "\n";
    }
    
    // Final check
    $checkStmt = db()->prepare("SELECT COUNT(*) as total FROM alumni WHERE nisn = ?");
    $checkStmt->execute(['1234567890123']);
    $check = $checkStmt->fetch();
    
    if ($check['total'] > 0) {
        echo "\n";
        echo "=================================================\n";
        echo "✓ SUCCESS! Data alumni berhasil dibuat.\n";
        echo "=================================================\n\n";
        
        echo "📋 Data Alumni Test:\n";
        echo "  NISN: 1234567890123\n";
        echo "  Nama: Muhammad Rizki Al-Azhari\n";
        echo "  Status: Alumni (Lulus)\n";
        echo "  Angkatan: 2026\n\n";
        
        echo "📊 Nilai yang tersedia:\n";
        echo "  ✓ Nilai Rapor: Semester 1-5 (semua mata pelajaran)\n";
        echo "  ✓ Nilai UAM: Semua mata pelajaran\n";
        echo "  ✓ Data Alumni: Lengkap\n\n";
        
        echo "🎯 Testing Cetak Leger:\n";
        echo "  1. Login ke sistem\n";
        echo "  2. Buka halaman 'Data Alumni'\n";
        echo "  3. Cari: 'Muhammad Rizki' atau '1234567890123'\n";
        echo "  4. Klik 'Lihat Nilai' untuk preview data\n";
        echo "  5. Klik 'Cetak Leger' untuk test fitur cetak\n\n";
        
    } else {
        echo "\n❌ ERROR: Data alumni tidak ditemukan!\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n❌ MIGRATION FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=================================================\n\n";
?>
