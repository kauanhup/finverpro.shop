-- SQL COMPLETO SEM FOREIGN KEYS PARA HOSTINGER
-- Database: meu_site

--
-- Table structure for table `administrador`
--

DROP TABLE IF EXISTS `administrador`;
CREATE TABLE `administrador` (
  `id` int(11) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `senha` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `administrador` VALUES (1,'5565996498222','kauan123');

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telefone` varchar(15) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `saldo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `nome` varchar(255) DEFAULT '',
  `email` varchar(255) DEFAULT '',
  `valor_investimento` decimal(10,2) DEFAULT 0.00,
  `codigo_referencia` varchar(10) DEFAULT NULL,
  `nivel_vip_id` int(11) DEFAULT 0,
  `salario_total` decimal(10,2) DEFAULT 0.00,
  `referenciado_por` int(11) DEFAULT NULL,
  `referenciador_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `valor_deposito` decimal(10,2) DEFAULT 0.00,
  `referencia_convite` varchar(10) DEFAULT NULL,
  `cargo` varchar(50) DEFAULT 'usuario',
  `data_cadastro` timestamp NULL DEFAULT current_timestamp(),
  `saldo_comissao` decimal(10,2) NOT NULL DEFAULT 0.00,
  `checklist` int(11) NOT NULL DEFAULT 0,
  `data_checklist` date DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `total_indicacoes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telefone` (`telefone`),
  UNIQUE KEY `codigo_referencia` (`codigo_referencia`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  INSERT INTO `usuarios` VALUES
(1,'5511999999999','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',100.00,'','',0.00,'REF000001',1,0.00,NULL,NULL,'2025-06-13 11:48:39',0.00,NULL,'usuario','2025-06-13 19:00:55',0.00,0,NULL,NULL,0),
(2,'5565996498222','$2y$12$dLa2AANI2aB6ZuoY6JDYzu8nP1vNPdP/.UmiUx5IVRbypIPdgk4qy',6795.75,'','',0.00,'REF000002',1,0.00,NULL,NULL,'2025-06-13 11:48:39',0.00,NULL,'admin','2025-06-13 19:00:55',0.00,1,'2025-06-22','user_2_1750462696_6855f0e88af5a.jpg',2),
(3,'11999887766',NULL,0.00,'','',500.00,NULL,0,0.00,2,NULL,'2025-06-17 23:55:26',0.00,NULL,'usuario','2025-06-17 23:55:26',0.00,0,NULL,NULL,0),
(4,'11999887767',NULL,0.00,'','',300.00,NULL,0,0.00,2,NULL,'2025-06-17 23:55:26',0.00,NULL,'usuario','2025-06-17 23:55:26',0.00,0,NULL,NULL,0),
(5,'11999887768',NULL,0.00,'','',800.00,NULL,0,0.00,2,NULL,'2025-06-17 23:55:26',0.00,NULL,'usuario','2025-06-17 23:55:26',0.00,0,NULL,NULL,0),
(6,'11999887769',NULL,0.00,'','',400.00,NULL,0,0.00,2,NULL,'2025-06-17 23:55:26',0.00,NULL,'usuario','2025-06-17 23:55:26',0.00,0,NULL,NULL,0),
(7,'11999887770',NULL,0.00,'','',600.00,NULL,0,0.00,2,NULL,'2025-06-17 23:55:26',0.00,NULL,'usuario','2025-06-17 23:55:26',0.00,0,NULL,NULL,0),
(11,'5565996498221','$2y$12$A3zFmebhePfo1VPuCReZ8uzlIMEjf6U.2Vchqm1R3/m6QrYcrKOI6',200.00,'Kauan','',0.00,'5A94C01E',0,0.00,2,2,'2025-06-22 13:50:29',0.00,'REF000002','usuario','2025-06-22 13:50:29',0.00,0,NULL,NULL,0),
(12,'5565996068451','$2y$12$dAKyOi9jU2Rzl98s3VQkEez3PaaCExl7inRrNPuDe3wrih3c.sp5a',400.00,'Deuza','',0.00,'AA4BB15D',0,0.00,2,2,'2025-06-22 13:53:01',0.00,'REF000002','usuario','2025-06-22 13:53:01',0.00,0,NULL,NULL,0);

--
-- Table structure for table `produtos`
--

DROP TABLE IF EXISTS `produtos`;
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT 'default.jpg',
  `valor_investimento` decimal(10,2) NOT NULL,
  `renda_diaria` decimal(10,2) NOT NULL,
  `validade` int(11) NOT NULL,
  `receita_total` decimal(10,2) NOT NULL,
  `status` enum('ativo','arquivado','inativo') DEFAULT 'ativo',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `limite_compras` int(11) DEFAULT 100,
  `vendidos` int(11) DEFAULT 0,
  `robot_number` varchar(10) DEFAULT 'R51',
  `duracao_dias` int(11) DEFAULT 30,
  `limite_dias_venda` int(11) DEFAULT NULL,
  `tipo_rendimento` enum('diario','final') DEFAULT 'diario',
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `produtos` VALUES
(11,'Hsbabb','Hsabha\r\nDx\r\nD\r\n\r\nX\r\nX\r\nX\r\nX\r\nX\r\nX\r\nX','produto_1749839610_4525.jpg',20.00,11.00,30,11.00,'arquivado','2025-06-13 18:33:30',300,3,'157g',30,3,'final','2025-06-20 13:55:17','2025-06-13 18:33:30'),
(13,'Robô EX','Robô ex','produto_1750433481_5167.jpg',200.00,5.00,11,50.00,'ativo','2025-06-20 15:31:21',1,2,'V1',10,160,'diario','2025-06-22 16:00:47','2025-06-20 15:31:21');

--
-- Table structure for table `carteira`
--

DROP TABLE IF EXISTS `carteira`;
CREATE TABLE `carteira` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `saldo` decimal(10,2) DEFAULT 0.00,
  `saldo_bonus` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `bonus`
--

DROP TABLE IF EXISTS `bonus`;
CREATE TABLE `bonus` (
  `id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `qnt_usos` int(11) DEFAULT NULL,
  `qnt_usados` int(11) DEFAULT NULL,
  `data_vencimento` datetime DEFAULT NULL,
  `saldo` int(11) DEFAULT NULL,
  `data_criacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bonus` VALUES
(NULL,'testee',1,0,'2025-06-14 00:00:00',600,'2025-06-13 11:06:32'),
(NULL,'TESTEE',1,0,'2025-06-22 00:00:00',600,'2025-06-13 11:07:23');

--
-- Table structure for table `bonus_codigos`
--

DROP TABLE IF EXISTS `bonus_codigos`;
CREATE TABLE `bonus_codigos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `max_usos` int(11) DEFAULT 1,
  `usos_atuais` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bonus_codigos` VALUES
(1,'WELCOME50',50.00,'Bônus de boas-vindas',100,0,1,'2025-06-12 23:24:23',NULL),
(2,'BONUS100',100.00,'Bônus promocional especial',50,0,1,'2025-06-12 23:24:23',NULL),
(3,'VIP200',200.00,'Bônus VIP exclusivo',25,0,1,'2025-06-12 23:24:23',NULL),
(4,'PROMO25',25.00,'Promoção limitada',200,0,1,'2025-06-12 23:24:23',NULL),
(5,'INICIO10',10.00,'Bônus para iniciantes',500,0,1,'2025-06-12 23:24:23',NULL),
(6,'BONUS123',1000.00,'Bônus especial de R$ 1000',1,1,1,'2025-06-12 23:31:01',NULL);

--
-- Table structure for table `bonus_resgatados`
--

DROP TABLE IF EXISTS `bonus_resgatados`;
CREATE TABLE `bonus_resgatados` (
  `user_id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `saldo` int(11) DEFAULT NULL,
  `data_resgate` datetime DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bonus_resgatados` VALUES (2,'BONUS123',NULL,NULL,1000.00);

--
-- Table structure for table `captcha_sessions`
--

DROP TABLE IF EXISTS `captcha_sessions`;
CREATE TABLE `captcha_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `question` varchar(100) NOT NULL,
  `answer` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `ip_address` (`ip_address`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `captcha_sessions` VALUES
(114,'502dc0c7fdc644e99218cea10a0660c3','2 - 5',-3,'127.0.0.1','2025-06-24 00:44:32',0);

--
-- Table structure for table `chaves_pix`
--

DROP TABLE IF EXISTS `chaves_pix`;
CREATE TABLE `chaves_pix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tipo_pix` enum('cpf','celular','email','chave-aleatoria') NOT NULL,
  `nome_titular` varchar(255) NOT NULL,
  `apelido` varchar(50) DEFAULT NULL,
  `chave_pix` varchar(255) NOT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `ativa` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chaves_pix` VALUES
(1,2,'cpf','Kauan domingos hup','Conta 1 ','704.944.371-97','ativo',1,'2025-06-09 12:03:08','2025-06-11 13:59:25'),
(2,2,'celular','Kauan domingos hup','Conta 2','(65) 99649-8222','ativo',0,'2025-06-09 12:06:46','2025-06-11 13:59:25'),
(3,2,'email','Kauan domingos hup','Chave 3 ','kauanhup@gmail.com','ativo',0,'2025-06-09 12:07:47','2025-06-11 13:57:53');

--
-- Table structure for table `checklist`
--

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
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `checklist` VALUES
(4,0,'CONFIG_VALORES',0,0.00,NULL,0.25,0.50,1.00,2.00,2.50,3.50,25.00);

--
-- Table structure for table `comissoes`
--

DROP TABLE IF EXISTS `comissoes`;
CREATE TABLE `comissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `referido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investimento` decimal(10,2) NOT NULL,
  `valor_comissao` decimal(10,2) NOT NULL,
  `nivel` int(11) NOT NULL,
  `status` enum('pendente','processado') DEFAULT 'pendente',
  `data_comissao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `referido_id` (`referido_id`),
  KEY `produto_id` (`produto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `comissoes` VALUES
(1,2,11,13,200.00,20.00,1,'processado','2025-06-22 16:00:47');

--
-- Table structure for table `config_saques`
--

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
  `mensagem_sucesso` text DEFAULT NULL,
  `mensagem_fora_horario` text DEFAULT NULL,
  `mensagem_limite_diario` text DEFAULT NULL,
  `mensagem_sem_investimento` text DEFAULT NULL,
  `mensagem_saldo_insuficiente` text DEFAULT NULL,
  `calculo_taxa` enum('percentual','fixo','hibrido') NOT NULL DEFAULT 'percentual',
  `aplicar_taxa_sobre` enum('valor_bruto','valor_liquido') NOT NULL DEFAULT 'valor_bruto',
  `arredondar_centavos` tinyint(1) NOT NULL DEFAULT 1,
  `permitir_mesmo_dia_deposito` tinyint(1) NOT NULL DEFAULT 1,
  `considerar_feriados` tinyint(1) NOT NULL DEFAULT 0,
  `bloquear_dezembro` tinyint(1) NOT NULL DEFAULT 0,
  `bloquear_janeiro` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `criado_por` int(11) DEFAULT NULL,
  `atualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config_saques` VALUES
(1,50.00,10000.00,9.00,0.00,1,NULL,NULL,'08:00:00','18:00:00',1,1,1,1,1,0,0,1,1,1,2,72,'Saque realizado com sucesso!','Saques só podem ser realizados de segunda a sexta, das 9h às 18h.','Você já realizou um saque hoje. Limite de 1 saque por dia.','Você precisa ter pelo menos 1 investimento ativo antes de solicitar um saque.','Seu saldo é insuficiente para saque.','percentual','valor_bruto',1,1,1,0,0,1,'2025-06-22 07:47:22','2025-06-22 09:10:17',NULL,2);

--
-- Table structure for table `config_saques_historico`
--

DROP TABLE IF EXISTS `config_saques_historico`;
CREATE TABLE `config_saques_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `campo_alterado` varchar(100) NOT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_novo` text DEFAULT NULL,
  `alterado_por` int(11) NOT NULL,
  `alterado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `configuracao_comissoes`
--

DROP TABLE IF EXISTS `configuracao_comissoes`;
CREATE TABLE `configuracao_comissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nivel` int(11) NOT NULL,
  `percentual` decimal(5,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nivel_unico` (`nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracao_comissoes` VALUES
(1,1,10.00,'Comissão Nível 1 - Indicação Direta',1,'2025-06-20 16:48:15','2025-06-20 17:12:00'),
(2,2,6.00,'Comissão Nível 2 - Segundo Nível',1,'2025-06-20 16:48:15','2025-06-20 17:12:11'),
(3,3,1.00,'Comissão Nível 3 - Terceiro Nível',1,'2025-06-20 16:48:15','2025-06-20 17:12:22');

--
-- Table structure for table `configurar_cadastro`
--

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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configurar_cadastro` VALUES
(1,0,1,'','','',1,6,1,6.00,'2025-06-19 12:57:06','2025-06-22 13:52:15');

--
-- Table structure for table `gateway`
--

DROP TABLE IF EXISTS `gateway`;
CREATE TABLE `gateway` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(500) DEFAULT NULL,
  `client_secret` varchar(1000) DEFAULT NULL,
  `status` enum('true','false') DEFAULT NULL,
  `banco` enum('SuitPay','VenturePay','PixUP','BSPay','SYNCPAY','FIVEPAY') DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gateway` VALUES
(1,'','','false','SYNCPAY',NULL),
(2,'','','false','FIVEPAY',NULL),
(3,'','','false','SuitPay',NULL),
(4,'Fernanda2025_5923864912','51747b29e33b296bebbdc243f46f30bb9cb1b1139488f4b52f86d4762787a87d','true','PixUP',NULL),
(5,'','','false','VenturePay',NULL);

--
-- Table structure for table `historico_rendimentos`
--

DROP TABLE IF EXISTS `historico_rendimentos`;
CREATE TABLE `historico_rendimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `investimento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `valor_rendimento` decimal(10,2) NOT NULL,
  `data_rendimento` date NOT NULL,
  `processado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `investimento_id` (`investimento_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `historico_transacoes`
--

DROP TABLE IF EXISTS `historico_transacoes`;
CREATE TABLE `historico_transacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tipo` enum('deposito','saque','investimento','rendimento','comissao') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('pendente','concluido','cancelado') DEFAULT 'pendente',
  `data_transacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `historico_transacoes` VALUES
(1,2,'investimento',100.00,'Investimento em Plano Básico','concluido','2025-06-12 23:36:10'),
(2,2,'investimento',100.00,'Investimento em Plano Básico','concluido','2025-06-12 23:37:17'),
(3,2,'investimento',100.00,'Investimento em testee','concluido','2025-06-13 18:29:28'),
(4,2,'investimento',20.00,'Investimento em Hsbabb','concluido','2025-06-13 18:39:20'),
(5,2,'investimento',20.00,'Investimento em Hsbabb','concluido','2025-06-13 18:39:39'),
(6,2,'investimento',20.00,'Investimento em Hsbabb','concluido','2025-06-13 22:02:36'),
(7,2,'investimento',200.00,'Investimento em Robô EX','concluido','2025-06-20 15:32:09'),
(8,2,'comissao',5900.00,'Transferência de comissões para carteira principal','concluido','2025-06-20 22:09:11'),
(9,2,'comissao',20.00,'Comissão nível 1 - Referido ID: 11','concluido','2025-06-22 16:00:47'),
(10,11,'investimento',200.00,'Investimento em Robô EX','concluido','2025-06-22 16:00:47'),
(11,2,'comissao',40.00,'Transferência de comissões para carteira principal','concluido','2025-06-22 16:02:00'),
(12,2,'rendimento',5.00,'Rendimento de 1 dias (creditado 1 dia após) - Robô EX','concluido','2025-06-22 16:13:22');

--
-- Table structure for table `indicacoes`
--

DROP TABLE IF EXISTS `indicacoes`;
CREATE TABLE `indicacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `indicado_id` int(11) NOT NULL,
  `data_indicacao` timestamp NULL DEFAULT current_timestamp(),
  `bonus` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `indicado_id` (`indicado_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `indicacoes` VALUES
(1,2,11,'2025-06-22 13:50:29',0.00),
(2,2,12,'2025-06-22 13:53:01',0.00);

--
-- Table structure for table `investidores`
--

DROP TABLE IF EXISTS `investidores`;
CREATE TABLE `investidores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `produto_investido` int(11) NOT NULL,
  `valor_investido` decimal(10,2) NOT NULL,
  `renda_diaria` decimal(10,2) NOT NULL,
  `renda_total` decimal(10,2) DEFAULT 0.00,
  `dias_restantes` int(11) NOT NULL,
  `data_investimento` timestamp NULL DEFAULT current_timestamp(),
  `data_vencimento` date NOT NULL,
  `status` enum('ativo','concluido','cancelado') DEFAULT 'ativo',
  `ultimo_rendimento` date DEFAULT NULL,
  `numero_telefone` varchar(20) DEFAULT NULL,
  `ultimo_ciclo` date DEFAULT NULL,
  `ciclo_rendimento` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_usuario` (`id_usuario`),
  KEY `produto_investido` (`produto_investido`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `investimentos`
--

DROP TABLE IF EXISTS `investimentos`;
CREATE TABLE `investimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investido` decimal(10,2) NOT NULL,
  `renda_diaria` decimal(10,2) NOT NULL,
  `renda_total` decimal(10,2) DEFAULT 0.00,
  `dias_restantes` int(11) NOT NULL,
  `data_investimento` timestamp NULL DEFAULT current_timestamp(),
  `data_vencimento` date NOT NULL,
  `status` enum('ativo','concluido','cancelado') DEFAULT 'ativo',
  `ultimo_rendimento` date DEFAULT NULL,
  `tipo_rendimento` enum('diario','final') DEFAULT 'diario',
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `investimentos` VALUES
(1,2,11,20.00,11.00,0.00,30,'2025-06-13 18:39:20','2025-07-13','ativo',NULL,'final'),
(2,2,11,20.00,11.00,0.00,30,'2025-06-13 18:39:39','2025-07-13','ativo',NULL,'final'),
(3,2,11,20.00,11.00,0.00,30,'2025-06-13 22:02:36','2025-07-13','ativo',NULL,'final'),
(4,2,13,200.00,5.00,5.00,9,'2025-06-20 15:32:09','2025-06-30','ativo','2025-06-21','diario'),
(5,11,13,200.00,5.00,0.00,10,'2025-06-22 16:00:47','2025-07-02','ativo',NULL,'diario');

--
-- Table structure for table `investimentos_usuarios`
--

DROP TABLE IF EXISTS `investimentos_usuarios`;
CREATE TABLE `investimentos_usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investido` decimal(10,2) NOT NULL,
  `data_investimento` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `produto_id` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `login_attempts` VALUES
(1,'127.0.0.1','2025-06-22 14:39:17'),
(2,'127.0.0.1','2025-06-22 14:41:28'),
(3,'127.0.0.1','2025-06-22 14:44:18'),
(4,'127.0.0.1','2025-06-22 14:45:12');

--
-- Table structure for table `niveis`
--

DROP TABLE IF EXISTS `niveis`;
CREATE TABLE `niveis` (
  `nivel` int(11) DEFAULT NULL,
  `percentual_comissao` int(11) DEFAULT NULL,
  `valor_minimo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `niveis` VALUES
(1,1,80),
(2,2,200),
(3,25,500);

--
-- Table structure for table `niveis_convite`
--

DROP TABLE IF EXISTS `niveis_convite`;
CREATE TABLE `niveis_convite` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nivel_1` int(11) DEFAULT 0,
  `total_nivel1` decimal(10,2) DEFAULT 0.00,
  `nivel_2` int(11) DEFAULT 0,
  `total_nivel2` decimal(10,2) DEFAULT 0.00,
  `nivel_3` int(11) DEFAULT 0,
  `total_nivel3` decimal(10,2) DEFAULT 0.00,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `niveis_convite` VALUES
(1,1,0,0.00,0,0.00,0,0.00,'2025-06-08 21:43:41'),
(2,2,5,0.00,0,0.00,0,0.00,'2025-06-22 16:02:00'),
(3,2,5,0.00,0,0.00,0,0.00,'2025-06-22 16:02:00');

--
-- Table structure for table `niveis_vip`
--

DROP TABLE IF EXISTS `niveis_vip`;
CREATE TABLE `niveis_vip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nivel` varchar(10) NOT NULL,
  `nome` varchar(50) DEFAULT NULL,
  `salario_padrao` decimal(10,2) DEFAULT NULL,
  `cor_badge` varchar(7) DEFAULT NULL,
  `icone` varchar(20) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `niveis_vip` VALUES
(1,'V0','Iniciante',0.00,'#6B7280','fa-user','👤'),
(2,'V1','Início da Jornada',5000.00,'#FFD700','fa-medal','🥇'),
(3,'V2','Influência em Expansão',15000.00,'#C0C0C0','fa-trophy','🥈'),
(4,'V3','Liderança em Formação',30000.00,'#CD7F32','fa-crown','🥉'),
(5,'V4','Líder Regional',50000.00,'#E5E4E2','fa-gem','🏆'),
(6,'V5','Embaixador do Projeto',100000.00,'#B9F2FF','fa-diamond','👑');

--
-- Table structure for table `pagamentos`
--

DROP TABLE IF EXISTS `pagamentos`;
CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `nome` varchar(50) DEFAULT NULL,
  `cpf` varchar(50) DEFAULT NULL,
  `numero_telefone` varchar(15) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `cod_referencia` varchar(50) DEFAULT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') NOT NULL,
  `data` datetime DEFAULT current_timestamp(),
  `Banco` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pagamentos` VALUES
(1,2,NULL,NULL,'5565996498222',50.00,'5dd9cf37b865dff6e543mbwv57i8z4vm','Aprovado','2025-06-14 20:24:03','PIXUP'),
(6,2,NULL,NULL,'5565996498222',50.00,'f914201c1b05dafae6b6mbxlzi2k5qa8','Aprovado','2025-06-15 08:55:27','PIXUP'),
(7,2,NULL,NULL,'5565996498222',50.00,'b2e70476e4101fcfc04bmbxm7zov3qw6','Aprovado','2025-06-15 09:02:03','PIXUP'),
(8,2,NULL,NULL,'5565996498222',30.00,'2b9b81b7527bc36e02f6mbxogof93u0i','Pendente','2025-06-15 10:04:47','PIXUP'),
(9,2,NULL,NULL,'5565996498222',30.00,'3fc0ebce00e4778ba7acmbxonvi51ncu','Pendente','2025-06-15 10:10:23','PIXUP'),
(10,2,NULL,NULL,'5565996498222',30.00,'d45fee66ad9b9ba6066ambxp6chc4yyz','Aprovado','2025-06-15 10:24:45','PIXUP'),
(11,2,NULL,NULL,'5565996498222',50.00,'25e885263fed9446376cmbz4exa45gcy','Pendente','2025-06-16 10:19:04','PIXUP'),
(12,2,NULL,NULL,'5565996498222',50.00,'bce354432abcfb715bf2mbzc0c1z10vw','Pendente','2025-06-16 13:51:41','PIXUP'),
(13,2,NULL,NULL,'5565996498222',50.00,'7208d2899d19f137bdc9mbzoiobc2vey','Pendente','2025-06-16 19:41:52','PIXUP'),
(14,2,NULL,NULL,'5565996498222',30.00,'1df121174d47eefc16f4mc3o9jsq1vjp','Pendente','2025-06-19 14:45:50','PIXUP'),
(15,2,NULL,NULL,'5565996498222',30.00,'7b98b4066c2241913856mc3ox96u49nw','Pendente','2025-06-19 15:04:17','PIXUP'),
(16,2,NULL,NULL,'5565996498222',30.00,'503b074f45499dc2b15cmc3p5mv91t77','Pendente','2025-06-19 15:10:48','PIXUP'),
(17,2,NULL,NULL,'5565996498222',30.00,'4a2adf6ffbd99744cbfdmc3qihq24bx7','Pendente','2025-06-19 15:48:48','PIXUP');

--
-- Table structure for table `personalizar_cores`
--

DROP TABLE IF EXISTS `personalizar_cores`;
CREATE TABLE `personalizar_cores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cor_1` varchar(7) DEFAULT NULL,
  `cor_2` varchar(7) DEFAULT NULL,
  `cor_3` varchar(7) DEFAULT NULL,
  `cor_4` varchar(7) DEFAULT NULL,
  `cor_5` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `personalizar_cores` VALUES
(1,'#121A1E','white','#152731','#335D67','#152731'),
(2,'#2e2e2e','#FFFFFF','#6b6b6b','#303536','#4a4a4a');

--
-- Table structure for table `personalizar_imagens`
--

DROP TABLE IF EXISTS `personalizar_imagens`;
CREATE TABLE `personalizar_imagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `logo` varchar(255) DEFAULT NULL,
  `tela_pix` varchar(255) DEFAULT '1.jpg',
  `tela_retirada` varchar(255) DEFAULT 'retirada.jpg',
  `tela_login` varchar(255) DEFAULT NULL,
  `inicio` varchar(255) DEFAULT '2.jpg',
  `tela_avatar` varchar(255) DEFAULT 'avatar.jpg',
  `tela_bonus` varchar(255) DEFAULT '1.jpg',
  `tela_perfil` varchar(255) DEFAULT NULL,
  `checklist_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `personalizar_imagens` VALUES
(1,'extra_gg_logo-removebg-preview_6854775be9827_1750366043.png','Logo-Pix-Png_6854775c20ba4_1750366044.webp','bonus___1_-removebg-preview_6854775c29be9_1750366044.png','BOASVINDAS_6854775bf0c96_1750366043.png','carteira2_6854775c056d3_1750366044.png','avatar-2_6854775c181ce_1750366044.jpg','bonus_-removebg-preview_6854775c34299_1750366044.png','3135768_6854775c103c2_1750366044.png','check-removebg-preview_6854775c3f7a2_1750366044.png','2025-06-14 12:02:06','2025-06-19 20:47:24');

--
-- Table structure for table `configurar_textos`
--

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
  `ticker_msg_1` text DEFAULT NULL,
  `ticker_msg_2` text DEFAULT NULL,
  `ticker_msg_3` text DEFAULT NULL,
  `ticker_msg_4` text DEFAULT NULL,
  `ticker_msg_5` text DEFAULT NULL,
  `ticker_msg_6` text DEFAULT NULL,
  `ticker_msg_7` text DEFAULT NULL,
  `ticker_msg_8` text DEFAULT NULL,
  `ticker_msg_9` text DEFAULT NULL,
  `ticker_msg_10` text DEFAULT NULL,
  `ticker_icon_1` varchar(50) DEFAULT 'fas fa-fire',
  `ticker_icon_2` varchar(50) DEFAULT 'fas fa-chart-line',
  `ticker_icon_3` varchar(50) DEFAULT 'fas fa-trophy',
  `ticker_icon_4` varchar(50) DEFAULT 'fas fa-rocket',
  `ticker_icon_5` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_6` varchar(50) DEFAULT 'fas fa-money-bill-wave',
  `ticker_icon_7` varchar(50) DEFAULT 'fas fa-users',
  `ticker_icon_8` varchar(50) DEFAULT 'fas fa-gem',
  `ticker_icon_9` varchar(50) DEFAULT 'fas fa-crown',
  `ticker_icon_10` varchar(50) DEFAULT 'fas fa-bolt',
  `ticker_ativo_1` tinyint(1) DEFAULT 0,
  `ticker_ativo_2` tinyint(1) DEFAULT 0,
  `ticker_ativo_3` tinyint(1) DEFAULT 0,
  `ticker_ativo_4` tinyint(1) DEFAULT 0,
  `ticker_ativo_5` tinyint(1) DEFAULT 0,
  `ticker_ativo_6` tinyint(1) DEFAULT 0,
  `ticker_ativo_7` tinyint(1) DEFAULT 0,
  `ticker_ativo_8` tinyint(1) DEFAULT 0,
  `ticker_ativo_9` tinyint(1) DEFAULT 0,
  `ticker_ativo_10` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configurar_textos` VALUES
(1,'https://t.me/65996498222','Invista na Finver Pro e evolua seu nível','Investimentos com descontos','Finver Pro','Deixe que robôs de inteligência artificial Invista por você!','Dinheiro,investimentos,plataforma,facil','https://finverpro.com','Bem-vindo!','popup_1750621109.jpg','Obrigado❤️',1,3000,'João Silva ganhou R$ 1.250 hoje!','Maria Costa já lucrou R$ 5.890 este mês','+2.847 investidores ativos na plataforma','Pedro Santos: \"Melhor investimento que já fiz!\"','Ana Costa retirou R$ 3.400','Carlos Mendes: R$ 890 de lucro em 24h','+1.000 investidores toda semana','','','','fas fa-money-bill-wave','fas fa-chart-line','fas fa-rocket','fas fa-crown','fas fa-money-bill-wave','fas fa-money-bill-wave','fas fa-rocket','fas fa-gem','fas fa-crown','fas fa-bolt',1,1,1,1,1,1,1,0,0,0),
(2,'https://t.me/','Estamos muito felizes em tê-lo como investidor. Co','Estamos felizes em tê-lo conosco no time','Plataforma - Investimentos','Descricao','','','Notificação','icon.svg','Fechar',1,3000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'fas fa-fire','fas fa-chart-line','fas fa-trophy','fas fa-rocket','fas fa-star','fas fa-money-bill-wave','fas fa-users','fas fa-gem','fas fa-crown','fas fa-bolt',0,0,0,0,0,0,0,0,0,0),
(3,'https://t.me/','Estamos muito felizes em tê-lo como investidor. Co','Estamos felizes em tê-lo conosco no time','Plataforma - Investimentos','Descricao','','','Notificação','icon.svg','Fechar',1,3000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'fas fa-fire','fas fa-chart-line','fas fa-trophy','fas fa-rocket','fas fa-star','fas fa-money-bill-wave','fas fa-users','fas fa-gem','fas fa-crown','fas fa-bolt',0,0,0,0,0,0,0,0,0,0);

--
-- Table structure for table `roleta`
--

DROP TABLE IF EXISTS `roleta`;
CREATE TABLE `roleta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premio_1_nome` varchar(100) NOT NULL DEFAULT 'iPhone 15 Pro',
  `premio_1_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'produto',
  `premio_1_valor` decimal(10,2) DEFAULT 0.00,
  `premio_1_imagem` varchar(255) DEFAULT NULL,
  `premio_1_cor` varchar(7) DEFAULT '#FF6B6B',
  `premio_1_chance` decimal(5,2) DEFAULT 2.00,
  `premio_2_nome` varchar(100) NOT NULL DEFAULT 'R$ 50,00',
  `premio_2_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'dinheiro',
  `premio_2_valor` decimal(10,2) DEFAULT 50.00,
  `premio_2_imagem` varchar(255) DEFAULT NULL,
  `premio_2_cor` varchar(7) DEFAULT '#4ECDC4',
  `premio_2_chance` decimal(5,2) DEFAULT 15.00,
  `premio_3_nome` varchar(100) NOT NULL DEFAULT 'Que pena!',
  `premio_3_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'nada',
  `premio_3_valor` decimal(10,2) DEFAULT 0.00,
  `premio_3_imagem` varchar(255) DEFAULT NULL,
  `premio_3_cor` varchar(7) DEFAULT '#95A5A6',
  `premio_3_chance` decimal(5,2) DEFAULT 25.00,
  `premio_4_nome` varchar(100) NOT NULL DEFAULT 'R$ 100,00',
  `premio_4_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'dinheiro',
  `premio_4_valor` decimal(10,2) DEFAULT 100.00,
  `premio_4_imagem` varchar(255) DEFAULT NULL,
  `premio_4_cor` varchar(7) DEFAULT '#45B7D1',
  `premio_4_chance` decimal(5,2) DEFAULT 10.00,
  `premio_5_nome` varchar(100) NOT NULL DEFAULT 'AirPods Pro',
  `premio_5_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'produto',
  `premio_5_valor` decimal(10,2) DEFAULT 0.00,
  `premio_5_imagem` varchar(255) DEFAULT NULL,
  `premio_5_cor` varchar(7) DEFAULT '#9B59B6',
  `premio_5_chance` decimal(5,2) DEFAULT 3.00,
  `premio_6_nome` varchar(100) NOT NULL DEFAULT 'R$ 25,00',
  `premio_6_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'dinheiro',
  `premio_6_valor` decimal(10,2) DEFAULT 25.00,
  `premio_6_imagem` varchar(255) DEFAULT NULL,
  `premio_6_cor` varchar(7) DEFAULT '#F39C12',
  `premio_6_chance` decimal(5,2) DEFAULT 20.00,
  `premio_7_nome` varchar(100) NOT NULL DEFAULT 'Ops! Não foi dessa vez',
  `premio_7_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'nada',
  `premio_7_valor` decimal(10,2) DEFAULT 0.00,
  `premio_7_imagem` varchar(255) DEFAULT NULL,
  `premio_7_cor` varchar(7) DEFAULT '#E74C3C',
  `premio_7_chance` decimal(5,2) DEFAULT 20.00,
  `premio_8_nome` varchar(100) NOT NULL DEFAULT 'R$ 200,00',
  `premio_8_tipo` enum('dinheiro','produto','nada') NOT NULL DEFAULT 'dinheiro',
  `premio_8_valor` decimal(10,2) DEFAULT 200.00,
  `premio_8_imagem` varchar(255) DEFAULT NULL,
  `premio_8_cor` varchar(7) DEFAULT '#27AE60',
  `premio_8_chance` decimal(5,2) DEFAULT 5.00,
  `valor_minimo_investimento` decimal(10,2) DEFAULT 200.00,
  `giros_por_investimento` int(11) DEFAULT 1,
  `giros_por_indicacao` int(11) DEFAULT 1,
  `limite_giros_dia` int(11) DEFAULT 5,
  `roleta_ativa` tinyint(1) DEFAULT 1,
  `duracao_animacao` int(11) DEFAULT 3000,
  `som_ativo` tinyint(1) DEFAULT 1,
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roleta` VALUES
(1,'iPhone 15 Pro','produto',NULL,NULL,'#ff6b6b',2.00,'R$ 50','dinheiro',50.00,NULL,'#4ecdc4',15.00,'Que pena!','nada',NULL,NULL,'#95a5a6',25.00,'R$ 100','dinheiro',100.00,NULL,'#45b7d1',10.00,'AirPods Pro','produto',NULL,NULL,'#9b59b6',3.00,'R$ 25','dinheiro',25.00,NULL,'#f39c12',20.00,'Nada!','nada',NULL,NULL,'#e74c3c',20.00,'R$ 200','dinheiro',200.00,NULL,'#27ae60',5.00,200.00,1,1,5,1,3000,1,'2025-06-20 00:43:55');

--
-- Table structure for table `roleta_giros_usuario`
--

DROP TABLE IF EXISTS `roleta_giros_usuario`;
CREATE TABLE `roleta_giros_usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `giros_disponiveis` int(11) NOT NULL DEFAULT 0,
  `ultimo_giro` timestamp NULL DEFAULT NULL,
  `giros_hoje` int(11) DEFAULT 0,
  `data_reset_diario` date DEFAULT NULL,
  `total_giros_historico` int(11) DEFAULT 0,
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unico` (`usuario_id`),
  KEY `giros_disponiveis` (`giros_disponiveis`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roleta_giros_usuario` VALUES
(1,2,1,'2025-06-21 00:21:52',0,'2025-06-22',3,'2025-06-22 16:00:47'),
(2,11,1,NULL,0,'2025-06-22',1,'2025-06-22 16:00:47');

--
-- Table structure for table `roleta_historico`
--

DROP TABLE IF EXISTS `roleta_historico`;
CREATE TABLE `roleta_historico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `premio_numero` int(11) NOT NULL,
  `premio_nome` varchar(100) NOT NULL,
  `premio_tipo` enum('dinheiro','produto','nada') NOT NULL,
  `premio_valor` decimal(10,2) DEFAULT 0.00,
  `origem_giro` enum('investimento','indicacao','bonus','manual') NOT NULL,
  `valor_investimento` decimal(10,2) DEFAULT NULL,
  `indicado_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `data_giro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `data_giro` (`data_giro`),
  KEY `origem_giro` (`origem_giro`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roleta_historico` VALUES
(1,2,3,'Que pena!','nada',NULL,'manual',NULL,NULL,'127.0.0.1','2025-06-21 00:21:52');

--
-- Table structure for table `salary_levels`
--

DROP TABLE IF EXISTS `salary_levels`;
CREATE TABLE `salary_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_code` varchar(20) NOT NULL,
  `level_name` varchar(255) DEFAULT NULL,
  `level_description` text DEFAULT NULL,
  `min_people` int(11) NOT NULL DEFAULT 0,
  `min_team_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `icon` varchar(10) DEFAULT '?',
  `color` varchar(7) DEFAULT '#10B981',
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `level_code` (`level_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_levels` VALUES
(1,'INICIANTE','Iniciante Padrão','',0,0.00,0.00,'','#808080',1,1,0,'2025-06-17 03:02:32','2025-06-22 16:27:33'),
(2,'BRONZE','Executivo Bronze','Primeiro nível de qualificação MLM',5,1000.00,150.00,'🥉','#CD7F32',0,1,1,'2025-06-17 03:02:48','2025-06-17 03:02:48'),
(3,'PRATA','Executivo Prata','Segundo nível de qualificação MLM',15,5000.00,400.00,'🥈','#C0C0C0',0,1,2,'2025-06-17 03:02:48','2025-06-17 03:02:48'),
(4,'OURO','Executivo Ouro','Terceiro nível de qualificação MLM',30,15000.00,800.00,'🥇','#FFD700',0,1,3,'2025-06-17 03:02:48','2025-06-17 03:02:48');

--
-- Table structure for table `salary_payments`
--

DROP TABLE IF EXISTS `salary_payments`;
CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `level_id` int(11) NOT NULL,
  `level_code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('disponivel','transferido') DEFAULT 'disponivel',
  `release_date` timestamp NULL DEFAULT current_timestamp(),
  `transfer_date` timestamp NULL DEFAULT NULL,
  `released_by` int(11) NOT NULL,
  `admin_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `level_id` (`level_id`),
  KEY `status` (`status`),
  KEY `request_id` (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_payments` VALUES
(1,2,1,2,'BRONZE',150.00,'transferido','2025-06-18 00:06:27','2025-06-18 00:06:54',2,''),
(2,2,2,2,'BRONZE',150.00,'transferido','2025-06-18 11:58:33','2025-06-18 23:10:07',2,'');

--
-- Table structure for table `salary_requests`
--

DROP TABLE IF EXISTS `salary_requests`;
CREATE TABLE `salary_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `level_code` varchar(20) NOT NULL,
  `requested_amount` decimal(10,2) NOT NULL,
  `current_team_people` int(11) NOT NULL,
  `current_team_value` decimal(12,2) NOT NULL,
  `status` enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  `request_date` timestamp NULL DEFAULT current_timestamp(),
  `response_date` timestamp NULL DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `user_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `level_id` (`level_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `salary_requests` VALUES
(1,2,2,'BRONZE',150.00,5,2950.00,'aprovado','2025-06-18 00:03:41','2025-06-18 00:06:27',2,'',''),
(2,2,2,'BRONZE',150.00,5,2950.00,'aprovado','2025-06-18 00:07:22','2025-06-18 11:58:33',2,'','');

--
-- Table structure for table `saques`
--

DROP TABLE IF EXISTS `saques`;
CREATE TABLE `saques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tipo_pix` enum('cpf','celular','email','chave-aleatoria') NOT NULL,
  `chave_pix` varchar(255) NOT NULL,
  `nome_titular` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `data` timestamp NULL DEFAULT current_timestamp(),
  `numero_telefone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `saques` VALUES
(3,2,'cpf','704.944.371-97','Kauan domingos hup',92.00,'Pendente','2025-06-16 13:00:35','5565996498222','2025-06-16 13:00:35','2025-06-16 13:00:35');

--
-- Table structure for table `saques_comissao`
--

DROP TABLE IF EXISTS `saques_comissao`;
CREATE TABLE `saques_comissao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_processamento` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `data` datetime DEFAULT NULL,
  `tipo_pix` varchar(50) DEFAULT 'CPF',
  `numero_telefone` varchar(20) DEFAULT NULL,
  `nome_titular` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `seo_analysis`
--

DROP TABLE IF EXISTS `seo_analysis`;
CREATE TABLE `seo_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `analysis_date` date NOT NULL,
  `title_score` int(11) DEFAULT 0,
  `description_score` int(11) DEFAULT 0,
  `keywords_score` int(11) DEFAULT 0,
  `overall_score` int(11) DEFAULT 0,
  `suggestions` longtext DEFAULT NULL,
  `performance_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`analysis_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `seo_history`
--

DROP TABLE IF EXISTS `seo_history`;
CREATE TABLE `seo_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `change_reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sms_codes`
--

DROP TABLE IF EXISTS `sms_codes`;
CREATE TABLE `sms_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telefone` varchar(15) NOT NULL,
  `codigo` varchar(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `transacoes`
--

DROP TABLE IF EXISTS `transacoes`;
CREATE TABLE `transacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pendente',
  `data_transacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `transacoes` VALUES
(1,12,'bonus',6.00,'Bônus de boas-vindas por cadastro','aprovado','2025-06-22 13:53:01');