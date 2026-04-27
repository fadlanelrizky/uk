<?php
$role_allowed = 'user';
require_once '../includes/header.php';

$search_query = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$where_sql = "e.tanggal_event >= CURDATE()";
if (!empty($search_query)) {
    $where_sql .= " AND (e.nama_event LIKE '%$search_query%' OR v.nama_venue LIKE '%$search_query%' OR v.alamat LIKE '%$search_query%')";
}

$events = $conn->query("
    SELECT e.*, v.nama_venue, v.alamat 
    FROM event e 
    JOIN venue v ON e.id_venue = v.id_venue 
    WHERE $where_sql
    ORDER BY e.tanggal_event ASC
");
?>

<!-- Hero -->
<div class="hero text-center">
    <div class="container">
        <div class="badge mb-3 px-3 py-2" style="background:rgba(129,140,248,0.15);border:1px solid rgba(129,140,248,0.3);border-radius:50px;color:#818cf8;font-size:0.78rem;font-weight:600;letter-spacing:1px;">
            🎵 KONSER &amp; EVENT TERBAIK
        </div>
        <h1 class="display-5 fw-bold mb-3" style="background:linear-gradient(to right,#f1f5f9,#818cf8,#c084fc);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;">
            Temukan Pengalaman<br>Konser Terbaikmu.
        </h1>
        <p class="lead mb-0" style="color:#94a3b8;max-width:520px;margin:0 auto;">
            Eksplor acara, pilih kursimu, bayar, dan dapatkan tiket digitalmu secara instan.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row align-items-end mb-4 gy-3">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <h3 class="fw-bold mb-1">Event Mendatang</h3>
                    <p class="text-muted small mb-0">Jangan sampai kehabisan tiket!</p>
                </div>
                <span class="badge px-3 py-2 d-none d-sm-inline-block" style="background:rgba(129,140,248,0.15);border:1px solid rgba(129,140,248,0.2);color:#818cf8;border-radius:50px;">
                    <?= $events->num_rows ?> Event
                </span>
            </div>
        </div>
        <div class="col-md-6">
            <form action="index.php" method="GET" class="d-flex gap-2 justify-content-md-end">
                <input type="text" name="q" class="form-control bg-dark border-secondary text-white shadow-none" placeholder="Cari event atau lokasi..." value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" style="max-width:300px; border-radius: 50px;">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php if($events->num_rows > 0): ?>
            <?php while($e = $events->fetch_assoc()): ?>
                <?php
                    $id_event  = $e['id_event'];
                    $tiket_info = $conn->query("SELECT MIN(harga) as min_price, SUM(kuota) as total_kuota FROM tiket WHERE id_event=$id_event")->fetch_assoc();
                    $harga_min = $tiket_info['min_price'];
                    $is_sold_out = ($tiket_info['total_kuota'] !== null && $tiket_info['total_kuota'] <= 0);
                    $img_path  = '../uploads/events/' . $e['gambar'];
                    $img_src   = (isset($e['gambar']) && $e['gambar'] && $e['gambar'] !== 'default.jpg' && file_exists($img_path))
                                 ? '../uploads/events/' . htmlspecialchars($e['gambar'])
                                 : null;
                ?>
                <div class="col">
                    <div class="card h-100" style="overflow:hidden;">
                        <!-- Image -->
                        <div style="height:200px;overflow:hidden;position:relative;">
                            <?php if($is_sold_out): ?>
                                <div style="position:absolute;top:12px;left:12px;background:rgba(239,68,68,0.9);backdrop-filter:blur(4px);padding:4px 10px;border-radius:8px;color:white;font-weight:bold;font-size:0.8rem;z-index:2;letter-spacing:1px;box-shadow:0 4px 10px rgba(239,68,68,0.3);">
                                    <i class="bi bi-x-circle-fill me-1"></i>SOLD OUT
                                </div>
                            <?php endif; ?>
                            <?php if($img_src): ?>
                                <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($e['nama_event']) ?>"
                                     style="width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease;">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(79,70,229,0.2),rgba(236,72,153,0.2));">
                                    <i class="bi bi-music-note-beamed" style="font-size:4rem;color:rgba(129,140,248,0.4);"></i>
                                </div>
                            <?php endif; ?>
                            <!-- Date badge -->
                            <div style="position:absolute;top:12px;right:12px;background:rgba(15,23,42,0.85);backdrop-filter:blur(10px);padding:4px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.1);">
                                <small class="fw-semibold" style="color:#818cf8;"><?= date('d M Y', strtotime($e['tanggal_event'])) ?></small>
                            </div>
                        </div>

                        <div class="card-body d-flex flex-column p-4">
                            <h5 class="card-title fw-bold mb-2 text-truncate" title="<?= htmlspecialchars($e['nama_event']) ?>"><?= htmlspecialchars($e['nama_event']) ?></h5>
                            <p class="small mb-3" style="color:#94a3b8;">
                                <i class="bi bi-geo-alt me-1" style="color:#c084fc;"></i>
                                <?= htmlspecialchars($e['nama_venue']) ?>
                            </p>
                            <?php if($e['deskripsi']): ?>
                            <p class="small mb-3" style="color:#64748b;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                <?= htmlspecialchars($e['deskripsi']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="mt-auto pt-3 d-flex justify-content-between align-items-center" style="border-top:1px solid rgba(255,255,255,0.06);">
                                <div>
                                    <span class="small" style="color:#64748b;">Mulai dari</span><br>
                                    <span class="fw-bold" style="color:#818cf8;font-size:1rem;">
                                        Rp <?= $harga_min ? number_format($harga_min, 0, ',', '.') : 'N/A' ?>
                                    </span>
                                </div>
                                <a href="detail_event.php?id=<?= $e['id_event'] ?>" class="btn btn-primary btn-sm px-4">
                                    Lihat Event<i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(129,140,248,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <i class="bi bi-search" style="font-size:2.5rem;color:#818cf8;"></i>
                </div>
                <h5 class="fw-semibold mb-2">Event Tidak Ditemukan</h5>
                <p class="text-muted small">Silakan coba dengan kata kunci pencarian yang lain.</p>
                <?php if(!empty($search_query)): ?>
                    <a href="index.php" class="btn btn-outline-primary btn-sm mt-3 px-4 rounded-pill">Lihat Semua Event</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card:hover img { transform: scale(1.05); }
</style>

<?php require_once '../includes/footer.php'; ?>

