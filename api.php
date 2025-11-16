<?php
// api.php - JOAQUÍN REPUESTOS - MySQL (FUNCIONA PERFECTO)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$db   = 'joaquin_repuestos';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // === LISTAR REPUESTOS ===
    case 'listar_repuestos':
        $stmt = $pdo->query("SELECT * FROM repuestos ORDER BY nombre");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // === AGREGAR REPUESTO ===
    case 'agregar_repuesto':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO repuestos (nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$data['nombre'], $data['descripcion'], $data['precio'], $data['stock']]);
        echo json_encode(['success' => $result]);
        break;

    // === EDITAR REPUESTO ===
    case 'editar_repuesto':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE repuestos SET nombre=?, descripcion=?, precio=?, stock=? WHERE id=?");
        $result = $stmt->execute([$data['nombre'], $data['descripcion'], $data['precio'], $data['stock'], $data['id']]);
        echo json_encode(['success' => $result]);
        break;

    // === ELIMINAR REPUESTO ===
    case 'eliminar_repuesto':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM repuestos WHERE id = ?");
        $result = $stmt->execute([$id]);
        echo json_encode(['success' => $result]);
        break;

    // === BUSCAR CLIENTES ===
    case 'buscar_clientes':
        $q = $_GET['q'] ?? '';
        $stmt = $pdo->prepare("SELECT id, nombre, telefono FROM clientes WHERE nombre LIKE ? LIMIT 10");
        $stmt->execute(["%$q%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // === AGREGAR CLIENTE ===
    case 'agregar_cliente':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)");
        $stmt->execute([$data['nombre'], $data['telefono'] ?? '', $data['email'] ?? '']);
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    // === GUARDAR VENTA ===
    case 'guardar_venta':
        $data = json_decode(file_get_contents('php://input'), true);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO ventas (repuesto_id, nombre_producto, descripcion, cantidad, precio_unitario, cliente_id, fecha_venta) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $data['repuesto_id'],
                $data['nombre_producto'],
                $data['descripcion'],
                $data['cantidad'],
                $data['precio_unitario'],
                $data['cliente_id']
            ]);

            $stmt = $pdo->prepare("UPDATE repuestos SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$data['cantidad'], $data['repuesto_id']]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // === LISTAR VENTAS (PARA ventas.html) ===
    case 'ventas':
        $stmt = $pdo->query("
            SELECT 
                v.fecha_venta,
                v.nombre_producto,
                v.descripcion,
                v.cantidad,
                v.precio_unitario,
                (v.cantidad * v.precio_unitario) AS total,
                COALESCE(c.nombre, 'Sin cliente') AS cliente
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            ORDER BY v.fecha_venta DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // === BORRAR HISTORIAL ===
    case 'borrar_ventas':
        $stmt = $pdo->prepare("DELETE FROM ventas");
        $result = $stmt->execute();
        echo json_encode(['success' => $result]);
        break;

    // === EXPORTAR VENTAS A EXCEL ===
    case 'exportar_ventas':
        $stmt = $pdo->query("
            SELECT 
                v.fecha_venta AS Fecha,
                v.nombre_producto AS Producto,
                v.descripcion AS Descripcion,
                COALESCE(c.nombre, 'Sin cliente') AS Cliente,
                v.cantidad AS Cantidad,
                v.precio_unitario AS 'Precio Unitario',
                (v.cantidad * v.precio_unitario) AS Total
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            ORDER BY v.fecha_venta DESC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $output = fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ventas_joaquin.csv');
        echo "\xEF\xBB\xBF";
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) fputcsv($output, $row);
        }
        exit;

    // === EXPORTAR INVENTARIO ===
    case 'exportar_inventario':
        $stmt = $pdo->query("SELECT nombre, descripcion, precio, stock FROM repuestos");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $output = fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inventario_joaquin.csv');
        echo "\xEF\xBB\xBF";
        fputcsv($output, ['Nombre', 'Descripción', 'Precio', 'Stock']);
        foreach ($data as $row) fputcsv($output, $row);
        exit;

    // === LISTAR TODOS LOS CLIENTES CON ESTADÍSTICAS ===
    case 'listar_clientes':
        $stmt = $pdo->query("
            SELECT 
                c.id,
                c.nombre,
                c.telefono,
                c.email,
                COUNT(v.id) as total_compras,
                COALESCE(SUM(v.cantidad * v.precio_unitario), 0) as total_gastado
            FROM clientes c
            LEFT JOIN ventas v ON c.id = v.cliente_id
            GROUP BY c.id
            ORDER BY c.nombre
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // === OBTENER UN CLIENTE ===
    case 'obtener_cliente':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    // === EDITAR CLIENTE ===
    case 'editar_cliente':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("UPDATE clientes SET nombre=?, telefono=?, email=? WHERE id=?");
        $result = $stmt->execute([
            $data['nombre'],
            $data['telefono'] ?? '',
            $data['email'] ?? '',
            $data['id']
        ]);
        echo json_encode(['success' => $result]);
        break;

    // === ELIMINAR CLIENTE ===
    case 'eliminar_cliente':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $result = $stmt->execute([$id]);
        echo json_encode(['success' => $result]);
        break;

    // === HISTORIAL DE COMPRAS DE UN CLIENTE ===
    case 'historial_cliente':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT 
                fecha_venta,
                nombre_producto,
                cantidad,
                (cantidad * precio_unitario) as total
            FROM ventas
            WHERE cliente_id = ?
            ORDER BY fecha_venta DESC
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // === EXPORTAR CLIENTES A EXCEL ===
    case 'exportar_clientes':
        $stmt = $pdo->query("
            SELECT 
                c.nombre AS Nombre,
                c.telefono AS Telefono,
                c.email AS Email,
                COUNT(v.id) as 'Total Compras',
                COALESCE(SUM(v.cantidad * v.precio_unitario), 0) as 'Total Gastado'
            FROM clientes c
            LEFT JOIN ventas v ON c.id = v.cliente_id
            GROUP BY c.id
            ORDER BY c.nombre
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output = fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clientes_joaquin.csv');
        echo "\xEF\xBB\xBF";
        
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) fputcsv($output, $row);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
?>