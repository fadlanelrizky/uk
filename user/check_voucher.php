<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['valid' => false, 'message' => 'Login diperlukan']);
    exit();
}

$kode  = isset($_GET['kode'])  ? $conn->real_escape_string($_GET['kode'])  : '';
$harga = isset($_GET['harga']) ? (float)$_GET['harga'] : 0; // harga tiket per-satuan × qty (subtotal)

if (empty($kode)) {
    echo json_encode(['valid' => false, 'message' => 'Kode promo kosong.']);
    exit();
}

// Cek apakah voucher ada dan aktif
$q = $conn->query("SELECT id_voucher, diskon, kuota FROM voucher WHERE kode_voucher='$kode' AND status='active'");
if ($q->num_rows === 0) {
    echo json_encode(['valid' => false, 'message' => 'Kode voucher tidak ditemukan atau tidak aktif.']);
    exit();
}

$v = $q->fetch_assoc();

// Cek kuota voucher
if ((int)$v['kuota'] <= 0) {
    echo json_encode(['valid' => false, 'message' => 'Kuota pemakaian voucher ini telah habis.']);
    exit();
}

$diskon = (float)$v['diskon'];

// Validasi: diskon tidak boleh >= harga tiket (subtotal)
// Hanya validasi jika harga dikirim dari frontend
if ($harga > 0 && $diskon >= $harga) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Voucher tidak dapat digunakan karena nilai diskon (Rp ' . number_format($diskon, 0, ',', '.') . ') melebihi atau sama dengan harga tiket.'
    ]);
    exit();
}

// Berhasil
echo json_encode([
    'valid'   => true,
    'diskon'  => $diskon,
    'message' => 'Voucher berhasil digunakan!'
]);
?>
