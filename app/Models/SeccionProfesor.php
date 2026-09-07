<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeccionProfesor extends Model
{
    protected $table = 'secciones_profesores';

    protected $fillable = [
        'seccion_id',
        'profesor_id',
        'tipo_participacion',
        'es_principal',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
    ];

    public function seccion()
    {
        return $this->belongsTo(Seccion::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }
}