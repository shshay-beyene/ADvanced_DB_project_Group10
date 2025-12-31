<?php
// index.php - Homepage/Dashboard
require_once 'header.php';
?>

<div class="hero">
    <h1>Welcome to Electronic Gadgets Platform: Mekelle</h1>
    <p class="subtitle">Your trusted marketplace for second-hand electronics in Mekelle</p>
    
    <div class="hero-stats">
        <div class="stat">
            <i class="fas fa-mobile-alt"></i>
            <h3>2000+</h3>
            <p>Electronic Items</p>
        </div>
        <div class="stat">
            <i class="fas fa-users"></i>
            <h3>1000+</h3>
            <p>Active Users</p>
        </div>
        <div class="stat">
            <i class="fas fa-shopping-cart"></i>
            <h3>500+</h3>
            <p>Successful Sales</p>
        </div>
    </div>
    
    <?php if (!isLoggedIn()): ?>
    <div class="cta-buttons">
        <a href="register.php" class="btn btn-primary btn-large">
            <i class="fas fa-user-plus"></i> Join Now
        </a>
        <a href="products.php" class="btn btn-secondary btn-large">
            <i class="fas fa-shopping-cart"></i> Browse Products
        </a>
    </div>
    <?php else: ?>
    <div class="cta-buttons">
        <a href="products.php" class="btn btn-primary btn-large">
            <i class="fas fa-shopping-cart"></i> Shop Now
        </a>
        <?php if (isSeller()): ?>
        <a href="add_product.php" class="btn btn-success btn-large">
            <i class="fas fa-plus"></i> Sell Your Item
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="features">
    <h2>Why Choose Electronic Gadgets Platform: Mekelle?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <i class="fas fa-shield-alt"></i>
            <h3>Verified Sellers</h3>
            <p>All sellers are verified to ensure authenticity</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-search"></i>
            <h3>Easy Search</h3>
            <p>Find exactly what you need with advanced filtering</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-truck"></i>
            <h3>Local Delivery</h3>
            <p>Get your items delivered within Mekelle</p>
        </div>
        <div class="feature-card">
            <i class="fas fa-handshake"></i>
            <h3>Safe Transactions</h3>
            <p>Secure payment and transaction system</p>
        </div>
    </div>
</div>

<div class="recent-products">
    <h2>Recently Added Items</h2>
    <?php
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.category_name, u.full_name as seller_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id 
            JOIN users u ON p.seller_id = u.user_id 
            WHERE p.is_available = TRUE 
            ORDER BY p.created_at DESC 
            LIMIT 6
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        if ($products): ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="product-info">
                    <h3><?php echo $product['name']; ?></h3>
                    <p class="brand"><?php echo $product['brand']; ?> - <?php echo $product['model']; ?></p>
                    <p class="condition">Condition: <?php echo ucfirst($product['condition']); ?></p>
                    <p class="price">ETB <?php echo number_format($product['price'], 2); ?></p>
                    <p class="seller">Sold by: <?php echo $product['seller_name']; ?></p>
                    <div class="product-actions">
                        <a href="products.php?view=<?php echo $product['product_id']; ?>" class="btn btn-sm">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php?add_to_cart=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-cart-plus"></i> Buy
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> View All Products
            </a>
        </div>
        <?php else: ?>
        <p class="no-data">No products available yet.</p>
        <?php endif;
    } catch (PDOException $e) {
        echo "<p class='error'>Error loading products: " . $e->getMessage() . "</p>";
    }
    ?>
</div>

<?php require_once 'footer.php'; ?>