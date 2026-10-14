<?php

use App\Http\Controllers\ActivosController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUsuarioController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\ImportacionesController;
use App\Http\Controllers\MantenimientosController;
use App\Http\Controllers\ModeloActivoController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Escritura limitada a Admin / SoporteTecnico y con rate limit propio
    Route::middleware(['role:Admin,SoporteTecnico', 'throttle:escritura'])->group(function () {
        Route::get('activos/nuevo', [ActivosController::class, 'create'])->name('activos.create');
        Route::post('activos', [ActivosController::class, 'store'])->name('activos.store');
        Route::get('activos/{activo}/editar', [ActivosController::class, 'edit'])->name('activos.edit');
        Route::put('activos/{activo}', [ActivosController::class, 'update'])->name('activos.update');
        Route::post('activos/{activo}/asignacion', [ActivosController::class, 'asignar'])->name('activos.asignar');
        Route::post('activos/{activo}/devolucion', [ActivosController::class, 'devolver'])->name('activos.devolver');
        Route::put('activos/{activo}/estado', [ActivosController::class, 'cambiarEstado'])->name('activos.estado');
        Route::put('activos/{activo}/compra', [ActivosController::class, 'guardarCompra'])->name('activos.compra.update');
        Route::post('activos/{activo}/mantenimientos', [MantenimientosController::class, 'store'])
            ->name('activos.mantenimientos.store');

        // Desincorporación individual y masiva (baja lógica de equipos)
        Route::post('activos/desincorporar', [ActivosController::class, 'desincorporarMasivo'])
            ->name('activos.desincorporar-masivo');
        Route::post('activos/{activo}/desincorporar', [ActivosController::class, 'desincorporar'])
            ->name('activos.desincorporar');

        Route::resource('personal', PersonalController::class)->except(['show']);
        Route::get('personal/{personal}', [PersonalController::class, 'show'])->name('personal.show');

        // Catálogos de inventario: ubicaciones y modelos de activos.
        Route::resource('ubicaciones', UbicacionController::class)
            ->except(['show'])
            ->parameters(['ubicaciones' => 'ubicacion']);
        Route::resource('modelos', ModeloActivoController::class)
            ->except(['show'])
            ->parameters(['modelos' => 'modelo']);
        Route::resource('estados', EstadosController::class)
            ->except(['index', 'show'])
            ->parameters(['estados' => 'estado']);

        // Baja masiva de personal (la individual es el destroy del resource)
        Route::post('personal/baja', [PersonalController::class, 'bajaMasiva'])
            ->name('personal.baja-masiva');

        // Importación CSV: visible solo para Admin / SoporteTecnico
        Route::get('importaciones', [ImportacionesController::class, 'index'])->name('importaciones.index');
        Route::post('importaciones', [ImportacionesController::class, 'store'])
            ->middleware('throttle:importacion')
            ->name('importaciones.store');
        Route::post('importaciones/confirmar', [ImportacionesController::class, 'confirmar'])
            ->middleware('throttle:importacion')
            ->name('importaciones.confirmar');
        Route::get('importaciones/{importacion}', [ImportacionesController::class, 'show'])
            ->whereNumber('importacion')
            ->name('importaciones.show');
        Route::delete('importaciones/{importacion}', [ImportacionesController::class, 'destroy'])
            ->whereNumber('importacion')
            ->name('importaciones.destroy');
        Route::post('importaciones/eliminar', [ImportacionesController::class, 'destroyMasivo'])
            ->name('importaciones.eliminar-masivo');

        // Borrado de mantenimientos (individual y masivo)
        Route::delete('mantenimientos/{mantenimiento}', [MantenimientosController::class, 'destroy'])
            ->whereNumber('mantenimiento')
            ->name('mantenimientos.destroy');
        Route::post('mantenimientos/eliminar', [MantenimientosController::class, 'destroyMasivo'])
            ->name('mantenimientos.eliminar-masivo');

        // Alertas de inventario (Fase 8)
        Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::post('alertas/revisar', [AlertaController::class, 'revisar'])->name('alertas.revisar');
    });

    // Notificaciones: cualquier usuario autenticado
    Route::post('notificaciones/leidas', [NotificacionesController::class, 'marcarLeidas'])
        ->middleware('throttle:escritura')
        ->name('notificaciones.leidas');

    // Exportación de reportes CSV: cualquier usuario autenticado
    Route::middleware('throttle:consulta')->prefix('reportes')->name('reportes.')->group(function () {
        Route::get('activos', [ReporteController::class, 'activos'])->name('activos');
        Route::get('personal', [ReporteController::class, 'personal'])->name('personal');
        Route::get('historial', [ReporteController::class, 'historial'])->name('historial');
        Route::get('mantenimientos', [ReporteController::class, 'mantenimientos'])->name('mantenimientos');
    });

    // Consulta: cualquier usuario autenticado
    Route::get('activos', [ActivosController::class, 'index'])->name('activos.index');
    Route::get('activos/buscar', [ActivosController::class, 'buscar'])->name('activos.buscar');
    Route::get('activos/{activo}', [ActivosController::class, 'show'])->name('activos.show');
    Route::get('activos/{activo}/imagen', [ActivosController::class, 'imagen'])->name('activos.imagen');
    Route::get('activos/{activo}/factura', [ActivosController::class, 'imagenFactura'])->name('activos.factura');
    Route::get('activos/{activo}/mantenimientos', [MantenimientosController::class, 'porActivo'])
        ->name('activos.mantenimientos');
    Route::get('api/activos', [ActivosController::class, 'apiIndex'])
        ->middleware('throttle:consulta')
        ->name('api.activos');
    Route::get('estados', [EstadosController::class, 'index'])->name('estados.index');
    Route::get('mantenimientos', [MantenimientosController::class, 'index'])->name('mantenimientos.index');
    Route::get('mantenimientos/evidencias/{evidencia}', [MantenimientosController::class, 'evidencia'])
        ->whereNumber('evidencia')
        ->name('mantenimientos.evidencia');
    Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');

    // Auditoría: solo Admin / Auditor
    Route::get('activos/{activo}/eventos', [ActivosController::class, 'eventos'])
        ->middleware(['role:Admin,Auditor', 'throttle:consulta'])
        ->name('activos.eventos');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:escritura')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:escritura')
        ->name('profile.destroy');

    // Administración: solo Admin. Depuración total de datos de inventario.
    Route::middleware('role:Admin')->prefix('administracion')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('depurar', [AdminController::class, 'depurar'])
            ->middleware('throttle:depuracion')
            ->name('depurar');

        // Gestión de usuarios del sistema (alta, modificación, habilitar/deshabilitar).
        Route::get('usuarios', [AdminUsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/nuevo', [AdminUsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [AdminUsuarioController::class, 'store'])
            ->middleware('throttle:escritura')
            ->name('usuarios.store');
        Route::get('usuarios/{usuario}/editar', [AdminUsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('usuarios/{usuario}', [AdminUsuarioController::class, 'update'])
            ->middleware('throttle:escritura')
            ->name('usuarios.update');
        Route::post('usuarios/{usuario}/estado', [AdminUsuarioController::class, 'toggleActivo'])
            ->middleware('throttle:escritura')
            ->name('usuarios.toggle');
    });
});

require __DIR__.'/auth.php';
