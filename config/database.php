<?php
$hostAtual = $_SERVER['HTTP_HOST'] ?? 'localhost';

$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

/*
|--------------------------------------------------------------------------
| BASE_URL
|--------------------------------------------------------------------------
| Local:  http://localhost/cristaos/
| Milbr:  https://seusite.com/cristaos/
|--------------------------------------------------------------------------
*/
$pastaProjeto = '/cristaos/';

if (!defined('BASE_URL')) {
    define('BASE_URL', $protocolo . $host . $pastaProjeto);
}

$ambiente = ($hostAtual === 'localhost') ? 'local' : 'prod';

$config = require __DIR__ . "/database.$ambiente.php";

$servername = $config['host'];
$username   = $config['user'];
$password   = $config['pass'];
$database   = $config['db'];

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$database;charset=utf8",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}