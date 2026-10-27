<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, username, password_hash, role
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role']
            ];

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | InventoryApp</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #F7E7B4;
            color: #4A2525;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 850px;
            min-height: 480px;
            overflow: hidden;
            border-radius: 18px;
            background: #FFF9E9;
            box-shadow: 0 15px 40px rgba(74, 37, 37, 0.18);
        }

        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
            color: #FFF9E9;
            background: linear-gradient(145deg, #713B3B, #4A2525, #301717);
        }

        .brand h1 {
            font-size: 34px;
            margin-bottom: 12px;
        }

        .brand p {
            line-height: 1.7;
            color: #F7E7B4;
        }

        .brand-line {
            width: 65px;
            height: 5px;
            margin-bottom: 25px;
            border-radius: 10px;
            background: #F7E7B4;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 45px 38px;
        }

        .form-section h2 {
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            margin-bottom: 28px;
            color: #806565;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #D8C5A0;
            border-radius: 8px;
            outline: none;
            background: #FFFCF4;
            color: #4A2525;
            font-size: 14px;
        }

        input:focus {
            border-color: #713B3B;
            box-shadow: 0 0 0 3px rgba(113, 59, 59, 0.10);
        }

        .login-button {
            width: 100%;
            margin-top: 8px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(100deg, #713B3B, #4A2525);
            color: #FFF9E9;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .login-button:hover {
            background: #301717;
        }

        .error {
            margin-bottom: 18px;
            padding: 12px;
            border-radius: 7px;
            background: #F8DEDA;
            color: #842B2B;
            font-size: 13px;
        }

        @media (max-width: 650px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .brand {
                min-height: 190px;
                padding: 25px;
            }

            .brand h1 {
                font-size: 28px;
            }

            .form-section {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <section class="brand">
        <div class="brand-line"></div>
        <h1>InventoryApp</h1>
        <p>Inventory Management System</p>
        <p>Kelola stok, kelola bisnis.</p>
    </section>

    <section class="form-section">
        <h2>Login</h2>
        <p class="subtitle">Silakan masuk ke akun Anda.</p>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars(
                        $username,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    maxlength="50"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="login-button">
                Masuk
            </button>
        </form>
    </section>
</div>

</body>
</html>
