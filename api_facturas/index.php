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

// "Modo estricto de tipos": si una función espera int y llega el string "5",
// PHP lanza error en vez de convertirlo por debajo de la mesa. DEBE ser la
// primera instrucción del archivo. Todos los archivos del proyecto lo llevan.
declare(strict_types=1);

// require_once = "carga este archivo PHP aquí (y solo una vez aunque se pida
// dos veces)". __DIR__ es la carpeta donde vive ESTE archivo — así las rutas
// funcionan sin importar desde dónde se ejecute. Es el inventario del
// proyecto a la vista, sin autoloader mágico:
require_once __DIR__ . '/servicios/ensamblador.php';
require_once __DIR__ . '/controladores/ControladorProducto.php';

// header() agrega un encabezado a la respuesta HTTP. Este le dice al cliente
// (navegador, requests, fetch…) que TODO lo que respondemos es JSON en UTF-8:
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------
// 1. ¿Qué piden? método HTTP + ruta (sin query string)
// ----------------------------------------------------------------------

// $_SERVER es un array que PHP llena solo en cada petición, con datos del
// servidor y de la petición. REQUEST_METHOD trae el verbo: GET, POST, PUT…
$metodo = $_SERVER['REQUEST_METHOD'];

// REQUEST_URI trae TODO lo pedido, ej. "/api/producto?limite=5".
// parse_url(..., PHP_URL_PATH) recorta y deja solo la ruta: "/api/producto"
// (el query string ?limite=5 lo leerá el controlador con $_GET).
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// El cuerpo (body) de la petición NO llega en una variable: hay que leerlo
// del flujo especial php://input — y solo se puede leer UNA vez, por eso se
// guarda aquí y se pasa a quien lo necesite.
//   file_get_contents  → lee el texto crudo del body (un JSON)
//   json_decode(..., true) → lo convierte a array asociativo de PHP
//   ?? []              → "si el resultado es null (JSON malo o vacío), usa []"
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// new crea el objeto controlador. Su dependencia (el servicio) NO la crea él:
// se la entrega crearServicioProducto(), la función del ensamblador — el
// único archivo del sistema que conoce clases concretas.
$controlador = new ControladorProducto(crearServicioProducto());

// ----------------------------------------------------------------------
// 2. Enrutar: comparar método + ruta y delegar al método del controlador
// ----------------------------------------------------------------------

// GET / — diagnóstico (usable como healthcheck)
if ($ruta === '/' && $metodo === 'GET') {
    // json_encode convierte un array de PHP a texto JSON; echo lo escribe en
    // la respuesta. JSON_UNESCAPED_UNICODE deja las tildes legibles
    // ("versión" y no "versión").
    echo json_encode([
        'mensaje'   => 'API Facturas funcionando',
        'version'   => 'v1',
        'contratos' => 'docs/spec_kit/versiones/v1_producto_mariadb/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    // return en el nivel principal del archivo = "terminamos, no sigas":
    return;
}

// /api/producto — la COLECCIÓN completa (sin código en la URL)
if ($ruta === '/api/producto') {
    // match compara $metodo contra cada opción y ejecuta SOLO la que
    // coincide (como un switch moderno: compara con ===, no necesita break).
    // default atrapa cualquier verbo no soportado aquí (PUT, DELETE…) → 405.
    match ($metodo) {
        'GET'   => $controlador->listar(),        // -> llama un método del objeto
        'POST'  => $controlador->crear($body),
        default => responderNoPermitido(),
    };
    return;
}

// /api/producto/{codigo} — UN recurso concreto.
// preg_match prueba la ruta contra una expresión regular:
//   ^/api/producto/  → la ruta empieza así
//   ([^/]+)          → captura "lo que siga, sin barras" (el código)
//   $                → y ahí termina (no hay nada después)
// Si coincide devuelve 1, y deja lo capturado en $coincidencias[1].
if (preg_match('#^/api/producto/([^/]+)$#', $ruta, $coincidencias) === 1) {
    // urldecode revierte la codificación de URL (un "%20" vuelve a ser espacio):
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

// Si llegamos aquí, ninguna ruta coincidió: 404 de RUTA
// (distinto del 404 de "el producto no existe", que decide el servicio).
// http_response_code fija el código de estado de la respuesta HTTP.
http_response_code(404);
echo json_encode([
    'estado' => 404, 'mensaje' => 'Ruta no encontrada.', 'detalle' => "$metodo $ruta",
], JSON_UNESCAPED_UNICODE);

// ----------------------------------------------------------------------
// Función de apoyo del enrutador. ": void" declara que no devuelve nada.
function responderNoPermitido(): void
{
    // 405 = "la ruta existe, pero no con ese método"
    http_response_code(405);
    echo json_encode([
        'estado' => 405, 'mensaje' => 'Método no permitido para esta ruta.',
    ], JSON_UNESCAPED_UNICODE);
}
