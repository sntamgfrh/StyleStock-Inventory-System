<?php

require_once __DIR__ . '/../helpers/auth.php';
wajibLogin();

require_once __DIR__ . '/../config/database.php';

// Statistik utama
$totalProduk = (int) $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

$totalKategori = (int) $pdo->query(
    "SELECT COUNT(*) FROM categories"
)->fetchColumn();

$totalSupplier = (int) $pdo->query(
    "SELECT COUNT(*) FROM suppliers"
)->fetchColumn();

$totalStok = (int) $pdo->query(
    "SELECT COALESCE(SUM(stock), 0) FROM products"
)->fetchColumn();

// Statistik transaksi
$totalBarangMasuk = (int) $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE type = 'IN'"
)->fetchColumn();

$totalBarangKeluar = (int) $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0)
     FROM stock_movements
     WHERE type = 'OUT'"
)->fetchColumn();

// Produk dengan stok menipis
$stokMenipis = $pdo->query(
    "SELECT sku, name, stock, minimum_stock
     FROM products
     WHERE stock <= minimum_stock
     ORDER BY stock ASC, name ASC"
)->fetchAll();

// Lima transaksi terbaru
$transaksiTerbaru = $pdo->query(
    "SELECT sm.type, sm.quantity, sm.created_at,
            p.name AS product_name
     FROM stock_movements sm
     JOIN products p ON p.id = sm.product_id
     ORDER BY sm.created_at DESC, sm.id DESC
     LIMIT 5"
)->fetchAll();

// Informasi pengguna
$nama = htmlspecialchars(
    $_SESSION['user']['name'] ?? 'Pengguna',
    ENT_QUOTES,
    'UTF-8'
);

$role = htmlspecialchars(
    ucfirst($_SESSION['user']['role'] ?? 'staff'),
    ENT_QUOTES,
    'UTF-8'
);

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
    <title>Dashboard - StyleStock</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #F7E7B4;
            color: #4A2525;
            min-height: 100vh;
        }

        header {
            background: linear-gradient(135deg, #4A2525, #754242);
            color: #F7E7B4;
            padding: 22px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        header h1 {
            font-size: 25px;
        }

        header p {
            margin-top: 6px;
        }

        .logout {
            background: #F7E7B4;
            color: #4A2525;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        main {
            padding: 30px;
        }

        .welcome {
            margin-bottom: 24px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
        }

        .card {
            display: block;
            background: #fffaf0;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #d9c58d;
            box-shadow: 0 5px 14px rgba(74, 37, 37, .08);
            color: #4A2525;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
        }

        a.card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(74, 37, 37, .16);
        }

        a.card:focus-visible {
            outline: 3px solid #754242;
            outline-offset: 3px;
        }

        .card p {
            margin-bottom: 12px;
        }

        .number {
            font-size: 30px;
            font-weight: bold;
        }

        .card-hint {
            display: block;
            margin-top: 12px;
            font-size: 12px;
            opacity: .75;
        }

        .menu {
            margin-top: 28px;
            background: #fffaf0;
            padding: 24px;
            border-radius: 14px;
            border: 1px solid #d9c58d;
            box-shadow: 0 5px 14px rgba(74, 37, 37, .05);
        }

        .menu h3 {
            margin-bottom: 16px;
        }

        .menu a:not(.link-histori) {
            display: inline-block;
            margin: 0 8px 10px 0;
            padding: 11px 15px;
            background: #4A2525;
            color: #F7E7B4;
            border-radius: 8px;
            text-decoration: none;
        }

        .menu a:hover,
        .logout:hover {
            opacity: .85;
        }

        .report-title {
            margin-top: 28px;
            margin-bottom: 15px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            min-width: 550px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #d9c58d;
        }

        th {
            background: #4A2525;
            color: #F7E7B4;
        }

        tbody tr:hover {
            background: #fff1d0;
        }

        .badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 12px;
        }

        .badge-in {
            background: #d7efce;
            color: #245b26;
        }

        .badge-out {
            background: #f5d0ca;
            color: #8a2419;
        }

        .empty {
            padding: 18px;
            text-align: center;
        }

        .link-histori {
            display: inline-block;
            margin-top: 16px;
            color: #4A2525;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            main {
                padding: 18px;
            }

            header {
                padding: 20px;
            }

            .card,
            .menu {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <div>
        <h1>StyleStock</h1>
        <p>Sistem Informasi Inventaris Fashion</p>
    </div>

    <a class="logout" href="logout.php">Logout</a>
</header>

<main>

    <section class="welcome">
        <h2>Dashboard</h2>
        <p>Selamat datang, <?= $nama ?>! Role: <?= $role ?></p>
    </section>

    <!-- Statistik utama yang dapat diklik -->
    <section class="cards">

        <a class="card" href="produk.php">
            <p>Total Produk</p>
            <div class="number"><?= $totalProduk ?></div>
            <span class="card-hint">Klik untuk melihat produk</span>
        </a>

        <a class="card" href="kategori.php">
            <p>Total Kategori</p>
            <div class="number"><?= $totalKategori ?></div>
            <span class="card-hint">Klik untuk melihat kategori</span>
        </a>

        <a class="card" href="supplier.php">
            <p>Total Supplier</p>
            <div class="number"><?= $totalSupplier ?></div>
            <span class="card-hint">Klik untuk melihat supplier</span>
        </a>

        <a class="card" href="produk.php">
            <p>Total Stok</p>
            <div class="number"><?= $totalStok ?></div>
            <span class="card-hint">Klik untuk melihat stok produk</span>
        </a>

    </section>

    <!-- Menu utama -->
    <section class="menu">
        <h3>Menu Utama</h3>

        <a href="kategori.php">Data Kategori</a>
        <a href="produk.php">Data Produk</a>
        <a href="supplier.php">Data Supplier</a>
        <a href="barang-masuk.php">Barang Masuk</a>
        <a href="barang-keluar.php">Barang Keluar</a>
        <a href="histori.php">Histori Transaksi</a>

        <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
            <a href="users.php">Kelola Pengguna</a>
        <?php endif; ?>
    </section>

    <!-- Ringkasan transaksi -->
    <h3 class="report-title">Ringkasan Transaksi</h3>

    <section class="cards">

        <a class="card" href="barang-masuk.php">
            <p>Total Barang Masuk</p>
            <div class="number"><?= $totalBarangMasuk ?></div>
            <span class="card-hint">Klik untuk melihat barang masuk</span>
        </a>

        <a class="card" href="barang-keluar.php">
            <p>Total Barang Keluar</p>
            <div class="number"><?= $totalBarangKeluar ?></div>
            <span class="card-hint">Klik untuk melihat barang keluar</span>
        </a>

        <a class="card" href="produk.php">
            <p>Produk Stok Menipis</p>
            <div class="number"><?= count($stokMenipis) ?></div>
            <span class="card-hint">Klik untuk memeriksa produk</span>
        </a>

    </section>

    <!-- Laporan stok menipis -->
    <section class="menu">
        <h3>Laporan Stok Menipis</h3>

        <p>Produk yang stoknya sama dengan atau di bawah stok minimum.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Stok Saat Ini</th>
                        <th>Stok Minimum</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($stokMenipis)): ?>
                    <tr>
                        <td colspan="4" class="empty">
                            Semua produk memiliki stok di atas batas minimum.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stokMenipis as $item): ?>
                        <tr>
                            <td><?= e($item['sku']) ?></td>
                            <td><?= e($item['name']) ?></td>
                            <td><?= (int) $item['stock'] ?></td>
                            <td><?= (int) $item['minimum_stock'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a class="link-histori" href="produk.php">
            Lihat Data Produk
        </a>
    </section>

    <!-- Transaksi terbaru -->
    <section class="menu">
        <h3>5 Transaksi Terbaru</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Produk</th>
                        <th>Jenis Transaksi</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($transaksiTerbaru)): ?>
                    <tr>
                        <td colspan="4" class="empty">
                            Belum ada transaksi barang masuk atau keluar.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transaksiTerbaru as $transaksi): ?>
                        <tr>
                            <td>
                                <?= e(date(
                                    'd-m-Y H:i',
                                    strtotime($transaksi['created_at'])
                                )) ?>
                            </td>

                            <td><?= e($transaksi['product_name']) ?></td>

                            <td>
                                <?php if ($transaksi['type'] === 'IN'): ?>
                                    <span class="badge badge-in">
                                        Barang Masuk
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-out">
                                        Barang Keluar
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td><?= (int) $transaksi['quantity'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a class="link-histori" href="histori.php">
            Lihat Semua Histori Transaksi
        </a>
    </section>

</main>

</body>
</html>
