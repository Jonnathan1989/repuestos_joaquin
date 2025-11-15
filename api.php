<?php
// api.php - API COMPLETA JOAQUÍN REPUESTOS (DESCRIPCIÓN INCLUIDA)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// LOGS EN ARCHIVO
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

require 'config.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // === REPUESTOS ===
        case 'listar_repuestos':
            $stmt = $pdo->query("SELECT * FROM repuestos ORDER BY nombre");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'agregar_repuesto':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                echo json_encode(['error' => 'JSON inválido']);
                error_log("agregar_repuesto: JSON inválido");
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO repuestos (nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?)");
            $stmt->execute([$input['nombre'], $input['descripcion'], $input['precio'], $input['stock']]);
            echo json_encode(['success' => true]);
            break;

        case 'editar_repuesto':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['id'])) {
                echo json_encode(['error' => 'Datos incompletos']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE repuestos SET nombre=?, descripcion=?, precio=?, stock=? WHERE id=?");
            $stmt->execute([$input['nombre'], $input['descripcion'], $input['precio'], $input['stock'], $input['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'eliminar_repuesto':
            $id = $_GET['id'] ?? 0;
            if ($id <= 0) {
                echo json_encode(['error' => 'ID inválido']);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM repuestos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'exportar_inventario':
            $stmt = $pdo->query("SELECT nombre, descripcion, precio, stock FROM repuestos ORDER BY nombre");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = "inventario_joaquin_" . date('d-m-Y') . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($output, ['Nombre', 'Descripción', 'Precio', 'Stock'], ',');

            foreach ($data as $r) {
                fputcsv($output, [
                    $r['nombre'],
                    $r['descripcion'],
                    number_format($r['precio'], 0, ',', '.'),
                    $r['stock']
                ], ',');
            }
            exit;

        // === VENTAS ===
        case 'ventas':
            $stmt = $pdo->query("
                SELECT 
                    v.id,
                    DATE_FORMAT(v.fecha_venta, '%d/%m/%Y %h:%i %p') AS fecha_venta,
                    COALESCE(r.nombre, v.nombre_producto, 'Sin nombre') AS nombre_producto,
                    COALESCE(v.descripcion, '') AS descripcion,
                    v.cantidad,
                    v.precio_unitario,
                    v.total
                FROM ventas v 
                LEFT JOIN repuestos r ON v.repuesto_id = r.id 
                ORDER BY v.fecha_venta DESC
            ");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'guardar_venta':
            error_log("guardar_venta: INICIO - Método: " . $_SERVER['REQUEST_METHOD']);

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Método no permitido']);
                error_log("guardar_venta: ERROR - Método no es POST");
                break;
            }

            $raw = file_get_contents('php://input');
            error_log("guardar_venta: RAW INPUT: " . $raw);

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'JSON malformado: ' . json_last_error_msg()]);
                error_log("guardar_venta: JSON ERROR: " . json_last_error_msg());
                break;
            }

            if (!$input) {
                echo json_encode(['error' => 'Datos vacíos']);
                error_log("guardar_venta: INPUT vacío después de json_decode");
                break;
            }

            error_log("guardar_venta: INPUT parseado: " . print_r($input, true));

            $repuesto_id = $input['repuesto_id'] ?? null;
            $nombre_producto = $input['nombre_producto'] ?? 'Producto sin nombre';
            $descripcion = $input['descripcion'] ?? '';
            $cantidad = (int)($input['cantidad'] ?? 0);
            $precio_unitario = (float)($input['precio_unitario'] ?? 0);
            $total = $cantidad * $precio_unitario;

            if ($cantidad <= 0 || $precio_unitario <= 0) {
                echo json_encode(['error' => 'Cantidad o precio inválidos']);
                error_log("guardar_venta: ERROR - Cantidad: $cantidad, Precio: $precio_unitario");
                break;
            }

            // OBTENER DESCRIPCIÓN DEL REPUESTO SI NO VIENE EN INPUT
            if (!$descripcion && $repuesto_id) {
                $stmt = $pdo->prepare("SELECT descripcion FROM repuestos WHERE id = ?");
                $stmt->execute([$repuesto_id]);
                $descripcion = $stmt->fetchColumn() ?: '';
                error_log("guardar_venta: Descripción obtenida de BD: '$descripcion'");
            }

            // RESTAR STOCK
            if ($repuesto_id) {
                $stmt = $pdo->prepare("UPDATE repuestos SET stock = stock - ? WHERE id = ? AND stock >= ?");
                $stmt->execute([$cantidad, $repuesto_id, $cantidad]);
                if ($stmt->rowCount() === 0) {
                    echo json_encode(['error' => 'Stock insuficiente o repuesto no existe']);
                    error_log("guardar_venta: ERROR - Stock insuficiente para ID $repuesto_id");
                    break;
                }
                error_log("guardar_venta: Stock restado correctamente para ID $repuesto_id");
            }

            // GUARDAR VENTA CON DESCRIPCIÓN
            $stmt = $pdo->prepare("
                INSERT INTO ventas 
                (repuesto_id, nombre_producto, descripcion, cantidad, precio_unitario, total, fecha_venta) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$repuesto_id, $nombre_producto, $descripcion, $cantidad, $precio_unitario, $total]);

            $venta_id = $pdo->lastInsertId();
            error_log("guardar_venta: VENTA GUARDADA - ID: $venta_id, Descripción: '$descripcion'");

            echo json_encode(['success' => true, 'venta_id' => $venta_id]);
            break;

        case 'borrar_ventas':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $pdo->exec("TRUNCATE TABLE ventas");
                echo json_encode(['success' => true]);
                error_log("borrar_ventas: Historial borrado");
            }
            break;

        case 'exportar_ventas':
            $stmt = $pdo->query("
                SELECT 
                    DATE_FORMAT(v.fecha_venta, '%d/%m/%Y %h:%i %p') AS fecha_venta,
                    COALESCE(r.nombre, v.nombre_producto, 'Sin nombre') AS nombre_producto,
                    COALESCE(v.descripcion, '') AS descripcion,
                    v.cantidad,
                    v.precio_unitario,
                    v.total
                FROM ventas v 
                LEFT JOIN repuestos r ON v.repuesto_id = r.id 
                ORDER BY v.fecha_venta DESC
            ");
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = "ventas_joaquin_" . date('d-m-Y') . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Fecha', 'Producto', 'Descripción', 'Cant.', 'Precio Unit.', 'Total'], ',');

            foreach ($ventas as $v) {
                fputcsv($output, [
                    $v['fecha_venta'],
                    $v['nombre_producto'],
                    $v['descripcion'],
                    $v['cantidad'],
                    number_format($v['precio_unitario'], 0, ',', '.'),
                    number_format($v['total'], 0, ',', '.')
                ], ',');
            }
            exit;

        default:
            echo json_encode(['error' => 'Acción no válida: ' . $action]);
            error_log("API: Acción desconocida: $action");
    }
} catch (Exception $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    echo json_encode(['error' => $msg]);
    error_log("API ERROR: $msg");
}
?>