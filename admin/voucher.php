<?php
$role_allowed = 'admin';
require_once '../includes/header.php';

$msg = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $kode = strtoupper($conn->real_escape_string($_POST['kode_voucher']));
    $diskon = (float)$_POST['diskon'];
    $kuota = (int)$_POST['kuota'];
    $status = $_POST['status'];

    if($_POST['action'] == 'add') {
        $check = $conn->query("SELECT id_voucher FROM voucher WHERE kode_voucher='$kode'");
        if($check->num_rows > 0){
            $msg = "Gagal! Kode Voucher sudah ada.";
        } else {
            $conn->query("INSERT INTO voucher (kode_voucher, diskon, kuota, status) VALUES ('$kode', $diskon, $kuota, '$status')");
            $msg = "Voucher berhasil ditambahkan!";
        }
    } elseif($_POST['action'] == 'edit') {
        $id = (int)$_POST['id_voucher'];
        $conn->query("UPDATE voucher SET kode_voucher='$kode', diskon=$diskon, kuota=$kuota, status='$status' WHERE id_voucher=$id");
        $msg = "Voucher berhasil diupdate!";
    }
}

if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM voucher WHERE id_voucher=$id");
    $msg = "Voucher berhasil dihapus!";
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_sql = "1=1";
if($search) {
    $where_sql .= " AND kode_voucher LIKE '%$search%'";
}

$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$total_res  = $conn->query("SELECT COUNT(*) as cnt FROM voucher WHERE $where_sql");
$total_rows = $total_res->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

$vouchers = $conn->query("SELECT * FROM voucher WHERE $where_sql ORDER BY id_voucher DESC LIMIT $offset, $per_page");

?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">Tambah / Edit Voucher</div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-success py-2"><?= $msg ?></div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id_voucher" id="id_voucher">
                    
                    <div class="mb-3">
                        <label class="text-white-50">Kode Voucher (Unique)</label>
                        <input type="text" class="form-control" name="kode_voucher" id="kode_voucher" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Diskon (Nominal Rp)</label>
                        <input type="number" step="0.01" class="form-control" name="diskon" id="diskon" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Kuota Pemakaian</label>
                        <input type="number" class="form-control" name="kuota" id="kuota" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Status</label>
                        <select class="form-control" name="status" id="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="btn-submit">Simpan Voucher</button>
                    <button type="button" class="btn btn-secondary w-100 mt-2 d-none" id="btn-cancel" onclick="resetForm()">Batal Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>Daftar Voucher</span>
                <form action="" method="GET" class="d-flex gap-2 mb-0">
                    <select name="per_page" class="form-select form-select-sm border-secondary bg-dark text-white" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" <?= $per_page == 5 ? 'selected' : '' ?>>5</option>
                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm border-secondary bg-transparent text-white" placeholder="Cari kode..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if($search): ?>
                        <a href="voucher.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-white">
                        <thead>
                            <tr>
                                <th>Kode Voucher</th>
                                <th>Diskon (Rp)</th>
                                <th>Sisa Kuota</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($v = $vouchers->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($v['kode_voucher']) ?></span></td>
                                    <td>Rp <?= number_format($v['diskon'], 0, ',', '.') ?></td>
                                    <td><?= $v['kuota'] ?>x</td>
                                    <td>
                                        <?php if($v['status'] == 'active'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editVoucher(<?= json_encode($v) ?>)'><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteVoucher('?delete=<?= $v['id_voucher'] ?>', '<?= htmlspecialchars(addslashes($v['kode_voucher'])) ?>')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if($total_pages >= 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center mb-0" data-bs-theme="dark">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>">&laquo;</a>
                            </li>
                            <?php for($i=1; $i<=$total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $per_page ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function editVoucher(data) {
    document.getElementById('form-action').value = 'edit';
    document.getElementById('id_voucher').value = data.id_voucher;
    document.getElementById('kode_voucher').value = data.kode_voucher;
    document.getElementById('diskon').value = data.diskon;
    document.getElementById('kuota').value = data.kuota;
    document.getElementById('status').value = data.status;
    document.getElementById('btn-submit').innerText = 'Update Voucher';
    document.getElementById('btn-submit').classList.replace('btn-primary', 'btn-success');
    document.getElementById('btn-cancel').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('form-action').value = 'add';
    document.getElementById('id_voucher').value = '';
    document.getElementById('kode_voucher').value = '';
    document.getElementById('diskon').value = '';
    document.getElementById('kuota').value = '';
    document.getElementById('status').value = 'active';
    document.getElementById('btn-submit').innerText = 'Simpan Voucher';
    document.getElementById('btn-submit').classList.replace('btn-success', 'btn-primary');
    document.getElementById('btn-cancel').classList.add('d-none');
}

function confirmDeleteVoucher(url, kode) {
    Swal.fire({
        title: 'Hapus Voucher?',
        html: `Apakah Anda yakin ingin menghapus voucher <strong>${kode}</strong>?`,
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

