<?php
// delete_product.php - Delete Product (Seller Only)
require_once 'config.php';
requireSeller();

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_products.php");
    exit();
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if product belongs to user
try {
    $pdo = getDBConnection();
    
    // Check if product has any orders
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as order_count 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.order_id 
        WHERE oi.product_id = ? AND o.status NOT IN ('cancelled')
    ");
    $checkStmt->execute([$product_id]);
    $result = $checkStmt->fetch();
    
    if ($result['order_count'] > 0) {
        // Product has orders, mark as unavailable instead of deleting
        $stmt = $pdo->prepare("
            UPDATE products 
            SET is_available = FALSE, stock_quantity = 0 
            WHERE product_id = ? AND seller_id = ?
        ");
        $stmt->execute([$product_id, $user_id]);
        $message = "Product has been marked as unavailable because it has existing orders.";
    } else {
        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ? AND seller_id = ?");
        $stmt->execute([$product_id, $user_id]);
        $message = "Product has been deleted successfully.";
    }
    
    // Check if deletion was successful
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = $message;
    } else {
        $_SESSION['error'] = "Product not found or you don't have permission to delete it.";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to my products page
header("Location: my_products.php");
exit();
?>