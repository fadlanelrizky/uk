<?php
require_once 'config/database.php';

// Get Current Data Event
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
    LIMIT 6
");

$isLoggedIn = isset($_SESSION['role']);
$role = $isLoggedIn ? $_SESSION['role'] : null;
$dashboardLink = '';
if($role == 'admin') $dashboardLink = 'admin/index.php';
elseif($role == 'user') $dashboardLink = 'user/index.php';
elseif($role == 'petugas') $dashboardLink = 'petugas/index.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>F-TIX - Konser & Hiburan Terbaik</title>
    
    <!-- SEO Optimization -->
    <meta name="description" content="Temukan dan beli tiket konser musik, festival, dan event hiburan terbaik di F-TIX dengan harga terbaik dan aman.">
    <meta name="keywords" content="tiket konser, beli tiket, event musik, festival, tiket online">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --secondary-color: #ec4899;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Glassmorphism Navbar */
        .navbar {
            background: rgba(15, 23, 42, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            color: var(--text-main) !important;
            font-weight: 500;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .nav-link:hover {
            opacity: 1;
        }

        /* Custom Buttons */
        .btn-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
            color: white;
        }

        .btn-outline-custom {
            border: 2px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* Hero Section */
        .hero {
            position: relative;
            padding: 220px 0 150px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(rgba(15, 23, 42, 0.4), rgba(15, 23, 42, 1)), url('img/landing_page.jpg') center/cover no-repeat;
            background-attachment: fixed;
            text-align: center;
        }

        .hero h1 {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -1px;
        }

        .text-gradient {
            background: linear-gradient(to right, #818cf8, #c084fc, #f472b6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Event Cards */
        .event-card {
            background: var(--dark-card);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(129, 140, 248, 0.3);
        }

        .card-img-placeholder {
            height: 220px;
            background: linear-gradient(45deg, #1e293b, #0f172a);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .card-img-placeholder::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(30,41,59,1) 100%);
        }

        .card-img-placeholder i {
            font-size: 5rem;
            color: rgba(255,255,255,0.05);
            z-index: 1;
            transition: transform 0.4s ease;
        }

        .event-card:hover .card-img-placeholder i {
            transform: scale(1.1) rotate(5deg);
            color: rgba(129, 140, 248, 0.2);
        }

        .event-date-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            z-index: 2;
        }

        .event-date-badge .day {
            font-size: 1.2rem;
            font-weight: 800;
            display: block;
            line-height: 1;
            color: #818cf8;
        }

        .event-date-badge .month {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .event-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .event-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .price-tag {
            font-size: 1.1rem;
            font-weight: 700;
            color: #f472b6;
        }

        /* Features Section */
        .feature-box {
            background: var(--dark-card);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .feature-box:hover {
            transform: translateY(-5px);
            background: rgba(30, 41, 59, 0.8);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #818cf8;
            margin-bottom: 1.5rem;
        }

        /* Footer */
        .footer {
            background: #0b1120;
            padding: 4rem 0 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero { padding: 120px 0 60px; min-height: auto; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-ticket-perforated-fill me-1" style="-webkit-text-fill-color: #818cf8;"></i>F-TIX</a>
            <button class="navbar-toggler border-0 shadow-none d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list text-white fs-1"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="#beranda">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#event">Event Mendatang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#keunggulan">Fitur</a>
                    </li>
                </ul>
                <div class="d-flex gap-3">
                    <?php if($isLoggedIn): ?>
                        <a href="<?= $dashboardLink ?>" class="btn btn-custom">Dashboard</a>
                        <a href="auth/logout.php" class="btn btn-outline-custom border-danger text-danger hover-bg-danger">Logout</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-outline-custom">Log In</a>
                        <a href="auth/register.php" class="btn btn-custom">Daftar Sekarang</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="beranda" class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 px-4 py-2 rounded-pill mb-4 fw-medium" style="backdrop-filter: blur(10px); letter-spacing: 0.5px;">
                        Tingkatkan Pengalaman Konsermu 🔥
                    </span>
                    <h1 class="display-3 fw-bold mb-4 text-white" style="letter-spacing: -1px; text-shadow: 0 4px 20px rgba(0,0,0,0.5);">Temukan Tiket Konser <br>Favoritmu Dengan Mudah</h1>
                    <p class="lead text-light mb-5 px-lg-5" style="opacity: 0.9; font-size: 1.2rem; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">Platform pembelian tiket konser musik dan festival paling eksklusif. Dapatkan akses cepat ke event artis favoritmu tanpa antrean panjang.</p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center">
                        <a href="#event" class="btn btn-custom btn-lg px-5 shadow-lg">Jelajahi Event</a>
                        <?php if(!$isLoggedIn): ?>
                            <a href="auth/register.php" class="btn btn-outline-custom btn-lg px-5" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(5px);">Buat Akun</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Events -->
    <section id="event" class="container py-5">
        <div class="row align-items-end mb-5 gy-4">
            <div class="col-lg-6">
                <h2 class="fw-bold display-6 mb-2">Event <span class="text-gradient">Mendatang</span></h2>
                <p class="text-white mb-0">Jangan lewatkan konser artis favoritmu</p>
            </div>
            <div class="col-lg-6">
                <form action="index.php#event" method="GET" class="d-flex gap-2 justify-content-lg-end">
                    <input type="text" name="q" class="form-control bg-dark border-secondary text-white shadow-none" placeholder="Cari event atau lokasi..." value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" style="max-width:300px; border-radius: 50px;">
                    <button type="submit" class="btn btn-custom rounded-pill px-4"><i class="bi bi-search"></i></button>
                    <?php if($events->num_rows > 0): ?>
                        <a href="<?= $isLoggedIn ? $dashboardLink : 'auth/login.php' ?>" class="btn btn-outline-custom d-none d-md-block">Lihat Semua</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if($events->num_rows > 0): ?>
                <?php while($e = $events->fetch_assoc()): ?>
                    <?php 
                        // Get minimum price and total quota for label
                        $id_event = $e['id_event'];
                        $tiket_info = $conn->query("SELECT MIN(harga) as min_price, SUM(kuota) as total_kuota FROM tiket WHERE id_event=$id_event")->fetch_assoc();
                        $harga_min = $tiket_info['min_price'];
                        $is_sold_out = ($tiket_info['total_kuota'] !== null && $tiket_info['total_kuota'] <= 0);
                    ?>
                    <div class="col">
                        <div class="event-card" onclick="window.location.href='<?= $isLoggedIn ? 'user/detail_event.php?id='.$e['id_event'] : 'auth/login.php' ?>'">
                            <div class="card-img-placeholder" style="position:relative;">
                                <?php if($is_sold_out): ?>
                                    <div style="position:absolute;top:15px;left:15px;background:rgba(239,68,68,0.9);backdrop-filter:blur(4px);padding:6px 12px;border-radius:8px;color:white;font-weight:bold;font-size:0.8rem;z-index:2;letter-spacing:1px;box-shadow:0 4px 10px rgba(239,68,68,0.3);">
                                        <i class="bi bi-x-circle-fill me-1"></i>SOLD OUT
                                    </div>
                                <?php endif; ?>
                                <?php
                                    $img_path = 'uploads/events/' . $e['gambar'];
                                    $has_img  = !empty($e['gambar']) && $e['gambar'] !== 'default.jpg' && file_exists($img_path);
                                ?>
                                <?php if($has_img): ?>
                                    <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($e['nama_event']) ?>"
                                         style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;z-index:0;">
                                    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,0.1) 0%,rgba(15,23,42,0.85) 100%);z-index:1;"></div>
                                <?php else: ?>
                                    <i class="bi bi-music-note-list" style="z-index:1;"></i>
                                <?php endif; ?>
                                <div class="event-date-badge" style="z-index:2;">
                                    <span class="day"><?= date('d', strtotime($e['tanggal_event'])) ?></span>
                                    <span class="month"><?= date('M', strtotime($e['tanggal_event'])) ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h3 class="event-title text-truncate text-white"><?= htmlspecialchars($e['nama_event']) ?></h3>
                                <div class="event-meta">
                                    <i class="bi bi-geo-alt text-primary"></i> 
                                    <span class="text-truncate"><?= htmlspecialchars($e['nama_venue']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div>
                                        <small class="d-block" style="color:#94a3b8;">Mulai dari</small>
                                        <div class="price-tag">Rp <?= $harga_min ? number_format($harga_min, 0, ',', '.') : 'TBA' ?></div>
                                    </div>
                                    <button class="btn btn-custom btn-sm px-3 rounded-pill"><i class="bi bi-ticket-perforated"></i> Beli</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 py-5 text-center">
                    <div class="p-5 border border-secondary border-opacity-25 rounded-4 bg-dark bg-opacity-50 d-inline-block">
                        <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                        <h4 class="text-white">Event tidak ditemukan</h4>
                        <p class="text-muted">Coba cari dengan kata kunci lain atau pantau terus update kami.</p>
                        <?php if(!empty($search_query)): ?>
                            <a href="index.php#event" class="btn btn-outline-custom mt-2">Lihat Semua Event</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if($events->num_rows > 0): ?>
            <div class="text-center mt-5 d-md-none">
                <a href="<?= $isLoggedIn ? $dashboardLink : 'auth/login.php' ?>" class="btn btn-outline-custom">Lihat Semua Event</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- Features -->
    <section id="keunggulan" class="container py-5 mt-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold display-6 mb-2">Kenapa Memilih <span class="text-gradient">F-TIX</span></h2>
            <p style="color:#cbd5e1;">Keunggulan platform kami untuk pengalaman terbaikmu</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="color:#f1f5f9;">100% Aman &amp; Resmi</h4>
                    <p style="color:#cbd5e1;" class="mb-0">Tiket dijamin keasliannya karena terintegrasi langsung dengan promotor resmi.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon" style="color: #ec4899; background: rgba(236, 72, 153, 0.1);">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="color:#f1f5f9;">Sistem Antrean Anti-Lag</h4>
                    <p style="color:#cbd5e1;" class="mb-0">Server cloud canggih memastikan pengalaman war tiket yang lancar tanpa kendala.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <div class="feature-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1);">
                        <i class="bi bi-qr-code-scan"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="color:#f1f5f9;">E-Tiket Instant</h4>
                    <p style="color:#cbd5e1;" class="mb-0">Dapatkan QR Code tiket sektika setelah pembayaran sukses. Praktis &amp; ramah lingkungan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4">
                    <a class="navbar-brand fs-3 mb-3 d-inline-block" href="index.php"><i class="bi bi-ticket-perforated-fill me-1" style="-webkit-text-fill-color: #818cf8;"></i>F-TIX</a>
                    <p style="color:#94a3b8;" class="pe-lg-4">Destinasi utama untuk menemukan dan mengamankan tiket konser artis dan festival favorit Anda di seluruh Indonesia.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-white text-decoration-none fs-5 transition: color 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-white)'"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white text-decoration-none fs-5 transition: color 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-white)'"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white text-decoration-none fs-5 transition: color 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-white)'"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="text-white fw-bold mb-4">Eksplor</h5>
                    <ul class="list-unstyled d-flex flex-column gap-2 text-muted">
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Konser</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Festival</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Hiburan</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Venue</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="text-white fw-bold mb-4">Dukungan</h5>
                    <ul class="list-unstyled d-flex flex-column gap-2 text-muted">
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Pusat Bantuan</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Syarat & Ketentuan</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Kebijakan Privasi</a></li>
                        <li><a href="#" class="text-muted text-decoration-none nav-link p-0">Hubungi Kami</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="text-white fw-bold mb-4">Newsletter</h5>
                    <p class="text-white">Dapatkan info pre-sale eksklusif dan update konser terbaru.</p>
                    <form class="d-flex gap-2">
                        <input type="email" class="form-control bg-dark border-secondary text-white shadow-none" placeholder="Alamat Email" style="border-radius: 50px;">
                        <button class="btn btn-custom px-4" type="button">Subscribe</button>
                    </form>
                </div>
            </div>
            <div class="border-top border-secondary border-opacity-25 mt-5 pt-4 text-center text-muted small">
                <p class="mb-0">&copy; <?= date('Y') ?> F-TIX. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '10px 0';
                navbar.style.background = 'rgba(15, 23, 42, 0.95) !important';
                navbar.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
            } else {
                navbar.style.padding = '20px 0';
                navbar.style.background = 'rgba(15, 23, 42, 0.5) !important';
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
