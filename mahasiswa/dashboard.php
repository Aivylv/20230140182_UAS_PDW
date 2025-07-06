<?php
// Letakkan semua logika PHP di bagian paling atas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// Redirect jika bukan mahasiswa atau belum login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit;
}

$id_mahasiswa = $_SESSION['user_id'];

// --- PENGAMBILAN DATA DINAMIS ---

// 1. Menghitung jumlah praktikum yang diikuti
$sql_praktikum_diikuti = "SELECT COUNT(id) as total FROM pendaftaran WHERE mahasiswa_id = ?";
$stmt_praktikum = $conn->prepare($sql_praktikum_diikuti);
$stmt_praktikum->bind_param("i", $id_mahasiswa);
$stmt_praktikum->execute();
$count_praktikum = $stmt_praktikum->get_result()->fetch_assoc()['total'];
$stmt_praktikum->close();

// 2. Menghitung jumlah tugas yang sudah dinilai (selesai)
$sql_tugas_selesai = "SELECT COUNT(id) as total FROM laporan WHERE mahasiswa_id = ? AND status = 'dinilai'";
$stmt_selesai = $conn->prepare($sql_tugas_selesai);
$stmt_selesai->bind_param("i", $id_mahasiswa);
$stmt_selesai->execute();
$count_tugas_selesai = $stmt_selesai->get_result()->fetch_assoc()['total'];
$stmt_selesai->close();

// 3. Menghitung jumlah tugas yang menunggu (total modul dikurangi yang sudah dikumpul)
// Ambil total modul dari semua praktikum yang diikuti
$sql_total_modul = "SELECT COUNT(m.id) as total 
                    FROM modul m
                    JOIN pendaftaran p ON m.mata_praktikum_id = p.mata_praktikum_id
                    WHERE p.mahasiswa_id = ?";
$stmt_total_modul = $conn->prepare($sql_total_modul);
$stmt_total_modul->bind_param("i", $id_mahasiswa);
$stmt_total_modul->execute();
$total_modul = $stmt_total_modul->get_result()->fetch_assoc()['total'];
$stmt_total_modul->close();

// Ambil total laporan yang sudah dikumpulkan
$sql_laporan_dikumpul = "SELECT COUNT(id) as total FROM laporan WHERE mahasiswa_id = ?";
$stmt_laporan_dikumpul = $conn->prepare($sql_laporan_dikumpul);
$stmt_laporan_dikumpul->bind_param("i", $id_mahasiswa);
$stmt_laporan_dikumpul->execute();
$total_laporan_dikumpul = $stmt_laporan_dikumpul->get_result()->fetch_assoc()['total'];
$stmt_laporan_dikumpul->close();

$count_tugas_menunggu = $total_modul - $total_laporan_dikumpul;


// 4. Mengambil Notifikasi Terbaru (Contoh: 3 aktivitas terakhir)
$notifikasi = [];
// a. Nilai terakhir yang diberikan
$sql_nilai_terakhir = "SELECT m.judul, mp.nama_praktikum, mp.id AS mata_praktikum_id
                       FROM laporan l
                       JOIN modul m ON l.modul_id = m.id
                       JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id
                       WHERE l.mahasiswa_id = ? AND l.status = 'dinilai' 
                       ORDER BY l.tanggal_upload DESC LIMIT 1";
$stmt_nilai = $conn->prepare($sql_nilai_terakhir);
$stmt_nilai->bind_param("i", $id_mahasiswa);
$stmt_nilai->execute();
$nilai_terakhir = $stmt_nilai->get_result()->fetch_assoc();
if ($nilai_terakhir) {
    $notifikasi[] = ['tipe' => 'nilai', 'data' => $nilai_terakhir];
}
$stmt_nilai->close();

// b. Pendaftaran terakhir
$sql_daftar_terakhir = "SELECT mp.nama_praktikum, p.mata_praktikum_id
                        FROM pendaftaran p
                        JOIN mata_praktikum mp ON p.mata_praktikum_id = mp.id
                        WHERE p.mahasiswa_id = ?
                        ORDER BY p.tanggal_daftar DESC LIMIT 1"; // Diperbaiki dari tanggal_pendaftaran menjadi tanggal_daftar
$stmt_daftar = $conn->prepare($sql_daftar_terakhir);
$stmt_daftar->bind_param("i", $id_mahasiswa);
$stmt_daftar->execute();
$daftar_terakhir = $stmt_daftar->get_result()->fetch_assoc();
if ($daftar_terakhir) {
    $notifikasi[] = ['tipe' => 'daftar', 'data' => $daftar_terakhir];
}
$stmt_daftar->close();


// Definisi variabel untuk template
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once 'templates/header_mahasiswa.php'; 
?>


<div class="bg-gradient-to-r from-blue-500 to-cyan-400 text-white p-8 rounded-xl shadow-lg mb-8">
    <h1 class="text-3xl font-bold">Selamat Datang Kembali, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h1>
    <p class="mt-2 opacity-90">Terus semangat dalam menyelesaikan semua modul praktikummu.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-blue-600"><?php echo $count_praktikum; ?></div>
        <div class="mt-2 text-lg text-gray-600">Praktikum Diikuti</div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-green-500"><?php echo $count_tugas_selesai; ?></div>
        <div class="mt-2 text-lg text-gray-600">Tugas Selesai</div>
    </div>
    
    <div class="bg-white p-6 rounded-xl shadow-md flex flex-col items-center justify-center">
        <div class="text-5xl font-extrabold text-yellow-500"><?php echo $count_tugas_menunggu; ?></div>
        <div class="mt-2 text-lg text-gray-600">Tugas Menunggu</div>
    </div>
    
</div>

<div class="bg-white p-6 rounded-xl shadow-md">
    <h3 class="text-2xl font-bold text-gray-800 mb-4">Aktivitas Terbaru</h3>
    <?php if (!empty($notifikasi)): ?>
    <ul class="space-y-4">
        <?php foreach($notifikasi as $item): ?>
            <?php if ($item['tipe'] == 'nilai'): ?>
            <li class="flex items-start p-3 border-b border-gray-100 last:border-b-0">
                <span class="text-xl mr-4">🔔</span>
                <div>
                    Nilai untuk <a href="course_detail.php?id=<?php echo $item['data']['mata_praktikum_id']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo htmlspecialchars($item['data']['judul']); ?></a> telah diberikan.
                </div>
            </li>
            <?php elseif ($item['tipe'] == 'daftar'): ?>
            <li class="flex items-start p-3 border-b border-gray-100 last:border-b-0">
                <span class="text-xl mr-4">✅</span>
                <div>
                    Anda berhasil mendaftar pada mata praktikum <a href="course_detail.php?id=<?php echo $item['data']['mata_praktikum_id']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo htmlspecialchars($item['data']['nama_praktikum']); ?></a>.
                </div>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="text-center py-8 text-gray-500">
        <p>Belum ada aktivitas terbaru untuk ditampilkan.</p>
    </div>
    <?php endif; ?>
</div>


<?php
// Panggil Footer
$conn->close();
require_once 'templates/footer_mahasiswa.php';
?>