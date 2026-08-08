<?php
/**
 * ValidadorProducto — la FRONTERA DE ENTRADA de la API.
 *
 * En PHP puro no hay Pydantic: la validación se construye a mano — y eso es
 * contenido del curso. Hay UN método por semántica HTTP:
 *   - validarCrear     → POST : todos los campos obligatorios (con código)
 *   - validarReemplazo → PUT  : todos obligatorios (el código va en la URL)
 *   - validarParcial   → PATCH: valida SOLO los campos enviados
 *
 * Cada método devuelve la LISTA DE ERRORES (vacía = datos válidos).
 * Si hay errores, el controlador responde 422 y nada llega al servicio
 * ni a la base de datos.
 */
declare(strict_types=1);

class ValidadorProducto
{
    /** POST /api/producto — el recurso completo, con su código. */
    public static function validarCrear(array $datos): array
    {
        $errores = self::validarCodigo($datos);
        return array_merge($errores, self::validarCampos($datos, obligatorios: true));
    }

    /** PUT /api/producto/{codigo} — reemplazo COMPLETO: todos obligatorios. */
    public static function validarReemplazo(array $datos): array
    {
        return self::validarCampos($datos, obligatorios: true);
    }

    /** PATCH /api/producto/{codigo} — parcial: solo se validan los enviados. */
    public static function validarParcial(array $datos): array
    {
        return self::validarCampos($datos, obligatorios: false);
    }

    // ------------------------------------------------------------------
    // Reglas compartidas
    // ------------------------------------------------------------------

    private static function validarCodigo(array $datos): array
    {
        $codigo = $datos['codigo'] ?? null;
        if (!is_string($codigo) || $codigo === '' || strlen($codigo) > 10) {
            return ['El campo codigo es obligatorio: texto de 1 a 10 caracteres.'];
        }
        return [];
    }

    /**
     * Valida nombre, stock y valorunitario.
     * Con $obligatorios=true (POST/PUT) los tres deben venir;
     * con false (PATCH) solo se valida lo que llegue.
     */
    private static function validarCampos(array $datos, bool $obligatorios): array
    {
        $errores = [];

        if (array_key_exists('nombre', $datos)) {
            if (!is_string($datos['nombre']) || trim($datos['nombre']) === '') {
                $errores[] = 'El campo nombre debe ser un texto no vacío.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo nombre es obligatorio.';
        }

        if (array_key_exists('stock', $datos)) {
            // is_int rechaza "7" (string) y 7.5 (float): el tipo también es la regla.
            if (!is_int($datos['stock']) || $datos['stock'] < 0) {
                $errores[] = 'El campo stock debe ser un entero mayor o igual a 0.';
            }
        } elseif ($obligatorios) {
            $errores[] = 'El campo stock es obligatorio.';
        }

        if (array_key_exists('valorunitario', $datos)) {
            $valor = $datos['valorunitario'];
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
    public static function filtrarColumnas(array $datos): array
    {
        $permitidas = ['nombre', 'stock', 'valorunitario'];
        return array_intersect_key($datos, array_flip($permitidas));
    }
}
