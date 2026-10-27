<?php
require __DIR__ . '/../config/database.php';

foreach ($pdo->query('DESCRIBE products') as $row) {
    echo $row['Field'] . PHP_EOL;
}
