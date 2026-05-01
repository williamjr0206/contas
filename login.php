<?php
session_start();
ini_set('display_errors',1);
error_reporting(E_ALL);

require __DIR__ . '/config/database.php';

$erro='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=$_POST['email'] ?? '';
    $senha=$_POST['senha'] ?? '';

    $stmt=$pdo->prepare("SELECT * FROM usuarios WHERE email=?");
    $stmt->execute([$email]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);

    if($user && password_verify($senha,$user['senha'])){
        $_SESSION['id_usuario']=$user['id_usuario'];
        $_SESSION['nome']=$user['nome_usuario'];
        $_SESSION['perfil']=$user['perfil'];

        header("Location: index.php");
        exit;
    }else{
        $erro="Usuário ou senha inválidos";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; }
        .login-box {
            width: 320px;
            margin: 100px auto;
            background: #fff;
            padding: 20px;
            border-radius: 5px;
        }
        input, button {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
        }
        .erro { color: red; margin-top: 10px; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Login</h2>

    <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
