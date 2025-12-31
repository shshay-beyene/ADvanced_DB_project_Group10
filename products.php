<?php
// products.php - View All Products with Direct Buy
require_once 'header.php';

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
$condition = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Handle direct buy
if (isset($_GET['buy_now'])) {
    if (!isLoggedIn()) {
        header("Location: login.php?redirect=products.php");
        exit();
    }
    
    $product_id = intval($_GET['buy_now']);
    header("Location: checkout.php?product_id=" . $product_id);
    exit();
}
?>

<div class="page-header">
    <h1><i class="fas fa-mobile-alt"></i> Browse Electronics</h1>
    <p>Find second-hand electronics at great prices</p>
</div>

<!-- Search and Filter Form -->
<div class="filter-card">
    <form method="GET" action="" class="filter-form">
        <div class="form-row">
            <div class="form-group search-group">
                <input type="text" name="search" placeholder="Search products by name, brand, or model..." 
                       value="<?php echo $search; ?>" class="search-input">
                <button type="submit" class="btn btn-primary search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option value="0">All Categories</option>
                    <?php
                    try {
                        $pdo = getDBConnection();
                        $stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NOT NULL ORDER BY category_name");
                        $categories = $stmt->fetchAll();
                        
                        foreach ($categories as $cat) {
                            $selected = ($category == $cat['category_id']) ? 'selected' : '';
                            echo "<option value='{$cat['category_id']}' $selected>{$cat['category_name']}</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option>Error loading categories</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="">Any Condition</option>
                    <option value="new" <?php echo ($condition == 'new') ? 'selected' : ''; ?>>New</option>
                    <option value="like_new" <?php echo ($condition == 'like_new') ? 'selected' : ''; ?>>Like New</option>
                    <option value="good" <?php echo ($condition == 'good') ? 'selected' : ''; ?>>Good</option>
                    <option value="fair" <?php echo ($condition == 'fair') ? 'selected' : ''; ?>>Fair</option>
                    <option value="poor" <?php echo ($condition == 'poor') ? 'selected' : ''; ?>>Poor</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Price Range (ETB)</label>
                <div class="price-range">
                    <input type="number" name="min_price" placeholder="Min" 
                           value="<?php echo $min_price; ?>" min="0" step="10">
                    <span>to</span>
                    <input type="number" name="max_price" placeholder="Max" 
                           value="<?php echo $max_price; ?>" min="0" step="10">
                </div>
            </div>
            
            <div class="form-group">
                <label>Sort By</label>
                <select name="sort">
                    <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo ($sort == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo ($sort == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo ($sort == 'name') ? 'selected' : ''; ?>>Name A-Z</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="filter-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Products Grid -->
<div class="products-grid">
    <?php
    try {
        $pdo = getDBConnection();
        
        // Build query with filters
        $sql = "
            SELECT p.*, c.category_name, u.full_name as seller_name, u.city, u.phone as seller_phone 
            FROM products p 
            JOIN categories c ON p.category_id = c.category_id 
            JOIN users u ON p.seller_id = u.user_id 
            WHERE p.is_available = TRUE AND p.stock_quantity > 0
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (p.name ILIKE :search OR p.description ILIKE :search OR p.brand ILIKE :search OR p.model ILIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if ($category > 0) {
            $sql .= " AND p.category_id = :category";
            $params[':category'] = $category;
        }
        
        if (!empty($condition)) {
            $sql .= " AND p.condition = :condition";
            $params[':condition'] = $condition;
        }
        
        $sql .= " AND p.price BETWEEN :min_price AND :max_price";
        $params[':min_price'] = $min_price;
        $params[':max_price'] = $max_price;
        
        // Add sorting
        switch ($sort) {
            case 'price_low':
                $sql .= " ORDER BY p.price ASC";
                break;
            case 'price_high':
                $sql .= " ORDER BY p.price DESC";
                break;
            case 'name':
                $sql .= " ORDER BY p.name ASC";
                break;
            default: // newest
                $sql .= " ORDER BY p.created_at DESC";
                break;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        if ($products): 
            foreach ($products as $product): 
            ?>
            <div class="product-card" data-product-id="<?php echo $product['product_id']; ?>">
                <div class="product-header">
                    <span class="category-badge"><?php echo $product['category_name']; ?></span>
                    <?php if ($product['condition'] == 'new'): ?>
                    <span class="condition-badge new">New</span>
                    <?php elseif ($product['condition'] == 'like_new'): ?>
                    <span class="condition-badge like-new">Like New</span>
                    <?php endif; ?>
                    
                    <?php if ($product['stock_quantity'] <= 3): ?>
                    <span class="stock-badge low-stock">Only <?php echo $product['stock_quantity']; ?> left!</span>
                    <?php endif; ?>
                </div>
                
                <div class="product-image">
                    <?php
                    $icon_class = 'fa-mobile-alt'; // default
                    $category_lower = strtolower($product['category_name']);
                    
                    if (strpos($category_lower, 'phone') !== false) $icon_class = 'fa-mobile-alt';
                    elseif (strpos($category_lower, 'laptop') !== false) $icon_class = 'fa-laptop';
                    elseif (strpos($category_lower, 'tablet') !== false) $icon_class = 'fa-tablet-alt';
                    elseif (strpos($category_lower, 'camera') !== false) $icon_class = 'fa-camera';
                    elseif (strpos($category_lower, 'headphone') !== false) $icon_class = 'fa-headphones';
                    elseif (strpos($category_lower, 'charger') !== false) $icon_class = 'fa-charging-station';
                    elseif (strpos($category_lower, 'case') !== false) $icon_class = 'fa-briefcase';
                    elseif (strpos($category_lower, 'cable') !== false) $icon_class = 'fa-plug';
                    ?>
                    <i class="fas <?php echo $icon_class; ?>"></i>
                </div>
                
                <div class="product-info">
                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="brand-model">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['brand']); ?>
                        <?php if (!empty($product['model'])): ?>
                        - <?php echo htmlspecialchars($product['model']); ?>
                        <?php endif; ?>
                    </p>
                    
                    <div class="product-details">
                        <p><i class="fas fa-palette"></i> Color: <?php echo $product['color'] ? htmlspecialchars($product['color']) : 'Not specified'; ?></p>
                        <p><i class="fas fa-battery-three-quarters"></i> Condition: 
                            <span class="condition-text condition-<?php echo $product['condition']; ?>">
                                <?php echo ucfirst($product['condition']); ?>
                            </span>
                        </p>
                        <p><i class="fas fa-box"></i> Stock: 
                            <span class="stock-quantity <?php echo $product['stock_quantity'] <= 3 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo $product['stock_quantity']; ?> available
                            </span>
                        </p>
                        <p><i class="fas fa-user"></i> Seller: 
                            <span class="seller-info">
                                <?php echo htmlspecialchars($product['seller_name']); ?> 
                                <small>(<?php echo $product['city']; ?>)</small>
                            </span>
                        </p>
                    </div>
                    
                    <div class="product-footer">
                        <div class="price-section">
                            <div class="price">
                                <strong>ETB <?php echo number_format($product['price'], 2); ?></strong>
                                <?php if (isLoggedIn()): ?>
                                
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['average_rating'] > 0): ?>
                            <div class="rating">
                                <?php
                                $full_stars = floor($product['average_rating']);
                                $half_star = ($product['average_rating'] - $full_stars) >= 0.5;
                                $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                                
                                for ($i = 0; $i < $full_stars; $i++): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php endfor; ?>
                                
                                <?php if ($half_star): ?>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                <?php endif; ?>
                                
                                <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                    <i class="far fa-star text-warning"></i>
                                <?php endfor; ?>
                                
                                <small>(<?php echo number_format($product['average_rating'], 1); ?>)</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-actions">
                            <a href="product_details.php?id=<?php echo $product['product_id']; ?>" 
                               class="btn btn-sm btn-view" title="View Details">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            
                            <?php if (isLoggedIn()): ?>
                                <?php if ($product['stock_quantity'] > 0): ?>
                                <a href="checkout.php?product_id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-sm btn-buy">
                                    <i class="fas fa-shopping-bag"></i> Buy Now
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="fas fa-times"></i> Out of Stock
                                </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php?redirect=products.php" class="btn btn-sm btn-buy">
                                    <i class="fas fa-sign-in-alt"></i> Login to Buy
                                </a>
                            <?php endif; ?>
                            
                            <?php if (isSeller() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['seller_id']): ?>
                            <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" 
                               class="btn btn-sm btn-edit" title="Edit Product">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; 
        else: ?>
        <div class="no-products">
            <i class="fas fa-search fa-3x"></i>
            <h3>No products found</h3>
            <p>Try adjusting your search filters or <a href="add_product.php">add a product</a> if you're a seller.</p>
        </div>
        <?php endif;
    } catch (PDOException $e) {
        echo "<div class='error'>Error loading products: " . $e->getMessage() . "</div>";
    }
    ?>
</div>

<?php require_once 'footer.php'; ?>