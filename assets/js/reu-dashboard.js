
// Theme management
let currentTheme = 'light';

function toggleTheme() {
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    document.getElementById('theme-switch').classList.toggle('dark');
    
    // Update theme icon and text
    const themeToggle = document.querySelector('.theme-toggle');
    const icon = themeToggle.querySelector('i');
    const text = themeToggle.querySelector('span');
    
    if (currentTheme === 'dark') {
        icon.className = 'fas fa-sun';
        text.textContent = 'Modo Claro';
    } else {
        icon.className = 'fas fa-moon';
        text.textContent = 'Modo Escuro';
    }
    
    showToast(`Modo ${currentTheme === 'dark' ? 'escuro' : 'claro'} ativado`, 'success');
}

// Language management
const translations = {
    pt: {
        'welcome': 'Bem-vindo de volta',
        'total_balance': 'Patrimônio Total',
        'quick_actions': 'Ações Rápidas',
        'deposit': 'Depósito',
        'withdraw': 'Saque',
        'featured_investments': 'Investimentos em Destaque',
        'view_all': 'Ver todos',
        'services': 'Serviços',
        'dark_mode': 'Modo Escuro',
        'light_mode': 'Modo Claro',
        'settings': 'Configurações',
        'logout': 'Sair'
    },
    en: {
        'welcome': 'Welcome back',
        'total_balance': 'Total Balance',
        'quick_actions': 'Quick Actions',
        'deposit': 'Deposit',
        'withdraw': 'Withdraw',
        'featured_investments': 'Featured Investments',
        'view_all': 'View all',
        'services': 'Services',
        'dark_mode': 'Dark Mode',
        'light_mode': 'Light Mode',
        'settings': 'Settings',
        'logout': 'Logout'
    },
    es: {
        'welcome': 'Bienvenido de vuelta',
        'total_balance': 'Balance Total',
        'quick_actions': 'Acciones Rápidas',
        'deposit': 'Depósito',
        'withdraw': 'Retiro',
        'featured_investments': 'Inversiones Destacadas',
        'view_all': 'Ver todo',
        'services': 'Servicios',
        'dark_mode': 'Modo Oscuro',
        'light_mode': 'Modo Claro',
        'settings': 'Configuraciones',
        'logout': 'Salir'
    }
};

function toggleLanguageDropdown() {
    const dropdown = document.getElementById('language-dropdown');
    dropdown.classList.toggle('show');
}

function changeLanguage(lang, langName) {
    document.getElementById('current-language').textContent = langName;
    document.querySelectorAll('.language-option').forEach(option => {
        option.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Update page content
    updatePageLanguage(lang);
    
    // Close dropdown
    document.getElementById('language-dropdown').classList.remove('show');
    
    showToast(`Idioma alterado para ${langName}`, 'success');
}

function updatePageLanguage(lang) {
    const elements = document.querySelectorAll('[data-translate]');
    elements.forEach(element => {
        const key = element.getAttribute('data-translate');
        if (translations[lang] && translations[lang][key]) {
            element.textContent = translations[lang][key];
        }
    });
}

// Close language dropdown when clicking outside
document.addEventListener('click', function(event) {
    const languageSelector = document.querySelector('.language-selector');
    if (!languageSelector.contains(event.target)) {
        document.getElementById('language-dropdown').classList.remove('show');
    }
});

// Sidebar toggle for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger');
    
    if (window.innerWidth <= 1024 && !sidebar.contains(event.target) && !hamburger.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});

// Toast notification system
function showToast(message, type = 'success', duration = 4000) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}" style="color: var(--${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'accent'});"></i>
            <span style="flex: 1; font-size: 14px; font-weight: 500;">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0; font-size: 16px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    // Auto remove toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme') || 'light';
    if (savedTheme === 'dark') {
        toggleTheme();
    }
});

// Navigation items click handling
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (this.getAttribute('href') === '#') {
            e.preventDefault();
        }
        
        // Update active state
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        this.classList.add('active');
    });
});

// Investment cards hover effect
document.querySelectorAll('.investment-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-4px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Service cards hover effect
document.querySelectorAll('.service-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-4px)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Responsive handling
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 1024) {
        sidebar.classList.remove('open');
    }
});

// Welcome message on page load
window.addEventListener('load', function() {
    setTimeout(() => {
        showToast('Bem-vindo ao FinverPro Dashboard!', 'success');
    }, 1000);
});
