<?php

namespace App\Services;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CuentasDocentes
{
    public static function normalizarUsuario(?string $valor): string
    {
        return mb_strtoupper(preg_replace('/[.\-\s]+/u', '', trim($valor ?? '')));
    }

    public static function rutParaAcceso(?string $rut): ?string
    {
        $rut = self::normalizarUsuario($rut);

        return preg_match('/\A[0-9]{5,10}K?\z/', $rut) ? $rut : null;
    }

    public static function buscarUsuario(string $identificador): ?User
    {
        $identificador = trim($identificador);
        $exacto = User::where('rut', $identificador)->first();
        if ($exacto) return $exacto;

        $normalizado = self::normalizarUsuario($identificador);
        $rutNormalizado = "UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?";
        $coincidencias = User::whereRaw($rutNormalizado, [$normalizado])->limit(2)->get();
        if ($coincidencias->isNotEmpty() || self::rutParaAcceso($normalizado) === null) {
            return $coincidencias->count() === 1 ? $coincidencias->first() : null;
        }

        $docentes = User::whereHas('profesor', fn ($profesor) => $profesor->where('estado', true)
            ->whereRaw($rutNormalizado, [$normalizado]))->limit(2)->get();

        return $docentes->count() === 1 ? $docentes->first() : null;
    }

    public function asegurarCuenta(Profesor $referencia): ?User
    {
        try {
            return DB::transaction(function () use ($referencia) {
                $profesor = Profesor::lockForUpdate()->findOrFail($referencia->id);
                if (! $profesor->estado) return null;

                $vinculado = User::where('profesor_id', $profesor->id)->lockForUpdate()->first();
                if ($vinculado) return $vinculado;

                $rut = self::normalizarUsuario($profesor->rut);
                $correo = $this->correoProfesor($profesor);
                if ($rut === '' && $correo === null) return null;
                if (mb_strlen($rut) > 12) return null;

                $usuarios = User::orderBy('id')->lockForUpdate()->get();
                $coincidentes = $usuarios->filter(fn ($usuario) =>
                    ($rut !== '' && self::normalizarUsuario($usuario->rut) === $rut)
                    || ($correo !== null && mb_strtolower(trim($usuario->email ?? '')) === $correo));
                if ($coincidentes->count() > 1) return null;

                $usuario = $coincidentes->first();
                if ($usuario) {
                    if ($usuario->profesor_id && (int) $usuario->profesor_id !== (int) $profesor->id) return null;
                    if ($rut !== '' && self::normalizarUsuario($usuario->rut) !== $rut) return null;
                    $usuario->forceFill(['profesor_id' => $profesor->id])->save();

                    return $usuario;
                }

                $login = $rut ?: 'DOC'.$profesor->id;
                if (mb_strlen($login) > 12) {
                    $login = 'D'.substr(hash('sha256', (string) $profesor->id), 0, 11);
                }
                if ($usuarios->contains(fn ($cuenta) => self::normalizarUsuario($cuenta->rut) === $login)) return null;
                $temporal = Str::random(16).'a'.random_int(10, 99);
                $usuario = new User([
                    'name' => trim($profesor->nombre.' '.$profesor->apellido),
                    'rut' => $login, 'email' => $correo, 'password' => $temporal,
                ]);
                $usuario->forceFill([
                    'profesor_id' => $profesor->id,
                    'debe_cambiar_password' => true,
                    'clave_temporal' => $temporal,
                ])->save();
                $rol = Role::findOrCreate('Docente', 'web');
                $rol->givePermissionTo([
                    Permission::findOrCreate('enviar propuesta horario', 'web'),
                    Permission::findOrCreate('ver programacion semestral', 'web'),
                ]);
                $usuario->assignRole($rol);

                return $usuario;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // Otra creación pudo ocupar el correo o identificador. No enlazar una identidad distinta.
            return User::where('profesor_id', $referencia->id)->first();
        }
    }

    public function correoProfesor(Profesor $profesor): ?string
    {
        foreach ([$profesor->correo_institucional, $profesor->correo_personal] as $correo) {
            $correo = mb_strtolower(trim($correo ?? ''));
            if (filter_var($correo, FILTER_VALIDATE_EMAIL)) return $correo;
        }

        return null;
    }
}
