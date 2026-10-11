<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompraRequest extends FormRequest
{
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
            'Numero_Requisicion' => ['nullable', 'string', 'max:50'],
            'Fecha_Compra' => ['required', 'date'],
            'Proveedor' => ['required', 'string', 'max:150'],
            'Costo_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'Tiempo_garantia' => ['nullable', 'string', 'max:50'],
            'Numero_factura' => ['nullable', 'string', 'max:50'],
            'Garantia' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Fecha_Compra.required' => 'La fecha de compra es obligatoria',
            'Fecha_Compra.date' => 'La fecha de compra no es válida',
            'Proveedor.required' => 'El proveedor es obligatorio',
            'Costo_compra.numeric' => 'El costo debe ser un número',
            'Costo_compra.min' => 'El costo no puede ser negativo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Numero_Requisicion' => 'número de requisición',
            'Fecha_Compra' => 'fecha de compra',
            'Proveedor' => 'proveedor',
            'Costo_compra' => 'costo de compra',
            'Tiempo_garantia' => 'tiempo de garantía',
            'Numero_factura' => 'número de factura',
            'Garantia' => 'garantía',
        ];
    }
}
