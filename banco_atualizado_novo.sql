-- ========================================
-- FINVER PRO - BANCO DE DADOS COMPLETO
-- Estrutura Completa e Organizada (Versão 3.0)
-- Base: Script de Reestruturação Completa
-- ========================================

-- CONFIGURAÇÕES INICIAIS
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- TABELAS PRINCIPAIS DO SISTEMA
-- ========================================

-- 1. ADMINISTRADORES
DROP TABLE IF EXISTS `administrador`;
CREATE TABLE `administrador` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(100) NOT NULL,
    `senha` varchar(255) NOT NULL,
    `nome` varchar(100) DEFAULT NULL,
    `nivel` enum('super','admin','moderador') DEFAULT 'admin',
    `ativo` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    INDEX `idx_nivel` (`nivel`),
    INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USUÁRIOS PRINCIPAIS
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `telefone` varchar(15) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `nome` varchar(255) DEFAULT '',
    `senha` varchar(255) NOT NULL,
    `codigo_referencia` varchar(10) NOT NULL,
    `indicado_por` int(11) DEFAULT NULL,
    `cargo` enum('usuario','admin','moderador') DEFAULT 'usuario',
    `nivel_vip_id` int(11) DEFAULT 0,
    `status` enum('ativo','inativo','suspenso') DEFAULT 'ativo',
    `foto_perfil` varchar(255) DEFAULT NULL,
    `data_nascimento` date DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ultimo_login` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `telefone` (`telefone`),
    UNIQUE KEY `codigo_referencia` (`codigo_referencia`),
    INDEX `idx_telefone` (`telefone`),
    INDEX `idx_indicado_por` (`indicado_por`),
    INDEX `idx_cargo` (`cargo`),
    INDEX `idx_status` (`status`),
    INDEX `idx_nivel_vip` (`nivel_vip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CARTEIRAS (SALDOS CENTRALIZADOS)
DROP TABLE IF EXISTS `carteiras`;
CREATE TABLE `carteiras` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `saldo_principal` decimal(15,2) DEFAULT 0.00,
    `saldo_bonus` decimal(15,2) DEFAULT 0.00,
    `saldo_comissao` decimal(15,2) DEFAULT 0.00,
    `total_depositado` decimal(15,2) DEFAULT 0.00,
    `total_investido` decimal(15,2) DEFAULT 0.00,
    `total_sacado` decimal(15,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuario_id` (`usuario_id`),
    CONSTRAINT `fk_carteiras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_saldo_principal_positivo` CHECK (`saldo_principal` >= 0),
    CONSTRAINT `chk_saldo_bonus_positivo` CHECK (`saldo_bonus` >= 0),
    CONSTRAINT `chk_saldo_comissao_positivo` CHECK (`saldo_comissao` >= 0),
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_saldo_principal` (`saldo_principal`),
    INDEX `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PRODUTOS (ROBÔS DE INVESTIMENTO)
DROP TABLE IF EXISTS `produtos`;
CREATE TABLE `produtos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `titulo` varchar(255) NOT NULL,
    `descricao` text,
    `codigo_robo` varchar(20) NOT NULL,
    `foto` varchar(255) DEFAULT 'produto-default.jpg',
    `valor_minimo` decimal(10,2) NOT NULL,
    `valor_maximo` decimal(10,2) DEFAULT NULL,
    `tipo_rendimento` enum('diario','unico','progressivo') NOT NULL DEFAULT 'diario',
    `rendimento_diario` decimal(5,2) DEFAULT 0.00,
    `rendimento_total` decimal(10,2) DEFAULT 0.00,
    `duracao_dias` int(11) NOT NULL,
    `periodo_carencia` int(11) DEFAULT 0,
    `limite_vendas` int(11) DEFAULT NULL,
    `vendas_realizadas` int(11) DEFAULT 0,
    `limite_por_usuario` int(11) DEFAULT 1,
    `status` enum('ativo','inativo','esgotado') DEFAULT 'ativo',
    `destaque` tinyint(1) DEFAULT 0,
    `ordem_exibicao` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo_robo` (`codigo_robo`),
    CONSTRAINT `chk_valor_minimo_positivo` CHECK (`valor_minimo` > 0),
    CONSTRAINT `chk_rendimento_diario_valido` CHECK (`rendimento_diario` >= 0 AND `rendimento_diario` <= 100),
    CONSTRAINT `chk_duracao_valida` CHECK (`duracao_dias` > 0),
    INDEX `idx_status` (`status`),
    INDEX `idx_tipo_rendimento` (`tipo_rendimento`),
    INDEX `idx_destaque` (`destaque`),
    INDEX `idx_ordem` (`ordem_exibicao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. INVESTIMENTOS
DROP TABLE IF EXISTS `investimentos`;
CREATE TABLE `investimentos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `produto_id` int(11) NOT NULL,
    `valor_investido` decimal(15,2) NOT NULL,
    `valor_rendimento_diario` decimal(15,2) DEFAULT 0.00,
    `valor_total_rendido` decimal(15,2) DEFAULT 0.00,
    `valor_a_receber` decimal(15,2) DEFAULT 0.00,
    `data_investimento` timestamp DEFAULT CURRENT_TIMESTAMP,
    `data_vencimento` date NOT NULL,
    `data_ultimo_rendimento` date DEFAULT NULL,
    `dias_restantes` int(11) NOT NULL,
    `status` enum('ativo','concluido','cancelado','pausado') DEFAULT 'ativo',
    `rendimentos_processados` int(11) DEFAULT 0,
    `proximo_rendimento` date DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_investimentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_investimentos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_valor_investido_positivo` CHECK (`valor_investido` > 0),
    CONSTRAINT `chk_dias_restantes_valido` CHECK (`dias_restantes` >= 0),
    INDEX `idx_usuario_status` (`usuario_id`, `status`),
    INDEX `idx_produto` (`produto_id`),
    INDEX `idx_vencimento` (`data_vencimento`),
    INDEX `idx_proximo_rendimento` (`proximo_rendimento`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TRANSAÇÕES (HISTÓRICO UNIFICADO)
DROP TABLE IF EXISTS `transacoes`;
CREATE TABLE `transacoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `tipo` enum('deposito','saque','investimento','rendimento','comissao','bonus','transferencia') NOT NULL,
    `subtipo` varchar(50) DEFAULT NULL,
    `categoria` varchar(50) DEFAULT NULL,
    `valor` decimal(15,2) NOT NULL,
    `taxa` decimal(15,2) DEFAULT 0.00,
    `valor_liquido` decimal(15,2) GENERATED ALWAYS AS (`valor` - `taxa`) STORED,
    `descricao` text,
    `referencia_externa` varchar(100) DEFAULT NULL,
    `referencia_interna` varchar(100) DEFAULT NULL,
    `status` enum('pendente','processando','concluido','cancelado','rejeitado') DEFAULT 'pendente',
    `data_processamento` timestamp NULL DEFAULT NULL,
    `observacoes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_transacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_valor_positivo` CHECK (`valor` > 0),
    CONSTRAINT `chk_taxa_valida` CHECK (`taxa` >= 0),
    INDEX `idx_usuario_tipo` (`usuario_id`, `tipo`),
    INDEX `idx_status_data` (`status`, `created_at`),
    INDEX `idx_tipo_data` (`tipo`, `created_at`),
    INDEX `idx_referencia_externa` (`referencia_externa`),
    INDEX `idx_processamento` (`data_processamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CHAVES PIX
DROP TABLE IF EXISTS `chaves_pix`;
CREATE TABLE `chaves_pix` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `tipo` enum('cpf','celular','email','chave_aleatoria') NOT NULL,
    `chave_pix` varchar(255) NOT NULL,
    `nome_titular` varchar(255) NOT NULL,
    `banco` varchar(100) DEFAULT NULL,
    `apelido` varchar(100) DEFAULT NULL,
    `ativa` tinyint(1) DEFAULT 0,
    `verificada` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_chaves_pix_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    INDEX `idx_usuario_ativa` (`usuario_id`, `ativa`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_verificada` (`verificada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. SAQUES
DROP TABLE IF EXISTS `saques`;
CREATE TABLE `saques` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `chave_pix_id` int(11) NOT NULL,
    `valor_bruto` decimal(15,2) NOT NULL,
    `taxa` decimal(15,2) DEFAULT 0.00,
    `valor_liquido` decimal(15,2) GENERATED ALWAYS AS (`valor_bruto` - `taxa`) STORED,
    `codigo_saque` varchar(50) NOT NULL,
    `status` enum('pendente','processando','aprovado','rejeitado','cancelado') DEFAULT 'pendente',
    `motivo_rejeicao` text DEFAULT NULL,
    `processado_por` int(11) DEFAULT NULL,
    `data_processamento` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo_saque` (`codigo_saque`),
    CONSTRAINT `fk_saques_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_saques_chave_pix` FOREIGN KEY (`chave_pix_id`) REFERENCES `chaves_pix` (`id`),
    CONSTRAINT `fk_saques_processado_por` FOREIGN KEY (`processado_por`) REFERENCES `usuarios` (`id`),
    CONSTRAINT `chk_valor_bruto_positivo` CHECK (`valor_bruto` > 0),
    INDEX `idx_usuario_status` (`usuario_id`, `status`),
    INDEX `idx_status_data` (`status`, `created_at`),
    INDEX `idx_codigo_saque` (`codigo_saque`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SISTEMA DE AFILIADOS E COMISSÕES
-- ========================================

-- 9. INDICAÇÕES
DROP TABLE IF EXISTS `indicacoes`;
CREATE TABLE `indicacoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `indicador_id` int(11) NOT NULL,
    `indicado_id` int(11) NOT NULL,
    `nivel` int(11) NOT NULL DEFAULT 1,
    `ativa` tinyint(1) DEFAULT 1,
    `bonus_pago` decimal(10,2) DEFAULT 0.00,
    `data_indicacao` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_indicacao` (`indicador_id`, `indicado_id`),
    CONSTRAINT `fk_indicacoes_indicador` FOREIGN KEY (`indicador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_indicacoes_indicado` FOREIGN KEY (`indicado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    INDEX `idx_indicador` (`indicador_id`),
    INDEX `idx_indicado` (`indicado_id`),
    INDEX `idx_nivel` (`nivel`),
    INDEX `idx_ativa` (`ativa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. COMISSÕES
DROP TABLE IF EXISTS `comissoes`;
CREATE TABLE `comissoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `referido_id` int(11) NOT NULL,
    `investimento_id` int(11) DEFAULT NULL,
    `valor_investimento` decimal(15,2) NOT NULL,
    `percentual_comissao` decimal(5,2) NOT NULL,
    `valor_comissao` decimal(15,2) NOT NULL,
    `nivel` int(11) NOT NULL,
    `status` enum('pendente','processado','cancelado') DEFAULT 'pendente',
    `data_processamento` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_comissoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comissoes_referido` FOREIGN KEY (`referido_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comissoes_investimento` FOREIGN KEY (`investimento_id`) REFERENCES `investimentos` (`id`) ON DELETE SET NULL,
    INDEX `idx_usuario_status` (`usuario_id`, `status`),
    INDEX `idx_referido` (`referido_id`),
    INDEX `idx_nivel` (`nivel`),
    INDEX `idx_investimento` (`investimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. CONFIGURAÇÃO DE COMISSÕES
DROP TABLE IF EXISTS `configuracao_comissoes`;
CREATE TABLE `configuracao_comissoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nivel` int(11) NOT NULL,
    `percentual` decimal(5,2) NOT NULL,
    `descricao` varchar(255) DEFAULT NULL,
    `ativo` tinyint(1) DEFAULT 1,
    `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `nivel` (`nivel`),
    INDEX `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SISTEMA DE PAGAMENTOS
-- ========================================

-- 12. GATEWAYS DE PAGAMENTO
DROP TABLE IF EXISTS `gateways`;
CREATE TABLE `gateways` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nome` varchar(50) NOT NULL,
    `codigo` varchar(20) NOT NULL,
    `client_id` varchar(500) DEFAULT NULL,
    `client_secret` varchar(1000) DEFAULT NULL,
    `webhook_url` varchar(500) DEFAULT NULL,
    `endpoint_api` varchar(500) DEFAULT NULL,
    `ativo` tinyint(1) DEFAULT 0,
    `ambiente` enum('sandbox','producao') DEFAULT 'sandbox',
    `taxa_percentual` decimal(5,2) DEFAULT 0.00,
    `taxa_fixa` decimal(10,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo` (`codigo`),
    INDEX `idx_ativo` (`ativo`),
    INDEX `idx_ambiente` (`ambiente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. PAGAMENTOS
DROP TABLE IF EXISTS `pagamentos`;
CREATE TABLE `pagamentos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) DEFAULT NULL,
    `gateway_id` int(11) NOT NULL,
    `valor` decimal(15,2) NOT NULL,
    `moeda` varchar(3) DEFAULT 'BRL',
    `codigo_referencia` varchar(100) NOT NULL,
    `id_externo` varchar(100) DEFAULT NULL,
    `status` enum('pendente','processando','aprovado','rejeitado','cancelado','expirado') NOT NULL,
    `nome_pagador` varchar(255) DEFAULT NULL,
    `email_pagador` varchar(255) DEFAULT NULL,
    `telefone_pagador` varchar(20) DEFAULT NULL,
    `documento_pagador` varchar(20) DEFAULT NULL,
    `metadados` json DEFAULT NULL,
    `callback_data` json DEFAULT NULL,
    `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
    `data_aprovacao` timestamp NULL DEFAULT NULL,
    `data_expiracao` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo_referencia` (`codigo_referencia`),
    CONSTRAINT `fk_pagamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pagamentos_gateway` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`),
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_gateway` (`gateway_id`),
    INDEX `idx_status_data` (`status`, `data_criacao`),
    INDEX `idx_id_externo` (`id_externo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- GAMIFICAÇÃO E BÔNUS
-- ========================================

-- 14. NÍVEIS VIP
DROP TABLE IF EXISTS `niveis_vip`;
CREATE TABLE `niveis_vip` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `codigo` varchar(10) NOT NULL,
    `nome` varchar(50) NOT NULL,
    `requisito_investimento` decimal(15,2) DEFAULT 0.00,
    `requisito_indicacoes` int(11) DEFAULT 0,
    `bonus_rendimento` decimal(5,2) DEFAULT 0.00,
    `comissao_extra` decimal(5,2) DEFAULT 0.00,
    `limite_saque_diario` decimal(15,2) DEFAULT NULL,
    `cor_badge` varchar(7) DEFAULT '#6B7280',
    `icone` varchar(50) DEFAULT 'fa-user',
    `emoji` varchar(10) DEFAULT '👤',
    `ordem` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo` (`codigo`),
    INDEX `idx_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. BÔNUS/CUPONS
DROP TABLE IF EXISTS `bonus_codigos`;
CREATE TABLE `bonus_codigos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `codigo` varchar(20) NOT NULL,
    `valor` decimal(10,2) NOT NULL,
    `descricao` text DEFAULT NULL,
    `max_usos` int(11) DEFAULT 1,
    `usos_atuais` int(11) DEFAULT 0,
    `ativo` tinyint(1) DEFAULT 1,
    `data_criacao` timestamp DEFAULT CURRENT_TIMESTAMP,
    `data_expiracao` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `codigo` (`codigo`),
    INDEX `idx_ativo` (`ativo`),
    INDEX `idx_expiracao` (`data_expiracao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. BÔNUS RESGATADOS
DROP TABLE IF EXISTS `bonus_resgatados`;
CREATE TABLE `bonus_resgatados` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `codigo` varchar(50) NOT NULL,
    `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
    `data_resgate` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_bonus_resgatados_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SISTEMA DE CONFIGURAÇÕES
-- ========================================

-- 17. CONFIGURAÇÕES GERAIS
DROP TABLE IF EXISTS `configuracoes`;
CREATE TABLE `configuracoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `categoria` varchar(50) NOT NULL,
    `chave` varchar(100) NOT NULL,
    `valor` text,
    `tipo` enum('string','number','boolean','json','email','url') DEFAULT 'string',
    `descricao` varchar(255) DEFAULT NULL,
    `publico` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_config` (`categoria`, `chave`),
    INDEX `idx_categoria` (`categoria`),
    INDEX `idx_publico` (`publico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. CONFIGURAÇÕES DE SAQUES
DROP TABLE IF EXISTS `config_saques`;
CREATE TABLE `config_saques` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `valor_minimo` decimal(10,2) NOT NULL DEFAULT 30.00,
    `valor_maximo` decimal(10,2) DEFAULT NULL,
    `taxa_percentual` decimal(5,2) NOT NULL DEFAULT 8.00,
    `taxa_fixa` decimal(10,2) NOT NULL DEFAULT 0.00,
    `limite_diario` int(11) NOT NULL DEFAULT 1,
    `limite_semanal` int(11) DEFAULT NULL,
    `limite_mensal` int(11) DEFAULT NULL,
    `horario_inicio` time NOT NULL DEFAULT '09:00:00',
    `horario_fim` time NOT NULL DEFAULT '18:00:00',
    `segunda_feira` tinyint(1) NOT NULL DEFAULT 1,
    `terca_feira` tinyint(1) NOT NULL DEFAULT 1,
    `quarta_feira` tinyint(1) NOT NULL DEFAULT 1,
    `quinta_feira` tinyint(1) NOT NULL DEFAULT 1,
    `sexta_feira` tinyint(1) NOT NULL DEFAULT 1,
    `sabado` tinyint(1) NOT NULL DEFAULT 0,
    `domingo` tinyint(1) NOT NULL DEFAULT 0,
    `requer_investimento_ativo` tinyint(1) NOT NULL DEFAULT 1,
    `quantidade_min_investimentos` int(11) NOT NULL DEFAULT 1,
    `requer_chave_pix` tinyint(1) NOT NULL DEFAULT 1,
    `tempo_processamento_min` int(11) NOT NULL DEFAULT 2,
    `tempo_processamento_max` int(11) NOT NULL DEFAULT 24,
    `ativo` tinyint(1) NOT NULL DEFAULT 1,
    `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. CONFIGURAÇÕES DE CADASTRO
DROP TABLE IF EXISTS `configurar_cadastro`;
CREATE TABLE `configurar_cadastro` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `sms_enabled` tinyint(1) DEFAULT 0,
    `require_username` tinyint(1) DEFAULT 0,
    `twilio_sid` varchar(255) DEFAULT '',
    `twilio_token` varchar(255) DEFAULT '',
    `twilio_phone` varchar(20) DEFAULT '',
    `require_invite_code` tinyint(1) DEFAULT 0,
    `min_password_length` int(11) DEFAULT 6,
    `allow_registration` tinyint(1) DEFAULT 1,
    `bonus_cadastro` decimal(10,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. PERSONALIZAÇÃO DO SITE
DROP TABLE IF EXISTS `configurar_textos`;
CREATE TABLE `configurar_textos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `link_suporte` varchar(255) DEFAULT NULL,
    `pop_up` text DEFAULT NULL,
    `anuncio` text DEFAULT NULL,
    `titulo_site` varchar(255) DEFAULT NULL,
    `descricao_site` text DEFAULT NULL,
    `keywords_site` text DEFAULT NULL,
    `link_site` varchar(255) DEFAULT NULL,
    `popup_titulo` varchar(100) DEFAULT 'Notificação',
    `popup_imagem` varchar(255) DEFAULT 'icon.svg',
    `popup_botao_texto` varchar(50) DEFAULT 'Fechar',
    `popup_ativo` tinyint(1) DEFAULT 1,
    `popup_delay` int(11) DEFAULT 3000,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. PERSONALIZAÇÃO DE CORES
DROP TABLE IF EXISTS `personalizar_cores`;
CREATE TABLE `personalizar_cores` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cor_1` varchar(7) DEFAULT '#121A1E',
    `cor_2` varchar(7) DEFAULT '#FFFFFF',
    `cor_3` varchar(7) DEFAULT '#152731',
    `cor_4` varchar(7) DEFAULT '#335D67',
    `cor_5` varchar(7) DEFAULT '#152731',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- SISTEMA DE SEGURANÇA E LOGS
-- ========================================

-- 22. TENTATIVAS DE LOGIN
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ip` varchar(45) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ip_created` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. SESSÕES DE CAPTCHA
DROP TABLE IF EXISTS `captcha_sessions`;
CREATE TABLE `captcha_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `session_id` varchar(255) NOT NULL,
    `question` varchar(100) NOT NULL,
    `answer` int(11) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `used` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_ip_created` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. LOGS DE AUDITORIA ADMINISTRATIVA
DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `admin_email` varchar(255) DEFAULT NULL,
    `action` varchar(255) NOT NULL,
    `details` text DEFAULT NULL,
    `table_affected` varchar(100) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_table_record` (`table_affected`, `record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- GAMIFICAÇÃO ADICIONAL
-- ========================================

-- 25. CHECKLIST DIÁRIO
DROP TABLE IF EXISTS `checklist`;
CREATE TABLE `checklist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `tarefa` varchar(255) NOT NULL,
    `concluida` tinyint(1) DEFAULT 0,
    `recompensa` decimal(10,2) DEFAULT 0.00,
    `data_conclusao` timestamp NULL DEFAULT NULL,
    `valor_dia1` decimal(10,2) DEFAULT 1.00,
    `valor_dia2` decimal(10,2) DEFAULT 2.00,
    `valor_dia3` decimal(10,2) DEFAULT 3.00,
    `valor_dia4` decimal(10,2) DEFAULT 5.00,
    `valor_dia5` decimal(10,2) DEFAULT 8.00,
    `valor_dia6` decimal(10,2) DEFAULT 15.00,
    `valor_dia7` decimal(10,2) DEFAULT 25.00,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_checklist_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_tarefa` (`tarefa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. SISTEMA DE ROLETA
DROP TABLE IF EXISTS `roleta`;
CREATE TABLE `roleta` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nome` varchar(100) NOT NULL,
    `tipo_premio` enum('dinheiro','bonus','produto','desconto') NOT NULL,
    `valor_premio` decimal(10,2) DEFAULT 0.00,
    `percentual_desconto` decimal(5,2) DEFAULT 0.00,
    `produto_id` int(11) DEFAULT NULL,
    `probabilidade` decimal(5,2) NOT NULL,
    `cor` varchar(7) DEFAULT '#FF0000',
    `icone` varchar(50) DEFAULT 'fa-gift',
    `ativo` tinyint(1) DEFAULT 1,
    `ordem` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ativo` (`ativo`),
    INDEX `idx_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. HISTÓRICO DE GIROS DA ROLETA
DROP TABLE IF EXISTS `roleta_historico`;
CREATE TABLE `roleta_historico` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `premio_id` int(11) NOT NULL,
    `tipo_premio` enum('dinheiro','bonus','produto','desconto') NOT NULL,
    `valor_ganho` decimal(10,2) DEFAULT 0.00,
    `data_giro` timestamp DEFAULT CURRENT_TIMESTAMP,
    `ip_address` varchar(45) DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_roleta_historico_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_roleta_historico_premio` FOREIGN KEY (`premio_id`) REFERENCES `roleta` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_data_giro` (`data_giro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- VIEWS ÚTEIS
-- ========================================

-- View para dashboard do usuário
DROP VIEW IF EXISTS `vw_dashboard_usuario`;
CREATE VIEW `vw_dashboard_usuario` AS
SELECT 
    u.id as usuario_id,
    u.nome,
    u.telefone,
    u.email,
    u.status,
    c.saldo_principal,
    c.saldo_bonus,
    c.saldo_comissao,
    (c.saldo_principal + c.saldo_bonus + c.saldo_comissao) as saldo_total,
    c.total_investido,
    c.total_depositado,
    c.total_sacado,
    COUNT(i.id) as total_investimentos_ativos,
    COALESCE(SUM(i.valor_total_rendido), 0) as total_rendido,
    nv.nome as nivel_vip,
    nv.cor_badge as cor_nivel,
    u.created_at as data_cadastro,
    u.ultimo_login
FROM usuarios u
LEFT JOIN carteiras c ON u.id = c.usuario_id
LEFT JOIN investimentos i ON u.id = i.usuario_id AND i.status = 'ativo'
LEFT JOIN niveis_vip nv ON u.nivel_vip_id = nv.id
GROUP BY u.id;

-- ========================================
-- DADOS INICIAIS
-- ========================================

-- Administrador padrão (senha: password - ALTERE IMEDIATAMENTE!)
INSERT INTO `administrador` (`email`, `senha`, `nome`, `nivel`) VALUES
('admin@finverpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'super');

-- Usuário de teste (senha: 123456)
INSERT INTO `usuarios` (`telefone`, `email`, `nome`, `senha`, `codigo_referencia`, `cargo`, `status`) VALUES
('5511999999999', 'teste@finverpro.com', 'Usuário Teste', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TEST001', 'usuario', 'ativo');

-- Carteira para usuário de teste
INSERT INTO `carteiras` (`usuario_id`, `saldo_principal`, `saldo_bonus`) VALUES
(1, 100.00, 50.00);

-- Produtos de exemplo
INSERT INTO `produtos` (`titulo`, `descricao`, `codigo_robo`, `valor_minimo`, `valor_maximo`, `tipo_rendimento`, `rendimento_diario`, `duracao_dias`, `status`, `destaque`) VALUES
('Robô Alpha', 'Robô de investimento conservador ideal para iniciantes', 'ALPHA001', 50.00, 1000.00, 'diario', 2.50, 30, 'ativo', 1),
('Robô Beta', 'Robô de investimento moderado com boa rentabilidade', 'BETA002', 100.00, 5000.00, 'diario', 3.80, 45, 'ativo', 1),
('Robô Gamma', 'Robô de investimento agressivo para perfil arrojado', 'GAMMA003', 500.00, 10000.00, 'diario', 5.20, 60, 'ativo', 0);

-- Níveis VIP padrão
INSERT INTO `niveis_vip` (`codigo`, `nome`, `requisito_investimento`, `requisito_indicacoes`, `cor_badge`, `icone`, `emoji`, `ordem`) VALUES
('V0', 'Iniciante', 0.00, 0, '#6B7280', 'fa-user', '👤', 0),
('V1', 'Bronze', 500.00, 1, '#CD7F32', 'fa-medal', '🥉', 1),
('V2', 'Prata', 2000.00, 5, '#C0C0C0', 'fa-trophy', '🥈', 2),
('V3', 'Ouro', 5000.00, 15, '#FFD700', 'fa-crown', '🥇', 3),
('V4', 'Platina', 15000.00, 50, '#E5E4E2', 'fa-gem', '💎', 4),
('V5', 'Diamante', 50000.00, 150, '#B9F2FF', 'fa-diamond', '👑', 5);

-- Configurações padrão
INSERT INTO `configuracoes` (`categoria`, `chave`, `valor`, `tipo`, `descricao`, `publico`) VALUES
('site', 'nome', 'FinverPro', 'string', 'Nome do site', 1),
('site', 'titulo', 'FinverPro - Investimentos Inteligentes', 'string', 'Título do site', 1),
('site', 'descricao', 'Plataforma de investimentos com IA', 'string', 'Descrição do site', 1),
('site', 'url', 'https://finverpro.shop', 'url', 'URL do site', 1),
('investimento', 'valor_minimo_global', '50.00', 'number', 'Valor mínimo para qualquer investimento', 0),
('saque', 'valor_minimo', '30.00', 'number', 'Valor mínimo para saque', 0),
('saque', 'taxa_percentual', '8.00', 'number', 'Taxa percentual sobre saque', 0),
('comissao', 'nivel_1', '10.00', 'number', 'Comissão nível 1 (%)', 0),
('comissao', 'nivel_2', '6.00', 'number', 'Comissão nível 2 (%)', 0),
('comissao', 'nivel_3', '1.00', 'number', 'Comissão nível 3 (%)', 0);

-- Configurações de comissão padrão
INSERT INTO `configuracao_comissoes` (`nivel`, `percentual`, `descricao`) VALUES
(1, 10.00, 'Comissão Nível 1 - Indicação Direta'),
(2, 6.00, 'Comissão Nível 2 - Segundo Nível'),
(3, 1.00, 'Comissão Nível 3 - Terceiro Nível');

-- Gateways padrão
INSERT INTO `gateways` (`nome`, `codigo`, `ativo`, `ambiente`) VALUES
('PixUP', 'PIXUP', 0, 'sandbox'),
('SuitPay', 'SUITPAY', 0, 'sandbox'),
('VenturePay', 'VENTUREPAY', 0, 'sandbox'),
('BSPay', 'BSPAY', 0, 'sandbox'),
('Manual', 'MANUAL', 1, 'producao');

-- Configuração de saques padrão
INSERT INTO `config_saques` (`valor_minimo`, `taxa_percentual`, `limite_diario`) VALUES
(30.00, 8.00, 1);

-- Configuração de cadastro padrão
INSERT INTO `configurar_cadastro` (`bonus_cadastro`, `min_password_length`) VALUES
(6.00, 6);

-- Configuração de textos padrão
INSERT INTO `configurar_textos` (`titulo_site`, `descricao_site`, `link_suporte`) VALUES
('FinverPro', 'Plataforma de investimentos com IA', 'https://t.me/finverpro');

-- Configuração de cores padrão
INSERT INTO `personalizar_cores` (`cor_1`, `cor_2`, `cor_3`, `cor_4`, `cor_5`) VALUES
('#121A1E', '#FFFFFF', '#152731', '#335D67', '#152731');

-- ========================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ========================================

CREATE INDEX `idx_usuarios_status_created` ON `usuarios` (`status`, `created_at`);
CREATE INDEX `idx_investimentos_usuario_status_vencimento` ON `investimentos` (`usuario_id`, `status`, `data_vencimento`);
CREATE INDEX `idx_transacoes_usuario_tipo_status` ON `transacoes` (`usuario_id`, `tipo`, `status`);
CREATE INDEX `idx_comissoes_usuario_status_data` ON `comissoes` (`usuario_id`, `status`, `created_at`);
CREATE INDEX `idx_saques_status_created` ON `saques` (`status`, `created_at`);
CREATE INDEX `idx_pagamentos_status_created` ON `pagamentos` (`status`, `data_criacao`);

-- ========================================
-- COMMIT E FINALIZAÇÃO
-- ========================================

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ========================================
-- ESTRUTURA COMPLETA CRIADA COM SUCESSO!
-- ========================================

/*
🎉 BANCO DE DADOS FINVER PRO COMPLETO!

✅ ESTRUTURA INCLUÍDA:
- 27 tabelas principais
- Foreign Keys e integridade
- Índices otimizados
- Views úteis
- Dados iniciais
- Sistema de logs de auditoria
- Configurações centralizadas
- Gamificação completa

✅ FUNCIONALIDADES:
- Usuários e carteiras
- Produtos e investimentos
- Sistema de afiliados
- Pagamentos e saques
- Configurações flexíveis
- Logs de auditoria
- Níveis VIP
- Roleta e checklist

🚀 PRONTO PARA USO EM PRODUÇÃO!
*/