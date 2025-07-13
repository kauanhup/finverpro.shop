
let captchaAnswer = 0;

// Generate captcha
function generateCaptcha() {
    const num1 = Math.floor(Math.random() * 10) + 1;
    const num2 = Math.floor(Math.random() * 10) + 1;
    const operators = ['+', '-', '*'];
    const operator = operators[Math.floor(Math.random() * operators.length)];
    
    let question = '';
    switch(operator) {
        case '+':
            captchaAnswer = num1 + num2;
            question = `${num1} + ${num2} = ?`;
            break;
        case '-':
            captchaAnswer = num1 - num2;
            question = `${num1} - ${num2} = ?`;
            break;
        case '*':
            captchaAnswer = num1 * num2;
            question = `${num1} × ${num2} = ?`;
            break;
    }
    
    document.getElementById('captcha-question').textContent = question;
    document.querySelector('input[name="captcha"]').value = '';
    
    // Clear any previous captcha errors
    const captchaError = document.getElementById('error-captcha');
    if (captchaError) {
        captchaError.textContent = '';
    }
    const captchaInput = document.querySelector('input[name="captcha"]');
    if (captchaInput) {
        captchaInput.classList.remove('error');
    }
}

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.querySelector('input[name="password"]');
    const passwordIcon = document.getElementById('password-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        passwordIcon.className = 'fas fa-eye';
    }
}

// Form validation
function validateForm() {
    let isValid = true;
    
    // Clear previous errors
    document.querySelectorAll('.error-message').forEach(error => {
        error.textContent = '';
    });
    document.querySelectorAll('.form-input').forEach(input => {
        input.classList.remove('error');
    });

    const emailOrPhone = document.querySelector('input[name="email_or_phone"]').value.trim();
    const password = document.querySelector('input[name="password"]').value;
    const captchaInput = parseInt(document.querySelector('input[name="captcha"]').value);

    // Validate email or phone
    if (!emailOrPhone) {
        showFieldError('email_or_phone', 'Campo obrigatório');
        isValid = false;
    } else {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        
        if (!emailRegex.test(emailOrPhone) && !phoneRegex.test(emailOrPhone.replace(/\D/g, ''))) {
            showFieldError('email_or_phone', 'Email ou telefone inválido');
            isValid = false;
        }
    }

    // Validate password
    if (!password) {
        showFieldError('password', 'Campo obrigatório');
        isValid = false;
    }

    // Validate captcha
    if (isNaN(captchaInput) || captchaInput !== captchaAnswer) {
        showFieldError('captcha', 'Resposta incorreta');
        isValid = false;
    }

    return isValid;
}

function showFieldError(fieldName, message) {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    const errorElement = document.getElementById(`error-${fieldName}`);
    
    if (input) input.classList.add('error');
    if (errorElement) errorElement.textContent = message;
}

// Toast notification system
function showToast(message, type = 'success', duration = 4000) {
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
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
}

// Input formatting for phone/email
function setupInputFormatting() {
    const emailPhoneInput = document.querySelector('input[name="email_or_phone"]');
    if (emailPhoneInput) {
        emailPhoneInput.addEventListener('input', function(e) {
            const value = e.target.value;
            if (/^[\+\d\s\-\(\)]+$/.test(value)) {
                e.target.placeholder = 'Ex: +55 11 99999-9999';
            } else {
                e.target.placeholder = 'Ex: usuario@email.com';
            }
        });
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    generateCaptcha();
    setupInputFormatting();
    
    setTimeout(() => {
        showToast('Bem-vindo ao FinverPro!', 'info');
    }, 1000);
});

// Handle Enter key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
        event.preventDefault();
        const form = document.getElementById('login-form');
        if (form) {
            form.dispatchEvent(new Event('submit'));
        }
    }
});
