<?php
// Letakkan semua logika PHP di bagian paling atas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';

// --- LOGIKA CRUD ---
$pesan = "";
$edit_data = null;

// CREATE & UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_akun'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = trim($_POST['password']);
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    // Validasi dasar
    if (empty($nama) || empty($email) || empty($role)) {
        $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Nama, email, dan peran tidak boleh kosong.</div>";
    } else {
        if ($user_id > 0) {
            // --- UPDATE ---
            // Cek duplikasi email (jika email diubah)
            $sql_check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt_check = $conn->prepare($sql_check_email);
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Email ini sudah digunakan oleh akun lain.</div>";
            } else {
                if (!empty($password)) {
                    // Jika password diisi, update password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET nama = ?, email = ?, role = ?, password = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $nama, $email, $role, $hashed_password, $user_id);
                } else {
                    // Jika password kosong, jangan update password
                    $sql = "UPDATE users SET nama = ?, email = ?, role = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $nama, $email, $role, $user_id);
                }
                
                $stmt->execute() ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Akun berhasil diperbarui.</div>" : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal memperbarui akun.</div>";
                $stmt->close();
            }
            $stmt_check->close();

        } else {
            // --- CREATE ---
            if (empty($password)) {
                 $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Password tidak boleh kosong untuk akun baru.</div>";
            } else {
                // Cek duplikasi email
                $sql_check_email = "SELECT id FROM users WHERE email = ?";
                $stmt_check = $conn->prepare($sql_check_email);
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Email ini sudah terdaftar.</div>";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (nama, email, role, password) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $nama, $email, $role, $hashed_password);
                    
                    $stmt->execute() ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Akun baru berhasil ditambahkan.</div>" : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal menambahkan akun.</div>";
                    $stmt->close();
                }
                $stmt_check->close();
            }
        }
    }
}

// DELETE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_akun'])) {
    $user_id_to_delete = intval($_POST['user_id']);

    // Mencegah admin menghapus akunnya sendiri
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $pesan = "<div class='bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg'>Anda tidak dapat menghapus akun Anda sendiri.</div>";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id_to_delete);
            $stmt->execute() ? $pesan = "<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg'>Akun berhasil dihapus.</div>" : $pesan = "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg'>Gagal menghapus akun.</div>";
            $stmt->close();
        }
    }
}

// READ (untuk mengisi form edit)
if (isset($_GET['edit'])) {
    $user_id_to_edit = intval($_GET['edit']);
    $sql = "SELECT id, nama, email, role FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $edit_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}


// 1. Definisi Variabel untuk Template
$pageTitle = 'Kelola Akun';
$activePage = 'akun';

// 2. Panggil Header
require_once 'templates/header.php';

// --- PENGAMBILAN DATA UNTUK TAMPILAN ---
$sql_users = "SELECT id, nama, email, role, created_at FROM users ORDER BY nama ASC";
$user_list = $conn->query($sql_users)->fetch_all(MYSQLI_ASSOC);
?>

<!-- Header Halaman -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Kelola Akun Pengguna</h1>
        <p class="text-gray-500 mt-1">Tambah, ubah, atau hapus data pengguna sistem.</p>
    </div>
    <button onclick="openModal('add')" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-transform transform hover:scale-105">
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Tambah Akun
    </button>
</div>

<?php echo $pesan; ?>

<!-- Tabel Akun Pengguna -->
<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 rounded-l-lg">Nama</th>
                <th scope="col" class="px-6 py-3">Email</th>
                <th scope="col" class="px-6 py-3 text-center">Peran</th>
                <th scope="col" class="px-6 py-3 rounded-r-lg text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($user_list) > 0): ?>
                <?php foreach ($user_list as $user): ?>
                    <tr class="bg-white border-b hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($user['nama']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($user['role'] == 'asisten'): ?>
                                <span class="px-3 py-1 text-xs font-medium text-purple-800 bg-purple-100 rounded-full">Asisten</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">Mahasiswa</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-4">
                                <a href="akun.php?edit=<?php echo $user['id']; ?>" class="font-medium text-blue-600 hover:underline">Edit</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): // Tombol hapus tidak muncul untuk akun sendiri ?>
                                <form action="akun.php" method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');" class="inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_akun" class="font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="bg-white border-b">
                    <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                        <p class="font-semibold">Belum ada data akun.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal untuk Tambah/Edit Akun -->
<div id="formModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center <?php echo $edit_data ? '' : 'hidden'; ?> z-50">
    <div class="relative mx-auto p-8 border w-full max-w-lg shadow-lg rounded-xl bg-white">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold text-gray-800"><?php echo $edit_data ? 'Edit' : 'Tambah'; ?> Akun Pengguna</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-2 rounded-full transition-colors">&times;</button>
        </div>
        <form action="akun.php" method="post">
            <input type="hidden" name="user_id" id="user_id" value="<?php echo $edit_data['id'] ?? '0'; ?>">
            <div class="mb-4">
                <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                <input type="text" name="nama" id="nama" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email" id="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" id="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?php echo $edit_data ? 'Kosongkan jika tidak diubah' : 'Wajib diisi'; ?>">
            </div>
            <div class="mb-6">
                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Peran</label>
                <select name="role" id="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <option value="">Pilih Peran</option>
                    <option value="mahasiswa" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                    <option value="asisten" <?php echo (isset($edit_data['role']) && $edit_data['role'] == 'asisten') ? 'selected' : ''; ?>>Asisten</option>
                </select>
            </div>
            <div class="flex justify-end gap-4">
                <button type="button" onclick="closeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">Batal</button>
                <button type="submit" name="submit_akun" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors"><?php echo $edit_data ? 'Simpan Perubahan' : 'Tambah Akun'; ?></button>
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
            document.getElementById('user_id').value = '0';
            modalTitle.textContent = 'Tambah Akun Pengguna';
            form.querySelector('button[type=submit]').textContent = 'Tambah Akun';
            document.getElementById('password').placeholder = 'Wajib diisi';
        } else {
            modalTitle.textContent = 'Edit Akun Pengguna';
            form.querySelector('button[type=submit]').textContent = 'Simpan Perubahan';
            document.getElementById('password').placeholder = 'Kosongkan jika tidak diubah';
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