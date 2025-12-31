<?php
// orders.php - View and Manage Orders
require_once 'header.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle order actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($order_id > 0) {
        try {
            $pdo = getDBConnection();
            
            // Check if order belongs to user
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch();
            
            if ($order) {
                switch ($action) {
                    case 'cancel':
                        // Only cancel pending orders
                        if ($order['status'] == 'pending') {
                            // FIXED: Simple update without updated_at
                            $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
                            $stmt->execute([$order_id]);
                            
                            // Restore product stock - SIMPLIFIED QUERY
                            $stmt = $pdo->prepare("
                                SELECT oi.product_id, oi.quantity 
                                FROM order_items oi 
                                WHERE oi.order_id = ?
                            ");
                            $stmt->execute([$order_id]);
                            $order_items = $stmt->fetchAll();
                            
                            foreach ($order_items as $item) {
                                $stmt = $pdo->prepare("
                                    UPDATE products 
                                    SET stock_quantity = stock_quantity + ?, 
                                        total_sales = total_sales - ? 
                                    WHERE product_id = ?
                                ");
                                $stmt->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
                            }
                            
                            // Update shipping status
                            $stmt = $pdo->prepare("UPDATE shipping SET status = 'cancelled' WHERE order_id = ?");
                            $stmt->execute([$order_id]);
                            
                            $_SESSION['success'] = "Order #{$order_id} has been cancelled";
                        } else {
                            $_SESSION['error'] = "Only pending orders can be cancelled";
                        }
                        break;
                        
                    case 'delete':
                        // Only delete cancelled or delivered orders
                        if (in_array($order['status'], ['cancelled', 'delivered'])) {
                            // Delete shipping record first
                            $stmt = $pdo->prepare("DELETE FROM shipping WHERE order_id = ?");
                            $stmt->execute([$order_id]);
                            
                            // Delete order items
                            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                            $stmt->execute([$order_id]);
                            
                            // Delete order
                            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
                            $stmt->execute([$order_id]);
                            
                            $_SESSION['success'] = "Order #{$order_id} has been deleted";
                        } else {
                            $_SESSION['error'] = "Only cancelled or delivered orders can be deleted";
                        }
                        break;
                        
                    case 'view':
                        // Redirect to order details
                        header("Location: order_details.php?id={$order_id}");
                        exit();
                        break;
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    
    header("Location: orders.php");
    exit();
}

// Get user's orders
try {
    $pdo = getDBConnection();
    
    // Get total spent
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as total_spent,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total_amount ELSE 0 END), 0) as cancelled_amount
        FROM orders 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $order_stats = $stmt->fetch();
    
    // Get all orders with shipping info
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            COUNT(oi.order_item_id) as item_count,
            STRING_AGG(p.name, ', ') as product_names,
            s.status as shipping_status,
            s.shipping_address,
            s.shipping_cost
        FROM orders o 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        LEFT JOIN products p ON oi.product_id = p.product_id 
        LEFT JOIN shipping s ON o.order_id = s.order_id 
        WHERE o.user_id = ? 
        GROUP BY o.order_id, s.shipping_id, s.status, s.shipping_address, s.shipping_cost
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error loading orders: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1><i class="fas fa-history"></i> My Orders</h1>
    <p>View and manage your purchase history</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
    <?php unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
    <?php unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- Order Statistics -->
<div class="order-stats">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $order_stats['total_orders']; ?></h3>
            <p>Total Orders</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <h3>ETB <?php echo number_format($order_stats['total_spent'], 2); ?></h3>
            <p>Total Spent</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>ETB <?php echo number_format($order_stats['pending_amount'], 2); ?></h3>
            <p>Pending Amount</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3>ETB <?php echo number_format($order_stats['cancelled_amount'], 2); ?></h3>
            <p>Cancelled Amount</p>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="orders-table-container">
    <h3>Order History</h3>
    
    <?php if ($orders): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Shipping Address</th>
                    <th>Total Amount</th>
                    <th>Order Status</th>
                    <th>Shipping Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    // Format shipping address for display
                    $shipping_address = isset($order['shipping_address']) ? $order['shipping_address'] : 'Not specified';
                    $shipping_address_short = strlen($shipping_address) > 30 
                        ? substr($shipping_address, 0, 30) . '...' 
                        : $shipping_address;
                ?>
                <tr>
                    <td>
                        <strong>#<?php echo $order['order_id']; ?></strong>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?><br>
                        <small><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
                    </td>
                    <td>
                        <div class="order-items-info">
                            <span class="item-count"><?php echo $order['item_count']; ?> item(s)</span>
                            <?php if (!empty($order['product_names'])): ?>
                            <div class="item-names" title="<?php echo htmlspecialchars($order['product_names']); ?>">
                                <?php 
                                $product_names = explode(', ', $order['product_names']);
                                foreach (array_slice($product_names, 0, 2) as $name): 
                                ?>
                                <span class="product-name"><?php echo htmlspecialchars(trim($name)); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($product_names) > 2): ?>
                                <span class="more-items">+<?php echo count($product_names) - 2; ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="shipping-address" title="<?php echo htmlspecialchars($shipping_address); ?>">
                            <?php echo htmlspecialchars($shipping_address_short); ?>
                        </div>
                    </td>
                    <td>
                        <div class="order-total">
                            <strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong><br>
                            <?php if (isset($order['shipping_cost'])): ?>
                            <small class="text-muted">Shipping: ETB <?php echo number_format($order['shipping_cost'], 2); ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <i class="fas fa-<?php 
                                switch($order['status']) {
                                    case 'pending': echo 'clock'; break;
                                    case 'confirmed': echo 'check-circle'; break;
                                    case 'shipped': echo 'shipping-fast'; break;
                                    case 'delivered': echo 'box-check'; break;
                                    case 'cancelled': echo 'times-circle'; break;
                                    default: echo 'circle';
                                }
                            ?>"></i>
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (isset($order['shipping_status'])): ?>
                        <span class="status-badge status-<?php echo $order['shipping_status']; ?>">
                            <i class="fas fa-<?php 
                                switch($order['shipping_status']) {
                                    case 'pending': echo 'clock'; break;
                                    case 'shipped': echo 'shipping-fast'; break;
                                    case 'delivered': echo 'box-check'; break;
                                    case 'cancelled': echo 'times-circle'; break;
                                    default: echo 'truck';
                                }
                            ?>"></i>
                            <?php echo ucfirst($order['shipping_status']); ?>
                        </span>
                        <?php else: ?>
                        <span class="status-badge">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" 
                               class="btn btn-sm btn-view" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                             <?php if ($order['status'] == 'pending'): ?>
                           <a href="update_order.php?id=<?php echo $order['order_id']; ?>" 
                            class="btn btn-sm btn-primary" title="Update Order">
                            <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($order['status'] == 'pending'): ?>
                            <a href="orders.php?action=cancel&id=<?php echo $order['order_id']; ?>" 
                               class="btn btn-sm btn-warning" title="Cancel Order"
                               onclick="return confirm('Are you sure you want to cancel this order?');">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['status'], ['cancelled', 'delivered'])): ?>
                            <a href="orders.php?action=delete&id=<?php echo $order['order_id']; ?>" 
                               class="btn btn-sm btn-danger" title="Delete Order"
                               onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-orders">
        <i class="fas fa-shopping-bag fa-3x"></i>
        <h3>No Orders Yet</h3>
        <p>You haven't placed any orders yet. <a href="products.php">Browse products</a> to make your first purchase!</p>
        <a href="products.php" class="btn btn-primary btn-large">
            <i class="fas fa-shopping-cart"></i> Shop Now
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Order Status Legend -->
<div class="status-legend">
    <h4>Order Status Legend</h4>
    <div class="legend-items">
        <div class="legend-item">
            <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
            <span>Order placed but not confirmed</span>
        </div>
        <div class="legend-item">
            <span class="status-badge status-confirmed"><i class="fas fa-check-circle"></i> Confirmed</span>
            <span>Order confirmed by seller</span>
        </div>
        <div class="legend-item">
            <span class="status-badge status-shipped"><i class="fas fa-shipping-fast"></i> Shipped</span>
            <span>Order shipped to your address</span>
        </div>
        <div class="legend-item">
            <span class="status-badge status-delivered"><i class="fas fa-box-check"></i> Delivered</span>
            <span>Order delivered successfully</span>
        </div>
        <div class="legend-item">
            <span class="status-badge status-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>
            <span>Order cancelled by you or seller</span>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>