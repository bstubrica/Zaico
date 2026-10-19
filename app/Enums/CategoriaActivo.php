<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Catálogo canónico de categorías de dispositivo según `Nomenclatura.md`.
 *
 * `ACTIVOS.Categoria` es texto libre heredado del inventario histórico: la base
 * guarda el nombre canónico y aquí se resuelven los sinónimos y variantes
 * (comparación normalizada, sin acentos ni mayúsculas). Es la única fuente de
 * verdad del catálogo; `config('zaico.nomenclatura')` solo describe el orden de
 * los componentes del nombre.
 *
 * `Router` absorbe «Access Point» (infraestructura de red) pese a figurar
 * también entre los sinónimos de biométricos en el documento, para que cada
 * dispositivo se contabilice bajo una sola categoría.
 */
enum CategoriaActivo: string
{
    case Monitor = 'Monitor';
    case Desktop = 'Desktop';
    case Laptop = 'Laptop';
    case Televisor = 'Televisor';
    case Impresora = 'Impresora';
    case Router = 'Router';
    case Switch = 'Switch';
    case Almacenamiento = 'Disco Duro / Almacenamiento';
    case Servidor = 'Servidor';
    case Teclado = 'Teclado';
    case Raton = 'Ratón';
    case TelefonoIp = 'Teléfono IP';
    case TelefonoAnalogico = 'Teléfono analógico';
    case Proyector = 'Proyector';
    case Scanner = 'Scanner';
    case Camara = 'Cámara';
    case FuenteDePoder = 'Fuente de Poder';
    case Regulador = 'Regulador';
    case Regleta = 'Regleta';
    case Ups = 'UPS';
    case Tablet = 'Tablet';
    case Smartphone = 'Smartphone';
    case Radios = 'Radios';
    case Biometricos = 'Biométricos';
    case Multibam = 'Multibam';

    /**
     * Sinónimos y variantes aceptados por categoría. Se normalizan con
     * `normalizar()` al comparar, por lo que basta escribirlos en minúsculas y
     * sin acentos.
     *
     * @return array<string, array<int, string>>
     */
    public static function sinonimos(): array
    {
        return [
            self::Monitor->value => ['pantalla', 'display', 'pantalla lcd', 'pantalla led'],
            self::Desktop->value => ['pc', 'computadora de escritorio', 'cpu', 'workstation'],
            self::Laptop->value => ['notebook', 'portatil', 'ultrabook'],
            self::Televisor->value => ['pantalla grande', 'pantalla plana'],
            self::Impresora->value => ['printer', 'multifuncional', 'plotter', 'impresora laser', 'impresora tinta'],
            self::Router->value => ['enrutador', 'router inalambrico', 'access point', 'ap'],
            self::Switch->value => ['conmutador de red', 'switch ethernet'],
            self::Almacenamiento->value => ['disco duro', 'hdd', 'ssd', 'unidad de estado solido', 'memoria flash', 'pendrive', 'usb drive'],
            self::Servidor->value => ['server', 'rack server', 'blade server'],
            self::Teclado->value => ['keyboard', 'teclado inalambrico', 'teclado usb'],
            self::Raton->value => ['mouse', 'raton optico', 'raton inalambrico'],
            self::TelefonoIp->value => ['telefono ip', 'telefono voip'],
            self::TelefonoAnalogico->value => ['senal analogica'],
            self::Proyector->value => ['proyector de video', 'videoproyector'],
            self::Scanner->value => ['escaner', 'lector de documentos'],
            self::Camara->value => ['camara web', 'camara ip', 'camara digital'],
            self::FuenteDePoder->value => ['fuente de alimentacion', 'psu', 'adaptador de corriente'],
            self::Regulador->value => ['regulador de corriente electrica'],
            self::Regleta->value => ['regleta de corriente electrica'],
            self::Ups->value => ['sistema de alimentacion ininterrumpida'],
            self::Tablet->value => ['tableta', 'ipad'],
            self::Smartphone->value => ['telefono movil', 'celular'],
            self::Radios->value => ['radios de onda corta', 'walkie-talkie'],
            self::Biometricos->value => ['escaneres de huellas', 'escaner facial', 'biometrico'],
            self::Multibam->value => ['multibam con linea telefonica'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $categoria): string => $categoria->value, self::cases());
    }

    /**
     * Opciones para `<select>`: valor almacenado => etiqueta visible.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $categoria) {
            $opciones[$categoria->value] = $categoria->value;
        }

        return $opciones;
    }

    /**
     * Resuelve una categoría libre (o un sinónimo) al nombre canónico. Devuelve
     * `null` si el valor no pertenece al catálogo; vacío también es `null`.
     */
    public static function canonico(?string $valor): ?string
    {
        $normalizado = self::normalizar($valor);

        if ($normalizado === '') {
            return null;
        }

        foreach (self::cases() as $categoria) {
            if (self::normalizar($categoria->value) === $normalizado) {
                return $categoria->value;
            }
        }

        foreach (self::sinonimos() as $canonica => $sinonimos) {
            if (in_array($normalizado, $sinonimos, true)) {
                return $canonica;
            }
        }

        return null;
    }

    /**
     * Normaliza un texto para comparar categorías: minúsculas, sin espacios
     * sobrantes y sin acentos (p. ej. «Ratón» y «RATON» coinciden).
     */
    public static function normalizar(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return Str::lower(Str::ascii(trim($valor)));
    }
}
