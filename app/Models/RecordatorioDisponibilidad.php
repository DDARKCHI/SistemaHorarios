<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordatorioDisponibilidad extends Model
{
    public const DIAS_ANTES = [3, 1];

    protected $table = 'recordatorios_disponibilidad';

    protected $fillable = [
        'solicitud_disponibilidad_id', 'envio_acceso_docente_id',
        'dias_antes', 'enviar_el', 'estado', 'enviado_en', 'error',
    ];

    protected function casts(): array
    {
        return [
            'dias_antes' => 'integer',
            'enviar_el' => 'date',
            'enviado_en' => 'datetime',
        ];
    }
}
