<?php
/**
 * index.php — el FRONT CONTROLLER de la API Facturas v1.
 *
 * TODAS las peticiones entran por aquí (el servidor se arranca con
 * `php -S 0.0.0.0:8022 index.php`). Este archivo hace UNA cosa: mirar el
 * método (GET, POST…) y la ruta, y entregar la petición al método del
 * controlador que corresponde. Nada de SQL, nada de negocio.
 *
 * El recorrido completo de una petición, paso a paso, está explicado en
 * docs/FLUJO_DE_UNA_PETICION.md.
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
// PHP lanza error en vez de convertirlo en silencio. DEBE ser la primera
// instrucción del archivo. Todos los archivos del proyecto lo llevan.
declare(strict_types=1);

// require_once = "carga este archivo aquí (una sola vez)". __DIR__ es la
// carpeta donde vive ESTE archivo. Esta lista es el inventario del proyecto:
require_once __DIR__ . '/servicios/ensamblador.php';
require_once __DIR__ . '/controladores/ControladorProducto.php';

// Toda respuesta de esta API es JSON — se avisa en el encabezado HTTP:
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------------
// 1. CAPTURAR la petición: método, ruta y body
// ----------------------------------------------------------------------

// $_SERVER es un array que PHP llena solo en cada petición.
// REQUEST_METHOD trae el verbo que mandó el cliente: "GET", "POST", "PUT"…
$metodo = $_SERVER['REQUEST_METHOD'];

// REQUEST_URI trae todo lo pedido, ej. "/api/producto?limite=5".
// parse_url(..., PHP_URL_PATH) recorta y deja SOLO la ruta: "/api/producto".
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// El body (el JSON que traen POST, PUT y PATCH) se lee del canal especial
// php://input — solo se puede leer UNA vez, por eso se guarda aquí:
//   file_get_contents  → lee el texto crudo del body
//   json_decode(..., true) → lo convierte a array de PHP
//   ?? []              → si no había body (o el JSON está malo), queda []
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Se arma el controlador. Su dependencia (el servicio) la crea el
// ensamblador — el único archivo del sistema que conoce clases concretas:
$controlador = new ControladorProducto(crearServicioProducto());

// ----------------------------------------------------------------------
// 2. ENRUTAR: comparar método + ruta y llamar al método del controlador
// ----------------------------------------------------------------------

// GET / — diagnóstico (sirve para saber si la API está viva)
if ($ruta === '/' && $metodo === 'GET') {
    // json_encode convierte el array a texto JSON; echo lo escribe en la
    // respuesta. JSON_UNESCAPED_UNICODE deja las tildes legibles.
    echo json_encode([
        'mensaje'   => 'API Facturas funcionando',
        'version'   => 'v1',
        'contratos' => 'docs/spec_kit/versiones/v1_producto_mariadb/6_contracts.md',
    ], JSON_UNESCAPED_UNICODE);
    return;   // terminamos: no siga evaluando rutas
}

// /api/producto — la COLECCIÓN (sin código en la URL): listar y crear
if ($ruta === '/api/producto') {
    if ($metodo === 'GET') {
        $controlador->listar();
    } elseif ($metodo === 'POST') {
        $controlador->crear($body);
    } else {
        responderNoPermitido();   // PUT, DELETE… aquí no existen → 405
    }
    return;
}

// /api/producto/{codigo} — UN producto concreto.
// str_starts_with pregunta si la ruta EMPIEZA por "/api/producto/";
// substr corta lo que sigue después de ese prefijo: eso es el código.
// Ej.: "/api/producto/PR001" → $codigo = "PR001".
if (str_starts_with($ruta, '/api/producto/')) {
    $codigo = substr($ruta, strlen('/api/producto/'));
    // urldecode revierte la codificación de URL (un "%20" vuelve a ser espacio):
    $codigo = urldecode($codigo);

    if ($metodo === 'GET') {
        $controlador->obtener($codigo);
    } elseif ($metodo === 'PUT') {
        $controlador->reemplazar($codigo, $body);
    } elseif ($metodo === 'PATCH') {
        $controlador->actualizar($codigo, $body);
    } elseif ($metodo === 'DELETE') {
        $controlador->eliminar($codigo);
    } else {
        responderNoPermitido();
    }
    return;
}

// Ninguna ruta coincidió: 404 de RUTA
// (distinto del 404 de "el producto no existe", que decide el servicio).
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
