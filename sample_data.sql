-- ====================================================================
-- SAMPLE DATA FOR PRISON MANAGEMENT SYSTEM
-- ====================================================================
-- This file contains realistic sample data for testing and demo purposes
-- Run this after creating the database schema

-- Disable foreign key checks to allow data deletion
SET FOREIGN_KEY_CHECKS=0;

-- ====================================================================
-- CLEAR EXISTING DATA (Run in reverse order of foreign key dependencies)
-- ====================================================================
DELETE FROM activity_logs;
DELETE FROM system_logs;
DELETE FROM po_items;
DELETE FROM purchase_orders;
DELETE FROM inventory_transactions;
DELETE FROM inventory_items;
DELETE FROM warehouses;
DELETE FROM inventory_categories;
DELETE FROM suppliers;
DELETE FROM incident_categories;
DELETE FROM incidents;
DELETE FROM medical_records;
DELETE FROM inmate_transactions;
DELETE FROM inmate_accounts;
DELETE FROM inmate_cell_assignment;
DELETE FROM inmates;
DELETE FROM cells;
DELETE FROM cell_blocks;
DELETE FROM duty_schedule;
DELETE FROM staff;
DELETE FROM users;
DELETE FROM facilities;

-- Reset AUTO_INCREMENT counters so IDs start from 1
ALTER TABLE facilities AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE cells AUTO_INCREMENT = 1;
ALTER TABLE cell_blocks AUTO_INCREMENT = 1;
ALTER TABLE inmates AUTO_INCREMENT = 1;
ALTER TABLE inmate_accounts AUTO_INCREMENT = 1;
ALTER TABLE inmate_transactions AUTO_INCREMENT = 1;
ALTER TABLE staff AUTO_INCREMENT = 1;
ALTER TABLE incident_categories AUTO_INCREMENT = 1;
ALTER TABLE incidents AUTO_INCREMENT = 1;
ALTER TABLE medical_records AUTO_INCREMENT = 1;
ALTER TABLE inventory_categories AUTO_INCREMENT = 1;
ALTER TABLE warehouses AUTO_INCREMENT = 1;
ALTER TABLE suppliers AUTO_INCREMENT = 1;
ALTER TABLE inventory_items AUTO_INCREMENT = 1;
ALTER TABLE purchase_orders AUTO_INCREMENT = 1;
ALTER TABLE po_items AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- ====================================================================
-- FACILITIES
-- ====================================================================
INSERT INTO facilities (name, type, location, total_capacity, contact_person, contact_phone, contact_email, status, created_at) VALUES
('Main Correctional Facility', 'PRISON', 'Downtown', 500, 'Michael Johnson', '555-0101', 'main@prison.gov', 'ACTIVE', NOW()),
('Northern Regional Prison', 'PRISON', 'North District', 350, 'Sarah Williams', '555-0102', 'north@prison.gov', 'ACTIVE', NOW()),
('Minimum Security Camp', 'PRISON', 'Outside City', 200, 'Thomas Wilson', '555-0103', 'minimum@prison.gov', 'ACTIVE', NOW());

-- ====================================================================
-- USERS (Admin, Managers, Officers)
-- ====================================================================
INSERT INTO users (username, email, password_hash, first_name, last_name, role, facility_id, is_active, created_at) VALUES
('admin', 'admin@pms.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'System', 'Administrator', 'SUPER_ADMIN', 1, 1, NOW()),
('director_main', 'director@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Michael', 'Johnson', 'FACILITY_ADMIN', 1, 1, NOW()),
('director_north', 'director@northprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Sarah', 'Williams', 'FACILITY_ADMIN', 2, 1, NOW()),
('officer_james', 'james@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'James', 'Smith', 'OFFICER', 1, 1, NOW()),
('officer_maria', 'maria@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Maria', 'Garcia', 'OFFICER', 1, 1, NOW()),
('nurse_alice', 'alice@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Alice', 'Brown', 'MEDICAL_STAFF', 1, 1, NOW()),
('accountant_bob', 'bob@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Bob', 'Davis', 'FINANCE_OFFICER', 1, 1, NOW()),
('manager_north', 'manager@northprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Thomas', 'Wilson', 'FACILITY_ADMIN', 2, 1, NOW()),
('doctor_jennifer', 'jennifer@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Jennifer', 'Lee', 'MEDICAL_STAFF', 1, 1, NOW()),
('tech_marcus', 'marcus@mainprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Marcus', 'Johnson', 'OFFICER', 1, 1, NOW()),
('hr_patricia', 'patricia@northprison.local', '$2y$10$jxnIEQYPF6/.QTthjDu0oOKoOmNDUF2e7CaVOK2/6UsPVv8pmBQKO', 'Patricia', 'Anderson', 'OFFICER', 2, 1, NOW());

-- ====================================================================
-- CELL BLOCKS
-- ====================================================================
INSERT INTO cell_blocks (facility_id, block_name, block_type, total_cells, capacity_per_cell, current_occupancy, created_at) VALUES
(1, 'Block A', 'GENERAL', 3, 2, 3, NOW()),
(1, 'Block B', 'ADMINISTRATIVE_SEGREGATION', 2, 1, 1, NOW()),
(2, 'Block C', 'GENERAL', 2, 3, 2, NOW()),
(3, 'Block D', 'GENERAL', 1, 4, 1, NOW());

-- ====================================================================
-- CELLS
-- ====================================================================
INSERT INTO cells (facility_id, block_id, cell_number, capacity, current_occupancy, status, created_at) VALUES
(1, 1, 'A-101', 2, 2, 'FULL', NOW()),
(1, 1, 'A-102', 2, 1, 'OPERATIONAL', NOW()),
(1, 1, 'A-103', 2, 0, 'OPERATIONAL', NOW()),
(1, 2, 'B-201', 1, 1, 'FULL', NOW()),
(1, 2, 'B-202', 1, 0, 'OPERATIONAL', NOW()),
(2, 3, 'C-101', 3, 2, 'OPERATIONAL', NOW()),
(2, 3, 'C-102', 3, 0, 'OPERATIONAL', NOW()),
(3, 4, 'D-101', 4, 1, 'OPERATIONAL', NOW());

-- ====================================================================
-- INMATES
-- ====================================================================
INSERT INTO inmates (inmate_id, first_name, last_name, gender, date_of_birth, national_id, admission_date, facility_id, status, risk_classification, sentence_length_months, medical_conditions, religion, next_of_kin_name, next_of_kin_phone, physical_marks, created_at) VALUES
('INM001', 'Robert', 'Thompson', 'MALE', '1985-03-15', 'ID123456001', '2022-05-10', 1, 'CONVICTED', 'MEDIUM', 24, 'Asthma', 'Christian', 'Mary Thompson', '555-2001', 'Scar on left arm', NOW()),
('INM002', 'David', 'Lee', 'MALE', '1990-07-22', 'ID123456002', '2021-08-20', 1, 'CONVICTED', 'HIGH', 36, 'Hypertension', 'Muslim', 'Ahmed Lee', '555-2002', 'Tattoo on chest', NOW()),
('INM003', 'Carlos', 'Martinez', 'MALE', '1988-11-30', 'ID123456003', '2023-01-15', 1, 'CONVICTED', 'MAXIMUM', 48, 'None', 'Catholic', 'Elena Martinez', '555-2003', 'None', NOW()),
('INM004', 'James', 'Wilson', 'MALE', '1992-02-14', 'ID123456004', '2023-06-01', 2, 'CONVICTED', 'LOW', 12, 'Anxiety disorder', 'Protestant', 'Jennifer Wilson', '555-2004', 'Birthmark on neck', NOW()),
('INM005', 'Michael', 'Anderson', 'MALE', '1987-09-25', 'ID123456005', '2022-03-10', 2, 'CONVICTED', 'MEDIUM', 18, 'Diabetes', 'Christian', 'Susan Anderson', '555-2005', 'None', NOW()),
('INM006', 'Kevin', 'Brown', 'MALE', '1991-12-05', 'ID123456006', '2023-04-20', 3, 'CONVICTED', 'LOW', 6, 'None', 'None', 'Patricia Brown', '555-2006', 'Scar above eyebrow', NOW()),
('INM007', 'Christopher', 'White', 'MALE', '1989-06-18', 'ID123456007', '2022-11-30', 1, 'TRANSFERRED', 'MEDIUM', 24, 'None', 'Christian', 'Lisa White', '555-2007', 'None', NOW()),
('INM008', 'Daniel', 'Harris', 'MALE', '1994-04-09', 'ID123456008', '2023-09-05', 2, 'RELEASED', 'LOW', 0, 'None', 'Hindu', 'Rajesh Harris', '555-2008', 'None', NOW());

-- ====================================================================
-- INMATE ACCOUNTS (Finance)
-- ====================================================================
INSERT INTO inmate_accounts (facility_id, inmate_id, account_balance, account_status, created_at, updated_at) VALUES
(1, 1, 250.50, 'ACTIVE', NOW(), NOW()),
(1, 2, 1000.00, 'ACTIVE', NOW(), NOW()),
(1, 3, 75.25, 'ACTIVE', NOW(), NOW()),
(2, 4, 500.00, 'ACTIVE', NOW(), NOW()),
(2, 5, 150.75, 'ACTIVE', NOW(), NOW()),
(3, 6, 320.00, 'ACTIVE', NOW(), NOW()),
(1, 7, 100.00, 'SUSPENDED', NOW(), NOW()),
(2, 8, 0.00, 'CLOSED', NOW(), NOW());

-- ====================================================================
-- FINANCIAL TRANSACTIONS
-- ====================================================================
INSERT INTO inmate_transactions (facility_id, account_id, transaction_type, amount, balance_before, balance_after, description, processed_by, created_at) VALUES
(1, 1, 'DEPOSIT', 200.00, 50.50, 250.50, 'Family deposit', 7, '2024-02-15 10:30:00'),
(1, 1, 'WITHDRAWAL', 50.00, 300.50, 250.50, 'Commissary purchase', 7, '2024-02-16 14:20:00'),
(1, 2, 'DEPOSIT', 500.00, 200.00, 700.00, 'Work payment', 7, '2024-02-10 09:00:00'),
(1, 2, 'DEPOSIT', 300.00, 700.00, 1000.00, 'Family deposit', 7, '2024-02-17 11:15:00'),
(1, 3, 'WITHDRAWAL', 25.00, 100.25, 75.25, 'Commissary purchase', 7, '2024-02-18 15:45:00'),
(2, 4, 'DEPOSIT', 400.00, 100.00, 500.00, 'Family deposit', 7, '2024-02-12 08:45:00'),
(2, 5, 'DEPOSIT', 100.00, 50.75, 150.75, 'Work payment', 7, '2024-02-14 10:00:00'),
(3, 6, 'DEPOSIT', 250.00, 70.00, 320.00, 'Family deposit', 7, '2024-02-18 13:30:00');

-- ====================================================================
-- STAFF MEMBERS
-- ====================================================================
INSERT INTO staff (facility_id, user_id, staff_id_number, position, staff_type, hire_date, employment_status, phone, created_at) VALUES
(1, 2, 'STF001', 'Security Officer', 'OFFICER', '2020-01-15', 'ACTIVE', '555-3001', NOW()),
(1, 5, 'STF002', 'Sergeant', 'OFFICER', '2019-06-20', 'ACTIVE', '555-3002', NOW()),
(1, 6, 'STF003', 'Nurse', 'NURSE', '2021-03-10', 'ACTIVE', '555-3003', NOW()),
(1, 4, 'STF004', 'Finance Officer', 'OFFICER', '2020-11-05', 'ACTIVE', '555-3004', NOW()),
(2, 7, 'STF005', 'Security Officer', 'OFFICER', '2019-09-15', 'ACTIVE', '555-3005', NOW()),
(1, 9, 'STF006', 'Doctor', 'NURSE', '2021-01-20', 'ACTIVE', '555-3006', NOW()),
(1, 10, 'STF007', 'Maintenance Technician', 'OFFICER', '2020-07-01', 'ACTIVE', '555-3007', NOW()),
(2, 11, 'STF008', 'HR Manager', 'OFFICER', '2018-05-10', 'ACTIVE', '555-3008', NOW());

-- ====================================================================
-- INCIDENT CATEGORIES
-- ====================================================================
INSERT INTO incident_categories (name, description, severity, created_at) VALUES
('Violence', 'Physical altercations between inmates or with staff', 'HIGH', NOW()),
('Escape Attempt', 'Attempted or successful escape from facility', 'CRITICAL', NOW()),
('Theft', 'Theft of property or contraband', 'MEDIUM', NOW()),
('Substance Abuse', 'Drug or alcohol-related incidents', 'MEDIUM', NOW()),
('Property Damage', 'Damage to facility property', 'LOW', NOW()),
('Misconduct', 'General inmate misconduct', 'LOW', NOW()),
('Self-Harm', 'Inmate self-harm incidents', 'HIGH', NOW()),
('Security Breach', 'Security system or protocol breaches', 'CRITICAL', NOW());

-- ====================================================================
-- INCIDENTS
-- ====================================================================
INSERT INTO incidents (facility_id, incident_category_id, inmate_id, staff_id, reported_by, incident_date, location, severity, status, description, created_at) VALUES
(1, 1, 1, 1, 1, '2024-02-18 14:30:00', 'Cell Block A', 'MEDIUM', 'RESOLVED', 'Two inmates engaged in fighting', NOW()),
(1, 5, 2, 2, 2, '2024-02-17 10:15:00', 'Recreation Yard', 'LOW', 'RESOLVED', 'Inmate defaced recreation area wall', NOW()),
(1, 3, 3, 3, 3, '2024-02-16 09:00:00', 'Prison Hospital', 'HIGH', 'UNDER_INVESTIGATION', 'Mobile phone and drugs found during cell search', NOW()),
(2, 4, 4, 5, 5, '2024-02-15 16:45:00', 'Cell Block C', 'CRITICAL', 'UNDER_INVESTIGATION', 'Suspicious activity and syringes found', NOW()),
(1, 6, 5, 1, 1, '2024-02-14 12:00:00', 'Dining Hall', 'LOW', 'RESOLVED', 'Inmate refused to follow officer instructions', NOW()),
(2, 7, 6, 5, 5, '2024-02-13 20:30:00', 'Cell Block C', 'HIGH', 'RESOLVED', 'Inmate found with makeshift cutting implement', NOW());

-- ====================================================================
-- MEDICAL RECORDS
-- ====================================================================
INSERT INTO medical_records (inmate_id, facility_id, diagnosis, treatment_plan, record_date, created_at) VALUES
(1, 1, 'Asthma', 'Prescribed inhaler', '2024-02-10 09:00:00', NOW()),
(2, 1, 'Hypertension', 'Blood pressure medication prescribed', '2024-02-12 10:00:00', NOW()),
(3, 1, 'Anxiety Disorder', 'Mental health counseling, anxiolytic medication', '2024-02-08 11:00:00', NOW()),
(4, 2, 'Diabetes Type 2', 'Insulin therapy, dietary management', '2024-02-09 09:30:00', NOW()),
(5, 2, 'Chronic Back Pain', 'Physical therapy, pain management medication', '2024-02-11 14:00:00', NOW()),
(6, 3, 'Common Cold', 'Rest, fluids, over-the-counter medication', '2024-02-17 08:00:00', NOW());

-- ====================================================================
-- INVENTORY CATEGORIES
-- ====================================================================
INSERT INTO inventory_categories (name, description, created_at) VALUES
('Food & Beverage', 'Meals, snacks, and beverages', NOW()),
('Toiletries', 'Soap, shampoo, toothpaste, etc.', NOW()),
('Clothing', 'Uniforms and inmate clothing', NOW()),
('Medical Supplies', 'Bandages, medications, medical equipment', NOW()),
('Cleaning Supplies', 'Disinfectants, cleaning materials', NOW()),
('Office Supplies', 'Paper, pens, printing materials', NOW()),
('Maintenance', 'Tools and maintenance equipment', NOW()),
('Security Equipment', 'Security devices and monitoring equipment', NOW());

-- ====================================================================
-- WAREHOUSES
-- ====================================================================
INSERT INTO warehouses (facility_id, name, location, capacity, created_at) VALUES
(1, 'Main Warehouse', 'Ground Floor, East Wing', 1000, NOW()),
(1, 'Medical Supply Room', 'Medical Wing, 2nd Floor', 200, NOW()),
(2, 'Central Warehouse', 'Building B', 800, NOW());

-- ====================================================================
-- SUPPLIERS / VENDORS
-- ====================================================================
INSERT INTO suppliers (name, contact_person, phone, email, address, status, created_at) VALUES
('Global Food Supplies', 'John Smith', '555-5001', 'john@globalfood.com', '100 Supply Street, Business City, CA 90001', 'ACTIVE', NOW()),
('MediPro Pharmaceuticals', 'Dr. Sarah Jones', '555-5002', 'sales@medipro.com', '200 Medical Plaza, Health City, NY 10001', 'ACTIVE', NOW()),
('Secure Equipment Inc', 'Mike Johnson', '555-5003', 'mike@secureequip.com', '300 Security Lane, Tech City, TX 75001', 'ACTIVE', NOW()),
('CleanCo Services', 'Lisa Williams', '555-5004', 'info@cleancopro.com', '400 Clean Avenue, Service City, FL 33101', 'ACTIVE', NOW()),
('Uniform Solutions', 'Robert Brown', '555-5005', 'sales@uniformsol.com', '500 Textile Road, Fashion City, NC 27601', 'ACTIVE', NOW());

-- ====================================================================
-- INVENTORY ITEMS
-- ====================================================================
INSERT INTO inventory_items (facility_id, category_id, warehouse_id, sku, name, description, unit_of_measure, current_quantity, reorder_level, reorder_quantity, unit_cost, supplier_id, last_reorder_date, status, created_at) VALUES
(1, 1, 1, 'FOOD-001', 'Rice (50lb bag)', 'Long grain white rice', 'bags', 45, 20, 50, 25.00, 1, '2024-02-18', 'IN_STOCK', NOW()),
(1, 1, 1, 'FOOD-002', 'Beans (25lb bag)', 'Assorted dried beans', 'bags', 30, 15, 50, 18.50, 1, '2024-02-17', 'IN_STOCK', NOW()),
(1, 1, 1, 'FOOD-003', 'Milk (1 liter)', 'Pasteurized whole milk', 'liters', 120, 100, 150, 1.50, 1, '2024-02-18', 'IN_STOCK', NOW()),
(1, 2, 1, 'TOIL-001', 'Soap Bar', 'Industrial soap bars', 'bars', 200, 100, 200, 0.75, 4, '2024-02-16', 'IN_STOCK', NOW()),
(1, 2, 1, 'TOIL-002', 'Toothpaste (500ml)', 'Regular fluoride toothpaste', 'tubes', 75, 40, 100, 2.25, 4, '2024-02-15', 'IN_STOCK', NOW()),
(1, 2, 1, 'TOIL-003', 'Toilet Paper (case)', '12 roll case', 'cases', 25, 20, 50, 12.00, 4, '2024-02-14', 'IN_STOCK', NOW()),
(1, 3, 1, 'CLOS-001', 'Inmate Uniform (S)', 'Small size regular uniform', 'units', 50, 30, 100, 15.00, 5, '2024-02-12', 'IN_STOCK', NOW()),
(1, 3, 1, 'CLOS-002', 'Inmate Uniform (M)', 'Medium size regular uniform', 'units', 60, 40, 100, 15.00, 5, '2024-02-12', 'IN_STOCK', NOW()),
(1, 3, 1, 'CLOS-003', 'Inmate Uniform (L)', 'Large size regular uniform', 'units', 40, 35, 100, 15.00, 5, '2024-02-12', 'IN_STOCK', NOW()),
(1, 4, 2, 'MED-001', 'Bandage (Box of 100)', 'Sterile adhesive bandages', 'boxes', 15, 10, 50, 8.50, 2, '2024-02-10', 'IN_STOCK', NOW()),
(1, 4, 2, 'MED-002', 'Syringes (10cc)', 'Sterile injector syringes', 'boxes', 12, 8, 30, 22.00, 2, '2024-02-08', 'LOW_STOCK', NOW()),
(1, 5, 1, 'CLEAN-001', 'Disinfectant Spray', 'Hospital grade disinfectant', 'bottles', 50, 30, 100, 5.50, 4, '2024-02-16', 'IN_STOCK', NOW()),
(1, 5, 1, 'CLEAN-002', 'Floor Cleaner (5L)', 'Industrial floor cleaner', 'liters', 20, 10, 50, 3.00, 4, '2024-02-14', 'IN_STOCK', NOW()),
(1, 8, 1, 'SEC-001', 'Security Cameras', 'HD surveillance cameras', 'units', 8, 5, 20, 350.00, 3, '2024-02-01', 'IN_STOCK', NOW()),
(1, 8, 1, 'SEC-002', 'Gate Locks', 'Electronic security locks', 'units', 4, 3, 10, 425.00, 3, '2024-01-15', 'LOW_STOCK', NOW());

-- ====================================================================
-- PURCHASE ORDERS
-- ====================================================================
INSERT INTO purchase_orders (po_number, supplier_id, facility_id, order_date, expected_delivery, status, total_amount, created_at) VALUES
('PO-2024-001', 1, 1, '2024-02-10', '2024-02-25', 'DELIVERED', 1500.00, NOW()),
('PO-2024-002', 2, 1, '2024-02-12', '2024-02-22', 'SUBMITTED', 850.00, NOW()),
('PO-2024-003', 3, 1, '2024-02-15', '2024-03-01', 'APPROVED', 2100.00, NOW()),
('PO-2024-004', 4, 1, '2024-02-17', '2024-02-27', 'SUBMITTED', 650.00, NOW());

-- ====================================================================
-- PURCHASE ORDER ITEMS
-- ====================================================================
INSERT INTO po_items (facility_id, po_id, item_id, quantity_ordered, unit_price, line_total) VALUES
(1, 1, 1, 20, 25.00, 500.00),
(1, 1, 2, 15, 18.50, 277.50),
(1, 1, 3, 50, 1.50, 75.00),
(1, 2, 10, 25, 8.50, 212.50),
(1, 2, 11, 30, 22.00, 660.00),
(1, 3, 14, 3, 350.00, 1050.00),
(1, 3, 15, 5, 425.00, 2125.00),
(1, 4, 4, 100, 0.75, 75.00),
(1, 4, 12, 40, 5.50, 220.00);

-- ====================================================================
-- NOTES
-- ====================================================================
/*
  ACCESSING THE DATA:
  
  1. Admin User:
     - Username: admin
     - Password: (use $2y$10$ hashed format - the hash shown is a placeholder)
  
  2. Test Cases:
     - INM001 (Robert): Active inmate with multiple programs, medical records, and visits
     - INM002 (David): High-risk inmate, involved in contraband incident
     - INM003 (Carlos): Critical risk, in disciplinary status
     - INM004 (James): Low-risk, pending transfer to minimum security
     - INM007 (Christopher): Pending transfer status
     - INM008 (Daniel): Released inmate (for historical records)
  
  3. Key Demo Points:
     - Occupancy rates at main facility (4 out of 8 cells = 50%)
     - Low stock alerts on medical supplies
     - Multiple incident statuses (resolved, investigating)
     - Financial transactions showing deposits and withdrawals
     - Program enrollments at various progress levels
     - Pending visit approvals
  
  4. To Update Password Hash:
     Replace password hashes with: password_hash('YourPassword', PASSWORD_BCRYPT)
     Example: UPDATE users SET password_hash = '$2y$10$...' WHERE username = 'admin';
*/
