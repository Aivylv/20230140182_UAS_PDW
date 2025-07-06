<?php
session_start();

// Jika sudah login, arahkan langsung ke dashboard
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'mahasiswa') {
        header("Location: mahasiswa/dashboard.php");
    } elseif ($_SESSION['user']['role'] === 'asisten') {
        header("Location: asisten/dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SIMPRAK - Sistem Informasi Praktikum</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md text-center">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Selamat Datang di SIMPRAK</h1>
        <p class="text-gray-600 mb-6">Sistem Informasi Manajemen Praktikum</p>
        <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded">
            Masuk ke Sistem
        </a>
    </div>

</body>
</html>