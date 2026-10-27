<?php

require_once __DIR__ . '/../helpers/auth.php';
wajibAdmin();

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'tambah') {
            $nama = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';

            if ($nama === '' || $username === '' || $password === '') {
                $error = 'Nama, username, dan password wajib diisi.';
            } elseif (strlen($password) < 8) {
                $error = 'Password minimal 8 karakter.';
            } elseif (!in_array($role, ['admin', 'staff'], true)) {
                $error = 'Role tidak valid.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (name, username, password_hash, role)
                         VALUES (?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $nama,
                        $username,
                        password_hash($password, PASSWORD_DEFAULT),
                        $role
                    ]);

                    $pesan = 'Pengguna berhasil ditambahkan.';
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $error = 'Username sudah digunakan.';
                    } else {
                        error_log($e->getMessage());
                        $error = 'Pengguna gagal ditambahkan.';
                    }
                }
            }
        }

        if ($aksi === 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $idLogin = (int) ($_SESSION['user']['id'] ?? 0);

            if (!$id || $id === $idLogin) {
                $error = 'Akun sendiri tidak dapat dihapus.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT id FROM users WHERE id = ? AND role = 'admin'"
                    );
                    $stmt->execute([$id]);
                    $targetAdmin = $stmt->fetch();

                    if ($targetAdmin) {
                        $jumlahAdmin = (int) $pdo->query(
                            "SELECT COUNT(*) FROM users WHERE role = 'admin'"
                        )->fetchColumn();

                        if ($jumlahAdmin <= 1) {
                            $error = 'Admin terakhir tidak boleh dihapus.';
                        }
                    }

                    if ($error === '') {
                        $stmt = $pdo->prepare(
                            'DELETE FROM users WHERE id = ?'
                        );
                        $stmt->execute([$id]);

                        $pesan = $stmt->rowCount()
                            ? 'Pengguna berhasil dihapus.'
                            : 'Pengguna tidak ditemukan.';
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = 'Pengguna tidak dapat dihapus. Akun mungkin memiliki histori transaksi.';
                }
            }
        }
    }
}

$pengguna = $pdo->query(
    'SELECT id, name, username, role, created_at
     FROM users
     ORDER BY id DESC'
)->fetchAll();

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Pengguna - StyleStock</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;min-height:100vh}
header{background:linear-gradient(135deg,#4A2525,#754242);color:#F7E7B4;padding:22px 30px}
header h1{font-size:25px}
header p{margin-top:6px}
main{max-width:1150px;margin:auto;padding:30px}
.nav{margin-bottom:22px}
.nav a{display:inline-block;background:#4A2525;color:#F7E7B4;padding:11px 15px;border-radius:8px;text-decoration:none;margin:0 8px 8px 0}
.panel{background:#fffaf0;padding:24px;border-radius:14px;border:1px solid #d9c58d;margin-bottom:24px;box-shadow:0 5px 14px rgba(74,37,37,.06)}
h2{margin-bottom:18px}
form .fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
label{display:block;font-weight:bold;margin-bottom:7px}
input,select{width:100%;padding:11px;border:1px solid #c7a77b;border-radius:7px;font-size:14px}
button{border:0;border-radius:7px;padding:11px 15px;cursor:pointer;font-weight:bold}
.submit{background:#4A2525;color:#F7E7B4;margin-top:16px}
.delete{background:#8a2419;color:white}
.notice{padding:12px;border-radius:7px;margin-bottom:16px}
.success{background:#d7efce;color:#245b26}
.failure{background:#f5d0ca;color:#8a2419}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:650px}
th,td{padding:12px;text-align:left;border-bottom:1px solid #d9c58d}
th{background:#4A2525;color:#F7E7B4}
.role{font-weight:bold;text-transform:capitalize}
.small{font-size:13px;margin-top:8px}
@media(max-width:600px){main{padding:16px}.panel{padding:16px}form .fields{grid-template-columns:1fr}header{padding:20px}}
</style>
</head>
<body>
<header>
    <h1>StyleStock</h1>
    <p>Kelola Pengguna Admin dan Staff</p>
</header>

<main>
    <nav class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="kategori.php">Kategori</a>
        <a href="produk.php">Produk</a>
        <a href="supplier.php">Supplier</a>
    </nav>

    <?php if ($pesan !== ''): ?>
        <div class="notice success"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice failure"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Tambah Pengguna</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="tambah">

            <div class="fields">
                <div>
                    <label for="name">Nama Lengkap</label>
                    <input id="name" name="name" maxlength="100" required>
                </div>

                <div>
                    <label for="username">Username</label>
                    <input id="username" name="username" maxlength="100" autocomplete="username" required>
                </div>

                <div>
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
                    <p class="small">Minimal 8 karakter.</p>
                </div>

                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <button class="submit" type="submit">Tambah Pengguna</button>
        </form>
    </section>

    <section class="panel">
        <h2>Daftar Pengguna</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pengguna): ?>
                    <tr>
                        <td colspan="6">Belum ada pengguna.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pengguna as $i => $user): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($user['name']) ?></td>
                            <td><?= e($user['username']) ?></td>
                            <td class="role"><?= e($user['role']) ?></td>
                            <td><?= e($user['created_at'] ?? '-') ?></td>
                            <td>
                                <?php if ((int)$user['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus pengguna ini?')">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                        <button class="delete" type="submit">Hapus</button>
                                    </form>
                                <?php else: ?>
                                    Akun Anda
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
