<?php
session_start();
require_once "../../config/db.php"; // Include your PDO database connection

$message = "";

// Function to hash password with SHA2 (256)
function hashPasswordSHA2($password) {
    return hash('sha256', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $message = "Please enter both username and password";
    } else {
        // Hash the entered password using SHA2
        $hashedPassword = hashPasswordSHA2($password);

        // Fetch user by username and SHA2-hashed password
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND password = :password AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([
            'username' => $username,
            'password' => $hashedPassword
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "Wrong username or password"; // single message for security
        } else {
            // Login successful, store session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['facility_id'] = $user['facility_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];

            // Optional: log login in system_logs
            $stmtLog = $conn->prepare("INSERT INTO system_logs (user_id, facility_id, action, module, description, ip_address, user_agent, created_at) VALUES (:user_id, :facility_id, 'Login', 'Authentication', 'User logged in', :ip, :agent, NOW())");
            $stmtLog->execute([
                'user_id' => $user['id'],
                'facility_id' => $user['facility_id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            header("Location: dashboard.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Prison Management</title>
    <link rel="stylesheet" href="../../assests/css/login.css">
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <h2>Prison Management System</h2>
        <p class="subtitle">Secure Access Portal</p>

        <?php if ($message): ?>
            <p class="message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</div>

</body>
</html>
