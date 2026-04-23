<?php
$role_allowed = 'admin';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id_event = (int)$_POST['id_event'];
    $nama = $conn->real_escape_string($_POST['nama_tiket']);
    $harga = (float)$_POST['harga'];
    $kuota = (int)$_POST['kuota'];
    $max_pembelian = isset($_POST['max_pembelian']) ? (int)$_POST['max_pembelian'] : 5;

    // Validasi Kapasitas
    $kapasitas_res = $conn->query("SELECT v.kapasitas FROM event e JOIN venue v ON e.id_venue = v.id_venue WHERE e.id_event = $id_event");
    $kapasitas_row = $kapasitas_res->fetch_assoc();
    $kapasitas_venue = $kapasitas_row ? (int)$kapasitas_row['kapasitas'] : 0;

    $kuota_saat_ini_res = $conn->query("SELECT SUM(kuota) as total_kuota FROM tiket WHERE id_event = $id_event");
    $kuota_saat_ini_row = $kuota_saat_ini_res->fetch_assoc();
    $total_kuota_lama = $kuota_saat_ini_row['total_kuota'] ? (int)$kuota_saat_ini_row['total_kuota'] : 0;

    if($harga <= 0) {
        $msg = "Error: Harga tiket tidak boleh Rp 0!";
        $msg_type = 'danger';
    } elseif($kuota < 0) {
        $msg = "Error: Kuota tiket tidak boleh minus!";
        $msg_type = 'danger';
    } else {
        if($_POST['action'] == 'add') {
            $total_baru = $total_kuota_lama + $kuota;
            if($total_baru > $kapasitas_venue) {
                $msg = "Error: Total kuota tiket ($total_baru) melebihi kapasitas venue ($kapasitas_venue)!";
                $msg_type = 'danger';
            } else {
                $conn->query("INSERT INTO tiket (id_event, nama_tiket, harga, kuota, max_pembelian) VALUES ($id_event, '$nama', $harga, $kuota, $max_pembelian)");
                $msg = "Kategori tiket berhasil ditambahkan!";
            }
        } elseif($_POST['action'] == 'edit') {
            $id = (int)$_POST['id_tiket'];
            $kuota_tiket_ini_res = $conn->query("SELECT kuota FROM tiket WHERE id_tiket = $id");
            $kuota_tiket_ini_row = $kuota_tiket_ini_res->fetch_assoc();
            $kuota_tiket_ini = $kuota_tiket_ini_row ? (int)$kuota_tiket_ini_row['kuota'] : 0;

            $total_baru = $total_kuota_lama - $kuota_tiket_ini + $kuota;
            if($total_baru > $kapasitas_venue) {
                $msg = "Error: Total kuota tiket ($total_baru) melebihi kapasitas venue ($kapasitas_venue)!";
                $msg_type = 'danger';
            } else {
                $conn->query("UPDATE tiket SET id_event=$id_event, nama_tiket='$nama', harga=$harga, kuota=$kuota, max_pembelian=$max_pembelian WHERE id_tiket=$id");
                $msg = "Kategori tiket berhasil diupdate!";
            }
        }
    }
}

if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM tiket WHERE id_tiket=$id");
    $msg = "Tiket berhasil dihapus!";
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_event = isset($_GET['filter_event']) ? (int)$_GET['filter_event'] : '';
$where_sql = "1=1";
if($search) {
    $where_sql .= " AND (t.nama_tiket LIKE '%$search%' OR e.nama_event LIKE '%$search%')";
}
if($filter_event) {
    $where_sql .= " AND t.id_event = $filter_event";
}

$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$total_res = $conn->query("SELECT COUNT(*) as cnt FROM tiket t JOIN event e ON t.id_event = e.id_event WHERE $where_sql");
$total_rows = $total_res->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

$tikets = $conn->query("SELECT t.*, e.nama_event, e.tanggal_event FROM tiket t JOIN event e ON t.id_event = e.id_event WHERE $where_sql ORDER BY e.id_event DESC, t.id_tiket DESC LIMIT $offset, $per_page");
$events = $conn->query("SELECT e.id_event, e.nama_event, v.kapasitas, COALESCE((SELECT SUM(kuota) FROM tiket WHERE id_event = e.id_event), 0) as terpakai FROM event e LEFT JOIN venue v ON e.id_venue = v.id_venue ORDER BY e.id_event DESC");
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">Tambah / Edit Tiket</div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-<?= $msg_type ?> py-2"><?= $msg ?></div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id_tiket" id="id_tiket">
                    
                    <div class="mb-3">
                        <label class="text-white-50">Pilih Event</label>
                        <select class="form-control" name="id_event" id="id_event" required>
                            <option value="" data-sisa="">-- Pilih Event --</option>
                            <?php while($e = $events->fetch_assoc()): ?>
                                <?php $sisa = max(0, $e['kapasitas'] - $e['terpakai']); ?>
                                <option value="<?= $e['id_event'] ?>" data-sisa="<?= $sisa ?>"><?= htmlspecialchars($e['nama_event']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Nama/Kategori Tiket</label>
                        <input type="text" class="form-control" name="nama_tiket" id="nama_tiket" required placeholder="Contoh: VIP, Festival">
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Harga (Rp)</label>
                        <input type="number" step="1" min="1" class="form-control" name="harga" id="harga" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Kuota Tersedia <span id="sisa_kuota_info" class="badge bg-info ms-2 d-none">Sisa: 0</span></label>
                        <input type="number" min="0" class="form-control" name="kuota" id="kuota" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Maks. Pembelian/Akun</label>
                        <input type="number" class="form-control" name="max_pembelian" id="max_pembelian" value="5" min="1" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="btn-submit">Simpan Tiket</button>
                    <button type="button" class="btn btn-secondary w-100 mt-2 d-none" id="btn-cancel" onclick="resetForm()">Batal Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <span>Daftar Tiket</span>
                <form action="" method="GET" class="d-flex gap-2 mb-0 flex-wrap">
                    <select name="filter_event" class="form-select form-select-sm border-secondary bg-dark text-white" style="max-width:200px;">
                        <option value="">Semua Event</option>
                        <?php 
                        $filter_events = $conn->query("SELECT id_event, nama_event FROM event ORDER BY id_event DESC");
                        while($fe = $filter_events->fetch_assoc()): 
                        ?>
                            <option value="<?= $fe['id_event'] ?>" <?= $filter_event == $fe['id_event'] ? 'selected' : '' ?>><?= htmlspecialchars($fe['nama_event']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm border-secondary bg-transparent text-white" style="max-width:150px;" placeholder="Cari tiket..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if($search || $filter_event): ?>
                        <a href="tiket.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-white">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Kategori Tiket</th>
                                <th>Harga</th>
                                <th>Kuota</th>
                                <th class="text-center">Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($tikets->num_rows > 0): ?>
                                <?php while($t = $tikets->fetch_assoc()): ?>
                                    <?php $is_past = strtotime($t['tanggal_event']) < strtotime('today'); ?>
                                    <tr class="<?= $is_past ? 'opacity-75' : '' ?>">
                                        <td><?= htmlspecialchars($t['nama_event']) ?></td>
                                        <td><?= htmlspecialchars($t['nama_tiket']) ?></td>
                                        <td>Rp <?= number_format($t['harga'], 0, ',', '.') ?></td>
                                        <td><?= $t['kuota'] ?></td>
                                        <td class="text-center">
                                            <?php if($is_past): ?>
                                                <span class="badge" style="background:#374151;color:#9ca3af;font-size:0.72rem;"><i class="bi bi-clock-history me-1"></i>Sudah Berlalu</span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-size:0.72rem;"><i class="bi bi-calendar-check me-1"></i>Akan Datang</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info py-0 px-2" onclick='editTiket(<?= json_encode($t) ?>)'><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-sm btn-danger py-0 px-2" onclick="confirmDeleteTiket('?delete=<?= $t['id_tiket'] ?>', '<?= htmlspecialchars(addslashes($t['nama_tiket'])) ?>')"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada tiket.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages >= 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0" data-bs-theme="dark">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&filter_event=<?= urlencode($filter_event) ?>">&laquo;</a>
                            </li>
                            <?php for($i=1; $i<=$total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter_event=<?= urlencode($filter_event) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&filter_event=<?= urlencode($filter_event) ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('id_event').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    var sisa = selected.getAttribute('data-sisa');
    var infoSpan = document.getElementById('sisa_kuota_info');
    
    if(sisa !== null && sisa !== "") {
        infoSpan.innerText = 'Sisa Kuota Venue: ' + sisa;
        infoSpan.classList.remove('d-none');
    } else {
        infoSpan.classList.add('d-none');
    }

    if(document.getElementById('form-action').value === 'add') {
        document.getElementById('kuota').value = sisa ? sisa : '';
    }
});
function editTiket(data) {
    document.getElementById('form-action').value = 'edit';
    document.getElementById('id_tiket').value = data.id_tiket;
    document.getElementById('id_event').value = data.id_event;
    
    // Trigger change to update sisa kuota badge
    var eventSelect = document.getElementById('id_event');
    var event = new Event('change');
    eventSelect.dispatchEvent(event);
    
    document.getElementById('nama_tiket').value = data.nama_tiket;
    document.getElementById('harga').value = data.harga;
    document.getElementById('kuota').value = data.kuota;
    document.getElementById('max_pembelian').value = data.max_pembelian || 5;
    document.getElementById('btn-submit').innerText = 'Update Tiket';
    document.getElementById('btn-submit').classList.replace('btn-primary', 'btn-success');
    document.getElementById('btn-cancel').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('form-action').value = 'add';
    document.getElementById('id_tiket').value = '';
    document.getElementById('id_event').value = '';
    
    // Trigger change to hide sisa kuota badge
    var eventSelect = document.getElementById('id_event');
    var event = new Event('change');
    eventSelect.dispatchEvent(event);

    document.getElementById('nama_tiket').value = '';
    document.getElementById('harga').value = '';
    document.getElementById('kuota').value = '';
    document.getElementById('max_pembelian').value = '5';
    document.getElementById('btn-submit').innerText = 'Simpan Tiket';
    document.getElementById('btn-submit').classList.replace('btn-success', 'btn-primary');
    document.getElementById('btn-cancel').classList.add('d-none');
}

function confirmDeleteTiket(url, nama) {
    Swal.fire({
        title: 'Hapus Tiket?',
        html: `Apakah Anda yakin ingin menghapus kategori tiket <strong>${nama}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        background: '#1e293b',
        color: '#f8fafc',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, Hapus'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

