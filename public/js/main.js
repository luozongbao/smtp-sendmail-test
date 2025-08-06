/**
 * SMTP Test Tool - Main JavaScript File
 * Handles all frontend interactions and API communications
 */

class SMTPTestTool {
    constructor() {
        this.currentTab = 'smtp-test';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadTestLogs();
        this.setupPortTypeToggle();
    }

    setupEventListeners() {
        // Tab navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
        });

        // Form submissions
        document.getElementById('smtp-test-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSMTPTest();
        });

        document.getElementById('imap-test-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleIMAPTest();
        });

        document.getElementById('port-scan-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handlePortScan();
        });

        document.getElementById('email-send-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleEmailSend();
        });

        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    setupPortTypeToggle() {
        const portTypeRadios = document.querySelectorAll('input[name="port_type"]');
        const customPortsDiv = document.getElementById('custom-ports');

        portTypeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'custom') {
                    customPortsDiv.style.display = 'grid';
                } else {
                    customPortsDiv.style.display = 'none';
                }
            });
        });
    }

    switchTab(tabId) {
        // Update navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');

        // Update content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tabId).classList.add('active');

        this.currentTab = tabId;

        // Load logs if logs tab is selected
        if (tabId === 'test-logs') {
            this.loadTestLogs();
        }
    }

    showLoading() {
        document.getElementById('loading-overlay').classList.add('active');
    }

    hideLoading() {
        document.getElementById('loading-overlay').classList.remove('active');
    }

    showModal(title, content) {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-results').innerHTML = content;
        document.getElementById('results-modal').classList.add('active');
    }

    closeModal() {
        document.getElementById('results-modal').classList.remove('active');
    }

    async handleSMTPTest() {
        const form = document.getElementById('smtp-test-form');
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;

        try {
            // Disable form and show progress
            this.setFormLoading(form, true, 'Testing SMTP...');

            const response = await fetch('api/smtp-test.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            this.displayTestResults('SMTP Test Results', result);
        } catch (error) {
            this.showError('SMTP Test Error', error.message);
        } finally {
            // Re-enable form
            this.setFormLoading(form, false, originalText);
        }
    }

    async handleIMAPTest() {
        const form = document.getElementById('imap-test-form');
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;

        try {
            // Disable form and show progress
            this.setFormLoading(form, true, 'Testing IMAP...');

            const response = await fetch('api/imap-test.php', {
                method: 'POST',
                body: formData
            });

            // Get response text first
            const responseText = await response.text();
            console.log('IMAP API Response:', responseText);
            
            try {
                const result = JSON.parse(responseText);
                this.displayTestResults('IMAP Test Results', result);
            } catch (jsonError) {
                console.error('JSON parsing error:', jsonError);
                console.error('Raw response:', responseText);
                this.showError('IMAP Test Error', `Server returned invalid JSON. Response: ${responseText.substring(0, 200)}...`);
            }
        } catch (error) {
            console.error('IMAP request error:', error);
            this.showError('IMAP Test Error', error.message);
        } finally {
            // Re-enable form
            this.setFormLoading(form, false, originalText);
        }
    }

    async handlePortScan() {
        const form = document.getElementById('port-scan-form');
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;

        try {
            // Disable form and show progress
            this.setFormLoading(form, true, 'Scanning ports...');
            
            console.log('Port scan request data:', Object.fromEntries(formData));
            
            const response = await fetch('api/port-scan.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('Port scan response:', result);
            
            this.displayTestResults('Port Scan Results', result);
        } catch (error) {
            console.error('Port scan error:', error);
            this.showError('Port Scan Error', error.message);
        } finally {
            // Re-enable form
            this.setFormLoading(form, false, originalText);
        }
    }

    async handleEmailSend() {
        const form = document.getElementById('email-send-form');
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;

        try {
            // Disable form and show progress
            this.setFormLoading(form, true, 'Sending email...');

            const response = await fetch('api/send-email.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            this.displayTestResults('Email Send Results', result);
        } catch (error) {
            this.showError('Email Send Error', error.message);
        } finally {
            // Re-enable form
            this.setFormLoading(form, false, originalText);
        }
    }

    async loadTestLogs() {
        const logsContainer = document.getElementById('logs-container');
        
        try {
            const response = await fetch('api/get-logs.php');
            const result = await response.json();

            if (result.success && result.logs) {
                this.displayLogs(result.logs);
            } else {
                logsContainer.innerHTML = '<div class="loading-message"><i class="fas fa-exclamation-circle"></i> No test logs found.</div>';
            }
        } catch (error) {
            logsContainer.innerHTML = '<div class="loading-message"><i class="fas fa-exclamation-triangle"></i> Error loading test logs.</div>';
        }
    }

    async clearLogs() {
        if (!confirm('Are you sure you want to clear all test logs? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/clear-logs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.loadTestLogs();
                this.showNotification('Logs cleared successfully', 'success');
            } else {
                this.showNotification('Failed to clear logs', 'error');
            }
        } catch (error) {
            this.showNotification('Error clearing logs', 'error');
        }
    }

    displayTestResults(title, result) {
        console.log('Displaying results for:', title, result);
        let html = '';

        if (result.success) {
            html += '<div class="result-section">';
            html += '<h4><i class="fas fa-check-circle" style="color: var(--success-color);"></i> Test Completed Successfully</h4>';
            
            if (result.connection_info) {
                html += '<div class="result-item success">';
                html += '<div class="result-label">Connection Status</div>';
                html += '<div class="result-value">' + this.escapeHtml(result.connection_info) + '</div>';
                html += '</div>';
            }

            if (result.server_info) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Server Information</div>';
                html += '<div class="result-value">' + this.escapeHtml(result.server_info) + '</div>';
                html += '</div>';
            }

            if (result.capabilities && Array.isArray(result.capabilities)) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Server Capabilities</div>';
                html += '<div class="result-value">' + result.capabilities.map(cap => this.escapeHtml(cap)).join('<br>') + '</div>';
                html += '</div>';
            }

            if (result.details && Array.isArray(result.details)) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Connection Details</div>';
                html += '<div class="result-value">' + result.details.map(detail => this.escapeHtml(detail)).join('<br>') + '</div>';
                html += '</div>';
            }

            if (result.response_time) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Response Time</div>';
                html += '<div class="result-value">' + result.response_time + 'ms</div>';
                html += '</div>';
            }

            if (result.mailboxes && Array.isArray(result.mailboxes)) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Available Mailboxes</div>';
                html += '<div class="result-value">' + result.mailboxes.map(mb => this.escapeHtml(mb)).join('<br>') + '</div>';
                html += '</div>';
            }

            // Port scan specific results
            if (result.open_ports !== undefined && Array.isArray(result.open_ports)) {
                console.log('Processing open ports:', result.open_ports, 'Details:', result.details);
                
                if (result.open_ports.length > 0) {
                    html += '<div class="result-item success">';
                    html += '<div class="result-label">Open Ports (' + result.open_ports.length + ')</div>';
                    html += '<div class="result-value">';
                    result.open_ports.forEach(port => {
                        const portDetail = result.details && result.details[port] ? result.details[port] : {};
                        const service = portDetail.service || 'Unknown Service';
                        const responseTime = portDetail.response_time || 0;
                        const banner = portDetail.banner ? ` - ${this.escapeHtml(portDetail.banner.substring(0, 50))}` : '';
                        html += `<div style="margin-bottom: 5px;"><strong>Port ${port}</strong>: ${this.escapeHtml(service)} (${responseTime}ms)${banner}</div>`;
                    });
                    html += '</div></div>';
                } else {
                    html += '<div class="result-item warning">';
                    html += '<div class="result-label">Open Ports</div>';
                    html += '<div class="result-value">No open ports found</div>';
                    html += '</div>';
                }
            }

            if (result.closed_ports !== undefined && Array.isArray(result.closed_ports) && result.closed_ports.length > 0) {
                html += '<div class="result-item error">';
                html += '<div class="result-label">Closed/Filtered Ports (' + result.closed_ports.length + ')</div>';
                html += '<div class="result-value">';
                if (result.closed_ports.length <= 10) {
                    // Show all closed ports if not too many
                    html += result.closed_ports.join(', ');
                } else {
                    // Show first 10 and count
                    html += result.closed_ports.slice(0, 10).join(', ') + ` and ${result.closed_ports.length - 10} more`;
                }
                html += '</div></div>';
            }

            // Show scan summary
            if (result.total_ports || result.scan_time) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Scan Summary</div>';
                html += '<div class="result-value">';
                if (result.total_ports) html += `Total ports scanned: ${result.total_ports}<br>`;
                if (result.scan_time) html += `Scan time: ${result.scan_time}ms<br>`;
                if (result.host) html += `Target host: ${this.escapeHtml(result.host)}`;
                html += '</div></div>';
            }

            if (result.email_sent) {
                html += '<div class="result-item success">';
                html += '<div class="result-label">Email Status</div>';
                html += '<div class="result-value">Email sent successfully!</div>';
                html += '</div>';
            }

            if (result.additional_info) {
                html += '<div class="result-item">';
                html += '<div class="result-label">Additional Information</div>';
                html += '<div class="result-value">' + this.escapeHtml(result.additional_info) + '</div>';
                html += '</div>';
            }

            html += '</div>';
        } else {
            html += '<div class="result-section">';
            html += '<h4><i class="fas fa-exclamation-circle" style="color: var(--error-color);"></i> Test Failed</h4>';
            html += '<div class="result-item error">';
            html += '<div class="result-label">Error Message</div>';
            html += '<div class="result-value">' + this.escapeHtml(result.error || 'Unknown error occurred') + '</div>';
            html += '</div>';

            if (result.debug_info) {
                html += '<div class="result-item warning">';
                html += '<div class="result-label">Debug Information</div>';
                html += '<div class="result-value">' + this.escapeHtml(result.debug_info) + '</div>';
                html += '</div>';
            }

            html += '</div>';
        }

        this.showModal(title, html);
    }

    displayLogs(logs) {
        const logsContainer = document.getElementById('logs-container');
        
        if (!logs || logs.length === 0) {
            logsContainer.innerHTML = '<div class="loading-message"><i class="fas fa-info-circle"></i> No test logs found.</div>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            const logClass = this.getLogClass(log.status);
            const timestamp = new Date(log.created_at).toLocaleString();
            
            html += `<div class="log-entry ${logClass}">`;
            html += `<div class="log-timestamp">${timestamp}</div>`;
            html += `<div class="log-message">`;
            html += `<strong>${this.escapeHtml(log.test_type)}</strong> - ${this.escapeHtml(log.target_host)}:${log.target_port}<br>`;
            html += `Status: <span class="status-indicator ${logClass}">${this.escapeHtml(log.status)}</span><br>`;
            if (log.result_data) {
                html += `Result: ${this.escapeHtml(log.result_data)}`;
            }
            html += `</div></div>`;
        });

        logsContainer.innerHTML = html;
    }

    getLogClass(status) {
        switch (status.toLowerCase()) {
            case 'success':
            case 'passed':
                return 'success';
            case 'failed':
            case 'error':
                return 'error';
            case 'warning':
                return 'warning';
            default:
                return 'info';
        }
    }

    showError(title, message) {
        const html = `
            <div class="result-section">
                <h4><i class="fas fa-exclamation-triangle" style="color: var(--error-color);"></i> Error</h4>
                <div class="result-item error">
                    <div class="result-label">Error Message</div>
                    <div class="result-value">${this.escapeHtml(message)}</div>
                </div>
            </div>
        `;
        this.showModal(title, html);
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            <span>${this.escapeHtml(message)}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;

        // Add styles if not already present
        if (!document.getElementById('notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 1rem 1.5rem;
                    border-radius: var(--radius-md);
                    color: white;
                    font-weight: 500;
                    z-index: 1001;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    min-width: 300px;
                    animation: slideInRight 0.3s ease;
                }
                .notification-success { background: var(--success-color); }
                .notification-error { background: var(--error-color); }
                .notification-warning { background: var(--warning-color); }
                .notification-info { background: var(--primary-color); }
                .notification button {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.2rem;
                    cursor: pointer;
                    margin-left: auto;
                    padding: 0.25rem;
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            default: return 'info-circle';
        }
    }

    escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setFormLoading(form, isLoading, buttonText = null) {
        const submitButton = form.querySelector('button[type="submit"]');
        const inputs = form.querySelectorAll('input, select, textarea, button');
        
        if (isLoading) {
            // Disable all form elements
            inputs.forEach(input => {
                input.disabled = true;
            });
            
            // Update button text if provided
            if (buttonText && submitButton) {
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + buttonText;
            }
        } else {
            // Re-enable all form elements
            inputs.forEach(input => {
                input.disabled = false;
            });
            
            // Restore button text if provided
            if (buttonText && submitButton) {
                submitButton.innerHTML = buttonText;
            }
        }
    }
}

// Global utility functions
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        // Reset custom port visibility
        const customPortsDiv = document.getElementById('custom-ports');
        if (customPortsDiv) {
            customPortsDiv.style.display = 'none';
        }
    }
}

function closeModal() {
    if (window.smtpTestTool) {
        window.smtpTestTool.closeModal();
    }
}

function loadTestLogs() {
    if (window.smtpTestTool) {
        window.smtpTestTool.loadTestLogs();
    }
}

function clearLogs() {
    if (window.smtpTestTool) {
        window.smtpTestTool.clearLogs();
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.smtpTestTool = new SMTPTestTool();
});
