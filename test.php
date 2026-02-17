<?php
class Test
{
    private static $passed = 0;
    private static $failed = 0;
    private static $testDB;

    public static function runAllTests()
    {
        echo "=== Запуск тестов ИС Склад ===\n\n";
        
        self::setupTestDatabase();
        
        // Запуск тестов товаров
        self::testProductCreation();
        self::testProductQuantityUpdate();
        self::testProductDuplicateSKU();
        
        // Запуск тестов операций
        self::testInTransaction();
        self::testOutTransaction();
        self::testTransactionHistory();
        
        // Запуск тестов аутентификации
        self::testUserAuthentication();
        self::testPasswordHashing();
        
        self::printResults();
    }
    
    private static function setupTestDatabase()
    {
        // Создаем тестовую БД в памяти
        self::$testDB = new PDO('sqlite::memory:');
        self::$testDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Создаем таблицы для тестов
        self::$testDB->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) UNIQUE,
                current_quantity INTEGER DEFAULT 0
            )
        ");
        
        self::$testDB->exec("
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                type VARCHAR(10) NOT NULL,
                quantity INTEGER NOT NULL,
                comment TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        self::$testDB->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user'
            )
        ");
    }
    
    private static function assertEquals($expected, $actual, $testName)
    {
        if ($expected === $actual) {
            self::$passed++;
            echo "✅ PASS: $testName\n";
        } else {
            self::$failed++;
            echo "❌ FAIL: $testName\n";
            echo "   Ожидалось: " . print_r($expected, true) . "\n";
            echo "   Получено: " . print_r($actual, true) . "\n";
        }
    }
    
    private static function assertTrue($condition, $testName)
    {
        self::assertEquals(true, $condition, $testName);
    }
    
    private static function assertFalse($condition, $testName)
    {
        self::assertEquals(false, $condition, $testName);
    }
    
    private static function printResults()
    {
        echo "\n=== Результаты тестирования ===\n";
        echo "Пройдено: " . self::$passed . "\n";
        echo "Провалено: " . self::$failed . "\n";
        echo "Всего: " . (self::$passed + self::$failed) . "\n";
        
        if (self::$failed === 0) {
            echo "🎉 Все тесты прошли успешно!\n";
        } else {
            echo "⚠️  Есть проваленные тесты!\n";
        }
    }

    // ТЕСТЫ ДЛЯ ТОВАРОВ
    private static function testProductCreation()
    {
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku, current_quantity) VALUES (?, ?, ?)");
        $result = $stmt->execute(['Тестовый товар', 'TEST001', 10]);
        
        self::assertTrue($result, "Создание товара");
        
        $product = self::$testDB->query("SELECT * FROM products WHERE sku = 'TEST001'")->fetch();
        self::assertEquals('Тестовый товар', $product['name'], "Проверка названия товара");
        self::assertEquals(10, $product['current_quantity'], "Проверка количества товара");
    }
    
    private static function testProductQuantityUpdate()
    {
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku, current_quantity) VALUES (?, ?, ?)");
        $stmt->execute(['Товар для обновления', 'UPDATE001', 5]);
        $productId = self::$testDB->lastInsertId();
        
        // Обновляем количество
        $stmt = self::$testDB->prepare("UPDATE products SET current_quantity = ? WHERE id = ?");
        $result = $stmt->execute([15, $productId]);
        
        self::assertTrue($result, "Обновление количества товара");
        
        $updatedProduct = self::$testDB->query("SELECT * FROM products WHERE id = $productId")->fetch();
        self::assertEquals(15, $updatedProduct['current_quantity'], "Проверка обновленного количества");
    }
    
    private static function testProductDuplicateSKU()
    {
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku) VALUES (?, ?)");
        $stmt->execute(['Товар 1', 'DUPLICATE_SKU']);
        
        // Пытаемся создать товар с тем же SKU
        try {
            $stmt = self::$testDB->prepare("INSERT INTO products (name, sku) VALUES (?, ?)");
            $result = $stmt->execute(['Товар 2', 'DUPLICATE_SKU']);
            self::assertFalse($result, "Проверка уникальности SKU - должна быть ошибка");
        } catch (PDOException $e) {
            self::assertTrue(true, "Проверка уникальности SKU - ошибка поймана правильно");
        }
    }

    // ТЕСТЫ ДЛЯ ОПЕРАЦИЙ
    private static function testInTransaction()
    {
        // Создаем товар
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku, current_quantity) VALUES (?, ?, ?)");
        $stmt->execute(['Товар для прихода', 'IN_TEST', 10]);
        $productId = self::$testDB->lastInsertId();
        
        // Создаем операцию прихода
        self::$testDB->beginTransaction();
        try {
            $stmt = self::$testDB->prepare("INSERT INTO transactions (product_id, type, quantity) VALUES (?, 'in', ?)");
            $stmt->execute([$productId, 5]);
            
            $stmt = self::$testDB->prepare("UPDATE products SET current_quantity = current_quantity + ? WHERE id = ?");
            $stmt->execute([5, $productId]);
            
            self::$testDB->commit();
            self::assertTrue(true, "Операция прихода товара");
            
            // Проверяем итоговое количество
            $product = self::$testDB->query("SELECT * FROM products WHERE id = $productId")->fetch();
            self::assertEquals(15, $product['current_quantity'], "Проверка количества после прихода");
            
        } catch (Exception $e) {
            self::$testDB->rollBack();
            self::assertFalse(true, "Операция прихода товара - ошибка: " . $e->getMessage());
        }
    }
    
    private static function testOutTransaction()
    {
        // Создаем товар
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku, current_quantity) VALUES (?, ?, ?)");
        $stmt->execute(['Товар для расхода', 'OUT_TEST', 20]);
        $productId = self::$testDB->lastInsertId();
        
        // Создаем операцию расхода
        self::$testDB->beginTransaction();
        try {
            $stmt = self::$testDB->prepare("INSERT INTO transactions (product_id, type, quantity) VALUES (?, 'out', ?)");
            $stmt->execute([$productId, 7]);
            
            $stmt = self::$testDB->prepare("UPDATE products SET current_quantity = current_quantity - ? WHERE id = ?");
            $stmt->execute([7, $productId]);
            
            self::$testDB->commit();
            self::assertTrue(true, "Операция расхода товара");
            
            // Проверяем итоговое количество
            $product = self::$testDB->query("SELECT * FROM products WHERE id = $productId")->fetch();
            self::assertEquals(13, $product['current_quantity'], "Проверка количества после расхода");
            
        } catch (Exception $e) {
            self::$testDB->rollBack();
            self::assertFalse(true, "Операция расхода товара - ошибка: " . $e->getMessage());
        }
    }
    
    private static function testTransactionHistory()
    {
        // Создаем товар
        $stmt = self::$testDB->prepare("INSERT INTO products (name, sku, current_quantity) VALUES (?, ?, ?)");
        $stmt->execute(['Товар для истории', 'HISTORY_TEST', 0]);
        $productId = self::$testDB->lastInsertId();
        
        // Добавляем несколько операций
        $operations = [
            ['in', 10, 'Первая поставка'],
            ['out', 3, 'Продажа'],
            ['in', 5, 'Допоставка']
        ];
        
        foreach ($operations as $op) {
            list($type, $quantity, $comment) = $op;
            $stmt = self::$testDB->prepare("INSERT INTO transactions (product_id, type, quantity, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$productId, $type, $quantity, $comment]);
        }
        
        // Проверяем количество операций
        $stmt = self::$testDB->prepare("SELECT COUNT(*) as count FROM transactions WHERE product_id = ?");
        $stmt->execute([$productId]);
        $count = $stmt->fetch()['count'];
        
        self::assertEquals(3, $count, "Проверка количества операций в истории");
        
        // Проверяем итоговое количество через расчет
        $stmt = self::$testDB->prepare("
            SELECT 
                SUM(CASE WHEN type = 'in' THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN type = 'out' THEN quantity ELSE 0 END) as total_out
            FROM transactions WHERE product_id = ?
        ");
        $stmt->execute([$productId]);
        $totals = $stmt->fetch();
        
        $calculatedQuantity = $totals['total_in'] - $totals['total_out'];
        self::assertEquals(12, $calculatedQuantity, "Проверка расчета остатка через историю операций");
    }

    // ТЕСТЫ ДЛЯ АУТЕНТИФИКАЦИИ
    private static function testUserAuthentication()
    {
        $username = "testuser";
        $password = "testpass123";
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = self::$testDB->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $result = $stmt->execute([$username, $hashedPassword]);
        
        self::assertTrue($result, "Создание тестового пользователя");
        
        // Проверяем аутентификацию
        $stmt = self::$testDB->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        $authSuccess = password_verify($password, $user['password']);
        self::assertTrue($authSuccess, "Проверка корректного пароля");
        
        $authFail = password_verify('wrongpassword', $user['password']);
        self::assertFalse($authFail, "Проверка некорректного пароля");
    }
    
    private static function testPasswordHashing()
    {
        $password = "mysecretpassword";
        $hash1 = password_hash($password, PASSWORD_DEFAULT);
        $hash2 = password_hash($password, PASSWORD_DEFAULT);
        
        // Хеши должны быть разными (из-за соли)
        self::assertTrue($hash1 !== $hash2, "Проверка уникальности хешей");
        
        // Но обе должны верифицироваться
        self::assertTrue(password_verify($password, $hash1), "Верификация первого хеша");
        self::assertTrue(password_verify($password, $hash2), "Верификация второго хеша");
        
        // Неверный пароль не должен верифицироваться
        self::assertFalse(password_verify('wrongpassword', $hash1), "Проверка неверного пароля");
    }
}

// Запуск всех тестов
Test::runAllTests();
?>