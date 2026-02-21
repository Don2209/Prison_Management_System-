<?php
/**
 * Page Header Component – Bootstrap 5 Sidebar Layout
 */

require_once dirname(__FILE__) . '/auth.php';

requireAuth();
$currentUser        = getCurrentUser();
$accessible_facilities = getAccessibleFacilities();
$currentUri         = $_SERVER['REQUEST_URI'] ?? '';

// helper: is current page?
function isActive(string $path): string {
    return strpos($_SERVER['REQUEST_URI'] ?? '', $path) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?> – <?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom dark admin theme -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ============================================================
     SIDEBAR
     ============================================================ -->
<div id="sidebarBackdrop" onclick="closeSidebar()"></div>

<aside id="sidebar">
    <!-- Brand -->
    <a class="sidebar-brand" href="<?php echo APP_URL; ?>/dashboard.php">
        <div class="sidebar-brand-icon"><i class="bi bi-shield-fill"></i></div>
        <div class="sidebar-brand-text">
            <strong>PMS</strong>
            <span>Prison Management</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <!-- Main -->
        <div class="sidebar-section-label">Main</div>

        <a href="<?php echo APP_URL; ?>/dashboard.php" class="sidebar-link <?php echo isActive('/dashboard.php'); ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <!-- Inmates -->
        <?php if (hasPermission('view', 'inmates')): ?>
        <div class="sidebar-section-label">Inmate Management</div>
        <a href="#nav-inmates" class="sidebar-link <?php echo isActive('/inmates') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/inmates') ? 'true' : 'false'; ?>">
            <i class="bi bi-person-badge"></i> Inmates
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/inmates') ? 'show' : ''; ?>" id="nav-inmates">
            <a href="<?php echo APP_URL; ?>/modules/inmates/index.php" class="sidebar-sub-link <?php echo isActive('/inmates/index'); ?>">
                <i class="bi bi-list-ul"></i> All Inmates
            </a>
            <?php if (hasPermission('create', 'inmates')): ?>
            <a href="<?php echo APP_URL; ?>/modules/inmates/add.php" class="sidebar-sub-link <?php echo isActive('/inmates/add'); ?>">
                <i class="bi bi-person-plus"></i> Add Inmate
            </a>
            <?php endif; ?>
            <a href="<?php echo APP_URL; ?>/modules/inmates/transfers.php" class="sidebar-sub-link <?php echo isActive('/transfers'); ?>">
                <i class="bi bi-arrow-left-right"></i> Transfers
            </a>
        </div>
        <?php endif; ?>

        <!-- Visits -->
        <?php if (hasPermission('view', 'visits')): ?>
        <a href="<?php echo APP_URL; ?>/modules/visits/index.php" class="sidebar-link <?php echo isActive('/visits'); ?>">
            <i class="bi bi-calendar-check"></i> Visits
        </a>
        <?php endif; ?>

        <!-- Staff -->
        <?php if (hasPermission('view', 'staff')): ?>
        <div class="sidebar-section-label">Staff</div>
        <a href="#nav-staff" class="sidebar-link <?php echo isActive('/staff') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/staff') ? 'true' : 'false'; ?>">
            <i class="bi bi-people"></i> Staff
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/staff') ? 'show' : ''; ?>" id="nav-staff">
            <a href="<?php echo APP_URL; ?>/modules/staff/index.php" class="sidebar-sub-link">
                <i class="bi bi-list-ul"></i> All Staff
            </a>
            <?php if (hasPermission('create', 'staff')): ?>
            <a href="<?php echo APP_URL; ?>/modules/staff/add.php" class="sidebar-sub-link">
                <i class="bi bi-person-plus"></i> Add Staff
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Facilities -->
        <?php if (isSuperAdmin()): ?>
        <a href="#nav-facilities" class="sidebar-link <?php echo isActive('/facilities') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/facilities') ? 'true' : 'false'; ?>">
            <i class="bi bi-building"></i> Facilities
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/facilities') ? 'show' : ''; ?>" id="nav-facilities">
            <a href="<?php echo APP_URL; ?>/modules/facilities/index.php" class="sidebar-sub-link">
                <i class="bi bi-list-ul"></i> All Facilities
            </a>
            <a href="<?php echo APP_URL; ?>/modules/facilities/add.php" class="sidebar-sub-link">
                <i class="bi bi-plus-circle"></i> Add Facility
            </a>
        </div>
        <?php endif; ?>

        <!-- Operations -->
        <div class="sidebar-section-label">Operations</div>

        <!-- Medical -->
        <?php if (hasPermission('view', 'medical_records')): ?>
        <a href="<?php echo APP_URL; ?>/modules/medical/index.php" class="sidebar-link <?php echo isActive('/medical'); ?>">
            <i class="bi bi-heart-pulse"></i> Medical
        </a>
        <?php endif; ?>

        <!-- Security -->
        <?php if (hasPermission('view', 'incidents')): ?>
        <a href="#nav-security" class="sidebar-link <?php echo isActive('/incidents') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/incidents') ? 'true' : 'false'; ?>">
            <i class="bi bi-shield-exclamation"></i> Security
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/incidents') ? 'show' : ''; ?>" id="nav-security">
            <a href="<?php echo APP_URL; ?>/modules/incidents/index.php" class="sidebar-sub-link">
                <i class="bi bi-list-ul"></i> Incidents
            </a>
            <?php if (hasPermission('create', 'incidents')): ?>
            <a href="<?php echo APP_URL; ?>/modules/incidents/add.php" class="sidebar-sub-link">
                <i class="bi bi-plus-circle"></i> Report Incident
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Inventory -->
        <?php if (hasPermission('view', 'inventory')): ?>
        <a href="#nav-inventory" class="sidebar-link <?php echo isActive('/inventory') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/inventory') ? 'true' : 'false'; ?>">
            <i class="bi bi-box-seam"></i> Inventory
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/inventory') ? 'show' : ''; ?>" id="nav-inventory">
            <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" class="sidebar-sub-link">
                <i class="bi bi-list-ul"></i> Stock
            </a>
            <a href="<?php echo APP_URL; ?>/modules/inventory/warehouses.php" class="sidebar-sub-link">
                <i class="bi bi-building"></i> Warehouses
            </a>
            <a href="<?php echo APP_URL; ?>/modules/inventory/purchase-orders.php" class="sidebar-sub-link">
                <i class="bi bi-receipt"></i> Purchase Orders
            </a>
        </div>
        <?php endif; ?>

        <!-- Finance -->
        <?php if (hasPermission('view', 'finance')): ?>
        <a href="#nav-finance" class="sidebar-link <?php echo isActive('/finance') ? 'active' : ''; ?>"
           data-bs-toggle="collapse" aria-expanded="<?php echo isActive('/finance') ? 'true' : 'false'; ?>">
            <i class="bi bi-cash-coin"></i> Finance
        </a>
        <div class="collapse sidebar-sub <?php echo isActive('/finance') ? 'show' : ''; ?>" id="nav-finance">
            <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php" class="sidebar-sub-link">
                <i class="bi bi-wallet2"></i> Inmate Accounts
            </a>
            <a href="<?php echo APP_URL; ?>/modules/finance/transactions.php" class="sidebar-sub-link">
                <i class="bi bi-arrow-down-up"></i> Transactions
            </a>
        </div>
        <?php endif; ?>

        <!-- Programs -->
        <a href="<?php echo APP_URL; ?>/modules/programs/index.php" class="sidebar-link <?php echo isActive('/programs'); ?>">
            <i class="bi bi-mortarboard"></i> Programs
        </a>

        <!-- Reports -->
        <?php if (hasPermission('view', 'reports')): ?>
        <div class="sidebar-section-label">Analytics</div>
        <a href="<?php echo APP_URL; ?>/modules/reports/index.php" class="sidebar-link <?php echo isActive('/reports'); ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <?php endif; ?>

        <!-- Settings (admin) -->
        <?php if (isSuperAdmin()): ?>
        <div class="sidebar-section-label">System</div>
        <a href="<?php echo APP_URL; ?>/modules/settings/" class="sidebar-link <?php echo isActive('/settings'); ?>">
            <i class="bi bi-gear"></i> Settings
        </a>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Bottom User -->
    <div style="padding: 12px 16px; border-top: 1px solid var(--sidebar-border);">
        <div class="d-flex align-items-center gap-2">
            <div class="topbar-avatar" style="width:32px;height:32px;font-size:0.75rem;">
                <?php echo strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)); ?>
            </div>
            <div class="flex-grow-1" style="min-width:0; overflow:hidden;">
                <div style="font-size:0.82rem; font-weight:600; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?>
                </div>
                <div style="font-size:0.72rem; color:var(--sidebar-muted);"><?php echo htmlspecialchars($currentUser['role'] ?? ''); ?></div>
            </div>
            <a href="<?php echo APP_URL; ?>/modules/auth/logout.php" class="topbar-icon-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ============================================================
     TOPBAR
     ============================================================ -->
<div id="topbar">
    <button id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

    <!-- Breadcrumb -->
    <div class="topbar-breadcrumb">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard.php">Home</a></li>
                <?php if (isset($pageTitle) && $pageTitle !== 'Dashboard'): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($pageTitle); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>

    <div class="topbar-right">
        <!-- Facility switcher -->
        <?php if (count($accessible_facilities) > 1): ?>
        <select class="facility-select" id="facilitySwitch" onchange="switchFacility(this.value)">
            <?php foreach ($accessible_facilities as $fac): ?>
            <option value="<?php echo $fac['id']; ?>" <?php echo ($fac['id'] == getCurrentFacility()) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($fac['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <!-- Notifications placeholder -->
        <button class="topbar-icon-btn" title="Notifications">
            <i class="bi bi-bell"></i>
        </button>

        <!-- User dropdown -->
        <div class="dropdown">
            <div class="topbar-avatar" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
                <?php echo strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1)); ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">
                    <?php echo htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')); ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($currentUser['role'] ?? ''); ?></small>
                </h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/auth/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/auth/change-password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/modules/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Alerts -->
<div id="alertsContainer"></div>

<!-- ============================================================
     MAIN WRAPPER
     ============================================================ -->
<div id="main-wrapper">
<div id="page-content">
        <div class="container">
