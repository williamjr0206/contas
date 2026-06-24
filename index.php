<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

verificaAcesso();

$baseUrl = BASE_URL;
$perfil = perfilAtual();

$cards = [
    ['chave' => 'relatorio_financeiro', 'titulo' => 'Relatório Finânceiro', 'texto' => 'Relatório Finânceiro.', 'link' => $baseUrl . 'relatorios/relatorio_financeiro.php'],
    ['chave' => 'fluxo_caixa', 'titulo' => 'Fluxo de Caixa', 'texto' => 'Relatório de Fluxo de Caixa.', 'link' => $baseUrl . 'relatorios/fluxo_caixa.php'],
    ['chave' => 'compromissos_pagar', 'titulo' => 'Compromissos a Pagar', 'texto' => 'Compromissos a Pagar.', 'link' => $baseUrl . 'relatorios/compromissos_pagamentos.php'],
    ['chave' => 'relatorio_financeiro', 'titulo' => 'Prestação de Contas', 'texto' => 'Prestação de Contas.', 'link' => $baseUrl . 'relatorios/prestacao_contas.php'],
    ['chave' => 'nota_fiscal', 'titulo' => 'Importações de Notas Fiscais', 'texto' => 'Importação de Notas.', 'link' => $baseUrl . 'cadastros/importar_nota_ia.php'],
    ['chave' => 'produto', 'titulo' => 'Cadastro de Produtos', 'texto' => 'Produtos.', 'link' => $baseUrl . 'cadastros/produtos.php'],
    ['chave' => 'movimento', 'titulo' => 'Lançamentos de Estoque', 'texto' => 'Lançamentos Estoque.', 'link' => $baseUrl . 'cadastros/movimentos.php'],
    ['chave' => 'estoque_produtos', 'titulo' => 'Estoque de Produtos', 'texto' => 'Relatório de estoque com saldo, preço e valor total.', 'link' => $baseUrl . 'relatorios/estoque_produtos.php'],
    ['chave' => 'claudia', 'titulo' => 'Acompanhamento Cláudia', 'texto' => 'Medições de glicemia e Refeições da Cláudia.', 'link' => $baseUrl . 'cadastros/claudia.php'],
    ['chave' => 'resumo', 'titulo' => 'Resumo de movimentação de Estoque por período', 'texto' => 'Resumo de movimentação do Estoque.', 'link' => $baseUrl . 'cadastros/resumo_movimento.php'],
    ['chave' => 'pessoa', 'titulo' => 'Cadastro de Pessoas', 'texto' => 'Cadastro de Pessoas para sugestões de cardápio do Juca.', 'link' => $baseUrl . 'cadastros/pessoas.php'],
    ['chave' => 'pessoa', 'titulo' => 'Cardápio do Juca', 'texto' => 'Sugestão de Cardápio do Juca.', 'link' => $baseUrl . 'relatorios/sugestao_cardapio_ia.php'],
    ['chave' => 'receitas', 'titulo' => 'Receitas da Zenilda', 'texto' => 'Receitas da Zenilda.', 'link' => $baseUrl . 'cadastros/receitas.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar, Receber, Estoque e Cláudia</title>
    <style>
        body {
            margin: 0;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .boas-vindas {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            margin-bottom: 20px;
        }

        .boas-vindas h2 {
            margin: 0 0 10px;
            color: #2c3e50;
        }

        .boas-vindas p {
            margin: 0;
            color: #555;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card h3 {
            margin: 0 0 8px;
            color: #2c3e50;
        }

        .card p {
            margin: 0 0 14px;
            color: #666;
            line-height: 1.4;
        }

        .card a {
            display: inline-block;
            text-decoration: none;
            background: #2c3e50;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
        }

        .card a:hover {
            background: #1abc9c;
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/includes/menu.php'; ?>

<div class="container">
    <div class="boas-vindas">
        <h2>Bem-vindo, <?= htmlspecialchars(nomeUsuarioAtual()) ?>!</h2>
        <p>Seu perfil atual é <strong><?= htmlspecialchars($perfil) ?></strong>. Abaixo estão os atalhos disponíveis no sistema da Pagar/Receber/Estoque e Cláudia.</p>
    </div>

    <div class="grid">
        <?php foreach ($cards as $card): ?>
            <?php if (!temPermissao($card['chave'])) continue; ?>
            <div class="card">
                <div>
                    <h3><?= htmlspecialchars($card['titulo']) ?></h3>
                    <p><?= htmlspecialchars($card['texto']) ?></p>
                </div>
                <div>
                    <a href="<?= htmlspecialchars($card['link']) ?>">Acessar</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>