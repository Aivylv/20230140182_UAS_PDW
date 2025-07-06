<?php
// Set variabel untuk halaman ini
$pageTitle = 'Detail Praktikum';
$activePage = 'my_courses'; // Tetap aktif di menu "Praktikum Saya"

// Sertakan header dan konfigurasi
require_once 'templates/header_mahasiswa.php';
require_once '../config.php';

// --- VALIDASI & PENGAMBILAN DATA AWAL ---

// Pastikan ID mata praktikum ada di URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect jika tidak ada ID
    header("Location: my_courses.php");
    exit();
}
$id_mata_praktikum = intval($_GET['id']);
$id_mahasiswa = $_SESSION['user_id'];
$pesan = ""; // Untuk notifikasi

// --- KEAMANAN: Cek apakah mahasiswa terdaftar di praktikum ini ---
$check_sql = "SELECT id FROM pendaftaran WHERE mahasiswa_id = ? AND mata_praktikum_id = ?";
if ($check_stmt = $conn->prepare($check_sql)) {
    $check_stmt->bind_param("ii", $id_mahasiswa, $id_mata_praktikum);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows == 0) {
        // Jika tidak terdaftar, redirect atau tampilkan pesan error
        echo "<div class='container mx-auto p-6'><div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg'>Anda tidak memiliki akses ke halaman ini.</div></div>";
        require_once 'templates/footer_mahasiswa.php';
        exit();
    }
    $check_stmt->close();
}

// --- LOGIKA UPLOAD LAPORAN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['file_laporan'])) {
    $id_modul = intval($_POST['modul_id']);
    
    // Direktori penyimpanan file laporan
    $target_dir = "../uploads/laporan/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["file_laporan"]["name"]);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $unique_file_name = "laporan_" . $id_modul . "_" . $id_mahasiswa . "_" . time() . "." . $file_ext;
    $target_file = $target_dir . $unique_file_name;

    // Validasi file (contoh: hanya PDF & DOCX, ukuran maks 5MB)
    $allowed_ext = ['pdf', 'doc', 'docx'];
    if (!in_array($file_ext, $allowed_ext)) {
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Error: Hanya file PDF, DOC, dan DOCX yang diizinkan.</div>";
    } elseif ($_FILES["file_laporan"]["size"] > 5000000) { // 5 MB
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Error: Ukuran file terlalu besar (Maks 5MB).</div>";
    } else {
        // Pindahkan file yang diunggah
        if (move_uploaded_file($_FILES["file_laporan"]["tmp_name"], $target_file)) {
            // Simpan informasi file ke database
            $insert_sql = "INSERT INTO laporan (mahasiswa_id, modul_id, file_laporan, status) VALUES (?, ?, ?, 'dikumpulkan')";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("iis", $id_mahasiswa, $id_modul, $unique_file_name);
                if ($insert_stmt->execute()) {
                    $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Laporan berhasil diunggah!</div>";
                } else {
                    $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Error: Gagal menyimpan data laporan ke database.</div>";
                }
                $insert_stmt->close();
            }
        } else {
            $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Error: Terjadi kesalahan saat mengunggah file.</div>";
        }
    }
}

// --- PENGAMBILAN DATA UNTUK TAMPILAN ---
// Ambil detail mata praktikum
$sql_course = "SELECT mp.nama_praktikum, mp.deskripsi, u.nama AS nama_asisten 
               FROM mata_praktikum mp 
               JOIN users u ON mp.asisten_id = u.id 
               WHERE mp.id = ?";
$stmt_course = $conn->prepare($sql_course);
$stmt_course->bind_param("i", $id_mata_praktikum);
$stmt_course->execute();
$course = $stmt_course->get_result()->fetch_assoc();
$stmt_course->close();

// Ambil semua modul dan status laporan mahasiswa untuk praktikum ini
$sql_modules = "SELECT 
                    m.id, 
                    m.judul, 
                    m.deskripsi, 
                    m.file_materi,
                    l.file_laporan,
                    l.status,
                    l.nilai,
                    l.feedback
                FROM modul m
                LEFT JOIN laporan l ON m.id = l.modul_id AND l.mahasiswa_id = ?
                WHERE m.mata_praktikum_id = ?
                ORDER BY m.urutan ASC, m.id ASC";
$stmt_modules = $conn->prepare($sql_modules);
$stmt_modules->bind_param("ii", $id_mahasiswa, $id_mata_praktikum);
$stmt_modules->execute();
$modules = $stmt_modules->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_modules->close();

$conn->close();
?>

<!-- Tombol Kembali dan Judul Halaman -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($course['nama_praktikum']); ?></h1>
        <p class="text-gray-600 mt-1">Asisten: <?php echo htmlspecialchars($course['nama_asisten']); ?></p>
    </div>
    <a href="my_courses.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">
        &larr; Kembali
    </a>
</div>

<!-- Deskripsi Praktikum -->
<div class="bg-white p-6 rounded-xl shadow-md mb-8">
    <h2 class="text-xl font-bold text-gray-800 mb-2">Deskripsi</h2>
    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($course['deskripsi'])); ?></p>
</div>

<!-- Tampilkan Pesan Notifikasi -->
<?php echo $pesan; ?>

<!-- Daftar Modul (Akordeon) -->
<div class="space-y-4">
    <h2 class="text-2xl font-bold text-gray-800">Daftar Modul & Tugas</h2>
    <?php if (count($modules) > 0): ?>
        <?php foreach ($modules as $index => $modul): ?>
            <div x-data="{ open: <?php echo $index === 0 ? 'true' : 'false'; ?> }" class="bg-white rounded-xl shadow-md overflow-hidden">
                <div @click="open = !open" class="p-5 cursor-pointer flex justify-between items-center border-b">
                    <h3 class="text-lg font-bold text-gray-900"><?php echo "Modul " . ($index + 1) . ": " . htmlspecialchars($modul['judul']); ?></h3>
                    <svg :class="{'rotate-180': open}" class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <div x-show="open" x-transition class="p-6 bg-gray-50/50">
                    <p class="text-gray-700 mb-6"><?php echo nl2br(htmlspecialchars($modul['deskripsi'])); ?></p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Kolom Materi & Nilai -->
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-bold text-gray-800 mb-2">Materi Praktikum</h4>
                                <?php if (!empty($modul['file_materi'])): ?>
                                    <a href="../uploads/materi/<?php echo htmlspecialchars($modul['file_materi']); ?>" download class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        Unduh Materi
                                    </a>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Materi belum tersedia.</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($modul['status'] === 'dinilai'): ?>
                            <div>
                                <h4 class="font-bold text-gray-800 mb-2">Penilaian</h4>
                                <div class="bg-blue-100 border-l-4 border-blue-500 p-4 rounded-md">
                                    <p class="text-gray-800"><strong>Nilai:</strong> <span class="text-2xl font-bold text-blue-600"><?php echo htmlspecialchars($modul['nilai']); ?></span></p>
                                    <?php if(!empty($modul['feedback'])): ?>
                                        <p class="text-gray-700 mt-2"><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($modul['feedback'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Kolom Laporan -->
                        <div>
                            <h4 class="font-bold text-gray-800 mb-2">Laporan Tugas</h4>
                            <?php if ($modul['file_laporan']): ?>
                                <!-- Jika laporan sudah diunggah -->
                                <div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 rounded-md">
                                    <p class="font-semibold">Laporan sudah diunggah.</p>
                                    <a href="../uploads/laporan/<?php echo htmlspecialchars($modul['file_laporan']); ?>" target="_blank" class="text-sm text-green-600 hover:underline">Lihat file</a>
                                    <p class="text-sm mt-1">Status: <span class="font-bold"><?php echo $modul['status'] === 'dinilai' ? 'Sudah Dinilai' : 'Menunggu Penilaian'; ?></span></p>
                                </div>
                            <?php else: ?>
                                <!-- Jika belum ada laporan, tampilkan form upload -->
                                <form action="course_detail.php?id=<?php echo $id_mata_praktikum; ?>" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="modul_id" value="<?php echo $modul['id']; ?>">
                                    <div>
                                        <input type="file" name="file_laporan" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                                        <p class="text-xs text-gray-500 mt-1">Tipe file: PDF, DOC, DOCX. Maks: 5MB.</p>
                                    </div>
                                    <button type="submit" class="mt-3 w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
                                        Kumpulkan Laporan
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-xl shadow-md">
            <h3 class="text-lg font-medium text-gray-900">Belum Ada Modul</h3>
            <p class="mt-1 text-sm text-gray-500">Asisten belum menambahkan modul untuk mata praktikum ini.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Script untuk Alpine.js (untuk akordeon) -->
<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>

<?php
// Sertakan footer
require_once 'templates/footer_mahasiswa.php';
?>