-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 26/11/2024 às 19:16
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `piramide`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `administrador`
--

CREATE TABLE `administrador` (
  `id` int(11) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `senha` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `bonus_resgatados`
--

CREATE TABLE `bonus_resgatados` (
  `user_id` int(11) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `saldo` int(11) DEFAULT NULL,
  `data_resgate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configurar_textos`
--

CREATE TABLE `configurar_textos` (
  `anuncio` varchar(50) DEFAULT NULL,
  `pop_up` varchar(50) DEFAULT NULL,
  `link_suporte` varchar(50) DEFAULT NULL,
  `titulo_site` varchar(50) DEFAULT NULL,
  `descricao_site` varchar(50) DEFAULT NULL,
  `keywords_site` varchar(50) DEFAULT NULL,
  `link_site` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configurar_textos`
--

INSERT INTO `configurar_textos` (`anuncio`, `pop_up`, `link_suporte`, `titulo_site`, `descricao_site`, `keywords_site`, `link_site`) VALUES
('Estamos felizes em tê-lo conosco no time', 'Estamos muito felizes em tê-lo como investidor. Co', 'https://t.me/', 'Plataforma - Investimentos', 'Descricao', '', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `gateway`
--

CREATE TABLE `gateway` (
  `id` int(11) DEFAULT NULL,
  `client_id` varchar(50) DEFAULT NULL,
  `client_secret` varchar(50) DEFAULT NULL,
  `status` enum('true','false') DEFAULT NULL,
  `banco` enum('SuitPay','VenturePay','PixUP','BSPay') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `gateway`
--

INSERT INTO `gateway` (`id`, `client_id`, `client_secret`, `status`, `banco`) VALUES
(1, 'Indefinido', 'Indefinido', 'false', 'SuitPay'),
(2, 'Indefinido', 'Indefinido', 'false', 'VenturePay');

-- --------------------------------------------------------

--
-- Estrutura para tabela `investidores`
--

CREATE TABLE `investidores` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `numero_telefone` varchar(15) NOT NULL,
  `produto_investido` int(11) DEFAULT NULL,
  `data_investimento` datetime DEFAULT current_timestamp(),
  `ultimo_ciclo` datetime DEFAULT NULL,
  `renda_diaria-off` int(11) DEFAULT NULL,
  `renda_total` int(11) DEFAULT NULL,
  `ciclo_rendimento` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `niveis`
--

CREATE TABLE `niveis` (
  `nivel` int(11) DEFAULT NULL,
  `percentual_comissao` int(11) DEFAULT NULL,
  `valor_minimo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `user_id` int(11) DEFAULT NULL,
  `nivel_1` int(11) DEFAULT NULL,
  `total_nivel1` decimal(20,6) DEFAULT NULL,
  `nivel_2` int(11) DEFAULT NULL,
  `total_nivel2` decimal(20,6) DEFAULT NULL,
  `nivel_3` int(11) DEFAULT NULL,
  `total_nivel3` decimal(20,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `personalizar_cores`
--

CREATE TABLE `personalizar_cores` (
  `cor_1` varchar(50) DEFAULT NULL,
  `cor_2` varchar(50) DEFAULT NULL,
  `cor_3` varchar(50) DEFAULT NULL,
  `cor_4` varchar(50) DEFAULT NULL,
  `cor_5` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `personalizar_cores`
--

INSERT INTO `personalizar_cores` (`cor_1`, `cor_2`, `cor_3`, `cor_4`, `cor_5`) VALUES
('#2e2e2e', '#FFFFFF', '#6b6b6b', '#303536', '#4a4a4a');

-- --------------------------------------------------------

--
-- Estrutura para tabela `personalizar_imagens`
--

CREATE TABLE `personalizar_imagens` (
  `logo` varchar(255) DEFAULT NULL,
  `tela_login` varchar(255) DEFAULT NULL,
  `inicio` varchar(255) DEFAULT NULL,
  `tela_perfil` varchar(255) DEFAULT NULL,
  `tela_avatar` varchar(255) DEFAULT NULL,
  `tela_pix` varchar(255) DEFAULT NULL,
  `tela_retirada` varchar(255) DEFAULT NULL,
  `tela_bonus` varchar(255) DEFAULT NULL,
  `checklist_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `personalizar_imagens`
--

INSERT INTO `personalizar_imagens` (`logo`, `tela_login`, `inicio`, `tela_perfil`, `tela_avatar`, `tela_pix`, `tela_retirada`, `tela_bonus`, `checklist_image`) VALUES
('3.png', '1.png', '2.png', '2.png', '7.png', '5.png', '5.png', '1.png', '8.png');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `valor_investimento` decimal(10,2) NOT NULL,
  `renda_diaria` decimal(10,2) DEFAULT NULL,
  `validade` int(11) DEFAULT NULL,
  `receita_total` decimal(10,2) DEFAULT 0.00,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `saques`
--

CREATE TABLE `saques` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tipo_pix` enum('CPF','Celular','Email','Chave Aleatória') NOT NULL,
  `chave_pix` varchar(50) NOT NULL,
  `numero_telefone` varchar(15) NOT NULL,
  `nome_titular` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') NOT NULL,
  `data` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `saques_comissao`
--

CREATE TABLE `saques_comissao` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tipo_pix` enum('CPF','Celular','Email','Chave Aleatória') NOT NULL,
  `chave_pix` varchar(50) NOT NULL,
  `numero_telefone` varchar(15) NOT NULL,
  `nome_titular` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') NOT NULL,
  `data` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `telefone` varchar(15) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `saldo` decimal(10,2) DEFAULT 0.00,
  `saldo_comissao` decimal(10,2) DEFAULT 0.00,
  `codigo_referencia` varchar(20) DEFAULT NULL,
  `referencia_convite` varchar(50) DEFAULT NULL,
  `primeiro_deposito` tinyint(4) DEFAULT NULL,
  `valor_deposito` decimal(20,6) DEFAULT NULL,
  `valor_investimento` int(11) DEFAULT NULL,
  `total_convites` int(11) DEFAULT 0,
  `checklist` int(11) DEFAULT 0,
  `data_checklist` datetime DEFAULT NULL,
  `tipo_pix` enum('CPF','Celular','Email','Chave Aleatória') DEFAULT NULL,
  `chave_pix` varchar(50) DEFAULT NULL,
  `nome_titular` varchar(100) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `cargo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `investidores`
--
ALTER TABLE `investidores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_investido` (`produto_investido`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `saques`
--
ALTER TABLE `saques`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `saques_comissao`
--
ALTER TABLE `saques_comissao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `telefone` (`telefone`),
  ADD UNIQUE KEY `codigo_referencia` (`codigo_referencia`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `investidores`
--
ALTER TABLE `investidores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `saques`
--
ALTER TABLE `saques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `saques_comissao`
--
ALTER TABLE `saques_comissao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `investidores`
--
ALTER TABLE `investidores`
  ADD CONSTRAINT `investidores_ibfk_1` FOREIGN KEY (`produto_investido`) REFERENCES `produtos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
