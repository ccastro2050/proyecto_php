<?php
/**
 * RepositorioProductoMariaDB — la capa de DATOS de la v1.
 *
 * Única clase del sistema que habla SQL y que conoce la conexión. Cumple el
 * contrato IRepositorioProducto con `implements` (interface nativa de PHP).
 *
 * Reglas de la constitución que se cumplen aquí:
 * - SQL SIEMPRE en prepared statements de PDO (nunca concatenar valores).
 * - El SQL queda visible (PDO como ejecutor, sin ORM).
 */
declare(strict_types=1);

require_once __DIR__ . '/IRepositorioProducto.php';

class RepositorioProductoMariaDB implements IRepositorioProducto
{
    private ?PDO $conexion = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $usuario,
        private readonly string $clave,
    ) {
        // La conexión NO se abre aquí: es perezosa (primer uso).
        // Este archivo no sabe de variables de entorno: el DSN llega de afuera.
    }

    // ------------------------------------------------------------------
    // Ayudantes privados
    // ------------------------------------------------------------------

    /** Abre la conexión PDO la primera vez y la reutiliza (perezosa). */
    private function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            $this->conexion = new PDO($this->dsn, $this->usuario, $this->clave, [
                // Errores como excepciones (el controlador los traduce a 500):
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Prepared statements REALES del servidor, no emulados.
                // Detalle MariaDB: con esto, :limite DEBE enlazarse como entero.
                PDO::ATTR_EMULATE_PREPARES => false,
                // Detalle MariaDB #2: por defecto, rowCount() de un UPDATE
                // cuenta filas CAMBIADAS (si el valor nuevo es igual al viejo
                // reporta 0, y parecería que el código "no existe"). Con
                // FOUND_ROWS cuenta filas ENCONTRADAS, como los demás motores:
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ]);
        }
        return $this->conexion;
    }

    /** Prepara una fila para JSON: el driver mysql entrega DECIMAL como string. */
    private function serializar(array $fila): array
    {
        return [
            'codigo'        => $fila['codigo'],
            'nombre'        => $fila['nombre'],
            'stock'         => (int) $fila['stock'],
            'valorunitario' => (float) $fila['valorunitario'],
        ];
    }

    // ------------------------------------------------------------------
    // Los 5 métodos del contrato
    // ------------------------------------------------------------------

    public function obtenerTodos(int $limite): array
    {
        $sql = 'SELECT codigo, nombre, stock, valorunitario
                FROM producto ORDER BY codigo LIMIT :limite';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        // PARAM_INT es obligatorio: un LIMIT con string es error en MariaDB.
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $fila) => $this->serializar($fila), $filas);
    }

    public function obtenerPorCodigo(string $codigo): ?array
    {
        $sql = 'SELECT codigo, nombre, stock, valorunitario
                FROM producto WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $this->serializar($fila);
    }

    public function crear(array $datos): bool
    {
        $sql = 'INSERT INTO producto (codigo, nombre, stock, valorunitario)
                VALUES (:codigo, :nombre, :stock, :valorunitario)';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute([
            'codigo'        => $datos['codigo'],
            'nombre'        => $datos['nombre'],
            'stock'         => $datos['stock'],
            'valorunitario' => $datos['valorunitario'],
        ]);
        return $sentencia->rowCount() === 1;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        // SET dinámico SOLO con las columnas que llegaron (PUT manda las 3,
        // PATCH un subconjunto). Los nombres salen del Validador (lista
        // blanca) — nunca del cliente — por eso es seguro interpolarlos;
        // los VALORES sí van siempre como parámetros.
        $asignaciones = [];
        foreach (array_keys($datos) as $columna) {
            $asignaciones[] = "$columna = :$columna";
        }
        $sql = 'UPDATE producto SET ' . implode(', ', $asignaciones)
             . ' WHERE codigo = :codigo_clave';

        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute($datos + ['codigo_clave' => $codigo]);
        return $sentencia->rowCount();
    }

    public function eliminar(string $codigo): int
    {
        $sql = 'DELETE FROM producto WHERE codigo = :codigo';
        $sentencia = $this->obtenerConexion()->prepare($sql);
        $sentencia->execute(['codigo' => $codigo]);
        return $sentencia->rowCount();
    }
}
