<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreProgramacion extends Model
{
    protected $table = 'cierres_programacion';
    protected $fillable = ['version_planificacion_id', 'etapa', 'usuario_id', 'firma', 'datos'];
    protected $casts = ['datos' => 'array'];
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
}
