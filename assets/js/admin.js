// Admin Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = this.files[0]?.name || 'No file chosen';
            const displayElement = this.closest('.file-upload').querySelector('p');
            if (displayElement) {
                displayElement.textContent = `Selected: ${fileName}`;
            }
        });
    });
    
    // Toggle ad fields based on type
    window.toggleAdFields = function(adType) {
        document.getElementById('google-ad-fields').style.display = 'none';
        document.getElementById('image-ad-fields').style.display = 'none';
        
        if (adType === 'google') {
            document.getElementById('google-ad-fields').style.display = 'block';
        } else if (adType === 'image') {
            document.getElementById('image-ad-fields').style.display = 'block';
        }
    }
    
    // Confirm before destructive actions
    const deleteButtons = document.querySelectorAll('a[onclick*="confirm"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Real-time form validation
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '';
            }
        });
    });
    
    // Auto-save functionality for settings
    let saveTimeout;
    const settingsForm = document.querySelector('form');
    if (settingsForm && window.location.pathname.includes('settings')) {
        const inputs = settingsForm.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    showAutoSaveNotification();
                }, 2000);
            });
        });
    }
    
    // Show auto-save notification
    function showAutoSaveNotification() {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 1000;
            animation: slideInUp 0.3s ease;
        `;
        notification.textContent = 'Settings auto-saved!';
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Chart initialization for dashboard
    if (typeof Chart !== 'undefined' && document.getElementById('statsChart')) {
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Channel Views',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
    
    // Bulk actions
    window.selectAll = function(source) {
        const checkboxes = document.querySelectorAll('input[name="selected[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
    }
    
    // Search and filter in tables
    const searchInputs = document.querySelectorAll('.table-search');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = this.closest('.admin-card').querySelector('table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
    
    // Export functionality
    window.exportTableToCSV = function(filename) {
        const table = document.querySelector('table');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                row.push(cols[j].innerText);
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
});

// Admin utility functions
const Admin = {
    // Show loading spinner
    showLoading: function() {
        const spinner = document.createElement('div');
        spinner.id = 'admin-loading';
        spinner.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3498db;"></i>
                    <p style="margin-top: 15px;">Processing...</p>
                </div>
            </div>
        `;
        document.body.appendChild(spinner);
    },
    
    // Hide loading spinner
    hideLoading: function() {
        const spinner = document.getElementById('admin-loading');
        if (spinner) {
            spinner.remove();
        }
    },
    
    // Show confirmation dialog
    confirmAction: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    // Update stats in real-time
    updateStats: async function() {
        try {
            const response = await fetch('/admin/api/stats.php');
            const stats = await response.json();
            
            // Update stat cards
            document.querySelectorAll('.stat-number').forEach(element => {
                const statType = element.closest('.stat-card').querySelector('.stat-label').textContent.toLowerCase();
                if (statType.includes('channel')) {
                    element.textContent = stats.totalChannels;
                } else if (statType.includes('view')) {
                    element.textContent = stats.totalViews.toLocaleString();
                }
            });
        } catch (error) {
            console.error('Error updating stats:', error);
        }
    },
    
    // Initialize real-time updates
    initRealTimeUpdates: function() {
        // Update stats every 30 seconds
        setInterval(this.updateStats, 30000);
        
        // Check for new notifications
        setInterval(this.checkNotifications, 60000);
    },
    
    // Check for admin notifications
    checkNotifications: async function() {
        try {
            const response = await fetch('/admin/api/notifications.php');
            const notifications = await response.json();
            
            if (notifications.length > 0) {
                this.showNotification(`You have ${notifications.length} new notifications`);
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    },
    
    // Show notification
    showNotification: function(message) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 1000;
            animation: slideInRight 0.3s ease;
        `;
        notification.innerHTML = `
            <i class="fas fa-bell"></i> ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
};

// Initialize admin functionality when DOM is loaded
if (document.body.classList.contains('admin-body')) {
    Admin.initRealTimeUpdates();
}