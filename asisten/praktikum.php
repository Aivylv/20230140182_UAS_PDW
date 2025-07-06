<?php
// 1. Definisi Variabel untuk Template
$pageTitle = 'Kelola Mata Praktikum';
$activePage = 'praktikum';

// 2. Panggil Header dan Konfigurasi
// (Asumsi header.php dan footer.php ada di dalam folder 'templates')
require_once 'templates/header.php'; 
require_once '../config.php';

// Inisialisasi session jika belum ada (header mungkin sudah melakukannya)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOGIKA CRUD ---
$pesan = "";
$edit_data = null; // Variabel untuk menyimpan data yang akan diedit

// CREATE & UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $nama_praktikum = trim($_POST['nama_praktikum']);
    $deskripsi = trim($_POST['deskripsi']);
    $asisten_id = intval($_POST['asisten_id']);
    $id_praktikum = isset($_POST['id_praktikum']) ? intval($_POST['id_praktikum']) : 0;

    if (empty($nama_praktikum) || empty($asisten_id)) {
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg' role='alert'>Nama praktikum dan asisten tidak boleh kosong.</div>";
    } else {
        if ($id_praktikum > 0) {
            // UPDATE
            $sql = "UPDATE mata_praktikum SET nama_praktikum = ?, deskripsi = ?, asisten_id = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssii", $nama_praktikum, $deskripsi, $asisten_id, $id_praktikum);
                $stmt->execute() 
                    ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg' role='alert'>Data berhasil diperbarui.</div>"
                    : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg' role='alert'>Gagal memperbarui data.</div>";
                $stmt->close();
            }
        } else {
            // CREATE
            $sql = "INSERT INTO mata_praktikum (nama_praktikum, deskripsi, asisten_id) VALUES (?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssi", $nama_praktikum, $deskripsi, $asisten_id);
                $stmt->execute()
                    ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg' role='alert'>Praktikum baru berhasil ditambahkan.</div>"
                    : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg' role='alert'>Gagal menambahkan data.</div>";
                $stmt->close();
            }
        }
    }
}

// DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $id_praktikum = intval($_POST['id_praktikum']);
    $sql = "DELETE FROM mata_praktikum WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_praktikum);
        $stmt->execute()
            ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg' role='alert'>Data berhasil dihapus.</div>"
            : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg' role='alert'>Gagal menghapus data.</div>";
        $stmt->close();
    }
}

// READ (untuk mengisi form edit)
if (isset($_GET['edit'])) {
    $id_praktikum = intval($_GET['edit']);
    $sql = "SELECT id, nama_praktikum, deskripsi, asisten_id FROM mata_praktikum WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_praktikum);
        $stmt->execute();
        $edit_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// --- PENGAMBILAN DATA UNTUK TAMPILAN ---
$sql_praktikum = "SELECT mp.id, mp.nama_praktikum, mp.deskripsi, u.nama as nama_asisten 
                  FROM mata_praktikum mp 
                  JOIN users u ON mp.asisten_id = u.id 
                  ORDER BY mp.nama_praktikum ASC";
$praktikum_list = $conn->query($sql_praktikum)->fetch_all(MYSQLI_ASSOC);

$sql_asisten = "SELECT id, nama FROM users WHERE role = 'asisten' ORDER BY nama ASC";
$asisten_list = $conn->query($sql_asisten)->fetch_all(MYSQLI_ASSOC);
?>

<!-- Konten halaman dimulai di sini -->
<!-- Header Halaman -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Mata Praktikum</h1>
        <p class="text-gray-500 mt-1">Kelola semua data master mata praktikum di sini.</p>
    </div>
    <button onclick="openModal('add')" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-transform transform hover:scale-105">
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Tambah Praktikum
    </button>
</div>

<?php echo $pesan; ?>

<!-- Tabel Data Praktikum dengan Gaya Dashboard -->
<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 rounded-l-lg">Nama Praktikum</th>
                <th scope="col" class="px-6 py-3">Deskripsi</th>
                <th scope="col" class="px-6 py-3">Asisten Penanggung Jawab</th>
                <th scope="col" class="px-6 py-3 rounded-r-lg text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($praktikum_list) > 0): ?>
                <?php foreach ($praktikum_list as $praktikum): ?>
                    <tr class="bg-white border-b hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($praktikum['nama_praktikum']); ?></td>
                        <td class="px-6 py-4 max-w-xs truncate" title="<?php echo htmlspecialchars($praktikum['deskripsi']); ?>"><?php echo htmlspecialchars($praktikum['deskripsi']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($praktikum['nama_asisten']); ?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-4">
                                <a href="praktikum.php?edit=<?php echo $praktikum['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                <form action="praktikum.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="inline">
                                    <input type="hidden" name="id_praktikum" value="<?php echo $praktikum['id']; ?>">
                                    <button type="submit" name="delete" class="font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="bg-white border-b">
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
                        <p class="mt-2 font-semibold">Belum ada data</p>
                        <p class="text-sm">Silakan tambahkan mata praktikum baru.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- Konten halaman berakhir di sini -->


<!-- Modal untuk Tambah/Edit Data -->
<div id="formModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center <?php echo $edit_data ? '' : 'hidden'; ?> z-50">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold text-gray-800"><?php echo $edit_data ? 'Edit' : 'Tambah'; ?> Mata Praktikum</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-full transition-colors">&times;</button>
        </div>
        <form action="praktikum.php" method="post">
            <input type="hidden" name="id_praktikum" id="id_praktikum" value="<?php echo $edit_data['id'] ?? '0'; ?>">
            <div class="mb-4">
                <label for="nama_praktikum" class="block text-sm font-medium text-gray-700 mb-2">Nama Praktikum</label>
                <input type="text" name="nama_praktikum" id="nama_praktikum" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($edit_data['nama_praktikum'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                <textarea name="deskripsi" id="deskripsi" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($edit_data['deskripsi'] ?? ''); ?></textarea>
            </div>
            <div class="mb-6">
                <label for="asisten_id" class="block text-sm font-medium text-gray-700 mb-2">Asisten Penanggung Jawab</label>
                <select name="asisten_id" id="asisten_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Pilih Asisten</option>
                    <?php foreach ($asisten_list as $asisten): ?>
                        <option value="<?php echo $asisten['id']; ?>" <?php echo (isset($edit_data['asisten_id']) && $edit_data['asisten_id'] == $asisten['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($asisten['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="closeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Batal</button>
                <button type="submit" name="submit" id="submitButton" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors"><?php echo $edit_data ? 'Simpan Perubahan' : 'Tambah'; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('formModal');
    const modalTitle = document.getElementById('modalTitle');
    const submitButton = document.getElementById('submitButton');
    const form = modal.querySelector('form');

    function openModal(mode) {
        if (mode === 'add') {
            form.reset();
            document.getElementById('id_praktikum').value = '0';
            modalTitle.textContent = 'Tambah Mata Praktikum';
            submitButton.textContent = 'Tambah';
        }
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({path: url.href}, '', url.href);
        }
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