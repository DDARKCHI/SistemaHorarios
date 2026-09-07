<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcionCambioHorario extends Model
{
    protected $table = 'opciones_cambio_horario';

    protected $fillable = [
        'solicitud_cambio_horario_id',
        'dia_id',
        'bloque_horario_id',
        'prioridad',
        'observaciones',
        'estado',
    ];

    public function solicitudCambioHorario()
    {
        return $this->belongsTo(SolicitudCambioHorario::class);
    }

    public function dia()
    {
        return $this->belongsTo(Dia::class);
    }

    public function bloqueHorario()
    {
        return $this->belongsTo(BloqueHorario::class);
    }
}