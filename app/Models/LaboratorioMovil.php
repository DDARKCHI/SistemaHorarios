<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaboratorioMovil extends Model
{
    protected $table = 'laboratorios_moviles';

    protected $fillable = [
        'universidad_id',
        'sede_id',
        'codigo',
        'nombre',
        'capacidad',
        'descripcion',
        'disponible',
        'estado',
    ];

    protected $casts = [
        'disponible' => 'boolean',
        'estado' => 'boolean',
        'capacidad' => 'integer',
    ];

    public function universidad()
    {
        return $this->belongsTo(Universidad::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }
}
