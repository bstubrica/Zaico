<?php

use App\Http\Controllers\ActivosController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\MantenimientosController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Escritura limitada a Admin / SoporteTecnico
    Route::middleware('role:Admin,SoporteTecnico')->group(function () {
        Route::get('activos/nuevo', [ActivosController::class, 'create'])->name('activos.create');
        Route::post('activos', [ActivosController::class, 'store'])->name('activos.store');
        Route::get('activos/{activo}/editar', [ActivosController::class, 'edit'])->name('activos.edit');
        Route::put('activos/{activo}', [ActivosController::class, 'update'])->name('activos.update');
        Route::post('activos/{activo}/asignacion', [ActivosController::class, 'asignar'])->name('activos.asignar');
        Route::post('activos/{activo}/devolucion', [ActivosController::class, 'devolver'])->name('activos.devolver');
        Route::put('activos/{activo}/estado', [ActivosController::class, 'cambiarEstado'])->name('activos.estado');
        Route::post('activos/{activo}/mantenimientos', [MantenimientosController::class, 'store'])
            ->name('activos.mantenimientos.store');

        Route::resource('personal', PersonalController::class)->except(['show']);
        Route::get('personal/{personal}', [PersonalController::class, 'show'])->name('personal.show');
    });

    // Consulta: cualquier usuario autenticado
    Route::get('activos', [ActivosController::class, 'index'])->name('activos.index');
    Route::get('activos/{activo}', [ActivosController::class, 'show'])->name('activos.show');
    Route::get('activos/{activo}/mantenimientos', [MantenimientosController::class, 'porActivo'])
        ->name('activos.mantenimientos');
    Route::get('api/activos', [ActivosController::class, 'apiIndex'])->name('api.activos');
    Route::get('estados', [EstadosController::class, 'index'])->name('estados.index');
    Route::get('mantenimientos', [MantenimientosController::class, 'index'])->name('mantenimientos.index');
    Route::get('mantenimientos/evidencias/{evidencia}', [MantenimientosController::class, 'evidencia'])
        ->whereNumber('evidencia')
        ->name('mantenimientos.evidencia');
    Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');

    // Auditoría: solo Admin / Auditor
    Route::get('activos/{activo}/eventos', [ActivosController::class, 'eventos'])
        ->middleware('role:Admin,Auditor')
        ->name('activos.eventos');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
