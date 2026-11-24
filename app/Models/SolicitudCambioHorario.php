<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambioHorario extends Model
{
    protected $table = 'solicitudes_cambio_horario';

    protected $fillable = [
        'horario_id',
        'profesor_id',
        'usuario_id',
        'motivo',
        'descripcion',
        'estado',
        'fecha_solicitud',
        'fecha_respuesta',
        'respuesta',
        'estado_activo',
        'fecha_limite', 'firma_origen', 'origen', 'revisado_por', 'revisado_en', 'motivo_revision',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_limite' => 'datetime', 'origen' => 'array', 'revisado_en' => 'datetime',
        'fecha_respuesta' => 'datetime',
        'estado_activo' => 'boolean',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function opciones()
    {
        return $this->hasMany(OpcionCambioHorario::class);
    }
    public function respuestasDocentes() { return $this->hasMany(RespuestaSolicitudCambio::class); }
}