<?php
/**
 * prueba_capas.php — Criterio 6 de la v1: el servicio funciona con un
 * repositorio FALSO en memoria que implementa IRepositorioProducto —
 * sin MariaDB corriendo.
 *
 * Si esto pasa, las capas quedaron bien cortadas (polimorfismo + inversión
 * de dependencias). Ejecutar:  php pruebas/prueba_capas.php
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

// Este require arrastra (por sus propios require_once) la interfaz del
// repositorio, el modelo Producto y las excepciones:
require_once __DIR__ . '/../servicios/ServicioProducto.php';

/**
 * El REPOSITORIO FALSO: cumple el mismo contrato que el de MariaDB, pero
 * guarda los Producto en un simple array en memoria — cero SQL, cero red.
 * Como el servicio depende de la INTERFAZ, no nota la diferencia.
 */
class RepositorioFalsoEnMemoria implements IRepositorioProducto
{
    /** El "almacén": un array con el código como llave y el Producto como valor. */
    private array $datos = [];

    public function obtenerTodos(int $limite): array
    {
        // array_values descarta las llaves (deja lista simple);
        // array_slice corta los primeros $limite elementos (como un LIMIT):
        return array_slice(array_values($this->datos), 0, $limite);
    }

    public function obtenerPorCodigo(string $codigo): ?Producto
    {
        // ?? null = si esa llave no existe, devolver null (el contrato):
        return $this->datos[$codigo] ?? null;
    }

    public function crear(Producto $producto): bool
    {
        // El objeto del modelo se guarda tal cual, con su código como llave:
        $this->datos[$producto->getCodigo()] = $producto;
        return true;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        // isset pregunta si la llave existe; 0 filas = "no existía":
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        // Se escriben SOLO los campos que llegaron (igual que el UPDATE
        // dinámico del repositorio real), usando los SETTERS del modelo:
        $producto = $this->datos[$codigo];
        if (array_key_exists('nombre', $datos)) {
            $producto->setNombre($datos['nombre']);
        }
        if (array_key_exists('stock', $datos)) {
            $producto->setStock($datos['stock']);
        }
        if (array_key_exists('valorunitario', $datos)) {
            $producto->setValorunitario((float) $datos['valorunitario']);
        }
        return 1;
    }

    public function eliminar(string $codigo): int
    {
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        // unset borra la llave del array:
        unset($this->datos[$codigo]);
        return 1;
    }
}

// ----------------------------------------------------------------------
// La prueba: el MISMO ServicioProducto, con otro repositorio (polimorfismo)
// ----------------------------------------------------------------------
$servicio = new ServicioProducto(new RepositorioFalsoEnMemoria());

/** Mini-verificador: si la condición es falsa, reporta y sale con error. */
function verificar(bool $condicion, string $descripcion): void
{
    if (!$condicion) {
        // STDERR es la salida de errores; exit(1) = terminar "mal"
        // (los scripts que salen con 0 pasaron, con != 0 fallaron):
        fwrite(STDERR, "FALLÓ: $descripcion\n");
        exit(1);
    }
}

// El ciclo completo contra el repositorio falso. Note que las lecturas
// devuelven OBJETOS Producto: se pregunta con los getters del modelo
// (getCodigo()), no con llaves de array (['codigo']).
$servicio->crear(['codigo' => 'T1', 'nombre' => 'Test', 'stock' => 5, 'valorunitario' => 100.0]);
verificar($servicio->listar(10)[0]->getCodigo() === 'T1',     'crear + listar');
verificar($servicio->obtener('T1')->getNombre() === 'Test',   'obtener por código');
verificar($servicio->actualizar('T1', ['stock' => 9]) === 1,  'actualizar');
verificar($servicio->obtener('T1')->getStock() === 9,         'el stock quedó en 9');
verificar($servicio->eliminar('T1') === 1,                    'eliminar');

// Las excepciones de negocio también funcionan sin BD:
try { $servicio->obtener('NOEXISTE'); verificar(false, 'debió lanzar NoEncontradoExcepcion'); }
catch (NoEncontradoExcepcion) { /* esperado */ }

try { $servicio->actualizar('T1', []); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

try { $servicio->listar(0); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

echo "CRITERIO 6 OK: el servicio funciona con el repositorio falso, sin MariaDB\n";
