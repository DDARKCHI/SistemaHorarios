<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionInstitucion extends Model
{
    protected $table = 'configuracion_institucion';
    protected $fillable = ['universidad_id', 'nombre_sistema', 'nombre_institucion', 'sigla', 'color_principal', 'color_secundario', 'color_fondo', 'lema_sistema', 'logo', 'logo_compacto', 'logo_institucion', 'favicon', 'identidad_activa', 'estado'];
    protected $casts = ['estado' => 'boolean', 'identidad_activa' => 'boolean'];
    public function universidad() { return $this->belongsTo(Universidad::class); }
}
