<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RespuestaDisponibilidad extends Model
{
    protected $table = 'respuestas_disponibilidad';

    protected $fillable = [
        'solicitud_disponibilidad_id',
        'profesor_id',
        'respuesta',
        'observaciones',
        'fecha_respuesta',
    ];

    protected $casts = [
        'fecha_respuesta' => 'datetime',
    ];

    public function solicitudDisponibilidad()
    {
        return $this->belongsTo(SolicitudDisponibilidad::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }
}