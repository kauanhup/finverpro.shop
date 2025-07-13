
// Sistema de Tradução
const translations = {
    pt: {
        dashboard: "Dashboard",
        inicio: "Início",
        perfil: "Perfil",
        financeiro: "Financeiro",
        deposito: "Depósito",
        saque: "Saque",
        extrato: "Extrato",
        investimentos: "Investimentos",
        produtos: "Produtos",
        meus_investimentos: "Meus Investimentos",
        afiliacao: "Afiliação",
        indicacoes: "Indicações",
        comissoes: "Comissões",
        link_afiliado: "Link Afiliado",
        bonus: "Bônus",
        roleta: "Roleta da Sorte",
        checklist: "Checklist",
        tarefas: "Tarefas",
        codigo_bonus: "Código Bônus",
        estatisticas: "Estatísticas",
        relatorios: "Relatórios",
        modo_escuro: "Modo Escuro",
        configuracoes: "Configurações",
        sair: "Sair",
        bem_vindo: "Bem-vindo de volta",
        online: "Online",
        ultimo_acesso: "Último acesso há",
        saldo_total: "Saldo Total",
        depositar: "Depositar",
        adicione_saldo: "Adicione saldo à sua conta",
        sacar: "Sacar",
        retire_seus_lucros: "Retire seus lucros",
        investir: "Investir",
        explore_produtos: "Explore nossos produtos",
        indicar: "Indicar",
        ganhe_comissoes: "Ganhe comissões",
        investimentos_ativos: "Investimentos Ativos",
        indicados: "Indicados",
        total_investido: "Total Investido",
        atividade_recente: "Atividade Recente",
        ver_tudo: "Ver tudo",
        deposito_aprovado: "Depósito aprovado",
        novo_investimento: "Novo investimento"
    },
    en: {
        dashboard: "Dashboard",
        inicio: "Home",
        perfil: "Profile",
        financeiro: "Financial",
        deposito: "Deposit",
        saque: "Withdrawal",
        extrato: "Statement",
        investimentos: "Investments",
        produtos: "Products",
        meus_investimentos: "My Investments",
        afiliacao: "Affiliation",
        indicacoes: "Referrals",
        comissoes: "Commissions",
        link_afiliado: "Affiliate Link",
        bonus: "Bonus",
        roleta: "Lucky Wheel",
        checklist: "Checklist",
        tarefas: "Tasks",
        codigo_bonus: "Bonus Code",
        estatisticas: "Statistics",
        relatorios: "Reports",
        modo_escuro: "Dark Mode",
        configuracoes: "Settings",
        sair: "Logout",
        bem_vindo: "Welcome back",
        online: "Online",
        ultimo_acesso: "Last access",
        saldo_total: "Total Balance",
        depositar: "Deposit",
        adicione_saldo: "Add balance to your account",
        sacar: "Withdraw",
        retire_seus_lucros: "Withdraw your profits",
        investir: "Invest",
        explore_produtos: "Explore our products",
        indicar: "Refer",
        ganhe_comissoes: "Earn commissions",
        investimentos_ativos: "Active Investments",
        indicados: "Referrals",
        total_investido: "Total Invested",
        atividade_recente: "Recent Activity",
        ver_tudo: "View all",
        deposito_aprovado: "Deposit approved",
        novo_investimento: "New investment"
    },
    es: {
        dashboard: "Panel",
        inicio: "Inicio",
        perfil: "Perfil",
        financeiro: "Financiero",
        deposito: "Depósito",
        saque: "Retiro",
        extrato: "Extracto",
        investimentos: "Inversiones",
        produtos: "Productos",
        meus_investimentos: "Mis Inversiones",
        afiliacao: "Afiliación",
        indicacoes: "Referencias",
        comissoes: "Comisiones",
        link_afiliado: "Enlace Afiliado",
        bonus: "Bono",
        roleta: "Ruleta de la Suerte",
        checklist: "Lista de Verificación",
        tarefas: "Tareas",
        codigo_bonus: "Código de Bono",
        estatisticas: "Estadísticas",
        relatorios: "Informes",
        modo_escuro: "Modo Oscuro",
        configuracoes: "Configuraciones",
        sair: "Salir",
        bem_vindo: "Bienvenido de vuelta",
        online: "En línea",
        ultimo_acesso: "Último acceso hace",
        saldo_total: "Saldo Total",
        depositar: "Depositar",
        adicione_saldo: "Agregar saldo a tu cuenta",
        sacar: "Retirar",
        retire_seus_lucros: "Retira tus ganancias",
        investir: "Invertir",
        explore_produtos: "Explora nuestros productos",
        indicar: "Referir",
        ganhe_comissoes: "Gana comisiones",
        investimentos_ativos: "Inversiones Activas",
        indicados: "Referidos",
        total_investido: "Total Invertido",
        atividade_recente: "Actividad Reciente",
        ver_tudo: "Ver todo",
        deposito_aprovado: "Depósito aprobado",
        novo_investimento: "Nueva inversión"
    }
};

let currentLanguage = localStorage.getItem('language') || 'pt';

function changeLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('language', lang);
    updatePageLanguage();
}

function updatePageLanguage() {
    const elements = document.querySelectorAll('[data-translate]');
    elements.forEach(element => {
        const key = element.getAttribute('data-translate');
        if (translations[currentLanguage] && translations[currentLanguage][key]) {
            element.textContent = translations[currentLanguage][key];
        }
    });
    
    // Atualizar o select de idioma
    const languageSelect = document.getElementById('language-select');
    if (languageSelect) {
        languageSelect.value = currentLanguage;
    }
}

// Inicializar tradução quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    updatePageLanguage();
});
