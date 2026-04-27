<?php
$role_allowed = 'petugas';
require_once '../includes/header.php';

$alert_type = '';
$alert_msg  = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kode_tiket'])) {
    $kode = trim($conn->real_escape_string($_POST['kode_tiket']));

    $cek = $conn->query("
        SELECT a.*, o.status as payment_status, o.total, u.nama_lengkap, t.nama_tiket, e.nama_event, e.tanggal_event
        FROM attendee a 
        JOIN order_detail od ON a.id_order_detail = od.id_detail
        JOIN orders o ON od.id_order = o.id_order
        JOIN users u ON o.id_user = u.id_user
        JOIN tiket t ON od.id_tiket = t.id_tiket
        JOIN event e ON t.id_event = e.id_event
        WHERE a.kode_tiket = '$kode'
        LIMIT 1
    ");

    if($cek->num_rows > 0) {
        $data = $cek->fetch_assoc();
        if($data['status_checkin'] == 'sudah') {
            $alert_type = "danger";
            $alert_msg  = "<strong>DITOLAK!</strong> Tiket <u>$kode</u> sudah digunakan pada " . date('d M Y H:i:s', strtotime($data['waktu_checkin']));
        } else {
            if($data['payment_status'] == 'paid') {
                $id_att = $data['id_attendee'];
                $time   = date('Y-m-d H:i:s');
                $conn->query("UPDATE attendee SET status_checkin='sudah', waktu_checkin='$time' WHERE id_attendee=$id_att");
                $alert_type = "success";
                $alert_msg  = "<strong>AKSES DIIZINKAN!</strong> Selamat datang, <u>" . htmlspecialchars($data['nama_lengkap']) . "</u>!";
            } else {
                $alert_type = "warning";
                $alert_msg  = "<strong>PENDING!</strong> Order tiket ini belum berstatus Lunas.";
            }
        }
    } else {
        $alert_type = "danger";
        $alert_msg  = "<strong>TIDAK DITEMUKAN!</strong> Kode <u>$kode</u> tidak terdaftar dalam sistem.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">

        <!-- ===== ALERT RESULT ===== -->
        <?php if($alert_msg): ?>
        <div class="alert-result mb-4 p-4 rounded-4 border d-flex align-items-start gap-3
            <?= $alert_type == 'success' ? 'border-success bg-success bg-opacity-10 text-success' : ($alert_type == 'warning' ? 'border-warning bg-warning bg-opacity-10 text-warning' : 'border-danger bg-danger bg-opacity-10 text-danger') ?>">
            <i class="bi <?= $alert_type == 'success' ? 'bi-check-circle-fill' : ($alert_type == 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-octagon-fill') ?> fs-3 flex-shrink-0"></i>
            <div>
                <div class="fs-6"><?= $alert_msg ?></div>
                <?php if(isset($data)): ?>
                <hr class="my-2 border-secondary">
                <small class="d-block opacity-75">Event: <strong><?= htmlspecialchars($data['nama_event']) ?></strong></small>
                <small class="d-block opacity-75">Tiket: <strong><?= htmlspecialchars($data['nama_tiket']) ?></strong></small>
                <small class="d-block opacity-75">Tanggal: <strong><?= date('d M Y', strtotime($data['tanggal_event'])) ?></strong></small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== SCANNER CARD ===== -->
        <div class="card p-4">
            <div class="text-center mb-4">
                <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#4f46e5,#ec4899);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                    <i class="bi bi-upc-scan text-white" style="font-size:1.8rem;"></i>
                </div>
                <h4 class="fw-bold mb-1">Validasi Tiket</h4>
                <p class="text-muted small mb-0">Ketik atau scan barcode kode tiket fisik</p>
            </div>

            <!-- ===== CAMERA SCANNER ===== -->
            <div id="reader" class="mb-4 overflow-hidden rounded-4 border border-secondary" style="max-width: 100%; width: 400px; margin: 0 auto; display: none;"></div>
            <div class="text-center mb-4">
                <button type="button" class="btn btn-outline-primary fw-bold" id="btn-toggle-cam">
                    <i class="bi bi-camera-video me-1"></i> Scan via Kamera
                </button>
            </div>

            <!-- ===== INPUT SECTION ===== -->
            <div id="section-manual">
                <form action="" method="POST" id="scan-form">
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text" style="background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-right:none;">
                            <i class="bi bi-upc" style="color:var(--accent-1,#818cf8)"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 fw-bold fs-4 text-center"
                               name="kode_tiket" id="kode_tiket"
                               placeholder="TIX-XXXXXXXX" required autofocus autocomplete="off"
                               style="letter-spacing:4px; text-transform:uppercase;">
                    </div>
                </form>
                <div class="text-center mt-3" id="scan-status">
                    <small class="text-muted"><i class="bi bi-lightning-charge me-1"></i>Auto-verifikasi saat format tiket sesuai (12 karakter)</small>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('kode_tiket');
    const form = document.getElementById('scan-form');
    const status = document.getElementById('scan-status');
    const btnCam = document.getElementById('btn-toggle-cam');
    const readerDiv = document.getElementById('reader');
    let html5QrcodeScanner = null;

    btnCam.addEventListener('click', () => {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
            html5QrcodeScanner = null;
            readerDiv.style.display = 'none';
            btnCam.innerHTML = '<i class="bi bi-camera-video me-1"></i> Scan via Kamera';
            btnCam.classList.replace('btn-danger', 'btn-outline-primary');
        } else {
            readerDiv.style.display = 'block';
            btnCam.innerHTML = '<i class="bi bi-camera-video-off me-1"></i> Tutup Kamera';
            btnCam.classList.replace('btn-outline-primary', 'btn-danger');

            html5QrcodeScanner = new Html5QrcodeScanner("reader", { 
                fps: 30, // Frame check lebih cepat
                qrbox: function(viewfinderWidth, viewfinderHeight) {
                    var minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    var size = Math.floor(minEdge * 0.8);
                    return { width: size, height: size };
                },
                // Resolusi tinggi HD (1280x720) krusial agar garis-garis Barcode 1D terlihat tajam
                videoConstraints: {
                    facingMode: "environment",
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    focusMode: "continuous"
                },
                rememberLastUsedCamera: true
            }, false);
            html5QrcodeScanner.render((decodedText) => {
                input.value = decodedText;
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
                readerDiv.style.display = 'none';
                btnCam.innerHTML = '<i class="bi bi-camera-video me-1"></i> Scan via Kamera';
                btnCam.classList.replace('btn-danger', 'btn-outline-primary');
                
                status.innerHTML = '<small class="text-success fw-bold"><i class="spinner-border spinner-border-sm me-1"></i> Memproses validasi tiket...</small>';
                input.readOnly = true;
                form.submit();
            }, (error) => {});
        }
    });

    // Mencegah input kecil, force uppercase saat diketik
    input.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
        
        // Auto-submit jika format TIX-XXXXXXXX (panjang 12 karakter) terpenuhi
        if (this.value.length >= 12 && this.value.startsWith('TIX-')) {
            status.innerHTML = '<small class="text-success fw-bold"><i class="spinner-border spinner-border-sm me-1"></i> Memproses validasi tiket...</small>';
            input.readOnly = true; // mencegah perubahan lanjutan sebelum memproses
            form.submit();
        }
    });

    // Selalu fokus ke input text setiap kali klik di area mana saja (fitur kemudahan UI)
    document.body.addEventListener('click', (e) => {
        if (!input.readOnly && e.target.tagName !== 'INPUT' && e.target.tagName !== 'A') {
            input.focus();
        }
    });

    // Auto-hilang alert notifikasi dalam 3 detik
    const alertResult = document.querySelector('.alert-result');
    if (alertResult) {
        setTimeout(() => {
            alertResult.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alertResult.style.opacity = '0';
            alertResult.style.transform = 'translateY(-10px)';
            setTimeout(() => alertResult.remove(), 500);
        }, 3000);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

