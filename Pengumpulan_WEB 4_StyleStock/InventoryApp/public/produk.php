<?php
require_once __DIR__ . '/../helpers/auth.php';
wajibLogin();
require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
$pesan = '';
$error = '';
$editProduk = null;

// PROSES TAMBAH, EDIT, DAN HAPUS PRODUK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        if ($aksi === 'simpan') {
            if (!$isAdmin) {
                http_response_code(403);
                $error = 'Akses ditolak. Hanya Admin yang dapat menambah atau mengedit produk.';
            } else {
                $idRaw = trim($_POST['id'] ?? '');
                $id = $idRaw === '' ? null : filter_var($idRaw, FILTER_VALIDATE_INT);
                $categoryId = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
                $sku = trim($_POST['sku'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $price = $_POST['price'] ?? '';
                $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
                $minimumStock = filter_var($_POST['minimum_stock'] ?? '', FILTER_VALIDATE_INT);

                if (
                    ($idRaw !== '' && ($id === false || $id < 1)) ||
                    $name === '' ||
                    $sku === '' ||
                    !$categoryId ||
                    !is_numeric($price) ||
                    !is_finite((float)$price) ||
                    (float)$price < 0 ||
                    $stock === false ||
                    $stock === null ||
                    $stock < 0 ||
                    $minimumStock === false ||
                    $minimumStock === null ||
                    $minimumStock < 0
                ) {
                    $error = 'Isi semua data dengan benar. Harga dan stok tidak boleh negatif.';
                } else {
                    $cekKategori = $pdo->prepare(
                        'SELECT COUNT(*) FROM categories WHERE id = :id'
                    );
                    $cekKategori->execute(['id' => $categoryId]);

                    if (!$cekKategori->fetchColumn()) {
                        $error = 'Kategori tidak ditemukan.';
                    } else {
                        try {
                            if ($id !== null) {
                                $stmt = $pdo->prepare(
                                    'UPDATE products
                                     SET category_id = :category_id,
                                         sku = :sku,
                                         name = :name,
                                         price = :price,
                                         stock = :stock,
                                         minimum_stock = :minimum_stock
                                     WHERE id = :id'
                                );

                                $stmt->execute([
                                    'category_id' => $categoryId,
                                    'sku' => $sku,
                                    'name' => $name,
                                    'price' => $price,
                                    'stock' => $stock,
                                    'minimum_stock' => $minimumStock,
                                    'id' => $id
                                ]);

                                $pesan = 'Produk berhasil diperbarui.';
                            } else {
                                $stmt = $pdo->prepare(
                                    'INSERT INTO products
                                     (category_id, sku, name, price, stock, minimum_stock)
                                     VALUES
                                     (:category_id, :sku, :name, :price, :stock, :minimum_stock)'
                                );

                                $stmt->execute([
                                    'category_id' => $categoryId,
                                    'sku' => $sku,
                                    'name' => $name,
                                    'price' => $price,
                                    'stock' => $stock,
                                    'minimum_stock' => $minimumStock
                                ]);

                                $pesan = 'Produk berhasil ditambahkan.';
                            }
                        } catch (PDOException $ex) {
                            if ($ex->getCode() === '23000') {
                                $error = 'SKU sudah digunakan atau data terkait tidak valid.';
                            } else {
                                error_log($ex->getMessage());
                                $error = 'Produk gagal disimpan.';
                            }
                        }
                    }
                }
            }
        } elseif ($aksi === 'hapus') {
            if (!$isAdmin) {
                http_response_code(403);
                $error = 'Hanya Admin yang dapat menghapus produk.';
            } else {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

                if (!$id || $id < 1) {
                    $error = 'ID produk tidak valid.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'DELETE FROM products WHERE id = :id'
                        );
                        $stmt->execute(['id' => $id]);

                        $pesan = $stmt->rowCount()
                            ? 'Produk berhasil dihapus.'
                            : 'Produk tidak ditemukan.';
                    } catch (PDOException $ex) {
                        error_log($ex->getMessage());
                        $error = 'Produk tidak dapat dihapus karena mungkin memiliki histori transaksi.';
                    }
                }
            }
        }
    }
}

// AMBIL DATA PRODUK UNTUK DIEDIT: ADMIN SAJA
if (isset($_GET['edit'])) {
    if (!$isAdmin) {
        http_response_code(403);
        $error = 'Akses ditolak. Hanya Admin yang dapat mengedit produk.';
    } else {
        $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

        if ($idEdit && $idEdit > 0) {
            $stmt = $pdo->prepare(
                'SELECT * FROM products WHERE id = :id'
            );
            $stmt->execute(['id' => $idEdit]);
            $editProduk = $stmt->fetch() ?: null;

            if (!$editProduk) {
                $error = 'Produk tidak ditemukan.';
            }
        }
    }
}

// AMBIL KATEGORI
$kategori = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

// PENCARIAN DAN FILTER KATEGORI
$cari = trim($_GET['cari'] ?? '');
$filterKategori = filter_var(
    $_GET['category_id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($filterKategori === false || $filterKategori === null || $filterKategori < 1) {
    $filterKategori = null;
}

$where = ' WHERE 1=1';
$params = [];

if ($cari !== '') {
    $where .= ' AND (p.name LIKE :cariNama OR p.sku LIKE :cariSku)';
    $params['cariNama'] = '%' . $cari . '%';
    $params['cariSku'] = '%' . $cari . '%';
}

if ($filterKategori !== null) {
    $where .= ' AND p.category_id = :category_id';
    $params['category_id'] = $filterKategori;
}

// HITUNG JUMLAH PRODUK
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products p' . $where);
$stmt->execute($params);
$totalProduk = (int)$stmt->fetchColumn();

// PAGINATION
$perPage = 10;
$totalHalaman = max(1, (int)ceil($totalProduk / $perPage));

$halaman = filter_var($_GET['halaman'] ?? 1, FILTER_VALIDATE_INT);
if ($halaman === false || $halaman === null || $halaman < 1) {
    $halaman = 1;
}

$halaman = min($halaman, $totalHalaman);
$offset = ($halaman - 1) * $perPage;

// AMBIL PRODUK
$sql = 'SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id'
        . $where .
        ' ORDER BY p.id DESC LIMIT :batas OFFSET :offset';

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $tipe = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue(':' . $key, $value, $tipe);
}

$stmt->bindValue(':batas', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$produk = $stmt->fetchAll();
$nomorAwal = $offset + 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Produk - StyleStock</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;min-height:100vh}
header{background:linear-gradient(135deg,#4A2525,#754242);color:#F7E7B4;padding:22px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{font-size:25px}
header p{margin-top:6px}
header a{background:#F7E7B4;color:#4A2525;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:bold}
main{max-width:1200px;margin:auto;padding:28px 20px}
h2{margin-bottom:8px}
.sub{margin-bottom:22px}
.panel{background:#fffaf0;border:1px solid #d9c58d;border-radius:12px;padding:22px;margin-bottom:22px}
h3{margin-bottom:16px}
label{display:block;font-weight:bold;margin-bottom:7px}
input,select{width:100%;padding:11px;border:1px solid #c8b68b;border-radius:7px;font:inherit;margin-bottom:14px;background:white;color:#4A2525}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 16px}
button,.btn{border:0;border-radius:7px;padding:10px 15px;background:#4A2525;color:#F7E7B4;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block;font:inherit}
.btn-light{background:#e9d9a6;color:#4A2525}
.search{display:flex;gap:10px;flex-wrap:wrap}
.search input,.search select{flex:1;min-width:160px;margin:0}
.alert{padding:12px;border-radius:8px;margin-bottom:16px;background:#e1f3d7;color:#24521e}
.alert.error{background:#f8d7da;color:#842029}
.table-wrap{overflow-x:auto;margin-top:18px}
table{width:100%;border-collapse:collapse;min-width:850px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e3d5af;vertical-align:top}
th{background:#f1e2b7}
td form{display:inline}
.actions{display:flex;gap:6px;align-items:center}
.actions a,.actions button{padding:7px 10px;font-size:13px}
.low{font-weight:bold;color:#a12626}
.pagination{display:flex;gap:8px;justify-content:center;align-items:center;flex-wrap:wrap;margin-top:20px}
.pagination .btn{font-size:14px}
.pagination .current{background:#4A2525;color:#F7E7B4;padding:10px 14px;border-radius:7px;font-weight:bold}
.info-hasil{margin-top:14px;font-size:14px}
@media(max-width:650px){
    header{padding:20px}
    main{padding:18px 12px}
    .panel{padding:16px}
    .grid{grid-template-columns:1fr}
}
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
    <h2>Data Produk</h2>
    <p class="sub">Kelola data produk dan pantau stok inventaris.</p>

    <?php if ($pesan): ?>
        <div class="alert"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <section class="panel">
        <h3><?= $editProduk ? 'Edit Produk' : 'Tambah Produk' ?></h3>

        <?php if (!$kategori): ?>
            <p>Tambahkan kategori terlebih dahulu sebelum membuat produk.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" value="<?= e($editProduk['id'] ?? '') ?>">

                <div class="grid">
                    <div>
                        <label for="sku">SKU</label>
                        <input id="sku" name="sku" maxlength="100" required value="<?= e($editProduk['sku'] ?? '') ?>" placeholder="Contoh: FSH-001">
                    </div>
                    <div>
                        <label for="name">Nama Produk</label>
                        <input id="name" name="name" maxlength="150" required value="<?= e($editProduk['name'] ?? '') ?>" placeholder="Nama produk">
                    </div>
                    <div>
                        <label for="category_id">Kategori</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori as $k): ?>
                                <option value="<?= (int)$k['id'] ?>" <?= (string)($editProduk['category_id'] ?? '') === (string)$k['id'] ? 'selected' : '' ?>>
                                    <?= e($k['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="price">Harga</label>
                        <input id="price" type="number" name="price" min="0" step="0.01" required value="<?= e($editProduk['price'] ?? '0') ?>">
                    </div>
                    <div>
                        <label for="stock">Stok</label>
                        <input id="stock" type="number" name="stock" min="0" step="1" required value="<?= e($editProduk['stock'] ?? '0') ?>">
                    </div>
                    <div>
                        <label for="minimum_stock">Batas Minimum Stok</label>
                        <input id="minimum_stock" type="number" name="minimum_stock" min="0" step="1" required value="<?= e($editProduk['minimum_stock'] ?? '5') ?>">
                    </div>
                </div>

                <button type="submit"><?= $editProduk ? 'Simpan Perubahan' : 'Tambah Produk' ?></button>

                <?php if ($editProduk): ?>
                    <a class="btn btn-light" href="produk.php">Batal Edit</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h3>Daftar Produk</h3>

        <form method="get" class="search">
            <input name="cari" value="<?= e($cari) ?>" placeholder="Cari nama produk atau SKU">
            <select name="category_id">
                <option value="">Semua Kategori</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= (int)$k['id'] ?>" <?= (string)$filterKategori === (string)$k['id'] ? 'selected' : '' ?>>
                        <?= e($k['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Cari / Filter</button>
            <a class="btn btn-light" href="produk.php">Reset</a>
        </form>

        <p class="info-hasil">
            Total produk ditemukan: <strong><?= $totalProduk ?></strong>
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th><th>SKU</th><th>Nama Produk</th><th>Kategori</th>
                        <th>Harga</th><th>Stok</th><th>Minimum</th>
                        <?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$produk): ?>
                        <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>">Belum ada produk.</td></tr>
                    <?php else: ?>
                        <?php foreach ($produk as $i => $p): ?>
                            <tr>
                                <td><?= $nomorAwal + $i ?></td>
                                <td><?= e($p['sku']) ?></td>
                                <td><?= e($p['name']) ?></td>
                                <td><?= e($p['category_name'] ?? '-') ?></td>
                                <td>Rp <?= number_format((float)$p['price'], 0, ',', '.') ?></td>
                                <td class="<?= (int)$p['stock'] <= (int)$p['minimum_stock'] ? 'low' : '' ?>"><?= (int)$p['stock'] ?></td>
                                <td><?= (int)$p['minimum_stock'] ?></td>
                                <?php if ($isAdmin): ?>
                                <td>
                                    <div class="actions">
                                        <a class="btn" href="produk.php?edit=<?= (int)$p['id'] ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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

        <?php if ($totalHalaman > 1): ?>
            <div class="pagination">
                <?php if ($halaman > 1): ?>
                    <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['halaman' => $halaman - 1]))) ?>">Sebelumnya</a>
                <?php endif; ?>

                <?php for ($nomor = 1; $nomor <= $totalHalaman; $nomor++): ?>
                    <?php if ($nomor === $halaman): ?>
                        <span class="current"><?= $nomor ?></span>
                    <?php else: ?>
                        <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['halaman' => $nomor]))) ?>"><?= $nomor ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($halaman < $totalHalaman): ?>
                    <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['halaman' => $halaman + 1]))) ?>">Berikutnya</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
