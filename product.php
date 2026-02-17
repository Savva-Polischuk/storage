<?php
require_once 'auth.php';
requireAuth();
require_once 'config.php';

$message = '';

// Обработка добавления операции
if ($_POST['action'] ?? '' === 'add') {
    $product_id = intval($_POST['product_id']);
    $type = $_POST['type'];
    $quantity = intval($_POST['quantity']);
    $comment = trim($_POST['comment']);
    
    try {
        $pdo->beginTransaction();
        
        // Добавляем операцию
        $stmt = $pdo->prepare("INSERT INTO transactions (product_id, type, quantity, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $type, $quantity, $comment]);
        
        // Обновляем количество товара
        if ($type === 'in') {
            $stmt = $pdo->prepare("UPDATE transactions SET quantity = quantity + ? WHERE product_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE transactions SET quantity = quantity - ? WHERE product_id = ?");
        }
        $stmt->execute([$quantity, $product_id]);
        
        $pdo->commit();
        $message = "Операция успешно добавлена!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $message = "Ошибка: " . $e->getMessage();
    }
}

// Получение списка товаров для выпадающего списка
$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Получение операций (всех или для конкретного товара)
// $product_id_filter = $_GET['product_id'] ?? null;
// if ($product_id_filter) {
//     $stmt = $pdo->prepare("SELECT t.*, p.name as product_name 
//                           FROM transactions t 
//                           JOIN products p ON t.product_id = p.id 
//                           WHERE t.product_id = ? 
//                           ORDER BY t.created_at DESC");
//     $stmt->execute([$product_id_filter]);
//     $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
// } else {
//     $transactions = $pdo->query("SELECT t.*, p.name as product_name 
//                                 FROM transactions t 
//                                 JOIN products p ON t.product_id = p.id 
//                                 ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
// }
?>

<<!DOCTYPE html>
<html>
<head>
    <title>Операции с товарами</title>
    <style>
        /* Базовые стили */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eaeaea;
            font-weight: 600;
        }
        
        h3 {
            color: #3498db;
            margin: 25px 0 15px;
            font-weight: 500;
        }
        
        /* Ссылки */
        a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        /* Сообщения */
        .message {
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        /* Форма */
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            margin-top: 0;
        }
        
        form > div {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        select, input, textarea {
            width: 100%;
            max-width: 400px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        select:focus, input:focus, textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        /* Таблица */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #eaeaea;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .in { 
            color: #27ae60; 
            font-weight: 500;
        }
        
        .out { 
            color: #e74c3c; 
            font-weight: 500;
        }
        
        /* Адаптивность */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .form-section {
                padding: 15px;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            select, input, textarea {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <h2>Операции с товарами</h2>
    
    <a href="product.php">← Назад к товарам</a> | 
    <a href="transaction.php">Все операции</a>
    
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'Ошибка') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Форма добавления операции -->
    <div class="form-section">
        <h3>Добавить операцию</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div>
                <label>Товар:</label><br>
                <select name="product_id" required>
                    <option value="">Выберите товар</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Тип операции:</label><br>
                <select name="type" required>
                    <option value="in">Приход</option>
                    <option value="out">Расход</option>
                </select>
            </div>
            
            <div>
                <label>Количество:</label><br>
                <input type="number" name="quantity" min="1" required>
            </div>
            
            <div>
                <label>Комментарий:</label><br>
                <textarea name="comment" rows="3" style="width: 300px;"></textarea>
            </div>
            
            <br>
            <button type="submit">Добавить операцию</button>
        </form>
    </div>
    
    <!-- Список операций -->
    <h3>История операций</h3>
    <?php if (empty($transactions)): ?>
        <p>Нет операций.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Товар</th>
                    <th>Тип</th>
                    <th>Количество</th>
                    <th>Комментарий</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo $transaction['created_at']; ?></td>
                    <td><?php echo htmlspecialchars($transaction['product_name']); ?></td>
                    <td class="<?php echo $transaction['type']; ?>">
                        <?php echo $transaction['type'] === 'in' ? 'Приход' : 'Расход'; ?>
                    </td>
                    <td><?php echo $transaction['quantity']; ?></td>
                    <td><?php echo htmlspecialchars($transaction['comment']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>