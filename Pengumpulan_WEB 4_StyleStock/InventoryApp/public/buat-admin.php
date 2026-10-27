<?php

require_once __DIR__ . '/../config/database.php';

$username = 'admin';
$password = 'StyleStock#2026';
$name = 'Administrator';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "SELECT id FROM users WHERE username = :username"
);
$stmt->execute(['username' => $username]);

$user = $stmt->fetch();

if ($user) {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET name = :name,
             password_hash = :password_hash,
             role = 'admin'
         WHERE username = :username"
    );

    $stmt->execute([
        'name' => $name,
        'password_hash' => $hash,
        'username' => $username
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, username, password_hash, role)
         VALUES (:name, :username, :password_hash, 'admin')"
    );

    $stmt->execute([
        'name' => $name,
        'username' => $username,
        'password_hash' => $hash
    ]);
}

echo 'Password admin StyleStock berhasil diperbarui.';
