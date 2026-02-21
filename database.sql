-- ===================================================================
-- ADVANCED MULTI-PRISON MANAGEMENT SYSTEM - DATABASE SCHEMA
-- ===================================================================
-- Production-ready schema with complete RBAC and audit trail support

CREATE DATABASE IF NOT EXISTS prison_management;
USE prison_management;

-- ===================================================================
-- 1. FACILITIES (Prisons/Administrative Offices)
-- ===================================================================
CREATE TABLE IF NOT EXISTS facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('PRISON', 'ADMIN_OFFICE', 'HOLDING_CENTER') NOT NULL DEFAULT 'PRISON',
    location VARCHAR(255) NOT NULL,
    total_capacity INT NOT NULL,
    current_population INT DEFAULT 0,
    status ENUM('ACTIVE', 'INACTIVE', 'UNDER_MAINTENANCE') NOT NULL DEFAULT 'ACTIVE',
    contact_person VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 2. USERS (System Users - Staff, Admin, Officers)
-- ===================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('SUPER_ADMIN', 'FACILITY_ADMIN', 'OFFICER', 'MEDICAL_STAFF', 'RECORDS_OFFICER', 'FINANCE_OFFICER') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_role (role),
    INDEX idx_deleted_at (deleted_at),
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 3. STAFF (Staff Members)
-- ===================================================================
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    user_id INT NOT NULL,
    staff_id_number VARCHAR(50) NOT NULL UNIQUE,
    position VARCHAR(100) NOT NULL,
    staff_type ENUM('OFFICER', 'NURSE', 'COUNSELOR', 'ADMINISTRATOR', 'GUARD', 'SUPPORT') NOT NULL,
    hire_date DATE NOT NULL,
    employment_status ENUM('ACTIVE', 'INACTIVE', 'ON_LEAVE', 'TERMINATED') NOT NULL DEFAULT 'ACTIVE',
    phone VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('MALE', 'FEMALE', 'OTHER'),
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_user_id (user_id),
    INDEX idx_staff_type (staff_type),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 4. DUTY SCHEDULE (Staff Shift Assignment)
-- ===================================================================
CREATE TABLE IF NOT EXISTS duty_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    staff_id INT NOT NULL,
    shift_date DATE NOT NULL,
    shift_type ENUM('MORNING', 'AFTERNOON', 'NIGHT') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    assignment VARCHAR(255),
    status ENUM('SCHEDULED', 'COMPLETED', 'ABSENT', 'CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_shift_date (shift_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 5. INMATES
-- ===================================================================
CREATE TABLE IF NOT EXISTS inmates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('MALE', 'FEMALE', 'OTHER') NOT NULL,
    national_id VARCHAR(50),
    biometric_id VARCHAR(100),
    admission_date DATE NOT NULL,
    release_date DATE NULL,
    sentence_length_months INT,
    status ENUM('REMAND', 'CONVICTED', 'RELEASED', 'TRANSFERRED', 'DECEASED') NOT NULL DEFAULT 'REMAND',
    risk_classification ENUM('LOW', 'MEDIUM', 'HIGH', 'MAXIMUM') NOT NULL DEFAULT 'MEDIUM',
    mother_tongue VARCHAR(50),
    religion VARCHAR(50),
    next_of_kin_name VARCHAR(255),
    next_of_kin_phone VARCHAR(20),
    medical_conditions TEXT,
    physical_marks TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_status (status),
    INDEX idx_risk_classification (risk_classification),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 6. CELL BLOCKS & CELLS
-- ===================================================================
CREATE TABLE IF NOT EXISTS cell_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    block_name VARCHAR(100) NOT NULL,
    block_type ENUM('GENERAL', 'PROTECTIVE_CUSTODY', 'MEDICAL', 'ADMINISTRATIVE_SEGREGATION') NOT NULL,
    total_cells INT NOT NULL,
    capacity_per_cell INT NOT NULL DEFAULT 1,
    current_occupancy INT DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_deleted_at (deleted_at),
    UNIQUE KEY unique_block (facility_id, block_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cells (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    block_id INT NOT NULL,
    cell_number VARCHAR(50) NOT NULL,
    capacity INT NOT NULL DEFAULT 1,
    current_occupancy INT DEFAULT 0,
    status ENUM('OPERATIONAL', 'MAINTENANCE', 'FULL') NOT NULL DEFAULT 'OPERATIONAL',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (block_id) REFERENCES cell_blocks(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_block_id (block_id),
    INDEX idx_deleted_at (deleted_at),
    UNIQUE KEY unique_cell (facility_id, cell_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inmate_cell_assignment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL,
    cell_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    released_date DATE NULL,
    status ENUM('ACTIVE', 'RELEASED', 'TRANSFERRED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (cell_id) REFERENCES cells(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_cell_id (cell_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 7. TRANSFERS (Inmate & Staff Transfers)
-- ===================================================================
CREATE TABLE IF NOT EXISTS inmate_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_from INT NOT NULL,
    facility_to INT NOT NULL,
    inmate_id INT NOT NULL,
    reason VARCHAR(255),
    transfer_date DATE,
    approval_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by INT,
    approved_date DATETIME NULL,
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_from) REFERENCES facilities(id),
    FOREIGN KEY (facility_to) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_approval_status (approval_status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_from INT NOT NULL,
    facility_to INT NOT NULL,
    staff_id INT NOT NULL,
    reason VARCHAR(255),
    transfer_date DATE,
    approval_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by INT,
    approved_date DATETIME NULL,
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_from) REFERENCES facilities(id),
    FOREIGN KEY (facility_to) REFERENCES facilities(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_approval_status (approval_status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 8. VISITS & COMMUNICATION
-- ===================================================================
CREATE TABLE IF NOT EXISTS visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    national_id VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(255),
    relationship_to_inmate VARCHAR(50),
    status ENUM('APPROVED', 'BLACKLISTED') DEFAULT 'APPROVED',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL,
    visitor_id INT NOT NULL,
    visit_date DATETIME NOT NULL,
    duration_minutes INT,
    visit_type ENUM('PERSONAL', 'LEGAL', 'OFFICIAL') NOT NULL DEFAULT 'PERSONAL',
    approval_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by INT,
    visit_room VARCHAR(100),
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (visitor_id) REFERENCES visitors(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_approval_status (approval_status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 9. INCIDENTS & SECURITY
-- ===================================================================
CREATE TABLE IF NOT EXISTS incident_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX idx_severity (severity),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    incident_category_id INT NOT NULL,
    inmate_id INT,
    staff_id INT,
    reported_by INT,
    incident_date DATETIME NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
    status ENUM('REPORTED', 'UNDER_INVESTIGATION', 'RESOLVED') NOT NULL DEFAULT 'REPORTED',
    investigation_notes TEXT,
    investigated_by INT,
    resolved_date DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (incident_category_id) REFERENCES incident_categories(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (investigated_by) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 10. MEDICAL SYSTEM
-- ===================================================================
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL,
    record_date DATETIME NOT NULL,
    examined_by INT,
    diagnosis TEXT,
    treatment_plan TEXT,
    vital_signs JSON,
    status ENUM('ACTIVE', 'RESOLVED', 'CHRONIC') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (examined_by) REFERENCES staff(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_record_date (record_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medication_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    prescribed_by INT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (prescribed_by) REFERENCES staff(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 11. REHABILITATION PROGRAMS
-- ===================================================================
CREATE TABLE IF NOT EXISTS rehabilitation_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    program_type ENUM('EDUCATION', 'VOCATIONAL', 'COUNSELING', 'SKILLS_TRAINING', 'SPORTS') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    capacity INT NOT NULL,
    instructor_id INT,
    status ENUM('ACTIVE', 'INACTIVE', 'COMPLETED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (instructor_id) REFERENCES staff(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_program_type (program_type),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS program_enrollment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    program_id INT NOT NULL,
    inmate_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    completion_date DATE NULL,
    status ENUM('ENROLLED', 'ACTIVE', 'COMPLETED', 'DROPPED') NOT NULL DEFAULT 'ENROLLED',
    progress_percentage INT DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (program_id) REFERENCES rehabilitation_programs(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_program_id (program_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 12. TRANSPORT & LOGISTICS
-- ===================================================================
CREATE TABLE IF NOT EXISTS transport_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    from_facility_id INT NOT NULL,
    to_facility_id INT NOT NULL,
    transport_date DATETIME NOT NULL,
    vehicle_reg VARCHAR(50),
    driver_staff_id INT,
    escort_staff_id INT,
    purpose VARCHAR(255),
    status ENUM('SCHEDULED', 'IN_TRANSIT', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (from_facility_id) REFERENCES facilities(id),
    FOREIGN KEY (to_facility_id) REFERENCES facilities(id),
    FOREIGN KEY (driver_staff_id) REFERENCES staff(id),
    FOREIGN KEY (escort_staff_id) REFERENCES staff(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_status (status),
    INDEX idx_transport_date (transport_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    schedule_id INT NOT NULL,
    inmate_id INT,
    item_description VARCHAR(255),
    quantity INT DEFAULT 1,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (schedule_id) REFERENCES transport_schedules(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_schedule_id (schedule_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 13. INMATE FINANCE
-- ===================================================================
CREATE TABLE IF NOT EXISTS inmate_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL UNIQUE,
    account_balance DECIMAL(10, 2) DEFAULT 0.00,
    account_status ENUM('ACTIVE', 'SUSPENDED', 'CLOSED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inmate_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    account_id INT NOT NULL,
    transaction_type ENUM('DEPOSIT', 'WITHDRAWAL', 'TRANSFER', 'CANTEEN') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance_before DECIMAL(10, 2),
    balance_after DECIMAL(10, 2),
    description VARCHAR(255),
    processed_by INT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (account_id) REFERENCES inmate_accounts(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_account_id (account_id),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 14. ADVANCED INVENTORY MANAGEMENT
-- ===================================================================
CREATE TABLE IF NOT EXISTS inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    capacity INT,
    current_stock_value DECIMAL(12, 2) DEFAULT 0.00,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_deleted_at (deleted_at),
    UNIQUE KEY unique_warehouse (facility_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    bank_details TEXT,
    status ENUM('ACTIVE', 'INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    category_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sku VARCHAR(100) NOT NULL,
    unit_of_measure VARCHAR(50),
    current_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    reorder_quantity INT NOT NULL DEFAULT 100,
    unit_cost DECIMAL(10, 2),
    supplier_id INT,
    last_reorder_date DATE NULL,
    status ENUM('IN_STOCK', 'LOW_STOCK', 'OUT_OF_STOCK', 'OBSOLETE') NOT NULL DEFAULT 'IN_STOCK',
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_category_id (category_id),
    INDEX idx_warehouse_id (warehouse_id),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at),
    UNIQUE KEY unique_sku_facility (sku, facility_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    item_id INT NOT NULL,
    transaction_type ENUM('IN', 'OUT', 'ADJUSTMENT', 'DAMAGE', 'LOSS') NOT NULL,
    quantity INT NOT NULL,
    quantity_before INT,
    quantity_after INT,
    reference_number VARCHAR(100),
    description TEXT,
    created_by INT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_item_id (item_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    supplier_id INT NOT NULL,
    po_number VARCHAR(100) NOT NULL UNIQUE,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    actual_delivery DATE NULL,
    total_amount DECIMAL(12, 2),
    status ENUM('DRAFT', 'SUBMITTED', 'APPROVED', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    approved_by INT,
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_price DECIMAL(10, 2),
    line_total DECIMAL(12, 2),
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_po_id (po_id),
    INDEX idx_item_id (item_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 15. COMPLAINTS & WELFARE
-- ===================================================================
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    inmate_id INT NOT NULL,
    complaint_type ENUM('FOOD', 'MEDICAL', 'SECURITY', 'DISCIPLINE', 'OTHER') NOT NULL,
    description TEXT NOT NULL,
    submission_date DATETIME NOT NULL,
    status ENUM('SUBMITTED', 'UNDER_REVIEW', 'RESOLVED', 'DISMISSED') NOT NULL DEFAULT 'SUBMITTED',
    assigned_to INT,
    resolution_notes TEXT,
    resolved_date DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (inmate_id) REFERENCES inmates(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_inmate_id (inmate_id),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 16. SYSTEM AUDIT LOGS
-- ===================================================================
CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100),
    entity_type VARCHAR(100),
    entity_id INT,
    description TEXT,
    status ENUM('SUCCESS', 'FAILURE') NOT NULL DEFAULT 'SUCCESS',
    ip_address VARCHAR(15),
    user_agent VARCHAR(255),
    created_at DATETIME,
    FOREIGN KEY (facility_id) REFERENCES facilities(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_facility_id (facility_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_entity_type_id (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- 17. ACTIVITY LOGS (Session and Login Tracking)
-- ===================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('LOGIN', 'LOGOUT', 'VIEW', 'CREATE', 'UPDATE', 'DELETE') NOT NULL,
    resource VARCHAR(255),
    ip_address VARCHAR(15),
    user_agent VARCHAR(255),
    created_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- INSERT INITIAL DATA
-- ===================================================================

-- Insert incident categories
INSERT INTO incident_categories (name, description, severity, created_at, updated_at) VALUES
('ESCAPE_ATTEMPT', 'Inmate escape attempt', 'CRITICAL', NOW(), NOW()),
('VIOLENCE', 'Violence between inmates or with staff', 'HIGH', NOW(), NOW()),
('CONTRABAND', 'Contraband found', 'MEDIUM', NOW(), NOW()),
('MEDICAL_EMERGENCY', 'Medical emergency', 'HIGH', NOW(), NOW()),
('PROPERTY_DAMAGE', 'Property damage', 'LOW', NOW(), NOW()),
('SECURITY_BREACH', 'Security breach', 'CRITICAL', NOW(), NOW());

-- Insert inventory categories
INSERT INTO inventory_categories (name, description, created_at, updated_at) VALUES
('FOOD_SUPPLIES', 'Food and beverages', NOW(), NOW()),
('MEDICAL_SUPPLIES', 'Medical equipment and supplies', NOW(), NOW()),
('CLOTHING', 'Uniforms and clothing', NOW(), NOW()),
('MAINTENANCE', 'Maintenance and repair materials', NOW(), NOW()),
('OFFICE_SUPPLIES', 'Office equipment and supplies', NOW(), NOW()),
('SECURITY_EQUIPMENT', 'Security and surveillance equipment', NOW(), NOW());
