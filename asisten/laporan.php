<?php
// Letakkan semua logika PHP di bagian paling atas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// --- LOGIKA PENILAIAN ---
$pesan = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai'])) {
    $laporan_id = intval($_POST['laporan_id']);
    $nilai = trim($_POST['nilai']);
    $feedback = trim($_POST['feedback']);
    $asisten_id = $_SESSION['user_id']; // Asisten yang menilai

    // Validasi sederhana
    if (!is_numeric($nilai) || $nilai < 0 || $nilai > 100) {
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Nilai tidak valid. Harap masukkan angka antara 0 dan 100.</div>";
    } else {
        // Query UPDATE untuk memasukkan nilai, feedback, dan mengubah status
        $sql_update = "UPDATE laporan SET nilai = ?, feedback = ?, status = 'dinilai' WHERE id = ?";
        // Security check tambahan bisa ditambahkan di sini untuk memastikan asisten hanya menilai laporan dari praktikum yang diampunya
        
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("dsi", $nilai, $feedback, $laporan_id);
            if ($stmt_update->execute()) {
                $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Laporan berhasil dinilai.</div>";
            } else {
                $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal memperbarui data laporan.</div>";
            }
            $stmt_update->close();
        }
    }
}

// 1. Definisi Variabel untuk Template
$pageTitle = 'Laporan Masuk';
$activePage = 'laporan_masuk';

// 2. Panggil Header
require_once 'templates/header.php';

// --- LOGIKA FILTER & PENGAMBILAN DATA ---
$asisten_id = $_SESSION['user_id'];

// Ambil nilai filter dari URL
$filter_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Query dasar untuk mengambil laporan
$sql_laporan = "SELECT 
                    l.id,
                    l.file_laporan,
                    l.tanggal_upload,
                    l.status,
                    l.nilai,
                    l.feedback,
                    u.nama AS nama_mahasiswa,
                    m.judul AS nama_modul,
                    mp.nama_praktikum
                FROM laporan l
                JOIN users u ON l.mahasiswa_id = u.id
                JOIN modul m ON l.modul_id = m.id
                JOIN mata_praktikum mp ON m.mata_praktikum_id = mp.id
                WHERE mp.asisten_id = ?";

// Tambahkan kondisi filter ke query
$params = [$asisten_id];
$types = "i";

if ($filter_course_id > 0) {
    $sql_laporan .= " AND mp.id = ?";
    $params[] = $filter_course_id;
    $types .= "i";
}
if (!empty($filter_status)) {
    $sql_laporan .= " AND l.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql_laporan .= " ORDER BY l.tanggal_upload DESC";

$stmt_laporan = $conn->prepare($sql_laporan);
$stmt_laporan->bind_param($types, ...$params);
$stmt_laporan->execute();
$laporan_list = $stmt_laporan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_laporan->close();

// Ambil daftar mata praktikum untuk filter dropdown
$sql_courses = "SELECT id, nama_praktikum FROM mata_praktikum WHERE asisten_id = ? ORDER BY nama_praktikum ASC";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->bind_param("i", $asisten_id);
$stmt_courses->execute();
$courses_result = $stmt_courses->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_courses->close();
?>

<!-- Header Halaman -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Laporan Masuk</h1>
        <p class="text-gray-500 mt-1">Lihat, unduh, dan nilai laporan yang dikumpulkan mahasiswa.</p>
    </div>
</div>

<?php echo $pesan; ?>

<!-- Form Filter -->
<div class="bg-white p-6 rounded-lg shadow-md mb-8">
    <form action="laporan.php" method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Mata Praktikum</label>
            <select name="course_id" id="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">Semua Praktikum</option>
                <?php foreach ($courses_result as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo ($filter_course_id == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['nama_praktikum']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Status</option>
                <option value="dikumpulkan" <?php echo ($filter_status == 'dikumpulkan') ? 'selected' : ''; ?>>Menunggu Penilaian</option>
                <option value="dinilai" <?php echo ($filter_status == 'dinilai') ? 'selected' : ''; ?>>Sudah Dinilai</option>
            </select>
        </div>
        <div class="md:col-span-2 flex gap-4">
            <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">Filter</button>
            <a href="laporan.php" class="w-full md:w-auto text-center bg-gray-200 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-300 transition-colors">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel Laporan -->
<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 rounded-l-lg">Mahasiswa</th>
                <th scope="col" class="px-6 py-3">Praktikum & Modul</th>
                <th scope="col" class="px-6 py-3">Tanggal Kumpul</th>
                <th scope="col" class="px-6 py-3 text-center">Status</th>
                <th scope="col" class="px-6 py-3 text-center">Nilai</th>
                <th scope="col" class="px-6 py-3 rounded-r-lg text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($laporan_list) > 0): ?>
                <?php foreach ($laporan_list as $laporan): ?>
                    <tr class="bg-white border-b hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($laporan['nama_mahasiswa']); ?></td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-800"><?php echo htmlspecialchars($laporan['nama_praktikum']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($laporan['nama_modul']); ?></div>
                        </td>
                        <td class="px-6 py-4"><?php echo date('d M Y, H:i', strtotime($laporan['tanggal_upload'])); ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($laporan['status'] == 'dinilai'): ?>
                                <span class="px-3 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">Sudah Dinilai</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 rounded-full">Menunggu</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-gray-800">
                            <?php echo ($laporan['status'] == 'dinilai' && isset($laporan['nilai'])) ? number_format($laporan['nilai'], 2) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="openGradeModal(<?php echo $laporan['id']; ?>, '<?php echo htmlspecialchars(addslashes($laporan['nama_mahasiswa'])); ?>', '<?php echo htmlspecialchars(addslashes($laporan['nama_modul'])); ?>', '<?php echo htmlspecialchars($laporan['file_laporan']); ?>', '<?php echo $laporan['nilai'] ?? ''; ?>', '<?php echo htmlspecialchars(addslashes($laporan['feedback'] ?? '')); ?>')" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors text-xs font-semibold">
                                <?php echo $laporan['status'] == 'dinilai' ? 'Lihat/Edit Nilai' : 'Beri Nilai'; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="bg-white border-b">
                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                        <p class="mt-2 font-semibold">Tidak ada laporan ditemukan</p>
                        <p class="text-sm">Coba ubah filter atau tunggu mahasiswa mengumpulkan laporan.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal untuk Memberi Nilai -->
<div id="gradeModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden z-50">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Beri Nilai Laporan</h3>
            <button onclick="closeGradeModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-full transition-colors">&times;</button>
        </div>
        <div class="mb-4">
            <p><span class="font-semibold">Mahasiswa:</span> <span id="modalMahasiswa"></span></p>
            <p><span class="font-semibold">Modul:</span> <span id="modalModul"></span></p>
            <a id="modalDownloadLink" href="#" target="_blank" class="inline-block mt-2 bg-blue-100 text-blue-700 font-bold py-2 px-4 rounded-lg hover:bg-blue-200">
                Unduh File Laporan
            </a>
        </div>
        <form action="laporan.php" method="post">
            <input type="hidden" name="laporan_id" id="modalLaporanId">
            <div class="mb-4">
                <label for="nilai" class="block text-sm font-medium text-gray-700 mb-2">Nilai (0-100)</label>
                <input type="number" step="0.01" name="nilai" id="modalNilai" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="mb-6">
                <label for="feedback" class="block text-sm font-medium text-gray-700 mb-2">Feedback (Opsional)</label>
                <textarea name="feedback" id="modalFeedback" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="closeGradeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Batal</button>
                <button type="submit" name="submit_nilai" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">Simpan Nilai</button>
            </div>
        </form>
    </div>
</div>

<script>
    const gradeModal = document.getElementById('gradeModal');

    function openGradeModal(laporanId, namaMahasiswa, namaModul, fileLaporan, nilai, feedback) {
        document.getElementById('modalLaporanId').value = laporanId;
        document.getElementById('modalMahasiswa').textContent = namaMahasiswa;
        document.getElementById('modalModul').textContent = namaModul;
        document.getElementById('modalDownloadLink').href = `../uploads/laporan/${fileLaporan}`;
        document.getElementById('modalNilai').value = nilai;
        document.getElementById('modalFeedback').value = feedback;
        gradeModal.classList.remove('hidden');
    }

    function closeGradeModal() {
        gradeModal.classList.add('hidden');
    }
</script>

<?php
// 3. Panggil Footer
$conn->close();
require_once 'templates/footer.php';
?>