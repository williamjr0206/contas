<?php
require __DIR__ . '/config/database.php';

$senha = '123456';
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nome_usuario, email, senha, perfil, ativo)
     VALUES (:nome_usuario, :email, :senha, :perfil, 1)"
);

$nome   = 'Administrador';
$email  = 'adm@teste.com';
$perfil = 'ADMIN';

$stmt->bindParam(':nome_usuario', $nome);
$stmt->bindParam(':email', $email);
$stmt->bindParam(':senha', $hash);
$stmt->bindParam(':perfil', $perfil);

if ($stmt->execute()) {
    echo "Usuário criado com sucesso!<br>";
    echo "Email: $email<br>";
    echo "Senha: $senha";
} else {
    echo "Erro ao criar usuário";
}
