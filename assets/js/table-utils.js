/**
 * Table Utilities for Sorting and Bulk Actions
 * Implements modern table features as recommended in Improvements.txt
 */

class TableManager {
    constructor(tableId, options = {}) {
        this.table = document.getElementById(tableId);
        this.options = {
            sortable: true,
            bulkActions: true,
            searchable: true,
            pagination: false,
            ...options
        };
        
        if (this.table) {
            this.init();
        }
    }
    
    init() {
        if (this.options.sortable) {
            this.addSorting();
        }
        
        if (this.options.bulkActions) {
            this.addBulkActions();
        }
        
        if (this.options.searchable) {
            this.addSearch();
        }
    }
    
    addSorting() {
        const headers = this.table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.innerHTML += ' <i class="bi bi-arrow-down-up text-muted"></i>';
            
            header.addEventListener('click', () => {
                this.sortTable(header.dataset.sort, header);
            });
        });
    }
    
    sortTable(column, header) {
        const tbody = this.table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAscending = header.classList.contains('sort-asc');
        
        // Remove sort classes from all headers
        this.table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        // Add appropriate sort class
        header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
        
        // Sort rows
        rows.sort((a, b) => {
            const aVal = this.getCellValue(a, column);
            const bVal = this.getCellValue(b, column);
            
            if (aVal < bVal) return isAscending ? 1 : -1;
            if (aVal > bVal) return isAscending ? -1 : 1;
            return 0;
        });
        
        // Reorder rows in DOM
        rows.forEach(row => tbody.appendChild(row));
    }
    
    getCellValue(row, column) {
        const cell = row.querySelector(`[data-sort-value]`) || 
                    row.cells[parseInt(column)] || 
                    row.querySelector(`td:nth-child(${parseInt(column) + 1})`);
        
        if (!cell) return '';
        
        const value = cell.dataset.sortValue || cell.textContent.trim();
        
        // Try to parse as number
        const numValue = parseFloat(value);
        if (!isNaN(numValue)) return numValue;
        
        // Try to parse as date
        const dateValue = new Date(value);
        if (!isNaN(dateValue.getTime())) return dateValue.getTime();
        
        return value.toLowerCase();
    }
    
    addBulkActions() {
        // Add select all checkbox to header
        const thead = this.table.querySelector('thead tr');
        if (thead && !thead.querySelector('.bulk-select-all')) {
            const th = document.createElement('th');
            th.className = 'bulk-select-all';
            th.style.width = '40px';
            th.innerHTML = '<input type="checkbox" class="bulk-select-all-checkbox">';
            thead.insertBefore(th, thead.firstChild);
        }
        
        // Add checkboxes to each row
        const tbody = this.table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(row => {
                if (!row.querySelector('.bulk-select')) {
                    const td = document.createElement('td');
                    td.className = 'bulk-select';
                    td.innerHTML = '<input type="checkbox" class="bulk-select-checkbox" data-id="' + (row.dataset.id || '') + '">';
                    row.insertBefore(td, row.firstChild);
                }
            });
        }
        
        // Add bulk actions toolbar
        this.addBulkActionsToolbar();
        
        // Add event listeners
        this.addBulkEventListeners();
    }
    
    addBulkActionsToolbar() {
        const existingToolbar = document.querySelector('.bulk-actions-toolbar');
        if (existingToolbar) return;
        
        const toolbar = document.createElement('div');
        toolbar.className = 'bulk-actions-toolbar';
        toolbar.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--space-3) var(--space-4);
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: var(--space-3);
            z-index: 1000;
        `;
        
        toolbar.innerHTML = `
            <span class="bulk-count">0 selected</span>
            <div class="bulk-actions">
                <button class="btn btn-sm btn-danger bulk-delete" data-action="delete">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <button class="btn btn-sm btn-secondary bulk-approve" data-action="approve">
                    <i class="bi bi-check"></i> Approve
                </button>
                <button class="btn btn-sm btn-secondary bulk-reject" data-action="reject">
                    <i class="bi bi-x"></i> Reject
                </button>
            </div>
            <button class="btn btn-sm btn-ghost bulk-cancel">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        document.body.appendChild(toolbar);
    }
    
    addBulkEventListeners() {
        const selectAllCheckbox = this.table.querySelector('.bulk-select-all-checkbox');
        const rowCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox');
        const toolbar = document.querySelector('.bulk-actions-toolbar');
        const bulkCount = toolbar.querySelector('.bulk-count');
        
        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
                this.updateBulkToolbar();
            });
        }
        
        // Individual row checkboxes
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateBulkToolbar();
                this.updateSelectAllState();
            });
        });
        
        // Bulk action buttons
        toolbar.querySelectorAll('[data-action]').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.performBulkAction(action);
            });
        });
        
        // Cancel bulk actions
        toolbar.querySelector('.bulk-cancel').addEventListener('click', () => {
            this.clearBulkSelection();
        });
    }
    
    updateBulkToolbar() {
        const selectedCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox:checked');
        const toolbar = document.querySelector('.bulk-actions-toolbar');
        const bulkCount = toolbar.querySelector('.bulk-count');
        
        if (selectedCheckboxes.length > 0) {
            toolbar.style.display = 'flex';
            bulkCount.textContent = `${selectedCheckboxes.length} selected`;
        } else {
            toolbar.style.display = 'none';
        }
    }
    
    updateSelectAllState() {
        const allCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox');
        const checkedCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox:checked');
        const selectAllCheckbox = this.table.querySelector('.bulk-select-all-checkbox');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCheckboxes.length === allCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
        }
    }
    
    performBulkAction(action) {
        const selectedCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.id).filter(id => id);
        
        if (selectedIds.length === 0) {
            toast('No items selected', 'warning');
            return;
        }
        
        const actionText = {
            'delete': 'delete',
            'approve': 'approve',
            'reject': 'reject'
        }[action] || action;
        
        if (confirm(`Are you sure you want to ${actionText} ${selectedIds.length} item(s)?`)) {
            // This would typically make an AJAX request to the server
            console.log(`Bulk ${action}:`, selectedIds);
            toast(`${selectedIds.length} item(s) ${actionText}d successfully`, 'success');
            this.clearBulkSelection();
        }
    }
    
    clearBulkSelection() {
        const allCheckboxes = this.table.querySelectorAll('.bulk-select-checkbox');
        const selectAllCheckbox = this.table.querySelector('.bulk-select-all-checkbox');
        
        allCheckboxes.forEach(checkbox => checkbox.checked = false);
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        
        const toolbar = document.querySelector('.bulk-actions-toolbar');
        toolbar.style.display = 'none';
    }
    
    addSearch() {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search';
        searchContainer.style.cssText = `
            margin-bottom: var(--space-4);
            display: flex;
            gap: var(--space-2);
            align-items: center;
        `;
        
        searchContainer.innerHTML = `
            <div class="input-icon" style="flex: 1; max-width: 300px;">
                <i class="bi bi-search icon"></i>
                <input type="text" class="input table-search-input" placeholder="Search...">
            </div>
            <button class="btn btn-sm btn-secondary table-clear-search" style="display: none;">
                <i class="bi bi-x"></i>
            </button>
        `;
        
        this.table.parentNode.insertBefore(searchContainer, this.table);
        
        const searchInput = searchContainer.querySelector('.table-search-input');
        const clearButton = searchContainer.querySelector('.table-clear-search');
        
        searchInput.addEventListener('input', (e) => {
            this.filterTable(e.target.value);
            clearButton.style.display = e.target.value ? 'block' : 'none';
        });
        
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            this.filterTable('');
            clearButton.style.display = 'none';
        });
    }
    
    filterTable(searchTerm) {
        const tbody = this.table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }
}

// Auto-initialize tables with data-table attribute
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-table]').forEach(table => {
        const tableId = table.id || 'table-' + Math.random().toString(36).substr(2, 9);
        table.id = tableId;
        
        new TableManager(tableId, {
            sortable: table.dataset.sortable !== 'false',
            bulkActions: table.dataset.bulkActions !== 'false',
            searchable: table.dataset.searchable !== 'false'
        });
    });
});
