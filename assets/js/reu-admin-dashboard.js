
// Finver Pro - Dashboard Admin JavaScript
class FinverProAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.setupLoader();
        this.setupMobileMenu();
        this.setupDateFilter();
        this.setupNavigation();
        this.setupAnimations();
        this.setupResponsive();
        this.logInit();
    }

    // Remove loader after page load
    setupLoader() {
        window.addEventListener("load", () => {
            setTimeout(() => {
                const loader = document.getElementById('loader');
                if (loader) {
                    loader.style.display = 'none';
                }
            }, 1000);
        });
    }

    // Mobile menu functionality
    setupMobileMenu() {
        const menuButton = document.getElementById('menuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (!menuButton || !sidebar || !overlay) return;

        menuButton.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        });
    }

    // Date filter functionality
    setupDateFilter() {
        // Set default dates
        this.setDefaultDates();
        
        // Add filter button event
        const filterButton = document.querySelector('.filter-button');
        if (filterButton) {
            filterButton.addEventListener('click', (e) => this.filterByDate(e));
        }
    }

    setDefaultDates() {
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');
        
        if (endDateInput && startDateInput) {
            // Set current date as default for end date
            const today = new Date().toISOString().split('T')[0];
            endDateInput.value = today;
            
            // Set start date to 30 days ago
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
        }
    }

    filterByDate(event) {
        const startDate = document.getElementById('startDate')?.value;
        const endDate = document.getElementById('endDate')?.value;
        
        if (!startDate || !endDate) {
            this.showAlert('◷ Por favor, selecione ambas as datas para filtrar', 'warning');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            this.showAlert('⚠ A data inicial não pode ser maior que a data final!', 'error');
            return;
        }
        
        // Add loading effect
        const button = event.target.closest('.filter-button');
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="icon">⟳</span> Filtrando...';
        button.disabled = true;
        
        // Simulate API call or actual filtering
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            this.showAlert(`⊞ Dados filtrados de ${this.formatDate(startDate)} até ${this.formatDate(endDate)}`, 'success');
            
            // Here you would normally update the stats with filtered data
            this.updateStatsWithFilteredData(startDate, endDate);
        }, 1500);
    }

    updateStatsWithFilteredData(startDate, endDate) {
        // This would normally make an AJAX call to get filtered data
        // For now, we'll just add some visual feedback
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.style.opacity = '0.7';
            setTimeout(() => {
                card.style.opacity = '1';
            }, 500);
        });
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }

    showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert ${type}`;
        alert.innerHTML = message;

        // Insert at the top of main content
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alert, container.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
    }

    // Navigation functionality
    setupNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                // Don't prevent default to allow normal navigation
                
                // Remove active class from all items
                navItems.forEach(nav => nav.classList.remove('active'));
                // Add active class to clicked item
                item.classList.add('active');
                
                // Close mobile menu if open
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('overlay');
                    
                    if (sidebar && overlay) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                }
            });
        });
    }

    // Animation effects
    setupAnimations() {
        this.setupStatCardAnimations();
        this.setupButtonAnimations();
    }

    setupStatCardAnimations() {
        const statCards = document.querySelectorAll('.stat-card');
        
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-6px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }

    setupButtonAnimations() {
        const buttons = document.querySelectorAll('button, .welcome-button');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    }

    // Responsive handling
    setupResponsive() {
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                
                if (sidebar && overlay) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            }
        });
    }

    // AJAX Functions for real-time updates
    async loadDashboardStats(startDate = null, endDate = null) {
        try {
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            const response = await fetch(`api/dashboard-stats.php?${params}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            this.updateStatsDisplay(data);
            
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
            this.showAlert('Erro ao carregar dados do dashboard', 'error');
        }
    }

    updateStatsDisplay(data) {
        // Update each stat card with real data
        const updates = {
            'depositos-hoje': data.deposits_today || '0',
            'total-depositos': data.total_deposits || '0',
            'cadastros-hoje': data.registrations_today || '0',
            'total-cadastros': data.total_registrations || '0',
            'total-sacado': data.total_withdrawals || '0',
            'comissoes-hoje': data.commissions_today || '0',
            'salarios-hoje': data.salaries_today || '0',
            'saldo-plataforma': data.platform_balance || '0',
            'investidores-ativos': data.active_investors || '0',
            'codigos-usados': data.codes_used || '0'
        };

        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                // Format currency values
                if (id.includes('depositos') || id.includes('sacado') || id.includes('comissoes') || id.includes('salarios') || id.includes('saldo')) {
                    element.textContent = this.formatCurrency(value);
                } else {
                    element.textContent = this.formatNumber(value);
                }
            }
        });
    }

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    formatNumber(value) {
        return new Intl.NumberFormat('pt-BR').format(value);
    }

    async loadRecentTransactions() {
        try {
            const response = await fetch('api/recent-transactions.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const transactions = await response.json();
            this.updateTransactionsDisplay(transactions);
            
        } catch (error) {
            console.error('Erro ao carregar transações:', error);
        }
    }

    updateTransactionsDisplay(transactions) {
        const container = document.querySelector('.transactions-section');
        const existingItems = container.querySelectorAll('.transaction-item');
        
        // Remove existing transaction items
        existingItems.forEach(item => item.remove());
        
        // Add new transactions
        transactions.forEach(transaction => {
            const item = this.createTransactionItem(transaction);
            container.appendChild(item);
        });
    }

    createTransactionItem(transaction) {
        const item = document.createElement('div');
        item.className = 'transaction-item';
        
        const iconClass = this.getTransactionIconClass(transaction.type);
        const amountClass = this.getTransactionAmountClass(transaction.type);
        const formattedAmount = this.formatCurrency(transaction.amount);
        const formattedDate = this.formatDate(transaction.created_at);
        
        item.innerHTML = `
            <div class="transaction-left">
                <div class="transaction-icon ${iconClass}">
                    <span class="icon">${this.getTransactionIcon(transaction.type)}</span>
                </div>
                <div class="transaction-info">
                    <h4>${transaction.description}</h4>
                    <span>${formattedDate}</span>
                </div>
            </div>
            <div class="transaction-amount" style="color: var(--${amountClass});">
                ${transaction.type === 'deposito' ? '+' : '-'}${formattedAmount}
            </div>
        `;
        
        return item;
    }

    getTransactionIconClass(type) {
        const classes = {
            'deposito': 'style="background: var(--gradient-success);"',
            'saque': 'style="background: var(--gradient-danger);"',
            'comissao': 'style="background: var(--gradient-warning);"',
            'salario': 'style="background: var(--gradient-info);"'
        };
        return classes[type] || '';
    }

    getTransactionAmountClass(type) {
        const classes = {
            'deposito': 'success',
            'saque': 'danger',
            'comissao': 'warning',
            'salario': 'info'
        };
        return classes[type] || 'text-primary';
    }

    getTransactionIcon(type) {
        const icons = {
            'deposito': '↗',
            'saque': '↙',
            'comissao': '%',
            'salario': '₹'
        };
        return icons[type] || '◉';
    }

    // Initialize auto-refresh
    startAutoRefresh() {
        // Refresh dashboard every 5 minutes
        setInterval(() => {
            this.loadDashboardStats();
            this.loadRecentTransactions();
        }, 300000); // 5 minutes
    }

    // Logout functionality
    async logout() {
        try {
            const response = await fetch('logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            if (response.ok) {
                window.location.href = 'loginadmin.php';
            } else {
                this.showAlert('Erro ao fazer logout', 'error');
            }
        } catch (error) {
            console.error('Erro no logout:', error);
            this.showAlert('Erro ao fazer logout', 'error');
        }
    }

    // Console log for debugging
    logInit() {
        console.log('→ Finver Pro Dashboard carregado com sucesso!');
        console.log('◉ Responsivo para mobile e desktop');
        console.log('⊞ Modo escuro ativo');
        console.log('⚙ Sistema de admin inicializado');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.finverProAdmin = new FinverProAdmin();
});

// Global functions for backward compatibility
function filterByDate() {
    if (window.finverProAdmin) {
        const event = { target: document.querySelector('.filter-button') };
        window.finverProAdmin.filterByDate(event);
    }
}
