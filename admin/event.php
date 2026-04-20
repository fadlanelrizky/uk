<?php
$role_allowed = 'admin';
require_once '../includes/header.php';

$msg = '';
$msg_type = 'success';

// Upload folder
$upload_dir = '../uploads/events/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id_venue  = (int)$_POST['id_venue'];
    $nama      = $conn->real_escape_string($_POST['nama_event']);
    $tgl       = $conn->real_escape_string($_POST['tanggal_event']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);

    // --- Proses Upload Gambar ---
    $gambar_sql = ''; // kosong berarti tidak diubah
    if (!empty($_FILES['gambar']['name'])) {
        $allowed = ['jpg','jpeg','png','webp','gif'];
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $msg = "Format gambar tidak valid! Gunakan JPG, PNG, WEBP, atau GIF.";
            $msg_type = 'danger';
        } elseif ($_FILES['gambar']['size'] > 2 * 1024 * 1024) {
            $msg = "Ukuran gambar maksimal 2 MB!";
            $msg_type = 'danger';
        } else {
            $filename = time() . '_' . preg_replace('/\s+/', '_', basename($_FILES['gambar']['name']));
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_dir . $filename)) {
                $gambar_sql = $conn->real_escape_string($filename);
            } else {
                $msg = "Gagal mengupload gambar. Periksa izin folder uploads/events/.";
                $msg_type = 'danger';
            }
        }
    }

    if ($msg === '') { // lanjut hanya jika tidak ada error gambar
        // --- Validasi Venue pada Tanggal yang Sama ---
        $check_sql = "SELECT id_event FROM event WHERE id_venue=$id_venue AND tanggal_event='$tgl'";
        if($_POST['action'] == 'edit') {
            $id = (int)$_POST['id_event'];
            $check_sql .= " AND id_event != $id";
        }
        $check_res = $conn->query($check_sql);
        if($check_res->num_rows > 0) {
            $msg = "Error: Venue sudah digunakan untuk event lain pada tanggal tersebut!";
            $msg_type = 'danger';
        } else {
            if($_POST['action'] == 'add') {
                $gambar_val = $gambar_sql ?: 'default.jpg';
                $conn->query("INSERT INTO event (id_venue, nama_event, tanggal_event, deskripsi, gambar)
                              VALUES ($id_venue, '$nama', '$tgl', '$deskripsi', '$gambar_val')");
                $msg = "Event berhasil ditambahkan!";
            } elseif($_POST['action'] == 'edit') {
                $id = (int)$_POST['id_event'];

                // Hapus gambar lama jika ada gambar baru
                if ($gambar_sql) {
                    $row = $conn->query("SELECT gambar FROM event WHERE id_event=$id")->fetch_assoc();
                    if ($row && $row['gambar'] && $row['gambar'] !== 'default.jpg') {
                        $old_file = $upload_dir . $row['gambar'];
                        if (file_exists($old_file)) unlink($old_file);
                    }
                    $conn->query("UPDATE event SET id_venue=$id_venue, nama_event='$nama', tanggal_event='$tgl',
                                  deskripsi='$deskripsi', gambar='$gambar_sql' WHERE id_event=$id");
                } else {
                    $conn->query("UPDATE event SET id_venue=$id_venue, nama_event='$nama', tanggal_event='$tgl',
                                  deskripsi='$deskripsi' WHERE id_event=$id");
                }
                $msg = "Event berhasil diupdate!";
            }
        }
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Hapus file gambar
    $row = $conn->query("SELECT gambar FROM event WHERE id_event=$id")->fetch_assoc();
    if ($row && $row['gambar'] && $row['gambar'] !== 'default.jpg') {
        $del_file = $upload_dir . $row['gambar'];
        if (file_exists($del_file)) unlink($del_file);
    }
    $conn->query("DELETE FROM event WHERE id_event=$id");
    $msg = "Event berhasil dihapus!";
    $msg_type = 'success';
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_sql = "1=1";
if($search) {
    $where_sql .= " AND (e.nama_event LIKE '%$search%' OR v.nama_venue LIKE '%$search%')";
}

$per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$total_res = $conn->query("SELECT COUNT(*) as cnt FROM event e JOIN venue v ON e.id_venue = v.id_venue WHERE $where_sql");
$total_rows = $total_res->fetch_assoc()['cnt'];
$total_pages = ceil($total_rows / $per_page);

$events = $conn->query("SELECT e.*, v.nama_venue FROM event e JOIN venue v ON e.id_venue = v.id_venue WHERE $where_sql ORDER BY e.id_event DESC LIMIT $offset, $per_page");
$venues = $conn->query("SELECT * FROM venue ORDER BY nama_venue ASC");
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">Tambah / Edit Event</div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-<?= $msg_type ?> py-2"><?= $msg ?></div>
                <?php endif; ?>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add" id="form-action">
                    <input type="hidden" name="id_event" id="id_event">
                    
                    <div class="mb-3">
                        <label class="text-white-50">Nama Event</label>
                        <input type="text" class="form-control" name="nama_event" id="nama_event" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Venue</label>
                        <select class="form-control" name="id_venue" id="id_venue_select" required>
                            <option value="">-- Pilih Venue --</option>
                            <?php while($v = $venues->fetch_assoc()): ?>
                                <option value="<?= $v['id_venue'] ?>"><?= htmlspecialchars($v['nama_venue']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Tanggal Event</label>
                        <input type="date" class="form-control" name="tanggal_event" id="tanggal_event" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="deskripsi" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="text-white-50">Gambar Event</label>
                        <input type="file" class="form-control" name="gambar" id="gambar" accept="image/*" onchange="previewGambar(this)">
                        <div class="form-text text-white-50">Format: JPG, PNG, WEBP, GIF. Maks 2 MB.</div>
                        <div id="preview-container" class="mt-2 d-none">
                            <img id="gambar-preview" src="" alt="Preview" class="img-fluid rounded" style="max-height:120px; object-fit:cover;">
                        </div>
                        <div id="current-gambar" class="mt-2 d-none">
                            <small class="text-white-50">Gambar saat ini:</small><br>
                            <img id="current-gambar-img" src="" alt="Gambar saat ini" class="img-fluid rounded mt-1" style="max-height:120px; object-fit:cover;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="btn-submit">Simpan Event</button>
                    <button type="button" class="btn btn-secondary w-100 mt-2 d-none" id="btn-cancel" onclick="resetForm()">Batal Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>Daftar Event</span>
                <form action="" method="GET" class="d-flex gap-2 mb-0">
                    <input type="text" name="search" class="form-control form-control-sm border-secondary bg-transparent text-white" placeholder="Cari event..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if($search): ?>
                        <a href="event.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered text-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gambar</th>
                                <th>Nama Event</th>
                                <th>Venue</th>
                                <th>Tanggal</th>
                                <th class="text-center">Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($e = $events->fetch_assoc()): ?>
                                <?php
                                    $img_path  = '../uploads/events/' . $e['gambar'];
                                    $img_src   = (file_exists($img_path) && $e['gambar'] !== 'default.jpg')
                                                 ? 'uploads/events/' . htmlspecialchars($e['gambar'])
                                                 : 'https://placehold.co/60x40/1a1a2e/ffffff?text=No+Img';
                                    $is_past   = strtotime($e['tanggal_event']) < strtotime('today');
                                ?>
                                <tr class="<?= $is_past ? 'opacity-75' : '' ?>">
                                    <td><?= $e['id_event'] ?></td>
                                    <td><img src="../<?= $img_src ?>" alt="<?= htmlspecialchars($e['nama_event']) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:4px;"></td>
                                    <td><?= htmlspecialchars($e['nama_event']) ?></td>
                                    <td><?= htmlspecialchars($e['nama_venue']) ?></td>
                                    <td><?= date('d M Y', strtotime($e['tanggal_event'])) ?></td>
                                    <td class="text-center">
                                        <?php if($is_past): ?>
                                            <span class="badge" style="background:#374151;color:#9ca3af;font-size:0.72rem;"><i class="bi bi-clock-history me-1"></i>Sudah Berlalu</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);font-size:0.72rem;"><i class="bi bi-calendar-check me-1"></i>Akan Datang</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editEvent(<?= json_encode($e) ?>)'><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete('?delete=<?= $e['id_event'] ?>', '<?= htmlspecialchars(addslashes($e['nama_event'])) ?>')"><i class="bi bi-trash"></i></button>
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
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">&laquo;</a>
                            </li>
                            <?php for($i=1; $i<=$total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary text-white' : 'bg-dark text-white border-secondary' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-white border-secondary" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_UPLOAD_URL = '../uploads/events/';

function previewGambar(input) {
    const preview = document.getElementById('gambar-preview');
    const container = document.getElementById('preview-container');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            container.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function editEvent(data) {
    document.getElementById('form-action').value = 'edit';
    document.getElementById('id_event').value = data.id_event;
    document.getElementById('nama_event').value = data.nama_event;
    document.getElementById('id_venue_select').value = data.id_venue;
    document.getElementById('tanggal_event').value = data.tanggal_event;
    document.getElementById('deskripsi').value = data.deskripsi;
    document.getElementById('btn-submit').innerText = 'Update Event';
    document.getElementById('btn-submit').classList.replace('btn-primary', 'btn-success');
    document.getElementById('btn-cancel').classList.remove('d-none');

    // Tampilkan gambar saat ini
    const currentDiv = document.getElementById('current-gambar');
    const currentImg = document.getElementById('current-gambar-img');
    if (data.gambar && data.gambar !== 'default.jpg') {
        currentImg.src = BASE_UPLOAD_URL + data.gambar;
        currentDiv.classList.remove('d-none');
    } else {
        currentDiv.classList.add('d-none');
    }
    document.getElementById('preview-container').classList.add('d-none');

    // Scroll ke form
    document.querySelector('.card').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-action').value = 'add';
    document.getElementById('id_event').value = '';
    document.getElementById('nama_event').value = '';
    document.getElementById('id_venue_select').value = '';
    document.getElementById('tanggal_event').value = '';
    document.getElementById('deskripsi').value = '';
    document.getElementById('gambar').value = '';
    document.getElementById('btn-submit').innerText = 'Simpan Event';
    document.getElementById('btn-submit').classList.replace('btn-success', 'btn-primary');
    document.getElementById('btn-cancel').classList.add('d-none');
    document.getElementById('preview-container').classList.add('d-none');
    document.getElementById('current-gambar').classList.add('d-none');
}

function confirmDelete(url, nama) {
    Swal.fire({
        title: 'Hapus Event?',
        html: `Apakah Anda yakin ingin menghapus event <strong>${nama}</strong> beserta seluruh laporannya?`,
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

