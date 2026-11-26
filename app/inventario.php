<?php
declare(strict_types=1);

function codigo(string $texto): string
{
    $sku = strtoupper(trim($texto));
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{0,31}$/D', $sku)) {
        throw new DomainException('Usa un código de hasta 32 letras, números o guiones.');
    }
    return $sku;
}

function crearProducto(PDO $db, string $sku, string $nombre, string $ubicacion): void
{
    $sku = codigo($sku);
    $nombre = trim($nombre);
    $ubicacion = trim($ubicacion);
    if (!preg_match('/^.{1,100}$/usD', $nombre) || !preg_match('/^.{1,60}$/usD', $ubicacion)) {
        throw new DomainException('Revisa el nombre (1–100 caracteres) y la ubicación (1–60).');
    }
    try {
        $stmt = $db->prepare('INSERT INTO productos (sku,nombre,ubicacion) VALUES (?,?,?)');
        $stmt->execute([$sku,$nombre,$ubicacion]);
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('Ya existe un producto con ese código.');
        }
        throw $error;
    }
}

// Cada operación registra stock e historial juntos, o deja ambos como estaban.
function registrarMovimiento(PDO $db, string $sku, string $tipo, int $cantidad, string $operacion): int
{
    $sku = codigo($sku);
    if (!in_array($tipo, ['entrada','salida'], true) || $cantidad < 1 || $cantidad > 999999) {
        throw new DomainException('Elige entrada o salida e indica entre 1 y 999999 unidades.');
    }
    if (!preg_match('/^[a-f0-9]{32}$/D', $operacion)) {
        throw new DomainException('Vuelve a abrir la ficha del producto.');
    }

    $db->beginTransaction();
    try {
        $buscar = $db->prepare('SELECT id, stock FROM productos WHERE sku = ? FOR UPDATE');
        $buscar->execute([$sku]);
        $producto = $buscar->fetch();
        if (!$producto) {
            throw new DomainException('Este código no pertenece al catálogo.');
        }

        // Un doble clic o el reintento de la misma petición no descuenta dos veces.
        $anterior = $db->prepare('SELECT * FROM movimientos WHERE operacion = ?');
        $anterior->execute([$operacion]);
        $previo = $anterior->fetch();
        if ($previo) {
            if ((int)$previo['producto_id'] !== (int)$producto['id'] ||
                $previo['tipo'] !== $tipo || (int)$previo['cantidad'] !== $cantidad) {
                throw new DomainException('Esta operación ya se usó. Vuelve a abrir la ficha.');
            }
            $db->commit();
            return (int)$previo['saldo'];
        }

        $stock = (int)$producto['stock'];
        if ($tipo === 'salida' && $cantidad > $stock) {
            throw new DomainException("Stock insuficiente. Quedan $stock unidades.");
        }
        $saldo = $tipo === 'entrada' ? $stock + $cantidad : $stock - $cantidad;
        if ($saldo > 2147483647) {
            throw new DomainException('El saldo supera el máximo permitido.');
        }

        $actualizar = $db->prepare('UPDATE productos SET stock = ? WHERE id = ?');
        $actualizar->execute([$saldo, $producto['id']]);
        $guardar = $db->prepare('INSERT INTO movimientos
            (operacion, producto_id, tipo, cantidad, saldo) VALUES (?, ?, ?, ?, ?)');
        $guardar->execute([$operacion, $producto['id'], $tipo, $cantidad, $saldo]);
        $db->commit();
        return $saldo;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
