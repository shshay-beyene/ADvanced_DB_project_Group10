<?php
// order_details.php - View Order Details
require_once 'header.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();
    
    // Get order details with shipping info
    $stmt = $pdo->prepare("
        SELECT o.*, 
               s.shipping_address, 
               s.phone as shipping_phone, 
               s.status as shipping_status, 
               s.shipping_cost,
               s.tracking_number,
               s.estimated_delivery,
               s.actual_delivery
        FROM orders o 
        LEFT JOIN shipping s ON o.order_id = s.order_id 
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: orders.php");
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, 
               p.name, 
               p.brand, 
               p.model, 
               p.condition, 
               c.category_name,
               u.full_name as seller_name
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id 
        JOIN categories c ON p.category_id = c.category_id
        JOIN users u ON p.seller_id = u.user_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
} catch (PDOException $e) {
    die("Error loading order details: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1><i class="fas fa-receipt"></i> Order Details</h1>
    <p>Order :<?php echo $order['order_id']; ?></p>
</div>

<div class="order-details-container">
    <!-- Order Information Card -->
    <div class="order-info-card">
        <div class="order-header">
            <h3><i class="fas fa-info-circle"></i> Order Information</h3>
            <span class="order-status status-<?php echo $order['status']; ?>">
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
        </div>
        
        <div class="order-info-grid">
            <div class="info-item">
                <label><i class="fas fa-hashtag"></i> Order ID</label>
                <p>:<?php echo $order['order_id']; ?></p>
            </div>
            
            <div class="info-item">
                <label><i class="fas fa-calendar-alt"></i> Order Date</label>
                <p><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
            </div>
            
            <div class="info-item">
                <label><i class="fas fa-credit-card"></i> Payment Method</label>
                <p><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
            </div>
            
            <div class="info-item">
                <label><i class="fas fa-money-bill-wave"></i> Payment Status</label>
                <p><?php echo ucfirst($order['payment_status']); ?></p>
            </div>
        </div>
        
        <?php if (!empty($order['notes'])): ?>
        <div class="info-item">
            <label><i class="fas fa-sticky-note"></i> Order Notes</label>
            <div class="notes-content">
                <?php echo htmlspecialchars($order['notes']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Shipping Information Card -->
    <div class="shipping-info-card">
        <h3><i class="fas fa-truck"></i> Shipping Information</h3>
        
        <div class="shipping-details">
            <div class="shipping-item">
                <label>Shipping Status:</label>
                <span class="status-badge status-<?php echo $order['shipping_status']; ?>">
                    <?php echo ucfirst($order['shipping_status']); ?>
                </span>
            </div>
            
            <div class="shipping-item">
                <label>Shipping Address:</label>
                <p><?php echo htmlspecialchars($order['shipping_address']); ?></p>
            </div>
            
            <div class="shipping-item">
                <label>Contact Phone:</label>
                <p><?php echo htmlspecialchars($order['shipping_phone']); ?></p>
            </div>
            
            <div class="shipping-item">
                <label>Shipping Cost:</label>
                <p>ETB <?php echo number_format($order['shipping_cost'], 2); ?></p>
            </div>
            
            <?php if (!empty($order['tracking_number'])): ?>
            <div class="shipping-item">
                <label>Tracking Number:</label>
                <p class="tracking-number"><?php echo $order['tracking_number']; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($order['estimated_delivery'])): ?>
            <div class="shipping-item">
                <label>Estimated Delivery:</label>
                <p><?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($order['actual_delivery'])): ?>
            <div class="shipping-item">
                <label>Actual Delivery:</label>
                <p><?php echo date('F j, Y', strtotime($order['actual_delivery'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Items Card -->
    <div class="order-items-card">
        <h3><i class="fas fa-box"></i> Order Items (<?php echo count($order_items); ?>)</h3>
        
        <?php if ($order_items): ?>
        <div class="table-responsive">
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Seller</th>
                        <th>Category</th>
                        <th>Condition</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td>
                            <div class="product-info">
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($item['brand']); ?> <?php echo htmlspecialchars($item['model']); ?></small>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['seller_name']); ?></td>
                        <td><?php echo $item['category_name']; ?></td>
                        <td>
                            <span class="condition-badge condition-<?php echo $item['condition']; ?>">
                                <?php echo ucfirst($item['condition']); ?>
                            </span>
                        </td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>ETB <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>ETB <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5"></td>
                        <td><strong>Subtotal:</strong></td>
                        <td><strong>ETB <?php echo number_format($subtotal, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="5"></td>
                        <td><strong>Shipping:</strong></td>
                        <td><strong>ETB <?php echo number_format($order['shipping_cost'], 2); ?></strong></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="5"></td>
                        <td><strong>Total:</strong></td>
                        <td><strong class="total-amount">ETB <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="no-items">
            <i class="fas fa-box-open"></i>
            <p>No items found for this order.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Actions -->
    <div class="order-actions">
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
        
        <?php if ($order['status'] == 'pending'): ?>
        <a href="orders.php?action=cancel&id=<?php echo $order['order_id']; ?>" 
           class="btn btn-warning"
           onclick="return confirm('Are you sure you want to cancel this order? This will restore product stock.');">
            <i class="fas fa-times"></i> Cancel Order
        </a>
        <?php endif; ?>
        
        <?php if (in_array($order['status'], ['cancelled', 'delivered'])): ?>
        <a href="orders.php?action=delete&id=<?php echo $order['order_id']; ?>" 
           class="btn btn-danger"
           onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
            <i class="fas fa-trash"></i> Delete Order
        </a>
        <?php endif; ?>
        
        <a href="products.php" class="btn btn-primary">
            <i class="fas fa-shopping-cart"></i> Continue Shopping
        </a>
        
        <!-- Print Receipt Button -->
        <button onclick="window.print()" class="btn btn-info">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>
</div>

<?php require_once 'footer.php'; ?>