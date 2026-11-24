<?php

namespace App\Services;

use App\Models\EnvioAccesoDocente;
use App\Models\Profesor;
use App\Models\ProfesorVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CorreccionRutDocente
{
    public function corregir(Profesor $profesor, ?string $rutNuevo): array
    {
        $rutAnterior = CuentasDocentes::normalizarUsuario($profesor->rut);
        $rutNuevo = CuentasDocentes::normalizarUsuario($rutNuevo) ?: null;
        $resultado = [
            'rut_anterior' => $rutAnterior ?: null,
            'rut_nuevo' => $rutNuevo,
            'cuenta_id' => null,
            'cuenta_desvinculada_id' => null,
            'envios_revinculados' => 0,
            'versiones_actualizadas' => 0,
        ];
        if (($rutAnterior ?: null) === $rutNuevo) return $resultado;

        $cuentas = User::select([
            'id', 'rut', 'email', 'profesor_id', 'debe_cambiar_password',
            'primer_ingreso_en', 'ultimo_ingreso_en',
        ])->with('roles', 'permissions')->orderBy('id')->lockForUpdate()->get();
        $vinculadas = $cuentas->where('profesor_id', $profesor->id);
        if ($vinculadas->count() > 1) {
            $this->rechazar('Hay varias cuentas vinculadas a este docente. Revisa sus accesos antes de corregir el RUT.');
        }
        $cuenta = $vinculadas->first();
        if (! $cuenta && $rutAnterior !== '') {
            $anteriores = $cuentas->filter(fn (User $usuario) =>
                CuentasDocentes::normalizarUsuario($usuario->rut) === $rutAnterior);
            if ($anteriores->isNotEmpty()) {
                $anterior = $anteriores->count() === 1 ? $anteriores->first() : null;
                $otraFicha = Profesor::whereKeyNot($profesor->id)
                    ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rutAnterior])
                    ->exists();
                if (! $anterior || $anterior->profesor_id || $otraFicha || ! $this->mismoCorreo($anterior, $profesor)) {
                    $this->rechazar('La cuenta que usa el RUT anterior necesita revisión. Comprueba el RUT, correo y vínculo docente.');
                }
                $cuenta = $anterior;
            }
        }
        if ($rutNuevo === null && $cuenta) {
            $this->rechazar('Indica el RUT nuevo para actualizar también la cuenta de acceso del docente.');
        }

        $destinos = $rutNuevo === null ? collect() : $cuentas->filter(fn (User $usuario) =>
            CuentasDocentes::normalizarUsuario($usuario->rut) === $rutNuevo);
        if ($destinos->count() > 1) {
            $this->rechazar('El RUT nuevo aparece en varias cuentas. Revisa sus accesos antes de guardarlo.');
        }
        $destino = $destinos->first();
        $provisoria = null;
        if ($destino && ! $destino->is($cuenta)) {
            if ($destino->profesor_id || ! $this->mismoCorreo($destino, $profesor)) {
                $this->rechazar('El RUT nuevo pertenece a otra cuenta. Revisa el RUT y el correo del docente.');
            }
            if ($cuenta) {
                if (! $this->esProvisoriaSinUso($cuenta) || $destino->debe_cambiar_password) {
                    $this->rechazar('El RUT nuevo pertenece a una cuenta existente. Revisa la vinculación de ambas cuentas antes de corregirlo.');
                }
                $provisoria = $cuenta;
            }
            $cuenta = $destino;
        }

        if ($provisoria) {
            $provisoria->forceFill(['profesor_id' => null])->save();
            $provisoria->syncRoles([]);
            $resultado['cuenta_desvinculada_id'] = $provisoria->id;
            $resultado['envios_revinculados'] = EnvioAccesoDocente::where('profesor_id', $profesor->id)
                ->where('user_id', $provisoria->id)->update(['user_id' => $cuenta->id]);
        }
        if ($cuenta) {
            try {
                $cuenta->forceFill(['rut' => $rutNuevo, 'profesor_id' => $profesor->id])->save();
            } catch (UniqueConstraintViolationException) {
                $this->rechazar('Otra cuenta acaba de ocupar este RUT. Actualiza la ficha y revisa los accesos antes de guardar.');
            }
            $resultado['cuenta_id'] = $cuenta->id;
        }
        $resultado['versiones_actualizadas'] = ProfesorVersion::where('profesor_id', $profesor->id)
            ->update(['rut' => $rutNuevo]);

        return $resultado;
    }

    private function mismoCorreo(User $usuario, Profesor $profesor): bool
    {
        $correo = mb_strtolower(trim($usuario->email ?? ''));
        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) return false;

        return collect([$profesor->correo_institucional, $profesor->correo_personal])
            ->contains(fn ($contacto) => mb_strtolower(trim($contacto ?? '')) === $correo);
    }

    private function esProvisoriaSinUso(User $usuario): bool
    {
        return $usuario->debe_cambiar_password
            && $usuario->primer_ingreso_en === null && $usuario->ultimo_ingreso_en === null
            && $usuario->profesor_id !== null
            && $usuario->permissions->isEmpty()
            && $usuario->roles->count() === 1
            && $usuario->roles->first()->name === 'Docente'
            && $usuario->roles->first()->guard_name === 'web';
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['rut' => $mensaje]);
    }
}
