<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/inventario.php';

function comprobar(bool $ok, string $paso): void {
    if (!$ok) { throw new RuntimeException("Falló: $paso"); }
    echo "OK: $paso", PHP_EOL;
}

$db = conexion();
$sku = 'TEST-'.strtoupper(bin2hex(random_bytes(5)));
crearProducto($db, $sku, 'Producto temporal de prueba', 'Pruebas');
try {
    comprobar(registrarMovimiento($db,$sku,'entrada',3,bin2hex(random_bytes(16)))===3, 'entrada inicial de tres');
    $clave = bin2hex(random_bytes(16));
    comprobar(registrarMovimiento($db,$sku,'salida',2,$clave)===1, 'salida de dos');
    comprobar(registrarMovimiento($db,$sku,'salida',2,$clave)===1, 'reintento con la misma clave');
    $rechazado = false;
    try { registrarMovimiento($db,$sku,'salida',2,bin2hex(random_bytes(16))); }
    catch (DomainException $error) { $rechazado = str_contains($error->getMessage(),'Stock insuficiente'); }
    comprobar($rechazado,'rechazo de una salida sin stock');
    $leer = $db->prepare('SELECT p.stock,COUNT(m.id) AS total FROM productos p
        JOIN movimientos m ON m.producto_id=p.id WHERE p.sku=? GROUP BY p.id,p.stock');
    $leer->execute([$sku]);
    $fila = $leer->fetch();
    comprobar((int)$fila['stock']===1 && (int)$fila['total']===2,'saldo e historial consistentes');
} finally {
    $db->beginTransaction();
    $db->prepare('DELETE FROM movimientos WHERE producto_id=(SELECT id FROM productos WHERE sku=?)')->execute([$sku]);
    $db->prepare('DELETE FROM productos WHERE sku=?')->execute([$sku]);
    $db->commit();
}
