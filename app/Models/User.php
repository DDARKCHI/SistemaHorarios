<?php

namespace App\Models;

use App\Notifications\ResetPasswordPersonalizado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'rut',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'clave_temporal',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'clave_temporal' => 'encrypted',
            'debe_cambiar_password' => 'boolean',
            'primer_ingreso_en' => 'datetime',
            'ultimo_ingreso_en' => 'datetime',
        ];
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class);
    }

    public function enviosAcceso()
    {
        return $this->hasMany(EnvioAccesoDocente::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordPersonalizado($token));
    }
}
