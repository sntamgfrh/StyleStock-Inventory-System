<?php
require __DIR__ . '/../config/database.php';

foreach ($pdo->query('DESCRIBE suppliers') as $row) {
    echo $row['Field'] . PHP_EOL;
}
