<?php
$role_allowed = 'user';
require_once '../includes/header.php';

$msg = '';
$err = '';
$id_user = $_SESSION['id_user'];

if(!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$id_event = (int)$_GET['id'];

// Proses Checkout Pemesanan (Dengqn transaksi lock-pessimistic / Mencegah Overbooking)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $id_tiket = (int)$_POST['id_tiket'];
    $qty = (int)$_POST['qty'];
    $kode_voucher = $conn->real_escape_string($_POST['voucher']);

    if($qty <= 0) {
        $err = "Kuantitas tidak valid.";
    } else {
        $conn->begin_transaction();
        try {
            // Lock tabel tiket spesifik untuk race condition prevention
            $cek_tiket = $conn->query("SELECT harga, kuota FROM tiket WHERE id_tiket=$id_tiket FOR UPDATE")->fetch_assoc();
            
            if(!$cek_tiket || $cek_tiket['kuota'] < $qty) {
                throw new Exception("Mohon maaf, Kuota tiket tidak mencukupi untuk pesanan anda.");
            }

            $harga_satuan = (float)$cek_tiket['harga'];
            $subtotal = $harga_satuan * $qty;
            $diskon = 0;
            $id_voucher_dipakai = "NULL";

            // Validasi & Lock Voucher
            if(!empty($kode_voucher)) {
                $cek_voucher = $conn->query("SELECT id_voucher, diskon, kuota FROM voucher WHERE kode_voucher='$kode_voucher' AND status='active' FOR UPDATE")->fetch_assoc();
                if(!$cek_voucher) {
                    throw new Exception("Kode voucher tidak valid atau tidak aktif.");
                }
                if($cek_voucher['kuota'] <= 0) {
                    throw new Exception("Kuota pemakaian voucher telah habis.");
                }

                // Validasi: diskon tidak boleh >= subtotal tiket
                if((float)$cek_voucher['diskon'] >= $subtotal) {
                    throw new Exception("Voucher tidak dapat digunakan karena nilai diskon melebihi atau sama dengan harga tiket.");
                }
                
                $diskon = (float)$cek_voucher['diskon'];
                $id_voucher_dipakai = $cek_voucher['id_voucher'];
                
                // Potong kuota voucher
                $conn->query("UPDATE voucher SET kuota = kuota - 1 WHERE id_voucher={$cek_voucher['id_voucher']}");
            }

            $total_bayar = $subtotal - $diskon;
            if($total_bayar < 0) $total_bayar = 0;

            // Potong Kuota Tiket (dengan double-check kuota di WHERE agar race-condition safe)
            $conn->query("UPDATE tiket SET kuota = kuota - $qty WHERE id_tiket=$id_tiket AND kuota >= $qty");
            if($conn->affected_rows !== 1) {
                throw new Exception("Gagal memotong kuota tiket. Silakan coba lagi.");
            }

            // Insert Orders
            $conn->query("INSERT INTO orders (id_user, id_voucher, total, status) VALUES ($id_user, $id_voucher_dipakai, $total_bayar, 'pending')");
            $insert_order_id = $conn->insert_id;

            // Insert Detail Order
            $conn->query("INSERT INTO order_detail (id_order, id_tiket, qty, subtotal) VALUES ($insert_order_id, $id_tiket, $qty, $subtotal)");

            $conn->commit();
            
            // Success: tampilkan notifikasi pop-up dan arahkan ke My Tickets
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Pesanan Berhasil!',
                    text: 'Silakan segera selesaikan pembayaran di halaman tiket Anda.',
                    icon: 'success',
                    background: '#1a1a24', color: '#f8fafc',
                    confirmButtonColor: '#818cf8',
                    confirmButtonText: 'Menuju Tiket Saya'
                }).then(() => { window.location.href='my_tickets.php'; });
            });
            </script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}

// Mengambil Data Event Event
$event = $conn->query("SELECT e.*, v.nama_venue, v.alamat, v.kapasitas FROM event e JOIN venue v ON e.id_venue = v.id_venue WHERE e.id_event=$id_event")->fetch_assoc();

if(!$event) {
    echo "<div class='container mt-5 text-center'><h1>Event tidak ditemukan.</h1></div>";
    require_once '../includes/footer.php';
    exit();
}

$tikets = $conn->query("SELECT * FROM tiket WHERE id_event=$id_event ORDER BY harga ASC");
$vouchers = $conn->query("SELECT kode_voucher, diskon, kuota FROM voucher WHERE status='active' AND kuota > 0 ORDER BY diskon DESC LIMIT 3");
?>

<div class="container mt-4 mb-5">
    <?php if($err): ?>
        <div class="alert alert-danger"><?= $err ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Event Detail -->
        <div class="col-md-7 mb-4">
            <div class="card shadow-sm h-100">
                <?php 
                    $img_src = (isset($event['gambar']) && $event['gambar'] && $event['gambar'] !== 'default.jpg' && file_exists('../uploads/events/' . $event['gambar'])) 
                               ? '../uploads/events/' . htmlspecialchars($event['gambar']) 
                               : 'https://placehold.co/800x400/1a1a2e/ffffff?text=No+Poster+Available';
                ?>
                <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($event['nama_event']) ?>" style="width:100%; height: 350px; object-fit: cover; border-radius: calc(16px - 1px) calc(16px - 1px) 0 0;">
                <div class="card-body p-4">
                    <h2 class="fw-bold mb-3"><?= htmlspecialchars($event['nama_event']) ?></h2>
                    <p class="mb-4 text-white-50"><i class="bi bi-calendar3"></i> <?= date('d F Y', strtotime($event['tanggal_event'])) ?></p>
                    
                    <h5 class="fw-bold border-bottom pb-2 border-secondary">Deskripsi Acara</h5>
                    <p style="white-space: pre-line;" class="text-white-50"><?= htmlspecialchars($event['deskripsi']) ?></p>
                    
                    <h5 class="fw-bold border-bottom pb-2 border-secondary mt-4">Lokasi</h5>
                    <p class="mb-1 fw-bold"><?= htmlspecialchars($event['nama_venue']) ?></p>
                    <p class="text-white-50"><?= htmlspecialchars($event['alamat']) ?></p>
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="col-md-5 mb-4">
            <div class="card shadow border-primary" style="background-color: #1a1a24;">
                <div class="card-body p-4">
                    <h4 class="mb-4 text-center">Beli Tiket</h4>
                    
                    <?php if($vouchers && $vouchers->num_rows > 0): ?>
                        <div class="alert alert-info border-info bg-info bg-opacity-10 py-2 px-3 mb-4">
                            <h6 class="alert-heading fw-bold mb-2 text-info"><i class="bi bi-tags-fill me-1"></i> Promo Spesial!</h6>
                            <ul class="mb-0 ps-3 small text-white-50" style="list-style-type: square;">
                            <?php while($v = $vouchers->fetch_assoc()): ?>
                                <li>Kode <b class="text-white bg-dark px-1 rounded border border-secondary"><?= $v['kode_voucher'] ?></b> diskon Rp <?= number_format($v['diskon'], 0, ',', '.') ?> (Sisa <?= $v['kuota'] ?>)</li>
                            <?php endwhile; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" id="form-checkout">
                        <input type="hidden" name="checkout" value="1">
                        
                        <div class="mb-4">
                            <label class="form-label text-white-50">Kategori Tiket</label>
                            <select class="form-select bg-dark text-white border-secondary" name="id_tiket" id="pilih-tiket" required onchange="calcTotal()">
                                <option value="">-- Pilih Tiket --</option>
                                <?php while($t = $tikets->fetch_assoc()): ?>
                                    <option value="<?= $t['id_tiket'] ?>" data-harga="<?= $t['harga'] ?>" data-kuota="<?= $t['kuota'] ?>" <?= $t['kuota'] <= 0 ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($t['nama_tiket']) ?> - Rp <?= number_format($t['harga'], 0, ',', '.') ?> <?= $t['kuota'] <= 0 ? '(HABIS)' : '(Sisa: ' . $t['kuota'] . ')' ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-danger d-none mt-1" id="err-kuota">Kuota tiket tidak mencukupi atau habis!</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-white-50">Kuantitas</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="qty" id="qty" min="1" value="1" required oninput="calcTotal()">
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-white-50">Kode Promo / Voucher (Opsional)</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-dark text-white border-secondary" name="voucher" id="voucher-input" placeholder="Masukkan jika ada">
                                <button type="button" class="btn btn-outline-primary" id="btn-cek-voucher">Gunakan</button>
                            </div>
                            <small id="voucher-msg" class="d-block mt-1"></small>
                        </div>

                        <ul class="list-group list-group-flush mb-4 rounded border border-secondary text-white-50">
                            <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center border-secondary">
                                Subtotal
                                <span id="text-subtotal">Rp 0</span>
                            </li>
                            <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center border-secondary d-none" id="row-diskon">
                                Diskon Promo
                                <span id="text-diskon" class="text-success fw-bold">- Rp 0</span>
                            </li>
                            <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center fw-bold pt-3 fs-5">
                                Total Bayar
                                <span class="text-primary" id="text-total">Rp 0</span>
                            </li>
                        </ul>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow" id="btn-submit" disabled>Checkout & Bayar</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
let nilaiDiskon = 0;
let isVoucherValid = false;

function calcTotal() {
    const sel = document.getElementById('pilih-tiket');
    const qty = parseInt(document.getElementById('qty').value) || 0;
    const btn = document.getElementById('btn-submit');
    const errObj = document.getElementById('err-kuota');
    const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Math.abs(num));
    
    if(sel.selectedIndex === 0 || qty <= 0) {
        document.getElementById('text-subtotal').innerText = 'Rp 0';
        document.getElementById('text-total').innerText = 'Rp 0';
        document.getElementById('row-diskon').classList.add('d-none');
        btn.disabled = true;
        return;
    }
    
    const option = sel.options[sel.selectedIndex];
    const harga = parseFloat(option.getAttribute('data-harga'));
    const kuota = parseInt(option.getAttribute('data-kuota'));
    
    if(qty > kuota) {
        errObj.classList.remove('d-none');
        btn.disabled = true;
    } else {
        errObj.classList.add('d-none');
        btn.disabled = false;
    }
    
    const subtotal = harga * qty;
    let total = subtotal;

    if (isVoucherValid && nilaiDiskon > 0) {
        total = subtotal - nilaiDiskon;
        if(total < 0) total = 0;
        document.getElementById('row-diskon').classList.remove('d-none');
        document.getElementById('text-diskon').innerText = '- ' + formatRp(nilaiDiskon);
    } else {
        document.getElementById('row-diskon').classList.add('d-none');
    }
    
    document.getElementById('text-subtotal').innerText = formatRp(subtotal);
    document.getElementById('text-total').innerText = formatRp(total); 
}

document.getElementById('btn-cek-voucher').addEventListener('click', function() {
    const kode = document.getElementById('voucher-input').value.trim();
    const msgObj = document.getElementById('voucher-msg');
    
    if(kode === '') {
        nilaiDiskon = 0;
        isVoucherValid = false;
        msgObj.innerText = '';
        calcTotal();
        return;
    }

    msgObj.className = 'd-block mt-1 text-info';
    msgObj.innerText = 'Mengecek voucher...';

    // Ambil subtotal saat ini untuk validasi diskon di server
    const selEl = document.getElementById('pilih-tiket');
    const qtyEl = parseInt(document.getElementById('qty').value) || 1;
    const hargaSatuan = selEl.selectedIndex > 0 ? parseFloat(selEl.options[selEl.selectedIndex].getAttribute('data-harga')) : 0;
    const subtotalSaatIni = hargaSatuan * qtyEl;

    fetch('check_voucher.php?kode=' + encodeURIComponent(kode) + '&harga=' + subtotalSaatIni)
    .then(response => response.json())
    .then(data => {
        if(data.valid) {
            msgObj.className = 'd-block mt-1 text-success fw-bold';
            msgObj.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${data.message}`;
            nilaiDiskon = parseFloat(data.diskon);
            isVoucherValid = true;
        } else {
            msgObj.className = 'd-block mt-1 text-danger';
            msgObj.innerHTML = `<i class="bi bi-x-circle-fill"></i> ${data.message}`;
            nilaiDiskon = 0;
            isVoucherValid = false;
        }
        calcTotal();
    })
    .catch(err => {
        msgObj.className = 'd-block mt-1 text-danger';
        msgObj.innerText = 'Terjadi kesalahan sistem.';
        console.error(err);
    });
});

// Bersihkan event qty dan re-calculate saat mengetik kode baru tapi jadi invalid logikanya
document.getElementById('qty').addEventListener('input', calcTotal);

// Bersihkan diskon jika input dikosongkan
document.getElementById('voucher-input').addEventListener('input', function() {
    if(this.value.trim() === '') {
        document.getElementById('voucher-msg').innerText = '';
        nilaiDiskon = 0;
        isVoucherValid = false;
        calcTotal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

