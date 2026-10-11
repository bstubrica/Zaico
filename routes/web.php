<?php

use App\Http\Controllers\ActivosController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\ComprasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\ImportacionesController;
use App\Http\Controllers\MantenimientosController;
use App\Http\Controllers\NotificacionesController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Escritura limitada a Admin / SoporteTecnico y con rate limit propio
    Route::middleware(['role:Admin,SoporteTecnico', 'throttle:escritura'])->group(function () {
        Route::get('activos/nuevo', [ActivosController::class, 'create'])->name('activos.create');
        Route::post('activos', [ActivosController::class, 'store'])->name('activos.store');
        Route::get('activos/{activo}/editar', [ActivosController::class, 'edit'])->name('activos.edit');
        Route::put('activos/{activo}', [ActivosController::class, 'update'])->name('activos.update');
        Route::delete('activos/{activo}', [ActivosController::class, 'destroy'])->name('activos.destroy');
        Route::post('activos/{activo}/asignacion', [ActivosController::class, 'asignar'])->name('activos.asignar');
        Route::post('activos/{activo}/devolucion', [ActivosController::class, 'devolver'])->name('activos.devolver');
        Route::put('activos/{activo}/estado', [ActivosController::class, 'cambiarEstado'])->name('activos.estado');
        Route::post('activos/{activo}/mantenimientos', [MantenimientosController::class, 'store'])
            ->name('activos.mantenimientos.store');
        Route::post('activos/{activo}/compra', [ComprasController::class, 'store'])
            ->name('activos.compra.store');
        Route::delete('activos/{activo}/compra', [ComprasController::class, 'destroy'])
            ->name('activos.compra.destroy');
        Route::get('mantenimientos/{mantenimiento}/editar', [MantenimientosController::class, 'edit'])
            ->whereNumber('mantenimiento')
            ->name('mantenimientos.edit');
        Route::put('mantenimientos/{mantenimiento}', [MantenimientosController::class, 'update'])
            ->whereNumber('mantenimiento')
            ->name('mantenimientos.update');
        Route::delete('mantenimientos/{mantenimiento}', [MantenimientosController::class, 'destroy'])
            ->whereNumber('mantenimiento')
            ->name('mantenimientos.destroy');

        Route::resource('personal', PersonalController::class)->except(['show']);
        Route::get('personal/{personal}', [PersonalController::class, 'show'])->name('personal.show');

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

        // Alertas de inventario (Fase 8)
        Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::post('alertas/revisar', [AlertaController::class, 'revisar'])->name('alertas.revisar');

        // Catálogo de estados: gestión exclusiva de Admin
        Route::middleware('role:Admin')->group(function () {
            Route::get('estados/nuevo', [EstadosController::class, 'create'])->name('estados.create');
            Route::post('estados', [EstadosController::class, 'store'])->name('estados.store');
            Route::get('estados/{estado}/editar', [EstadosController::class, 'edit'])->name('estados.edit');
            Route::put('estados/{estado}', [EstadosController::class, 'update'])->name('estados.update');
            Route::delete('estados/{estado}', [EstadosController::class, 'destroy'])->name('estados.destroy');
        });

        // Administración de usuarios: gestión exclusiva de Admin
        Route::middleware('role:Admin')->group(function () {
            Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
            Route::get('usuarios/nuevo', [UsuarioController::class, 'create'])->name('usuarios.create');
            Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
            Route::get('usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
            Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
            Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
        });
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
    Route::get('activos/{activo}/mantenimientos', [MantenimientosController::class, 'porActivo'])
        ->name('activos.mantenimientos');
    Route::get('api/activos', [ActivosController::class, 'apiIndex'])
        ->middleware('throttle:consulta')
        ->name('api.activos');
    Route::get('estados', [EstadosController::class, 'index'])->name('estados.index');
    Route::get('compras', [ComprasController::class, 'index'])->name('compras.index');
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
});

require __DIR__.'/auth.php';
