<?php
declare(strict_types=1);
require __DIR__.'/../app/db.php';
require __DIR__.'/../app/inventario.php';

function e(mixed $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function campo(string $nombre): string
{
    return is_string($_POST[$nombre] ?? null) ? $_POST[$nombre] : '';
}
function urlFicha(string $sku): string
{
    return '?vista=producto&sku='.rawurlencode($sku);
}
session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Lax']);
$_SESSION['token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['token'];
$aviso = $_SESSION['aviso'] ?? null;
unset($_SESSION['aviso']);
$vista = is_string($_GET['vista'] ?? null) ? $_GET['vista'] : 'productos';
$sku = is_string($_GET['sku'] ?? null) ? strtoupper(trim($_GET['sku'])) : '';
$busqueda = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$titulos = ['productos'=>'Productos','producto'=>'Ficha de producto','nuevo'=>'Nuevo producto',
    'escaner'=>'Escáner','historial'=>'Historial'];
if (!isset($titulos[$vista])) { $vista = 'productos'; }
$errorBD = false;
$producto = null;
try {
    $db = conexion();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = campo('accion');
        $destino = $accion === 'crear' ? '?vista=nuevo' : urlFicha(campo('sku'));
        try {
            if (!hash_equals($token,campo('token'))) {
                throw new DomainException('La sesión cambió. Vuelve a enviar el formulario.');
            }
            if ($accion === 'crear') {
                $nuevoSku = codigo(campo('sku'));
                crearProducto($db, $nuevoSku, campo('nombre'), campo('ubicacion'));
                $destino = urlFicha($nuevoSku);
                $mensaje = 'Producto creado. Ya puedes registrar su primera entrada.';
            } elseif ($accion === 'mover') {
                $cantidad = filter_var(campo('cantidad'), FILTER_VALIDATE_INT);
                $saldo = registrarMovimiento($db,campo('sku'),campo('tipo'),$cantidad ?: 0,campo('operacion'));
                $mensaje = "Movimiento guardado. Saldo del movimiento: $saldo unidades.";
            } else {
                throw new DomainException('Operación desconocida.');
            }
            $_SESSION['aviso'] = ['ok',$mensaje];
        } catch (DomainException $error) {
            $_SESSION['aviso'] = ['error',$error->getMessage()];
        }
        header('Location: '.$destino, true, 303);
        exit;
    }
    if ($vista === 'productos') {
        $consulta = $db->prepare('SELECT * FROM productos
            WHERE LOCATE(?, nombre)>0 OR LOCATE(?, sku)>0 ORDER BY nombre');
        $consulta->execute([$busqueda,$busqueda]);
        $productos = $consulta->fetchAll();
        $totales = $db->query('SELECT COUNT(*) AS productos, COALESCE(SUM(stock),0) AS unidades,
            COUNT(CASE WHEN stock=0 THEN 1 END) AS agotados FROM productos')->fetch();
    } elseif ($vista === 'producto') {
        $consulta = $db->prepare('SELECT * FROM productos WHERE sku=?');
        $consulta->execute([$sku]);
        $producto = $consulta->fetch();
        if (!$producto) { http_response_code(404); }
    } elseif ($vista === 'historial') {
        $consulta = $db->prepare('SELECT m.*,p.nombre,p.sku FROM movimientos m
            JOIN productos p ON p.id=m.producto_id WHERE (CHAR_LENGTH(?)=0 OR p.sku=?) ORDER BY m.id DESC');
        $consulta->execute([$sku,$sku]);
        $movimientos = $consulta->fetchAll();
    }
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(503);
    $errorBD = true;
}
?>
<!doctype html><html lang="es-PE"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($titulos[$vista])?> · Almacén QR</title>
<link rel="stylesheet" href="app.css"></head><body>
<header><div class="bar"><a class="marca" href="?"><span class="marca-icon" aria-hidden="true">▦</span> Almacén QR</a>
<nav aria-label="Secciones"><?php foreach (['productos'=>'Productos','escaner'=>'Escáner','historial'=>'Historial'] as $id=>$nombre): ?>
<a href="?vista=<?=$id?>" <?=($vista===$id || ($id==='productos' && in_array($vista,['nuevo','producto'])))?'aria-current="page"':''?>><?=$nombre?></a><?php endforeach ?></nav></div></header>
<main>
<?php if ($aviso): ?><p role="status" class="aviso <?=e($aviso[0])?>"><?=e($aviso[1])?></p><?php endif ?>
<?php if ($errorBD): ?><h1>Inventario sin conexión</h1><p>Comprueba que MySQL esté activo, importa el archivo SQL y revisa la conexión indicada en el README.</p>
<?php elseif ($vista==='productos'): ?>
<div class="titulo"><h1>Productos</h1><a href="?vista=nuevo" class="btn">＋ Nuevo producto</a></div>
<section class="resumen" aria-label="Resumen del inventario">
<?php foreach (['productos'=>'Productos registrados','unidades'=>'Unidades en stock','agotados'=>'Sin existencias'] as $key=>$label): ?>
<div><span><?=$label?></span><strong><?=e($totales[$key])?></strong></div><?php endforeach ?></section>
<form class="buscar" method="get"><label for="q">Buscar en el catálogo</label><div class="fila"><input id="q" name="q" value="<?=e($busqueda)?>" placeholder="Nombre o código"><button class="sec">Buscar</button></div></form>
<div class="panel scroll"><table><thead><tr><th>Producto</th><th>Ubicación</th><th class="num">Stock</th><th>Ficha</th></tr></thead><tbody>
<?php foreach ($productos as $p): ?><tr><td><b><?=e($p['nombre'])?></b><small><?=e($p['sku'])?></small></td><td><?=e($p['ubicacion'])?></td><td class="num"><b><?=e($p['stock'])?></b></td><td><a href="<?=e(urlFicha($p['sku']))?>" aria-label="Abrir <?=e($p['nombre'])?>">Abrir</a></td></tr><?php endforeach ?>
</tbody></table><?php if (!$productos): ?><p class="vacio">No hay productos que coincidan.</p><?php endif ?></div>
<?php elseif ($vista==='nuevo'): ?><a href="?">Volver a productos</a><h1>Nuevo producto</h1>
<form method="post" class="panel form"><input type="hidden" name="accion" value="crear"><input type="hidden" name="token" value="<?=e($token)?>">
<label>Código único<input name="sku" required maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9&#45;]{0,31}" placeholder="TEC-004"></label>
<label>Nombre<input name="nombre" required maxlength="100" placeholder="Adaptador USB"></label>
<label>Ubicación<input name="ubicacion" required maxlength="60" placeholder="Estante A1"></label>
<p>El stock inicial es cero. Registra una entrada para dejar constancia de la cantidad recibida.</p><button>Crear producto</button></form>
<?php elseif ($vista==='producto' && !$producto): ?><h1>Producto no encontrado</h1><p>El código <?=e($sku)?> no está en el catálogo.</p><a href="?vista=escaner">Buscar otro código</a>
<?php elseif ($vista==='producto'): ?><a class="noprint" href="?">Volver a productos</a><h1><?=e($producto['nombre'])?></h1>
<div class="columnas"><section class="panel ficha"><p class="meta"><?=e($producto['sku'])?> · <?=e($producto['ubicacion'])?></p>
<p class="saldo">Stock disponible <strong><?=e($producto['stock'])?></strong></p>
<h2>Registrar movimiento</h2><form method="post" class="compact">
<input type="hidden" name="accion" value="mover"><input type="hidden" name="sku" value="<?=e($sku)?>">
<input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="operacion" value="<?=bin2hex(random_bytes(16))?>">
<div class="fila"><label>Movimiento<select name="tipo"><option value="salida">Salida</option><option value="entrada">Entrada</option></select></label>
<label>Unidades<input type="number" name="cantidad" required min="1" max="999999" value="1"></label></div>
<button>Guardar movimiento</button></form><a href="?vista=historial&amp;sku=<?=rawurlencode($sku)?>">Ver historial del producto</a></section>
<section class="panel etiqueta"><h2>Etiqueta QR</h2><div id="qr" data-sku="<?=e($sku)?>"></div><b><?=e($producto['nombre'])?></b><code><?=e($sku)?></code><button class="sec noprint" id="imprimir">Imprimir etiqueta</button></section></div>
<script src="vendor/qrcode.js" defer></script><script src="etiqueta.js" defer></script>
<?php elseif ($vista==='escaner'): ?><h1>Escáner</h1><div class="columnas">
<section class="panel ficha"><h2>Leer una etiqueta QR</h2><p>Apunta al código de un producto o carga una foto de la etiqueta.</p>
<video id="video" playsinline muted hidden></video><canvas id="lienzo" hidden></canvas>
<div class="acciones"><button id="camara">Activar cámara</button><button id="parar" class="sec" hidden>Detener</button></div>
<label class="archivo">Leer desde imagen<input id="imagen" type="file" accept="image/*"></label><p id="estado" role="status"></p></section>
<form method="get" class="panel form"><h2>Ingresar código</h2><input type="hidden" name="vista" value="producto">
<label>Código de la etiqueta<input required maxlength="32" name="sku" placeholder="TEC-001"></label><button>Buscar producto</button></form></div>
<script defer src="vendor/jsQR.js"></script><script defer src="escaner.js"></script>
<?php elseif ($vista==='historial'): ?><div class="titulo"><h1>Historial</h1><?php if ($sku!==''): ?><a href="?vista=historial">Ver todos</a><?php endif ?></div>
<p><?= $sku ? 'Movimientos de '.e($sku) : 'Entradas y salidas, de la más reciente a la más antigua.' ?></p>
<div class="panel scroll"><table><thead><tr><th>Fecha · Lima</th><th>Producto</th><th>Movimiento</th><th class="num">Cantidad</th><th class="num">Saldo</th></tr></thead><tbody>
<?php foreach ($movimientos as $m): ?><tr><td><?=e(date('d/m/Y H:i',strtotime($m['fecha'])))?></td><td><a href="<?=e(urlFicha($m['sku']))?>"><?=e($m['nombre'])?></a></td><td><span class="tipo <?=e($m['tipo'])?>"><?=e(ucfirst($m['tipo']))?></span></td><td class="num"><?=e($m['cantidad'])?></td><td class="num"><?=e($m['saldo'])?></td></tr><?php endforeach ?></tbody></table>
<?php if (!$movimientos): ?><p class="vacio">Todavía no hay movimientos.</p><?php endif ?></div>
<?php endif ?></main></body></html>
