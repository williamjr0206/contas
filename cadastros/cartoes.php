<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

verificaAcesso();

require __DIR__ . '/../includes/menu.php';

/* =========================================================
   FUNÇÃO PARA NORMALIZAR VALOR MONETÁRIO
   Compatível com PHP 7.4
========================================================= */
function normalizarValorMonetario($valor)
{
    $valor = trim($valor);

    if ($valor === '') {
        return 0;
    }

    /*
     * Exemplos aceitos:
     *
     * 1000.50
     * 1000,50
     * 1.000,50
     */

    if (
        strpos($valor, ',') !== false
        && strpos($valor, '.') !== false
    ) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);

    } elseif (strpos($valor, ',') !== false) {
        $valor = str_replace(',', '.', $valor);
    }

    return (float) $valor;
}

/* =========================================================
   MENSAGENS
========================================================= */
$mensagem = '';
$tipoMensagem = '';

if (isset($_GET['sucesso'])) {

    if ($_GET['sucesso'] === 'salvo') {
        $mensagem = 'Lançamento salvo com sucesso.';
        $tipoMensagem = 'sucesso';

    } elseif ($_GET['sucesso'] === 'excluido') {
        $mensagem = 'Lançamento excluído com sucesso.';
        $tipoMensagem = 'sucesso';
    }
}

if (isset($_GET['erro'])) {

    if ($_GET['erro'] === 'dados_invalidos') {
        $mensagem = 'Preencha corretamente todos os campos obrigatórios.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'nao_encontrado') {
        $mensagem = 'O lançamento informado não foi encontrado.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'faturado') {
        $mensagem = 'Esse lançamento já foi faturado e não pode ser alterado ou excluído.';
        $tipoMensagem = 'erro';

    } elseif ($_GET['erro'] === 'banco') {
        $mensagem = 'Ocorreu um erro ao realizar a operação.';
        $tipoMensagem = 'erro';
    }
}

/* =========================================================
   SALVAR OU EDITAR
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = isset($_POST['id'])
        ? (int) $_POST['id']
        : 0;

    $documentoNumero = isset($_POST['documento_numero'])
        ? trim($_POST['documento_numero'])
        : '';

    $dataLancamento = isset($_POST['data_lancamento'])
        ? trim($_POST['data_lancamento'])
        : '';

    $descricao = isset($_POST['descricao'])
        ? trim($_POST['descricao'])
        : '';

    $idCartao = isset($_POST['id_cartao'])
        ? (int) $_POST['id_cartao']
        : 0;

    $idGrupo = isset($_POST['id_grupo'])
        ? (int) $_POST['id_grupo']
        : 0;

    $valorInformado = isset($_POST['valor'])
        ? $_POST['valor']
        : '';

    $valor = normalizarValorMonetario($valorInformado);

    /*
     * Documento pode ficar vazio.
     * Nesse caso, será salvo como NULL.
     */
    if ($documentoNumero === '') {
        $documentoNumero = null;
    }

    /* =====================================================
       VALIDAÇÃO DOS CAMPOS
    ===================================================== */
    if (
        $dataLancamento === ''
        || $descricao === ''
        || $idCartao <= 0
        || $idGrupo <= 0
        || $valor <= 0
    ) {
        header(
            'Location: '
            . BASE_URL
            . 'cadastros/cartoes.php?erro=dados_invalidos'
        );
        exit;
    }

    /*
     * Validação da data.
     */
    $dataObjeto = DateTime::createFromFormat(
        'Y-m-d',
        $dataLancamento
    );

    if (
        !$dataObjeto
        || $dataObjeto->format('Y-m-d') !== $dataLancamento
    ) {
        header(
            'Location: '
            . BASE_URL
            . 'cadastros/cartoes.php?erro=dados_invalidos'
        );
        exit;
    }

    try {

        /* =================================================
           EDITAR
        ================================================= */
        if ($id > 0) {

            /*
             * Verifica se o registro existe e se ainda
             * pode ser alterado.
             */
            $stmtVerificar = $pdo->prepare("
                SELECT
                    id_lancamento_cartao,
                    status,
                    id_lancamento_fatura
                FROM lancamentos_cartoes
                WHERE id_lancamento_cartao = :id
            ");

            $stmtVerificar->execute([
                ':id' => $id
            ]);

            $registroAtual = $stmtVerificar->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$registroAtual) {
                header(
                    'Location: '
                    . BASE_URL
                    . 'cadastros/cartoes.php?erro=nao_encontrado'
                );
                exit;
            }

            /*
             * Somente lançamentos com status Aberto
             * e sem vínculo com uma fatura podem ser editados.
             */
            if (
                $registroAtual['status'] !== 'Aberto'
                || !empty(
                    $registroAtual['id_lancamento_fatura']
                )
            ) {
                header(
                    'Location: '
                    . BASE_URL
                    . 'cadastros/cartoes.php?erro=faturado'
                );
                exit;
            }

            $sql = "
                UPDATE lancamentos_cartoes
                SET
                    documento_numero = :documento_numero,
                    data_lancamento = :data_lancamento,
                    descricao = :descricao,
                    id_cartao = :id_cartao,
                    id_grupo = :id_grupo,
                    valor = :valor
                WHERE id_lancamento_cartao = :id
                  AND status = 'Aberto'
                  AND id_lancamento_fatura IS NULL
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':documento_numero' => $documentoNumero,
                ':data_lancamento' => $dataLancamento,
                ':descricao' => $descricao,
                ':id_cartao' => $idCartao,
                ':id_grupo' => $idGrupo,
                ':valor' => $valor,
                ':id' => $id
            ]);

        /* =================================================
           INSERIR
        ================================================= */
        } else {

            $sql = "
                INSERT INTO lancamentos_cartoes (
                    documento_numero,
                    data_lancamento,
                    descricao,
                    id_cartao,
                    id_grupo,
                    valor,
                    status,
                    id_lancamento_fatura
                ) VALUES (
                    :documento_numero,
                    :data_lancamento,
                    :descricao,
                    :id_cartao,
                    :id_grupo,
                    :valor,
                    'Aberto',
                    NULL
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':documento_numero' => $documentoNumero,
                ':data_lancamento' => $dataLancamento,
                ':descricao' => $descricao,
                ':id_cartao' => $idCartao,
                ':id_grupo' => $idGrupo,
                ':valor' => $valor
            ]);
        }

        header(
            'Location: '
            . BASE_URL
            . 'cadastros/cartoes.php?sucesso=salvo'
        );
        exit;

    } catch (PDOException $e) {

        /*
         * Durante os testes, deixei o erro completo visível.
         * Isso ajuda a descobrir problemas na tabela.
         */
        die(
            '<strong>Erro ao salvar o lançamento:</strong><br>'
            . htmlspecialchars($e->getMessage())
        );
    }
}

/* =========================================================
   EXCLUIR
========================================================= */
if (isset($_GET['delete'])) {

    $idExcluir = (int) $_GET['delete'];

    if ($idExcluir <= 0) {
        header(
            'Location: '
            . BASE_URL
            . 'cadastros/cartoes.php?erro=nao_encontrado'
        );
        exit;
    }

    try {

        /*
         * Verifica a situação antes de excluir.
         */
        $stmtVerificar = $pdo->prepare("
            SELECT
                id_lancamento_cartao,
                status,
                id_lancamento_fatura
            FROM lancamentos_cartoes
            WHERE id_lancamento_cartao = :id
        ");

        $stmtVerificar->execute([
            ':id' => $idExcluir
        ]);

        $registro = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            header(
                'Location: '
                . BASE_URL
                . 'cadastros/cartoes.php?erro=nao_encontrado'
            );
            exit;
        }

        /*
         * Não permite excluir um lançamento já faturado.
         */
        if (
            $registro['status'] !== 'Aberto'
            || !empty($registro['id_lancamento_fatura'])
        ) {
            header(
                'Location: '
                . BASE_URL
                . 'cadastros/cartoes.php?erro=faturado'
            );
            exit;
        }

        /*
         * Correção:
         * a exclusão deve acontecer em lancamentos_cartoes,
         * e não na tabela cartoes.
         */
        $stmtExcluir = $pdo->prepare("
            DELETE FROM lancamentos_cartoes
            WHERE id_lancamento_cartao = :id
              AND status = 'Aberto'
              AND id_lancamento_fatura IS NULL
        ");

        $stmtExcluir->execute([
            ':id' => $idExcluir
        ]);

        header(
            'Location: '
            . BASE_URL
            . 'cadastros/cartoes.php?sucesso=excluido'
        );
        exit;

    } catch (PDOException $e) {

        die(
            '<strong>Erro ao excluir o lançamento:</strong><br>'
            . htmlspecialchars($e->getMessage())
        );
    }
}

/* =========================================================
   CARREGAR REGISTRO PARA EDIÇÃO
========================================================= */
$editar = null;

if (isset($_GET['edit'])) {

    $idEditar = (int) $_GET['edit'];

    if ($idEditar > 0) {

        $stmtEditar = $pdo->prepare("
            SELECT *
            FROM lancamentos_cartoes
            WHERE id_lancamento_cartao = :id
        ");

        $stmtEditar->execute([
            ':id' => $idEditar
        ]);

        $editar = $stmtEditar->fetch(PDO::FETCH_ASSOC);

        if (!$editar) {
            header(
                'Location: '
                . BASE_URL
                . 'cadastros/cartoes.php?erro=nao_encontrado'
            );
            exit;
        }

        /*
         * Um lançamento faturado não deve abrir
         * no formulário de edição.
         */
        if (
            $editar['status'] !== 'Aberto'
            || !empty($editar['id_lancamento_fatura'])
        ) {
            header(
                'Location: '
                . BASE_URL
                . 'cadastros/cartoes.php?erro=faturado'
            );
            exit;
        }
    }
}

/* =========================================================
   BUSCAR GRUPOS
========================================================= */
$stmtGrupos = $pdo->query("
    SELECT
        id_grupo,
        descricao
    FROM grupos
    ORDER BY descricao
");

$grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BUSCAR CARTÕES
========================================================= */
$stmtCartoes = $pdo->query("
    SELECT
        id_cartao,
        descricao
    FROM cartoes
    ORDER BY descricao
");

$cartoes = $stmtCartoes->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   LISTAR LANÇAMENTOS
========================================================= */
$stmtLancamentos = $pdo->query("
    SELECT
        lc.id_lancamento_cartao,
        lc.documento_numero,
        lc.data_lancamento,
        lc.descricao,
        lc.id_cartao,
        lc.id_grupo,
        lc.valor,
        lc.status,
        lc.id_lancamento_fatura,

        c.descricao AS cartao_descricao,
        g.descricao AS grupo_descricao

    FROM lancamentos_cartoes lc

    LEFT JOIN cartoes c
        ON c.id_cartao = lc.id_cartao

    LEFT JOIN grupos g
        ON g.id_grupo = lc.id_grupo

    ORDER BY
        lc.documento_numero DESC,
        lc.data_lancamento DESC,
        lc.id_lancamento_cartao DESC
");

$lancamentos = $stmtLancamentos->fetchAll(
    PDO::FETCH_ASSOC
);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Lançamentos de Compras com Cartões de Crédito
</title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f7f7f7;
    color: #222;
}

.container {
    max-width: 1250px;
    margin: 0 auto;
}

.bloco {
    background: #ffffff;
    border: 1px solid #dddddd;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 22px;
}

h2 {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(260px, 1fr));
    gap: 15px 22px;
}

.campo {
    display: flex;
    flex-direction: column;
}

.campo label {
    font-weight: bold;
    margin-bottom: 5px;
}

input,
select {
    box-sizing: border-box;
    width: 100%;
    padding: 9px;
    border: 1px solid #bbbbbb;
    border-radius: 4px;
    font-size: 15px;
}

.acoes-formulario {
    margin-top: 18px;
    display: flex;
    gap: 10px;
}

button,
.botao {
    display: inline-block;
    border: none;
    border-radius: 4px;
    padding: 9px 16px;
    font-size: 15px;
    cursor: pointer;
    text-decoration: none;
}

button {
    background: #2867a5;
    color: #ffffff;
}

.botao-cancelar {
    background: #777777;
    color: #ffffff;
}

.mensagem {
    padding: 12px;
    margin-bottom: 18px;
    border-radius: 4px;
}

.mensagem-sucesso {
    background: #e9f7eb;
    border: 1px solid #83bf8b;
    color: #205b28;
}

.mensagem-erro {
    background: #fdeaea;
    border: 1px solid #d99090;
    color: #842525;
}

.tabela-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
}

th,
td {
    padding: 9px;
    border: 1px solid #cccccc;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #eeeeee;
}

.valor {
    text-align: right;
    white-space: nowrap;
}

.acoes {
    white-space: nowrap;
}

.acoes a {
    margin-right: 10px;
}

.status {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: bold;
}

.status-aberto {
    background: #fff3cd;
    color: #765b00;
}

.status-faturado {
    background: #dbeafe;
    color: #174a7e;
}

.status-pago {
    background: #dff3e3;
    color: #246230;
}

.status-cancelado {
    background: #ececec;
    color: #555555;
}

.bloqueado {
    color: #777777;
    font-style: italic;
}

.sem-registros {
    text-align: center;
    padding: 22px;
    color: #666666;
}

@media (max-width: 750px) {

    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

</head>

<body>

<div class="container">

    <?php if ($mensagem !== ''): ?>

        <?php
        $classeMensagem = '';

        if ($tipoMensagem === 'sucesso') {
            $classeMensagem = 'mensagem-sucesso';
        } else {
            $classeMensagem = 'mensagem-erro';
        }
        ?>

        <div class="mensagem <?= $classeMensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>

    <?php endif; ?>

    <div class="bloco">

        <h2>
            <?= $editar ? 'Editar' : 'Novo' ?>
            Lançamento de Cartão
        </h2>

        <form method="post">

            <input
                type="hidden"
                name="id"
                value="<?= htmlspecialchars(
                    isset($editar['id_lancamento_cartao'])
                        ? $editar['id_lancamento_cartao']
                        : ''
                ) ?>"
            >

            <div class="form-grid">

                <div class="campo">

                    <label for="documento_numero">
                        Documento
                    </label>

                    <input
                        type="text"
                        id="documento_numero"
                        name="documento_numero"
                        maxlength="50"
                        placeholder="Número, código ou NSU"
                        value="<?= htmlspecialchars(
                            isset($editar['documento_numero'])
                                ? $editar['documento_numero']
                                : ''
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="data_lancamento">
                        Data do lançamento
                    </label>

                    <input
                        type="date"
                        id="data_lancamento"
                        name="data_lancamento"
                        required
                        value="<?= htmlspecialchars(
                            isset($editar['data_lancamento'])
                                ? $editar['data_lancamento']
                                : date('Y-m-d')
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="descricao">
                        Descrição
                    </label>

                    <input
                        type="text"
                        id="descricao"
                        name="descricao"
                        maxlength="150"
                        required
                        placeholder="Descrição da compra"
                        value="<?= htmlspecialchars(
                            isset($editar['descricao'])
                                ? $editar['descricao']
                                : ''
                        ) ?>"
                    >

                </div>

                <div class="campo">

                    <label for="id_cartao">
                        Cartão
                    </label>

                    <select
                        id="id_cartao"
                        name="id_cartao"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($cartoes as $cartao): ?>

                            <?php
                            $cartaoSelecionado = '';

                            if (
                                isset($editar['id_cartao'])
                                && (int) $editar['id_cartao']
                                    === (int) $cartao['id_cartao']
                            ) {
                                $cartaoSelecionado = 'selected';
                            }
                            ?>

                            <option
                                value="<?= (int) $cartao['id_cartao'] ?>"
                                <?= $cartaoSelecionado ?>
                            >
                                <?= htmlspecialchars(
                                    $cartao['descricao']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label for="id_grupo">
                        Grupo
                    </label>

                    <select
                        id="id_grupo"
                        name="id_grupo"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($grupos as $grupo): ?>

                            <?php
                            $grupoSelecionado = '';

                            if (
                                isset($editar['id_grupo'])
                                && (int) $editar['id_grupo']
                                    === (int) $grupo['id_grupo']
                            ) {
                                $grupoSelecionado = 'selected';
                            }
                            ?>

                            <option
                                value="<?= (int) $grupo['id_grupo'] ?>"
                                <?= $grupoSelecionado ?>
                            >
                                <?= htmlspecialchars(
                                    $grupo['descricao']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="campo">

                    <label for="valor">
                        Valor
                    </label>

                    <input
                        type="number"
                        id="valor"
                        name="valor"
                        required
                        min="0.01"
                        step="0.01"
                        placeholder="0,00"
                        value="<?php
                        if (isset($editar['valor'])) {
                            echo htmlspecialchars(
                                number_format(
                                    (float) $editar['valor'],
                                    2,
                                    '.',
                                    ''
                                )
                            );
                        }
                        ?>"
                    >

                </div>

            </div>

            <div class="acoes-formulario">

                <button type="submit">
                    <?= $editar ? 'Atualizar' : 'Salvar' ?>
                </button>

                <?php if ($editar): ?>

                    <a
                        class="botao botao-cancelar"
                        href="<?= BASE_URL ?>cadastros/cartoes.php"
                    >
                        Cancelar edição
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>

    <div class="bloco">

        <h2>Lista de lançamentos</h2>

        <div class="tabela-container">

            <table>

                <thead>

                    <tr>
                        <th>Documento</th>
                        <th>Lançamento</th>
                        <th>Descrição</th>
                        <th>Cartão</th>
                        <th>Grupo</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (count($lancamentos) === 0): ?>

                        <tr>
                            <td
                                colspan="8"
                                class="sem-registros"
                            >
                                Nenhum lançamento de cartão cadastrado.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($lancamentos as $lancamento): ?>

                            <?php
                            $status = isset($lancamento['status'])
                                ? $lancamento['status']
                                : 'Aberto';

                            /*
                             * Compatível com PHP 7.4.
                             * Não utiliza o comando match.
                             */
                            $classeStatus = 'status-aberto';

                            if ($status === 'Faturado') {
                                $classeStatus = 'status-faturado';

                            } elseif ($status === 'Pago') {
                                $classeStatus = 'status-pago';

                            } elseif ($status === 'Cancelado') {
                                $classeStatus = 'status-cancelado';
                            }

                            $podeAlterar = false;

                            if (
                                $status === 'Aberto'
                                && empty(
                                    $lancamento[
                                        'id_lancamento_fatura'
                                    ]
                                )
                            ) {
                                $podeAlterar = true;
                            }

                            $dataFormatada = '';

                            if (
                                !empty(
                                    $lancamento['data_lancamento']
                                )
                            ) {
                                $dataObjeto = DateTime::createFromFormat(
                                    'Y-m-d',
                                    $lancamento[
                                        'data_lancamento'
                                    ]
                                );

                                if ($dataObjeto) {
                                    $dataFormatada =
                                        $dataObjeto->format('d/m/Y');
                                } else {
                                    $dataFormatada =
                                        $lancamento[
                                            'data_lancamento'
                                        ];
                                }
                            }
                            ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        isset(
                                            $lancamento[
                                                'documento_numero'
                                            ]
                                        )
                                            ? $lancamento[
                                                'documento_numero'
                                            ]
                                            : ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $dataFormatada
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $lancamento['descricao']
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        isset(
                                            $lancamento[
                                                'cartao_descricao'
                                            ]
                                        )
                                            ? $lancamento[
                                                'cartao_descricao'
                                            ]
                                            : ''
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        isset(
                                            $lancamento[
                                                'grupo_descricao'
                                            ]
                                        )
                                            ? $lancamento[
                                                'grupo_descricao'
                                            ]
                                            : ''
                                    ) ?>
                                </td>

                                <td class="valor">
                                    R$
                                    <?= number_format(
                                        (float) $lancamento['valor'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>

                                    <span
                                        class="status <?= $classeStatus ?>"
                                    >
                                        <?= htmlspecialchars($status) ?>
                                    </span>

                                </td>

                                <td class="acoes">

                                    <?php if ($podeAlterar): ?>

                                        <a
                                            href="?edit=<?= (int)
                                                $lancamento[
                                                    'id_lancamento_cartao'
                                                ]
                                            ?>"
                                        >
                                            Editar
                                        </a>

                                        <a
                                            href="?delete=<?= (int)
                                                $lancamento[
                                                    'id_lancamento_cartao'
                                                ]
                                            ?>"
                                            onclick="return confirm(
                                                'Deseja realmente excluir este lançamento?'
                                            );"
                                        >
                                            Excluir
                                        </a>

                                    <?php else: ?>

                                        <span class="bloqueado">
                                            Bloqueado
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>