<?php
/**
 * User Login Page
 * Main login entry point
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Prison Management System</title>
    <link rel="stylesheet" href="assests/css/login.css">
    <style>
        .login-form-group {
            margin-bottom: 1rem;
        }
        .login-form-group input {
            width: 100%;
            padding: 12px;
            margin-bottom: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        .login-form-group input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 600;
        }
        .login-btn:hover {
            background: #1d4ed8;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        .login-links {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .login-links a {
            color: #2563eb;
            text-decoration: none;
        }
        .login-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2>PMS</h2>
            <p class="subtitle">Advanced Multi-Prison Management System</p>
            
            <div id="loginMessage"></div>
            
            <form id="loginForm">
                <div class="login-form-group">
                    <input type="text" id="username" name="username" placeholder="Username" required>
                </div>
                <div class="login-form-group">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="login-links">
                <a href="#" onclick="showReset(); return false;">Forgot Password?</a>
            </div>
        </div>
    </div>

    <script>
        const APP_URL = 'http://localhost/PMS';
        const API_URL = 'http://localhost/PMS/api';

        // Login form handler
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('loginMessage');
            
            // Show loading
            messageDiv.innerHTML = '<div class="alert alert-success">Logging in...</div>';
            
            fetch(API_URL + '/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<div class="alert alert-success">Login successful. Redirecting...</div>';
                    setTimeout(() => {
                        window.location.href = APP_URL + '/dashboard.php';
                    }, 1000);
                } else {
                    messageDiv.innerHTML = '<div class="alert alert-error">' + (data.message || 'Login failed') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.innerHTML = '<div class="alert alert-error">Connection error. Please try again.</div>';
            });
        });

        function showReset() {
            alert('Please contact your system administrator to reset your password.');
        }
    </script>
</body>
</html>