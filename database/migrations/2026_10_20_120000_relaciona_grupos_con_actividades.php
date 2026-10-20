<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La base existente puede haber recibido esta corrección manualmente.
        if (! Schema::hasColumn('grupos_seccion', 'seccion_id')) {
            if (! Schema::hasColumn('grupos_seccion', 'actividad_seccion_id')) {
                throw new RuntimeException('La tabla grupos_seccion no tiene una relación reconocida.');
            }

            return;
        }

        // Resolver todos los grupos antes de modificar columnas o registros.
        $asignaciones = $this->resolverActividades();
        $tieneActividad = Schema::hasColumn('grupos_seccion', 'actividad_seccion_id');

        if ($tieneActividad) {
            foreach (DB::table('grupos_seccion')->whereNotNull('actividad_seccion_id')->get() as $grupo) {
                if ((int) $grupo->actividad_seccion_id !== $asignaciones[$grupo->id]) {
                    throw new RuntimeException("El grupo {$grupo->id} tiene una conversión parcial incompatible. Revisar antes de continuar.");
                }
            }
        } else {
            Schema::table('grupos_seccion', function (Blueprint $table) {
                $table->unsignedBigInteger('actividad_seccion_id')->nullable();
            });
        }

        DB::transaction(function () use ($asignaciones) {
            foreach ($asignaciones as $grupoId => $actividadId) {
                DB::table('grupos_seccion')->where('id', $grupoId)->update([
                    'actividad_seccion_id' => $actividadId,
                ]);
            }
        });

        Schema::table('grupos_seccion', function (Blueprint $table) {
            $table->unsignedBigInteger('actividad_seccion_id')->nullable(false)->change();
        });

        if (! $this->tieneRelacion('actividad_seccion_id')) {
            Schema::table('grupos_seccion', function (Blueprint $table) {
                $table->foreign('actividad_seccion_id')
                    ->references('id')->on('actividades_seccion')->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('grupos_seccion', 'grupo_actividad_unique')) {
            Schema::table('grupos_seccion', function (Blueprint $table) {
                $table->unique(['actividad_seccion_id', 'codigo'], 'grupo_actividad_unique');
            });
        }

        if ($this->tieneRelacion('seccion_id')) {
            Schema::table('grupos_seccion', function (Blueprint $table) {
                $table->dropForeign(['seccion_id']);
            });
        }

        if (Schema::hasIndex('grupos_seccion', 'grupo_seccion_unique')) {
            Schema::table('grupos_seccion', function (Blueprint $table) {
                $table->dropUnique('grupo_seccion_unique');
            });
        }

        Schema::table('grupos_seccion', function (Blueprint $table) {
            $table->dropColumn('seccion_id');
        });
    }

    public function down(): void
    {
        // Varias actividades pueden tener el mismo código de grupo por sección.
        // Revertir también desharía correcciones manuales previas a esta migración.
        throw new RuntimeException(
            'Esta adaptación no admite rollback automático. Restaurar un respaldo verificado si se necesita volver al esquema anterior.'
        );
    }

    private function resolverActividades(): array
    {
        $actividades = DB::table('actividades_seccion')->get(['id', 'seccion_id']);
        $porSeccion = $actividades->groupBy('seccion_id');
        $porId = $actividades->keyBy('id');
        // Incluir también horarios históricos o inactivos: sus vínculos deben conservarse.
        $porGrupo = DB::table('horarios')
            ->whereNotNull('grupo_seccion_id')
            ->select('grupo_seccion_id', 'actividad_seccion_id')
            ->distinct()->get()->groupBy('grupo_seccion_id');
        $asignaciones = [];

        foreach (DB::table('grupos_seccion')->get(['id', 'seccion_id']) as $grupo) {
            $candidatas = $porGrupo->get($grupo->id, collect())->pluck('actividad_seccion_id');

            if ($candidatas->isEmpty()) {
                $candidatas = $porSeccion->get($grupo->seccion_id, collect())->pluck('id');
            }

            if ($candidatas->count() !== 1) {
                throw new RuntimeException(
                    "No se puede determinar una única actividad para el grupo {$grupo->id}. Revisar sus actividades y horarios antes de migrar."
                );
            }

            $actividadId = (int) $candidatas->first();
            $actividad = $porId->get($actividadId);

            if (! $actividad || (int) $actividad->seccion_id !== (int) $grupo->seccion_id) {
                throw new RuntimeException("El grupo {$grupo->id} tiene horarios de una actividad que no pertenece a su sección.");
            }

            $asignaciones[$grupo->id] = $actividadId;
        }

        return $asignaciones;
    }

    private function tieneRelacion(string $columna): bool
    {
        return collect(Schema::getForeignKeys('grupos_seccion'))
            ->contains(fn (array $relacion) => $relacion['columns'] === [$columna]);
    }
};
