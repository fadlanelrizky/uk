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
            $cek_tiket = $conn->query("SELECT harga, kuota, max_pembelian FROM tiket WHERE id_tiket=$id_tiket FOR UPDATE")->fetch_assoc();
            
            if(!$cek_tiket || $cek_tiket['kuota'] < $qty) {
                throw new Exception("Mohon maaf, Kuota tiket tidak mencukupi untuk pesanan anda.");
            }

            // Validasi batas maksimal tiket per user per kategori tiket
            $cek_user_tickets = $conn->query("
                SELECT SUM(od.qty) as total_qty
                FROM orders o
                JOIN order_detail od ON o.id_order = od.id_order
                WHERE o.id_user = $id_user AND od.id_tiket = $id_tiket AND o.status != 'cancelled'
            ")->fetch_assoc();
            
            $max_per_user = (int)$cek_tiket['max_pembelian'];
            $total_sebelumnya = (int)$cek_user_tickets['total_qty'];
            if (($total_sebelumnya + $qty) > $max_per_user) {
                $sisa_kuota_user = $max_per_user - $total_sebelumnya;
                if ($sisa_kuota_user > 0) {
                    throw new Exception("Anda hanya dapat membeli maksimal $sisa_kuota_user tiket lagi untuk kategori ini (Batas $max_per_user tiket/akun).");
                } else {
                    throw new Exception("Anda telah mencapai batas maksimal pembelian tiket untuk kategori ini ($max_per_user tiket).");
                }
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

// Cek jatah tiket user per kategori tiket
$user_purchases_res = $conn->query("
    SELECT od.id_tiket, SUM(od.qty) as total_qty
    FROM orders o
    JOIN order_detail od ON o.id_order = od.id_order
    JOIN tiket t ON od.id_tiket = t.id_tiket
    WHERE o.id_user = $id_user AND t.id_event = $id_event AND o.status != 'cancelled'
    GROUP BY od.id_tiket
");
$user_purchases = [];
while($up = $user_purchases_res->fetch_assoc()) {
    $user_purchases[$up['id_tiket']] = (int)$up['total_qty'];
}
?>

<div class="container mt-4 mb-5">
    <?php if($err): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Gagal!',
                text: '<?= addslashes($err) ?>',
                icon: 'error',
                background: '#1a1a24', color: '#f8fafc',
                confirmButtonColor: '#ec4899'
            });
        });
        </script>
    <?php endif; ?>

    <div class="mb-3">
        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

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
                        <div class="alert alert-info border-info bg-info bg-opacity-10 py-3 px-3 mb-4 rounded-3">
                            <h6 class="alert-heading fw-bold mb-3 text-info"><i class="bi bi-tags-fill me-1"></i> Promo Spesial! (Klik untuk pakai)</h6>
                            <div class="d-flex flex-wrap gap-2">
                            <?php while($v = $vouchers->fetch_assoc()): ?>
                                <button type="button" class="btn btn-sm btn-outline-info border-info border-opacity-50 text-start btn-apply-voucher" data-kode="<?= htmlspecialchars($v['kode_voucher']) ?>" style="background: rgba(13,202,240,0.05);">
                                    <div class="fw-bold fs-6"><?= htmlspecialchars($v['kode_voucher']) ?></div>
                                    <div class="small text-white-50">Diskon Rp <?= number_format($v['diskon'], 0, ',', '.') ?> (Sisa <?= $v['kuota'] ?>)</div>
                                </button>
                            <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" id="form-checkout" onsubmit="
                        const btn = document.getElementById('btn-submit');
                        btn.disabled = true;
                        btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-2\'></span>Memproses...';
                    ">
                        <input type="hidden" name="checkout" value="1">
                        
                        <div class="mb-4">
                            <label class="form-label text-white-50 mb-3">Kategori Tiket</label>
                            <div class="row g-3">
                                <?php while($t = $tikets->fetch_assoc()): ?>
                                    <?php 
                                        $is_habis = $t['kuota'] <= 0;
                                        $sisa_teks = $is_habis ? 'HABIS' : ($t['kuota'] <= 10 ? 'Sisa ' . $t['kuota'] . ' Tiket!' : 'Sisa ' . $t['kuota'] . ' Tiket');
                                        $sisa_class = $is_habis ? 'text-danger fw-bold' : ($t['kuota'] <= 10 ? 'text-warning fw-bold' : 'text-success');
                                    ?>
                                    <div class="col-12">
                                        <label class="w-100" style="<?= !$is_habis ? 'cursor:pointer;' : 'opacity:0.6;' ?>">
                                            <input type="radio" name="id_tiket" class="d-none ticket-radio" value="<?= $t['id_tiket'] ?>" data-harga="<?= $t['harga'] ?>" data-kuota="<?= $t['kuota'] ?>" data-max-pembelian="<?= $t['max_pembelian'] ?>" data-purchased="<?= isset($user_purchases[$t['id_tiket']]) ? $user_purchases[$t['id_tiket']] : 0 ?>" <?= $is_habis ? 'disabled' : '' ?> onchange="calcTotal()" required>
                                            <div class="card bg-dark border-secondary ticket-card transition-all">
                                                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="fw-bold mb-1 text-white"><?= htmlspecialchars($t['nama_tiket']) ?></h6>
                                                        <div class="text-primary fw-bold">Rp <?= number_format($t['harga'], 0, ',', '.') ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="<?= $sisa_class ?>"><?= $sisa_teks ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <small class="text-danger d-none mt-2 d-block" id="err-kuota"></small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-white-50">Kuantitas</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="qty" id="qty" min="1" max="1" value="1" required oninput="calcTotal()" disabled>
                            <small class="text-info mt-1 d-block" id="info-max-beli"><i class="bi bi-info-circle me-1"></i>Pilih kategori tiket terlebih dahulu.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-white-50">Kode Promo / Voucher (Opsional)</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" name="voucher" id="voucher-input" placeholder="Masukkan atau tempel kode di sini">
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

                        <div class="sticky-mobile-checkout">
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow" id="btn-submit" disabled>Checkout & Bayar</button>
                        </div>
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
    const selectedTicket = document.querySelector('input[name="id_tiket"]:checked');
    let qty = parseInt(document.getElementById('qty').value) || 0;
    const btn = document.getElementById('btn-submit');
    const errObj = document.getElementById('err-kuota');
    const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Math.abs(num));
    
    // Update active style for radio cards
    document.querySelectorAll('.ticket-card').forEach(el => {
        el.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
        el.classList.add('border-secondary', 'bg-dark');
    });
    if(selectedTicket) {
        const card = selectedTicket.nextElementSibling;
        card.classList.remove('border-secondary', 'bg-dark');
        card.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
    }

    if(!selectedTicket) {
        document.getElementById('text-subtotal').innerText = 'Rp 0';
        document.getElementById('text-total').innerText = 'Rp 0';
        document.getElementById('row-diskon').classList.add('d-none');
        document.getElementById('qty').disabled = true;
        document.getElementById('info-max-beli').innerHTML = '<i class="bi bi-info-circle me-1"></i>Pilih kategori tiket terlebih dahulu.';
        btn.disabled = true;
        return;
    }
    
    const harga = parseFloat(selectedTicket.getAttribute('data-harga'));
    const kuota = parseInt(selectedTicket.getAttribute('data-kuota'));
    const maxPembelian = parseInt(selectedTicket.getAttribute('data-max-pembelian')) || 5;
    const purchased = parseInt(selectedTicket.getAttribute('data-purchased')) || 0;
    
    let maxBeli = maxPembelian - purchased;
    if(maxBeli < 0) maxBeli = 0;

    const qtyInput = document.getElementById('qty');
    qtyInput.disabled = (maxBeli <= 0);
    qtyInput.max = maxBeli;

    qty = parseInt(qtyInput.value) || 0;
    if(qty > maxBeli && maxBeli > 0) {
        qty = maxBeli;
        qtyInput.value = maxBeli;
    } else if (qty === 0 && maxBeli > 0) {
        qty = 1;
        qtyInput.value = 1;
    }

    document.getElementById('info-max-beli').innerHTML = `<i class="bi bi-info-circle me-1"></i>Maks. ${maxPembelian} tiket/akun. (Sisa jatah Anda: ${maxBeli})`;
    
    if(qty > kuota) {
        errObj.innerText = "Kuota tiket tidak mencukupi atau habis!";
        errObj.classList.remove('d-none');
        btn.disabled = true;
    } else if (qty > maxBeli) {
        errObj.innerText = `Melebihi sisa jatah pembelian Anda (${maxBeli} tiket).`;
        errObj.classList.remove('d-none');
        btn.disabled = true;
    } else if (maxBeli <= 0) {
        errObj.innerText = `Anda telah mencapai batas maksimal pembelian tiket ini (${maxPembelian} tiket).`;
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

let voucherTimeout;
function checkVoucher() {
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
    const selectedTicket = document.querySelector('input[name="id_tiket"]:checked');
    const qtyEl = parseInt(document.getElementById('qty').value) || 1;
    const hargaSatuan = selectedTicket ? parseFloat(selectedTicket.getAttribute('data-harga')) : 0;
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
}

// Bersihkan event qty dan re-calculate saat mengetik kode baru tapi jadi invalid logikanya
document.getElementById('qty').addEventListener('input', calcTotal);

// Bersihkan diskon jika input dikosongkan, atau cek jika diisi
document.getElementById('voucher-input').addEventListener('input', function() {
    clearTimeout(voucherTimeout);
    if(this.value.trim() === '') {
        document.getElementById('voucher-msg').innerText = '';
        nilaiDiskon = 0;
        isVoucherValid = false;
        calcTotal();
    } else {
        voucherTimeout = setTimeout(checkVoucher, 600); // 600ms debounce
    }
});

// Auto-apply voucher when clicking promo buttons
document.querySelectorAll('.btn-apply-voucher').forEach(btn => {
    btn.addEventListener('click', function() {
        const kode = this.getAttribute('data-kode');
        document.getElementById('voucher-input').value = kode;
        clearTimeout(voucherTimeout);
        checkVoucher();
    });
});
</script>

<style>
.ticket-card { transition: all 0.2s ease-in-out; }
.ticket-radio:checked + .ticket-card {
    border-color: #818cf8 !important;
    background-color: rgba(129,140,248,0.1) !important;
}

@media (max-width: 767.98px) {
    .sticky-mobile-checkout {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1040;
        padding: 1rem;
        background: #1a1a24;
        border-top: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px 20px 0 0;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
    }
    body { padding-bottom: 90px; }
}
</style>

<?php require_once '../includes/footer.php'; ?>

