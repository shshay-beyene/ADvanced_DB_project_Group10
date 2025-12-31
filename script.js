// script.js - JavaScript for MekelleTech Recycle

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Form validation
    initFormValidation();
    
    // Password toggle
    initPasswordToggle();
    
    // Search functionality
    initSearch();
    
    // Product filtering
    initProductFiltering();
    
    // Cart functionality
    initCart();
    
    // Load initial cart count
    loadCartCount();
});

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        el.addEventListener('mouseenter', showTooltip);
        el.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = this.title;
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.left = (rect.left + window.scrollX) + 'px';
    tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 10) + 'px';
    
    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form:not(.no-validate)');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
}

function validateForm(e) {
    let valid = true;
    const form = e.target;
    const required = form.querySelectorAll('[required]');
    
    required.forEach(input => {
        if (!input.value.trim()) {
            showError(input, 'This field is required');
            valid = false;
        } else {
            clearError(input);
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                showError(input, 'Please enter a valid email address');
                valid = false;
            }
        }
        
        // Password confirmation
        if (input.name === 'confirm_password' && form.querySelector('[name="password"]')) {
            const password = form.querySelector('[name="password"]').value;
            if (input.value !== password) {
                showError(input, 'Passwords do not match');
                valid = false;
            }
        }
        
        // Price validation
        if (input.name === 'price' && input.value) {
            const price = parseFloat(input.value);
            if (price <= 0) {
                showError(input, 'Price must be greater than 0');
                valid = false;
            }
        }
        
        // Quantity validation
        if (input.name === 'quantity' && input.value) {
            const quantity = parseInt(input.value);
            if (quantity <= 0) {
                showError(input, 'Quantity must be at least 1');
                valid = false;
            }
        }
    });
    
    if (!valid) {
        e.preventDefault();
        e.stopPropagation();
        showNotification('Please fix the errors in the form', 'error');
    }
    
    return valid;
}

function showError(input, message) {
    clearError(input);
    
    const error = document.createElement('div');
    error.className = 'error-message';
    error.textContent = message;
    error.style.color = '#e74c3c';
    error.style.fontSize = '0.9rem';
    error.style.marginTop = '5px';
    
    input.parentNode.appendChild(error);
    input.classList.add('error');
}

function clearError(input) {
    const error = input.parentNode.querySelector('.error-message');
    if (error) {
        error.remove();
    }
    input.classList.remove('error');
}

// Password Toggle
function initPasswordToggle() {
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
}

// Search
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', debounce(function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        }, 300));
    }
}

// Product Filtering
function initProductFiltering() {
    const filterForm = document.querySelector('.filter-form');
    if (filterForm) {
        const inputs = filterForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    }
}

// Cart Functionality
function initCart() {
    // Add to Cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const quantity = this.getAttribute('data-quantity') || 1;
            
            if (productId) {
                addToCart(productId, quantity);
            }
        });
    });
    
    // Quick Add to Cart buttons
    document.querySelectorAll('.quick-add-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            addToCart(productId, 1, true); // true = quick add without redirect
        });
    });
    
    // Update quantity buttons in cart
    document.querySelectorAll('.update-quantity').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            if (input) {
                updateCartQuantity(productId, input.value);
            }
        });
    });
    
    // Remove from cart buttons
    document.querySelectorAll('.remove-from-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            if (confirm('Remove this item from cart?')) {
                removeFromCart(productId);
            }
        });
    });
    
    // Clear cart button
    const clearCartBtn = document.querySelector('.clear-cart-btn');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function(e) {
            if (!confirm('Clear entire shopping cart? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
}

// Add to Cart Function
function addToCart(productId, quantity = 1, quickAdd = false) {
    if (!productId) return;
    
    fetch('cart_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Product added to cart!', 'success');
            updateCartCount(data.cart_count);
            
            // Update button appearance
            const btn = document.querySelector(`[data-product-id="${productId}"]`);
            if (btn && !btn.classList.contains('quick-add-cart')) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Added';
                btn.classList.add('added');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('added');
                }, 2000);
            }
            
            // Redirect to cart if not quick add
            if (!quickAdd && data.redirect) {
                setTimeout(() => {
                    window.location.href = 'cart.php';
                }, 1500);
            }
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Update cart quantity
function updateCartQuantity(productId, quantity) {
    if (!productId || quantity < 1) return;
    
    fetch('cart_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cart updated', 'success');
            updateCartCount(data.cart_count);
            
            // Update subtotal if element exists
            const subtotalEl = document.querySelector(`.subtotal[data-product-id="${productId}"]`);
            if (subtotalEl && data.subtotal) {
                subtotalEl.textContent = 'ETB ' + formatCurrency(data.subtotal);
            }
            
            // Update total if element exists
            const totalEl = document.querySelector('.cart-total');
            if (totalEl && data.cart_total) {
                totalEl.textContent = 'ETB ' + formatCurrency(data.cart_total);
            }
        } else {
            showNotification(data.message || 'Failed to update cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Remove from cart
function removeFromCart(productId) {
    if (!productId) return;
    
    fetch('cart_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=remove&product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Product removed from cart', 'success');
            updateCartCount(data.cart_count);
            
            // Remove item from DOM
            const itemEl = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            if (itemEl) {
                itemEl.remove();
            }
            
            // Update totals
            const totalEl = document.querySelector('.cart-total');
            if (totalEl && data.cart_total) {
                totalEl.textContent = 'ETB ' + formatCurrency(data.cart_total);
            }
            
            // Show empty cart message if needed
            if (data.cart_count === 0) {
                showEmptyCartMessage();
            }
        } else {
            showNotification(data.message || 'Failed to remove item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Load initial cart count
function loadCartCount() {
    fetch('cart_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
        }
    })
    .catch(error => console.error('Error loading cart count:', error));
}

// Update cart count display
function updateCartCount(count) {
    // Update cart count badge
    let cartCount = document.querySelector('.cart-count');
    
    if (!cartCount) {
        const cartLink = document.getElementById('cart-link');
        if (cartLink) {
            cartCount = document.createElement('span');
            cartCount.className = 'cart-count';
            cartLink.appendChild(cartCount);
        }
    }
    
    if (cartCount) {
        cartCount.textContent = count;
        cartCount.style.display = count > 0 ? 'inline-flex' : 'none';
    }
}

// Show empty cart message
function showEmptyCartMessage() {
    const cartContainer = document.querySelector('.cart-items');
    if (cartContainer) {
        cartContainer.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-3x"></i>
                <h3>Your cart is empty</h3>
                <p>Add some products to your cart and they will appear here.</p>
                <a href="products.php" class="btn btn-primary btn-large">
                    <i class="fas fa-shopping-cart"></i> Browse Products
                </a>
            </div>
        `;
    }
}

// Notification system
function showNotification(message, type = 'info', duration = 5000) {
    // Remove existing notifications
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 
                           type === 'error' ? 'exclamation-circle' : 
                           'info-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove after duration
    const autoRemove = setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, duration);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        clearTimeout(autoRemove);
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    });
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-ET', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Add CSS for notifications and cart
const style = document.createElement('style');
style.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
        max-width: 400px;
    }
    
    .notification-success {
        background-color: #27ae60;
    }
    
    .notification-error {
        background-color: #e74c3c;
    }
    
    .notification-info {
        background-color: #3498db;
    }
    
    .notification-warning {
        background-color: #f39c12;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        margin-left: 10px;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .fade-out {
        animation: slideOut 0.3s ease forwards;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .cart-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e74c3c;
        color: white;
        font-size: 0.8rem;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 5px;
        min-width: 20px;
        height: 20px;
    }
    
    .add-to-cart-btn.added {
        background-color: #27ae60 !important;
    }
    
    .error {
        border-color: #e74c3c !important;
    }
    
    .error-message {
        color: #e74c3c;
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    .tooltip {
        position: absolute;
        background: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.9rem;
        z-index: 1000;
        white-space: nowrap;
    }
    
    .tooltip:before {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: #333;
    }
`;
document.head.appendChild(style);
// script.js - Simplified version without cart

document.addEventListener('DOMContentLoaded', function() {
    initFormValidation();
    initPasswordToggle();
    initSearch();
    initProductFiltering();
});

// Keep only these functions:
// validateForm, showError, clearError, initPasswordToggle, initSearch, initProductFiltering
// Remove all cart-related functions (addToCart, updateCartCount, etc.)