/**
 * Main Application JavaScript
 * Frontend logic, API calls, and UI interactions
 */

// Global configuration (set by header.php)
const APP_URL = window.APP_URL || 'http://localhost/PMS';
const API_URL = window.API_URL || 'http://localhost/PMS/api';
const CURRENT_USER = window.CURRENT_USER || {};

/**
 * Show notification alert
 */
function showAlert(message, type = 'success', duration = 5000) {
    const alertsContainer = document.getElementById('alertsContainer');
    if (!alertsContainer) return;
    
    const alertId = 'alert-' + Date.now();
    const alert = document.createElement('div');
    alert.id = alertId;
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `<strong>${type.toUpperCase()}:</strong> ${message}`;
    
    alertsContainer.appendChild(alert);
    
    if (duration > 0) {
        setTimeout(() => {
            alert.remove();
        }, duration);
    }
    
    return alertId;
}

/**
 * API Call Helper
 */
async function apiCall(endpoint, options = {}) {
    const method = options.method || 'GET';
    const data = options.data || null;
    const headers = options.headers || {};
    
    const config = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            ...headers
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        config.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(API_URL + endpoint, config);
        const result = await response.json();
        
        if (!response.ok && !result.success) {
            throw new Error(result.message || 'API request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        showAlert(error.message || 'Connection error', 'error');
        throw error;
    }
}

/**
 * Format date for display
 */
function formatDate(dateString, format = 'MMM dd, yyyy') {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return date.toLocaleDateString('en-US', options);
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount || 0);
}

/**
 * Switch facility
 */
function switchFacility(facilityId) {
    // Store facility in session/localStorage
    localStorage.setItem('selectedFacility', facilityId);
    
    // Reload page
    window.location.reload();
}

/**
 * Confirm action
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Logout user
 */
function logoutUser() {
    if (confirmAction('Are you sure you want to logout?')) {
        window.location.href = APP_URL + '/api/auth/logout.php';
    }
}

/**
 * Initialize dropdown menus
 */
document.addEventListener('DOMContentLoaded', function() {
    // User dropdown
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        userMenu.addEventListener('click', function(e) {
            if (e.target.classList.contains('user-name')) {
                this.querySelector('.user-dropdown').toggle();
            }
        });
    }
    
    // Dropdown toggles
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.parentElement;
            parent.classList.toggle('active');
        });
    });
});

/**
 * Form submission handler
 */
function handleFormSubmit(formId, endpoint, method = 'POST') {
    const form = document.getElementById(formId);
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        try {
            const result = await apiCall(endpoint, {
                method: method,
                data: data
            });
            
            if (result.success) {
                showAlert(result.message || 'Success', 'success');
                // Optionally reset form
                form.reset();
                // Optionally redirect
                if (result.redirect) {
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1500);
                }
            }
        } catch (error) {
            showAlert(error.message, 'error');
        }
    });
}

/**
 * Load data into table
 */
async function loadTableData(tableId, endpoint, columns) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    try {
        const result = await apiCall(endpoint);
        
        if (result.success && result.data) {
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = '';
            
            result.data.forEach(row => {
                const tr = document.createElement('tr');
                columns.forEach(col => {
                    const td = document.createElement('td');
                    td.textContent = row[col] || '-';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }
    } catch (error) {
        console.error('Error loading table data:', error);
    }
}

/**
 * Search functionality
 */
function setupSearch(inputId, tableId, columnIndexes = [0, 1]) {
    const searchInput = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!searchInput || !table) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tbody = table.querySelector('tbody');
        
        tbody.querySelectorAll('tr').forEach(row => {
            let match = false;
            
            columnIndexes.forEach(idx => {
                const cell = row.cells[idx];
                if (cell && cell.textContent.toLowerCase().includes(searchTerm)) {
                    match = true;
                }
            });
            
            row.style.display = match ? '' : 'none';
        });
    });
}

/**
 * Setup pagination
 */
function setupPagination(paginationConainer, onPageChange) {
    const links = paginationConainer?.querySelectorAll('a');
    if (!links) return;
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            if (page && onPageChange) {
                onPageChange(page);
            }
        });
    });
}

/**
 * Toggle element visibility
 */
function toggleElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}

/**
 * Modal helpers
 */
class Modal {
    constructor(modalId) {
        this.modal = document.getElementById(modalId);
    }
    
    show() {
        if (this.modal) {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    hide() {
        if (this.modal) {
            this.modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
}

/**
 * Export utilities
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        let rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent + '"');
        });
        csv.push(rowData.join(','));
    });
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = filename;
    link.click();
}

/**
 * Print functionality
 */
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<pre>' + element.innerHTML + '</pre>');
    printWindow.document.close();
    printWindow.print();
}

/**
 * Validation helpers
 */
const validators = {
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    phone: (value) => /^[\d\s\-\+\(\)]+$/.test(value) && value.length >= 10,
    password: (value) => value.length >= 8,
    date: (value) => !isNaN(Date.parse(value))
};

function validateField(fieldId, validationType) {
    const field = document.getElementById(fieldId);
    if (!field) return false;
    
    const validator = validators[validationType];
    const isValid = validator ? validator(field.value) : true;
    
    if (!isValid) {
        field.style.borderColor = '#e74c3c';
        showAlert(`Invalid ${validationType}`, 'error', 3000);
    } else {
        field.style.borderColor = '';
    }
    
    return isValid;
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.title = element.getAttribute('data-tooltip');
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltips();
});

/**
 * Utility: Parse query parameters
 */
function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

/**
 * Utility: Sleep function
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
