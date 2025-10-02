/**
 * Comprehensive Admissions Management System
 * Main JavaScript File
 */

// Global App Object
window.AdmissionsApp = {
    // Configuration
    config: {
        apiUrl: '/api',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        uploadMaxSize: 10 * 1024 * 1024, // 10MB
        allowedFileTypes: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
        dateFormat: 'YYYY-MM-DD',
        timeFormat: 'HH:mm:ss'
    },

    // Initialize the application
    init: function() {
        this.initTooltips();
        this.initModals();
        this.initFileUploads();
        this.initFormValidation();
        this.initDataTables();
        this.initCharts();
        this.initNotifications();
        this.initAutoSave();
        this.initProgressBars();
    },

    // Initialize Bootstrap tooltips
    initTooltips: function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },

    // Initialize modals
    initModals: function() {
        // Auto-focus first input in modals
        document.addEventListener('shown.bs.modal', function (e) {
            const firstInput = e.target.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Clear form data when modal is hidden
        document.addEventListener('hidden.bs.modal', function (e) {
            const form = e.target.querySelector('form');
            if (form) {
                form.reset();
                // Clear validation classes
                form.classList.remove('was-validated');
                const inputs = form.querySelectorAll('.is-invalid, .is-valid');
                inputs.forEach(input => {
                    input.classList.remove('is-invalid', 'is-valid');
                });
            }
        });
    },

    // Initialize file uploads
    initFileUploads: function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                const maxSize = AdmissionsApp.config.uploadMaxSize;
                const allowedTypes = AdmissionsApp.config.allowedFileTypes;
                
                files.forEach(file => {
                    // Check file size
                    if (file.size > maxSize) {
                        AdmissionsApp.showNotification('File size exceeds 10MB limit', 'error');
                        e.target.value = '';
                        return;
                    }
                    
                    // Check file type
                    const fileExtension = file.name.split('.').pop().toLowerCase();
                    if (!allowedTypes.includes(fileExtension)) {
                        AdmissionsApp.showNotification('Invalid file type. Allowed: ' + allowedTypes.join(', '), 'error');
                        e.target.value = '';
                        return;
                    }
                });
                
                // Show file preview if image
                if (files.length > 0 && files[0].type.startsWith('image/')) {
                    AdmissionsApp.showImagePreview(files[0], e.target);
                }
            });
        });

        // Drag and drop functionality
        const dropAreas = document.querySelectorAll('.file-upload-area');
        dropAreas.forEach(area => {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                const fileInput = this.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        });
    },

    // Show image preview
    showImagePreview: function(file, input) {
        const reader = new FileReader();
        reader.onload = function(e) {
            let preview = input.parentNode.querySelector('.image-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview mt-2';
                input.parentNode.appendChild(preview);
            }
            
            preview.innerHTML = `
                <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                <div class="mt-1">
                    <small class="text-muted">${file.name} (${AdmissionsApp.formatFileSize(file.size)})</small>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    },

    // Initialize form validation
    initFormValidation: function() {
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        // Real-time validation
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                this.classList.add('was-validated');
            });
        });
    },

    // Initialize data tables
    initDataTables: function() {
        const tables = document.querySelectorAll('.data-table');
        
        tables.forEach(table => {
            // Add sorting functionality
            const headers = table.querySelectorAll('th[data-sort]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    AdmissionsApp.sortTable(table, this.dataset.sort);
                });
            });
        });
    },

    // Sort table
    sortTable: function(table, column) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAscending = table.dataset.sortDirection !== 'asc';
        
        rows.sort((a, b) => {
            const aVal = a.querySelector(`[data-sort-value="${column}"]`)?.textContent || '';
            const bVal = b.querySelector(`[data-sort-value="${column}"]`)?.textContent || '';
            
            if (isAscending) {
                return aVal.localeCompare(bVal);
            } else {
                return bVal.localeCompare(aVal);
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
        table.dataset.sortDirection = isAscending ? 'asc' : 'desc';
    },

    // Initialize charts
    initCharts: function() {
        // This would integrate with Chart.js if available
        const chartElements = document.querySelectorAll('[data-chart]');
        
        chartElements.forEach(element => {
            const type = element.dataset.chart;
            const data = JSON.parse(element.dataset.chartData || '{}');
            
            // Chart.js integration would go here
            console.log('Chart initialization:', type, data);
        });
    },

    // Initialize notifications
    initNotifications: function() {
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }
            }, 5000);
        });
    },

    // Initialize auto-save functionality
    initAutoSave: function() {
        const autoSaveForms = document.querySelectorAll('[data-auto-save]');
        
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');
            let saveTimeout;
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        AdmissionsApp.autoSaveForm(form);
                    }, 2000);
                });
            });
        });
    },

    // Auto-save form data
    autoSaveForm: function(form) {
        const formData = new FormData(form);
        const autoSaveUrl = form.dataset.autoSave;
        
        if (autoSaveUrl) {
            fetch(autoSaveUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    AdmissionsApp.showNotification('Draft saved', 'success', 2000);
                }
            })
            .catch(error => {
                console.error('Auto-save error:', error);
            });
        }
    },

    // Initialize progress bars
    initProgressBars: function() {
        const progressBars = document.querySelectorAll('.progress-bar[data-progress]');
        
        progressBars.forEach(bar => {
            const progress = bar.dataset.progress;
            bar.style.width = progress + '%';
            bar.setAttribute('aria-valuenow', progress);
        });
    },

    // Show notification
    showNotification: function(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
    },

    // Format file size
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    // Format date
    formatDate: function(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        
        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day);
    },

    // Confirm action
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    // AJAX request helper
    ajax: function(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (this.config.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = this.config.csrfToken;
        }
        
        const finalOptions = { ...defaultOptions, ...options };
        
        return fetch(url, finalOptions)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            });
    },

    // Export table to CSV
    exportTableToCSV: function(tableId, filename = 'export.csv') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td, th');
            const rowData = Array.from(cells).map(cell => {
                return '"' + cell.textContent.replace(/"/g, '""') + '"';
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        
        window.URL.revokeObjectURL(url);
    },

    // Print page
    printPage: function() {
        window.print();
    },

    // Copy to clipboard
    copyToClipboard: function(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showNotification('Copied to clipboard', 'success', 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            this.showNotification('Failed to copy to clipboard', 'error');
        });
    },

    // Search functionality
    initSearch: function(inputSelector, targetSelector) {
        const searchInput = document.querySelector(inputSelector);
        const targets = document.querySelectorAll(targetSelector);
        
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            targets.forEach(target => {
                const text = target.textContent.toLowerCase();
                const shouldShow = text.includes(searchTerm);
                
                target.style.display = shouldShow ? '' : 'none';
            });
        });
    },

    // Pagination helper
    paginate: function(items, page, perPage) {
        const startIndex = (page - 1) * perPage;
        const endIndex = startIndex + perPage;
        
        return {
            data: items.slice(startIndex, endIndex),
            totalPages: Math.ceil(items.length / perPage),
            currentPage: page,
            hasNext: page < Math.ceil(items.length / perPage),
            hasPrev: page > 1
        };
    }
};

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    AdmissionsApp.init();
});

// Global utility functions
window.showNotification = AdmissionsApp.showNotification;
window.exportTableToCSV = AdmissionsApp.exportTableToCSV;
window.printPage = AdmissionsApp.printPage;
