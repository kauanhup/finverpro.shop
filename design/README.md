# FinverPro - Designs do Sistema

Este diretório contém todos os designs HTML/CSS/JS criados para o sistema FinverPro, baseados no template fornecido e adaptados para as funcionalidades específicas da plataforma.

## 📁 Estrutura dos Designs

```
design/
├── dashboard/           # Dashboard principal
├── roleta/             # Roleta da sorte
├── investimentos/      # Produtos e investimentos
├── equipe/             # Indicações e equipe
├── perfil/             # Perfil do usuário
├── relatorios/         # Relatórios e estatísticas
└── assets/             # Recursos compartilhados
    ├── css/
    ├── js/
    └── images/
```

## 🎨 Design System

### Cores
- **Primária**: `#0F172A` (Slate 900)
- **Secundária**: `#1E293B` (Slate 800)
- **Accent**: `#3B82F6` (Blue 500)
- **Sucesso**: `#059669` (Emerald 600)
- **Aviso**: `#D97706` (Amber 600)
- **Erro**: `#DC2626` (Red 600)
- **Roxo**: `#8B5CF6` (Violet 500)

### Tipografia
- **Principal**: Inter (sistema)
- **Secundária**: Roboto (números e títulos)

### Breakpoints
- **Desktop**: > 1024px
- **Tablet**: ≤ 1024px
- **Mobile**: ≤ 768px

## 📱 Funcionalidades dos Designs

### 1. Dashboard (`/dashboard/`)
**Características:**
- Visão geral do patrimônio
- Cards de estatísticas animados
- Gráficos interativos
- Ações rápidas
- Menu lateral responsivo

**Elementos incluídos:**
- Saldo total e distribuição
- Últimos investimentos
- Indicações recentes
- Atalhos para principais funcionalidades

### 2. Roleta da Sorte (`/roleta/`)
**Características:**
- Roleta animada em CSS/JS
- Sistema de premiação
- Histórico de prêmios
- Moedas/pontos do usuário
- Animações fluidas

**Elementos incluídos:**
- Roleta visual interativa
- Botão de girar com animações
- Lista de prêmios disponíveis
- Histórico de vitórias

### 3. Investimentos (`/investimentos/`)
**Características:**
- Catálogo de produtos
- Modal de investimento
- Meus investimentos ativos
- Calculadora de rendimento
- Status em tempo real

**Elementos incluídos:**
- Cards de produtos com informações detalhadas
- Sistema de filtros
- Modal para realizar investimentos
- Tabela de investimentos ativos

### 4. Equipe (`/equipe/`)
**Características:**
- Programa de indicações
- Código de referência
- Lista da rede
- Níveis de comissão
- Compartilhamento social

**Elementos incluídos:**
- Card do código de indicação
- Botões de compartilhamento
- Tabela de indicados
- Sistema de filtros
- Níveis de comissão (Bronze, Prata, Ouro, Diamante)

### 5. Perfil (`/perfil/`)
**Características:**
- Informações pessoais
- Configurações de segurança
- Preferências de notificação
- Histórico de atividades
- Upload de avatar

**Elementos incluídos:**
- Tabs de navegação
- Formulários de edição
- Toggle switches
- Sistema de notificações toast
- Log de atividades

### 6. Relatórios (`/relatorios/`)
**Características:**
- Gráficos interativos (Chart.js)
- Métricas de performance
- Filtros de período
- Exportação de relatórios
- Tabelas de transações

**Elementos incluídos:**
- Gráfico de evolução patrimonial
- Gráfico de distribuição de carteira
- Cards de performance
- Tabela de transações
- Seletores de período

## 🔧 Tecnologias Utilizadas

### Frontend
- **HTML5**: Estrutura semântica
- **CSS3**: Estilização moderna com CSS Variables
- **JavaScript ES6+**: Interatividade
- **Chart.js**: Gráficos e visualizações
- **Font Awesome**: Ícones
- **Google Fonts**: Tipografia (Inter + Roboto)

### Recursos
- **CSS Grid & Flexbox**: Layout responsivo
- **CSS Variables**: Sistema de cores dinâmico
- **CSS Animations**: Transições suaves
- **LocalStorage**: Persistência de dados (simulada)
- **Fetch API**: Simulação de requisições

## 📱 Responsividade

Todos os designs são totalmente responsivos e incluem:

### Desktop (> 1024px)
- Sidebar fixa
- Layout em grid completo
- Hover effects
- Tooltips avançados

### Tablet/Mobile (≤ 1024px)
- Sidebar colapsável
- Menu hamburger
- Layout em coluna única
- Touch-friendly buttons
- Gestos de swipe (onde aplicável)

## 🎯 Padrões de UX/UI

### Navegação
- Sidebar consistente em todas as páginas
- Breadcrumbs para orientação
- Estados ativos claramente identificados
- Transições suaves entre páginas

### Feedback Visual
- Loading states
- Animações de sucesso/erro
- Progress indicators
- Tooltips informativos

### Acessibilidade
- Contraste adequado (WCAG AA)
- Navegação por teclado
- Labels descritivos
- Foco visível

## 🚀 Como Implementar

### 1. Integração com Backend
```javascript
// Exemplo de integração API
const API_BASE = 'https://api.finverpro.com';

async function fetchUserData() {
    const response = await fetch(`${API_BASE}/user`);
    return response.json();
}
```

### 2. Autenticação
```javascript
// Sistema de autenticação
function checkAuth() {
    const token = localStorage.getItem('authToken');
    if (!token) {
        window.location.href = '/login';
    }
}
```

### 3. Dados Dinâmicos
Os designs incluem dados simulados que podem ser facilmente substituídos por dados reais da API:

```javascript
// Substituir dados simulados
const mockData = {
    balance: 52847.32,
    investments: [...],
    referrals: [...]
};

// Por dados da API
const realData = await fetchDashboardData();
```

## 🔧 Customização

### Cores
Edite as CSS Variables no `:root` para personalizar o tema:

```css
:root {
    --accent: #3B82F6;        /* Cor principal */
    --success: #059669;       /* Cor de sucesso */
    --warning: #D97706;       /* Cor de aviso */
    --danger: #DC2626;        /* Cor de erro */
}
```

### Tema Escuro
Todos os designs incluem suporte a tema escuro via atributo `data-theme="dark"`.

### Componentes
Os componentes são modulares e podem ser reutilizados:

```html
<!-- Card padrão -->
<div class="stat-card">
    <div class="stat-icon">
        <i class="fas fa-chart-line"></i>
    </div>
    <div class="stat-value">R$ 52.847</div>
    <div class="stat-label">Patrimônio Total</div>
</div>
```

## 📋 Checklist de Implementação

- [ ] Configurar API endpoints
- [ ] Implementar autenticação
- [ ] Conectar dados reais
- [ ] Configurar notificações
- [ ] Implementar upload de arquivos
- [ ] Configurar sistema de pagamentos
- [ ] Testes de responsividade
- [ ] Testes de acessibilidade
- [ ] Otimização de performance
- [ ] Deploy e configuração de domínio

## 🤝 Suporte

Para dúvidas sobre implementação ou customização dos designs:

1. Consulte a documentação inline nos arquivos HTML
2. Verifique os comentários no CSS para entender a estrutura
3. Teste todas as funcionalidades em diferentes dispositivos
4. Valide a acessibilidade com ferramentas apropriadas

## 📄 Licença

Estes designs foram criados especificamente para o projeto FinverPro e devem ser usados conforme acordado.

---

**Nota**: Os designs incluem dados simulados para demonstração. Na implementação real, substitua por dados dinâmicos da API do sistema.