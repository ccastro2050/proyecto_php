<?php
/**
 * ControladorProducto — la capa HTTP de la v1.
 *
 * Su único trabajo: leer la petición, VALIDAR la forma del body (los ifs de
 * los métodos privados de abajo → 422), delegar al servicio, y traducir el
 * resultado (o la excepción) a una respuesta HTTP con JSON.
 * Aquí NO hay SQL ni reglas de negocio.
 *
 * Traducción a códigos HTTP (el contrato de 6_contracts.md §0):
 *   Body con errores de forma    → 422 (con la lista de errores)
 *   InvalidArgumentException     → 400 (regla de negocio)
 *   NoEncontradoExcepcion        → 404
 *   PDOException y cualquier otra→ 500 (mensaje del motor en `detalle`)
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

require_once __DIR__ . '/../servicios/IServicioProducto.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ControladorProducto
{
    // "Promoción de propiedades" (PHP 8): el parámetro queda guardado como
    // atributo del objeto ($this->servicio).
    //   private  → solo esta clase lo ve
    //   readonly → se asigna una vez y nadie lo puede cambiar después
    // Ojo al TIPO: es la INTERFAZ IServicioProducto, no una clase concreta.
    public function __construct(
        private readonly IServicioProducto $servicio,
    ) {
    }

    // ------------------------------------------------------------------
    // GET /api/producto[?limite=N]
    // ------------------------------------------------------------------
    public function listar(): void
    {
        // $_GET es el array con el query string (?limite=5 → $_GET['limite']).
        // SIEMPRE llega como texto: "(int)" lo convierte a entero aquí, en
        // la frontera HTTP. El ternario (condición ? siHay : siNoHay) pone
        // 1000 por defecto.
        $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 1000;

        // intentar() ejecuta la operación dentro de un try/catch centralizado.
        // "function () use ($limite)" es una FUNCIÓN ANÓNIMA: un bloque de
        // código que se pasa como argumento; "use" mete $limite al bloque.
        $this->intentar(function () use ($limite) {
            $productos = $this->servicio->listar($limite);
            if ($productos === []) {
                http_response_code(204);   // 204 = éxito SIN contenido: tabla vacía
                return;
            }
            // La "envoltura" de las lecturas: metadatos + datos.
            // $productos es una lista de objetos Producto: json_encode los
            // serializa solo (propiedades públicas del modelo).
            $this->responder(200, [
                'tabla'  => 'producto',
                'limite' => $limite,
                'total'  => count($productos),
                'datos'  => $productos,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // GET /api/producto/{codigo}
    // ------------------------------------------------------------------
    public function obtener(string $codigo): void
    {
        $this->intentar(function () use ($codigo) {
            // Si no existe, el servicio lanza NoEncontradoExcepcion y
            // intentar() la vuelve 404 — aquí solo va el camino feliz.
            $this->responder(200, $this->servicio->obtener($codigo));
        });
    }

    // ------------------------------------------------------------------
    // POST /api/producto   (body completo, con código)
    // ------------------------------------------------------------------
    public function crear(array $body): void
    {
        // VALIDAR PRIMERO: la lista de errores decide. POST exige TODO
        // (incluido el código). Si hay errores → 422 y aquí se acaba.
        $errores = array_merge(
            $this->validarCodigo($body),
            $this->validarCampos($body, obligatorios: true),
        );
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($body) {
            // Se arma el registro: el código + SOLO las columnas conocidas
            // ("+" une dos arrays; filtrarColumnas botó lo desconocido).
            $datos = ['codigo' => $body['codigo']] + $this->filtrarColumnas($body);
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
        // PUT exige TODOS los campos (el código va en la URL): un PUT con
        // body parcial muere aquí con 422 — esa es la semántica de PUT.
        $errores = $this->validarCampos($body, obligatorios: true);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($codigo, $body) {
            $filas = $this->servicio->actualizar($codigo, $this->filtrarColumnas($body));
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
        // PATCH valida SOLO lo que llegó (obligatorios: false). El MISMO
        // body {"stock": 99} que en PUT da 422, aquí pasa — la diferencia
        // entre PUT y PATCH queda escrita en código.
        $errores = $this->validarCampos($body, obligatorios: false);
        if ($errores !== []) {
            $this->responder(422, [
                'estado' => 422, 'mensaje' => 'Datos inválidos.', 'errores' => $errores,
            ]);
            return;
        }

        $this->intentar(function () use ($codigo, $body) {
            // El body vacío NO es 422: es una regla de negocio (400) que
            // decide el servicio — forma vs negocio, cada cosa en su capa.
            $filas = $this->servicio->actualizar($codigo, $this->filtrarColumnas($body));
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

    // ==================================================================
    // LA VALIDACIÓN DEL BODY (los ifs de la frontera HTTP → 422)
    // Son métodos privados del controlador porque validar la FORMA de la
    // petición es trabajo de la capa HTTP. Devuelven LISTA de errores
    // (vacía = todo bien) para reportarle al cliente todo de una vez.
    // ==================================================================

    /** El código: obligatorio, texto de 1 a 10 caracteres (VARCHAR(10)). */
    private function validarCodigo(array $datos): array
    {
        // ?? = "si la llave no existe (o es null), usa null":
        $codigo = $datos['codigo'] ?? null;
        // Tres condiciones unidas con || (basta que UNA falle):
        if (!is_string($codigo) || $codigo === '' || strlen($codigo) > 10) {
            return ['El campo codigo es obligatorio: texto de 1 a 10 caracteres.'];
        }
        return [];   // lista vacía = sin errores
    }

    /**
     * Valida nombre, stock y valorunitario.
     * Con $obligatorios=true (POST/PUT) los tres deben venir;
     * con false (PATCH) solo se valida lo que llegue.
     */
    private function validarCampos(array $datos, bool $obligatorios): array
    {
        $errores = [];

        // array_key_exists pregunta "¿vino este campo en el body?".
        // El patrón de cada campo es el mismo:
        //   si vino    → validar su contenido
        //   si no vino → es error SOLO cuando el verbo lo exige (POST/PUT)
        if (array_key_exists('nombre', $datos)) {
            // trim quita espacios a los lados: "   " no cuenta como nombre.
            if (!is_string($datos['nombre']) || trim($datos['nombre']) === '') {
                $errores[] = 'El campo nombre debe ser un texto no vacío.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo nombre es obligatorio.';
        }

        if (array_key_exists('stock', $datos)) {
            // is_int rechaza "7" (string) y 7.5 (float): el TIPO también es
            // parte de la regla.
            if (!is_int($datos['stock']) || $datos['stock'] < 0) {
                $errores[] = 'El campo stock debe ser un entero mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo stock es obligatorio.';
        }

        if (array_key_exists('valorunitario', $datos)) {
            $valor = $datos['valorunitario'];
            // El precio acepta entero O decimal (120000 y 120000.50 valen):
            if ((!is_int($valor) && !is_float($valor)) || $valor < 0) {
                $errores[] = 'El campo valorunitario debe ser un número mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo valorunitario es obligatorio.';
        }

        return $errores;
    }

    /**
     * Deja pasar SOLO las columnas conocidas (lista blanca): lo que el
     * cliente envíe de más se ignora y jamás llega a un SQL.
     */
    private function filtrarColumnas(array $datos): array
    {
        $permitidas = ['nombre', 'stock', 'valorunitario'];
        // array_flip voltea la lista (valores → llaves) y
        // array_intersect_key deja de $datos SOLO esas llaves:
        return array_intersect_key($datos, array_flip($permitidas));
    }

    // ------------------------------------------------------------------
    // La traducción de excepciones a HTTP, en UN solo lugar
    // ------------------------------------------------------------------

    // "callable" = algo que se puede ejecutar (las funciones anónimas que
    // le pasan los métodos de arriba).
    private function intentar(callable $operacion): void
    {
        // try/catch: se intenta la operación; si algo lanza una excepción,
        // PHP salta al catch cuyo TIPO coincida (se prueban en orden).
        try {
            $operacion();
        } catch (InvalidArgumentException $e) {
            // La lanza el servicio ante reglas de negocio rotas → 400.
            // $e->getMessage() trae el texto con que se lanzó.
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parámetros inválidos.', 'detalle' => $e->getMessage(),
            ]);
        } catch (NoEncontradoExcepcion $e) {
            // Nuestra excepción propia: el recurso no existe → 404.
            $this->responder(404, [
                'estado' => 404, 'mensaje' => 'Producto no encontrado.', 'detalle' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            // Throwable = el padre de TODOS los errores de PHP: atrapa lo
            // inesperado (una PDOException de la BD, por ejemplo) → 500.
            $this->responder(500, [
                'estado' => 500, 'mensaje' => 'Error interno.', 'detalle' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Escribe el código de estado y el cuerpo JSON.
     * "array|Producto" = acepta un array (envolturas y errores) O un objeto
     * del modelo (json_encode serializa sus propiedades públicas).
     */
    private function responder(int $estado, array|Producto $cuerpo): void
    {
        http_response_code($estado);                        // el código HTTP (200, 404…)
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);  // el body como JSON
    }
}
