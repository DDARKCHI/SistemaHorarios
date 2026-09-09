<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioProfesor extends Model
{
    protected $table = 'horarios_profesores';

    protected $fillable = [
        'horario_id',
        'profesor_id',
        'tipo_participacion',
        'es_principal',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'estado' => 'boolean',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }
}