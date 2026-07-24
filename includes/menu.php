<?php
require_once __DIR__ . '/../config/database.php';

if (!function_exists('nomeUsuarioAtual')) {
    require_once __DIR__ . '/../config/auth.php';
}

$baseUrl = BASE_URL;

$menu = [
    'Cadastros' => [
        ['chave' => 'usuarios', 'titulo' => 'Usuários', 'link' => $baseUrl . 'cadastros/usuarios.php'],
        ['chave' => 'lancamentos', 'titulo' => 'Lançamentos', 'link' => $baseUrl . 'cadastros/lancamentos.php'],
        ['chave' => 'cartao', 'titulo' => 'Lançamentos de Cartões de Crédito', 'link' => $baseUrl . 'cadastros/cartoes.php'],
        ['chave' => 'classificacoes', 'titulo' => 'Classificação de Lançamentos', 'link' => $baseUrl . 'cadastros/grupos.php'],
        ['chave' => 'nota_fiscal', 'titulo' => 'Importar Nota Fiscal', 'link' => $baseUrl . 'cadastros/importar_nota_ia.php'],
        ['chave' => 'produto', 'titulo' => 'Produtos', 'link' => $baseUrl . 'cadastros/produtos.php'],
        ['chave' => 'movimento', 'titulo' => 'Lançamentos Estoque', 'link' => $baseUrl . 'cadastros/movimentos.php'],
        ['chave' => 'claudia', 'titulo' => 'Medições glicêmicas e Refeições da Cláudia', 'link' => $baseUrl . 'cadastros/claudia.php'],
        ['chave' => 'resumo', 'titulo' => 'Resumo de Movimento de Estoque', 'link' => $baseUrl . 'cadastros/resumo_movimento.php'],
        ['chave' => 'pessoa', 'titulo' => 'Cadastro de Pessoas', 'link' => $baseUrl . 'cadastros/pessoas.php'],
        ['chave' => 'receitas', 'titulo' => 'Receitas da Zenilda', 'link' => $baseUrl . 'cadastros/receitas.php'],
    ],

    'Relatórios' => [
        ['chave' => 'relatorio_financeiro', 'titulo' => 'Relatório Finânceiro', 'link' => $baseUrl . 'relatorios/relatorio_financeiro.php'],
        ['chave' => 'fluxo_caixa', 'titulo' => 'Relatório de Fluxo de Caixa', 'link' => $baseUrl . 'relatorios/fluxo_caixa.php'],
        ['chave' => 'compromissos_pagar', 'titulo' => 'Compromissos a Pagar', 'link' => $baseUrl . 'relatorios/compromissos_pagamentos.php'],
        ['chave' => 'relatorio_financeiro', 'titulo' => 'Prestação de Contas', 'link' => $baseUrl . 'relatorios/prestacao_contas.php'],
        ['chave' => 'estoque_produtos', 'titulo' => 'Estoque de Produtos', 'link' => $baseUrl . 'relatorios/estoque_produtos.php'],
        ['chave' => 'cardapio', 'titulo' => 'Sugestão de Cardápio do Juca', 'link' => $baseUrl . 'relatorios/sugestao_cardapio_ia.php'],
    ],
    'Consultas' => [
        ['chave' => 'consultas', 'titulo' => 'Consulta Cartões de Créditos', 'link' => $baseUrl . 'cadastros/consulta_cartoes.php'],
    ],

    'Sessão' => [
        ['chave' => 'sair', 'titulo' => 'Sair', 'link' => $baseUrl . 'logout.php'],
    ],
];
?>

<style>
    * { box-sizing: border-box; }

    .topo-sistema {
        background: #2c3e50;
        color: #fff;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 999;
    }

    .topo-sistema .titulo {
        font-size: 18px;
        font-weight: 700;
    }

    .topo-sistema .usuario {
        font-size: 14px;
    }

    .menu-botao {
        display: none;
        background: #1abc9c;
        border: none;
        color: #fff;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 18px;
        cursor: pointer;
    }

    .menu-sistema {
        background: #34495e;
        padding: 8px 12px;
    }

    .menu-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
    }

    .menu-bloco {
        min-width: 180px;
    }

    .menu-bloco h4 {
        margin: 6px 0 8px;
        color: #ecf0f1;
        font-size: 14px;
        text-transform: uppercase;
    }

    .menu-bloco a {
        display: block;
        color: #fff;
        text-decoration: none;
        padding: 7px 10px;
        border-radius: 8px;
        margin-bottom: 4px;
    }

    .menu-bloco a:hover {
        background: #1abc9c;
    }

    @media (max-width: 768px) {
        .menu-botao {
            display: inline-block;
        }

        .menu-sistema {
            display: none;
        }

        .menu-sistema.aberto {
            display: block;
        }

        .menu-grid {
            display: block;
        }

        .menu-bloco {
            margin-bottom: 14px;
        }
    }
</style>

<div class="topo-sistema">
    <div class="titulo">Sistemas - Pagar/Receber/Estoques/Cláudia - SZJW</div>
    <div class="usuario">
        <?= htmlspecialchars(nomeUsuarioAtual()) ?> - Perfil: <strong><?= htmlspecialchars(perfilAtual()) ?></strong>
    </div>
    <button class="menu-botao" type="button" onclick="alternarMenu()">☰</button>
</div>

<nav class="menu-sistema" id="menuSistema">
    <div class="menu-grid">
        <?php foreach ($menu as $grupo => $itens): ?>
            <?php
            $visiveis = array_filter($itens, fn($item) => temPermissao($item['chave']));
            if (!$visiveis) continue;
            ?>
            <div class="menu-bloco">
                <h4><?= htmlspecialchars($grupo) ?></h4>
                <?php foreach ($visiveis as $item): ?>
                    <a href="<?= htmlspecialchars($item['link']) ?>"><?= htmlspecialchars($item['titulo']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</nav>

<script>
function alternarMenu() {
    const menu = document.getElementById('menuSistema');
    menu.classList.toggle('aberto');
}
</script>