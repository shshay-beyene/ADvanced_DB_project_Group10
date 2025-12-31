<?php
// profile.php - User Profile
require_once 'header.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: logout.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Check if email is already taken by another user
            $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $checkStmt->execute([$email, $user_id]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = "Email already taken by another user";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        full_name = :full_name,
                        email = :email,
                        phone = :phone,
                        address = :address,
                        city = :city,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE user_id = :user_id
                ");
                
                $stmt->execute([
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'user_id' => $user_id
                ]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($new_password)) {
        $errors[] = "New password is required";
    } elseif (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "Please confirm your new password";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                $error = "Current password is incorrect";
            } else {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$new_password_hash, $user_id]);
                
                $success = "Password changed successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error changing password: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-user"></i> My Profile</h1>
    <p>Manage your account information and settings</p>
</div>

<div class="profile-container">
    <div class="profile-sidebar">
        <div class="profile-card">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
            <p class="profile-role">
                <span class="role-badge role-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </p>
            <p class="profile-email">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
            </p>
            <p class="profile-joined">
                <i class="fas fa-calendar-alt"></i> 
                Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
            </p>
        </div>
        
        <div class="profile-stats">
            <h4>Account Stats</h4>
            <ul>
                <li>
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders: 
                        <?php 
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo "0";
                        }
                        ?>
                    </span>
                </li>
                <?php if (isSeller()): ?>
                <li>
                    <i class="fas fa-box"></i>
                    <span>Products: 
                        <?php 
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo "0";
                        }
                        ?>
                    </span>
                </li>
                <?php endif; ?>
                <li>
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Last Login: 
                        <?php 
                        echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never';
                        ?>
                    </span>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="profile-content">
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <!-- Profile Update Form -->
        <div class="profile-section">
            <h3><i class="fas fa-user-edit"></i> Update Profile Information</h3>
            <form method="POST" action="">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small class="form-help">Username cannot be changed</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars($user['city']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Account Type</label>
                        <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Password Change Form -->
        <div class="profile-section">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
            <form method="POST" action="">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <small class="form-help">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Account Status -->
        <div class="profile-section">
            <h3><i class="fas fa-cog"></i> Account Settings</h3>
            <div class="settings-list">
                <div class="setting-item">
                    <div class="setting-info">
                        <h4>Account Status</h4>
                        <p>
                            <?php if ($user['is_active']): ?>
                            <span class="status-badge status-active"><i class="fas fa-check-circle"></i> Active</span>
                            <?php else: ?>
                            <span class="status-badge status-inactive"><i class="fas fa-times-circle"></i> Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-info">
                        <h4>Email Notifications</h4>
                        <p>Receive notifications about orders and promotions</p>
                    </div>
                    <div class="setting-action">
                        <label class="switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>