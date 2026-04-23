<?php
$role_allowed = 'admin';
require_once '../includes/header.php';

// Handle Add/Edit
$msg = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $nama = $conn->real_escape_string($_POST['nama_venue']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $kapasitas = (int)$_POST['kapasitas'];

    if($_POST['action'] == 'add') {
        $conn->query("INSERT INTO venue (nama_venue, alamat, kapasitas) VALUES ('$nama', '$alamat', $kapasitas)");
        $msg = "Venue berhasil ditambahkan!";
    } elseif($_POST['action'] == 'edit') {
        $id = (int)$_POST['id_venue'];
        $conn->query("UPDATE venue SET nama_venue='$nama', alamat='$alamat', kapasitas=$kapasitas WHERE id_venue=$id");
        $msg = "Venue berhasil diupdate!";
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM venue WHERE id_venue=$id");
    $msg = "Venue berhasil dihapus!";
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_sql = "1=1";
if($search) {
    $where_sql .= " AND (nama_venue LIKE '%$search%' OR alamat LIKE '%$search%')";
}

$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$total_res = $conn->query("SELECT COUNT(*) as cnt FROM venue WHERE $where_sql");
$total_rows = $total_res->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

$venues = $conn->query("SELECT * FROM venue WHERE $where_sql ORDER BY id_venue DESC LIMIT $offset, $per_page");
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">Tambah / Edit Venue</div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-success py-2"><?= $msg ?></div>
                <?php endif; ?>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id_venue" id="id_venue">
                    
                    <div class="mb-3">
                        <label class="text-white-50">Nama Venue</label>
                        <input type="text" class="form-control" name="nama_venue" id="nama_venue" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Alamat</label>
                        <textarea class="form-control" name="alamat" id="alamat" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Kapasitas</label>
                        <input type="number" class="form-control" name="kapasitas" id="kapasitas" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="btn-submit">Simpan Venue</button>
                    <button type="button" class="btn btn-secondary w-100 mt-2 d-none" id="btn-cancel" onclick="resetForm()">Batal Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>Daftar Venue</span>
                <form action="" method="GET" class="d-flex gap-2 mb-0">
                    <select name="per_page" class="form-select form-select-sm border-secondary bg-dark text-white" style="width: auto;" onchange="this.form.submit()">
                        <option value="5" <?= $per_page == 5 ? 'selected' : '' ?>>5</option>
                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                    <input type="text" name="search" class="form-control form-control-sm border-secondary bg-transparent text-white" placeholder="Cari venue..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if($search): ?>
                        <a href="venue.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Venue</th>
                                <th>Alamat</th>
                                <th>Kapasitas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($v = $venues->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $v['id_venue'] ?></td>
                                    <td><?= htmlspecialchars($v['nama_venue']) ?></td>
                                    <td><?= htmlspecialchars($v['alamat']) ?></td>
                                    <td><?= number_format($v['kapasitas'], 0, ',', '.') ?> orang</td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editVenue(<?= json_encode($v) ?>)'><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteVenue('?delete=<?= $v['id_venue'] ?>', '<?= htmlspecialchars(addslashes($v['nama_venue'])) ?>')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
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
function editVenue(data) {
    document.getElementById('form-action').value = 'edit';
    document.getElementById('id_venue').value = data.id_venue;
    document.getElementById('nama_venue').value = data.nama_venue;
    document.getElementById('alamat').value = data.alamat;
    document.getElementById('kapasitas').value = data.kapasitas;
    document.getElementById('btn-submit').innerText = 'Update Venue';
    document.getElementById('btn-submit').classList.replace('btn-primary', 'btn-success');
    document.getElementById('btn-cancel').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('form-action').value = 'add';
    document.getElementById('id_venue').value = '';
    document.getElementById('nama_venue').value = '';
    document.getElementById('alamat').value = '';
    document.getElementById('kapasitas').value = '';
    document.getElementById('btn-submit').innerText = 'Simpan Venue';
    document.getElementById('btn-submit').classList.replace('btn-success', 'btn-primary');
    document.getElementById('btn-cancel').classList.add('d-none');
}

function confirmDeleteVenue(url, nama) {
    Swal.fire({
        title: 'Hapus Venue?',
        html: `Apakah Anda yakin ingin menghapus venue <strong>${nama}</strong>?`,
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

