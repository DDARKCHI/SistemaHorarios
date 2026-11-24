<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RespuestaSolicitudCambio extends Model
{
    protected $table = 'respuestas_solicitud_cambio';
    protected $fillable = ['solicitud_cambio_horario_id', 'profesor_id', 'opcion_id', 'estado', 'observaciones', 'respondido_en'];
    protected $casts = ['respondido_en' => 'datetime'];
    public function solicitud() { return $this->belongsTo(SolicitudCambioHorario::class, 'solicitud_cambio_horario_id'); }
    public function profesor() { return $this->belongsTo(Profesor::class); }
    public function opcion() { return $this->belongsTo(OpcionCambioHorario::class, 'opcion_id'); }
}
