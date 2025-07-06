<?php
// Set variabel untuk halaman ini
$pageTitle = 'Cari Praktikum';
$activePage = 'courses'; // Digunakan untuk menandai menu aktif di header

// Sertakan header
require_once 'templates/header_mahasiswa.php';

// Sertakan file konfigurasi database
require_once '../config.php';

// Ambil ID mahasiswa dari session
$id_mahasiswa = $_SESSION['user_id'];
$pesan = ""; // Variabel untuk pesan notifikasi

// --- LOGIKA PENDAFTARAN ---
// Proses ketika mahasiswa menekan tombol "Daftar"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_course_id'])) {
    $id_praktikum_to_register = $_POST['register_course_id'];

    // Cek dulu apakah sudah terdaftar untuk mencegah pendaftaran ganda
    $check_sql = "SELECT id FROM pendaftaran WHERE mahasiswa_id = ? AND mata_praktikum_id = ?";
    if ($check_stmt = $conn->prepare($check_sql)) {
        $check_stmt->bind_param("ii", $id_mahasiswa, $id_praktikum_to_register);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows == 0) {
            // Jika belum terdaftar, lakukan pendaftaran
            $insert_sql = "INSERT INTO pendaftaran (mahasiswa_id, mata_praktikum_id) VALUES (?, ?)";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("ii", $id_mahasiswa, $id_praktikum_to_register);
                if ($insert_stmt->execute()) {
                    $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg' role='alert'>Pendaftaran berhasil!</div>";
                } else {
                    $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg' role='alert'>Terjadi kesalahan saat mendaftar.</div>";
                }
                $insert_stmt->close();
            }
        } else {
            // Seharusnya tidak terjadi jika tombol dinonaktifkan, tapi sebagai pengaman
            $pesan = "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg' role='alert'>Anda sudah terdaftar pada praktikum ini.</div>";
        }
        $check_stmt->close();
    }
}


// --- LOGIKA PENCARIAN & PENGAMBILAN DATA ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%" . $search_term . "%";

// Siapkan query untuk mengambil semua mata praktikum
// Query ini juga memeriksa apakah mahasiswa yang login sudah terdaftar atau belum
$sql = "SELECT 
            mp.id, 
            mp.nama_praktikum, 
            mp.deskripsi, 
            u.nama AS nama_asisten,
            p.id AS pendaftaran_id -- Jika NULL berarti belum daftar, jika ada isinya berarti sudah
        FROM mata_praktikum mp
        JOIN users u ON mp.asisten_id = u.id
        LEFT JOIN pendaftaran p ON mp.id = p.mata_praktikum_id AND p.mahasiswa_id = ?
        WHERE mp.nama_praktikum LIKE ?
        ORDER BY mp.nama_praktikum ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_mahasiswa, $search_param);
$stmt->execute();
$result = $stmt->get_result();
$praktikum_list = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Judul Halaman dan Form Pencarian -->
<div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Katalog Mata Praktikum</h1>
        <p class="text-gray-600 mt-1">Cari dan daftar untuk mata praktikum yang tersedia.</p>
    </div>
    <form action="courses.php" method="get" class="w-full md:w-auto">
        <div class="relative">
            <input type="text" name="search" placeholder="Cari nama praktikum..." class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="absolute right-0 top-0 mt-2 mr-3">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>
        </div>
    </form>
</div>

<!-- Tampilkan Pesan Notifikasi -->
<?php echo $pesan; ?>

<!-- Konten Halaman -->
<div class="bg-white p-6 rounded-xl shadow-md">
    <?php if (count($praktikum_list) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($praktikum_list as $praktikum): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 flex flex-col justify-between transition-shadow hover:shadow-lg">
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
                        <?php if ($praktikum['pendaftaran_id'] !== null): ?>
                            <!-- Jika sudah terdaftar -->
                            <span class="w-full text-center bg-green-500 text-white font-semibold py-2 px-4 rounded-lg inline-block">
                                Sudah Terdaftar
                            </span>
                        <?php else: ?>
                            <!-- Jika belum terdaftar, tampilkan tombol daftar -->
                            <form action="courses.php" method="post">
                                <input type="hidden" name="register_course_id" value="<?php echo $praktikum['id']; ?>">
                                <button type="submit" class="w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors duration-300">
                                    Daftar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Tampilkan jika tidak ada praktikum yang ditemukan -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Tidak Ada Praktikum Ditemukan</h3>
            <p class="mt-1 text-sm text-gray-500">Tidak ada mata praktikum yang cocok dengan pencarian "<?php echo htmlspecialchars($search_term); ?>".</p>
        </div>
    <?php endif; ?>
</div>

<?php
// Sertakan footer
require_once 'templates/footer_mahasiswa.php';
?>
