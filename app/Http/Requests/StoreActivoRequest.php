<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasServidor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivoRequest extends FormRequest
{
    use ReglasServidor;

    protected function prepareForValidation(): void
    {
        $this->normalizarDatosServidor();
    }

    public function authorize(): bool
    {
        return $this->user()?->puedeEscribir() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Nombre_de_activo' => ['required', 'string', 'min:3', 'max:150'],
            'Etiqueta_activo' => ['required', 'string', 'max:100', 'unique:ACTIVOS,Etiqueta_activo'],
            'Serial' => ['nullable', 'string', 'max:100', 'unique:ACTIVOS,Serial'],
            'Ubicacion' => ['nullable', 'string', 'max:150'],
            'Ubicacion_Predeterminada' => ['nullable', 'string', 'max:150'],
            'fk_ubicacion' => ['nullable', Rule::exists('UBICACIONES', 'id')],
            'Fabricante' => ['nullable', 'string', 'max:100'],
            'Categoria' => ['nullable', 'string', 'max:100'],
            'Modelo' => ['nullable', 'string', 'max:100'],
            'fk_modelo' => ['nullable', Rule::exists('MODELOS_ACTIVO', 'id')],
            'Observaciones' => ['nullable', 'string'],
            'Direccion_MAC' => ['nullable', 'string', 'mac_address', 'max:17'],
            'Estado' => ['required', 'string', 'max:30', Rule::exists('ESTADOS_ACTIVO', 'Grupo')->where('Estado', 1)],
            'fk_estado' => [
                'nullable',
                Rule::exists('ESTADOS_ACTIVO', 'id')->where('Grupo', $this->input('Estado')),
            ],
            'imagen' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ] + $this->reglasServidor();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Nombre_de_activo.required' => 'El nombre del activo es obligatorio',
            'Nombre_de_activo.min' => 'El nombre del activo debe tener al menos 3 caracteres',
            'Nombre_de_activo.max' => 'El nombre del activo no puede exceder 150 caracteres',
            'Etiqueta_activo.required' => 'La etiqueta es obligatoria',
            'Etiqueta_activo.unique' => 'Ya existe un activo con esa etiqueta',
            'Etiqueta_activo.max' => 'La etiqueta no puede exceder 100 caracteres',
            'Serial.unique' => 'Ya existe un activo con ese número de serie',
            'Serial.max' => 'El número de serie no puede exceder 100 caracteres',
            'Direccion_MAC.mac_address' => 'La dirección MAC no es válida',
            'fk_ubicacion.exists' => 'La ubicación seleccionada no es válida',
            'fk_modelo.exists' => 'El modelo seleccionado no es válido',
            'Estado.required' => 'El estado es obligatorio',
            'Estado.exists' => 'El grupo de estado seleccionado no es válido',
            'fk_estado.exists' => 'El detalle de estado no corresponde al grupo seleccionado',
            'imagen.file' => 'La imagen debe ser un archivo',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP',
            'imagen.max' => 'La imagen no puede exceder 10 MB',
        ] + $this->mensajesServidor();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Nombre_de_activo' => 'nombre del activo',
            'Etiqueta_activo' => 'etiqueta',
            'Serial' => 'número de serie',
            'Ubicacion' => 'ubicación',
            'Ubicacion_Predeterminada' => 'ubicación predeterminada',
            'fk_ubicacion' => 'ubicación',
            'fk_modelo' => 'modelo',
            'Direccion_MAC' => 'dirección MAC',
            'fk_estado' => 'estado de catálogo',
            'imagen' => 'imagen del activo',
        ] + $this->atributosServidor();
    }
}
