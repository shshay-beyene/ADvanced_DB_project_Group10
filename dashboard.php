<?php
// dashboard.php - User Dashboard
require_once 'header.php';
requireLogin();

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
?>

<div class="dashboard-header">
    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
    <p>Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
</div>

<div class="dashboard-grid">
    <!-- User Stats -->
    <div class="dashboard-card stats-card">
        <h3><i class="fas fa-chart-bar"></i> Your Statistics</h3>
        <?php
        try {
            // Get user statistics
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
                    (SELECT COUNT(*) FROM products WHERE seller_id = ?) as total_products,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND status = 'delivered') as total_spent
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $stats = $stmt->fetch();
            ?>
            <div class="stats-grid">
                <div class="stat-item">
                    <i class="fas fa-shopping-cart"></i>
                    <h4><?php echo $stats['total_orders']; ?></h4>
                    <p>Total Orders</p>
                </div>
                <?php if (isSeller()): ?>
                <div class="stat-item">
                    <i class="fas fa-box"></i>
                    <h4><?php echo $stats['total_products']; ?></h4>
                    <p>Products Listed</p>
                </div>
                <?php endif; ?>
                <div class="stat-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <h4>ETB <?php echo number_format($stats['total_spent'], 2); ?></h4>
                    <p>Total Spent</p>
                </div>
            </div>
        <?php } catch (PDOException $e) {
            echo "<p class='error'>Error loading statistics: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="dashboard-card actions-card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="actions-grid">
            <a href="products.php" class="action-btn">
                <i class="fas fa-shopping-cart"></i>
                <span>Browse Products</span>
            </a>
            <?php if (isSeller()): ?>
            <a href="add_product.php" class="action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Sell New Item</span>
            </a>
            <a href="my_products.php" class="action-btn">
                <i class="fas fa-box"></i>
                <span>My Products</span>
            </a>
            <?php endif; ?>
            <a href="profile.php" class="action-btn">
                <i class="fas fa-user-edit"></i>
                <span>Edit Profile</span>
            </a>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="dashboard-card orders-card">
        <h3><i class="fas fa-history"></i> Recent Orders</h3>
        <?php
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, COUNT(oi.order_item_id) as item_count 
                FROM orders o 
                LEFT JOIN order_items oi ON o.order_id = oi.order_id 
                WHERE o.user_id = ? 
                GROUP BY o.order_id 
                ORDER BY o.order_date DESC 
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $orders = $stmt->fetchAll();
            
            if ($orders): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>ETB <?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $order['item_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-right">
                <a href="dashboard.php?view=orders" class="btn btn-sm">View All Orders</a>
            </div>
            <?php else: ?>
            <p class="no-data">No orders yet. <a href="products.php">Start shopping!</a></p>
            <?php endif;
        } catch (PDOException $e) {
            echo "<p class='error'>Error loading orders: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <!-- Recent Products (For Sellers) -->
    <?php if (isSeller()): ?>
    <div class="dashboard-card products-card">
        <h3><i class="fas fa-boxes"></i> Recent Products</h3>
        <?php
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM products 
                WHERE seller_id = ? 
                ORDER BY created_at DESC 
                LIMIT 4
            ");
            $stmt->execute([$user_id]);
            $products = $stmt->fetchAll();
            
            if ($products): ?>
            <div class="product-list-mini">
                <?php foreach ($products as $product): ?>
                <div class="product-item-mini">
                    <div class="product-info-mini">
                        <h4><?php echo $product['name']; ?></h4>
                        <p class="price">ETB <?php echo number_format($product['price'], 2); ?></p>
                        <p class="stock">Stock: <?php echo $product['stock_quantity']; ?></p>
                    </div>
                    <div class="product-actions-mini">
                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                           class="btn btn-sm btn-edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-right">
                <a href="my_products.php" class="btn btn-sm">View All Products</a>
            </div>
            <?php else: ?>
            <p class="no-data">No products listed yet. <a href="add_product.php">Add your first product!</a></p>
            <?php endif;
        } catch (PDOException $e) {
            echo "<p class='error'>Error loading products: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Recent Activity -->
<div class="dashboard-card">
    <h3><i class="fas fa-bell"></i> Recent Activity</h3>
    <?php
    try {
        // Get recent activity
        $stmt = $pdo->prepare("
            (SELECT 'order' as type, order_date as date, CONCAT('Order placed #', order_id) as description 
             FROM orders WHERE user_id = ?)
            UNION ALL
            (SELECT 'product' as type, created_at as date, CONCAT('Product added: ', name) as description 
             FROM products WHERE seller_id = ?)
            ORDER BY date DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id]);
        $activities = $stmt->fetchAll();
        
        if ($activities): ?>
        <ul class="activity-list">
            <?php foreach ($activities as $activity): ?>
            <li class="activity-item">
                <i class="fas fa-<?php echo $activity['type'] == 'order' ? 'shopping-cart' : 'box'; ?>"></i>
                <div class="activity-content">
                    <p><?php echo $activity['description']; ?></p>
                    <small><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></small>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="no-data">No recent activity</p>
        <?php endif;
    } catch (PDOException $e) {
        echo "<p class='error'>Error loading activity: " . $e->getMessage() . "</p>";
    }
    ?>
</div>

<?php require_once 'footer.php'; ?>