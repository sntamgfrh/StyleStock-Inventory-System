<?php
require_once __DIR__ . '/../helpers/auth.php';
wajibLogin();
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$pesan = '';
$error = '';
$editSupplier = null;

// PROSES TAMBAH, EDIT, DAN HAPUS SUPPLIER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } elseif (!$isAdmin) {
        http_response_code(403);
        $error = 'Akses ditolak. Hanya Admin yang dapat mengelola supplier.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'simpan') {
            $idRaw = trim($_POST['id'] ?? '');
            $id = $idRaw === '' ? null : filter_var($idRaw, FILTER_VALIDATE_INT);

            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (
                ($idRaw !== '' && ($id === false || $id < 1)) ||
                $name === ''
            ) {
                $error = 'Nama supplier wajib diisi dan ID harus valid.';
            } else {
                try {
                    if ($id !== null) {
                        $stmt = $pdo->prepare(
                            'UPDATE suppliers
                             SET name = :name,
                                 contact = :contact,
                                 phone = :phone,
                                 address = :address
                             WHERE id = :id'
                        );

                        $stmt->execute([
                            'name' => $name,
                            'contact' => $contact !== '' ? $contact : null,
                            'phone' => $phone !== '' ? $phone : null,
                            'address' => $address !== '' ? $address : null,
                            'id' => $id
                        ]);

                        $pesan = $stmt->rowCount() > 0
                            ? 'Supplier berhasil diperbarui.'
                            : 'Tidak ada perubahan data supplier.';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO suppliers (name, contact, phone, address)
                             VALUES (:name, :contact, :phone, :address)'
                        );

                        $stmt->execute([
                            'name' => $name,
                            'contact' => $contact !== '' ? $contact : null,
                            'phone' => $phone !== '' ? $phone : null,
                            'address' => $address !== '' ? $address : null
                        ]);

                        $pesan = 'Supplier berhasil ditambahkan.';
                    }
                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = 'Supplier gagal disimpan. Periksa kembali data.';
                }
            }
        } elseif ($aksi === 'hapus') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false || $id === null || $id < 1) {
                $error = 'ID supplier tidak valid.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM suppliers WHERE id = :id'
                    );
                    $stmt->execute(['id' => $id]);

                    $pesan = $stmt->rowCount() > 0
                        ? 'Supplier berhasil dihapus.'
                        : 'Supplier tidak ditemukan.';
                } catch (PDOException $ex) {
                    error_log($ex->getMessage());
                    $error = 'Supplier tidak dapat dihapus karena mungkin masih digunakan oleh data lain.';
                }
            }
        } else {
            $error = 'Aksi tidak valid.';
        }
    }
}

// EDIT SUPPLIER HANYA UNTUK ADMIN
if (isset($_GET['edit'])) {
    if (!$isAdmin) {
        http_response_code(403);
        $error = 'Akses ditolak. Hanya Admin yang dapat mengedit supplier.';
    } else {
        $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

        if ($idEdit !== false && $idEdit !== null && $idEdit > 0) {
            $stmt = $pdo->prepare(
                'SELECT * FROM suppliers WHERE id = :id'
            );
            $stmt->execute(['id' => $idEdit]);
            $editSupplier = $stmt->fetch() ?: null;

            if (!$editSupplier) {
                $error = 'Supplier tidak ditemukan.';
            }
        } else {
            $error = 'ID supplier tidak valid.';
        }
    }
}

// PENCARIAN SUPPLIER
$cari = trim($_GET['cari'] ?? '');

$sql = 'SELECT id, name, contact, phone, address, created_at FROM suppliers';
$params = [];

if ($cari !== '') {
    $sql .= ' WHERE name LIKE :cariNama
              OR contact LIKE :cariKontak
              OR phone LIKE :cariTelepon';

    $kataCari = '%' . $cari . '%';
    $params = [
        'cariNama' => $kataCari,
        'cariKontak' => $kataCari,
        'cariTelepon' => $kataCari
    ];
}

$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Supplier - StyleStock</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;min-height:100vh}
header{background:linear-gradient(135deg,#4A2525,#754242);color:#F7E7B4;padding:22px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{font-size:25px}header p{margin-top:6px}
header a{background:#F7E7B4;color:#4A2525;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:bold}
main{max-width:1150px;margin:auto;padding:28px 20px}
h2{margin-bottom:8px}.sub{margin-bottom:22px}
.panel{background:#fffaf0;border:1px solid #d9c58d;border-radius:12px;padding:22px;margin-bottom:22px}
h3{margin-bottom:16px}
label{display:block;font-weight:bold;margin-bottom:7px}
input,textarea{width:100%;padding:11px;border:1px solid #c8b68c;border-radius:7px;font:inherit;margin-bottom:14px;background:white;color:#4A2525}
textarea{min-height:75px;resize:vertical}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}
button,.btn{border:0;border-radius:7px;padding:10px 15px;background:#4A2525;color:#F7E7B4;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block;font:inherit}
.btn-light{background:#e9d9a6;color:#4A2525}
.search{display:flex;gap:10px;flex-wrap:wrap}.search input{flex:1;min-width:180px;margin:0}
.alert{padding:12px;border-radius:8px;margin-bottom:16px;background:#e1f3d7;color:#24521e}
.alert.error{background:#f8d7da;color:#842029}
.table-wrap{overflow-x:auto;margin-top:18px}
table{width:100%;border-collapse:collapse;min-width:750px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e3d5af;vertical-align:top}
th{background:#f1e2b7}
.actions{display:flex;gap:6px;align-items:center}
.actions a,.actions button{padding:7px 10px;font-size:13px}
@media(max-width:650px){header{padding:20px}main{padding:18px 12px}.panel{padding:16px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header>
<div><h1>StyleStock</h1><p>Sistem Informasi Inventaris Fashion</p></div>
<a href="dashboard.php">Kembali ke Dashboard</a>
</header>

<main>
<h2>Data Supplier</h2>
<p class="sub">Kelola informasi pemasok produk fashion.</p>

<?php if ($pesan !== ''): ?>
    <div class="alert"><?= e($pesan) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<section class="panel">
<h3><?= $editSupplier ? 'Edit Supplier' : 'Tambah Supplier' ?></h3>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="simpan">
<input type="hidden" name="id" value="<?= e($editSupplier['id'] ?? '') ?>">

<div class="grid">
<div>
<label for="name">Nama Supplier</label>
<input id="name" name="name" maxlength="150" required value="<?= e($editSupplier['name'] ?? '') ?>" placeholder="Nama pemasok">
</div>
<div>
<label for="contact">Nama Kontak</label>
<input id="contact" name="contact" maxlength="150" value="<?= e($editSupplier['contact'] ?? '') ?>" placeholder="Nama orang yang bisa dihubungi">
</div>
<div>
<label for="phone">Nomor Telepon</label>
<input id="phone" name="phone" maxlength="30" value="<?= e($editSupplier['phone'] ?? '') ?>" placeholder="Nomor telepon supplier">
</div>
<div>
<label for="address">Alamat</label>
<textarea id="address" name="address" placeholder="Alamat supplier"><?= e($editSupplier['address'] ?? '') ?></textarea>
</div>
</div>

<button type="submit"><?= $editSupplier ? 'Simpan Perubahan' : 'Tambah Supplier' ?></button>
<?php if ($editSupplier): ?>
    <a class="btn btn-light" href="supplier.php">Batal Edit</a>
<?php endif; ?>
</form>
</section>
<?php endif; ?>

<section class="panel">
<h3>Daftar Supplier</h3>
<form method="get" class="search">
<input name="cari" value="<?= e($cari) ?>" placeholder="Cari nama, kontak, atau telepon">
<button type="submit">Cari</button>
<a class="btn btn-light" href="supplier.php">Reset</a>
</form>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>No.</th><th>Nama Supplier</th><th>Kontak</th><th>Telepon</th><th>Alamat</th><th>Tanggal Dibuat</th>
<?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php if (!$suppliers): ?>
<tr><td colspan="<?= $isAdmin ? 7 : 6 ?>">Belum ada data supplier.</td></tr>
<?php else: ?>
<?php foreach ($suppliers as $i => $s): ?>
<tr>
<td><?= $i + 1 ?></td>
<td><?= e($s['name']) ?></td>
<td><?= e($s['contact'] ?: '-') ?></td>
<td><?= e($s['phone'] ?: '-') ?></td>
<td><?= e($s['address'] ?: '-') ?></td>
<td><?= e($s['created_at']) ?></td>
<?php if ($isAdmin): ?>
<td>
<div class="actions">
<a class="btn" href="supplier.php?edit=<?= (int)$s['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Yakin ingin menghapus supplier ini?')">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<input type="hidden" name="aksi" value="hapus">
<input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<button type="submit">Hapus</button>
</form>
</div>
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
