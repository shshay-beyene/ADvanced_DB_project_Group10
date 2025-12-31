<?php
// register.php - User Registration
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $role = $_POST['role']; // buyer or seller
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Please fill in all required fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if username or email already exists
            $checkStmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $checkStmt->execute(['username' => $username, 'email' => $email]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = "Username or email already exists";
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, phone, address, role) 
                    VALUES (:username, :email, :password_hash, :full_name, :phone, :address, :role)
                ");
                
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'address' => $address,
                    'role' => $role
                ]);
                
                $success = "Registration successful! You can now login.";
                header("Refresh: 2; URL=login.php");
            }
        } catch (PDOException $e) {
            $error = "Registration error: " . $e->getMessage();
        }
    }
}
?>

<?php require_once 'header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <h2><i class="fas fa-user-plus"></i> Create New Account</h2>
        
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
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username *</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Choose a username" 
                           value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="At least 6 characters" minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm your password">
                </div>
            </div>
            
            <div class="form-group">
                <label for="full_name"><i class="fas fa-id-card"></i> Full Name *</label>
                <input type="text" id="full_name" name="full_name" required 
                       placeholder="Enter your full name" 
                       value="<?php echo isset($_POST['full_name']) ? $_POST['full_name'] : ''; ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="+251 XXX XXX XXX" 
                           value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> Account Type *</label>
                    <select id="role" name="role" required>
                        <option value="buyer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'buyer') ? 'selected' : ''; ?>>Buyer</option>
                        <option value="seller" <?php echo (isset($_POST['role']) && $_POST['role'] == 'seller') ? 'selected' : ''; ?>>Seller</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address"><i class="fas fa-map-marker-alt"></i> Address (Mekelle)</label>
                <textarea id="address" name="address" rows="2" 
                          placeholder="Enter your address in Mekelle"><?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </div>
            
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>