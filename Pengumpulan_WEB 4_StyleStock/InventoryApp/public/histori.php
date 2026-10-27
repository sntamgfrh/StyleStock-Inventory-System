<?php
require_once __DIR__ . '/../helpers/auth.php';
wajibLogin();

require_once __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars(
        (string)($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

$keyword = trim($_GET['cari'] ?? '');
$jenis = $_GET['jenis'] ?? '';

if (!is_string($keyword)) {
    $keyword = '';
}

if (!is_string($jenis)) {
    $jenis = '';
}

if (!in_array($jenis, ['', 'IN', 'OUT'], true)) {
    $jenis = '';
}

// Pagination
$perHalaman = 10;
$halaman = filter_var(
    $_GET['halaman'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($halaman === false || $halaman < 1) {
    $halaman = 1;
}

// Filter histori
$where = ' WHERE 1=1 ';
$params = [];

if ($keyword !== '') {
    $where .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR u.name LIKE ?) ';
    $like = '%' . $keyword . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($jenis !== '') {
    $where .= ' AND sm.type = ? ';
    $params[] = $jenis;
}

// Hitung seluruh transaksi sesuai filter.
$countSql = "
    SELECT COUNT(*)
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.user_id
    LEFT JOIN suppliers s ON s.id = sm.supplier_id
    $where
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRiwayat = (int)$countStmt->fetchColumn();

$totalHalaman = max(
    1,
    (int)ceil($totalRiwayat / $perHalaman)
);

if ($halaman > $totalHalaman) {
    $halaman = $totalHalaman;
}

$offset = ($halaman - 1) * $perHalaman;

// Ambil transaksi untuk halaman yang sedang dibuka.
$sql = "
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           p.sku, p.name AS product_name,
           u.name AS user_name,
           s.name AS supplier_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.user_id
    LEFT JOIN suppliers s ON s.id = sm.supplier_id
    $where
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT $perHalaman OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// Pertahankan filter saat berpindah halaman.
function urlHalaman(int $nomor, string $keyword, string $jenis): string {
    return 'histori.php?' . http_build_query([
        'cari' => $keyword,
        'jenis' => $jenis,
        'halaman' => $nomor
    ]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Histori Transaksi - StyleStock</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#F7E7B4;color:#4A2525;padding:30px}
.container{max-width:1200px;margin:auto}
.header{background:linear-gradient(135deg,#4A2525,#754646);color:#F7E7B4;padding:25px;border-radius:12px;margin-bottom:22px}
.header h1{margin-bottom:8px}
.nav{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.nav a,.btn{display:inline-block;padding:10px 15px;border-radius:7px;text-decoration:none;border:0;cursor:pointer;font-weight:bold}
.nav a{background:#4A2525;color:#F7E7B4}
.panel{background:#fffaf0;padding:22px;border-radius:12px;box-shadow:0 4px 12px #4A252522}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}
input,select{padding:11px;border:1px solid #c7a77b;border-radius:7px;background:white;color:#4A2525}
button{background:#4A2525;color:#F7E7B4}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{padding:12px;text-align:left;border-bottom:1px solid #e5d6b3}
th{background:#4A2525;color:#F7E7B4}
tr:hover{background:#fff1d0}
.badge{padding:5px 9px;border-radius:5px;font-weight:bold}
.in{background:#d7efce;color:#245b26}
.out{background:#f5d0ca;color:#8a2419}
.empty{text-align:center;padding:25px}
.table-wrap{overflow-x:auto}
.pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px;align-items:center}
.pagination a,.pagination span{padding:9px 13px;border-radius:7px;text-decoration:none;font-weight:bold}
.pagination a{background:#4A2525;color:#F7E7B4}
.pagination .active{background:#e8d5a3;color:#4A2525}
.pagination .disabled{background:#eee2c5;color:#8a7b61}
@media(max-width:600px){body{padding:12px}.panel{padding:12px}th,td{padding:8px;font-size:12px}}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <h1>Histori Transaksi</h1>
        <p>Riwayat seluruh aktivitas barang masuk dan barang keluar.</p>
    </div>

    <div class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="barang-masuk.php">Barang Masuk</a>
        <a href="barang-keluar.php">Barang Keluar</a>
        <a href="produk.php">Data Produk</a>
    </div>

    <div class="panel">
        <h2>Daftar Riwayat</h2>

        <form method="GET" class="filters">
            <input
                type="text"
                name="cari"
                placeholder="Cari produk, SKU, atau pengguna..."
                value="<?= e($keyword) ?>"
            >

            <select name="jenis">
                <option value="">Semua Transaksi</option>
                <option value="IN" <?= $jenis === 'IN' ? 'selected' : '' ?>>
                    Barang Masuk
                </option>
                <option value="OUT" <?= $jenis === 'OUT' ? 'selected' : '' ?>>
                    Barang Keluar
                </option>
            </select>

            <button class="btn" type="submit">Cari / Filter</button>

            <a
                class="btn"
                href="histori.php"
                style="background:#e8d5a3;color:#4A2525"
            >Reset</a>
        </form>

        <p>
            Total riwayat:
            <strong><?= $totalRiwayat ?></strong> transaksi
        </p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Jumlah</th>
                        <th>Supplier</th>
                        <th>Pengguna</th>
                        <th>Catatan</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$riwayat): ?>
                    <tr>
                        <td colspan="9" class="empty">
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayat as $i => $r): ?>
                        <tr>
                            <td><?= $offset + $i + 1 ?></td>

                            <td>
                                <?= e(date(
                                    'd-m-Y H:i',
                                    strtotime($r['created_at'])
                                )) ?>
                            </td>

                            <td>
                                <?php if ($r['type'] === 'IN'): ?>
                                    <span class="badge in">Barang Masuk</span>
                                <?php else: ?>
                                    <span class="badge out">Barang Keluar</span>
                                <?php endif; ?>
                            </td>

                            <td><?= e($r['sku']) ?></td>
                            <td><?= e($r['product_name']) ?></td>
                            <td><?= (int)$r['quantity'] ?></td>
                            <td><?= e($r['supplier_name'] ?? '-') ?></td>
                            <td><?= e($r['user_name'] ?? '-') ?></td>
                            <td><?= e($r['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalRiwayat > 0): ?>
            <div class="pagination">
                <?php if ($halaman > 1): ?>
                    <a href="<?= e(urlHalaman($halaman - 1, $keyword, $jenis)) ?>">
                        Sebelumnya
                    </a>
                <?php else: ?>
                    <span class="disabled">Sebelumnya</span>
                <?php endif; ?>

                <span class="active">
                    Halaman <?= $halaman ?> dari <?= $totalHalaman ?>
                </span>

                <?php if ($halaman < $totalHalaman): ?>
                    <a href="<?= e(urlHalaman($halaman + 1, $keyword, $jenis)) ?>">
                        Berikutnya
                    </a>
                <?php else: ?>
                    <span class="disabled">Berikutnya</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
