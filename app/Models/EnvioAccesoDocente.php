<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvioAccesoDocente extends Model
{
    protected $table = 'envios_accesos_docentes';

    protected $fillable = [
        'profesor_id', 'user_id', 'periodo_academico_id', 'solicitud_disponibilidad_id',
        'enviado_por', 'lote', 'estado', 'correo', 'enviado_en', 'error',
    ];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }

    public function profesor() { return $this->belongsTo(Profesor::class); }
    public function usuario() { return $this->belongsTo(User::class, 'user_id'); }
    public function periodoAcademico() { return $this->belongsTo(PeriodoAcademico::class); }
    public function solicitudDisponibilidad() { return $this->belongsTo(SolicitudDisponibilidad::class); }
    public function remitente() { return $this->belongsTo(User::class, 'enviado_por'); }
}
