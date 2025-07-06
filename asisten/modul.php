<?php
// Letakkan semua logika PHP yang mungkin melakukan redirect di bagian paling atas.
// Inisialisasi session harus menjadi yang pertama.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Panggil file konfigurasi untuk koneksi database
require_once '../config.php';

// Ambil course_id dari URL. Jika tidak ada, nilainya 0.
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// JIKA course_id TIDAK ADA, TAMPILKAN HALAMAN PEMILIHAN MATA PRAKTIKUM
if ($course_id === 0) {
    $pageTitle = 'Pilih Praktikum';
    $activePage = 'modul';
    require_once 'templates/header.php';

    // Ambil semua mata praktikum yang diampu oleh asisten yang sedang login
    $sql_courses = "SELECT id, nama_praktikum, deskripsi FROM mata_praktikum WHERE asisten_id = ? ORDER BY nama_praktikum ASC";
    $stmt_courses = $conn->prepare($sql_courses);
    $stmt_courses->bind_param("i", $_SESSION['user_id']);
    $stmt_courses->execute();
    $courses_result = $stmt_courses->get_result();
    ?>
    
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-2">Pilih Mata Praktikum</h2>
        <p class="text-gray-600">Pilih mata praktikum yang modulnya ingin Anda kelola.</p>
    </div>
    
    <?php if ($courses_result->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($course = $courses_result->fetch_assoc()): ?>
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($course['nama_praktikum']); ?></h3>
                        <p class="text-gray-600 mb-4 text-sm">
                            <?php echo htmlspecialchars(substr($course['deskripsi'], 0, 100)); ?><?php if (strlen($course['deskripsi']) > 100) echo '...'; ?>
                        </p>
                    </div>
                    <a href="modul.php?course_id=<?php echo $course['id']; ?>" class="w-full text-center bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors font-semibold">
                        Kelola Modul
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-16 text-gray-500 bg-white rounded-lg shadow-md">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Belum Ada Mata Praktikum</h3>
            <p class="mb-4">Anda belum ditugaskan untuk mengampu mata praktikum apapun.</p>
            <a href="praktikum.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                Kelola Mata Praktikum
            </a>
        </div>
    <?php endif; ?>
    
    <?php
    require_once 'templates/footer.php';
    exit; // Hentikan eksekusi setelah menampilkan halaman pemilihan
}

// JIKA course_id ADA, LANJUTKAN KE PENGELOLAAN MODUL
// Security Check: Pastikan asisten ini adalah pemilik mata praktikum
$check_owner_sql = "SELECT nama_praktikum FROM mata_praktikum WHERE id = ? AND asisten_id = ?";
$check_stmt = $conn->prepare($check_owner_sql);
$check_stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$check_stmt->execute();
$course_data = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if (!$course_data) {
    // Jika bukan pemilik, redirect atau tampilkan error
    header("Location: praktikum.php");
    exit();
}

// 1. Definisi Variabel untuk Template
$pageTitle = 'Kelola Modul';
$activePage = 'kelola_modul';

// 2. Panggil Header
require_once 'templates/header.php'; 

// --- LOGIKA CRUD ---
$pesan = "";
$edit_data = null;
$target_dir = "../uploads/materi/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// CREATE & UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $urutan = intval($_POST['urutan']);
    $id_modul = isset($_POST['id_modul']) ? intval($_POST['id_modul']) : 0;
    $file_materi_lama = $_POST['file_materi_lama'] ?? '';
    $file_materi_baru = $file_materi_lama;

    // Logika upload file
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $file_name = basename($_FILES["file_materi"]["name"]);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_file_name = "materi_" . $course_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $unique_file_name;
        $allowed_ext = ['pdf', 'doc', 'docx'];

        if (in_array($file_ext, $allowed_ext) && $_FILES["file_materi"]["size"] <= 5000000) {
            if (move_uploaded_file($_FILES["file_materi"]["tmp_name"], $target_file)) {
                if (!empty($file_materi_lama) && file_exists($target_dir . $file_materi_lama)) {
                    unlink($target_dir . $file_materi_lama);
                }
                $file_materi_baru = $unique_file_name;
            } else {
                $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal mengunggah file.</div>";
            }
        } else {
            $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>File tidak valid (Hanya PDF/DOC/DOCX, maks 5MB).</div>";
        }
    }

    if (empty($judul)) {
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Judul modul tidak boleh kosong.</div>";
    } elseif (empty($pesan)) {
        if ($id_modul > 0) {
            // UPDATE
            $sql = "UPDATE modul SET judul = ?, deskripsi = ?, urutan = ?, file_materi = ? WHERE id = ? AND mata_praktikum_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssisii", $judul, $deskripsi, $urutan, $file_materi_baru, $id_modul, $course_id);
                $stmt->execute() ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Modul berhasil diperbarui.</div>" : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal memperbarui modul.</div>";
                $stmt->close();
            }
        } else {
            // CREATE
            $sql = "INSERT INTO modul (mata_praktikum_id, judul, deskripsi, urutan, file_materi) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("issis", $course_id, $judul, $deskripsi, $urutan, $file_materi_baru);
                $stmt->execute() ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Modul baru berhasil ditambahkan.</div>" : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal menambahkan modul.</div>";
                $stmt->close();
            }
        }
    }
}

// DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $id_modul = intval($_POST['id_modul']);
    $file_materi_hapus = $_POST['file_materi_hapus'];
    $sql = "DELETE FROM modul WHERE id = ? AND mata_praktikum_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $id_modul, $course_id);
        if ($stmt->execute()) {
            if (!empty($file_materi_hapus) && file_exists($target_dir . $file_materi_hapus)) {
                unlink($target_dir . $file_materi_hapus);
            }
            $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Modul berhasil dihapus.</div>";
        } else {
            $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal menghapus modul.</div>";
        }
        $stmt->close();
    }
}

// READ (untuk mengisi form edit)
if (isset($_GET['edit'])) {
    $id_modul = intval($_GET['edit']);
    $sql = "SELECT id, judul, deskripsi, urutan, file_materi FROM modul WHERE id = ? AND mata_praktikum_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $id_modul, $course_id);
        $stmt->execute();
        $edit_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Ambil daftar modul untuk ditampilkan (termasuk deskripsi)
$sql_modul = "SELECT id, judul, deskripsi, urutan, file_materi FROM modul WHERE mata_praktikum_id = ? ORDER BY urutan ASC";
$stmt_modul = $conn->prepare($sql_modul);
$stmt_modul->bind_param("i", $course_id);
$stmt_modul->execute();
$modul_list = $stmt_modul->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_modul->close();
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <a href="modul.php" class="text-sm text-blue-600 hover:underline mb-2 inline-block">&larr; Kembali ke Pemilihan Praktikum</a>
        <h1 class="text-3xl font-bold text-gray-800">Kelola Modul: <?php echo htmlspecialchars($course_data['nama_praktikum']); ?></h1>
        <p class="text-gray-500 mt-1">Tambah, ubah, atau hapus modul untuk mata praktikum ini.</p>
    </div>
    <button onclick="openModal('add')" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-transform transform hover:scale-105">
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Tambah Modul
    </button>
</div>

<?php echo $pesan; ?>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 rounded-l-lg">Urutan</th>
                <th scope="col" class="px-6 py-3">Judul Modul</th>
                <th scope="col" class="px-6 py-3">Deskripsi</th>
                <th scope="col" class="px-6 py-3">File Materi</th>
                <th scope="col" class="px-6 py-3 rounded-r-lg text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($modul_list) > 0): ?>
                <?php foreach ($modul_list as $modul): ?>
                    <tr class="bg-white border-b hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-900 text-center"><?php echo htmlspecialchars($modul['urutan']); ?></td>
                        <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($modul['judul']); ?></td>
                        <td class="px-6 py-4 max-w-sm truncate" title="<?php echo htmlspecialchars($modul['deskripsi']); ?>">
                            <?php echo htmlspecialchars($modul['deskripsi']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if (!empty($modul['file_materi'])): ?>
                                <a href="../uploads/materi/<?php echo htmlspecialchars($modul['file_materi']); ?>" target="_blank" class="text-blue-600 hover:underline">Lihat File</a>
                            <?php else: ?>
                                <span class="text-gray-400">Tidak ada</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-4">
                                <a href="modul.php?course_id=<?php echo $course_id; ?>&edit=<?php echo $modul['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                <form action="modul.php?course_id=<?php echo $course_id; ?>" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus modul ini?');" class="inline">
                                    <input type="hidden" name="id_modul" value="<?php echo $modul['id']; ?>">
                                    <input type="hidden" name="file_materi_hapus" value="<?php echo htmlspecialchars($modul['file_materi']); ?>">
                                    <button type="submit" name="delete" class="font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="bg-white border-b">
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        <p class="mt-2 font-semibold">Belum ada modul</p>
                        <p class="text-sm">Silakan tambahkan modul baru untuk praktikum ini.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="formModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center <?php echo $edit_data ? '' : 'hidden'; ?> z-50">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold text-gray-800"><?php echo $edit_data ? 'Edit' : 'Tambah'; ?> Modul</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-full transition-colors">&times;</button>
        </div>
        <form action="modul.php?course_id=<?php echo $course_id; ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="id_modul" id="id_modul" value="<?php echo $edit_data['id'] ?? '0'; ?>">
            <input type="hidden" name="file_materi_lama" value="<?php echo htmlspecialchars($edit_data['file_materi'] ?? ''); ?>">
            <div class="mb-4">
                <label for="judul" class="block text-sm font-medium text-gray-700 mb-2">Judul Modul</label>
                <input type="text" name="judul" id="judul" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($edit_data['judul'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label for="urutan" class="block text-sm font-medium text-gray-700 mb-2">Urutan</label>
                <input type="number" name="urutan" id="urutan" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($edit_data['urutan'] ?? '1'); ?>" required min="1">
            </div>
            <div class="mb-4">
                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($edit_data['deskripsi'] ?? ''); ?></textarea>
            </div>
            <div class="mb-6">
                <label for="file_materi" class="block text-sm font-medium text-gray-700 mb-2">File Materi</label>
                <input type="file" name="file_materi" id="file_materi" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
                <p class="text-xs text-gray-500 mt-1">
                    <?php 
                    if (!empty($edit_data['file_materi'])) {
                        echo "File saat ini: " . htmlspecialchars($edit_data['file_materi']) . ". Biarkan kosong jika tidak ingin mengubah.";
                    } else {
                        echo "Tipe file: PDF, DOC, DOCX. Maks: 5MB.";
                    }
                    ?>
                </p>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="closeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Batal</button>
                <button type="submit" name="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors"><?php echo $edit_data ? 'Simpan Perubahan' : 'Tambah'; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('formModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = modal.querySelector('form');

    function openModal(mode) {
        if (mode === 'add') {
            form.reset();
            document.getElementById('id_modul').value = '0';
            modalTitle.textContent = 'Tambah Modul';
            form.querySelector('button[type=submit]').textContent = 'Tambah';
        }
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        const courseId = '<?php echo $course_id; ?>';
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        // Pastikan course_id tetap ada di URL
        url.searchParams.set('course_id', courseId);
        window.history.replaceState({path: url.href}, '', url.href);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit')) {
            openModal('edit');
        }
    });
</script>

<?php
// 3. Panggil Footer
$conn->close();
require_once 'templates/footer.php';
?>