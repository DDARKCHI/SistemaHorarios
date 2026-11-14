<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sala extends Model
{
    protected $table = 'salas';

    protected $fillable = [
        'universidad_id',
        'sede_id',
        'tipo_sala_id',
        'codigo',
        'nombre',
        'capacidad',
        'edificio',
        'piso',
        'descripcion',
        'disponible',
        'estado',
    ];

    protected $casts = [
        'disponible' => 'boolean',
        'estado' => 'boolean',
        'capacidad' => 'integer',
    ];

    public function getDescripcionAttribute(?string $value): ?string
    {
        return trim($value ?? '') === 'Recurso importado desde programación Excel.' ? null : $value;
    }

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function tipoSala()
    {
        return $this->belongsTo(TipoSala::class);
    }

    public function laboratorios()
    {
        return $this->hasMany(Laboratorio::class);
    }
}
