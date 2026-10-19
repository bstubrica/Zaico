<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\TarjetaRed;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ServidorService
{
    public function __construct(private readonly HistorialService $historial) {}

    /**
     * Switches disponibles para conectar tarjetas de red: equipos cuya
     * categoría es de switch, más los ya referenciados por el activo aunque
     * hayan cambiado de categoría (para no perder la selección al editar).
     *
     * @param  array<int, int|string|null>  $idsExtra
     * @return Collection<int, Activo>
     */
    public function catalogoSwitches(array $idsExtra = []): Collection
    {
        $idsExtra = array_values(array_filter(
            array_map(fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null, $idsExtra),
        ));

        $categorias = array_values((array) config('zaico.servidores.categorias_switch', []));

        return Activo::query()
            ->where(function ($query) use ($idsExtra, $categorias): void {
                $query->whereIn(DB::raw('lower(trim("Categoria"))'), $categorias);

                if ($idsExtra !== []) {
                    $query->orWhereIn('id', $idsExtra);
                }
            })
            ->orderBy('Nombre_de_activo')
            ->get(['id', 'Nombre_de_activo', 'Etiqueta_activo', 'Categoria']);
    }

    /**
     * Reemplaza las tarjetas de red del equipo por la lista recibida (altas,
     * cambios y bajas) y deja un evento de auditoría si el resultado cambió.
     *
     * Las tarjetas son datos hijos: se pueden borrar físicamente sin romper la
     * invariante de «sin DELETE físico» de `ACTIVOS`/`PERSONAL`, y el borrado
     * queda trazado en `HISTORIAL_EVENTOS`.
     *
     * @param  array<int, array<string, mixed>>  $tarjetas
     */
    public function sincronizarTarjetas(Activo $activo, array $tarjetas, int $usuarioId): void
    {
        $antes = $this->retratoTarjetas($activo);
        $preparadas = $this->prepararTarjetas($tarjetas);
        $numeros = array_column($preparadas, 'Numero');

        DB::transaction(function () use ($activo, $preparadas, $numeros): void {
            foreach ($preparadas as $datos) {
                TarjetaRed::updateOrCreate(
                    ['fk_activo' => $activo->id, 'Numero' => $datos['Numero']],
                    $datos,
                );
            }

            $restantes = $activo->tarjetasRed();

            if ($numeros === []) {
                $restantes->delete();
            } else {
                $restantes->whereNotIn('Numero', $numeros)->delete();
            }
        });

        $despues = $this->retratoTarjetas($activo);

        if ($antes === $despues) {
            return;
        }

        $this->historial->registrar(
            $activo->id,
            'Modificacion',
            'Tarjetas de red actualizadas',
            anterior: $antes === [] ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
            nuevo: $despues === [] ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
            usuarioId: $usuarioId,
        );
    }

    /**
     * Normaliza la lista recibida del formulario y resuelve el nombre del
     * switch denormalizado (`Switch`) con una sola consulta.
     *
     * @param  array<int, array<string, mixed>>  $tarjetas
     * @return array<int, array<string, mixed>>
     */
    private function prepararTarjetas(array $tarjetas): array
    {
        $preparadas = [];
        $idsSwitch = [];

        foreach ($tarjetas as $tarjeta) {
            $numero = (int) ($tarjeta['Numero'] ?? 0);

            if ($numero < 1) {
                continue;
            }

            $fkSwitch = $tarjeta['fk_switch'] ?? null;
            $fkSwitch = ($fkSwitch === null || $fkSwitch === '' || $fkSwitch === '0') ? null : (int) $fkSwitch;

            if ($fkSwitch !== null) {
                $idsSwitch[$fkSwitch] = $fkSwitch;
            }

            $preparadas[] = [
                'Numero' => $numero,
                'Nombre' => $tarjeta['Nombre'] ?? null,
                'Direccion_MAC' => $tarjeta['Direccion_MAC'] ?? null,
                'IP' => $tarjeta['IP'] ?? null,
                'fk_switch' => $fkSwitch,
                'Switch' => null,
                'Puerto' => $tarjeta['Puerto'] ?? null,
            ];
        }

        if ($idsSwitch === []) {
            return $preparadas;
        }

        $nombres = Activo::query()
            ->whereKey(array_values($idsSwitch))
            ->pluck('Nombre_de_activo', 'id');

        foreach ($preparadas as $indice => $tarjeta) {
            $fkSwitch = $tarjeta['fk_switch'];

            if ($fkSwitch !== null) {
                $preparadas[$indice]['Switch'] = $nombres[$fkSwitch] ?? null;
            }
        }

        return $preparadas;
    }

    /**
     * Estado actual de las tarjetas del activo, en forma comparable para saber
     * si una sincronización produjo cambios.
     *
     * @return array<int, array<string, mixed>>
     */
    private function retratoTarjetas(Activo $activo): array
    {
        return $activo->tarjetasRed()
            ->get()
            ->map(fn (TarjetaRed $tarjeta): array => [
                'Numero' => (int) $tarjeta->Numero,
                'Nombre' => $tarjeta->Nombre,
                'Direccion_MAC' => $tarjeta->Direccion_MAC,
                'IP' => $tarjeta->IP,
                'fk_switch' => $tarjeta->fk_switch === null ? null : (int) $tarjeta->fk_switch,
                'Switch' => $tarjeta->Switch,
                'Puerto' => $tarjeta->Puerto,
            ])
            ->values()
            ->all();
    }
}
