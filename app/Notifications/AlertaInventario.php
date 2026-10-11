<?php

namespace App\Notifications;

use App\Models\Alerta;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AlertaInventario extends Notification
{
    use Queueable;

    public function __construct(public readonly Alerta $alerta) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alerta_id' => $this->alerta->id,
            'tipo' => $this->alerta->Tipo,
            'titulo' => $this->alerta->tipo_label,
            'mensaje' => $this->alerta->Mensaje,
            'fk_activo' => $this->alerta->fk_activo,
            'etiqueta' => $this->alerta->activo?->Etiqueta_activo,
        ];
    }
}
