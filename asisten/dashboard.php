<?php
// Letakkan semua logika PHP di bagian paling atas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// Redirect jika bukan asisten atau belum login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'asisten') {
    header("Location: ../login.php");
    exit;
}

// 1. Definisi Variabel untuk Template
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// 2. Panggil Header
require_once 'templates/header.php';

$asisten_id = $_SESSION['user_id'];

// --- PENGAMBILAN DATA STATISTIK ---

// Menghitung Total Modul yang Diajarkan
$totalModulesQuery = "SELECT COUNT(m.id) as total 
                      FROM modul m 
                      JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id 
                      WHERE mp.asisten_id = ?";
$totalModulesStmt = $conn->prepare($totalModulesQuery);
$totalModulesStmt->bind_param("i", $asisten_id);
$totalModulesStmt->execute();
$totalModules = $totalModulesStmt->get_result()->fetch_assoc()['total'];
$totalModulesStmt->close();

// Menghitung Total Laporan Masuk
$totalReportsQuery = "SELECT COUNT(l.id) as total 
                      FROM laporan l 
                      JOIN modul m ON l.modul_id = m.id 
                      JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id 
                      WHERE mp.asisten_id = ?";
$totalReportsStmt = $conn->prepare($totalReportsQuery);
$totalReportsStmt->bind_param("i", $asisten_id);
$totalReportsStmt->execute();
$totalReports = $totalReportsStmt->get_result()->fetch_assoc()['total'];
$totalReportsStmt->close();

// Menghitung Laporan yang Belum Dinilai (status: dikumpulkan)
$pendingReportsQuery = "SELECT COUNT(l.id) as total 
                        FROM laporan l 
                        JOIN modul m ON l.modul_id = m.id 
                        JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id 
                        WHERE mp.asisten_id = ? AND l.status = 'dikumpulkan'"; // Diperbaiki dari 'pending'
$pendingReportsStmt = $conn->prepare($pendingReportsQuery);
$pendingReportsStmt->bind_param("i", $asisten_id);
$pendingReportsStmt->execute();
$pendingReports = $pendingReportsStmt->get_result()->fetch_assoc()['total'];
$pendingReportsStmt->close();

// Mengambil Aktivitas Laporan Terbaru
$recentActivitiesQuery = "SELECT l.tanggal_upload, l.status, u.nama as mahasiswa_nama, m.judul as modul_judul
                          FROM laporan l
                          JOIN users u ON l.mahasiswa_id = u.id
                          JOIN modul m ON l.modul_id = m.id
                          JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id
                          WHERE mp.asisten_id = ?
                          ORDER BY l.tanggal_upload DESC
                          LIMIT 5";
$recentActivitiesStmt = $conn->prepare($recentActivitiesQuery);
$recentActivitiesStmt->bind_param("i", $asisten_id);
$recentActivitiesStmt->execute();
$recentActivities = $recentActivitiesStmt->get_result();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    
    <div class="bg-white p-6 rounded-lg shadow-md flex items-center space-x-4">
        <div class="bg-blue-100 p-3 rounded-full">
            <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">Total Modul Diajarkan</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $totalModules; ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md flex items-center space-x-4">
        <div class="bg-green-100 p-3 rounded-full">
            <svg class="w-6 h-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">Total Laporan Masuk</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $totalReports; ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md flex items-center space-x-4">
        <div class="bg-yellow-100 p-3 rounded-full">
            <svg class="w-6 h-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
            <p class="text-sm text-gray-500">Laporan Belum Dinilai</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo $pendingReports; ?></p>
        </div>
    </div>
</div>

<div class="bg-white p-6 rounded-lg shadow-md mt-8">
    <h3 class="text-xl font-bold text-gray-800 mb-4">Aktivitas Laporan Terbaru</h3>
    <?php if ($recentActivities->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($activity = $recentActivities->fetch_assoc()): ?>
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                        <span class="font-bold text-gray-500"><?php echo strtoupper(substr($activity['mahasiswa_nama'], 0, 2)); ?></span>
                    </div>
                    <div>
                        <p class="text-gray-800">
                            <strong><?php echo htmlspecialchars($activity['mahasiswa_nama']); ?></strong> 
                            mengumpulkan laporan untuk <strong><?php echo htmlspecialchars($activity['modul_judul']); ?></strong>
                        </p>
                        <p class="text-sm text-gray-500"><?php echo date('d M Y, H:i', strtotime($activity['tanggal_upload'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
            <p>Belum ada aktivitas laporan terbaru.</p>
        </div>
    <?php endif; ?>
</div>

<?php
// 3. Panggil Footer
$conn->close();
require_once 'templates/footer.php';
?>