<?php
require __DIR__ . '/../config/database.php';

foreach ($pdo->query('DESCRIBE stock_movements') as $row) {
    echo $row['Field'] . PHP_EOL;
}
