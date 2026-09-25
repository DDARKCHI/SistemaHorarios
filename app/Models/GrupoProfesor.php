<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoProfesor extends Model
{
    protected $table = 'grupos_profesores';

    protected $fillable = [
        'grupo_seccion_id',
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

    public function grupoSeccion()
    {
        return $this->belongsTo(GrupoSeccion::class);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }
}