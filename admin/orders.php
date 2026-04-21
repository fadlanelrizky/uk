<?php
$role_allowed = 'admin';

// ============================================================
// EXPORT CSV — harus dijalankan SEBELUM include header.php
// agar tidak ada HTML output yang mencemari response file.
// ============================================================
if(isset($_GET['export']) && $_GET['export'] == 'csv') {
    require_once '../config/database.php';

    $search        = isset($_GET['search'])  ? $conn->real_escape_string($_GET['search'])  : '';
    $filter_status = isset($_GET['status'])  ? $conn->real_escape_string($_GET['status'])  : '';

    $where_sql = "1=1";
    if($search)        $where_sql .= " AND (o.id_order LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%' OR u.email LIKE '%$search%')";
    if($filter_status) $where_sql .= " AND o.status = '$filter_status'";

    // BOM UTF-8 agar Excel tidak salah baca karakter
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Orders_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

    fputcsv($output, ['ID Order', 'Tanggal', 'Nama Kustomer', 'Email', 'Total Transaksi (Rp)', 'Status']);

    $export_data = $conn->query("
        SELECT o.id_order, o.tanggal_order, u.nama_lengkap, u.email, o.total, o.status
        FROM orders o
        JOIN users u ON o.id_user = u.id_user
        WHERE $where_sql
        ORDER BY o.tanggal_order DESC
    ");
    while($row = $export_data->fetch_assoc()) {
        fputcsv($output, [
            '#' . str_pad($row['id_order'], 4, '0', STR_PAD_LEFT),
            date('d/m/Y H:i', strtotime($row['tanggal_order'])),
            $row['nama_lengkap'],
            $row['email'],
            number_format($row['total'], 0, ',', '.'),
            strtoupper($row['status'])
        ]);
    }
    fclose($output);
    exit();
}
// ============================================================

require_once '../includes/header.php';

// Filter & Search — digunakan untuk query halaman maupun stats
$search        = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

$where_sql = "1=1";
if($search)        $where_sql .= " AND (o.id_order LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%' OR u.email LIKE '%$search%')";
if($filter_status) $where_sql .= " AND o.status = '$filter_status'";

$msg = '';

// Proses Approve dan Cancel
if(isset($_POST['approve'])) {
    $id_order = (int)$_POST['approve'];
    $conn->begin_transaction();
    try {
        $cek_tiket = $conn->query("SELECT a.id_attendee FROM attendee a JOIN order_detail od ON a.id_order_detail = od.id_detail WHERE od.id_order=$id_order");
        if($cek_tiket->num_rows == 0) {
            $conn->query("UPDATE orders SET status='paid' WHERE id_order=$id_order");
            $details = $conn->query("SELECT * FROM order_detail WHERE id_order=$id_order");
            while($d = $details->fetch_assoc()) {
                $qty = (int)$d['qty'];
                for($i=0; $i<$qty; $i++) {
                    $kode = 'TIX-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
                    $id_detail = $d['id_detail'];
                    $conn->query("INSERT INTO attendee (id_order_detail, kode_tiket, status_checkin) VALUES ($id_detail, '$kode', 'belum')");
                }
            }
            $conn->commit();
            $msg = "Order diverifikasi & e-tiket terbit!";
        } else {
            $conn->rollback();
            $msg = "Order sudah diverifikasi.";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

if(isset($_POST['cancel'])) {
    $id = (int)$_POST['cancel'];
    $conn->begin_transaction();
    try {
        $conn->query("UPDATE orders SET status='cancelled' WHERE id_order=$id");
        // Kembalikan kuota tiket agar stok sinkron
        $details = $conn->query("SELECT id_tiket, qty FROM order_detail WHERE id_order=$id");
        while($d = $details->fetch_assoc()) {
            $conn->query("UPDATE tiket SET kuota = kuota + {$d['qty']} WHERE id_tiket={$d['id_tiket']}");
        }
        $conn->commit();
        $msg = "Order dibatalkan & kuota tiket dikembalikan.";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error saat membatalkan: " . $e->getMessage();
    }
}

// 2. Pagination
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$total_rows_query = $conn->query("SELECT COUNT(*) as cnt FROM orders o JOIN users u ON o.id_user = u.id_user WHERE $where_sql");
$total_rows = $total_rows_query->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

// Data Orders with Pagination
$orders = $conn->query("
    SELECT o.*, u.nama_lengkap, u.email 
    FROM orders o 
    JOIN users u ON o.id_user = u.id_user 
    WHERE $where_sql
    ORDER BY o.tanggal_order DESC
    LIMIT $offset, $per_page
");

// 3. Stats for Widgets
$stats_query = "
    SELECT 
        SUM(CASE WHEN o.status = 'paid' THEN o.total ELSE 0 END) as total_revenue,
        SUM(CASE WHEN o.status = 'paid' THEN 1 ELSE 0 END) as total_paid_orders,
        (SELECT COUNT(a.id_attendee) FROM attendee a JOIN order_detail od ON a.id_order_detail = od.id_detail JOIN orders ord ON od.id_order = ord.id_order WHERE ord.status = 'paid') as total_tickets_sold
    FROM orders o 
    JOIN users u ON o.id_user = u.id_user 
    WHERE $where_sql
";
$stats = $conn->query($stats_query)->fetch_assoc();
?>

<!-- START HTML -->
<div class="row" id="report-content">
    <div class="col-12 mb-3">
        <?php if($msg): ?>
            <div class="alert alert-info py-2 d-print-none"><?= $msg ?></div>
        <?php endif; ?>
    </div>

    <div class="col-12 mb-3 d-print-none">
        <div class="card p-3 shadow-lg">
            <form action="" method="GET" class="row gx-3 gy-2 align-items-center">
                <div class="col-md-4">
                    <label class="form-label text-white-50 mb-1"><small>Cari (ID/Nama/Email)</small></label>
                    <input type="text" name="search" class="form-control form-control-sm border-secondary bg-transparent text-white" value="<?= htmlspecialchars($search) ?>" placeholder="Kata kunci...">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-white-50 mb-1"><small>Filter Status</small></label>
                    <select name="status" class="form-select form-select-sm border-secondary bg-dark text-white">
                        <option value="">-- Semua Status --</option>
                        <option value="paid" <?= $filter_status == 'paid' ? 'selected' : '' ?>>Paid (Berhasil)</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending (Menunggu)</option>
                        <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled (Batal)</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2 mt-4 mt-md-0">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="orders.php" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
                </div>
            </form>
            
            <hr class="border-secondary mb-2 mt-3">
            <div class="text-end">
                <a href="?export=csv&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel (CSV)</a>
                <button onclick="window.print()" class="btn btn-sm btn-danger"><i class="bi bi-file-pdf"></i> Cetak Laporan (PDF)</button>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-lg">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center border-bottom border-secondary">
                <span>Daftar Transaksi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered text-white m-0 border-secondary">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">#ID</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Detail Tiket</th>
                                <th>Total (Rp)</th>
                                <th class="text-center">Status</th>
                                <th class="text-center d-print-none">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($orders->num_rows > 0): ?>
                                <?php while($o = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center align-middle"><?= $o['id_order'] ?></td>
                                        <td class="align-middle"><?= date('d-m-Y H:i', strtotime($o['tanggal_order'])) ?></td>
                                        <td class="align-middle">
                                            <?= htmlspecialchars($o['nama_lengkap']) ?><br>
                                            <small class="text-white-50"><i class="bi bi-envelope"></i> <?= htmlspecialchars($o['email']) ?></small>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php 
                                            $id_loop = $o['id_order'];
                                            $det_q = $conn->query("
                                                SELECT od.id_detail, e.nama_event, t.nama_tiket, od.qty 
                                                FROM order_detail od 
                                                JOIN tiket t ON od.id_tiket = t.id_tiket 
                                                JOIN event e ON t.id_event = e.id_event
                                                WHERE od.id_order = $id_loop
                                            ");
                                            
                                            $swal_html = "<div class=\'text-start\'>";
                                            $current_event = "";
                                            while($dq = $det_q->fetch_assoc()) {
                                                if($current_event != $dq['nama_event']) {
                                                    $current_event = $dq['nama_event'];
                                                    $swal_html .= "<div class=\'text-info mt-3 mb-1 fw-bold\'><i class=\'bi bi-calendar-event me-2\'></i>" . addslashes(htmlspecialchars($current_event)) . "</div>";
                                                }
                                                $swal_html .= "<div class=\'ps-4 mb-1 opacity-75\'><i class=\'bi bi-ticket-perforated me-2\'></i>" . addslashes(htmlspecialchars($dq['nama_tiket'])) . " <span class=\'badge bg-secondary ms-1\'>&times; " . $dq['qty'] . "</span></div>";
                                                
                                                // Fetch attendees specific to this order detail
                                                $id_detail_loop = $dq['id_detail'];
                                                $att_q = $conn->query("SELECT kode_tiket, status_checkin FROM attendee WHERE id_order_detail = $id_detail_loop");
                                                if($att_q->num_rows > 0) {
                                                    $swal_html .= "<div class=\'ps-5 mb-3 mt-1\'>";
                                                    while($att = $att_q->fetch_assoc()) {
                                                        $badge = $att['status_checkin'] == 'sudah' ? "<span class=\'badge bg-success ms-2\'>Checked-in</span>" : "<span class=\'badge bg-dark border border-secondary ms-2\'>Belum</span>";
                                                        $swal_html .= "<div class=\'small text-white-50 font-monospace mb-1\'><i class=\'bi bi-qr-code me-1\'></i> " . addslashes($att['kode_tiket']) . $badge . "</div>";
                                                    }
                                                    $swal_html .= "</div>";
                                                } else {
                                                    $swal_html .= "<div class=\'mb-3\'></div>"; // spacer if no ticket generated yet
                                                }
                                            }
                                            $swal_html .= "</div>";
                                            ?>
                                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1 d-print-none" onclick="showTicketDetail('Order #<?= str_pad($o['id_order'], 4, '0', STR_PAD_LEFT) ?>', '<?= $swal_html ?>')">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <span class="d-none d-print-inline text-white-50"><small>Lihat di Web</small></span>
                                        </td>
                                        <td class="align-middle fw-semibold text-success">Rp <?= number_format($o['total'], 0, ',', '.') ?></td>
                                        <td class="text-center align-middle">
                                            <?php if($o['status'] == 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif($o['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle d-print-none">
                                            <?php 
                                                $id_loop = $o['id_order'];
                                                $cek_t = $conn->query("SELECT id_attendee FROM attendee a JOIN order_detail od ON a.id_order_detail = od.id_detail WHERE od.id_order=$id_loop");
                                                $has_ticket = $cek_t->num_rows > 0;
                                            ?>
                                            <?php if($o['status'] == 'pending'): ?>
                                                <!-- Pending: hanya bisa di-cancel, approve tidak diperlukan (tiket auto-terbit saat bayar) -->
                                                <form method="POST" action="" id="cancelForm<?= $o['id_order'] ?>" class="d-inline m-0 p-0">
                                                    <input type="hidden" name="cancel" value="<?= $o['id_order'] ?>">
                                                    <button type="button" class="btn btn-sm btn-danger py-1" onclick="confirmCancel(<?= $o['id_order'] ?>, '<?= htmlspecialchars(addslashes($o['nama_lengkap'])) ?>')">
                                                        <i class="bi bi-x-lg me-1"></i>Cancel
                                                    </button>
                                                </form>
                                            <?php elseif($has_ticket): ?>
                                                <span class="badge bg-secondary p-2 mt-1">Selesai</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary opacity-50 p-2 mt-1" style="text-decoration:line-through;">Batal</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-white-50 py-4">Tidak ada data transaksi.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer bg-transparent border-top border-secondary pt-3 pb-2 d-print-none">
                <!-- Pagination Links -->
                <?php if($total_pages >= 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0" data-bs-theme="dark">
                            <!-- Prev -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page - 1 ?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>">&laquo;</a>
                            </li>
                            <!-- Num -->
                            <?php for($i=1; $i<=$total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="?page=<?= $i ?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <!-- Next -->
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page + 1 ?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter_status)?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</div>

<style>
@media print {
    body { background-color: white !important; color: black !important; padding: 20px;}
    .app-wrapper { background: white !important;}
    .card { border: none !important; box-shadow: none !important; margin-bottom: 20px;}
    .card-header {color:black!important; background-color:white!important; border-bottom: 2px solid black !important; font-size: 1.5rem; font-weight:bold;}
    .table-bordered th, .table-bordered td { border-color: #dee2e6 !important; color: black !important; }
    .badge { color: black !important; border: 1px solid black; }
    .bg-success, .bg-warning, .bg-danger, .bg-dark, .bg-primary, .bg-info { background-color: transparent !important; }
    #sidebar, .navbar, .mobile-header, #toggle-sidebar { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    body::before { display: none !important; }
    ::-webkit-scrollbar { display: none; }
    .text-white-50 { color: #666 !important; }
}
</style>

<script>
function confirmApprove(id, nama) {
    Swal.fire({
        title: 'Verifikasi Order?',
        html: `Terbitkan E-Tiket untuk pesanan dari <strong>${nama}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Verifikasi!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('approveForm' + id).submit();
        }
    });
}

function confirmCancel(id, nama) {
    Swal.fire({
        title: 'Batalkan Order?',
        html: `Batalkan pesanan <strong>${nama}</strong> ini? Kuota tiket akan dikembalikan ke sistem.`,
        icon: 'warning',
        showCancelButton: true,
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Batalkan'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('cancelForm' + id).submit();
        }
    });
}

function showTicketDetail(orderId, htmlContent) {
    Swal.fire({
        title: 'Detail ' + orderId,
        html: htmlContent,
        icon: 'info',
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Tutup',
        customClass: {
            htmlContainer: 'text-start'
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
