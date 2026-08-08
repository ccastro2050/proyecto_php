<?php
/**
 * ServicioProducto — la capa de NEGOCIO de la v1.
 *
 * Recibe POR CONSTRUCTOR la interfaz del repositorio (inversión de
 * dependencias): no sabe si detrás hay MariaDB, un falso en memoria para
 * pruebas, o el PostgreSQL que llegará en la v3.
 *
 * No conoce HTTP: comunica los problemas con excepciones de negocio que el
 * controlador traduce a códigos (InvalidArgumentException → 400 ·
 * NoEncontradoExcepcion → 404).
 */
declare(strict_types=1);

require_once __DIR__ . '/IServicioProducto.php';
require_once __DIR__ . '/../repositorios/IRepositorioProducto.php';
require_once __DIR__ . '/../excepciones/NoEncontradoExcepcion.php';

class ServicioProducto implements IServicioProducto
{
    public function __construct(
        // Se guarda LA INTERFAZ: cualquier clase que la implemente sirve.
        private readonly IRepositorioProducto $repositorio,
    ) {
    }

    // ------------------------------------------------------------------
    // Validaciones pequeñas y compartidas
    // ------------------------------------------------------------------

    private function validarCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('El código del producto no puede estar vacío.');
        }
        return $codigo;
    }

    // ------------------------------------------------------------------
    // Operaciones de negocio
    // ------------------------------------------------------------------

    public function listar(int $limite): array
    {
        // El contrato dice 400 (no 422) para límites inválidos:
        // es una REGLA DE NEGOCIO, no un problema de forma del body.
        if ($limite <= 0) {
            throw new InvalidArgumentException('El límite debe ser un entero mayor que cero.');
        }
        return $this->repositorio->obtenerTodos($limite);
    }

    public function obtener(string $codigo): array
    {
        $codigo = $this->validarCodigo($codigo);
        $fila = $this->repositorio->obtenerPorCodigo($codigo);
        if ($fila === null) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $fila;
    }

    public function crear(array $datos): void
    {
        // Los datos ya pasaron por el Validador (tipos y rangos): aquí solo
        // se delega. Si la BD rechaza (PK duplicada), la PDOException sube
        // tal cual y el controlador la convierte en 500 con el detalle.
        $this->repositorio->crear($datos);
    }

    public function actualizar(string $codigo, array $datos): int
    {
        $codigo = $this->validarCodigo($codigo);
        if ($datos === []) {
            throw new InvalidArgumentException('No se envió ningún campo para actualizar.');
        }
        $filasAfectadas = $this->repositorio->actualizar($codigo, $datos);
        if ($filasAfectadas === 0) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $filasAfectadas;
    }

    public function eliminar(string $codigo): int
    {
        $codigo = $this->validarCodigo($codigo);
        $filasEliminadas = $this->repositorio->eliminar($codigo);
        if ($filasEliminadas === 0) {
            throw new NoEncontradoExcepcion("No existe un producto con codigo = $codigo");
        }
        return $filasEliminadas;
    }
}
