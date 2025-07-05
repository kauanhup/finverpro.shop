/**
 * ========================================
 * FINVER PRO - ADMIN PANEL JAVASCRIPT
 * Sistema de Interatividade Moderno
 * ========================================
 */

class FinverAdmin {
    constructor() {
        this.sidebar = document.querySelector('.sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.mobileToggle = document.querySelector('.mobile-menu-toggle');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.isMobile = window.innerWidth <= 1024;
        this.toastContainer = null;
        this.modals = new Map();
        
        this.init();
    }
    
    /**
     * Inicializar todas as funcionalidades
     */
    init() {
        this.setupEventListeners();
        this.setupSidebar();
        this.setupToasts();
        this.setupModals();
        this.setupTables();
        this.setupForms();
        this.setupRealTimeUpdates();
        this.setupKeyboardShortcuts();
        this.checkResponsive();
        
        console.log('🚀 Finver Admin Panel carregado com sucesso!');
    }
    
    /**
     * ========================================
     * CONFIGURAÇÃO DE EVENT LISTENERS
     * ========================================
     */
    setupEventListeners() {
        // Resize da janela
        window.addEventListener('resize', () => {
            this.checkResponsive();
        });
        
        // Toggle do menu mobile
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleSidebar();
            });
        }
        
        // Toggle do sidebar desktop
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.collapseSidebar();
            });
        }
        
        // Fechar sidebar ao clicar fora (mobile)
        document.addEventListener('click', (e) => {
            if (this.isMobile && this.sidebar && this.sidebar.classList.contains('open')) {
                if (!this.sidebar.contains(e.target) && 
                    !this.mobileToggle?.contains(e.target)) {
                    this.closeSidebar();
                }
            }
        });
        
        // Prevenir propagação no sidebar
        if (this.sidebar) {
            this.sidebar.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    }
    
    /**
     * ========================================
     * GERENCIAMENTO DO SIDEBAR
     * ========================================
     */
    setupSidebar() {
        // Marcar link ativo
        this.setActiveNavLink();
        
        // Animações de hover nos links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('mouseenter', () => {
                if (!link.classList.contains('active')) {
                    link.style.transform = 'translateX(8px)';
                }
            });
            
            link.addEventListener('mouseleave', () => {
                if (!link.classList.contains('active')) {
                    link.style.transform = 'translateX(0)';
                }
            });
        });
    }
    
    setActiveNavLink() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            
            // Verificar se o href do link corresponde ao caminho atual
            const linkPath = new URL(link.href).pathname;
            if (currentPath.includes(linkPath.split('/').slice(-2, -1)[0]) || 
                currentPath === linkPath) {
                link.classList.add('active');
            }
        });
    }
    
    toggleSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.toggle('open');
            
            // Adicionar animação
            if (this.sidebar.classList.contains('open')) {
                this.sidebar.style.animation = 'slideIn 0.3s ease-out';
            }
        }
    }
    
    collapseSidebar() {
        if (this.sidebar && this.mainContent) {
            this.sidebar.classList.toggle('collapsed');
            this.mainContent.classList.toggle('expanded');
            
            // Salvar estado no localStorage
            const collapsed = this.sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', collapsed);
        }
    }
    
    closeSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.remove('open');
        }
    }
    
    checkResponsive() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 1024;
        
        // Se mudou de mobile para desktop ou vice-versa
        if (wasMobile !== this.isMobile) {
            if (this.isMobile) {
                // Mudou para mobile
                this.sidebar?.classList.remove('collapsed');
                this.mainContent?.classList.remove('expanded');
                this.closeSidebar();
            } else {
                // Mudou para desktop
                this.sidebar?.classList.remove('open');
                
                // Restaurar estado colapsado do localStorage
                const collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                if (collapsed) {
                    this.sidebar?.classList.add('collapsed');
                    this.mainContent?.classList.add('expanded');
                }
            }
        }
    }
    
    /**
     * ========================================
     * SISTEMA DE NOTIFICAÇÕES (TOASTS)
     * ========================================
     */
    setupToasts() {
        // Criar container de toasts se não existir
        if (!document.querySelector('.toast-container')) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.className = 'toast-container';
            document.body.appendChild(this.toastContainer);
        } else {
            this.toastContainer = document.querySelector('.toast-container');
        }
    }
    
    showToast(title, message, type = 'info', duration = 5000) {
        const toastId = 'toast-' + Date.now();
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.id = toastId;
        
        const icons = {
            success: 'fas fa-check-circle',
            danger: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };
        
        toast.innerHTML = `
            <div class="toast-header">
                <div class="toast-title">
                    <i class="${icons[type] || icons.info}"></i>
                    ${title}
                </div>
                <button class="toast-close" onclick="finverAdmin.closeToast('${toastId}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        
        this.toastContainer.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto remover
        if (duration > 0) {
            setTimeout(() => {
                this.closeToast(toastId);
            }, duration);
        }
        
        return toastId;
    }
    
    closeToast(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }
    
    /**
     * ========================================
     * SISTEMA DE MODAIS
     * ========================================
     */
    setupModals() {
        // Configurar modais existentes
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            const modal = overlay.querySelector('.modal');
            const closeButtons = overlay.querySelectorAll('.modal-close, [data-dismiss="modal"]');
            
            // Fechar ao clicar no overlay
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closeModal(overlay.id);
                }
            });
            
            // Fechar ao clicar nos botões de fechar
            closeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.closeModal(overlay.id);
                });
            });
            
            // Prevenir propagação no modal
            modal?.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });
    }
    
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focar no primeiro elemento focável
            const focusable = modal.querySelector('input, button, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusable) {
                setTimeout(() => focusable.focus(), 100);
            }
        }
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
    
    createModal(options) {
        const modalId = 'modal-' + Date.now();
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = modalId;
        
        const buttons = options.buttons || [];
        const buttonHtml = buttons.map(btn => 
            `<button class="btn btn-${btn.type || 'secondary'}" ${btn.onclick ? `onclick="${btn.onclick}"` : ''}>
                ${btn.icon ? `<i class="${btn.icon}"></i>` : ''}
                ${btn.text}
            </button>`
        ).join('');
        
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">${options.title || 'Modal'}</h3>
                    <button class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    ${options.content || ''}
                </div>
                ${buttons.length ? `<div class="modal-footer">${buttonHtml}</div>` : ''}
            </div>
        `;
        
        document.body.appendChild(modal);
        this.setupModals(); // Reconfigurar eventos
        
        return modalId;
    }
    
    /**
     * ========================================
     * MELHORIAS EM TABELAS
     * ========================================
     */
    setupTables() {
        // Configurar ordenação nas tabelas
        document.querySelectorAll('.table th[data-sort]').forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(header);
            });
        });
        
        // Configurar seleção em massa
        document.querySelectorAll('.table-select-all').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target);
            });
        });
        
        // Hover effects nas linhas
        document.querySelectorAll('.table tr').forEach(row => {
            if (row.parentElement.tagName !== 'THEAD') {
                row.addEventListener('mouseenter', () => {
                    row.style.transform = 'scale(1.002)';
                    row.style.transition = 'transform 0.2s ease';
                });
                
                row.addEventListener('mouseleave', () => {
                    row.style.transform = 'scale(1)';
                });
            }
        });
    }
    
    sortTable(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const column = Array.from(header.parentElement.children).indexOf(header);
        const currentSort = header.dataset.sort;
        const isAsc = currentSort !== 'asc';
        
        // Limpar outras ordenações
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            delete th.dataset.sort;
        });
        
        // Definir nova ordenação
        header.dataset.sort = isAsc ? 'asc' : 'desc';
        header.classList.add(isAsc ? 'sort-asc' : 'sort-desc');
        
        // Ordenar linhas
        rows.sort((a, b) => {
            const aValue = a.cells[column].textContent.trim();
            const bValue = b.cells[column].textContent.trim();
            
            if (isNaN(aValue)) {
                return isAsc ? 
                    aValue.localeCompare(bValue) : 
                    bValue.localeCompare(aValue);
            } else {
                return isAsc ? 
                    parseFloat(aValue) - parseFloat(bValue) : 
                    parseFloat(bValue) - parseFloat(aValue);
            }
        });
        
        // Reorganizar DOM
        rows.forEach(row => tbody.appendChild(row));
    }
    
    toggleSelectAll(checkbox) {
        const table = checkbox.closest('table');
        const checkboxes = table.querySelectorAll('tbody input[type="checkbox"]');
        
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
    }
    
    /**
     * ========================================
     * MELHORIAS EM FORMULÁRIOS
     * ========================================
     */
    setupForms() {
        // Auto-save em formulários
        document.querySelectorAll('[data-autosave]').forEach(form => {
            this.setupAutosave(form);
        });
        
        // Validação em tempo real
        document.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
            
            // Melhorar visual do focus
            input.addEventListener('focus', () => {
                input.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.classList.remove('focused');
            });
        });
        
        // Submit com loading
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    this.setLoadingState(submitBtn, true);
                }
            });
        });
    }
    
    setupAutosave(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        const saveKey = `autosave-${form.id || 'form'}`;
        
        // Carregar dados salvos
        const saved = localStorage.getItem(saveKey);
        if (saved) {
            const data = JSON.parse(saved);
            inputs.forEach(input => {
                if (data[input.name]) {
                    input.value = data[input.name];
                }
            });
        }
        
        // Salvar em mudanças
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                const data = {};
                inputs.forEach(inp => {
                    data[inp.name] = inp.value;
                });
                localStorage.setItem(saveKey, JSON.stringify(data));
            });
        });
        
        // Limpar ao submeter
        form.addEventListener('submit', () => {
            localStorage.removeItem(saveKey);
        });
    }
    
    validateField(field) {
        const value = field.value.trim();
        const rules = field.dataset.validate?.split('|') || [];
        let isValid = true;
        let message = '';
        
        rules.forEach(rule => {
            if (!isValid) return;
            
            switch (rule) {
                case 'required':
                    if (!value) {
                        isValid = false;
                        message = 'Este campo é obrigatório';
                    }
                    break;
                case 'email':
                    if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        isValid = false;
                        message = 'Email inválido';
                    }
                    break;
                case 'numeric':
                    if (value && isNaN(value)) {
                        isValid = false;
                        message = 'Deve ser um número';
                    }
                    break;
            }
        });
        
        this.showFieldValidation(field, isValid, message);
    }
    
    showFieldValidation(field, isValid, message) {
        const group = field.closest('.form-group');
        const existing = group.querySelector('.field-error');
        
        if (existing) {
            existing.remove();
        }
        
        field.classList.remove('invalid', 'valid');
        
        if (!isValid) {
            field.classList.add('invalid');
            const error = document.createElement('div');
            error.className = 'field-error';
            error.textContent = message;
            error.style.color = 'var(--danger)';
            error.style.fontSize = '0.8125rem';
            error.style.marginTop = '0.25rem';
            group.appendChild(error);
        } else if (field.value.trim()) {
            field.classList.add('valid');
        }
    }
    
    /**
     * ========================================
     * ATUALIZAÇÕES EM TEMPO REAL
     * ========================================
     */
    setupRealTimeUpdates() {
        // Atualizar estatísticas do dashboard
        if (window.location.pathname.includes('/dashboard/')) {
            this.startStatsUpdates();
        }
        
        // Atualizar notificações
        this.startNotificationUpdates();
    }
    
    startStatsUpdates() {
        this.updateStats();
        
        // Atualizar a cada 30 segundos
        setInterval(() => {
            this.updateStats();
        }, 30000);
    }
    
    async updateStats() {
        try {
            const response = await fetch('/administracao/api/stats.php');
            if (!response.ok) return;
            
            const data = await response.json();
            
            // Atualizar valores com animação
            document.querySelectorAll('[data-stat]').forEach(element => {
                const statType = element.dataset.stat;
                if (data[statType] !== undefined) {
                    this.animateValue(element, parseFloat(element.textContent.replace(/[^\d.-]/g, '')), data[statType]);
                }
            });
            
        } catch (error) {
            console.warn('Erro ao atualizar estatísticas:', error);
        }
    }
    
    startNotificationUpdates() {
        this.checkNotifications();
        
        // Verificar a cada 2 minutos
        setInterval(() => {
            this.checkNotifications();
        }, 120000);
    }
    
    async checkNotifications() {
        try {
            const response = await fetch('/administracao/api/notifications.php');
            if (!response.ok) return;
            
            const data = await response.json();
            
            // Atualizar badges no menu
            this.updateNavBadges(data);
            
            // Mostrar notificações importantes
            if (data.urgent && data.urgent.length > 0) {
                data.urgent.forEach(notification => {
                    this.showToast(notification.title, notification.message, 'warning', 0);
                });
            }
            
        } catch (error) {
            console.warn('Erro ao verificar notificações:', error);
        }
    }
    
    updateNavBadges(data) {
        // Atualizar badge de saques pendentes
        const saquesLink = document.querySelector('a[href*="saques"]');
        if (saquesLink && data.saquesPendentes) {
            let badge = saquesLink.querySelector('.nav-badge');
            if (data.saquesPendentes > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'nav-badge';
                    saquesLink.appendChild(badge);
                }
                badge.textContent = data.saquesPendentes;
            } else if (badge) {
                badge.remove();
            }
        }
    }
    
    /**
     * ========================================
     * ATALHOS DE TECLADO
     * ========================================
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K para busca
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.openQuickSearch();
            }
            
            // ESC para fechar modais
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal-overlay.show');
                if (openModal) {
                    this.closeModal(openModal.id);
                }
            }
            
            // Ctrl/Cmd + S para salvar formulários
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const form = document.querySelector('form');
                if (form) {
                    e.preventDefault();
                    form.requestSubmit();
                }
            }
        });
    }
    
    openQuickSearch() {
        // Implementar busca rápida (modal ou dropdown)
        console.log('Quick search activated');
    }
    
    /**
     * ========================================
     * UTILITÁRIOS
     * ========================================
     */
    animateValue(element, start, end, duration = 1000) {
        const range = end - start;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = start + (range * easeOut);
            
            // Formatear valor baseado no tipo
            if (element.textContent.includes('R$')) {
                element.textContent = `R$ ${current.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            } else {
                element.textContent = Math.round(current).toLocaleString('pt-BR');
            }
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }
    
    setLoadingState(element, loading) {
        if (loading) {
            element.dataset.originalText = element.textContent;
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
            element.disabled = true;
        } else {
            element.textContent = element.dataset.originalText || 'Salvar';
            element.disabled = false;
        }
    }
    
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
    
    formatDate(date) {
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }
    
    /**
     * ========================================
     * MÉTODOS PÚBLICOS PARA USO EXTERNO
     * ========================================
     */
    
    // Mostrar confirmação antes de ações críticas
    confirm(title, message, callback) {
        const modalId = this.createModal({
            title: title,
            content: `<p>${message}</p>`,
            buttons: [
                {
                    text: 'Cancelar',
                    type: 'secondary',
                    onclick: `finverAdmin.closeModal('${modalId}')`
                },
                {
                    text: 'Confirmar',
                    type: 'danger',
                    onclick: `finverAdmin.closeModal('${modalId}'); (${callback.toString()})()`
                }
            ]
        });
        
        this.openModal(modalId);
    }
    
    // Loading global
    showLoading() {
        if (!document.querySelector('.loading-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }
        
        document.querySelector('.loading-overlay').classList.add('show');
    }
    
    hideLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.classList.remove('show');
        }
    }
    
    // Refresh da página com loading
    refresh() {
        this.showLoading();
        window.location.reload();
    }
    
    // Navegar com loading
    navigate(url) {
        this.showLoading();
        window.location.href = url;
    }
}

/**
 * ========================================
 * FUNÇÕES AUXILIARES GLOBAIS
 * ========================================
 */

// Instanciar quando DOM estiver pronto
let finverAdmin;
document.addEventListener('DOMContentLoaded', () => {
    finverAdmin = new FinverAdmin();
    
    // Tornar disponível globalmente
    window.finverAdmin = finverAdmin;
});

// Função para deletar com confirmação
function deleteWithConfirm(url, message = 'Tem certeza que deseja excluir este item?') {
    finverAdmin.confirm(
        'Confirmar Exclusão',
        message,
        () => {
            finverAdmin.showLoading();
            window.location.href = url;
        }
    );
}

// Função para aprovar/rejeitar saques
function processWithdrawal(id, action) {
    const messages = {
        approve: 'Tem certeza que deseja aprovar este saque?',
        reject: 'Tem certeza que deseja rejeitar este saque?'
    };
    
    finverAdmin.confirm(
        'Confirmar Ação',
        messages[action],
        () => {
            finverAdmin.showLoading();
            
            fetch(`/administracao/api/process-withdrawal.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id, action })
            })
            .then(response => response.json())
            .then(data => {
                finverAdmin.hideLoading();
                
                if (data.success) {
                    finverAdmin.showToast('Sucesso', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    finverAdmin.showToast('Erro', data.message, 'danger');
                }
            })
            .catch(error => {
                finverAdmin.hideLoading();
                finverAdmin.showToast('Erro', 'Erro de conexão', 'danger');
            });
        }
    );
}

// Função para filtros de data
function setDateFilter(period) {
    const now = new Date();
    let startDate, endDate = now.toISOString().split('T')[0];
    
    switch (period) {
        case 'today':
            startDate = endDate;
            break;
        case 'week':
            startDate = new Date(now.setDate(now.getDate() - 7)).toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(now.setMonth(now.getMonth() - 1)).toISOString().split('T')[0];
            break;
        case 'year':
            startDate = new Date(now.setFullYear(now.getFullYear() - 1)).toISOString().split('T')[0];
            break;
    }
    
    if (startDate) {
        const startInput = document.querySelector('input[name="start_date"]');
        const endInput = document.querySelector('input[name="end_date"]');
        
        if (startInput) startInput.value = startDate;
        if (endInput) endInput.value = endDate;
        
        // Submeter formulário automaticamente
        const form = startInput?.closest('form');
        if (form) form.submit();
    }
}

// Função para exportar dados
function exportData(format = 'excel') {
    finverAdmin.showLoading();
    
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    
    // Criar link invisível para download
    const link = document.createElement('a');
    link.href = url.toString();
    link.download = `export_${Date.now()}.${format === 'excel' ? 'xlsx' : 'csv'}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        finverAdmin.hideLoading();
        finverAdmin.showToast('Sucesso', 'Download iniciado!', 'success');
    }, 1000);
}

/**
 * ========================================
 * EXTENSÕES JQUERY (OPCIONAL)
 * ========================================
 */
if (typeof $ !== 'undefined') {
    // Extensões jQuery se disponível
    $.fn.toast = function(options) {
        return finverAdmin.showToast(
            options.title || 'Notificação',
            options.message || '',
            options.type || 'info',
            options.duration || 5000
        );
    };
    
    $.fn.confirm = function(options) {
        return finverAdmin.confirm(
            options.title || 'Confirmar',
            options.message || 'Tem certeza?',
            options.callback || (() => {})
        );
    };
}

console.log('📱 Admin JavaScript carregado - Versão 2.0');