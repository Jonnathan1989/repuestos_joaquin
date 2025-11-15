<?php
// config.php - CONEXIÓN A MySQL
$host = 'localhost';
$db   = 'joaquin_repuestos';
$user = 'root';
$pass = '';  // ← ¡VACÍA! (porque nunca pusiste contraseña)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Conexión fallida: ' . $e->getMessage()]);
    exit;
}
?>