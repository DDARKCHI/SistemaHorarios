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
        'bloques', 'revisado_por', 'revisado_en', 'motivo_revision',
    ];

    protected $casts = [
        'fecha_respuesta' => 'datetime',
        'bloques' => 'array', 'revisado_en' => 'datetime',
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