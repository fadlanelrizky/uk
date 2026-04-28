<?php
require_once '../config/database.php';

if(!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$email = $_SESSION['reset_email'];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "UPDATE users SET password = '$hashed' WHERE email = '$email'";
        if($conn->query($query)) {
            unset($_SESSION['reset_email']);
            $_SESSION['success'] = "Password berhasil diubah. Silakan login dengan password baru.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Gagal mengatur ulang password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - F-TIX</title>
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
    </style>
</head>
<body>

<div class="bg-glow-1"></div>
<div class="bg-glow-2"></div>

<div class="glass-card">
    <a href="../index.php" class="brand-logo"><i class="bi bi-ticket-perforated-fill me-1" style="-webkit-text-fill-color: #818cf8;"></i>F-TIX</a>
    
    <div class="text-center mb-4">
        <h4 class="fw-bold">Buat Password Baru</h4>
        <p class="text-muted small">Untuk email <strong><?= htmlspecialchars($email) ?></strong></p>
    </div>
    
    <?php if($error): ?>
        <div class="alert alert-danger py-2 border-0 bg-danger bg-opacity-25 text-danger rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-floating mb-3 position-relative">
            <input type="password" class="form-control" id="floatingPassword" name="password" required placeholder="Password Baru" style="padding-right: 3rem;" minlength="6">
            <label for="floatingPassword" class="text-muted">Password Baru</label>
            <button type="button" class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted pe-3" onclick="togglePassword('floatingPassword', 'toggleIcon1')" style="z-index: 10;">
                <i class="bi bi-eye-slash" id="toggleIcon1"></i>
            </button>
        </div>
        
        <div class="form-floating mb-4 position-relative">
            <input type="password" class="form-control" id="floatingConfirmPassword" name="confirm_password" required placeholder="Konfirmasi Password" style="padding-right: 3rem;" minlength="6">
            <label for="floatingConfirmPassword" class="text-muted">Konfirmasi Password</label>
            <button type="button" class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted pe-3" onclick="togglePassword('floatingConfirmPassword', 'toggleIcon2')" style="z-index: 10;">
                <i class="bi bi-eye-slash" id="toggleIcon2"></i>
            </button>
        </div>
        
        <button class="btn btn-custom w-100 py-2 mb-3" type="submit">Simpan Password <i class="bi bi-check2-circle ms-1"></i></button>
        
        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none text-muted small">Batal dan kembali ke Login</a>
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
