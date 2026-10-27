<?php
require __DIR__ . '/../config/database.php';
foreach ($pdo->query('DESCRIBE categories') as $row) {
    echo $row['Field'] . PHP_EOL;
}
