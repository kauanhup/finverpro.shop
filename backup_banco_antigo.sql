-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 28/06/2025 às 20:50
-- Versão do servidor: 10.11.10-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u201575542_meu_site`
--
CREATE DATABASE IF NOT EXISTS `u201575542_meu_site` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `u201575542_meu_site`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `administrador`
--

CREATE TABLE `administrador` (
  `id` int(11) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `senha` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `administrador`
--

INSERT INTO `administrador` (`id`, `email`, `senha`) VALUES
(1, '5565996498222', 'kauan123');

-- --------------------------------------------------------

--
-- Estrutura para tabela `bonus`
--

CREATE TABLE `bonus` (
  `id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `qnt_usos` int(11) DEFAULT NULL,
  `qnt_usados` int(11) DEFAULT NULL,
  `data_vencimento` datetime DEFAULT NULL,
  `saldo` int(11) DEFAULT NULL,
  `data_criacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `bonus`
--

INSERT INTO `bonus` (`id`, `codigo`, `qnt_usos`, `qnt_usados`, `data_vencimento`, `saldo`, `data_criacao`) VALUES
(NULL, 'testee', 1, 0, '2025-06-14 00:00:00', 600, '2025-06-13 11:06:32'),
(NULL, 'TESTEE', 1, 0, '2025-06-22 00:00:00', 600, '2025-06-13 11:07:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `bonus_codigos`
--

CREATE TABLE `bonus_codigos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `max_usos` int(11) DEFAULT 1,
  `usos_atuais` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_expiracao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `bonus_codigos`
--

INSERT INTO `bonus_codigos` (`id`, `codigo`, `valor`, `descricao`, `max_usos`, `usos_atuais`, `ativo`, `data_criacao`, `data_expiracao`) VALUES
(1, 'WELCOME50', 50.00, 'Bônus de boas-vindas', 100, 0, 1, '2025-06-12 23:24:23', NULL),
(2, 'BONUS100', 100.00, 'Bônus promocional especial', 50, 0, 1, '2025-06-12 23:24:23', NULL),
(3, 'VIP200', 200.00, 'Bônus VIP exclusivo', 25, 0, 1, '2025-06-12 23:24:23', NULL),
(4, 'PROMO25', 25.00, 'Promoção limitada', 200, 0, 1, '2025-06-12 23:24:23', NULL),
(5, 'INICIO10', 10.00, 'Bônus para iniciantes', 500, 0, 1, '2025-06-12 23:24:23', NULL),
(6, 'BONUS123', 1000.00, 'Bônus especial de R$ 1000', 1, 1, 1, '2025-06-12 23:31:01', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `bonus_resgatados`
--

CREATE TABLE `bonus_resgatados` (
  `user_id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `saldo` int(11) DEFAULT NULL,
  `data_resgate` datetime DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `bonus_resgatados`
--

INSERT INTO `bonus_resgatados` (`user_id`, `codigo`, `saldo`, `data_resgate`, `valor`) VALUES
(2, 'BONUS123', NULL, NULL, 1000.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `captcha_sessions`
--

CREATE TABLE `captcha_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `question` varchar(100) NOT NULL,
  `answer` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `captcha_sessions`
--

INSERT INTO `captcha_sessions` (`id`, `session_id`, `question`, `answer`, `ip_address`, `created_at`, `used`) VALUES
(186, '149g4q0uisgh6ib9dlmcgrdper', '6 + 6', 12, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-28 20:39:14', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `carteira`
--

CREATE TABLE `carteira` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `saldo` decimal(10,2) DEFAULT 0.00,
  `saldo_bonus` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chaves_pix`
--

CREATE TABLE `chaves_pix` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tipo_pix` enum('cpf','celular','email','chave-aleatoria') NOT NULL,
  `nome_titular` varchar(255) NOT NULL,
  `apelido` varchar(50) DEFAULT NULL,
  `chave_pix` varchar(255) NOT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `ativa` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `chaves_pix`
--

INSERT INTO `chaves_pix` (`id`, `user_id`, `tipo_pix`, `nome_titular`, `apelido`, `chave_pix`, `status`, `ativa`, `created_at`, `updated_at`) VALUES
(1, 2, 'cpf', 'Kauan domingos hup', 'Conta 1 ', '704.944.371-97', 'ativo', 1, '2025-06-09 12:03:08', '2025-06-11 13:59:25'),
(2, 2, 'celular', 'Kauan domingos hup', 'Conta 2', '(65) 99649-8222', 'ativo', 0, '2025-06-09 12:06:46', '2025-06-11 13:59:25'),
(3, 2, 'email', 'Kauan domingos hup', 'Chave 3 ', 'kauanhup@gmail.com', 'ativo', 0, '2025-06-09 12:07:47', '2025-06-11 13:57:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `checklist`
--

CREATE TABLE `checklist` (
  `id` int(11) NOT NULL,
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
  `valor_dia7` decimal(10,2) DEFAULT 25.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `checklist`
--

INSERT INTO `checklist` (`id`, `user_id`, `tarefa`, `concluida`, `recompensa`, `data_conclusao`, `valor_dia1`, `valor_dia2`, `valor_dia3`, `valor_dia4`, `valor_dia5`, `valor_dia6`, `valor_dia7`) VALUES
(4, 0, 'CONFIG_VALORES', 0, 0.00, NULL, 0.25, 0.50, 0.75, 1.15, 1.99, 2.05, 25.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `comissoes`
--

CREATE TABLE `comissoes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `referido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investimento` decimal(10,2) NOT NULL,
  `valor_comissao` decimal(10,2) NOT NULL,
  `nivel` int(11) NOT NULL,
  `status` enum('pendente','processado') DEFAULT 'pendente',
  `data_comissao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `comissoes`
--

INSERT INTO `comissoes` (`id`, `user_id`, `referido_id`, `produto_id`, `valor_investimento`, `valor_comissao`, `nivel`, `status`, `data_comissao`) VALUES
(1, 2, 11, 13, 200.00, 20.00, 1, 'processado', '2025-06-22 16:00:47'),
(2, 2, 11, 15, 200.00, 20.00, 1, 'processado', '2025-06-28 16:49:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracao_comissoes`
--

CREATE TABLE `configuracao_comissoes` (
  `id` int(11) NOT NULL,
  `nivel` int(11) NOT NULL,
  `percentual` decimal(5,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracao_comissoes`
--

INSERT INTO `configuracao_comissoes` (`id`, `nivel`, `percentual`, `descricao`, `ativo`, `data_criacao`, `data_atualizacao`) VALUES
(1, 1, 10.00, 'Comissão Nível 1 - Indicação Direta', 1, '2025-06-20 16:48:15', '2025-06-20 17:12:00'),
(2, 2, 6.00, 'Comissão Nível 2 - Segundo Nível', 1, '2025-06-20 16:48:15', '2025-06-20 17:12:11'),
(3, 3, 1.00, 'Comissão Nível 3 - Terceiro Nível', 1, '2025-06-20 16:48:15', '2025-06-20 17:12:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configurar_cadastro`
--

CREATE TABLE `configurar_cadastro` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configurar_cadastro`
--

INSERT INTO `configurar_cadastro` (`id`, `sms_enabled`, `require_username`, `twilio_sid`, `twilio_token`, `twilio_phone`, `require_invite_code`, `min_password_length`, `allow_registration`, `bonus_cadastro`, `created_at`, `updated_at`) VALUES
(1, 0, 1, '', '', '', 0, 6, 1, 6.00, '2025-06-19 12:57:06', '2025-06-27 00:36:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configurar_textos`
--

CREATE TABLE `configurar_textos` (
  `id` int(11) NOT NULL,
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
  `ticker_msg_11` text DEFAULT NULL,
  `ticker_msg_12` text DEFAULT NULL,
  `ticker_msg_13` text DEFAULT NULL,
  `ticker_msg_14` text DEFAULT NULL,
  `ticker_msg_15` text DEFAULT NULL,
  `ticker_msg_16` text DEFAULT NULL,
  `ticker_msg_17` text DEFAULT NULL,
  `ticker_msg_18` text DEFAULT NULL,
  `ticker_msg_19` text DEFAULT NULL,
  `ticker_msg_20` text DEFAULT NULL,
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
  `ticker_icon_11` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_12` varchar(50) DEFAULT 'fas fa-fire',
  `ticker_icon_13` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_14` varchar(50) DEFAULT 'fas fa-fire',
  `ticker_icon_15` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_16` varchar(50) DEFAULT 'fas fa-fire',
  `ticker_icon_17` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_18` varchar(50) DEFAULT 'fas fa-fire',
  `ticker_icon_19` varchar(50) DEFAULT 'fas fa-star',
  `ticker_icon_20` varchar(50) DEFAULT 'fas fa-fire',
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
  `ticker_ativo_11` tinyint(1) DEFAULT 0,
  `ticker_ativo_12` tinyint(1) DEFAULT 0,
  `ticker_ativo_13` tinyint(1) DEFAULT 0,
  `ticker_ativo_14` tinyint(1) DEFAULT 0,
  `ticker_ativo_15` tinyint(1) DEFAULT 0,
  `ticker_ativo_16` tinyint(1) DEFAULT 0,
  `ticker_ativo_17` tinyint(1) DEFAULT 0,
  `ticker_ativo_18` tinyint(1) DEFAULT 0,
  `ticker_ativo_19` tinyint(1) DEFAULT 0,
  `ticker_ativo_20` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configurar_textos`
--

INSERT INTO `configurar_textos` (`id`, `link_suporte`, `pop_up`, `anuncio`, `titulo_site`, `descricao_site`, `keywords_site`, `link_site`, `popup_titulo`, `popup_imagem`, `popup_botao_texto`, `popup_ativo`, `popup_delay`, `ticker_msg_1`, `ticker_msg_2`, `ticker_msg_3`, `ticker_msg_4`, `ticker_msg_5`, `ticker_msg_6`, `ticker_msg_7`, `ticker_msg_8`, `ticker_msg_9`, `ticker_msg_10`, `ticker_msg_11`, `ticker_msg_12`, `ticker_msg_13`, `ticker_msg_14`, `ticker_msg_15`, `ticker_msg_16`, `ticker_msg_17`, `ticker_msg_18`, `ticker_msg_19`, `ticker_msg_20`, `ticker_icon_1`, `ticker_icon_2`, `ticker_icon_3`, `ticker_icon_4`, `ticker_icon_5`, `ticker_icon_6`, `ticker_icon_7`, `ticker_icon_8`, `ticker_icon_9`, `ticker_icon_10`, `ticker_icon_11`, `ticker_icon_12`, `ticker_icon_13`, `ticker_icon_14`, `ticker_icon_15`, `ticker_icon_16`, `ticker_icon_17`, `ticker_icon_18`, `ticker_icon_19`, `ticker_icon_20`, `ticker_ativo_1`, `ticker_ativo_2`, `ticker_ativo_3`, `ticker_ativo_4`, `ticker_ativo_5`, `ticker_ativo_6`, `ticker_ativo_7`, `ticker_ativo_8`, `ticker_ativo_9`, `ticker_ativo_10`, `ticker_ativo_11`, `ticker_ativo_12`, `ticker_ativo_13`, `ticker_ativo_14`, `ticker_ativo_15`, `ticker_ativo_16`, `ticker_ativo_17`, `ticker_ativo_18`, `ticker_ativo_19`, `ticker_ativo_20`) VALUES
(1, 'https://t.me/finverpro', 'Robôs com desconto hoje!', 'Convide amigos e receba salários mensais!', 'Finver Pro', 'Mude sua vida financeira: deixe nossa IA investir por você e conquiste a liberdade', 'investimentos com inteligência artificial, robô de investimento automático, renda passiva online, investir com IA, plataforma de investimentos Brasil, ganhar dinheiro investindo, investimento automatizado, robôs traders, lucros com IA, renda extra mensal', 'https://finverpro.shop', 'Notificação', 'icon.svg', 'Fechar', 1, 3000, 'Tecnologia que gera renda no piloto automático', 'Maria Costa já lucrou R$ 5.890 este mês', '+1.000 novos investidores toda semana', 'Sua independência financeira começa aqui!', 'Recuperei meu dinheiro em 3 semanas\" - Ana', 'Inteligência artificial = lucros reais', 'Resultados surpreendentes desde o 1º dia', 'Mais de R$ 150.000 pagos hoje', '+3.500 robôs IA trabalhando agora', '+2.847 investidores ativos na plataforma', 'R$ 50 milhões movimentados este ano', 'AVISO: Sistema operando com 99.7% de eficiência', 'TRENDING: Mais de 2.8k pessoas compartilharam', '\'Nunca vi nada igual, fantástico!\' - Larissa B.', '\'Melhor plataforma que já usei!\' - Carolina M.', '+670 pessoas investindo neste momento', '+2.300 novos membros se juntaram esta semana', 'Rafael Pereira: +R$ 2.890 em lucros passivos', 'Beatriz Lima retirou R$ 4.950 - Aprovado na hora!', 'Plataforma #1 em rentabilidade no Brasil', 'fas fa-crown', 'fas fa-money-bill-wave', 'fas fa-chart-line', 'fas fa-crown', 'fas fa-users', 'fas fa-chart-line', 'fas fa-users', 'fas fa-gem', 'fas fa-gem', 'fas fa-users', 'fas fa-money-bill-wave', 'fas fa-crown', 'fas fa-chart-line', 'fas fa-users', 'fas fa-users', 'fas fa-fire', 'fas fa-users', 'fas fa-rocket', 'fas fa-crown', 'fas fa-fire', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_saques`
--

CREATE TABLE `config_saques` (
  `id` int(11) NOT NULL,
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
  `atualizado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `config_saques`
--

INSERT INTO `config_saques` (`id`, `valor_minimo`, `valor_maximo`, `taxa_percentual`, `taxa_fixa`, `limite_diario`, `limite_semanal`, `limite_mensal`, `horario_inicio`, `horario_fim`, `segunda_feira`, `terca_feira`, `quarta_feira`, `quinta_feira`, `sexta_feira`, `sabado`, `domingo`, `requer_investimento_ativo`, `quantidade_min_investimentos`, `requer_chave_pix`, `tempo_processamento_min`, `tempo_processamento_max`, `mensagem_sucesso`, `mensagem_fora_horario`, `mensagem_limite_diario`, `mensagem_sem_investimento`, `mensagem_saldo_insuficiente`, `calculo_taxa`, `aplicar_taxa_sobre`, `arredondar_centavos`, `permitir_mesmo_dia_deposito`, `considerar_feriados`, `bloquear_dezembro`, `bloquear_janeiro`, `ativo`, `criado_em`, `atualizado_em`, `criado_por`, `atualizado_por`) VALUES
(1, 37.00, 10000.00, 9.00, 0.00, 1, NULL, NULL, '08:00:00', '18:00:00', 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 2, 72, 'Saque realizado com sucesso!', 'Saques só podem ser realizados de segunda a sexta, das 9h às 18h.', 'Você já realizou um saque hoje. Limite de 1 saque por dia.', 'Você precisa ter pelo menos 1 investimento ativo antes de solicitar um saque.', 'Seu saldo é insuficiente para saque.', 'percentual', 'valor_bruto', 1, 1, 1, 0, 0, 1, '2025-06-22 07:47:22', '2025-06-28 13:57:50', NULL, 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `config_saques_historico`
--

CREATE TABLE `config_saques_historico` (
  `id` int(11) NOT NULL,
  `config_id` int(11) NOT NULL,
  `campo_alterado` varchar(100) NOT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_novo` text DEFAULT NULL,
  `alterado_por` int(11) NOT NULL,
  `alterado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gateway`
--

CREATE TABLE `gateway` (
  `id` int(11) NOT NULL,
  `client_id` varchar(500) DEFAULT NULL,
  `client_secret` varchar(1000) DEFAULT NULL,
  `status` enum('true','false') DEFAULT NULL,
  `banco` enum('SuitPay','VenturePay','PixUP','BSPay','SYNCPAY','FIVEPAY') DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `gateway`
--

INSERT INTO `gateway` (`id`, `client_id`, `client_secret`, `status`, `banco`, `webhook_url`) VALUES
(1, '', '', 'false', 'SYNCPAY', ''),
(2, '', '', 'false', 'FIVEPAY', NULL),
(3, '', '', 'false', 'SuitPay', NULL),
(4, 'Fernanda2025_6643147463', 'e6edc8505369ecc0010bad2321139d5582afafdd744a398dec448a31e3fce3fb', 'true', 'PixUP', 'https://finverpro.shop/gate/webhook.php'),
(5, '', '', 'false', 'VenturePay', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_rendimentos`
--

CREATE TABLE `historico_rendimentos` (
  `id` int(11) NOT NULL,
  `investimento_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `valor_rendimento` decimal(10,2) NOT NULL,
  `data_rendimento` date NOT NULL,
  `processado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_transacoes`
--

CREATE TABLE `historico_transacoes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tipo` enum('deposito','saque','investimento','rendimento','comissao') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('pendente','concluido','cancelado') DEFAULT 'pendente',
  `data_transacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `historico_transacoes`
--

INSERT INTO `historico_transacoes` (`id`, `user_id`, `tipo`, `valor`, `descricao`, `status`, `data_transacao`) VALUES
(1, 2, 'investimento', 100.00, 'Investimento em Plano Básico', 'concluido', '2025-06-12 23:36:10'),
(2, 2, 'investimento', 100.00, 'Investimento em Plano Básico', 'concluido', '2025-06-12 23:37:17'),
(3, 2, 'investimento', 100.00, 'Investimento em testee', 'concluido', '2025-06-13 18:29:28'),
(4, 2, 'investimento', 20.00, 'Investimento em Hsbabb', 'concluido', '2025-06-13 18:39:20'),
(5, 2, 'investimento', 20.00, 'Investimento em Hsbabb', 'concluido', '2025-06-13 18:39:39'),
(6, 2, 'investimento', 20.00, 'Investimento em Hsbabb', 'concluido', '2025-06-13 22:02:36'),
(7, 2, 'investimento', 200.00, 'Investimento em Robô EX', 'concluido', '2025-06-20 15:32:09'),
(8, 2, 'comissao', 5900.00, 'Transferência de comissões para carteira principal', 'concluido', '2025-06-20 22:09:11'),
(9, 2, 'comissao', 20.00, 'Comissão nível 1 - Referido ID: 11', 'concluido', '2025-06-22 16:00:47'),
(10, 11, 'investimento', 200.00, 'Investimento em Robô EX', 'concluido', '2025-06-22 16:00:47'),
(11, 2, 'comissao', 40.00, 'Transferência de comissões para carteira principal', 'concluido', '2025-06-22 16:02:00'),
(12, 2, 'rendimento', 5.00, 'Rendimento de 1 dias (creditado 1 dia após) - Robô EX', 'concluido', '2025-06-22 16:13:22'),
(13, 2, 'rendimento', 25.00, 'Rendimento de 5 dias (creditado 1 dia após) - Robô EX', 'concluido', '2025-06-27 00:12:41'),
(14, 2, 'investimento', 50.00, 'Investimento em Robô EX', 'concluido', '2025-06-27 00:17:36'),
(15, 2, 'investimento', 200.00, 'Investimento em Ia inteligentes', 'concluido', '2025-06-27 00:25:44'),
(16, 2, 'investimento', 350.00, 'Investimento em Ia inteligentes', 'concluido', '2025-06-27 00:25:49'),
(17, 2, 'investimento', 500.00, 'Investimento em Trader', 'concluido', '2025-06-27 00:25:53'),
(18, 2, 'investimento', 750.00, 'Investimento em Trazer', 'concluido', '2025-06-27 00:25:56'),
(19, 2, 'rendimento', 25.00, 'Prêmio da Roleta da Sorte', 'concluido', '2025-06-27 11:50:32'),
(20, 2, 'deposito', 30.00, 'Depósito via PIX - Transaction ID: e4e6ea6d062352ad0ebemcfgzlmh5thv', 'concluido', '2025-06-27 23:56:32'),
(21, 2, 'deposito', 30.00, 'Depósito via PIX - Transaction ID: 0a1c5e5eb1b867f96f21mcfiz8io3kux', 'concluido', '2025-06-28 00:51:53'),
(22, 2, 'saque', 9.10, 'Saque PIX processado com sucesso - ID: SAQUE_4_1751111246', '', '2025-06-28 11:47:27'),
(23, 2, 'rendimento', 5.00, 'Rendimento de 1 dias (creditado 1 dia após) - Robô EX', 'concluido', '2025-06-28 13:48:14'),
(24, 2, 'comissao', 20.00, 'Comissão nível 1 - Referido ID: 11', 'concluido', '2025-06-28 16:49:34'),
(25, 11, 'investimento', 200.00, 'Investimento em Ia inteligentes', 'concluido', '2025-06-28 16:49:34'),
(26, 11, 'rendimento', 25.00, 'Rendimento de 5 dias (creditado 1 dia após) - Robô EX', 'concluido', '2025-06-28 16:51:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `indicacoes`
--

CREATE TABLE `indicacoes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `indicado_id` int(11) NOT NULL,
  `data_indicacao` timestamp NULL DEFAULT current_timestamp(),
  `bonus` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `indicacoes`
--

INSERT INTO `indicacoes` (`id`, `user_id`, `indicado_id`, `data_indicacao`, `bonus`) VALUES
(1, 2, 11, '2025-06-22 13:50:29', 0.00),
(2, 2, 12, '2025-06-22 13:53:01', 0.00),
(3, 11, 14, '2025-06-28 17:08:11', 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `investidores`
--

CREATE TABLE `investidores` (
  `id` int(11) NOT NULL,
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
  `ciclo_rendimento` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `investimentos`
--

CREATE TABLE `investimentos` (
  `id` int(11) NOT NULL,
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
  `valor_final` decimal(10,2) DEFAULT 0.00 COMMENT 'Cópia do valor_final do produto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `investimentos`
--

INSERT INTO `investimentos` (`id`, `usuario_id`, `produto_id`, `valor_investido`, `renda_diaria`, `renda_total`, `dias_restantes`, `data_investimento`, `data_vencimento`, `status`, `ultimo_rendimento`, `tipo_rendimento`, `valor_final`) VALUES
(1, 2, 11, 20.00, 11.00, 0.00, 30, '2025-06-13 18:39:20', '2025-07-13', 'ativo', NULL, 'final', 0.00),
(2, 2, 11, 20.00, 11.00, 0.00, 30, '2025-06-13 18:39:39', '2025-07-13', 'ativo', NULL, 'final', 0.00),
(3, 2, 11, 20.00, 11.00, 0.00, 30, '2025-06-13 22:02:36', '2025-07-13', 'ativo', NULL, 'final', 0.00),
(4, 2, 13, 200.00, 5.00, 35.00, 3, '2025-06-20 15:32:09', '2025-06-30', 'ativo', '2025-06-27', 'diario', 0.00),
(5, 11, 13, 200.00, 5.00, 25.00, 5, '2025-06-22 16:00:47', '2025-07-02', 'ativo', '2025-06-27', 'diario', 0.00),
(6, 2, 14, 50.00, 5.00, 0.00, 3, '2025-06-27 00:17:36', '2025-06-30', 'ativo', NULL, 'diario', 0.00),
(7, 2, 15, 200.00, 15.00, 0.00, 20, '2025-06-27 00:25:44', '2025-07-17', 'ativo', NULL, 'diario', 0.00),
(8, 2, 16, 350.00, 23.00, 0.00, 20, '2025-06-27 00:25:49', '2025-07-17', 'ativo', NULL, 'diario', 0.00),
(9, 2, 17, 500.00, 56.00, 0.00, 30, '2025-06-27 00:25:53', '2025-07-27', 'ativo', NULL, 'diario', 0.00),
(10, 2, 18, 750.00, 60.00, 0.00, 31, '2025-06-27 00:25:56', '2025-07-28', 'ativo', NULL, 'final', 0.00),
(11, 11, 15, 200.00, 15.00, 0.00, 20, '2025-06-28 16:49:34', '2025-07-18', 'ativo', NULL, 'diario', 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `investimentos_usuarios`
--

CREATE TABLE `investimentos_usuarios` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `valor_investido` decimal(10,2) NOT NULL,
  `data_investimento` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip`, `created_at`) VALUES
(1, '127.0.0.1', '2025-06-22 14:39:17'),
(2, '127.0.0.1', '2025-06-22 14:41:28'),
(3, '127.0.0.1', '2025-06-22 14:44:18'),
(4, '127.0.0.1', '2025-06-22 14:45:12'),
(5, '177.222.236.49', '2025-06-28 19:04:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `niveis`
--

CREATE TABLE `niveis` (
  `nivel` int(11) DEFAULT NULL,
  `percentual_comissao` int(11) DEFAULT NULL,
  `valor_minimo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `niveis`
--

INSERT INTO `niveis` (`nivel`, `percentual_comissao`, `valor_minimo`) VALUES
(1, 1, 80),
(2, 2, 200),
(3, 25, 500);

-- --------------------------------------------------------

--
-- Estrutura para tabela `niveis_convite`
--

CREATE TABLE `niveis_convite` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nivel_1` int(11) DEFAULT 0,
  `total_nivel1` decimal(10,2) DEFAULT 0.00,
  `nivel_2` int(11) DEFAULT 0,
  `total_nivel2` decimal(10,2) DEFAULT 0.00,
  `nivel_3` int(11) DEFAULT 0,
  `total_nivel3` decimal(10,2) DEFAULT 0.00,
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `niveis_convite`
--

INSERT INTO `niveis_convite` (`id`, `user_id`, `nivel_1`, `total_nivel1`, `nivel_2`, `total_nivel2`, `nivel_3`, `total_nivel3`, `data_atualizacao`) VALUES
(1, 1, 0, 0.00, 0, 0.00, 0, 0.00, '2025-06-08 21:43:41'),
(2, 2, 5, 20.00, 0, 0.00, 0, 0.00, '2025-06-28 16:49:34'),
(3, 2, 5, 20.00, 0, 0.00, 0, 0.00, '2025-06-28 16:49:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `niveis_vip`
--

CREATE TABLE `niveis_vip` (
  `id` int(11) NOT NULL,
  `nivel` varchar(10) NOT NULL,
  `nome` varchar(50) DEFAULT NULL,
  `salario_padrao` decimal(10,2) DEFAULT NULL,
  `cor_badge` varchar(7) DEFAULT NULL,
  `icone` varchar(20) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `niveis_vip`
--

INSERT INTO `niveis_vip` (`id`, `nivel`, `nome`, `salario_padrao`, `cor_badge`, `icone`, `emoji`) VALUES
(1, 'V0', 'Iniciante', 0.00, '#6B7280', 'fa-user', '👤'),
(2, 'V1', 'Início da Jornada', 5000.00, '#FFD700', 'fa-medal', '🥇'),
(3, 'V2', 'Influência em Expansão', 15000.00, '#C0C0C0', 'fa-trophy', '🥈'),
(4, 'V3', 'Liderança em Formação', 30000.00, '#CD7F32', 'fa-crown', '🥉'),
(5, 'V4', 'Líder Regional', 50000.00, '#E5E4E2', 'fa-gem', '🏆'),
(6, 'V5', 'Embaixador do Projeto', 100000.00, '#B9F2FF', 'fa-diamond', '👑');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nome` varchar(50) DEFAULT NULL,
  `cpf` varchar(50) DEFAULT NULL,
  `numero_telefone` varchar(15) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `cod_referencia` varchar(50) DEFAULT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') NOT NULL,
  `data` datetime DEFAULT current_timestamp(),
  `Banco` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pagamentos`
--

INSERT INTO `pagamentos` (`id`, `user_id`, `nome`, `cpf`, `numero_telefone`, `valor`, `cod_referencia`, `status`, `data`, `Banco`) VALUES
(1, 2, NULL, NULL, '5565996498222', 50.00, '5dd9cf37b865dff6e543mbwv57i8z4vm', 'Aprovado', '2025-06-14 20:24:03', 'PIXUP'),
(6, 2, NULL, NULL, '5565996498222', 50.00, 'f914201c1b05dafae6b6mbxlzi2k5qa8', 'Aprovado', '2025-06-15 08:55:27', 'PIXUP'),
(7, 2, NULL, NULL, '5565996498222', 50.00, 'b2e70476e4101fcfc04bmbxm7zov3qw6', 'Aprovado', '2025-06-15 09:02:03', 'PIXUP'),
(8, 2, NULL, NULL, '5565996498222', 30.00, '2b9b81b7527bc36e02f6mbxogof93u0i', 'Pendente', '2025-06-15 10:04:47', 'PIXUP'),
(9, 2, NULL, NULL, '5565996498222', 30.00, '3fc0ebce00e4778ba7acmbxonvi51ncu', 'Pendente', '2025-06-15 10:10:23', 'PIXUP'),
(10, 2, NULL, NULL, '5565996498222', 30.00, 'd45fee66ad9b9ba6066ambxp6chc4yyz', 'Aprovado', '2025-06-15 10:24:45', 'PIXUP'),
(11, 2, NULL, NULL, '5565996498222', 50.00, '25e885263fed9446376cmbz4exa45gcy', 'Pendente', '2025-06-16 10:19:04', 'PIXUP'),
(12, 2, NULL, NULL, '5565996498222', 50.00, 'bce354432abcfb715bf2mbzc0c1z10vw', 'Pendente', '2025-06-16 13:51:41', 'PIXUP'),
(13, 2, NULL, NULL, '5565996498222', 50.00, '7208d2899d19f137bdc9mbzoiobc2vey', 'Pendente', '2025-06-16 19:41:52', 'PIXUP'),
(14, 2, NULL, NULL, '5565996498222', 30.00, '1df121174d47eefc16f4mc3o9jsq1vjp', 'Pendente', '2025-06-19 14:45:50', 'PIXUP'),
(15, 2, NULL, NULL, '5565996498222', 30.00, '7b98b4066c2241913856mc3ox96u49nw', 'Pendente', '2025-06-19 15:04:17', 'PIXUP'),
(16, 2, NULL, NULL, '5565996498222', 30.00, '503b074f45499dc2b15cmc3p5mv91t77', 'Pendente', '2025-06-19 15:10:48', 'PIXUP'),
(17, 2, NULL, NULL, '5565996498222', 30.00, '4a2adf6ffbd99744cbfdmc3qihq24bx7', 'Pendente', '2025-06-19 15:48:48', 'PIXUP'),
(18, 2, NULL, NULL, '5565996498222', 30.00, '0ef7a8bc11b3bbc2e9f0mcdzucsk2icg', 'Pendente', '2025-06-26 20:07:39', 'PIXUP'),
(19, 2, NULL, NULL, '5565996498222', 30.00, '58e3d430cadedec8080fmce0ea9auhh3', 'Pendente', '2025-06-26 20:23:09', 'PIXUP'),
(20, 2, NULL, NULL, '5565996498222', 30.00, '6f21b987fa9971d240d4mce0gs9w5ooa', 'Pendente', '2025-06-26 20:25:06', 'PIXUP'),
(21, 2, NULL, NULL, '5565996498222', 30.00, '960a3e836c9b4208ebecmce0ktx710g7', 'Pendente', '2025-06-26 20:28:15', 'PIXUP'),
(22, 2, NULL, NULL, '5565996498222', 30.00, '2f0ba96e1bdaa85c13bfmcerhubd4gfw', 'Pendente', '2025-06-27 09:01:46', 'PIXUP'),
(23, 2, NULL, NULL, '5565996498222', 30.00, '87921949b68a7c533546mcfgjm302s0b', 'Pendente', '2025-06-27 20:42:59', 'PIXUP'),
(24, 2, NULL, NULL, '5565996498222', 30.00, 'e4e6ea6d062352ad0ebemcfgzlmh5thv', 'Aprovado', '2025-06-27 20:56:31', 'PIXUP'),
(25, 2, NULL, NULL, '5565996498222', 30.00, '506481e11da20920d8demcfiw41o4gk5', 'Pendente', '2025-06-27 21:48:41', 'PIXUP'),
(26, 2, NULL, NULL, '5565996498222', 30.00, '0a1c5e5eb1b867f96f21mcfiz8io3kux', 'Aprovado', '2025-06-27 21:51:52', 'PIXUP'),
(27, 2, NULL, NULL, '5565996498222', 30.00, 'c94364fa18f7b2f13e58mcg8g5ze2hm9', 'Aprovado', '2025-06-28 09:44:07', 'PIXUP'),
(28, 2, NULL, NULL, '5565996498222', 30.00, '512b9355f074a4269cb5mcg8hicv2uc9', 'Aprovado', '2025-06-28 09:45:10', 'PIXUP');

-- --------------------------------------------------------

--
-- Estrutura para tabela `personalizar_cores`
--

CREATE TABLE `personalizar_cores` (
  `id` int(11) NOT NULL,
  `cor_1` varchar(7) DEFAULT NULL,
  `cor_2` varchar(7) DEFAULT NULL,
  `cor_3` varchar(7) DEFAULT NULL,
  `cor_4` varchar(7) DEFAULT NULL,
  `cor_5` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `personalizar_cores`
--

INSERT INTO `personalizar_cores` (`id`, `cor_1`, `cor_2`, `cor_3`, `cor_4`, `cor_5`) VALUES
(1, '#121A1E', 'white', '#152731', '#335D67', '#152731'),
(2, '#2e2e2e', '#FFFFFF', '#6b6b6b', '#303536', '#4a4a4a');

-- --------------------------------------------------------

--
-- Estrutura para tabela `personalizar_imagens`
--

CREATE TABLE `personalizar_imagens` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `personalizar_imagens`
--

INSERT INTO `personalizar_imagens` (`id`, `logo`, `tela_pix`, `tela_retirada`, `tela_login`, `inicio`, `tela_avatar`, `tela_bonus`, `tela_perfil`, `checklist_image`, `created_at`, `updated_at`) VALUES
(1, '1000381241_685fedde15612_1751117278.jpg', '1000392159_685fe916dfeac_1751116054.png', 'bonus___1_-removebg-preview_6854775c29be9_1750366044.png', 'BOASVINDAS_6854775bf0c96_1750366043.png', 'carteira2_6854775c056d3_1750366044.png', 'avatar-2_6854775c181ce_1750366044.jpg', '1000381244_685fe7aa308ad_1751115690.png', '3135768_6854775c103c2_1750366044.png', '1000381245_685fe7aa30af6_1751115690.png', '2025-06-14 12:02:06', '2025-06-28 13:27:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
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
  `valor_final` decimal(10,2) DEFAULT 0.00 COMMENT 'Para produtos tipo final: valor único pago no fim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `titulo`, `descricao`, `foto`, `valor_investimento`, `renda_diaria`, `validade`, `receita_total`, `status`, `created_at`, `limite_compras`, `vendidos`, `robot_number`, `duracao_dias`, `limite_dias_venda`, `tipo_rendimento`, `data_atualizacao`, `data_criacao`, `valor_final`) VALUES
(11, 'Hsbabb', 'Hsabha\r\nDx\r\nD\r\n\r\nX\r\nX\r\nX\r\nX\r\nX\r\nX\r\nX', 'produto_1749839610_4525.jpg', 20.00, 11.00, 30, 11.00, 'arquivado', '2025-06-13 18:33:30', 300, 3, '157g', 30, 3, 'final', '2025-06-28 17:50:43', '2025-06-13 18:33:30', 11.00),
(13, 'Robô EX', 'Robô ex', 'produto_1750433481_5167.jpg', 200.00, 5.00, 11, 50.00, 'arquivado', '2025-06-20 15:31:21', 1, 2, 'V1', 10, 160, 'diario', '2025-06-27 00:12:23', '2025-06-20 15:31:21', 0.00),
(14, 'Robô EX', 'Invista com a FinverPro – Robô de Renda Automática\r\n\r\n???? Comece com apenas R$50\r\n???? Ganhe R$5 por dia\r\n???? Rendimento gerado por robôs de inteligência artificial\r\n\r\nNa FinverPro, você coloca seu dinheiro para trabalhar com tecnologia de ponta. Nosso robô de IA opera de forma inteligente e automática para gerar renda diária, com segurança e estabilidade.\r\n\r\n✅ Baixo investimento inicial\r\n✅ Rendimento fixo diário\r\n✅ Sem necessidade de experiência\r\n✅ Acompanhe tudo em tempo real\r\n\r\nFinverPro – sua renda, automatizada.', 'produto-default.jpg', 50.00, 5.00, 3, 15.00, 'ativo', '2025-06-27 00:17:09', 1, 1, 'R64', 3, NULL, 'diario', '2025-06-27 00:17:36', '2025-06-27 00:17:09', 0.00),
(15, 'Ia inteligentes', '', 'produto-default.jpg', 200.00, 15.00, 15, 300.00, 'ativo', '2025-06-27 00:20:11', 1, 2, 'A1', 20, 23, 'diario', '2025-06-28 16:49:34', '2025-06-27 00:20:11', 0.00),
(16, 'Ia inteligentes', '', 'produto-default.jpg', 350.00, 23.00, 20, 460.00, 'ativo', '2025-06-27 00:22:36', 1, 1, 'A2', 20, 25, 'diario', '2025-06-27 00:25:49', '2025-06-27 00:22:36', 0.00),
(17, 'Trader', '', 'produto-default.jpg', 500.00, 56.00, 30, 1680.00, 'ativo', '2025-06-27 00:23:52', 1, 1, 'R34', 30, 50, 'diario', '2025-06-27 00:25:53', '2025-06-27 00:23:52', 0.00),
(18, 'Trazer', '', 'produto-default.jpg', 750.00, 60.00, 30, 60.00, 'ativo', '2025-06-27 00:25:27', 1, 1, 'R35', 31, 50, 'final', '2025-06-28 17:50:43', '2025-06-27 00:25:27', 60.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `roleta`
--

CREATE TABLE `roleta` (
  `id` int(11) NOT NULL,
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
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `roleta`
--

INSERT INTO `roleta` (`id`, `premio_1_nome`, `premio_1_tipo`, `premio_1_valor`, `premio_1_imagem`, `premio_1_cor`, `premio_1_chance`, `premio_2_nome`, `premio_2_tipo`, `premio_2_valor`, `premio_2_imagem`, `premio_2_cor`, `premio_2_chance`, `premio_3_nome`, `premio_3_tipo`, `premio_3_valor`, `premio_3_imagem`, `premio_3_cor`, `premio_3_chance`, `premio_4_nome`, `premio_4_tipo`, `premio_4_valor`, `premio_4_imagem`, `premio_4_cor`, `premio_4_chance`, `premio_5_nome`, `premio_5_tipo`, `premio_5_valor`, `premio_5_imagem`, `premio_5_cor`, `premio_5_chance`, `premio_6_nome`, `premio_6_tipo`, `premio_6_valor`, `premio_6_imagem`, `premio_6_cor`, `premio_6_chance`, `premio_7_nome`, `premio_7_tipo`, `premio_7_valor`, `premio_7_imagem`, `premio_7_cor`, `premio_7_chance`, `premio_8_nome`, `premio_8_tipo`, `premio_8_valor`, `premio_8_imagem`, `premio_8_cor`, `premio_8_chance`, `valor_minimo_investimento`, `giros_por_investimento`, `giros_por_indicacao`, `limite_giros_dia`, `roleta_ativa`, `duracao_animacao`, `som_ativo`, `data_atualizacao`) VALUES
(1, 'iPhone 15 Pro', 'produto', NULL, NULL, '#ff6b6b', 0.00, 'R$ 50', 'dinheiro', 50.00, NULL, '#4ecdc4', 17.00, 'Que pena!', 'nada', NULL, NULL, '#95a5a6', 25.00, 'R$ 100', 'dinheiro', 100.00, NULL, '#45b7d1', 5.00, 'AirPods Pro', 'produto', NULL, NULL, '#9b59b6', 0.00, 'R$ 25', 'dinheiro', 25.00, NULL, '#f39c12', 20.00, 'R$ 5', 'dinheiro', 5.00, NULL, '#e74c3c', 33.00, 'R$ 200', 'dinheiro', 200.00, NULL, '#27ae60', 0.00, 200.00, 1, 1, 15, 1, 3000, 1, '2025-06-28 14:00:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `roleta_giros_usuario`
--

CREATE TABLE `roleta_giros_usuario` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `giros_disponiveis` int(11) NOT NULL DEFAULT 0,
  `ultimo_giro` timestamp NULL DEFAULT NULL,
  `giros_hoje` int(11) DEFAULT 0,
  `data_reset_diario` date DEFAULT NULL,
  `total_giros_historico` int(11) DEFAULT 0,
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `roleta_giros_usuario`
--

INSERT INTO `roleta_giros_usuario` (`id`, `usuario_id`, `giros_disponiveis`, `ultimo_giro`, `giros_hoje`, `data_reset_diario`, `total_giros_historico`, `data_atualizacao`) VALUES
(1, 2, 1, '2025-06-27 11:50:32', 0, '2025-06-28', 13, '2025-06-28 16:49:34'),
(2, 11, 2, NULL, 0, '2025-06-22', 2, '2025-06-28 16:49:34'),
(3, 13, 0, NULL, 0, '2025-06-27', 0, '2025-06-27 15:05:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `roleta_historico`
--

CREATE TABLE `roleta_historico` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `premio_numero` int(11) NOT NULL,
  `premio_nome` varchar(100) NOT NULL,
  `premio_tipo` enum('dinheiro','produto','nada') NOT NULL,
  `premio_valor` decimal(10,2) DEFAULT 0.00,
  `origem_giro` enum('investimento','indicacao','bonus','manual') NOT NULL,
  `valor_investimento` decimal(10,2) DEFAULT NULL,
  `indicado_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `data_giro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `roleta_historico`
--

INSERT INTO `roleta_historico` (`id`, `usuario_id`, `premio_numero`, `premio_nome`, `premio_tipo`, `premio_valor`, `origem_giro`, `valor_investimento`, `indicado_id`, `ip_address`, `data_giro`) VALUES
(1, 2, 3, 'Que pena!', 'nada', NULL, 'manual', NULL, NULL, '127.0.0.1', '2025-06-21 00:21:52'),
(2, 2, 2, 'R$ 50', 'dinheiro', 50.00, 'manual', NULL, NULL, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-26 23:13:26'),
(3, 2, 2, 'R$ 50', 'dinheiro', 50.00, 'manual', NULL, NULL, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-27 11:25:58'),
(4, 2, 3, 'Que pena!', 'nada', NULL, 'manual', NULL, NULL, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-27 11:40:51'),
(5, 2, 2, 'R$ 50', 'dinheiro', 50.00, 'manual', NULL, NULL, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-27 11:41:02'),
(6, 2, 6, 'R$ 25', 'dinheiro', 25.00, 'manual', NULL, NULL, '2804:15a4:8037:9a00:6d53:708c:ec86:6766', '2025-06-27 11:50:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `salary_levels`
--

CREATE TABLE `salary_levels` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `salary_levels`
--

INSERT INTO `salary_levels` (`id`, `level_code`, `level_name`, `level_description`, `min_people`, `min_team_value`, `monthly_salary`, `icon`, `color`, `is_default`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'INICIANTE', 'Iniciante Padrão', '', 0, 0.00, 0.00, '', '#808080', 1, 1, 0, '2025-06-17 03:02:32', '2025-06-22 16:27:33'),
(6, 'V1', 'Gerente', '', 10, 3000.00, 300.00, '', '#10b981', 0, 1, 2, '2025-06-27 00:41:12', '2025-06-27 00:45:17'),
(7, 'V2', 'Gerente', '', 20, 7000.00, 800.00, '', '#10b981', 0, 1, 3, '2025-06-27 00:42:12', '2025-06-27 00:46:07'),
(8, 'V3', 'Gerente', '', 50, 100000.00, 3000.00, '', '#10b981', 0, 1, 4, '2025-06-27 00:47:22', '2025-06-27 00:47:22'),
(9, 'Z4', 'Executivo ', '', 100, 200000.00, 10000.00, '', '#10b981', 0, 1, 5, '2025-06-27 00:48:39', '2025-06-27 00:48:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `salary_payments`
--

CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `level_id` int(11) NOT NULL,
  `level_code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('disponivel','transferido') DEFAULT 'disponivel',
  `release_date` timestamp NULL DEFAULT current_timestamp(),
  `transfer_date` timestamp NULL DEFAULT NULL,
  `released_by` int(11) NOT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `salary_payments`
--

INSERT INTO `salary_payments` (`id`, `user_id`, `request_id`, `level_id`, `level_code`, `amount`, `status`, `release_date`, `transfer_date`, `released_by`, `admin_notes`) VALUES
(1, 2, 1, 2, 'BRONZE', 150.00, 'transferido', '2025-06-18 00:06:27', '2025-06-18 00:06:54', 2, ''),
(2, 2, 2, 2, 'BRONZE', 150.00, 'transferido', '2025-06-18 11:58:33', '2025-06-18 23:10:07', 2, '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `salary_requests`
--

CREATE TABLE `salary_requests` (
  `id` int(11) NOT NULL,
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
  `user_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `salary_requests`
--

INSERT INTO `salary_requests` (`id`, `user_id`, `level_id`, `level_code`, `requested_amount`, `current_team_people`, `current_team_value`, `status`, `request_date`, `response_date`, `admin_id`, `admin_notes`, `user_message`) VALUES
(1, 2, 2, 'BRONZE', 150.00, 5, 2950.00, 'aprovado', '2025-06-18 00:03:41', '2025-06-18 00:06:27', 2, '', ''),
(2, 2, 2, 'BRONZE', 150.00, 5, 2950.00, 'aprovado', '2025-06-18 00:07:22', '2025-06-18 11:58:33', 2, '', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `saques`
--

CREATE TABLE `saques` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tipo_pix` enum('cpf','celular','email','chave-aleatoria') NOT NULL,
  `chave_pix` varchar(255) NOT NULL,
  `nome_titular` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `data` timestamp NULL DEFAULT current_timestamp(),
  `numero_telefone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `saques`
--

INSERT INTO `saques` (`id`, `user_id`, `tipo_pix`, `chave_pix`, `nome_titular`, `valor`, `status`, `data`, `numero_telefone`, `created_at`, `updated_at`) VALUES
(3, 2, 'cpf', '704.944.371-97', 'Kauan domingos hup', 92.00, 'Pendente', '2025-06-16 13:00:35', '5565996498222', '2025-06-16 13:00:35', '2025-06-16 13:00:35'),
(4, 2, 'cpf', '704.944.371-97', 'Kauan domingos hup', 9.10, 'Aprovado', '2025-06-27 12:08:52', '5565996498222', '2025-06-27 12:08:52', '2025-06-28 11:47:27');

-- --------------------------------------------------------

--
-- Estrutura para tabela `saques_comissao`
--

CREATE TABLE `saques_comissao` (
  `id` int(11) NOT NULL,
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
  `nome_titular` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `seo_analysis`
--

CREATE TABLE `seo_analysis` (
  `id` int(11) NOT NULL,
  `analysis_date` date NOT NULL,
  `title_score` int(11) DEFAULT 0,
  `description_score` int(11) DEFAULT 0,
  `keywords_score` int(11) DEFAULT 0,
  `overall_score` int(11) DEFAULT 0,
  `suggestions` longtext DEFAULT NULL,
  `performance_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `seo_history`
--

CREATE TABLE `seo_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `change_reason` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sms_codes`
--

CREATE TABLE `sms_codes` (
  `id` int(11) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `codigo` varchar(6) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transacoes`
--

CREATE TABLE `transacoes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pendente',
  `data_transacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transacoes`
--

INSERT INTO `transacoes` (`id`, `user_id`, `tipo`, `valor`, `descricao`, `status`, `data_transacao`) VALUES
(1, 12, 'bonus', 6.00, 'Bônus de boas-vindas por cadastro', 'aprovado', '2025-06-22 13:53:01'),
(2, 13, 'bonus', 6.00, 'Bônus de boas-vindas por cadastro', 'aprovado', '2025-06-27 15:05:17'),
(3, 14, 'bonus', 6.00, 'Bônus de boas-vindas por cadastro', 'aprovado', '2025-06-28 17:08:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
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
  `total_indicacoes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `telefone`, `senha`, `saldo`, `nome`, `email`, `valor_investimento`, `codigo_referencia`, `nivel_vip_id`, `salario_total`, `referenciado_por`, `referenciador_id`, `data_criacao`, `valor_deposito`, `referencia_convite`, `cargo`, `data_cadastro`, `saldo_comissao`, `checklist`, `data_checklist`, `foto_perfil`, `total_indicacoes`) VALUES
(1, '5511999999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 100.00, '', '', 0.00, 'REF000001', 1, 0.00, NULL, NULL, '2025-06-13 11:48:39', 0.00, NULL, 'usuario', '2025-06-13 19:00:55', 0.00, 0, NULL, NULL, 0),
(2, '5565996498222', '$2y$12$dLa2AANI2aB6ZuoY6JDYzu8nP1vNPdP/.UmiUx5IVRbypIPdgk4qy', 5133.15, '', '', 0.00, 'REF000002', 1, 0.00, NULL, NULL, '2025-06-13 11:48:39', 0.00, NULL, 'admin', '2025-06-13 19:00:55', 0.00, 4, '2025-06-28', 'user_2_1751118531_685ff2c33ae30.jpg', 2),
(3, '11999887766', NULL, 0.00, '', '', 500.00, NULL, 0, 0.00, 2, NULL, '2025-06-17 23:55:26', 0.00, NULL, 'usuario', '2025-06-17 23:55:26', 0.00, 0, NULL, NULL, 0),
(4, '11999887767', NULL, 0.00, '', '', 300.00, NULL, 0, 0.00, 2, NULL, '2025-06-17 23:55:26', 0.00, NULL, 'usuario', '2025-06-17 23:55:26', 0.00, 0, NULL, NULL, 0),
(5, '11999887768', NULL, 0.00, '', '', 800.00, NULL, 0, 0.00, 2, NULL, '2025-06-17 23:55:26', 0.00, NULL, 'usuario', '2025-06-17 23:55:26', 0.00, 0, NULL, NULL, 0),
(6, '11999887769', NULL, 0.00, '', '', 400.00, NULL, 0, 0.00, 2, NULL, '2025-06-17 23:55:26', 0.00, NULL, 'usuario', '2025-06-17 23:55:26', 0.00, 0, NULL, NULL, 0),
(7, '11999887770', NULL, 0.00, '', '', 600.00, NULL, 0, 0.00, 2, NULL, '2025-06-17 23:55:26', 0.00, NULL, 'usuario', '2025-06-17 23:55:26', 0.00, 0, NULL, NULL, 0),
(11, '5565996498221', '$2y$12$A3zFmebhePfo1VPuCReZ8uzlIMEjf6U.2Vchqm1R3/m6QrYcrKOI6', 25.00, 'Kauan', '', 0.00, '5A94C01E', 0, 0.00, 2, 2, '2025-06-22 13:50:29', 0.00, 'REF000002', 'usuario', '2025-06-22 13:50:29', 0.00, 0, NULL, 'user_11_1751130323_686020d3985de.jpg', 1),
(12, '5565996068451', '$2y$12$dAKyOi9jU2Rzl98s3VQkEez3PaaCExl7inRrNPuDe3wrih3c.sp5a', 400.00, 'Deuza', '', 0.00, 'AA4BB15D', 0, 0.00, 2, 2, '2025-06-22 13:53:01', 0.00, 'REF000002', 'usuario', '2025-06-22 13:53:01', 0.00, 0, NULL, NULL, 0),
(13, '+5511988888888', '$2y$10$cEKgNnzOyDv1szB8DKtsoucFf93zqWEAl5BCtBIbFNPw7Foesag2i', 6.25, 'Adminhu', '', 0.00, '0484B5EA', 0, 0.00, NULL, NULL, '2025-06-27 15:05:17', 0.00, NULL, 'usuario', '2025-06-27 15:05:17', 0.00, 1, '2025-06-27', NULL, 0),
(14, '+5565996498211', '$2y$10$9W9EcM5uro/uY2zhXXbVd.O8Ykfc8lBTbd19aGkMnvGq0OdBzAgL.', 6.00, '+55 65 99649-8222', '', 0.00, '0AE98446', 0, 0.00, 11, 11, '2025-06-28 17:08:11', 0.00, '5A94C01E', 'usuario', '2025-06-28 17:08:11', 0.00, 0, NULL, NULL, 0);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `bonus_codigos`
--
ALTER TABLE `bonus_codigos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `captcha_sessions`
--
ALTER TABLE `captcha_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `created_at` (`created_at`);

--
-- Índices de tabela `carteira`
--
ALTER TABLE `carteira`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `chaves_pix`
--
ALTER TABLE `chaves_pix`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `checklist`
--
ALTER TABLE `checklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `comissoes`
--
ALTER TABLE `comissoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `referido_id` (`referido_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `configuracao_comissoes`
--
ALTER TABLE `configuracao_comissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nivel_unico` (`nivel`);

--
-- Índices de tabela `configurar_cadastro`
--
ALTER TABLE `configurar_cadastro`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `configurar_textos`
--
ALTER TABLE `configurar_textos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `config_saques`
--
ALTER TABLE `config_saques`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `config_saques_historico`
--
ALTER TABLE `config_saques_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `config_id` (`config_id`);

--
-- Índices de tabela `gateway`
--
ALTER TABLE `gateway`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `historico_rendimentos`
--
ALTER TABLE `historico_rendimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `investimento_id` (`investimento_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `historico_transacoes`
--
ALTER TABLE `historico_transacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `indicacoes`
--
ALTER TABLE `indicacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `indicado_id` (`indicado_id`);

--
-- Índices de tabela `investidores`
--
ALTER TABLE `investidores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `produto_investido` (`produto_investido`);

--
-- Índices de tabela `investimentos`
--
ALTER TABLE `investimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `investimentos_usuarios`
--
ALTER TABLE `investimentos_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip` (`ip`,`created_at`);

--
-- Índices de tabela `niveis_convite`
--
ALTER TABLE `niveis_convite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `niveis_vip`
--
ALTER TABLE `niveis_vip`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `personalizar_cores`
--
ALTER TABLE `personalizar_cores`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `personalizar_imagens`
--
ALTER TABLE `personalizar_imagens`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `roleta`
--
ALTER TABLE `roleta`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `roleta_giros_usuario`
--
ALTER TABLE `roleta_giros_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_unico` (`usuario_id`),
  ADD KEY `giros_disponiveis` (`giros_disponiveis`);

--
-- Índices de tabela `roleta_historico`
--
ALTER TABLE `roleta_historico`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `data_giro` (`data_giro`),
  ADD KEY `origem_giro` (`origem_giro`);

--
-- Índices de tabela `salary_levels`
--
ALTER TABLE `salary_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_code` (`level_code`);

--
-- Índices de tabela `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `status` (`status`),
  ADD KEY `request_id` (`request_id`);

--
-- Índices de tabela `salary_requests`
--
ALTER TABLE `salary_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `saques`
--
ALTER TABLE `saques`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `saques_comissao`
--
ALTER TABLE `saques_comissao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `seo_analysis`
--
ALTER TABLE `seo_analysis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`analysis_date`);

--
-- Índices de tabela `seo_history`
--
ALTER TABLE `seo_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`created_at`);

--
-- Índices de tabela `sms_codes`
--
ALTER TABLE `sms_codes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `transacoes`
--
ALTER TABLE `transacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefone` (`telefone`),
  ADD UNIQUE KEY `codigo_referencia` (`codigo_referencia`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `bonus_codigos`
--
ALTER TABLE `bonus_codigos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `captcha_sessions`
--
ALTER TABLE `captcha_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT de tabela `carteira`
--
ALTER TABLE `carteira`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `chaves_pix`
--
ALTER TABLE `chaves_pix`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `checklist`
--
ALTER TABLE `checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `comissoes`
--
ALTER TABLE `comissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `configuracao_comissoes`
--
ALTER TABLE `configuracao_comissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `configurar_cadastro`
--
ALTER TABLE `configurar_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `configurar_textos`
--
ALTER TABLE `configurar_textos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `config_saques`
--
ALTER TABLE `config_saques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `config_saques_historico`
--
ALTER TABLE `config_saques_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `gateway`
--
ALTER TABLE `gateway`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `historico_rendimentos`
--
ALTER TABLE `historico_rendimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_transacoes`
--
ALTER TABLE `historico_transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `indicacoes`
--
ALTER TABLE `indicacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `investidores`
--
ALTER TABLE `investidores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `investimentos`
--
ALTER TABLE `investimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `investimentos_usuarios`
--
ALTER TABLE `investimentos_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `niveis_convite`
--
ALTER TABLE `niveis_convite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `niveis_vip`
--
ALTER TABLE `niveis_vip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `personalizar_cores`
--
ALTER TABLE `personalizar_cores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `personalizar_imagens`
--
ALTER TABLE `personalizar_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `roleta`
--
ALTER TABLE `roleta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `roleta_giros_usuario`
--
ALTER TABLE `roleta_giros_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `roleta_historico`
--
ALTER TABLE `roleta_historico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `salary_levels`
--
ALTER TABLE `salary_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `salary_requests`
--
ALTER TABLE `salary_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `saques`
--
ALTER TABLE `saques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `saques_comissao`
--
ALTER TABLE `saques_comissao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `seo_analysis`
--
ALTER TABLE `seo_analysis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `seo_history`
--
ALTER TABLE `seo_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `sms_codes`
--
ALTER TABLE `sms_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `transacoes`
--
ALTER TABLE `transacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
