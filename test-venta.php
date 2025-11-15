<?php
// test-venta.php - PRUEBA DE VENTA CON ERRORES ACTIVADOS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

echo "<h3>PRUEBA DE VENTA</h3>";

$data = [
    'repuesto_id' => 1,
    'nombre_producto' => 'Carburador',
    'cantidad' => 1,
    'precio_unitario' => 125000
];

echo "<pre>DATOS ENVIADOS:\n";
print_r($data);
echo "</pre>";

$ch = curl_init('http://localhost/repuestos_joaquin/api.php?action=guardar_venta');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<pre>RESPUESTA HTTP: $http_code\n";
echo "RESPUESTA:\n$response";
echo "</pre>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    if (isset($result['success'])) {
        echo "<h4 style='color:green'>VENTA GUARDADA CON ÉXITO!</h4>";
    } else {
        echo "<h4 style='color:red'>ERROR EN API: " . ($result['error'] ?? 'Desconocido') . "</h4>";
    }
} else {
    echo "<h4 style='color:red'>ERROR HTTP: $http_code</h4>";
}
?>