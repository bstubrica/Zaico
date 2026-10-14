<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuardarCompraRequest extends FormRequest
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
            'Numero_Requisicion' => ['nullable', 'string', 'max:100'],
            'Numero_factura' => ['nullable', 'string', 'max:100'],
            'Proveedor' => ['nullable', 'string', 'max:150'],
            'Fecha_Compra' => ['nullable', 'date'],
            'Costo_compra' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'Tiempo_garantia' => ['nullable', 'string', 'max:100'],
            'Garantia' => ['nullable', 'string', 'max:150'],
            'imagen_factura' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Numero_Requisicion.max' => 'El número de requisición no puede exceder 100 caracteres',
            'Numero_factura.max' => 'El número de factura no puede exceder 100 caracteres',
            'Proveedor.max' => 'El proveedor no puede exceder 150 caracteres',
            'Fecha_Compra.date' => 'La fecha de compra no es válida',
            'Costo_compra.numeric' => 'El costo de compra debe ser un número',
            'Costo_compra.min' => 'El costo de compra no puede ser negativo',
            'Costo_compra.max' => 'El costo de compra es demasiado alto',
            'Tiempo_garantia.max' => 'El tiempo de garantía no puede exceder 100 caracteres',
            'Garantia.max' => 'La garantía no puede exceder 150 caracteres',
            'imagen_factura.mimes' => 'La foto de la factura debe ser JPG, PNG o WEBP',
            'imagen_factura.max' => 'La foto de la factura no puede superar 10 MB',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Numero_Requisicion' => 'número de requisición',
            'Numero_factura' => 'número de factura',
            'Proveedor' => 'proveedor',
            'Fecha_Compra' => 'fecha de compra',
            'Costo_compra' => 'costo de compra',
            'Tiempo_garantia' => 'tiempo de garantía',
            'Garantia' => 'garantía',
            'imagen_factura' => 'foto de la factura',
        ];
    }
}
