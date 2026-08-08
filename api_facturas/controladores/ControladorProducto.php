<?php
/**
 * ControladorProducto — la capa HTTP de la v1.
 *
 * Su único trabajo: leer la petición, pasar por la FRONTERA (Validador → 422),
 * delegar al servicio, y traducir el resultado (o la excepción) a una
 * respuesta HTTP con JSON. Aquí NO hay SQL ni reglas de negocio.
 *
 * Traducción de excepciones (el contrato de 6_contracts.md §0):
 *   Validador con errores        → 422 (no es excepción: es lista de errores)
 *   InvalidArgumentException     → 400 (regla de negocio)
 *   NoEncontradoExcepcion        → 404
 *   PDOException y cualquier otra→ 500 (mensaje del motor en `detalle`)
 */
declare(strict_types=1);

require_once __DIR__ . '/../modelos/ValidadorProducto.php';
require_once __DIR__ . '/../servicios/IServicioProducto.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ControladorProducto
{
    public function __construct(
        private readonly IServicioProducto $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/producto[?limite=N]
    // ------------------------------------------------------------------
    public function listar(): void
    {
        // El query string llega como texto: se convierte aquí (frontera HTTP).
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        $this->intentar(function () use ($limite) {
            $filas = $this->servicio->listar($limite);
            if ($filas === []) {
                http_response_code(204);   // éxito sin contenido: tabla vacía
                return;
            }
            $this->responder(200, [
                'tabla'  => 'producto',
                'limite' => $limite,
                'total'  => count($filas),
                'datos'  => $filas,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // GET /api/producto/{codigo}
    // ------------------------------------------------------------------
    public function obtener(string $codigo): void
    {
        $this->intentar(function () use ($codigo) {
            $this->responder(200, $this->servicio->obtener($codigo));
        });
    }

    // ------------------------------------------------------------------
    // POST /api/producto   (body completo, con código)
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        $errores = ValidadorProducto::validarCrear($body);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($body) {
            $datos = ['codigo' => $body['codigo']] + ValidadorProducto::filtrarColumnas($body);
            $this->servicio->crear($datos);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto creado exitosamente.',
            ]);
        });
    }

    // ------------------------------------------------------------------
    // PUT /api/producto/{codigo}   (reemplazo COMPLETO)
    // ------------------------------------------------------------------
    public function reemplazar(string $codigo, array $body): void
    {
        $errores = ValidadorProducto::validarReemplazo($body);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($codigo, $body) {
            $filas = $this->servicio->actualizar($codigo, ValidadorProducto::filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto reemplazado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // PATCH /api/producto/{codigo}   (parcial: solo lo enviado)
    // ------------------------------------------------------------------
    public function actualizar(string $codigo, array $body): void
    {
        $errores = ValidadorProducto::validarParcial($body);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($codigo, $body) {
            // El body vacío NO es 422: es una regla de negocio (400) del servicio.
            $filas = $this->servicio->actualizar($codigo, ValidadorProducto::filtrarColumnas($body));
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto actualizado exitosamente.',
                'filasAfectadas' => $filas,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // DELETE /api/producto/{codigo}
    // ------------------------------------------------------------------
    public function eliminar(string $codigo): void
    {
        $this->intentar(function () use ($codigo) {
            $filas = $this->servicio->eliminar($codigo);
            $this->responder(200, [
                'estado' => 200, 'mensaje' => 'Producto eliminado exitosamente.',
                'filasEliminadas' => $filas,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // La traducción de excepciones a HTTP, en UN solo lugar
    // ------------------------------------------------------------------
    private function intentar(callable $operacion): void
    {
        try {
            $operacion();
        } catch (InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    /** Escribe el código de estado y el cuerpo JSON de la respuesta. */
    private function responder(int $estado, array $cuerpo): void
    {
        http_response_code($estado);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
