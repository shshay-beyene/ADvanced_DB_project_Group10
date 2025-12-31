<?php
// my_products.php - View Seller's Products
require_once 'header.php';
requireSeller();

$user_id = $_SESSION['user_id'];

// Handle success/error messages
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> My Products</h1>
    <p>Manage your listed electronics</p>
    <a href="add_product.php" class="btn btn-primary">
        <i class="fas fa-plus-circle"></i> Add New Product
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<div class="products-table-container">
    <?php
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.category_name, 
                   COUNT(oi.order_item_id) as total_sold,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.quantity ELSE 0 END), 0) as delivered_qty
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            LEFT JOIN order_items oi ON p.product_id = oi.product_id 
            LEFT JOIN orders o ON oi.order_id = o.order_id 
            WHERE p.seller_id = ? 
            GROUP BY p.product_id, c.category_name 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $products = $stmt->fetchAll();
        
        if ($products): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Sales</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <strong><?php echo $product['name']; ?></strong><br>
                        <small><?php echo $product['brand']; ?> <?php echo $product['model']; ?></small>
                    </td>
                    <td><?php echo $product['category_name']; ?></td>
                    <td>ETB <?php echo number_format($product['price'], 2); ?></td>
                    <td>
                        <?php echo $product['stock_quantity']; ?>
                        <?php if ($product['stock_quantity'] <= 0): ?>
                        <span class="badge badge-warning">Out of Stock</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="condition-badge <?php echo $product['condition']; ?>">
                            <?php echo ucfirst($product['condition']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($product['is_available']): ?>
                        <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                        <span class="status-badge status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="sales-info">
                            <span class="sales-count"><?php echo $product['delivered_qty']; ?> sold</span><br>
                            <small>ETB <?php echo number_format($product['delivered_qty'] * $product['price'], 2); ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                               class="btn btn-sm btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete_product.php?id=<?php echo $product['product_id']; ?>" 
                               class="btn btn-sm btn-delete" title="Delete"
                               onclick="return confirm('Are you sure? This will remove the product from sale.');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="products.php?view=<?php echo $product['product_id']; ?>" 
                               class="btn btn-sm btn-view" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Statistics Summary -->
        <div class="stats-summary">
            <?php
            $total_products = count($products);
            $active_products = array_filter($products, function($p) { return $p['is_available'] && $p['stock_quantity'] > 0; });
            $total_sales = array_sum(array_column($products, 'delivered_qty'));
            $total_revenue = array_sum(array_map(function($p) { return $p['delivered_qty'] * $p['price']; }, $products));
            ?>
            <div class="summary-card">
                <h4><i class="fas fa-box"></i> Total Products</h4>
                <p class="summary-number"><?php echo $total_products; ?></p>
            </div>
            <div class="summary-card">
                <h4><i class="fas fa-check-circle"></i> Active Listings</h4>
                <p class="summary-number"><?php echo count($active_products); ?></p>
            </div>
            <div class="summary-card">
                <h4><i class="fas fa-shopping-cart"></i> Total Sales</h4>
                <p class="summary-number"><?php echo $total_sales; ?></p>
            </div>
            <div class="summary-card">
                <h4><i class="fas fa-money-bill-wave"></i> Total Revenue</h4>
                <p class="summary-number">ETB <?php echo number_format($total_revenue, 2); ?></p>
            </div>
        </div>
        <?php else: ?>
        <div class="no-products">
            <i class="fas fa-box-open fa-3x"></i>
            <h3>No Products Listed Yet</h3>
            <p>You haven't listed any products for sale yet.</p>
            <a href="add_product.php" class="btn btn-primary btn-large">
                <i class="fas fa-plus-circle"></i> Add Your First Product
            </a>
        </div>
        <?php endif;
    } catch (PDOException $e) {
        echo "<div class='error'>Error loading products: " . $e->getMessage() . "</div>";
    }
    ?>
</div>

<?php require_once 'footer.php'; ?>