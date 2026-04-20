<?php
/**
 * MASTER HEADER - TIX EVENT
 * Menangani: Authentication, Role Check, Dynamic Sidebar/Navbar, dan Assets.
 */
require_once __DIR__ . '/../config/database.php';

// 1. Cek Login Global
if (!isset($_SESSION['id_user'])) {
    header("Location: " . base_url('auth/login.php'));
    exit();
}

// 2. Cek Role (Jika halaman menentukan $role_allowed)
if (isset($role_allowed)) {
    if ($_SESSION['role'] !== $role_allowed) {
        // Jika role tidak sesuai, tendang ke dashboard masing-masing atau login
        if ($_SESSION['role'] === 'admin') header("Location: " . base_url('admin/index.php'));
        elseif ($_SESSION['role'] === 'petugas') header("Location: " . base_url('petugas/index.php'));
        else header("Location: " . base_url('user/index.php'));
        exit();
    }
}

$role     = $_SESSION['role'];
$cur_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . " - " : "" ?>F-TIX</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    function confirmLogout(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Keluar sistem?',
            text: 'Sesi Anda akan segera diakhiri.',
            icon: 'warning',
            showCancelButton: true,
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "<?= base_url('auth/logout.php') ?>";
            }
        });
    }
    </script>

    <style>
        :root {
            --bg-base:      #0f172a;
            --bg-surface:   rgba(30,41,59,0.85);
            --bg-card:      rgba(30,41,59,0.7);
            --border:       rgba(255,255,255,0.08);
            --accent-1:     #818cf8;
            --accent-2:     #c084fc;
            --accent-grad:  linear-gradient(135deg,#4f46e5,#ec4899);
            --text-muted:   #94a3b8;
            --sidebar-w:    260px;
            --sidebar-w-sm: 70px;
            --transition:   0.3s cubic-bezier(.4,0,.2,1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-base);
            color: #f1f5f9;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Ambient Glow */
        body::before {
            content: ''; position: fixed; top: -20%; left: -15%;
            width: 55vw; height: 55vw;
            background: radial-gradient(circle, rgba(79,70,229,0.15) 0%, transparent 70%);
            border-radius: 50%; filter: blur(80px); pointer-events: none; z-index: 0;
            animation: pulse-glow 8s infinite alternate;
        }
        @keyframes pulse-glow { 0% { transform: scale(1); opacity: 0.6; } 100% { transform: scale(1.1); opacity: 1; } }

        /* SIDEBAR STYLES (Admin & Petugas) */
        .app-wrapper { display: flex; min-height: 100vh; z-index: 1; position: relative; }
        .sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            position: fixed; top: 0; left: 0;
            background: rgba(15,23,42,0.9);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            transition: width var(--transition), left var(--transition);
            z-index: 1050;
            display: flex; flex-direction: column;
            overflow: visible; /* Allow toggle button to be visible */
        }
        .sidebar.collapsed { width: var(--sidebar-w-sm); }

        .sidebar-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
            overflow: hidden;
            white-space: nowrap;
        }
        .sidebar.collapsed .sidebar-top {
            padding: 1.25rem 0;
            justify-content: center;
        }
        
        .sidebar-brand {
            font-weight: 800; font-size: 1.5rem;
            background: linear-gradient(to right, var(--accent-1), var(--accent-2));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
            text-decoration: none; margin: 0; padding: 0;
            display: flex; align-items: center;
        }
        .sidebar.collapsed .sidebar-brand { display: none; }

        .sidebar .nav-link {
            padding: 0.8rem 1.5rem;
            color: var(--text-muted);
            display: flex; align-items: center; gap: 15px;
            transition: all 0.2s; white-space: nowrap;
            text-decoration: none; border-radius: 12px; margin: 2px 10px;
        }
        .sidebar .nav-link i { font-size: 1.2rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff; background: rgba(129,140,248,0.1);
        }
        .sidebar .nav-link.active {
            background: var(--accent-grad);
            box-shadow: 0 4px 15px rgba(79,70,229,0.3);
        }
        .sidebar .nav-link.text-danger:hover { background: rgba(239,68,68,0.1); }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-w);
            padding: 2rem;
            transition: margin-left var(--transition);
        }
        .main-content.expanded { margin-left: var(--sidebar-w-sm); }

        /* SIDEBAR TOGGLE */
        .toggle-btn {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1.4rem;
            color: var(--text-muted);
            transition: all 0.3s ease;
            flex-shrink: 0; z-index: 1060;
        }
        .toggle-btn:hover { color: #fff; background: rgba(129,140,248,0.15); border-color: rgba(129,140,248,0.4); }

        .nav-list {
            overflow-x: hidden;
            overflow-y: auto;
            flex-grow: 1;
            padding-bottom: 2rem;
        }
        .nav-list::-webkit-scrollbar { width: 4px; }
        .nav-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        /* USER NAVBAR STYLES */
        .navbar-user {
            background: rgba(15,23,42,0.8) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
           
        }

        /* REUSABLE UI */
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: var(--accent-grad); border: none; font-weight: 600;
            padding: 0.6rem 1.5rem; border-radius: 10px;
        }
        
        /* Tooltip simple */
        .sidebar.collapsed .nav-text { display: none; }

        @media (max-width: 768px) {
            .sidebar { left: -100%; width: 280px !important; }
            .sidebar.mobile-show { left: 0; box-shadow: 20px 0 50px rgba(0,0,0,0.5); }
            .main-content { margin-left: 0 !important; padding-top: 5rem; }
            .toggle-btn { display: none; } /* Hide desktop toggle on mobile */
            .sidebar-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
                z-index: 1040; display: none;
            }
            .sidebar-overlay.show { display: block; }
        }
        
        .text-gradient {
            background: var(--accent-grad);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Prevent transitions on page load to avoid flicker */
        .preload, .preload * {
            transition: none !important;
        }
    </style>
</head>
<body class="preload">

<?php if ($role === 'user'): ?>
    <!-- TOP NAVBAR FOR USER -->
    <nav class="navbar navbar-expand-lg navbar-user sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-transparent bg-clip-text" 
               style="background-image: var(--accent-grad); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;" 
               href="<?= base_url('user/index.php') ?>"><i class="bi bi-ticket-perforated-fill me-1" style="-webkit-text-fill-color: #818cf8;"></i>F-TIX</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navUser">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navUser">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= $cur_page=='index.php'?'active text-white':'' ?>" href="<?= base_url('user/index.php') ?>">Cari Tiket</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $cur_page=='my_tickets.php'?'active text-white':'' ?>" href="<?= base_url('user/my_tickets.php') ?>">Tiket Saya</a>
                    </li>
                </ul>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5" style="color:var(--accent-1)"></i>
                        <span><?= $_SESSION['nama'] ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 bg-dark-subtle mt-2 p-2" style="border-radius:12px;">
                        <li><a class="dropdown-item rounded-3" href="#" onclick="confirmLogout(event)"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <main class="py-4">
<?php else: ?>
    <!-- SIDEBAR FOR ADMIN & PETUGAS -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    <!-- Mobile Header -->
    <div class="mobile-header d-md-none bg-dark border-bottom border-white border-opacity-10 p-3 d-flex justify-content-between align-items-center fixed-top" style="z-index: 1045;">
        <span class="fw-bold text-gradient">F-TIX</span>
        <button class="btn btn-link text-white p-0 shadow-none" id="mobile-sidebar-toggle">
            <i class="bi bi-list fs-1"></i>
        </button>
    </div>

    <div class="app-wrapper">
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-top">
                <a href="#" class="sidebar-brand">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent-1);"></i>F-TIX
                </a>
                <div class="toggle-btn" id="toggle-sidebar">
                    <i class="bi bi-list" id="toggle-icon"></i>
                </div>
            </div>
            <div class="nav-list">
                <?php if ($role === 'admin'): ?>
                    <a href="index.php" class="nav-link <?= $cur_page=='index.php'?'active':'' ?>">
                        <i class="bi bi-speedometer2"></i> <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="venue.php" class="nav-link <?= $cur_page=='venue.php'?'active':'' ?>">
                        <i class="bi bi-geo-alt"></i> <span class="nav-text">Venue</span>
                    </a>
                    <a href="event.php" class="nav-link <?= $cur_page=='event.php'?'active':'' ?>">
                        <i class="bi bi-calendar-event"></i> <span class="nav-text">Event</span>
                    </a>
                    <a href="tiket.php" class="nav-link <?= $cur_page=='tiket.php'?'active':'' ?>">
                        <i class="bi bi-ticket-perforated"></i> <span class="nav-text">Tiket</span>
                    </a>
                    <a href="voucher.php" class="nav-link <?= $cur_page=='voucher.php'?'active':'' ?>">
                        <i class="bi bi-tags"></i> <span class="nav-text">Voucher</span>
                    </a>
                    <a href="orders.php" class="nav-link <?= $cur_page=='orders.php'?'active':'' ?>">
                        <i class="bi bi-cart-check"></i> <span class="nav-text">Orders</span>
                    </a>
                <?php elseif ($role === 'petugas'): ?>
                    <a href="index.php" class="nav-link <?= $cur_page=='index.php'?'active':'' ?>">
                        <i class="bi bi-qr-code-scan"></i> <span class="nav-text">Gate Scanner</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="sidebar-footer p-3 border-top border-secondary-subtle">
                <a href="#" onclick="confirmLogout(event)" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-left"></i> <span class="nav-text">Logout</span>
                </a>
            </div>
        </nav>
        
        <main class="main-content" id="main-content">
        <!-- Script Anti-Flash (mencegah kedip saat load halaman) -->
        <script>
            if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 768) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('main-content').classList.add('expanded');
            }
        </script>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold m-0"><?= strtoupper($role) ?> PANEL</h4>
                <div class="px-3 py-2 rounded-pill d-flex align-items-center gap-2" style="background:var(--bg-card);border:1px solid var(--border)">
                    <i class="bi bi-person-circle" style="color:var(--accent-1)"></i>
                    <small><?= $_SESSION['nama'] ?></small>
                </div>
            </div>
<?php endif; ?>
