<?php
// Set variabel untuk halaman ini
$pageTitle = 'Praktikum Saya';
$activePage = 'my_courses'; // Digunakan untuk menandai menu aktif di header

// Sertakan header
require_once 'templates/header_mahasiswa.php';

// Sertakan file konfigurasi database
require_once '../config.php';

// Ambil ID mahasiswa dari session
$id_mahasiswa = $_SESSION['user_id'];

// Siapkan query untuk mengambil data praktikum yang diikuti oleh mahasiswa
// Query ini disesuaikan dengan skema database yang baru
$sql = "SELECT 
            mp.id, 
            mp.nama_praktikum, 
            mp.deskripsi, 
            u.nama AS nama_asisten
        FROM pendaftaran p
        JOIN mata_praktikum mp ON p.mata_praktikum_id = mp.id
        JOIN users u ON mp.asisten_id = u.id
        WHERE p.mahasiswa_id = ?
        ORDER BY mp.nama_praktikum ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_mahasiswa);
$stmt->execute();
$result = $stmt->get_result();
$praktikum_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Judul Halaman -->
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Praktikum yang Saya Ikuti</h1>
    <p class="text-gray-600 mt-1">Berikut adalah daftar semua mata praktikum yang telah Anda daftarkan.</p>
</div>

<!-- Konten Halaman -->
<div class="bg-white p-6 rounded-xl shadow-md">
    <?php if (count($praktikum_list) > 0): ?>
        <!-- Tampilkan jika ada praktikum yang diikuti -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($praktikum_list as $praktikum): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 flex flex-col justify-between transition-transform transform hover:scale-105 hover:shadow-lg">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></h3>
                        <p class="text-sm text-gray-500 mt-1 mb-3">
                            Asisten: <?php echo htmlspecialchars($praktikum['nama_asisten'] ?? 'N/A'); ?>
                        </p>
                        <p class="text-gray-700 text-sm line-clamp-3">
                            <?php echo htmlspecialchars($praktikum['deskripsi']); ?>
                        </p>
                    </div>
                    <div class="mt-4">
                        <a href="course_detail.php?id=<?php echo $praktikum['id']; ?>" class="w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors duration-300 inline-block">
                            Lihat Detail & Tugas
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Tampilkan jika belum ada praktikum yang diikuti -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Anda Belum Terdaftar</h3>
            <p class="mt-1 text-sm text-gray-500">Anda belum terdaftar di mata praktikum manapun. Silakan cari dan daftar praktikum terlebih dahulu.</p>
            <div class="mt-6">
                <a href="courses.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cari Praktikum Sekarang
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Sertakan footer
require_once 'templates/footer_mahasiswa.php';
?>
