<?php
require_once '../config/database.php';

if(isset($_SESSION['id_user'])) {
    if($_SESSION['role'] == 'admin') {
        header("Location: ../admin/index.php");
    } elseif($_SESSION['role'] == 'petugas') {
        header("Location: ../petugas/index.php");
    } else {
        header("Location: ../user/index.php");
    }
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM users WHERE email = '$email' LIMIT 1");
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Verifikasi password Bcrypt
        if(password_verify($password, $user['password'])) {
            // Anti-Session Fixation
            session_regenerate_id(true);

            $_SESSION['id_user'] = $user['id_user'];
            $_SESSION['nama'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            if($user['role'] == 'admin') {
                header("Location: ../admin/index.php");
            } elseif($user['role'] == 'petugas') {
                header("Location: ../petugas/index.php");
            } else {
                header("Location: ../user/index.php");
            }
            exit();
        } else {
            $error = "Password tidak sesuai!";
        }
    } else {
        $error = "Email tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - F-TIX</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a; 
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Elements */
        .bg-glow-1 {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.3) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: pulse-glow 8s infinite alternate;
        }

        .bg-glow-2 {
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 55vw;
            height: 55vw;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.2) 0%, rgba(15, 23, 42, 0) 70%);
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            animation: pulse-glow 10s infinite alternate-reverse;
        }

        @keyframes pulse-glow {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.2); opacity: 1; }
        }

        .glass-card { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 3rem 2rem;
            width: 100%;
            max-width: 420px;
            z-index: 1;
        }

        .brand-logo {
            font-weight: 800;
            font-size: 2rem;
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            margin-bottom: 2rem;
            display: block;
            text-decoration: none;
        }

        .form-control { 
            background-color: rgba(15, 23, 42, 0.6); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }

        .form-control:focus { 
            background-color: rgba(15, 23, 42, 0.8); 
            border-color: #818cf8;
            box-shadow: 0 0 0 0.25rem rgba(129, 140, 248, 0.25);
        }

        .btn-custom {
            background: linear-gradient(135deg, #4f46e5, #ec4899);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
            color: white;
        }

        /* Back button */
        .btn-back {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            color: #cbd5e1;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .btn-back:hover {
            background: rgba(79, 70, 229, 0.4);
            border-color: #818cf8;
            color: #fff;
        }
    </style>
</head>
<body>

<div class="bg-glow-1"></div>
<div class="bg-glow-2"></div>

<!-- Tombol Kembali -->
<a href="../index.php" class="btn-back">
    <i class="bi bi-arrow-left me-1"></i> Beranda
</a>

<div class="glass-card">
    <a href="../index.php" class="brand-logo"><i class="bi bi-ticket-perforated-fill me-1" style="-webkit-text-fill-color: #818cf8;"></i>F-TIX</a>
    
    <div class="text-center mb-4">
        <h4 class="fw-bold">Selamat Datang Kembali</h4>
        <p class="text-muted small">Login untuk mengakses tiket dan e-tiket Anda</p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger py-2 border-0 bg-danger bg-opacity-25 text-danger rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-floating mb-3">
            <input type="email" class="form-control" id="floatingInput" name="email" required placeholder="name@example.com">
            <label for="floatingInput" class="text-muted">Alamat Email</label>
        </div>
        <div class="form-floating mb-4 position-relative">
            <input type="password" class="form-control" id="floatingPassword" name="password" required placeholder="Password" style="padding-right: 3rem;">
            <label for="floatingPassword" class="text-muted">Password</label>
            <button type="button" class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted pe-3" onclick="togglePassword('floatingPassword', 'toggleIcon')" style="z-index: 10;">
                <i class="bi bi-eye-slash" id="toggleIcon"></i>
            </button>
        </div>
        
        <button class="btn btn-custom w-100 py-2 mb-3" type="submit">Sign In <i class="bi bi-box-arrow-in-right ms-1"></i></button>
        
        <div class="text-center mt-3">
            <span class="text-muted small">Belum punya akun? <a href="register.php" class="text-decoration-none fw-semibold" style="color: #818cf8;">Daftar Sekarang</a></span>
        </div>
    </form>
</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    }
}
</script>
</body>
</html>
