<?php
/**
 * index.php — el FRONT CONTROLLER de la API Facturas v1.
 *
 * TODAS las peticiones entran por aquí (el servidor se arranca con
 * `php -S 0.0.0.0:8022 index.php`, así que no hay que configurar rewrite).
 * Este archivo hace UNA cosa: mirar el método y la ruta, y entregar la
 * petición al controlador que corresponde. Nada de SQL, nada de negocio.
 *
 * Rutas de la v1 (contratos exactos en docs 6_contracts.md):
 *   GET    /                          → diagnóstico
 *   GET    /api/producto[?limite=N]   → listar
 *   POST   /api/producto              → crear
 *   GET    /api/producto/{codigo}     → obtener uno
 *   PUT    /api/producto/{codigo}     → reemplazo completo
 *   PATCH  /api/producto/{codigo}     → actualización parcial
 *   DELETE /api/producto/{codigo}     → eliminar
 */
declare(strict_types=1);

// El inventario del proyecto, a la vista (sin autoloader mágico):
require_once __DIR__ . '/servicios/ensamblador.php';
require_once __DIR__ . '/controladores/ControladorProducto.php';

// Toda respuesta de esta API es JSON:
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------
// 1. ¿Qué piden? método HTTP + ruta (sin query string)
// ----------------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// El body JSON se lee UNA vez (php://input solo se puede leer una vez):
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// El controlador se arma por petición; solo el ensamblador conoce clases concretas:
$controlador = new ControladorProducto(crearServicioProducto());

// ----------------------------------------------------------------------
// 2. Enrutar
// ----------------------------------------------------------------------

// GET / — diagnóstico (usable como healthcheck)
if ($ruta === '/' && $metodo === 'GET') {
    echo json_encode([
        'mensaje'   => 'API Facturas funcionando',
        'version'   => 'v1',
        'contratos' => 'docs/spec_kit/versiones/v1_producto_mariadb/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    return;
}

// /api/producto — colección
if ($ruta === '/api/producto') {
    match ($metodo) {
        'GET'   => $controlador->listar(),
        'POST'  => $controlador->crear($body),
        default => responderNoPermitido(),
    };
    return;
}

// /api/producto/{codigo} — un recurso concreto
if (preg_match('#^/api/producto/([^/]+)$#', $ruta, $coincidencias) === 1) {
    $codigo = urldecode($coincidencias[1]);
    match ($metodo) {
        'GET'    => $controlador->obtener($codigo),
        'PUT'    => $controlador->reemplazar($codigo, $body),
        'PATCH'  => $controlador->actualizar($codigo, $body),
        'DELETE' => $controlador->eliminar($codigo),
        default  => responderNoPermitido(),
    };
    return;
}

// Nada coincidió: 404 de ruta (distinto del 404 de "producto no existe")
http_response_code(404);
echo json_encode([
    'estado' => 404, 'mensaje' => 'Ruta no encontrada.', 'detalle' => "$metodo $ruta",
], JSON_UNESCAPED_UNICODE);

// ----------------------------------------------------------------------
function responderNoPermitido(): void
{
    http_response_code(405);
    echo json_encode([
        'estado' => 405, 'mensaje' => 'Método no permitido para esta ruta.',
    ], JSON_UNESCAPED_UNICODE);
}
