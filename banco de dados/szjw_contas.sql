-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 02/07/2026 às 12:33
-- Versão do servidor: 5.7.44
-- Versão do PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `szjw_contas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `autores`
--

CREATE TABLE `autores` (
  `Id_autor` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Despejando dados para a tabela `autores`
--

INSERT INTO `autores` (`Id_autor`, `nome`) VALUES
(1, 'Adm'),


--
-- Estrutura para tabela `grupos`
--

CREATE TABLE `grupos` (
  `id_grupo` int(11) NOT NULL,
  `descricao` varchar(80) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Despejando dados para a tabela `grupos`
--

INSERT INTO `grupos` (`id_grupo`, `descricao`) VALUES
(1, 'Aluguéis'),
(2, 'Combustível'),
(3, 'Despesa com Alimentação'),
(5, 'Despesa com Carro'),
(6, 'Despesa com saúde'),
(7, 'Despesa de Casa'),
(9, 'Lazer'),
(10, 'Pessoal'),
(11, 'Presentes'),
(12, 'Rendimentos'),
(14, 'Viagem para São Paulo'),
(15, 'Aplicações Financeiras'),
(16, 'Despesas com Casas Alugadas'),
(17, 'Tributos'),
(18, 'Transações Finânceiras'),
(19, 'Resgate');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lancamentos`
--

CREATE TABLE `lancamentos` (
  `id_lancamento` int(11) NOT NULL,
  `documento_numero` int(11) NOT NULL,
  `data_lancamento` date NOT NULL,
  `descricao` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `tipo` enum('Pagar','Receber') NOT NULL,
  `data_vencimento` date NOT NULL,
  `valor_nominal` float NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` float DEFAULT NULL,
  `status` enum('Aberto','Recebido','Pago') NOT NULL,
  `forma_de_pagamento_recebimento` enum('','Pix Recebido','Pix QR Code','Dinheiro','Aplicação','Cartão Débito','Cartão Crédito','Débito Automático','Crédito em Conta','Débito em Conta','Pagamento Boleto','Pix Pagamento','Transação Bancária') DEFAULT NULL,
  `id_grupo` int(11) NOT NULL,
  `id_autor` int(11) DEFAULT NULL,
  `foto_nota` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Estrutura para tabela `medidas_caseiras`
--

CREATE TABLE `medidas_caseiras` (
  `id_medida` int(11) NOT NULL,
  `descricao` varchar(150) NOT NULL,
  `quantidade` decimal(10,4) NOT NULL,
  `unidade` varchar(20) NOT NULL,
  `observacao` varchar(255) DEFAULT NULL,
  `ativo` char(1) NOT NULL DEFAULT 'S',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Despejando dados para a tabela `medidas_caseiras`
--

INSERT INTO `medidas_caseiras` (`id_medida`, `descricao`, `quantidade`, `unidade`, `observacao`, `ativo`, `criado_em`) VALUES
(1, 'Dente médio de alho', 3.0000, 'G', 'Peso médio aproximado', 'S', '2026-06-27 15:48:00'),
(2, 'Colher de sopa de azeite', 15.0000, 'ML', 'Medida culinária padrão', 'S', '2026-06-27 15:48:00'),
(3, 'Colher de sopa de óleo de soja', 15.0000, 'ML', 'Medida culinária padrão', 'S', '2026-06-27 15:48:00'),
(4, 'Colher de sopa de farinha de milho', 10.0000, 'G', 'Fubá ou flocão', 'S', '2026-06-27 15:48:00'),
(5, 'Xícara de chá de farinha de milho', 120.0000, 'G', 'Medida padrão', 'S', '2026-06-27 15:48:00'),
(6, 'Ovo médio', 50.0000, 'G', 'Peso médio sem casca', 'S', '2026-06-27 15:48:00'),
(7, 'Colher de sopa de açúcar', 12.0000, 'G', NULL, 'S', '2026-06-27 15:48:00'),
(8, 'Colher de chá de sal', 5.0000, 'G', NULL, 'S', '2026-06-27 15:48:00'),
(9, 'Colher de sopa de manteiga', 14.0000, 'G', NULL, 'S', '2026-06-27 15:48:00'),
(10, 'Xícara de chá de leite', 240.0000, 'ML', NULL, 'S', '2026-06-27 15:48:00'),
(11, 'Unidade média de abacate', 500.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(12, 'Fatia média de abacaxi', 75.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(13, 'Unidade média de abacaxi', 1200.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(14, 'Unidade de acerola', 8.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(15, 'Unidade média de ameixa', 35.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(16, 'Unidade média de banana nanica', 90.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(17, 'Unidade média de banana prata', 80.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(18, 'Unidade média de banana maçã', 70.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(19, 'Unidade média de caqui', 120.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(20, 'Unidade média de goiaba', 170.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(21, 'Unidade média de kiwi', 75.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(22, 'Unidade média de laranja pera', 160.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(23, 'Unidade média de limão tahiti', 70.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(24, 'Unidade média de maçã', 130.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(25, 'Fatia média de mamão formosa', 150.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(26, 'Unidade média de mamão papaia', 350.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(27, 'Unidade média de manga', 300.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(28, 'Unidade média de maracujá', 120.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(29, 'Fatia média de melancia', 300.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(30, 'Fatia média de melão', 200.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(31, 'Unidade média de morango', 12.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(32, 'Unidade média de pera', 150.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(33, 'Unidade média de pêssego', 120.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(34, 'Unidade média de tangerina', 140.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(35, 'Unidade de uva', 6.0000, 'G', 'Fruta', 'S', '2026-06-28 15:14:45'),
(36, 'Unidade média de batata', 180.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(37, 'Unidade média de batata doce', 200.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(38, 'Unidade média de cenoura', 100.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(39, 'Unidade média de beterraba', 150.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(40, 'Unidade média de cebola', 150.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(41, 'Unidade média de tomate', 120.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(42, 'Unidade média de pepino', 200.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(43, 'Unidade média de abobrinha', 250.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(44, 'Unidade média de berinjela', 300.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(45, 'Unidade média de chuchu', 300.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(46, 'Unidade média de pimentão', 150.0000, 'G', 'Legume', 'S', '2026-06-28 15:14:45'),
(47, 'Folha média de alface', 10.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(48, 'Folha média de couve', 20.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(49, 'Xícara de chá de repolho picado', 70.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(50, 'Xícara de chá de brócolis cozido', 90.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(51, 'Xícara de chá de couve-flor cozida', 100.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(52, 'Xícara de chá de espinafre cozido', 90.0000, 'G', 'Verdura', 'S', '2026-06-28 15:14:45'),
(53, 'Bife médio de carne bovina', 100.0000, 'G', 'Carne', 'S', '2026-06-28 15:14:45'),
(54, 'Porção média de carne moída', 100.0000, 'G', 'Carne', 'S', '2026-06-28 15:14:45'),
(55, 'Filé médio de frango', 120.0000, 'G', 'Frango', 'S', '2026-06-28 15:14:45'),
(56, 'Coxa média de frango', 120.0000, 'G', 'Frango', 'S', '2026-06-28 15:14:45'),
(57, 'Sobrecoxa média de frango', 150.0000, 'G', 'Frango', 'S', '2026-06-28 15:14:45'),
(58, 'Porção média de peito de frango desfiado', 100.0000, 'G', 'Frango', 'S', '2026-06-28 15:14:45'),
(59, 'Filé médio de peixe', 120.0000, 'G', 'Pescado', 'S', '2026-06-28 15:14:45'),
(60, 'Posta média de peixe', 150.0000, 'G', 'Pescado', 'S', '2026-06-28 15:14:45'),
(61, 'Lata de sardinha escorrida', 84.0000, 'G', 'Pescado', 'S', '2026-06-28 15:14:45'),
(62, 'Lata de atum escorrido', 120.0000, 'G', 'Pescado', 'S', '2026-06-28 15:14:45'),
(63, 'Xícara de chá de arroz cru', 180.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(64, 'Xícara de chá de arroz cozido', 160.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(65, 'Xícara de chá de feijão cru', 180.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(66, 'Concha média de feijão cozido', 100.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(67, 'Colher de sopa de farinha de trigo', 8.0000, 'G', 'Farinha', 'S', '2026-06-28 15:14:45'),
(68, 'Xícara de chá de farinha de trigo', 120.0000, 'G', 'Farinha', 'S', '2026-06-28 15:14:45'),
(69, 'Colher de sopa de farinha de milho', 10.0000, 'G', 'Farinha', 'S', '2026-06-28 15:14:45'),
(70, 'Xícara de chá de farinha de milho', 120.0000, 'G', 'Farinha', 'S', '2026-06-28 15:14:45'),
(71, 'Colher de sopa de aveia', 10.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(72, 'Xícara de chá de aveia', 80.0000, 'G', 'Cereal', 'S', '2026-06-28 15:14:45'),
(73, 'Colher de sopa de amido de milho', 8.0000, 'G', 'Farinha', 'S', '2026-06-28 15:14:45'),
(74, 'Xícara de chá de macarrão cru', 100.0000, 'G', 'Massa', 'S', '2026-06-28 15:14:45'),
(75, 'Xícara de chá de macarrão cozido', 140.0000, 'G', 'Massa', 'S', '2026-06-28 15:14:45'),
(76, 'Xícara de chá de leite', 240.0000, 'ML', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(77, 'Copo americano de leite', 200.0000, 'ML', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(78, 'Colher de sopa de leite em pó', 10.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(79, 'Fatia média de queijo muçarela', 20.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(80, 'Fatia média de queijo prato', 20.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(81, 'Colher de sopa de queijo ralado', 8.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(82, 'Colher de sopa de requeijão', 30.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(83, 'Colher de sopa de creme de leite', 15.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(84, 'Pote de iogurte natural', 170.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(85, 'Dente médio de alho', 3.0000, 'G', 'Tempero', 'S', '2026-06-28 15:14:45'),
(86, 'Colher de sopa de azeite', 15.0000, 'ML', 'Condimento', 'S', '2026-06-28 15:14:45'),
(87, 'Colher de sopa de óleo de soja', 15.0000, 'ML', 'Condimento', 'S', '2026-06-28 15:14:45'),
(88, 'Colher de chá de sal', 5.0000, 'G', 'Tempero', 'S', '2026-06-28 15:14:45'),
(89, 'Colher de sopa de sal', 18.0000, 'G', 'Tempero', 'S', '2026-06-28 15:14:45'),
(90, 'Colher de sopa de açúcar', 12.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(91, 'Xícara de chá de açúcar', 200.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(92, 'Colher de sopa de manteiga', 14.0000, 'G', 'Laticínio', 'S', '2026-06-28 15:14:45'),
(93, 'Colher de sopa de margarina', 14.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(94, 'Colher de sopa de molho de tomate', 15.0000, 'G', 'Condimento', 'S', '2026-06-28 15:14:45'),
(95, 'Colher de sopa de extrato de tomate', 20.0000, 'G', 'Condimento', 'S', '2026-06-28 15:14:45'),
(96, 'Colher de chá de fermento químico', 4.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(97, 'Colher de sopa de fermento químico', 12.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(98, 'Colher de chá de canela em pó', 2.5000, 'G', 'Tempero', 'S', '2026-06-28 15:14:45'),
(99, 'Colher de sopa de chocolate em pó', 10.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(100, 'Colher de sopa de cacau em pó', 10.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(101, 'Colher de sopa de mel', 20.0000, 'G', 'Ingrediente', 'S', '2026-06-28 15:14:45'),
(102, 'Colher de chá de líquido', 5.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45'),
(103, 'Colher de sobremesa de líquido', 10.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45'),
(104, 'Colher de sopa de líquido', 15.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45'),
(105, 'Xícara de chá de líquido', 240.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45'),
(106, 'Copo americano de líquido', 200.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45'),
(107, 'Concha média de líquido', 100.0000, 'ML', 'Medida geral', 'S', '2026-06-28 15:14:45');

-- --------------------------------------------------------

--
-- Estrutura para tabela `movimento`
--

CREATE TABLE `movimento` (
  `id_movimento` int(11) NOT NULL,
  `data_movimento` date NOT NULL,
  `documento` varchar(150) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `codigo` varchar(150) NOT NULL,
  `quantidade` float NOT NULL,
  `quantidade_digitada` float NOT NULL DEFAULT '0',
  `tipo` enum('Entrada','Saída','Retorno') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Estrutura para tabela `pessoas`
--

CREATE TABLE `pessoas` (
  `id_pessoa` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `data_nascimento` date NOT NULL,
  `cidade` varchar(50) NOT NULL,
  `estado` varchar(2) NOT NULL,
  `altura` float NOT NULL,
  `peso` float NOT NULL,
  `diabetico` enum('Sim','Não') NOT NULL DEFAULT 'Não',
  `hipertenso` enum('Sim','Não') NOT NULL DEFAULT 'Não',
  `colesterol_alto` enum('Sim','Não') NOT NULL DEFAULT 'Não',
  `intolerancia_lactose` enum('Sim','Não') NOT NULL DEFAULT 'Não',
  `vegetariano` enum('Sim','Não') NOT NULL DEFAULT 'Não'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id_produto` int(11) NOT NULL,
  `codigo` varchar(150) NOT NULL,
  `fornecedor` varchar(200) NOT NULL,
  `descricao` varchar(150) NOT NULL,
  `preco` float NOT NULL,
  `unidade` varchar(5) NOT NULL,
  `unidade_consumo` varchar(20) DEFAULT NULL,
  `id_medida_caseira` int(11) DEFAULT NULL,
  `quantidade_embalagem` decimal(10,4) DEFAULT '1.0000',
  `peso_unidade_consumo` decimal(10,4) DEFAULT '1.0000',
  `conteudo_embalagem` decimal(10,4) DEFAULT '1.0000',
  `fator_conversao_consumo` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `saldo` float NOT NULL,
  `cadastrado_em` date NOT NULL,
  `descricao_normalizada` varchar(250) DEFAULT NULL,
  `tipo_de_produto` enum('Cozinha','Banheiro','Alimentos','Remédios','Vestuários','Outros') DEFAULT 'Alimentos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Estrutura para tabela `receitas`
--

CREATE TABLE `receitas` (
  `id_receita` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text,
  `fonte_site` text,
  `tempo_preparo_minutos` int(11) DEFAULT NULL,
  `rendimento` varchar(100) DEFAULT NULL,
  `dificuldade` enum('Fácil','Média','Difícil') DEFAULT 'Fácil',
  `modo_preparo` text,
  `observacoes` text,
  `favorita` enum('Sim','Não') DEFAULT 'Não',
  `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `foto_receita` varchar(255) DEFAULT NULL,
  `data_atualizacao` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Estrutura para tabela `receitas_categorias`
--

CREATE TABLE `receitas_categorias` (
  `id_categoria` int(11) NOT NULL,
  `nome_categoria` varchar(100) NOT NULL,
  `descricao` text,
  `ativo` enum('Sim','Não') DEFAULT 'Sim',
  `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Despejando dados para a tabela `receitas_categorias`
--

INSERT INTO `receitas_categorias` (`id_categoria`, `nome_categoria`, `descricao`, `ativo`, `data_cadastro`) VALUES
(1, 'Refeições', 'Pratos principais, almoço e jantar', 'Sim', '2026-06-24 16:03:22'),
(2, 'Sobremesas', 'Doces, bolos, tortas e sobremesas em geral', 'Sim', '2026-06-24 16:03:22'),
(3, 'Petiscos', 'Entradas, salgados e acompanhamentos', 'Sim', '2026-06-24 16:03:22'),
(4, 'Bebidas', 'Sucos, vitaminas, cafés e outras bebidas', 'Sim', '2026-06-24 16:03:22'),
(5, 'Massas', 'Pães, pizzas, panquecas, macarrão e similares', 'Sim', '2026-06-24 16:03:22'),
(6, 'Saladas', 'Saladas frias, quentes e acompanhamentos leves', 'Sim', '2026-06-24 16:03:22'),
(7, 'Chás e receitas caseiras', 'Receitas tradicionais, chás e preparos caseiros', 'Sim', '2026-06-24 16:03:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `receitas_ingredientes`
--

CREATE TABLE `receitas_ingredientes` (
  `id_ingrediente` int(11) NOT NULL,
  `id_receita` int(11) NOT NULL,
  `id_produto` int(11) DEFAULT NULL,
  `descricao_ingrediente` varchar(500) DEFAULT NULL,
  `quantidade` decimal(10,3) NOT NULL DEFAULT '0.000',
  `unidade` varchar(30) DEFAULT NULL,
  `saldo_consumo` decimal(10,3) DEFAULT '0.000',
  `quantidade_faltante` decimal(10,3) DEFAULT '0.000',
  `status_estoque` varchar(30) DEFAULT NULL,
  `custo_unitario` decimal(10,2) DEFAULT '0.00',
  `custo_total` decimal(10,2) DEFAULT '0.00',
  `observacao` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Estrutura para tabela `receitas_processos`
--

CREATE TABLE `receitas_processos` (
  `id_processo` int(11) NOT NULL,
  `id_receita` int(11) NOT NULL,
  `sequencia` int(11) NOT NULL,
  `descricao_etapa` text NOT NULL,
  `tempo_estimado_minutos` int(11) DEFAULT NULL,
  `observacao` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nome_usuario` varchar(150) CHARACTER SET latin1 COLLATE latin1_german1_ci NOT NULL,
  `email` varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `senha` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `perfil` enum('ADMIN','OPERADOR','CONSULTA','LIDER') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `autores`
--
ALTER TABLE `autores`
  ADD PRIMARY KEY (`Id_autor`);

--
-- Índices de tabela `claudia`
--
ALTER TABLE `claudia`
  ADD PRIMARY KEY (`id_lancamento`);

--
-- Índices de tabela `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id_grupo`);

--
-- Índices de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD PRIMARY KEY (`id_lancamento`),
  ADD KEY `id_grupo` (`id_grupo`),
  ADD KEY `id_autor` (`id_autor`);

--
-- Índices de tabela `medidas_caseiras`
--
ALTER TABLE `medidas_caseiras`
  ADD PRIMARY KEY (`id_medida`);

--
-- Índices de tabela `movimento`
--
ALTER TABLE `movimento`
  ADD PRIMARY KEY (`id_movimento`),
  ADD KEY `id_produto` (`id_produto`);

--
-- Índices de tabela `pessoas`
--
ALTER TABLE `pessoas`
  ADD PRIMARY KEY (`id_pessoa`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id_produto`),
  ADD KEY `codigo` (`codigo`),
  ADD KEY `idx_produtos_busca_inteligente` (`fornecedor`,`unidade`,`descricao_normalizada`);

--
-- Índices de tabela `receitas`
--
ALTER TABLE `receitas`
  ADD PRIMARY KEY (`id_receita`),
  ADD KEY `fk_receitas_categoria` (`id_categoria`);

--
-- Índices de tabela `receitas_categorias`
--
ALTER TABLE `receitas_categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Índices de tabela `receitas_ingredientes`
--
ALTER TABLE `receitas_ingredientes`
  ADD PRIMARY KEY (`id_ingrediente`),
  ADD KEY `fk_receitas_ingredientes_receita` (`id_receita`);

--
-- Índices de tabela `receitas_processos`
--
ALTER TABLE `receitas_processos`
  ADD PRIMARY KEY (`id_processo`),
  ADD KEY `fk_receitas_processos_receita` (`id_receita`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `autores`
--
ALTER TABLE `autores`
  MODIFY `Id_autor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;


--
-- AUTO_INCREMENT de tabela `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id_grupo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  MODIFY `id_lancamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `medidas_caseiras`
--
ALTER TABLE `medidas_caseiras`
  MODIFY `id_medida` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `movimento`
--
ALTER TABLE `movimento`
  MODIFY `id_movimento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `pessoas`
--
ALTER TABLE `pessoas`
  MODIFY `id_pessoa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id_produto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `receitas`
--
ALTER TABLE `receitas`
  MODIFY `id_receita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `receitas_categorias`
--
ALTER TABLE `receitas_categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `receitas_ingredientes`
--
ALTER TABLE `receitas_ingredientes`
  MODIFY `id_ingrediente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `receitas_processos`
--
ALTER TABLE `receitas_processos`
  MODIFY `id_processo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD CONSTRAINT `lancamento_lbfk2` FOREIGN KEY (`id_autor`) REFERENCES `autores` (`Id_autor`),
  ADD CONSTRAINT `lancamentos_ibfk_1` FOREIGN KEY (`id_grupo`) REFERENCES `grupos` (`id_grupo`);

--
-- Restrições para tabelas `movimento`
--
ALTER TABLE `movimento`
  ADD CONSTRAINT `movimento_ibfk_1` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id_produto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `receitas`
--
ALTER TABLE `receitas`
  ADD CONSTRAINT `fk_receitas_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `receitas_categorias` (`id_categoria`);

--
-- Restrições para tabelas `receitas_ingredientes`
--
ALTER TABLE `receitas_ingredientes`
  ADD CONSTRAINT `fk_receitas_ingredientes_receita` FOREIGN KEY (`id_receita`) REFERENCES `receitas` (`id_receita`) ON DELETE CASCADE;

--
-- Restrições para tabelas `receitas_processos`
--
ALTER TABLE `receitas_processos`
  ADD CONSTRAINT `fk_receitas_processos_receita` FOREIGN KEY (`id_receita`) REFERENCES `receitas` (`id_receita`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
