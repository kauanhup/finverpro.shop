-- FinverPro - Banco de Dados Reestruturado
-- Versão: 2.0
-- Data: 28/06/2025
-- Descrição: Reestruturação completa para melhor performance, organização e escalabilidade

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ===================================================
-- SEÇÃO 1: TABELAS PRINCIPAIS (USUÁRIOS E AUTENTICAÇÃO)
-- ===================================================

-- Tabela de usuários reestruturada
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `codigo_referencia` varchar(10) NOT NULL,
  `referenciado_por` int(11) DEFAULT NULL,
  `tipo_usuario` enum('usuario','admin','moderador') NOT NULL DEFAULT 'usuario',
  `status` enum('ativo','inativo','suspenso') NOT NULL DEFAULT 'ativo',
  `nivel_vip` varchar(10) DEFAULT 'V0',
  `foto_perfil` varchar(255) DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telefone` (`telefone`),
  UNIQUE KEY `codigo_referencia` (`codigo_referencia`),
  UNIQUE KEY `email` (`email`),
  KEY `referenciado_por` (`referenciado_por`),
  KEY `status` (`status`),
  KEY `tipo_usuario` (`tipo_usuario`),
  CONSTRAINT `fk_usuario_referenciador` FOREIGN KEY (`referenciado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================
-- SEÇÃO 2: SISTEMA FINANCEIRO UNIFICADO
-- ===================================================

-- Carteiras dos usuários (unificando saldo principal e comissões)
CREATE TABLE `carteiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `saldo_principal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_comissao` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_depositado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_sacado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_investido` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_carteira_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================
-- SEÇÃO 3: PRODUTOS E INVESTIMENTOS
-- ===================================================

-- Produtos de investimento reestruturados
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `categoria` varchar(100) DEFAULT 'robo-ia',
  `codigo_produto` varchar(20) NOT NULL,
  `imagem` varchar(255) DEFAULT 'default.jpg',
  `valor_minimo` decimal(15,2) NOT NULL,
  `valor_maximo` decimal(15,2) DEFAULT NULL,
  `rendimento_diario` decimal(8,4) NOT NULL COMMENT 'Percentual diário ou valor fixo',
  `tipo_rendimento` enum('percentual_diario','valor_fixo_diario','valor_final') NOT NULL DEFAULT 'percentual_diario',
  `duracao_dias` int(11) NOT NULL,
  `valor_final` decimal(15,2) DEFAULT NULL COMMENT 'Para tipo valor_final',
  `limite_vendas` int(11) DEFAULT NULL,
  `vendidos` int(11) NOT NULL DEFAULT 0,
  `comissao_nivel1` decimal(5,2) DEFAULT 10.00,
  `comissao_nivel2` decimal(5,2) DEFAULT 6.00,
  `comissao_nivel3` decimal(5,2) DEFAULT 1.00,
  `status` enum('ativo','inativo','arquivado','manutencao') NOT NULL DEFAULT 'ativo',
  `data_inicio_vendas` timestamp NULL DEFAULT NULL,
  `data_fim_vendas` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_produto` (`codigo_produto`),
  KEY `status` (`status`),
  KEY `categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Investimentos dos usuários
CREATE TABLE `investimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investido` decimal(15,2) NOT NULL,
  `rendimento_acumulado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dias_restantes` int(11) NOT NULL,
  `data_vencimento` date NOT NULL,
  `ultimo_rendimento` date DEFAULT NULL,
  `status` enum('ativo','concluido','cancelado','pausado') NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `produto_id` (`produto_id`),
  KEY `status` (`status`),
  KEY `data_vencimento` (`data_vencimento`),
  CONSTRAINT `fk_investimento_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_investimento_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEÇÃO 4: SISTEMA DE PAGAMENTOS
-- ===================================================

-- Depósitos e saques unificados
CREATE TABLE `operacoes_financeiras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('deposito','saque') NOT NULL,
  `metodo` enum('pix','ted','boleto','cartao') NOT NULL DEFAULT 'pix',
  `valor_solicitado` decimal(15,2) NOT NULL,
  `valor_taxa` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_liquido` decimal(15,2) NOT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `nome_titular` varchar(255) DEFAULT NULL,
  `documento_titular` varchar(20) DEFAULT NULL,
  `codigo_referencia` varchar(100) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `status` enum('pendente','processando','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
  `data_processamento` timestamp NULL DEFAULT NULL,
  `processado_por` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `metadados` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `tipo` (`tipo`),
  KEY `status` (`status`),
  KEY `codigo_referencia` (`codigo_referencia`),
  KEY `gateway` (`gateway`),
  CONSTRAINT `fk_operacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_operacao_processador` FOREIGN KEY (`processado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEÇÃO 5: SISTEMA DE AFILIAÇÃO
-- ===================================================

-- Rede de afiliação
CREATE TABLE `rede_afiliacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `patrocinador_id` int(11) NOT NULL,
  `nivel` int(11) NOT NULL DEFAULT 1,
  `total_indicacoes_diretas` int(11) NOT NULL DEFAULT 0,
  `total_volume_equipe` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_comissoes` decimal(15,2) NOT NULL DEFAULT 0.00,
  `nivel_vip_atual` varchar(10) DEFAULT 'V0',
  `data_ultima_qualificacao` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_patrocinador` (`usuario_id`, `patrocinador_id`),
  KEY `patrocinador_id` (`patrocinador_id`),
  KEY `nivel` (`nivel`),
  KEY `nivel_vip_atual` (`nivel_vip_atual`),
  CONSTRAINT `fk_rede_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rede_patrocinador` FOREIGN KEY (`patrocinador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Comissões detalhadas
CREATE TABLE `comissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `origem_usuario_id` int(11) NOT NULL,
  `investimento_id` int(11) DEFAULT NULL,
  `nivel_comissao` int(11) NOT NULL,
  `percentual_aplicado` decimal(5,2) NOT NULL,
  `valor_base` decimal(15,2) NOT NULL,
  `valor_comissao` decimal(15,2) NOT NULL,
  `tipo` enum('investimento','renovacao','bonus') NOT NULL DEFAULT 'investimento',
  `status` enum('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  `data_pagamento` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `origem_usuario_id` (`origem_usuario_id`),
  KEY `investimento_id` (`investimento_id`),
  KEY `nivel_comissao` (`nivel_comissao`),
  KEY `status` (`status`),
  CONSTRAINT `fk_comissao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comissao_origem` FOREIGN KEY (`origem_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comissao_investimento` FOREIGN KEY (`investimento_id`) REFERENCES `investimentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================================================
-- SEÇÃO 6: SISTEMA DE BÔNUS E PROMOÇÕES
-- ===================================================

-- Códigos de bônus
CREATE TABLE `bonus_codigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('valor_fixo','percentual','produto_gratis') NOT NULL DEFAULT 'valor_fixo',
  `valor` decimal(15,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `uso_maximo` int(11) DEFAULT 1,
  `uso_atual` int(11) NOT NULL DEFAULT 0,
  `uso_por_usuario` int(11) DEFAULT 1,
  `valor_minimo_deposito` decimal(15,2) DEFAULT 0.00,
  `apenas_primeiro_uso` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `data_inicio` timestamp NULL DEFAULT current_timestamp(),
  `data_expiracao` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `ativo` (`ativo`),
  KEY `data_expiracao` (`data_expiracao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Histórico de uso de bônus
CREATE TABLE `bonus_utilizados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `bonus_codigo_id` int(11) NOT NULL,
  `codigo_usado` varchar(50) NOT NULL,
  `valor_concedido` decimal(15,2) NOT NULL,
  `operacao_id` int(11) DEFAULT NULL COMMENT 'Referência para depósito relacionado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `bonus_codigo_id` (`bonus_codigo_id`),
  KEY `operacao_id` (`operacao_id`),
  CONSTRAINT `fk_bonus_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bonus_codigo` FOREIGN KEY (`bonus_codigo_id`) REFERENCES `bonus_codigos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bonus_operacao` FOREIGN KEY (`operacao_id`) REFERENCES `operacoes_financeiras` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEÇÃO 7: CONFIGURAÇÕES DO SISTEMA
-- ===================================================

-- Configurações gerais unificadas
CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(50) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` enum('string','integer','decimal','boolean','json','text') NOT NULL DEFAULT 'string',
  `descricao` text DEFAULT NULL,
  `editavel` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoria_chave` (`categoria`, `chave`),
  KEY `categoria` (`categoria`),
  KEY `editavel` (`editavel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações essenciais
INSERT INTO `configuracoes` (`categoria`, `chave`, `valor`, `tipo`, `descricao`) VALUES
('sistema', 'nome_site', 'Finver Pro', 'string', 'Nome do site'),
('sistema', 'url_site', 'https://finverpro.shop', 'string', 'URL principal do site'),
('sistema', 'email_contato', 'contato@finverpro.shop', 'string', 'Email de contato'),
('sistema', 'telefone_suporte', 'https://t.me/finverpro', 'string', 'Link do suporte'),
('financeiro', 'saque_valor_minimo', '37.00', 'decimal', 'Valor mínimo para saque'),
('financeiro', 'saque_taxa_percentual', '9.00', 'decimal', 'Taxa percentual sobre saques'),
('financeiro', 'saque_limite_diario', '1', 'integer', 'Limite de saques por dia'),
('afiliacao', 'comissao_nivel1', '10.00', 'decimal', 'Comissão nível 1 (%)'),
('afiliacao', 'comissao_nivel2', '6.00', 'decimal', 'Comissão nível 2 (%)'),
('afiliacao', 'comissao_nivel3', '1.00', 'decimal', 'Comissão nível 3 (%)'),
('cadastro', 'bonus_boas_vindas', '6.00', 'decimal', 'Bônus de cadastro'),
('cadastro', 'requer_convite', '0', 'boolean', 'Cadastro apenas por convite'),
('gateway', 'pixup_client_id', 'Fernanda2025_6643147463', 'string', 'Client ID PixUP'),
('gateway', 'pixup_ativo', '1', 'boolean', 'Gateway PixUP ativo');

-- ===================================================
-- SEÇÃO 8: AUDITORIA E LOGS
-- ===================================================

-- Log de ações administrativas
CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela_afetada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_anteriores` json DEFAULT NULL,
  `dados_novos` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `acao` (`acao`),
  KEY `tabela_afetada` (`tabela_afetada`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login para segurança
CREATE TABLE `tentativas_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telefone` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `sucesso` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `telefone` (`telefone`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEÇÃO 9: RECURSOS ADICIONAIS
-- ===================================================

-- PIX keys dos usuários
CREATE TABLE `chaves_pix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('cpf','cnpj','celular','email','chave_aleatoria') NOT NULL,
  `chave` varchar(255) NOT NULL,
  `nome_titular` varchar(255) NOT NULL,
  `apelido` varchar(100) DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 0,
  `verificada` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `ativa` (`ativa`),
  CONSTRAINT `fk_pix_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Sistema de gamificação (checklist diário)
CREATE TABLE `checklist_diario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `dia_consecutivo` int(11) NOT NULL DEFAULT 1,
  `ultimo_checkin` date DEFAULT NULL,
  `total_dias` int(11) NOT NULL DEFAULT 0,
  `valor_acumulado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_checklist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personalização visual
CREATE TABLE `personalizacao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` enum('cores','imagens','textos') NOT NULL,
  `elemento` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoria_elemento` (`categoria`, `elemento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


COMMIT;