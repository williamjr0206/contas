<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

verificaAcesso();

$baseUrl = BASE_URL;
$perfil = perfilAtual();

$cards = [
    ['chave' => 'membros',     'titulo' => 'Membros',                                   'texto' => 'Cadastro e manutenção dos membros da igreja.',                  'link' => $baseUrl . 'cadastros/membros.php'],
    ['chave' => 'relatorio_financeiro',     'titulo' => 'Relatório Finânceiro',         'texto' => 'Relatório Finânceiro.',                                         'link' => $baseUrl . 'relatorios/relatorio_financeiro.php'],
    ['chave' => 'fluxo_caixa',     'titulo' => 'Fluxo de Caixa',                                   'texto' => 'Relatório de Fluxo de Caixa.',                  'link' => $baseUrl . 'relatorios/fluxo_caixa.php'],
    ['chave' => 'compromissos_pagar',     'titulo' => 'Compromissos a Pagar',                                   'texto' => 'Compromissos a Pagar.',                  'link' => $baseUrl . 'relatorios/compromissos_pagamentos.php'],
    ['chave' => 'prestacao_de_contas',     'titulo' => 'Prestaçâo de Contas',                                   'texto' => 'Prestação de Contas.',                  'link' => $baseUrl . 'relatorios/prestacao_contas.php'],
    ['chave' => 'atas',        'titulo' => 'Atas',                                      'texto' => 'Cadastro e manutenção das atas.',                               'link' => $baseUrl . 'cadastros/atas.php'],
    ['chave' => 'dizimos',     'titulo' => 'Dízimos',                                   'texto' => 'Cadastro e Lançamentos de Dízimos.',                            'link' => $baseUrl . 'cadastros/dizimos.php'],
    ['chave' => 'historico_membro',     'titulo' => 'Histórico de Membro',                                   'texto' => 'Cadastro de Histórico de Membro.',                  'link' => $baseUrl . 'cadastros/historico_membro.php'],
    ['chave' => 'atas_pesquisa','titulo' => 'Pesquisa de Atas',                         'texto' => 'Pesquisa por número da reunião, data ou palavra-chave.',        'link' => $baseUrl . 'consultas/atas_pesquisa.php'],
    ['chave' => 'visitantes',  'titulo' => 'Visitantes',                                'texto' => 'Cadastro de visitantes e acompanhamentos.',                     'link' => $baseUrl . 'cadastros/visitantes.php'],
    ['chave' => 'lancamentos','titulo' => 'Lançamentos',                         'texto' => 'Lançamentos de Contas a Pagar e Receber',        'link' => $baseUrl . 'cadastros/lancamentos.php'],
    ['chave' => 'classificacoes','titulo' => 'Classificações','texto' => 'Cadastro de Classificações Lançamentos.',                 'link' => $baseUrl . 'cadastros/grupos.php'],
    ['chave' => 'igrejas',     'titulo' => 'Igrejas',                                   'texto' => 'Cadastro das igrejas vinculadas.',                              'link' => $baseUrl . 'cadastros/igrejas.php'],
    ['chave' => 'cargos',      'titulo' => 'Cargos',                                    'texto' => 'Cadastro de cargos e funções.',                                 'link' => $baseUrl . 'cadastros/cargos.php'],
    ['chave' => 'tipo',        'titulo' => 'Tipo',                                      'texto' => 'Tipos de membros da igreja.',                                   'link' => $baseUrl . 'cadastros/tipo.php'],
    ['chave' => 'cursos',      'titulo' => 'Cursos',                                    'texto' => 'Cadastro dos cursos da EBD.',                                   'link' => $baseUrl . 'cadastros/cursos.php'],
    ['chave' => 'eventos',     'titulo' => 'Eventos',                                   'texto' => 'Cadastro de eventos e reuniões.',                               'link' => $baseUrl . 'cadastros/eventos.php'],
    ['chave' => 'aulas',       'titulo' => 'Aulas',                                     'texto' => 'Registro das aulas ministradas.',                               'link' => $baseUrl . 'cadastros/aulas.php'],
    ['chave' => 'professores', 'titulo' => 'Professores',                               'texto' => 'Cadastro dos professores.',                                     'link' => $baseUrl . 'cadastros/professores.php'],
    ['chave' => 'presencas',   'titulo' => 'Presenças',                                 'texto' => 'Lançamento de presenças em lote.',                              'link' => $baseUrl . 'cadastros/presencas_lote.php'],
    ['chave' => 'usuarios',    'titulo' => 'Usuários',                                  'texto' => 'Administração de usuários do sistema.',                         'link' => $baseUrl . 'cadastros/usuarios.php'],
    ['chave' => 'relatorios',  'titulo' => 'Aniversariantes',                           'texto' => 'Relatório de aniversariantes.',                                 'link' => $baseUrl . 'relatorios/aniversariantes.php'],
    ['chave' => 'relatorios',  'titulo' => 'Lista de Membros',                          'texto' => 'Relação geral dos membros cadastrados.',                        'link' => $baseUrl . 'relatorios/lista_membros.php'],
    ['chave' => 'lista_de_presencas', 'titulo' => 'Lista de Presenças',                 'texto' => 'Relatório escolar de presença e falta por evento.',             'link' => $baseUrl . 'relatorios/lista_de_presencas.php'],
    ['chave' => 'boas_vindas', 'titulo' => 'Boas-vindas',                               'texto' => 'Carta de boas-vindas para visitantes.',                         'link' => $baseUrl . 'relatorios/boas_vindas.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início - IPI Muzambinho</title>
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
        <p>Seu perfil atual é <strong><?= htmlspecialchars($perfil) ?></strong>. Abaixo estão os atalhos disponíveis no sistema da IPI Muzambinho.</p>
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