<?php
require_once 'auth.php';
requireAuth();

$user = getCurrentUser();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Склад - Главная</title>
</head>
<body>
    <h2>Добро пожаловать, <?php echo htmlspecialchars($user['username']); ?>!</h2>
    <p>Роль: <?php echo htmlspecialchars($user['role']); ?></p>
    
    <nav>
        <a href="product.php">Управление товарами</a> |
        <a href="transaction.php">Операции</a> |
        <a href="logout.php">Выход</a>
    </nav>
    
    <!-- Здесь будет основной контент системы -->
</body>
</html>