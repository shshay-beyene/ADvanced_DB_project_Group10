<?php
// edit_product.php - Edit Product (Seller Only)
require_once 'header.php';
requireSeller();

$error = '';
$success = '';

// Get product ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_products.php");
    exit();
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if product belongs to user
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND seller_id = ?");
    $stmt->execute([$product_id, $user_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header("Location: my_products.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $color = sanitize($_POST['color']);
    $purchase_date = $_POST['purchase_date'];
    $condition = $_POST['condition'];
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $category_id = intval($_POST['category_id']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $specifications = json_encode([
        'storage' => sanitize($_POST['storage']),
        'ram' => sanitize($_POST['ram']),
        'battery' => sanitize($_POST['battery']),
        'screen' => sanitize($_POST['screen'])
    ]);
    
    // Validation
    if (empty($name) || empty($brand) || $price <= 0 || $category_id <= 0) {
        $error = "Please fill in all required fields with valid data";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE products SET
                    name = :name,
                    description = :description,
                    brand = :brand,
                    model = :model,
                    color = :color,
                    purchase_date = :purchase_date,
                    condition = :condition,
                    specifications = :specifications,
                    price = :price,
                    stock_quantity = :stock_quantity,
                    category_id = :category_id,
                    is_available = :is_available,
                    updated_at = CURRENT_TIMESTAMP
                WHERE product_id = :product_id AND seller_id = :seller_id
            ");
            
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'brand' => $brand,
                'model' => $model,
                'color' => $color,
                'purchase_date' => $purchase_date ?: null,
                'condition' => $condition,
                'specifications' => $specifications,
                'price' => $price,
                'stock_quantity' => $stock_quantity,
                'category_id' => $category_id,
                'is_available' => $is_available,
                'product_id' => $product_id,
                'seller_id' => $user_id
            ]);
            
            $success = "Product updated successfully!";
            
            // Refresh product data
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "Error updating product: " . $e->getMessage();
        }
    }
}

// Parse specifications JSON
$specs = json_decode($product['specifications'], true) ?: [];
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Edit Product</h1>
    <p>Update your product information</p>
</div>

<div class="form-container">
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <div class="mt-2">
            <a href="my_products.php" class="btn btn-sm">Back to My Products</a>
            <a href="products.php" class="btn btn-sm btn-primary">View in Store</a>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="product-form">
        <div class="form-row">
            <div class="form-group">
                <label for="name"><i class="fas fa-heading"></i> Product Name *</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="brand"><i class="fas fa-tag"></i> Brand *</label>
                <input type="text" id="brand" name="brand" required 
                       value="<?php echo htmlspecialchars($product['brand']); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description"><i class="fas fa-align-left"></i> Description</label>
            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="model"><i class="fas fa-microchip"></i> Model</label>
                <input type="text" id="model" name="model" 
                       value="<?php echo htmlspecialchars($product['model']); ?>">
            </div>
            
            <div class="form-group">
                <label for="color"><i class="fas fa-palette"></i> Color</label>
                <input type="text" id="color" name="color" 
                       value="<?php echo htmlspecialchars($product['color']); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="purchase_date"><i class="fas fa-calendar-alt"></i> Purchase Date</label>
                <input type="date" id="purchase_date" name="purchase_date" 
                       value="<?php echo $product['purchase_date']; ?>">
            </div>
            
            <div class="form-group">
                <label for="condition"><i class="fas fa-battery-three-quarters"></i> Condition *</label>
                <select id="condition" name="condition" required>
                    <option value="new" <?php echo ($product['condition'] == 'new') ? 'selected' : ''; ?>>New</option>
                    <option value="like_new" <?php echo ($product['condition'] == 'like_new') ? 'selected' : ''; ?>>Like New</option>
                    <option value="good" <?php echo ($product['condition'] == 'good') ? 'selected' : ''; ?>>Good</option>
                    <option value="fair" <?php echo ($product['condition'] == 'fair') ? 'selected' : ''; ?>>Fair</option>
                    <option value="poor" <?php echo ($product['condition'] == 'poor') ? 'selected' : ''; ?>>Poor</option>
                </select>
            </div>
        </div>
        
        <div class="specifications">
            <h3><i class="fas fa-cogs"></i> Specifications</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="storage">Storage</label>
                    <input type="text" id="storage" name="storage" 
                           value="<?php echo isset($specs['storage']) ? htmlspecialchars($specs['storage']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="ram">RAM</label>
                    <input type="text" id="ram" name="ram" 
                           value="<?php echo isset($specs['ram']) ? htmlspecialchars($specs['ram']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="battery">Battery</label>
                    <input type="text" id="battery" name="battery" 
                           value="<?php echo isset($specs['battery']) ? htmlspecialchars($specs['battery']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="screen">Screen</label>
                    <input type="text" id="screen" name="screen" 
                           value="<?php echo isset($specs['screen']) ? htmlspecialchars($specs['screen']) : ''; ?>">
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="price"><i class="fas fa-money-bill-wave"></i> Price (ETB) *</label>
                <input type="number" id="price" name="price" required min="0" step="0.01" 
                       value="<?php echo $product['price']; ?>">
            </div>
            
            <div class="form-group">
                <label for="stock_quantity"><i class="fas fa-box"></i> Stock Quantity *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" required min="0" 
                       value="<?php echo $product['stock_quantity']; ?>">
            </div>
            
            <div class="form-group">
                <label for="category_id"><i class="fas fa-layer-group"></i> Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php
                    try {
                        // Get only child categories (for products)
                        $stmt = $pdo->query("
                            SELECT c.*, p.category_name as parent_name 
                            FROM categories c 
                            LEFT JOIN categories p ON c.parent_id = p.category_id 
                            WHERE c.parent_id IS NOT NULL 
                            ORDER BY p.category_name, c.category_name
                        ");
                        $categories = $stmt->fetchAll();
                        
                        $current_parent = '';
                        foreach ($categories as $cat) {
                            if ($current_parent != $cat['parent_name']) {
                                if ($current_parent != '') echo '</optgroup>';
                                echo '<optgroup label="' . $cat['parent_name'] . '">';
                                $current_parent = $cat['parent_name'];
                            }
                            $selected = ($product['category_id'] == $cat['category_id']) ? 'selected' : '';
                            echo "<option value='{$cat['category_id']}' $selected>{$cat['category_name']}</option>";
                        }
                        if ($current_parent != '') echo '</optgroup>';
                    } catch (PDOException $e) {
                        echo "<option>Error loading categories</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_available" value="1" 
                       <?php echo $product['is_available'] ? 'checked' : ''; ?>>
                <span><i class="fas fa-check-circle"></i> Available for sale</span>
            </label>
        </div>
        
        <div class="product-stats">
            <h4>Product Statistics</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Total Sales: <?php echo $product['total_sales']; ?></span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-star"></i>
                    <span>Average Rating: <?php echo number_format($product['average_rating'], 1); ?>/5</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-calendar"></i>
                    <span>Created: <?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-history"></i>
                    <span>Updated: <?php echo date('M d, Y', strtotime($product['updated_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-large">
                <i class="fas fa-save"></i> Update Product
            </button>
            <a href="my_products.php" class="btn btn-secondary">Cancel</a>
            <a href="delete_product.php?id=<?php echo $product_id; ?>" 
               class="btn btn-danger" 
               onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>