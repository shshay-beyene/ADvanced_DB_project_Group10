<?php
// add_product.php - Add New Product (Seller Only)
require_once 'header.php';
requireSeller();

$error = '';
$success = '';

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
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, description, brand, model, color, purchase_date, 
                    condition, specifications, price, stock_quantity, 
                    category_id, seller_id
                ) VALUES (
                    :name, :description, :brand, :model, :color, :purchase_date, 
                    :condition, :specifications, :price, :stock_quantity, 
                    :category_id, :seller_id
                )
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
                'seller_id' => $_SESSION['user_id']
            ]);
            
            $product_id = $pdo->lastInsertId();
            $success = "Product added successfully! Product ID: #{$product_id}";
            
            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Add New Product</h1>
    <p>Sell your second-hand electronics on MekelleTech Recycle</p>
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
            <a href="my_products.php" class="btn btn-sm">View My Products</a>
            <a href="add_product.php" class="btn btn-sm btn-primary">Add Another</a>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="product-form">
        <div class="form-row">
            <div class="form-group">
                <label for="name"><i class="fas fa-heading"></i> Product Name *</label>
                <input type="text" id="name" name="name" required 
                       placeholder="e.g., iPhone 13 Pro, Dell XPS 15"
                       value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="brand"><i class="fas fa-tag"></i> Brand *</label>
                <input type="text" id="brand" name="brand" required 
                       placeholder="e.g., Apple, Samsung, Dell"
                       value="<?php echo isset($_POST['brand']) ? $_POST['brand'] : ''; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description"><i class="fas fa-align-left"></i> Description</label>
            <textarea id="description" name="description" rows="3" 
                      placeholder="Describe your product, any issues, accessories included, etc."><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="model"><i class="fas fa-microchip"></i> Model</label>
                <input type="text" id="model" name="model" 
                       placeholder="e.g., iPhone 13 Pro, XPS 15 9510"
                       value="<?php echo isset($_POST['model']) ? $_POST['model'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="color"><i class="fas fa-palette"></i> Color</label>
                <input type="text" id="color" name="color" 
                       placeholder="e.g., Black, Silver, Graphite"
                       value="<?php echo isset($_POST['color']) ? $_POST['color'] : ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="purchase_date"><i class="fas fa-calendar-alt"></i> Purchase Date</label>
                <input type="date" id="purchase_date" name="purchase_date" 
                       value="<?php echo isset($_POST['purchase_date']) ? $_POST['purchase_date'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="condition"><i class="fas fa-battery-three-quarters"></i> Condition *</label>
                <select id="condition" name="condition" required>
                    <option value="new" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'new') ? 'selected' : ''; ?>>New</option>
                    <option value="like_new" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'like_new') ? 'selected' : ''; ?>>Like New</option>
                    <option value="good" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'good') ? 'selected' : ''; ?>>Good</option>
                    <option value="fair" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'fair') ? 'selected' : ''; ?>>Fair</option>
                    <option value="poor" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'poor') ? 'selected' : ''; ?>>Poor</option>
                </select>
            </div>
        </div>
        
        <div class="specifications">
            <h3><i class="fas fa-cogs"></i> Specifications</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="storage">Storage</label>
                    <input type="text" id="storage" name="storage" 
                           placeholder="e.g., 256GB, 512GB SSD"
                           value="<?php echo isset($_POST['storage']) ? $_POST['storage'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="ram">RAM</label>
                    <input type="text" id="ram" name="ram" 
                           placeholder="e.g., 8GB, 16GB"
                           value="<?php echo isset($_POST['ram']) ? $_POST['ram'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="battery">Battery</label>
                    <input type="text" id="battery" name="battery" 
                           placeholder="e.g., 4000mAh, 80Wh"
                           value="<?php echo isset($_POST['battery']) ? $_POST['battery'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="screen">Screen</label>
                    <input type="text" id="screen" name="screen" 
                           placeholder="e.g., 6.1inch, 15.6inch 4K"
                           value="<?php echo isset($_POST['screen']) ? $_POST['screen'] : ''; ?>">
                </div>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="price"><i class="fas fa-money-bill-wave"></i> Price (ETB) *</label>
                <input type="number" id="price" name="price" required min="0" step="0.01" 
                       placeholder="e.g., 850.00"
                       value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="stock_quantity"><i class="fas fa-box"></i> Stock Quantity *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" required min="1" 
                       value="<?php echo isset($_POST['stock_quantity']) ? $_POST['stock_quantity'] : 1; ?>">
            </div>
            
            <div class="form-group">
                <label for="category_id"><i class="fas fa-layer-group"></i> Category *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php
                    try {
                        $pdo = getDBConnection();
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
                            $selected = (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : '';
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
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-large">
                <i class="fas fa-plus-circle"></i> Add Product
            </button>
            <a href="my_products.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>