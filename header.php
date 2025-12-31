<?php
// header.php - Common Header
// Start output buffering to prevent header errors
ob_start();

// Include config
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electronic Gadgets Platform: Mekelle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-recycle"></i>
                <span>Electronic Gadgets Platform: Mekelle</span>
            </a>
            
            <div class="nav-links">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="products.php"><i class="fas fa-mobile-alt"></i> Products</a>
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="orders.php"><i class="fas fa-history"></i> My Orders</a>
                    <?php if (isSeller()): ?>
                        <a href="my_products.php"><i class="fas fa-box"></i> My Products</a>
                        <a href="add_product.php"><i class="fas fa-plus-circle"></i> Sell Item</a>
                    <?php endif; ?>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="register-btn"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
            
            <div class="user-info">
                <?php if (isLoggedIn() && isset($_SESSION['full_name'])): ?>
                    <span class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                <?php elseif (isLoggedIn()): ?>
                    <span class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container main-content">