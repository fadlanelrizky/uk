<?php
$role_allowed = 'user';
require_once '../includes/header.php';

$id_user = $_SESSION['id_user'];

// --- Tambahan Logic Pay & Cancel ---
if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_order = (int)$_GET['id'];
    
    // Pastikan order ini valid dan masih pending
    $cek = $conn->query("SELECT status FROM orders WHERE id_order=$id_order AND id_user=$id_user AND status='pending'")->fetch_assoc();
    if($cek) {
        if($action === 'pay') {
            $conn->query("UPDATE orders SET status='paid' WHERE id_order=$id_order");
            
            // Tiket akan di-generate oleh admin setelah verifikasi
            echo "<script>
            Swal.fire({
                title: 'Berhasil!',
                text: 'Pembayaran berhasil dikonfirmasi. Menunggu persetujuan admin untuk penerbitan e-tiket.',
                icon: 'success',
                background: '#1e293b', color: '#f8fafc',
                confirmButtonColor: '#818cf8'
            }).then(() => { window.location.href='my_tickets.php'; });
            </script>";
            exit();
        } elseif($action === 'cancel') {
            $conn->query("UPDATE orders SET status='cancelled' WHERE id_order=$id_order");
            
            // Kembalikan kuota tiket agar database sinkron
            $details = $conn->query("SELECT id_tiket, qty FROM order_detail WHERE id_order=$id_order");
            while($d = $details->fetch_assoc()) {
                $qty = (int)$d['qty'];
                $id_tiket = $d['id_tiket'];
                $conn->query("UPDATE tiket SET kuota = kuota + $qty WHERE id_tiket=$id_tiket");
            }
            echo "<script>
            Swal.fire({
                title: 'Dibatalkan',
                text: 'Pemesanan telah dibatalkan. Kuota tiket telah dikembalikan.',
                icon: 'warning',
                background: '#1e293b', color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            }).then(() => { window.location.href='my_tickets.php'; });
            </script>";
            exit();
        }
    }
}
// ------------------------------------

$orders = $conn->query("
    SELECT o.*, t.nama_tiket, e.nama_event, e.tanggal_event, e.gambar as event_gambar, od.qty, od.id_detail, od.subtotal
    FROM orders o 
    JOIN order_detail od ON o.id_order = od.id_order
    JOIN tiket t ON od.id_tiket = t.id_tiket
    JOIN event e ON t.id_event = e.id_event
    WHERE o.id_user = $id_user 
    ORDER BY o.tanggal_order DESC
");
?>

<div class="container py-5">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h2 class="fw-bold mb-1">E-Tiket Saya</h2>
            <p class="text-muted small mb-0">Riwayat pemesanan dan kode akses gate tiketmu</p>
        </div>
        <a href="index.php" class="btn btn-sm px-3 py-2" style="background:rgba(129,140,248,0.12);border:1px solid rgba(129,140,248,0.25);border-radius:10px;color:#818cf8;font-weight:600;text-decoration:none;font-size:0.85rem;">
            <i class="bi bi-plus me-1"></i>Beli Tiket Lagi
        </a>
    </div>

    <style>
        .order-card {
            background: #161e2e;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .ticket-wrapper {
            display: flex;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid rgba(129,140,248,0.25);
            border-left: 6px solid #818cf8;
            border-radius: 14px;
            position: relative;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        .ticket-wrapper:hover {
            transform: translateY(-3px);
            border-color: rgba(129,140,248,0.5);
            box-shadow: 0 10px 25px rgba(129,140,248,0.15);
        }
        .ticket-info {
            flex: 1;
            padding: 1.5rem;
            border-right: 2px dashed rgba(129,140,248,0.4);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .ticket-info::before, .ticket-info::after {
            content: '';
            position: absolute;
            right: -13px; /* Centers the 26px circle on the dashed line */
            width: 26px;
            height: 26px;
            background: #161e2e; /* Matches .order-card to fake transparency */
            border-radius: 50%;
            z-index: 10;
        }
        .ticket-info::before { 
            top: -14px; /* Placed exactly at the top border edge */
            border-bottom: 1px solid rgba(129,140,248,0.25); 
        }
        .ticket-info::after { 
            bottom: -14px; /* Placed exactly at the bottom border edge */
            border-top: 1px solid rgba(129,140,248,0.25); 
        }
        .ticket-qr {
            width: 170px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(15,23,42,0.4);
            border-radius: 0 12px 12px 0;
        }
        @media (max-width: 576px) {
            .ticket-wrapper { flex-direction: column; border-left: 1px solid rgba(129,140,248,0.25); border-top: 6px solid #818cf8; }
            .ticket-info { border-right: none; border-bottom: 2px dashed rgba(129,140,248,0.4); }
            .ticket-info::before { top: auto; bottom: -13px; right: -14px; border: none; border-left: 1px solid rgba(129,140,248,0.25); }
            .ticket-info::after { top: auto; bottom: -13px; right: auto; left: -14px; border: none; border-right: 1px solid rgba(129,140,248,0.25); }
            .ticket-qr { width: 100%; flex-direction: row; gap: 15px; justify-content: flex-start; border-radius: 0 0 12px 12px; }
            .ticket-qr .text-center { display: flex; flex-direction: column; align-items: flex-start; justify-content: center; }
        }
    </style>

    <?php if($orders->num_rows > 0): ?>
        <?php while($o = $orders->fetch_assoc()): ?>
            <?php
                $status_class = $o['status'] == 'paid' ? 'success' : ($o['status'] == 'pending' ? 'warning' : 'danger');
                $status_label = $o['status'] == 'paid' ? 'Lunas' : ($o['status'] == 'pending' ? 'Menunggu' : 'Dibatalkan');
                $status_icon  = $o['status'] == 'paid' ? 'bi-check-circle-fill' : ($o['status'] == 'pending' ? 'bi-clock-fill' : 'bi-x-circle-fill');
            ?>
            <div class="order-card">
                <!-- Header Order -->
                <div class="d-flex justify-content-between align-items-md-center flex-column flex-md-row mb-4 gap-2">
                    <div>
                        <h5 class="fw-bold mb-1" style="color:#f8fafc;">Order <span class="font-monospace text-muted" style="font-size:0.9rem;">#<?= str_pad($o['id_order'], 4, '0', STR_PAD_LEFT) ?></span></h5>
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i><?= date('d M Y H:i', strtotime($o['tanggal_order'])) ?> 
                            <span class="mx-2">&bull;</span> 
                            <strong><?= $o['qty'] ?> tiket</strong> 
                            <span class="mx-2">&bull;</span> 
                            <span style="color:#818cf8; font-weight:600;">Rp <?= number_format($o['total'], 0, ',', '.') ?></span>
                        </small>
                    </div>
                    <div>
                        <span class="badge rounded-pill px-3 py-2 text-white shadow" style="background-color: var(--bs-<?= $status_class ?>); font-size:0.75rem; letter-spacing: 0.5px;">
                            <i class="bi <?= $status_icon ?> me-1"></i><?= $status_label ?>
                        </span>
                    </div>
                </div>

                <?php if($o['status'] == 'paid'): ?>
                    <?php
                        $id_detail = $o['id_detail'];
                        $attendees = $conn->query("SELECT kode_tiket, status_checkin FROM attendee WHERE id_order_detail=$id_detail");
                    ?>
                    <?php if($attendees->num_rows > 0): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php while($att = $attendees->fetch_assoc()): ?>
                                <div class="ticket-wrapper">
                                    <!-- Bagian Kiri: Info Konser -->
                                    <div class="ticket-info">
                                        <div class="mb-3">
                                            <h5 class="fw-bold mb-1 text-white text-truncate"><?= htmlspecialchars($o['nama_event']) ?></h5>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar3 me-1" style="color:#c084fc;"></i> <?= date('d M Y', strtotime($o['tanggal_event'])) ?>
                                            </p>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <small class="text-muted d-block" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;">Tipe Tiket</small>
                                                <span class="fw-semibold" style="color:#e2e8f0; font-size:0.9rem;"><?= htmlspecialchars($o['nama_tiket']) ?></span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;">Pemesan</small>
                                                <span class="fw-semibold text-truncate d-block" style="color:#e2e8f0; font-size:0.9rem;"><?= htmlspecialchars($_SESSION['nama']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Bagian Kanan: QR Code & Status -->
                                    <div class="ticket-qr">
                                        <div class="bg-white p-2 rounded-3 shadow-sm mb-2" style="width:fit-content;">
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= $att['kode_tiket'] ?>&size=100x100&bgcolor=ffffff&color=0f172a" 
                                                 alt="QR Code" style="width:70px;height:70px;display:block;">
                                        </div>
                                        <div class="text-center">
                                            <div class="font-monospace fw-bold mb-1" style="color:#a5b4fc;font-size:0.8rem;letter-spacing:1px;">
                                                <?= $att['kode_tiket'] ?>
                                            </div>
                                            <?php if($att['status_checkin'] == 'sudah'): ?>
                                                <span class="badge bg-success text-white shadow-sm" style="font-size:0.7rem; padding: 0.4rem 0.6rem;">Terpakai</span>
                                            <?php else: ?>
                                                <span class="badge text-white shadow-sm" style="background:#818cf8; font-size:0.7rem; padding: 0.4rem 0.6rem;">Berlaku</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <!-- State Pembayaran Lunas tapi belum diverifikasi Admin -->
                        <div class="ticket-wrapper">
                            <div class="ticket-info" style="border-right:none;">
                                <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($o['nama_event']) ?></h5>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-calendar3 me-1" style="color:#c084fc;"></i> <?= date('d M Y', strtotime($o['tanggal_event'])) ?>
                                    <span class="mx-2">&bull;</span>
                                    <i class="bi bi-ticket-perforated ms-1 me-1" style="color:#818cf8;"></i> <?= htmlspecialchars($o['nama_tiket']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="alert mt-3 mb-0 border-0 shadow-sm" style="background: rgba(129,140,248,0.1); border: 1px solid rgba(129,140,248,0.2) !important; color: #a5b4fc; border-radius: 12px; font-size:0.9rem;">
                            <i class="bi bi-hourglass-split me-2"></i> Pembayaran berhasil dikonfirmasi. Menunggu persetujuan Admin untuk penerbitan E-Tiket (QR Code & Kode).
                        </div>
                    <?php endif; ?>

                <?php elseif($o['status'] == 'pending'): ?>
                    <div class="ticket-wrapper">
                        <div class="ticket-info" style="border-right:none;">
                            <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($o['nama_event']) ?></h5>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-calendar3 me-1" style="color:#c084fc;"></i> <?= date('d M Y', strtotime($o['tanggal_event'])) ?>
                                <span class="mx-2">&bull;</span>
                                <i class="bi bi-ticket-perforated ms-1 me-1" style="color:#818cf8;"></i> <?= htmlspecialchars($o['nama_tiket']) ?>
                            </p>
                        </div>
                    </div>
                    <?php
                        $subtotal = $o['subtotal'] ?? 0;
                        $total_bayar = $o['total'];
                        $diskon = $subtotal - $total_bayar;
                        if($diskon < 0) $diskon = 0;
                    ?>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success px-4 bg-gradient" style="border-radius:8px;" onclick="confirmPay('?action=pay&id=<?= $o['id_order'] ?>', '<?= htmlspecialchars(addslashes($o['nama_event'])) ?>', '<?= htmlspecialchars(addslashes($o['nama_tiket'])) ?>', <?= $o['qty'] ?>, <?= $subtotal ?>, <?= $diskon ?>, <?= $total_bayar ?>)">
                            <i class="bi bi-wallet2 me-1"></i> Bayar Sekarang
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger px-3" style="border-radius:8px;" onclick="confirmCancelUser('?action=cancel&id=<?= $o['id_order'] ?>', '<?= htmlspecialchars(addslashes($o['nama_event'])) ?>')">
                            Batalkan
                        </button>
                    </div>
                
                <?php else: ?>
                    <div class="ticket-wrapper opacity-50">
                        <div class="ticket-info" style="border-right:none;">
                            <h5 class="fw-bold mb-1 text-white text-decoration-line-through"><?= htmlspecialchars($o['nama_event']) ?></h5>
                            <p class="text-muted small mb-0">Pesanan telah dibatalkan.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>

    <?php else: ?>
        <div class="text-center py-5 mt-3">
            <div style="width:100px;height:100px;border-radius:50%;background:rgba(129,140,248,0.08);border:2px solid rgba(129,140,248,0.15);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1.5rem;">
                <i class="bi bi-cart-x" style="font-size:2.8rem;color:#818cf8;"></i>
            </div>
            <h4 class="fw-bold mb-2">Belum ada tiket</h4>
            <p class="text-muted mb-4">Riwayat pemesanan Anda masih kosong. Yuk beli tiket sekarang!</p>
            <a href="index.php" class="btn btn-primary px-5 py-2">
                <i class="bi bi-calendar-event me-2"></i>Lihat Event
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka).replace('IDR', 'Rp ').trim();
}

function confirmPay(url, eventName, ticketName, qty, subtotal, diskon, total) {
    let diskonHtml = '';
    if (diskon > 0) {
        diskonHtml = `
            <div class="d-flex justify-content-between mb-2 text-start" style="font-size:0.9rem">
                <span class="text-white-50">Potongan Harga</span>
                <span class="text-success fw-bold">- ${formatRupiah(diskon)}</span>
            </div>
        `;
    }

    Swal.fire({
        title: '<div class="text-start fs-5"><i class="bi bi-wallet2 me-2"></i>Detail Pembayaran</div>',
        html: `
            <div class="bg-dark p-3 rounded mb-3 border border-secondary border-opacity-50 text-start">
                <h6 class="text-white mb-2 fw-bold">${eventName}</h6>
                <p class="small text-muted mb-0"><i class="bi bi-ticket-perforated"></i> Kategori: ${ticketName} <span class="mx-2">|</span> Qty: ${qty} Tiket</p>
            </div>
            
            <div class="d-flex justify-content-between mb-2 text-start" style="font-size:0.9rem">
                <span class="text-white-50">Subtotal Tiket</span>
                <span>${formatRupiah(subtotal)}</span>
            </div>
            ${diskonHtml}
            <hr class="border-secondary border-opacity-25 my-3">
            <div class="d-flex justify-content-between mb-2 fs-5 fw-bold text-start">
                <span>Total Tagihan</span>
                <span class="text-primary">${formatRupiah(total)}</span>
            </div>
        `,
        background: '#1e293b',
        color: '#f8fafc',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Konfirmasi Bayar <i class="bi bi-chevron-right"></i>',
        cancelButtonText: 'Tutup'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

function confirmCancelUser(url, eventName) {
    Swal.fire({
        title: 'Batalkan Pesanan?',
        html: `Apakah Anda yakin ingin membatalkan pesanan tiket <strong>${eventName}</strong> ini? Aksi ini akan mereset tagihan dan mengembalikan kuota.`,
        icon: 'warning',
        showCancelButton: true,
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Batalkan'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
