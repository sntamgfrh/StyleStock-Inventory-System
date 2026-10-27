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

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } else {
        $productId = filter_var(
            $_POST['product_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $supplierId = filter_var(
            $_POST['supplier_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $_POST['quantity'] ?? '',
            FILTER_VALIDATE_INT
        );

        $notes = trim($_POST['notes'] ?? '');

        if (
            $productId === false || $productId === null || $productId < 1 ||
            $supplierId === false || $supplierId === null || $supplierId < 1 ||
            $quantity === false || $quantity === null || $quantity < 1
        ) {
            $error = 'Pilih produk dan supplier yang valid, serta jumlah minimal 1.';
        } elseif (strlen($notes) > 1000) {
            $error = 'Catatan maksimal 1000 karakter.';
        } else {
            try {
                $pdo->beginTransaction();

                // Pastikan produk tersedia dan kunci baris selama transaksi.
                $stmt = $pdo->prepare(
                    'SELECT id FROM products WHERE id = :id FOR UPDATE'
                );
                $stmt->execute(['id' => $productId]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException('Produk tidak ditemukan.');
                }

                // Pastikan supplier tersedia.
                $stmt = $pdo->prepare(
                    'SELECT id FROM suppliers WHERE id = :id'
                );
                $stmt->execute(['id' => $supplierId]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException('Supplier tidak ditemukan.');
                }

                // Catat transaksi barang masuk.
                $stmt = $pdo->prepare(
                    "INSERT INTO stock_movements
                    (product_id, user_id, supplier_id, type, quantity, notes)
                    VALUES
                    (:product_id, :user_id, :supplier_id, 'IN', :quantity, :notes)"
                );

                $stmt->execute([
                    'product_id' => $productId,
                    'user_id' => $_SESSION['user']['id'],
                    'supplier_id' => $supplierId,
                    'quantity' => $quantity,
                    'notes' => $notes !== '' ? $notes : null
                ]);

                // Tambahkan stok produk.
                $stmt = $pdo->prepare(
                    'UPDATE products
                     SET stock = stock + :quantity
                     WHERE id = :id'
                );

                $stmt->execute([
                    'quantity' => $quantity,
                    'id' => $productId
                ]);

                $pdo->commit();

                $pesan = 'Barang masuk berhasil dicatat dan stok produk telah diperbarui.';
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($ex instanceof RuntimeException) {
                    $error = $ex->getMessage();
                } else {
                    error_log($ex->getMessage());
                    $error = 'Transaksi gagal disimpan. Periksa data dan struktur database.';
                }
            }
        }
    }
}

// Ambil data produk dan supplier untuk form.
$produk = $pdo->query(
    'SELECT id, sku, name, stock
     FROM products
     ORDER BY name'
)->fetchAll();

$suppliers = $pdo->query(
    'SELECT id, name
     FROM suppliers
     ORDER BY name'
)->fetchAll();

// Ambil seluruh histori barang masuk.
$stmt = $pdo->query(
    "SELECT sm.id, sm.quantity, sm.notes, sm.created_at,
            p.sku, p.name AS product_name,
            s.name AS supplier_name,
            u.name AS user_name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     LEFT JOIN suppliers s ON s.id = sm.supplier_id
     LEFT JOIN users u ON u.id = sm.user_id
     WHERE sm.type = 'IN'
     ORDER BY sm.id DESC"
);

$histori = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Barang Masuk - StyleStock</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;min-height:100vh}
header{background:linear-gradient(135deg,#4A2525,#754242);color:#F7E7B4;padding:22px 30px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header h1{font-size:25px}
header p{margin-top:6px}
header a{background:#F7E7B4;color:#4A2525;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:bold}
main{max-width:1150px;margin:auto;padding:28px 20px}
h2{margin-bottom:8px}
.sub{margin-bottom:22px}
.panel{background:#fffaf0;border:1px solid #d9c58d;border-radius:12px;padding:22px;margin-bottom:22px}
h3{margin-bottom:16px}
label{display:block;font-weight:bold;margin-bottom:7px}
input,select,textarea{width:100%;padding:11px;border:1px solid #c8b68b;border-radius:7px;font:inherit;margin-bottom:14px;background:white;color:#4A2525}
textarea{min-height:80px;resize:vertical}
button{border:0;border-radius:7px;padding:11px 16px;background:#4A2525;color:#F7E7B4;font-weight:bold;cursor:pointer}
.alert{padding:12px;border-radius:8px;margin-bottom:16px;background:#e1f3d7;color:#24521e}
.alert.error{background:#f8d7da;color:#842029}
.table-wrap{overflow-x:auto;margin-top:15px}
table{width:100%;border-collapse:collapse;min-width:700px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e3d5af;vertical-align:top}
th{background:#f1e2b7}
@media(max-width:600px){
    header{padding:20px}
    main{padding:18px 12px}
    .panel{padding:16px}
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
    <h2>Barang Masuk</h2>
    <p class="sub">Catat penerimaan barang dari supplier dan perbarui stok.</p>

    <?php if ($pesan): ?>
        <div class="alert"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h3>Form Barang Masuk</h3>

        <?php if (!$produk || !$suppliers): ?>
            <p>Data produk dan supplier harus tersedia terlebih dahulu sebelum transaksi dapat dicatat.</p>
        <?php else: ?>
            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <label for="product_id">Produk</label>
                <select id="product_id" name="product_id" required>
                    <option value="">-- Pilih Produk --</option>
                    <?php foreach ($produk as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= e($p['sku']) ?> - <?= e($p['name']) ?>
                            (Stok: <?= (int)$p['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="supplier_id">Supplier</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">-- Pilih Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)$s['id'] ?>">
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity">Jumlah Barang Masuk</label>
                <input
                    id="quantity"
                    type="number"
                    name="quantity"
                    min="1"
                    step="1"
                    required
                >

                <label for="notes">Catatan (Opsional)</label>
                <textarea
                    id="notes"
                    name="notes"
                    maxlength="1000"
                    placeholder="Contoh: Pembelian stok dari supplier"
                ></textarea>

                <button type="submit">Simpan Barang Masuk</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Histori Barang Masuk</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>SKU</th>
                        <th>Produk</th>
                        <th>Supplier</th>
                        <th>Jumlah</th>
                        <th>Petugas</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$histori): ?>
                        <tr>
                            <td colspan="8">Belum ada histori barang masuk.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($histori as $i => $h): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($h['created_at']) ?></td>
                                <td><?= e($h['sku']) ?></td>
                                <td><?= e($h['product_name']) ?></td>
                                <td><?= e($h['supplier_name'] ?? '-') ?></td>
                                <td><?= (int)$h['quantity'] ?></td>
                                <td><?= e($h['user_name'] ?? '-') ?></td>
                                <td><?= e($h['notes'] ?? '-') ?></td>
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
