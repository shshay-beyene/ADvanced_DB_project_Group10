<?php
// checkout.php - Direct Checkout Page
require_once 'header.php';
requireLogin();

// Check if product ID is provided
if (!isset($_GET['product_id']) || !is_numeric($_GET['product_id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['product_id']);

// Get product details
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, u.full_name as seller_name, u.city 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        JOIN users u ON p.seller_id = u.user_id 
        WHERE p.product_id = ? AND p.is_available = TRUE AND p.stock_quantity > 0
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = "Product not available or out of stock";
        header("Location: products.php");
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $payment_method = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : '';
        $shipping_address = isset($_POST['shipping_address']) ? sanitize($_POST['shipping_address']) : '';
        $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
        $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
        
        // Validation
        if (empty($payment_method) || empty($shipping_address) || empty($phone)) {
            throw new Exception("Please fill all required fields");
        }
        
        if ($quantity < 1 || $quantity > $product['stock_quantity']) {
            throw new Exception("Invalid quantity. Maximum available: " . $product['stock_quantity']);
        }
        
        // Calculate total
        $total_amount = $product['price'] * $quantity;
        
        // Apply discount if logged in
        if (isLoggedIn()) {
            $stmt = $pdo->prepare("SELECT calculate_discounted_price(?, ?::product_condition)");
            $stmt->execute([$product['price'], $product['condition']]);
            $discounted_price = $stmt->fetchColumn();
            $total_amount = $discounted_price * $quantity;
        }
        
        // Add shipping cost
        $shipping_cost = 50.00; // Fixed shipping cost
        $grand_total = $total_amount + $shipping_cost;
        
        // CORRECTED: Create order WITHOUT shipping_address
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total_amount, status, payment_method, notes) 
            VALUES (?, ?, 'pending', ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $grand_total,
            $payment_method,
            $notes
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Add order item
        $unit_price = isLoggedIn() ? $discounted_price : $product['price'];
        
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $product_id,
            $quantity,
            $unit_price
        ]);
        
        // Update product stock
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ?,
                total_sales = total_sales + ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE product_id = ?
        ");
        $stmt->execute([$quantity, $quantity, $product_id]);
        
        // CORRECTED: Create shipping record with shipping_address
        $stmt = $pdo->prepare("
            INSERT INTO shipping (order_id, shipping_address, phone, status, shipping_cost) 
            VALUES (?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$order_id, $shipping_address, $phone, $shipping_cost]);
        
        $pdo->commit();
        
        // Redirect to order confirmation
        $_SESSION['success'] = "Order placed successfully! Order ID: #{$order_id}";
        header("Location: order_confirmation.php?id={$order_id}");
        exit();
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Order failed: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-shopping-bag"></i> Checkout</h1>
    <p>Complete your purchase</p>
</div>

<div class="checkout-container">
    <div class="checkout-summary">
        <h3>Order Summary</h3>
        <div class="product-summary">
            <div class="product-image">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="product-details">
                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                <p><?php echo htmlspecialchars($product['brand']); ?> <?php echo htmlspecialchars($product['model']); ?></p>
                <p>Condition: <?php echo ucfirst($product['condition']); ?></p>
                <p>Seller: <?php echo htmlspecialchars($product['seller_name']); ?></p>
                <p>Available: <?php echo $product['stock_quantity']; ?> in stock</p>
            </div>
            <div class="product-price">
                <p class="original-price">ETB <?php echo number_format($product['price'], 2); ?></p>
                <?php if (isLoggedIn()): ?>
                <?php 
                $stmt = $pdo->prepare("SELECT calculate_discounted_price(?, ?::product_condition)");
                $stmt->execute([$product['price'], $product['condition']]);
                $discounted_price = $stmt->fetchColumn();
                ?>
               
                <?php endif; ?>
            </div>
        </div>
        
        <div class="price-breakdown">
            <div class="price-row">
                <span>Product Price</span>
                <span>
                    <?php if (isLoggedIn()): ?>
                    ETB <?php echo number_format($discounted_price, 2); ?>
                    <?php else: ?>
                    ETB <?php echo number_format($product['price'], 2); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="price-row">
                <span>Shipping</span>
                <span>ETB 50.00</span>
            </div>
            <div class="price-row total">
                <span>Total Amount</span>
                <span>
                    <?php if (isLoggedIn()): ?>
                    ETB <?php echo number_format($discounted_price + 50, 2); ?>
                    <?php else: ?>
                    ETB <?php echo number_format($product['price'] + 50, 2); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="checkout-form">
        <h3>Order Information</h3>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" 
                       max="<?php echo $product['stock_quantity']; ?>" required>
                <small>Maximum <?php echo $product['stock_quantity']; ?> available</small>
            </div>
            
            <div class="form-group">
                <label for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method" required>
                    <option value="">Select Payment Method</option>
                    <option value="cash_on_delivery">Cash on Delivery</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="tele_birr">Tele Birr</option>
                    <option value="cbe_birr">CBE Birr</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="shipping_address">Shipping Address *</label>
                <textarea id="shipping_address" name="shipping_address" rows="3" required 
                          placeholder="Enter your shipping address in Mekelle"><?php echo isset($_SESSION['address']) ? htmlspecialchars($_SESSION['address']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number *</label>
                <input type="tel" id="phone" name="phone" required 
                       value="<?php echo isset($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : ''; ?>"
                       placeholder="+251 XXX XXX XXX">
            </div>
            
            <div class="form-group">
                <label for="notes">Order Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="2" 
                          placeholder="Any special instructions for your order"></textarea>
            </div>
            
            <div class="form-actions">
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="submit" name="place_order" class="btn btn-success btn-large">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>