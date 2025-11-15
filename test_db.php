<?php
// test_db.php - MUESTRA ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$host = 'localhost';
$db   = 'joaquin_repuestos';
$user = 'root';
$pass = '';

echo "<pre>PROBANDO CONEXIÓN...\n</pre>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h3 style='color:green'>CONEXIÓN EXITOSA A LA BASE DE DATOS</h3>";
} catch (PDOException $e) {
    echo "<h3 style='color:red'>ERROR DE CONEXIÓN</h3>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . " (línea " . $e->getLine() . ")</p>";
}
?>