<?php
// Подключаем конфигурационный файл
require_once 'config.php';

// Запрос для получения остатков
$query = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.unit,
        COALESCE(SUM(CASE 
            WHEN t.type = 'in' THEN t.quantity 
            WHEN t.type = 'out' THEN -t.quantity 
        END), 0) AS stock
    FROM Products p
    LEFT JOIN `Transactions` t ON p.id = t.product_id
    GROUP BY p.id, p.name, p.sku, p.unit
    ORDER BY p.name
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Ошибка выполнения запроса: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Склад</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .product-name {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 2px solid #764ba2;
            padding-bottom: 8px;
        }

        .product-sku {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .stock-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .stock-quantity {
            font-size: 2rem;
            font-weight: bold;
            color: #764ba2;
        }

        .stock-unit {
            color: #666;
            font-size: 1rem;
        }

        .low-stock {
            color: #e74c3c;
        }

        .no-stock {
            color: #95a5a6;
        }

        .status-dot {
            height: 12px;
            width: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .in-stock { background-color: #2ecc71; }
        .low-stock-dot { background-color: #f39c12; }
        .out-of-stock { background-color: #e74c3c; }

        .summary {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }

        .summary h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .summary p {
            color: #666;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Склад</h1>
            <p>Актуальная информация о наличии товаров</p>
        </div>

        <div class="summary">
            <h2>Общая статистика</h2>
            <p>Всего товаров: <?= count($products) ?> | 
               В наличии: <?= count(array_filter($products, fn($p) => $p['stock'] > 0)) ?> | 
               Нет в наличии: <?= count(array_filter($products, fn($p) => $p['stock'] == 0)) ?>
            </p>
        </div>

        <div class="products-grid">
            <?php foreach ($products as $product): 
                $stock = $product['stock'];
                $statusClass = '';
                $statusDot = '';
                
                if ($stock == 0) {
                    $statusClass = 'no-stock';
                    $statusDot = 'out-of-stock';
                } elseif ($stock < 10) {
                    $statusClass = 'low-stock';
                    $statusDot = 'low-stock-dot';
                } else {
                    $statusDot = 'in-stock';
                }
            ?>
                <div class="product-card">
                    <h2 class="product-name"><?= htmlspecialchars($product['name']) ?></h2>
                    <div class="product-sku">Артикул: <?= $product['sku'] ? htmlspecialchars($product['sku']) : 'Не указан' ?></div>
                    
                    <div class="stock-info">
                        <div>
                            <span class="status-dot <?= $statusDot ?>"></span>
                            <span>Остаток:</span>
                        </div>
                        <div class="stock-quantity <?= $statusClass ?>">
                            <?= number_format($stock, 2) ?>
                            <span class="stock-unit"><?= htmlspecialchars($product['unit']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="summary">
                <h2>Товары не найдены</h2>
                <p>В базе данных нет товаров или транзакций</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>