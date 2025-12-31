<?php
// order_confirmation.php - Order Confirmation Page
require_once 'header.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // CORRECTED: Join with shipping table to get shipping_address
    $stmt = $pdo->prepare("
        SELECT o.*, s.shipping_address, s.phone as shipping_phone, 
               s.status as shipping_status, s.shipping_cost,
               COUNT(oi.order_item_id) as item_count
        FROM orders o 
        LEFT JOIN shipping s ON o.order_id = s.order_id 
        LEFT JOIN order_items oi ON o.order_id = oi.order_id 
        WHERE o.order_id = ? AND o.user_id = ?
        GROUP BY o.order_id, s.shipping_id
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: dashboard.php");
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.brand, p.model 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1><i class="fas fa-check-circle text-success"></i> Order Confirmed!</h1>
    <p>Thank you for your purchase. Your order has been received.</p>
</div>

<div class="confirmation-container">
    <div class="confirmation-card">
        <div class="confirmation-header">
            <h3><i class="fas fa-receipt"></i> Order Details</h3>
            <span class="order-id">Order <?php echo $order['order_id']; ?></span>
        </div>
        
        <div class="confirmation-body">
            <div class="confirmation-row">
                <div class="confirmation-col">
                    <h4><i class="fas fa-calendar-alt"></i> Order Date</h4>
                    <p><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
                </div>
                
                <div class="confirmation-col">
                    <h4><i class="fas fa-money-bill-wave"></i> Total Amount</h4>
                    <p class="total-amount">ETB <?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
                
                <div class="confirmation-col">
                    <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
                    <p><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                </div>
                
                <div class="confirmation-col">
                    <h4><i class="fas fa-truck"></i> Shipping Status</h4>
                    <p><span class="status-badge status-<?php echo $order['shipping_status']; ?>">
                        <?php echo ucfirst($order['shipping_status']); ?>
                    </span></p>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <div class="shipping-info">
                <h4><i class="fas fa-map-marker-alt"></i> Shipping Information</h4>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                <p><strong>Shipping Cost:</strong> ETB <?php echo number_format($order['shipping_cost'], 2); ?></p>
            </div>
            
            <div class="order-items">
                <h4><i class="fas fa-box"></i> Order Items (<?php echo $order['item_count']; ?>)</h4>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo $item['name']; ?></strong><br>
                                <small><?php echo $item['brand']; ?> <?php echo $item['model']; ?></small>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>ETB <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>ETB <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                            <td><strong>ETB <?php echo number_format($order['total_amount'] - $order['shipping_cost'], 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Shipping:</strong></td>
                            <td><strong>ETB <?php echo number_format($order['shipping_cost'], 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                            <td><strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if (!empty($order['notes'])): ?>
            <div class="order-notes">
                <h4><i class="fas fa-sticky-note"></i> Order Notes</h4>
                <p><?php echo htmlspecialchars($order['notes']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="confirmation-footer">
            <p>An email confirmation has been sent to <strong><?php echo $_SESSION['email']; ?></strong></p>
            <div class="confirmation-actions">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <a href="orders.php" class="btn btn-success">
                    <i class="fas fa-list"></i> View All Orders
                </a>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-shopping-cart"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>