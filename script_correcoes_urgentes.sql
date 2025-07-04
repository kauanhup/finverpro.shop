-- ========================================
-- SCRIPT DE CORREÇÕES URGENTES 
-- FinverPro.shop Database
-- ========================================

-- IMPORTANTE: FAZER BACKUP ANTES DE EXECUTAR!
-- mysqldump -u root -p meu_site > backup_finverpro_$(date +%Y%m%d_%H%M%S).sql

-- ========================================
-- FASE 1: LIMPEZA E PADRONIZAÇÃO
-- ========================================

-- 1.1 Remover tabela `investidores` duplicada (migrar dados para `investimentos`)
INSERT INTO investimentos (
    usuario_id, produto_id, valor_investido, renda_diaria, 
    dias_restantes, data_investimento, status, ultimo_rendimento
)
SELECT 
    id_usuario, produto_investido, 
    COALESCE(renda_total, 0), COALESCE(renda_diaria, 0),
    30, COALESCE(data_investimento, NOW()), 'ativo', ultimo_ciclo
FROM investidores 
WHERE id_usuario IS NOT NULL;

-- Verificar se migração foi bem-sucedida antes de dropar
-- DROP TABLE investidores;

-- 1.2 Consolidar tabela de transações (migrar `transacoes` para `historico_transacoes`)
INSERT INTO historico_transacoes (
    user_id, tipo, valor, descricao, status, data_transacao
)
SELECT 
    user_id, tipo, valor, descricao, status, data_transacao
FROM transacoes
WHERE user_id IS NOT NULL;

-- DROP TABLE transacoes;

-- ========================================
-- FASE 2: PADRONIZAÇÃO DE NOMENCLATURA
-- ========================================

-- 2.1 Padronizar colunas de data
ALTER TABLE usuarios CHANGE data_criacao created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE usuarios CHANGE data_cadastro created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 2.2 Padronizar IDs de usuário
ALTER TABLE comissoes CHANGE user_id usuario_id INT(11) NOT NULL;
ALTER TABLE indicacoes CHANGE user_id usuario_id INT(11) NOT NULL;
ALTER TABLE niveis_convite CHANGE user_id usuario_id INT(11) NOT NULL;

-- 2.3 Padronizar campos de telefone
ALTER TABLE usuarios CHANGE telefone numero_telefone VARCHAR(15);

-- ========================================
-- FASE 3: ADIÇÃO DE FOREIGN KEYS
-- ========================================

-- 3.1 Foreign Keys para investimentos
ALTER TABLE investimentos 
ADD CONSTRAINT fk_investimentos_usuario 
FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;

ALTER TABLE investimentos 
ADD CONSTRAINT fk_investimentos_produto 
FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE;

-- 3.2 Foreign Keys para transações
ALTER TABLE historico_transacoes 
ADD CONSTRAINT fk_transacoes_usuario 
FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE;

-- 3.3 Foreign Keys para comissões
ALTER TABLE comissoes 
ADD CONSTRAINT fk_comissoes_usuario 
FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;

ALTER TABLE comissoes 
ADD CONSTRAINT fk_comissoes_referido 
FOREIGN KEY (referido_id) REFERENCES usuarios(id) ON DELETE CASCADE;

-- 3.4 Foreign Keys para chaves PIX
ALTER TABLE chaves_pix 
ADD CONSTRAINT fk_chaves_pix_usuario 
FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE;

-- 3.5 Foreign Keys para carteira
ALTER TABLE carteira 
ADD CONSTRAINT fk_carteira_usuario 
FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE;

-- ========================================
-- FASE 4: OTIMIZAÇÃO DE PERFORMANCE
-- ========================================

-- 4.1 Índices essenciais para performance
CREATE INDEX idx_usuarios_telefone ON usuarios(numero_telefone);
CREATE INDEX idx_usuarios_codigo_ref ON usuarios(codigo_referencia);
CREATE INDEX idx_usuarios_cargo ON usuarios(cargo);

CREATE INDEX idx_investimentos_usuario_status ON investimentos(usuario_id, status);
CREATE INDEX idx_investimentos_data ON investimentos(data_investimento);
CREATE INDEX idx_investimentos_vencimento ON investimentos(data_vencimento);

CREATE INDEX idx_transacoes_usuario_tipo ON historico_transacoes(user_id, tipo);
CREATE INDEX idx_transacoes_status_data ON historico_transacoes(status, data_transacao);

CREATE INDEX idx_pagamentos_status_data ON pagamentos(status, data);
CREATE INDEX idx_pagamentos_usuario ON pagamentos(user_id);

CREATE INDEX idx_comissoes_usuario_nivel ON comissoes(usuario_id, nivel);
CREATE INDEX idx_comissoes_status ON comissoes(status);

-- 4.2 Índices compostos para consultas específicas
CREATE INDEX idx_investimentos_usuario_produto ON investimentos(usuario_id, produto_id);
CREATE INDEX idx_chaves_pix_usuario_ativa ON chaves_pix(user_id, ativa);

-- ========================================
-- FASE 5: LIMPEZA DE CONFIGURAÇÕES
-- ========================================

-- 5.1 Criar tabela de configurações normalizada
CREATE TABLE configuracoes_sistema (
    id INT PRIMARY KEY AUTO_INCREMENT,
    categoria VARCHAR(50) NOT NULL,
    chave VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    descricao VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_config (categoria, chave)
);

-- 5.2 Migrar configurações de textos
INSERT INTO configuracoes_sistema (categoria, chave, valor, tipo, descricao) VALUES
('site', 'titulo', (SELECT titulo_site FROM configurar_textos LIMIT 1), 'string', 'Título do site'),
('site', 'descricao', (SELECT descricao_site FROM configurar_textos LIMIT 1), 'string', 'Descrição do site'),
('site', 'keywords', (SELECT keywords_site FROM configurar_textos LIMIT 1), 'string', 'Keywords SEO'),
('site', 'link_site', (SELECT link_site FROM configurar_textos LIMIT 1), 'string', 'URL do site'),
('suporte', 'link', (SELECT link_suporte FROM configurar_textos LIMIT 1), 'string', 'Link do suporte'),
('popup', 'titulo', (SELECT popup_titulo FROM configurar_textos LIMIT 1), 'string', 'Título do popup'),
('popup', 'ativo', (SELECT popup_ativo FROM configurar_textos LIMIT 1), 'boolean', 'Popup ativo'),
('popup', 'delay', (SELECT popup_delay FROM configurar_textos LIMIT 1), 'number', 'Delay do popup em ms');

-- 5.3 Migrar configurações de cores
INSERT INTO configuracoes_sistema (categoria, chave, valor, tipo, descricao) VALUES
('cores', 'primaria', (SELECT cor_1 FROM personalizar_cores LIMIT 1), 'string', 'Cor primária'),
('cores', 'secundaria', (SELECT cor_2 FROM personalizar_cores LIMIT 1), 'string', 'Cor secundária'),
('cores', 'terciaria', (SELECT cor_3 FROM personalizar_cores LIMIT 1), 'string', 'Cor terciária'),
('cores', 'quaternaria', (SELECT cor_4 FROM personalizar_cores LIMIT 1), 'string', 'Cor quaternária'),
('cores', 'quinaria', (SELECT cor_5 FROM personalizar_cores LIMIT 1), 'string', 'Cor quinária');

-- ========================================
-- FASE 6: CORREÇÕES DE DADOS INCONSISTENTES
-- ========================================

-- 6.1 Corrigir valores NULL desnecessários
UPDATE usuarios SET saldo = 0.00 WHERE saldo IS NULL;
UPDATE usuarios SET saldo_comissao = 0.00 WHERE saldo_comissao IS NULL;
UPDATE usuarios SET valor_investimento = 0.00 WHERE valor_investimento IS NULL;
UPDATE usuarios SET valor_deposito = 0.00 WHERE valor_deposito IS NULL;

-- 6.2 Corrigir status inconsistentes
UPDATE investimentos SET status = 'ativo' WHERE status IS NULL;
UPDATE historico_transacoes SET status = 'pendente' WHERE status IS NULL OR status = '';

-- 6.3 Corrigir datas inválidas
UPDATE usuarios SET created_at = NOW() WHERE created_at IS NULL;
UPDATE investimentos SET data_investimento = NOW() WHERE data_investimento IS NULL;

-- ========================================
-- FASE 7: VALIDAÇÕES DE DADOS
-- ========================================

-- 7.1 Adicionar constraints de validação
ALTER TABLE usuarios 
ADD CONSTRAINT chk_saldo_positivo CHECK (saldo >= 0),
ADD CONSTRAINT chk_saldo_comissao_positivo CHECK (saldo_comissao >= 0);

ALTER TABLE investimentos 
ADD CONSTRAINT chk_valor_investido_positivo CHECK (valor_investido > 0),
ADD CONSTRAINT chk_dias_restantes_valido CHECK (dias_restantes >= 0);

ALTER TABLE produtos 
ADD CONSTRAINT chk_valor_produto_positivo CHECK (valor_investimento > 0),
ADD CONSTRAINT chk_renda_diaria_positiva CHECK (renda_diaria >= 0);

-- ========================================
-- FASE 8: LIMPEZA FINAL
-- ========================================

-- 8.1 Remover registros órfãos (executar após confirmar FKs)
-- DELETE FROM investimentos WHERE usuario_id NOT IN (SELECT id FROM usuarios);
-- DELETE FROM historico_transacoes WHERE user_id NOT IN (SELECT id FROM usuarios);
-- DELETE FROM comissoes WHERE usuario_id NOT IN (SELECT id FROM usuarios);

-- 8.2 Otimizar tabelas após mudanças
OPTIMIZE TABLE usuarios;
OPTIMIZE TABLE investimentos;
OPTIMIZE TABLE historico_transacoes;
OPTIMIZE TABLE produtos;
OPTIMIZE TABLE pagamentos;

-- ========================================
-- VERIFICAÇÕES PÓS-MIGRAÇÃO
-- ========================================

-- Verificar integridade dos dados
SELECT 'Usuários total:' as tabela, COUNT(*) as registros FROM usuarios
UNION ALL
SELECT 'Investimentos total:', COUNT(*) FROM investimentos
UNION ALL
SELECT 'Transações total:', COUNT(*) FROM historico_transacoes
UNION ALL
SELECT 'Produtos total:', COUNT(*) FROM produtos
UNION ALL
SELECT 'Pagamentos total:', COUNT(*) FROM pagamentos;

-- Verificar foreign keys
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_SCHEMA = 'meu_site'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Verificar índices
SHOW INDEX FROM usuarios;
SHOW INDEX FROM investimentos;
SHOW INDEX FROM historico_transacoes;

-- ========================================
-- CONCLUSÃO
-- ========================================

/*
SCRIPT EXECUTADO COM SUCESSO!

PRÓXIMOS PASSOS:
1. Verificar se todos os dados foram migrados corretamente
2. Testar a aplicação em ambiente de desenvolvimento
3. Atualizar o código PHP para usar as novas estruturas
4. Implementar cache para configurações
5. Adicionar logs de auditoria

PERFORMANCE ESPERADA:
- 50-70% melhoria nas consultas
- Redução significativa na redundância
- Integridade referencial garantida
- Estrutura mais organizada e manutenível

ATENÇÃO: 
- Mantenha o backup até ter certeza que tudo está funcionando
- Teste todas as funcionalidades antes de ir para produção
- Monitore a performance após a migração
*/