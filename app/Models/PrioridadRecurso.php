<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioridadRecurso extends Model
{
    public const RECURSOS = ['sala' => 'Sala', 'laboratorio' => 'Laboratorio',
        'movil' => 'Laboratorio móvil', 'equipamiento' => 'Equipamiento'];

    protected $table = 'prioridades_recursos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'nivel',
        'estado',
        'asignatura_id',
        'tipo_recurso',
        'tipo_sala_id',
        'tipo_equipamiento',
        'clave_recurso',
    ];

    protected $casts = ['nivel' => 'integer', 'estado' => 'boolean'];

    public function requerimientosActividad()
    {
        return $this->hasMany(RequerimientoActividad::class);
    }

    public function asignatura() { return $this->belongsTo(Asignatura::class); }
    public function tipoSala() { return $this->belongsTo(TipoSala::class); }

    public function getRecursoDescripcionAttribute(): string
    {
        $tipo = self::RECURSOS[$this->tipo_recurso] ?? 'Recurso';
        $detalle = $this->tipoSala && !$this->tipoSala->esDeImportacion() ? ' · '.$this->tipoSala->nombre : '';
        if ($this->tipo_equipamiento) $detalle .= ' · '.$this->tipo_equipamiento;
        return $tipo.($detalle ?: ' · Todos los tipos');
    }
}
