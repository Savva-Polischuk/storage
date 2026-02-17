<?php
require_once 'config.php';

// Создание первого пользователя (запустить один раз)
$username = "admin";
$password = "warehouse123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
if ($stmt->execute([$username, $hashed_password])) {
    echo "Пользователь создан: $username / $password";
} else {
    echo "Ошибка создания пользователя";
}
?>