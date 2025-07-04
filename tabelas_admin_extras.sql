-- ========================================
-- TABELAS EXTRAS PARA ADMIN COMPLETO
-- FinverPro.shop - Extensões do Admin
-- ========================================

USE meu_site;

-- ========================================
-- 1. LOGS DE AUDITORIA DO ADMIN
-- ========================================
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    acao VARCHAR(100) NOT NULL,
    tabela_afetada VARCHAR(50),
    registro_id INT,
    dados_anteriores JSON,
    dados_novos JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES usuarios(id),
    INDEX idx_admin_data (admin_id, created_at),
    INDEX idx_acao (acao),
    INDEX idx_tabela (tabela_afetada)
) ENGINE=InnoDB;

-- ========================================
-- 2. DASHBOARD WIDGETS (CONFIGURÁVEIS)
-- ========================================
CREATE TABLE dashboard_widgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    widget_type ENUM('estatistica', 'grafico', 'lista', 'alerta') NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    posicao_x INT DEFAULT 0,
    posicao_y INT DEFAULT 0,
    largura INT DEFAULT 6, -- Grid de 12 colunas
    altura INT DEFAULT 4,
    configuracao JSON,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES usuarios(id),
    INDEX idx_admin_ativo (admin_id, ativo)
) ENGINE=InnoDB;

-- ========================================
-- 3. TAREFAS AGENDADAS DO SISTEMA
-- ========================================
CREATE TABLE system_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    comando VARCHAR(500) NOT NULL,
    cron_expression VARCHAR(50) NOT NULL,
    ultima_execucao TIMESTAMP NULL,
    proxima_execucao TIMESTAMP NULL,
    status ENUM('ativo', 'inativo', 'erro') DEFAULT 'ativo',
    tentativas_erro INT DEFAULT 0,
    max_tentativas INT DEFAULT 3,
    log_saida TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status_proxima (status, proxima_execucao)
) ENGINE=InnoDB;

-- ========================================
-- 4. PERMISSÕES DO ADMIN (RBAC)
-- ========================================
CREATE TABLE admin_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) UNIQUE NOT NULL,
    descricao VARCHAR(255),
    categoria VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) UNIQUE NOT NULL,
    descricao VARCHAR(255),
    cor VARCHAR(7) DEFAULT '#6B7280',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Adicionar coluna de role nos usuários
ALTER TABLE usuarios ADD COLUMN admin_role_id INT DEFAULT NULL;
ALTER TABLE usuarios ADD FOREIGN KEY (admin_role_id) REFERENCES admin_roles(id);

-- ========================================
-- 5. NOTIFICAÇÕES DO ADMIN
-- ========================================
CREATE TABLE admin_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT DEFAULT NULL, -- NULL = para todos
    tipo ENUM('info', 'warning', 'error', 'success') NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    mensagem TEXT NOT NULL,
    acao_url VARCHAR(255),
    acao_texto VARCHAR(50),
    lida BOOLEAN DEFAULT FALSE,
    importante BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_admin_lida (admin_id, lida),
    INDEX idx_importante (importante),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ========================================
-- 6. BACKUP E MANUTENÇÃO
-- ========================================
CREATE TABLE system_backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('completo', 'incremental', 'configuracoes') NOT NULL,
    tamanho_mb DECIMAL(10,2),
    arquivo_path VARCHAR(500),
    admin_id INT,
    status ENUM('criando', 'concluido', 'erro') DEFAULT 'criando',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES usuarios(id),
    INDEX idx_tipo_data (tipo, created_at),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ========================================
-- 7. ESTATÍSTICAS CONSOLIDADAS (CACHE)
-- ========================================
CREATE TABLE dashboard_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stat_key VARCHAR(50) UNIQUE NOT NULL,
    stat_value DECIMAL(15,2),
    stat_data JSON,
    periodo ENUM('hoje', 'semana', 'mes', 'ano', 'total') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key_periodo (stat_key, periodo)
) ENGINE=InnoDB;

-- ========================================
-- INSERIR DADOS PADRÃO
-- ========================================

-- Permissões padrão
INSERT INTO admin_permissions (nome, descricao, categoria) VALUES
-- Usuários
('users.view', 'Visualizar usuários', 'usuarios'),
('users.create', 'Criar usuários', 'usuarios'),
('users.edit', 'Editar usuários', 'usuarios'),
('users.delete', 'Deletar usuários', 'usuarios'),

-- Financeiro
('finance.view', 'Visualizar dados financeiros', 'financeiro'),
('finance.deposits', 'Gerenciar depósitos', 'financeiro'),
('finance.withdrawals', 'Gerenciar saques', 'financeiro'),
('finance.commissions', 'Gerenciar comissões', 'financeiro'),

-- Investimentos
('investments.view', 'Visualizar investimentos', 'investimentos'),
('investments.manage', 'Gerenciar investimentos', 'investimentos'),
('products.view', 'Visualizar produtos', 'investimentos'),
('products.manage', 'Gerenciar produtos', 'investimentos'),

-- Configurações
('config.view', 'Visualizar configurações', 'configuracoes'),
('config.edit', 'Editar configurações', 'configuracoes'),
('config.system', 'Configurações do sistema', 'configuracoes'),

-- Admin
('admin.logs', 'Visualizar logs', 'admin'),
('admin.backups', 'Gerenciar backups', 'admin'),
('admin.permissions', 'Gerenciar permissões', 'admin'),
('admin.full', 'Acesso total (Super Admin)', 'admin');

-- Roles padrão
INSERT INTO admin_roles (nome, descricao, cor) VALUES
('super_admin', 'Super Administrador', '#DC2626'),
('admin', 'Administrador', '#059669'),
('moderador', 'Moderador', '#2563EB'),
('financeiro', 'Operador Financeiro', '#7C3AED'),
('suporte', 'Suporte', '#EA580C');

-- Atribuir todas as permissões ao Super Admin
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT 1, id FROM admin_permissions;

-- Permissões para Admin comum
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT 2, id FROM admin_permissions WHERE nome NOT IN ('admin.permissions', 'admin.full');

-- Permissões para Moderador
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT 3, id FROM admin_permissions WHERE categoria IN ('usuarios', 'investimentos');

-- Permissões para Financeiro
INSERT INTO admin_role_permissions (role_id, permission_id)
SELECT 4, id FROM admin_permissions WHERE categoria = 'financeiro';

-- Tarefas do sistema padrão
INSERT INTO system_tasks (nome, descricao, comando, cron_expression) VALUES
('Processar Rendimentos', 'Processa rendimentos diários dos investimentos', 'php /processar_rendimentos.php', '0 9 * * *'),
('Limpar Logs Antigos', 'Remove logs com mais de 30 dias', 'php /limpar_logs.php', '0 2 * * 0'),
('Backup Diário', 'Backup automático do banco de dados', 'php /backup_database.php', '0 3 * * *'),
('Calcular Estatísticas', 'Atualiza cache das estatísticas do dashboard', 'php /calcular_stats.php', '*/15 * * * *');

-- Widgets padrão para o dashboard
INSERT INTO dashboard_widgets (admin_id, widget_type, titulo, posicao_x, posicao_y, largura, altura, configuracao) VALUES
(2, 'estatistica', 'Total de Usuários', 0, 0, 3, 2, '{"query": "SELECT COUNT(*) FROM usuarios", "icon": "users", "color": "blue"}'),
(2, 'estatistica', 'Saldo Total', 3, 0, 3, 2, '{"query": "SELECT SUM(saldo_principal) FROM carteiras", "icon": "wallet", "color": "green", "format": "currency"}'),
(2, 'estatistica', 'Investimentos Ativos', 6, 0, 3, 2, '{"query": "SELECT COUNT(*) FROM investimentos WHERE status = \\"ativo\\"", "icon": "trending-up", "color": "purple"}'),
(2, 'estatistica', 'Saques Pendentes', 9, 0, 3, 2, '{"query": "SELECT COUNT(*) FROM saques WHERE status = \\"pendente\\"", "icon": "arrow-down", "color": "orange"}'),
(2, 'grafico', 'Depósitos vs Saques', 0, 2, 8, 4, '{"type": "line", "queries": {"deposits": "SELECT DATE(created_at) as date, SUM(valor) as value FROM transacoes WHERE tipo = \\"deposito\\" GROUP BY DATE(created_at)", "withdrawals": "SELECT DATE(created_at) as date, SUM(valor) as value FROM transacoes WHERE tipo = \\"saque\\" GROUP BY DATE(created_at)"}}'),
(2, 'lista', 'Últimos Usuários', 8, 2, 4, 4, '{"query": "SELECT nome, telefone, created_at FROM usuarios ORDER BY created_at DESC LIMIT 5", "columns": ["nome", "telefone", "data"]}');

-- Estatísticas iniciais
INSERT INTO dashboard_stats (stat_key, stat_value, periodo) VALUES
('usuarios_total', 0, 'total'),
('usuarios_hoje', 0, 'hoje'),
('depositos_total', 0, 'total'),
('depositos_hoje', 0, 'hoje'),
('saques_total', 0, 'total'),
('saques_hoje', 0, 'hoje'),
('investimentos_ativos', 0, 'total'),
('comissoes_pagas', 0, 'total');

-- ========================================
-- PROCEDURES PARA ATUALIZAR STATS
-- ========================================

DELIMITER $$
CREATE PROCEDURE AtualizarDashboardStats()
BEGIN
    -- Usuários
    UPDATE dashboard_stats SET stat_value = (SELECT COUNT(*) FROM usuarios) WHERE stat_key = 'usuarios_total';
    UPDATE dashboard_stats SET stat_value = (SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = CURDATE()) WHERE stat_key = 'usuarios_hoje';
    
    -- Depósitos
    UPDATE dashboard_stats SET stat_value = (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'deposito' AND status = 'concluido') WHERE stat_key = 'depositos_total';
    UPDATE dashboard_stats SET stat_value = (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'deposito' AND status = 'concluido' AND DATE(created_at) = CURDATE()) WHERE stat_key = 'depositos_hoje';
    
    -- Saques
    UPDATE dashboard_stats SET stat_value = (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'saque' AND status = 'concluido') WHERE stat_key = 'saques_total';
    UPDATE dashboard_stats SET stat_value = (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'saque' AND status = 'concluido' AND DATE(created_at) = CURDATE()) WHERE stat_key = 'saques_hoje';
    
    -- Investimentos
    UPDATE dashboard_stats SET stat_value = (SELECT COUNT(*) FROM investimentos WHERE status = 'ativo') WHERE stat_key = 'investimentos_ativos';
    
    -- Comissões
    UPDATE dashboard_stats SET stat_value = (SELECT COALESCE(SUM(valor_comissao), 0) FROM comissoes WHERE status = 'processado') WHERE stat_key = 'comissoes_pagas';
END$$
DELIMITER ;

-- Executar uma vez para popular
CALL AtualizarDashboardStats();

-- ========================================
-- TRIGGERS PARA LOG DE AUDITORIA
-- ========================================

-- Trigger para log de updates em configurações
DELIMITER $$
CREATE TRIGGER tr_audit_configuracoes_update
AFTER UPDATE ON configuracoes
FOR EACH ROW
BEGIN
    INSERT INTO admin_logs (admin_id, acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip_address)
    VALUES (
        @current_admin_id,
        'UPDATE',
        'configuracoes',
        NEW.id,
        JSON_OBJECT('categoria', OLD.categoria, 'chave', OLD.chave, 'valor', OLD.valor),
        JSON_OBJECT('categoria', NEW.categoria, 'chave', NEW.chave, 'valor', NEW.valor),
        @current_admin_ip
    );
END$$
DELIMITER ;

-- Trigger para log de updates em usuários (admin)
DELIMITER $$
CREATE TRIGGER tr_audit_usuarios_update
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    IF @current_admin_id IS NOT NULL THEN
        INSERT INTO admin_logs (admin_id, acao, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip_address)
        VALUES (
            @current_admin_id,
            'UPDATE',
            'usuarios',
            NEW.id,
            JSON_OBJECT('cargo', OLD.cargo, 'status', OLD.status),
            JSON_OBJECT('cargo', NEW.cargo, 'status', NEW.status),
            @current_admin_ip
        );
    END IF;
END$$
DELIMITER ;

-- ========================================
-- VIEWS ÚTEIS PARA O ADMIN
-- ========================================

-- View com estatísticas gerais
CREATE VIEW vw_admin_stats AS
SELECT 
    (SELECT COUNT(*) FROM usuarios) as total_usuarios,
    (SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) = CURDATE()) as usuarios_hoje,
    (SELECT COUNT(*) FROM usuarios WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as usuarios_semana,
    (SELECT COALESCE(SUM(saldo_principal + saldo_bonus + saldo_comissao), 0) FROM carteiras) as saldo_total_plataforma,
    (SELECT COUNT(*) FROM investimentos WHERE status = 'ativo') as investimentos_ativos,
    (SELECT COUNT(*) FROM saques WHERE status = 'pendente') as saques_pendentes,
    (SELECT COUNT(*) FROM pagamentos WHERE status = 'pendente') as depositos_pendentes,
    (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'deposito' AND status = 'concluido' AND DATE(created_at) = CURDATE()) as depositos_hoje,
    (SELECT COALESCE(SUM(valor), 0) FROM transacoes WHERE tipo = 'saque' AND status = 'concluido' AND DATE(created_at) = CURDATE()) as saques_hoje;

-- View com usuários mais ativos
CREATE VIEW vw_usuarios_ativos AS
SELECT 
    u.id,
    u.nome,
    u.telefone,
    u.created_at,
    c.saldo_principal + c.saldo_bonus + c.saldo_comissao as saldo_total,
    COUNT(i.id) as total_investimentos,
    COALESCE(SUM(i.valor_investido), 0) as total_investido,
    u.cargo,
    nv.nome as nivel_vip
FROM usuarios u
LEFT JOIN carteiras c ON u.id = c.usuario_id
LEFT JOIN investimentos i ON u.id = i.usuario_id
LEFT JOIN niveis_vip nv ON u.nivel_vip_id = nv.id
GROUP BY u.id
ORDER BY total_investido DESC;

/*
🎉 EXTENSÕES DO ADMIN CRIADAS!

✅ FUNCIONALIDADES ADICIONADAS:
- Sistema de logs e auditoria
- Dashboard configurável com widgets
- Permissões e roles (RBAC)
- Notificações do admin
- Tarefas agendadas
- Backup e manutenção
- Cache de estatísticas
- Views otimizadas

✅ PRONTO PARA:
- Admin moderno e escalável
- Controle total de permissões
- Monitoramento em tempo real
- Automação de tarefas
- Auditoria completa
*/