-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 28/05/2026 às 14:19
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

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos`
--

CREATE TABLE `grupos` (
  `id_grupo` int(11) NOT NULL,
  `descricao` varchar(80) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
  `tipo` enum('Entrada','Saída','Retorno') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

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
  `fator_conversao_consumo` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `saldo` float NOT NULL,
  `cadastrado_em` date NOT NULL,
  `descricao_normalizada` varchar(250) DEFAULT NULL,
  `tipo_de_produto` enum('Cozinha','Banheiro','Alimentos','Remédios','Vestuários','Outros') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

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

-- Índices para tabelas despejadas
--

--
-- Índices de tabela `autores`
--
ALTER TABLE `autores`
  ADD PRIMARY KEY (`Id_autor`);

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
-- Índices de tabela `movimento`
--
ALTER TABLE `movimento`
  ADD PRIMARY KEY (`id_movimento`),
  ADD KEY `id_produto` (`id_produto`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id_produto`),
  ADD KEY `codigo` (`codigo`),
  ADD KEY `idx_produtos_busca_inteligente` (`fornecedor`,`unidade`,`descricao_normalizada`);

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
-- AUTO_INCREMENT de tabela `movimento`
--
ALTER TABLE `movimento`
  MODIFY `id_movimento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id_produto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
