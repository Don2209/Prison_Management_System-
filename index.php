<?php
/**
 * User Login Page
 * Main login entry point
 */

// Load config first — handles session ini_set, session_start, and APP_URL
require_once __DIR__ . '/config/app.php';

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
    <title>Login — Prison Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base:      #0d1117;
            --bg-card:      #161b22;
            --bg-panel:     #0d1117;
            --border:       #21262d;
            --border-focus: #1f6feb;
            --text-primary: #e6edf3;
            --text-muted:   #8b949e;
            --accent:       #1f6feb;
            --accent-hover: #388bfd;
            --danger:       #f85149;
            --success:      #3fb950;
            --input-bg:     #0d1117;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
        }

        /* Animated grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(31,111,235,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(31,111,235,.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* Glow orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            animation: drift 12s ease-in-out infinite alternate;
        }
        .orb-1 { width: 500px; height: 500px; background: rgba(31,111,235,.18); top: -150px; left: -100px; }
        .orb-2 { width: 400px; height: 400px; background: rgba(88,166,255,.12); bottom: -100px; right: -80px; animation-delay: -6s; }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(30px, 20px) scale(1.05); }
        }

        /* Page layout */
        .page-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
        }

        /* Left branding panel */
        .brand-panel {
            flex: 1;
            display: none;
            flex-direction: column;
            justify-content: center;
            padding: 60px 64px;
            background: linear-gradient(155deg, #0d1117 0%, #0d1117 60%, #101728 100%);
            border-right: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        @media (min-width: 1024px) { .brand-panel { display: flex; } }

        .brand-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 50%, rgba(31,111,235,.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 56px;
        }
        .brand-logo-icon {
            width: 52px;
            height: 52px;
            background: var(--accent);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            box-shadow: 0 0 0 8px rgba(31,111,235,.15);
        }
        .brand-logo span {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.3px;
        }
        .brand-logo small {
            display: block;
            font-size: .72rem;
            font-weight: 400;
            color: var(--text-muted);
            letter-spacing: .5px;
        }

        .brand-headline {
            font-size: 2.6rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -.5px;
            margin-bottom: 18px;
            color: var(--text-primary);
        }
        .brand-headline span { color: var(--accent-hover); }

        .brand-sub {
            font-size: .95rem;
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 380px;
            margin-bottom: 48px;
        }

        .brand-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .stat-card {
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .stat-card .stat-num {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-hover);
        }
        .stat-card .stat-label {
            font-size: .72rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-top: 2px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(31,111,235,.12);
            border: 1px solid rgba(31,111,235,.3);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: .75rem;
            color: var(--accent-hover);
            font-weight: 500;
            margin-bottom: 28px;
        }
        .brand-badge i { font-size: .65rem; }

        /* Login panel */
        .login-panel {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 32px;
            background: var(--bg-panel);
        }
        @media (min-width: 1024px) { .login-panel { padding: 60px 56px; } }

        /* Mobile logo */
        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }
        @media (min-width: 1024px) { .mobile-brand { display: none; } }
        .mobile-brand-icon {
            width: 44px; height: 44px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: #fff;
        }
        .mobile-brand span { font-size: 1rem; font-weight: 700; }
        .mobile-brand small { display: block; font-size: .7rem; color: var(--text-muted); }

        /* Form header */
        .form-header { margin-bottom: 32px; }
        .form-header h1 {
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -.4px;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .form-header p { font-size: .87rem; color: var(--text-muted); }

        /* Alert */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 20px;
            animation: fadeSlide .25s ease;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-error   { background: rgba(248,81,73,.12);  border: 1px solid rgba(248,81,73,.35);  color: #f85149; }
        .alert-success { background: rgba(63,185,80,.12);  border: 1px solid rgba(63,185,80,.35);  color: #3fb950; }
        .alert-loading { background: rgba(31,111,235,.1);  border: 1px solid rgba(31,111,235,.3);  color: var(--accent-hover); }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Form groups */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 7px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrap .input-icon {
            position: absolute;
            left: 13px;
            color: var(--text-muted);
            font-size: .85rem;
            pointer-events: none;
            transition: color .2s;
        }
        .input-wrap input {
            width: 100%;
            padding: 11px 14px 11px 38px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: .9rem;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-wrap input::placeholder { color: var(--text-muted); }
        .input-wrap input:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(31,111,235,.18);
        }

        .input-icon-right {
            position: absolute;
            right: 13px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: .85rem;
            padding: 4px;
            transition: color .2s;
        }
        .input-icon-right:hover { color: var(--text-primary); }

        /* Forgot link row */
        .form-row-between {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 22px;
            margin-top: -8px;
        }
        .form-row-between a {
            font-size: .8rem;
            color: var(--accent-hover);
            text-decoration: none;
            transition: color .2s;
        }
        .form-row-between a:hover { color: #fff; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .2s, box-shadow .2s, transform .1s;
            position: relative;
            overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 60%);
        }
        .btn-login:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 18px rgba(31,111,235,.45);
        }
        .btn-login:active { transform: scale(.98); }
        .btn-login:disabled { opacity: .65; cursor: not-allowed; }
        .btn-login .spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: .75rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Footer note */
        .login-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: .75rem;
            color: var(--text-muted);
        }
        .login-footer a { color: var(--accent-hover); text-decoration: none; }

        /* Input shake on error */
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60%  { transform: translateX(-6px); }
            40%,80%  { transform: translateX(6px); }
        }
        .shake { animation: shake .35s ease; }

        /* Security badge row */
        .security-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .72rem;
            color: var(--text-muted);
            margin-top: 14px;
            justify-content: center;
        }
        .security-row i { color: var(--success); }
    </style>
</head>
<body>

    <!-- Ambient orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="page-wrapper">

        <!-- Left branding panel (desktop only) -->
        <div class="brand-panel">
            <div class="brand-logo">
                <div class="brand-logo-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div>
                    <span>PMS</span>
                    <small>PRISON MANAGEMENT SYSTEM</small>
                </div>
            </div>

            <div class="brand-badge">
                <i class="fa-solid fa-circle" style="color:#3fb950;"></i>
                System Online &amp; Operational
            </div>

            <h2 class="brand-headline">
                Secure.<br>
                Intelligent.<br>
                <span>Unified Control.</span>
            </h2>

            <p class="brand-sub">
                A comprehensive platform for managing correctional facilities,
                inmate records, staff operations, medical services, and compliance —
                all from a single secure dashboard.
            </p>

            <div class="brand-stats">
                <div class="stat-card">
                    <div class="stat-num">360°</div>
                    <div class="stat-label">Oversight</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num">24/7</div>
                    <div class="stat-label">Monitoring</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num">AES</div>
                    <div class="stat-label">Encrypted</div>
                </div>
            </div>
        </div>

        <!-- Login panel -->
        <div class="login-panel">

            <!-- Mobile-only logo -->
            <div class="mobile-brand">
                <div class="mobile-brand-icon"><i class="fa-solid fa-building-columns"></i></div>
                <div>
                    <span>PMS</span>
                    <small>Prison Management System</small>
                </div>
            </div>

            <div class="form-header">
                <h1>Welcome back</h1>
                <p>Sign in to continue to your dashboard</p>
            </div>

            <div id="loginMessage"></div>

            <form id="loginForm" novalidate>

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="input-icon-right" id="togglePwd" aria-label="Toggle password visibility" tabindex="-1">
                            <i class="fa-solid fa-eye" id="togglePwdIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row-between">
                    <a href="#" onclick="showReset(); return false;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="spinner" id="btnSpinner"></span>
                    <i class="fa-solid fa-right-to-bracket" id="btnIcon"></i>
                    <span id="btnText">Sign In</span>
                </button>

                <div class="security-row">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secured with end-to-end encryption
                </div>

            </form>

            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> Prison Management System &mdash;
                <a href="#" onclick="showReset(); return false;">Need help?</a>
            </div>
        </div>
    </div>

    <script>
        const APP_URL = '<?php echo rtrim(APP_URL, '/'); ?>';
        const API_URL = '<?php echo rtrim(API_URL, '/'); ?>';

        /* Password toggle */
        document.getElementById('togglePwd').addEventListener('click', function () {
            const pwd  = document.getElementById('password');
            const icon = document.getElementById('togglePwdIcon');
            const show = pwd.type === 'password';
            pwd.type   = show ? 'text' : 'password';
            icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });

        /* Helpers */
        function setMessage(html) {
            document.getElementById('loginMessage').innerHTML = html;
        }
        function setLoading(on) {
            const btn     = document.getElementById('loginBtn');
            const spinner = document.getElementById('btnSpinner');
            const icon    = document.getElementById('btnIcon');
            const text    = document.getElementById('btnText');
            btn.disabled          = on;
            spinner.style.display = on ? 'block' : 'none';
            icon.style.display    = on ? 'none'  : 'inline';
            text.textContent      = on ? 'Signing in\u2026' : 'Sign In';
        }
        function shakeInputs() {
            ['username','password'].forEach(function(id) {
                var el = document.getElementById(id);
                el.classList.remove('shake');
                void el.offsetWidth;
                el.classList.add('shake');
            });
        }

        /* Login form */
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            e.preventDefault();

            var username = document.getElementById('username').value.trim();
            var password = document.getElementById('password').value;

            if (!username || !password) {
                setMessage('<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><span>Please fill in both fields.</span></div>');
                shakeInputs();
                return;
            }

            setLoading(true);
            setMessage('<div class="alert alert-loading"><i class="fa-solid fa-circle-notch fa-spin"></i><span>Verifying credentials\u2026</span></div>');

            fetch(API_URL + '/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username, password: password })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    setMessage('<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span>Login successful \u2014 redirecting\u2026</span></div>');
                    setTimeout(function() { window.location.href = APP_URL + '/dashboard.php'; }, 900);
                } else {
                    setLoading(false);
                    setMessage('<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i><span>' + (data.message || 'Invalid username or password.') + '</span></div>');
                    shakeInputs();
                }
            })
            .catch(function() {
                setLoading(false);
                setMessage('<div class="alert alert-error"><i class="fa-solid fa-wifi"></i><span>Connection error. Please try again.</span></div>');
            });
        });

        function showReset() {
            alert('Please contact your system administrator to reset your password.');
        }
    </script>
</body>
</html>
