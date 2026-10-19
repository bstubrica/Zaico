<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CategoriaActivo;
use App\Models\ModeloActivo;
use App\Rules\NombreActivoNomenclatura;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Reglas, mensajes y normalización de la nomenclatura del activo
 * (`Nomenclatura.md`): categoría canónica y estructura del nombre
 * `Tipo Fabricante Modelo Serial Etiqueta`.
 *
 * Se comparten entre el alta y la edición para que ambas rutas no diverjan,
 * igual que `ReglasServidor`. La categoría se normaliza a su sinónimo canónico
 * antes de validar; los valores fuera del catálogo se rechazan.
 */
trait ReglasNomenclatura
{
    /**
     * Resuelve la categoría al nombre canónico del catálogo antes de validar
     * (admite sinónimos y variantes). Si no pertenece al catálogo se deja tal
     * cual para que la regla `in` la rechace con el mensaje correcto.
     */
    protected function normalizarCategoria(): void
    {
        if (! $this->has('Categoria')) {
            return;
        }

        $valor = $this->input('Categoria');

        if (! is_string($valor)) {
            return;
        }

        $valor = trim($valor);

        if ($valor === '') {
            $this->merge(['Categoria' => null]);

            return;
        }

        $this->merge(['Categoria' => CategoriaActivo::canonico($valor) ?? $valor]);
    }

    /**
     * Regla de la categoría: obligatoria y dentro del catálogo canónico.
     *
     * @return array<int, ValidationRule|string>
     */
    protected function reglaCategoria(): array
    {
        return ['required', 'string', 'max:100', Rule::in(CategoriaActivo::valores())];
    }

    /**
     * Regla del nombre: estructura `Tipo Fabricante Modelo Serial Etiqueta`.
     */
    protected function reglaNombreActivo(): NombreActivoNomenclatura
    {
        return new NombreActivoNomenclatura(
            categoria: $this->valorEfectivo('Categoria'),
            fabricante: $this->valorEfectivo('Fabricante'),
            modelo: $this->modeloEfectivo(),
            etiqueta: $this->valorEfectivo('Etiqueta_activo'),
            serial: $this->valorEfectivo('Serial'),
        );
    }

    /**
     * Valor del campo tal como llegó, o el del activo de la ruta cuando el
     * request no lo trae (edición parcial): la estructura del nombre se valida
     * siempre contra el estado resultante del activo.
     */
    protected function valorEfectivo(string $campo): ?string
    {
        if ($this->has($campo)) {
            $valor = $this->input($campo);

            return is_string($valor) ? trim($valor) : null;
        }

        $activo = $this->route('activo');

        $valor = $activo?->{$campo};

        return is_string($valor) ? trim($valor) : null;
    }

    /**
     * Modelo que debe aparecer en el nombre: el del catálogo si se eligió uno,
     * con respaldo en el texto libre.
     */
    protected function modeloEfectivo(): ?string
    {
        $fkModelo = $this->input('fk_modelo');

        if (filled($fkModelo)) {
            $nombre = ModeloActivo::whereKey($fkModelo)->value('Nombre');

            if (filled($nombre)) {
                return (string) $nombre;
            }
        }

        return $this->valorEfectivo('Modelo');
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesNomenclatura(): array
    {
        return [
            'Categoria.required' => 'La categoría es obligatoria.',
            'Categoria.in' => 'La categoría debe ser una del catálogo de nomenclatura (por ejemplo Laptop, Desktop, Monitor).',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosNomenclatura(): array
    {
        return [
            'Nombre_de_activo' => 'nombre del activo',
            'Categoria' => 'categoría',
        ];
    }
}
