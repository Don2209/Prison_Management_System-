/**
 * Modern Data Table Component
 * Advanced search, sort, filter, and pagination
 */

/**
 * Build actions object from array of conditional action definitions
 * Filters out undefined/null values caused by PHP conditionals
 */
function buildActions(actionArray) {
    const actions = {};
    actionArray.forEach(actionObj => {
        if (actionObj && typeof actionObj === 'object') {
            Object.assign(actions, actionObj);
        }
    });
    return actions;
}

class ModernDataTable {
    constructor(tableId, apiEndpoint, columns, options = {}) {
        this.table = document.getElementById(tableId);
        this.apiEndpoint = apiEndpoint;
        this.columns = columns;
        this.options = {
            searchFields: options.searchFields || [],
            sortable: options.sortable !== false,
            paginated: options.paginated !== false,
            perPage: options.perPage || 20,
            actions: options.actions || {},
            onRowClick: options.onRowClick || null,
            ...options
        };
        
        this.currentPage = 1;
        this.data = [];
        this.filteredData = [];
        this.sortField = null;
        this.sortDirection = 'asc';
        
        this.init();
    }
    
    init() {
        this.createTableStructure();
        this.attachEventListeners();
        this.loadData();
    }
    
    createTableStructure() {
        this.table.innerHTML = `
            <div class="data-table-container">
                <div class="data-table-header">
                    <div class="data-table-title">${this.options.title || 'Data Table'}</div>
                    <div class="data-table-controls">
                        <div class="search-box">
                            <input type="text" id="${this.table.id}-search" placeholder="Search..." class="table-search">
                        </div>
                        <button class="btn-primary btn-sm" id="${this.table.id}-refresh">
                            🔄 Refresh
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="modern-table" id="${this.table.id}-table">
                        <thead>
                            <tr>
                                ${this.columns.map(col => `
                                    <th class="sortable" data-field="${col.field}">
                                        ${col.label}
                                        ${this.options.sortable ? '<span class="sort-icon">⬍</span>' : ''}
                                    </th>
                                `).join('')}
                                ${Object.keys(this.options.actions).length > 0 ? '<th>Actions</th>' : ''}
                            </tr>
                        </thead>
                        <tbody id="${this.table.id}-tbody">
                            ${[1, 2, 3, 4, 5].map(() => `
                                <tr>
                                    ${this.columns.map(() => '<td><div class="skeleton skeleton-line"></div></td>').join('')}
                                    ${Object.keys(this.options.actions).length > 0 ? '<td></td>' : ''}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div id="${this.table.id}-pagination" class="pagination"></div>
            </div>
        `;
    }
    
    attachEventListeners() {
        // Search
        const searchInput = document.getElementById(`${this.table.id}-search`);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        }
        
        // Refresh
        const refreshBtn = document.getElementById(`${this.table.id}-refresh`);
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadData());
        }
        
        // Sorting
        document.querySelectorAll(`#${this.table.id} .sortable`).forEach(th => {
            th.addEventListener('click', (e) => {
                const field = e.target.closest('th').getAttribute('data-field');
                this.handleSort(field);
            });
        });
    }
    
    async loadData() {
        try {
            const response = await apiCall(this.apiEndpoint);
            
            if (response.success) {
                this.data = response.data || [];
                this.filteredData = [...this.data];
                this.render();
            }
        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('Failed to load data');
        }
    }
    
    handleSearch(query) {
        const lowerQuery = query.toLowerCase();
        
        this.filteredData = this.data.filter(row => {
            return this.options.searchFields.some(field => {
                const value = String(row[field] || '').toLowerCase();
                return value.includes(lowerQuery);
            });
        });
        
        this.currentPage = 1;
        this.render();
    }
    
    handleSort(field) {
        if (this.sortField === field) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortField = field;
            this.sortDirection = 'asc';
        }
        
        this.filteredData.sort((a, b) => {
            const aVal = a[field];
            const bVal = b[field];
            
            if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        this.render();
    }
    
    render() {
        const tbody = document.getElementById(`${this.table.id}-tbody`);
        
        if (this.filteredData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.columns.length + (Object.keys(this.options.actions).length > 0 ? 1 : 0)}" style="text-align: center; padding: 2rem;">
                        <div style="color: rgba(255, 255, 255, 0.5);">No data found</div>
                    </td>
                </tr>
            `;
            return;
        }
        
        // Pagination
        const start = (this.currentPage - 1) * this.options.perPage;
        const end = start + this.options.perPage;
        const pageData = this.options.paginated ? this.filteredData.slice(start, end) : this.filteredData;
        
        // Render rows
        tbody.innerHTML = pageData.map(row => `
            <tr ${this.options.onRowClick ? 'style="cursor: pointer;"' : ''}>
                ${this.columns.map(col => `
                    <td>
                        ${col.render ? col.render(row[col.field], row) : this.formatCell(row[col.field], col.type)}
                    </td>
                `).join('')}
                ${Object.keys(this.options.actions).length > 0 ? `
                    <td class="action-buttons">
                        ${Object.entries(this.options.actions).map(([key, action]) => {
                            const icon = action.icon || '✓';
                            const iconHtml = icon.startsWith('bi-') ? `<i class="bi ${icon}"></i>` : icon;
                            return `
                            <button class="btn-action ${key}" title="${action.title || key}">
                                ${iconHtml} ${action.label || key}
                            </button>
                        `;
                        }).join('')}
                    </td>
                ` : ''}
            </tr>
        `).join('');
        
        // Attach row click listeners
        if (this.options.onRowClick) {
            tbody.querySelectorAll('tr').forEach((tr, idx) => {
                tr.addEventListener('click', () => this.options.onRowClick(pageData[idx]));
            });
        }
        
        // Attach action listeners
        Object.entries(this.options.actions).forEach(([key, action]) => {
            tbody.querySelectorAll(`.btn-action.${key}`).forEach((btn, idx) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    action.callback(pageData[idx]);
                });
            });
        });
        
        // Pagination
        if (this.options.paginated) {
            this.renderPagination();
        }
    }
    
    renderPagination() {
        const paginationDiv = document.getElementById(`${this.table.id}-pagination`);
        const totalPages = Math.ceil(this.filteredData.length / this.options.perPage);
        
        if (totalPages <= 1) {
            paginationDiv.innerHTML = '';
            return;
        }
        
        let html = '';
        
        // Previous
        html += `<button class="pagination-btn" ${this.currentPage === 1 ? 'disabled' : ''} onclick="this.closest('.pagination').parentElement.previousElementSibling.ModernDataTable.previousPage()">← Prev</button>`;
        
        // Pages
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 1 && i <= this.currentPage + 1)) {
                html += `<button class="pagination-btn ${i === this.currentPage ? 'active' : ''}">${i}</button>`;
            } else if (i === 2 || i === totalPages - 1) {
                html += `<span class="pagination-btn" style="cursor: default; border: none;">...</span>`;
            }
        }
        
        // Next
        html += `<button class="pagination-btn" ${this.currentPage === totalPages ? 'disabled' : ''}>Next →</button>`;
        
        paginationDiv.innerHTML = html;
        
        // Attach page clicks
        paginationDiv.querySelectorAll('.pagination-btn:not([disabled])').forEach((btn, idx) => {
            btn.addEventListener('click', (e) => {
                if (e.target.textContent === '← Prev') {
                    this.currentPage = Math.max(1, this.currentPage - 1);
                } else if (e.target.textContent === 'Next →') {
                    this.currentPage++;
                } else if (!isNaN(e.target.textContent)) {
                    this.currentPage = parseInt(e.target.textContent);
                }
                this.render();
            });
        });
    }
    
    formatCell(value, type) {
        if (value === null || value === undefined) return '-';
        
        if (type === 'badge') {
            return `<span class="status-badge status-${value?.toLowerCase() || 'inactive'}">${value}</span>`;
        }
        
        if (type === 'currency') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(value);
        }
        
        if (type === 'date') {
            return new Date(value).toLocaleDateString('en-US');
        }
        
        if (type === 'boolean') {
            return value ? '✓ Yes' : '✗ No';
        }
        
        return String(value);
    }
    
    showError(message) {
        const tbody = document.getElementById(`${this.table.id}-tbody`);
        tbody.innerHTML = `
            <tr>
                <td colspan="${this.columns.length + 1}" style="text-align: center; padding: 2rem;">
                    <div class="alert-glass alert-danger">${message}</div>
                </td>
            </tr>
        `;
    }
    
    previousPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.render();
        }
    }
}

// Global data table instances
const dataTables = {};

/**
 * Initialize modern data table
 */
function initDataTable(tableId, apiEndpoint, columns, options = {}) {
    dataTables[tableId] = new ModernDataTable(tableId, apiEndpoint, columns, options);
    return dataTables[tableId];
}

/**
 * Quick Filter for Tables
 */
function quickFilter(tableId, query) {
    if (dataTables[tableId]) {
        dataTables[tableId].handleSearch(query);
    }
}

/**
 * Export Table to CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const data = dataTables[tableId]?.filteredData || [];
    const columns = dataTables[tableId]?.columns || [];
    
    let csv = columns.map(col => `"${col.label}"`).join(',') + '\n';
    csv += data.map(row => 
        columns.map(col => `"${row[col.field] || ''}"`)
            .join(',')
    ).join('\n');
    
    downloadCSV(csv, filename);
}
