<?php
require_once __DIR__ . '/../helpers/auth.php';
wajibLogin();
require_once __DIR__ . '/../config/database.php';

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$pesan = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// PROSES TAMBAH DAN HAPUS KATEGORI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$isAdmin) {
        http_response_code(403);
        $error = 'Akses ditolak. Hanya Admin yang dapat mengelola kategori.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'tambah') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($name === '') {
                $error = 'Nama kategori wajib diisi.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO categories (name, description)
                         VALUES (:name, :description)'
                    );

                    $stmt->execute([
                        'name' => $name,
                        'description' => $description !== '' ? $description : null,
                    ]);

                    $pesan = 'Kategori berhasil ditambahkan.';
                } catch (PDOException $ex) {
                    if ($ex->getCode() === '23000') {
                        $error = 'Nama kategori sudah digunakan.';
                    } else {
                        error_log($ex->getMessage());
                        $error = 'Kategori gagal ditambahkan.';
                    }
                }
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false || $id === null || $id < 1) {
                $error = 'ID kategori tidak valid.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM categories WHERE id = :id'
                    );
                    $stmt->execute(['id' => $id]);

                    $pesan = $stmt->rowCount() > 0
                        ? 'Kategori berhasil dihapus.'
                        : 'Kategori tidak ditemukan.';
                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = 'Kategori tidak dapat dihapus karena mungkin masih digunakan oleh produk.';
                }
            }
        } else {
            $error = 'Aksi tidak valid.';
        }
    }
}

// PENCARIAN KATEGORI
$cari = trim($_GET['cari'] ?? '');

$sql = 'SELECT id, name, description, created_at FROM categories';
$params = [];

if ($cari !== '') {
    // Placeholder berbeda agar kompatibel dengan PDO native prepares
    $sql .= ' WHERE name LIKE :cariNama OR description LIKE :cariDeskripsi';
    $params['cariNama'] = '%' . $cari . '%';
    $params['cariDeskripsi'] = '%' . $cari . '%';
}

$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kategori = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Kategori - StyleStock</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;min-height:100vh}
header{background:linear-gradient(135deg,#4A2525,#754242);color:#F7E7B4;padding:22px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{font-size:25px}
header p{margin-top:6px}
a{color:inherit}
header a{background:#F7E7B4;color:#4A2525;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:bold}
main{max-width:1100px;margin:auto;padding:28px 20px}
h2{margin-bottom:8px}
.sub{margin-bottom:22px}
.panel{background:#fffaf0;border:1px solid #d9c58d;border-radius:12px;padding:22px;margin-bottom:22px}
h3{margin-bottom:16px}
label{display:block;margin-bottom:7px;font-weight:bold}
input,textarea{width:100%;padding:11px;border:1px solid #c8b68b;border-radius:7px;font:inherit;margin-bottom:14px;background:#fff}
textarea{min-height:80px;resize:vertical}
button,.btn{border:0;border-radius:7px;padding:10px 15px;background:#4A2525;color:#F7E7B4;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block;font:inherit}
.search{display:flex;gap:10px;flex-wrap:wrap}
.search input{flex:1;min-width:180px;margin:0}
.alert{padding:12px;border-radius:8px;margin-bottom:16px;background:#e1f3d7;color:#24521e}
.alert.error{background:#f8d7da;color:#842029}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:600px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e3d5af;vertical-align:top}
th{background:#f1e2b7}
td form{display:inline}
.small{font-size:13px;color:#725858}
@media(max-width:600px){header{padding:20px}main{padding:18px 12px}.panel{padding:16px}}
</style>
</head>
<body>
<header>
    <div>
        <h1>StyleStock</h1>
        <p>Sistem Informasi Inventaris Fashion</p>
    </div>
    <a href="dashboard.php">Kembali ke Dashboard</a>
</header>

<main>
    <h2>Data Kategori</h2>
    <p class="sub">Kelola kategori produk inventaris.</p>

    <?php if ($pesan !== ''): ?>
        <div class="alert"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <section class="panel">
        <h3>Tambah Kategori</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="aksi" value="tambah">

            <label for="name">Nama Kategori</label>
            <input id="name" name="name" maxlength="100" required placeholder="Contoh: Atasan">

            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" placeholder="Keterangan kategori (opsional)"></textarea>

            <button type="submit">Simpan Kategori</button>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h3>Daftar Kategori</h3>

        <form method="get" class="search">
            <input name="cari" value="<?= e($cari) ?>" placeholder="Cari nama atau deskripsi kategori">
            <button type="submit">Cari</button>
            <a class="btn" href="kategori.php">Reset</a>
        </form>

        <div class="table-wrap" style="margin-top:18px">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Kategori</th>
                        <th>Deskripsi</th>
                        <th>Tanggal Dibuat</th>
                        <?php if ($isAdmin): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$kategori): ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? 5 : 4 ?>">Belum ada kategori.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kategori as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($item['name']) ?></td>
                        <td><?= e($item['description'] ?: '-') ?></td>
                        <td><?= e($item['created_at']) ?></td>

                        <?php if ($isAdmin): ?>
                        <td>
                            <form method="post" onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
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
