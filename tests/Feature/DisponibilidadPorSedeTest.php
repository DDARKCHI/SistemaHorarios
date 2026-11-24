<?php

namespace Tests\Feature;

use App\Models;
use App\Services\DocentesPorSede;
use App\Services\EnviosAccesosDocentes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Tests\IsolatedDatabaseTestCase;

class DisponibilidadPorSedeTest extends IsolatedDatabaseTestCase
{
    private Models\PeriodoAcademico $periodo;
    private Models\VersionPlanificacion $version;
    private Models\Sede $talcahuano;
    private Models\Sede $chillan;
    private Models\Profesor $ana;
    private Models\Profesor $pedro;
    private Models\Profesor $compartido;
    private Models\Profesor $sinSede;
    private Models\Horario $claseAna;
    private Models\User $admin;
    private int $tipoSala;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $uni = DB::table('universidades')->value('id');
        $this->admin = Models\User::create(['name'=>'Coordinación','rut'=>'17777777','email'=>'sedes@example.test','password'=>'prueba']);
        $this->admin->givePermissionTo(Permission::findOrCreate('asignar profesores','web'));
        $this->actingAs($this->admin);
        $this->periodo = Models\PeriodoAcademico::create(['universidad_id'=>$uni,'nombre'=>'2026-2','anio'=>2026,'numero_semestre'=>2,'estado'=>true]);
        $this->version = Models\VersionPlanificacion::create(['periodo_academico_id'=>$this->periodo->id,'nombre'=>'Actual','tipo'=>'semestral','version'=>'1.0','es_actual'=>true]);
        $this->talcahuano = Models\Sede::create(['universidad_id'=>$uni,'nombre'=>'Talcahuano','estado'=>true]);
        $this->chillan = Models\Sede::create(['universidad_id'=>$uni,'nombre'=>'Chillán','estado'=>true]);
        $this->tipoSala = Models\TipoSala::create(['nombre'=>'Aula'])->id;
        $this->ana=$this->docente('Ana','12345678');
        $this->pedro=$this->docente('Pedro','87654321');
        $this->compartido=$this->docente('Compartido','22222222');
        $this->sinSede=$this->docente('Sin sede','33333333');
        $this->claseAna=$this->clase($this->ana,$this->talcahuano);
        $this->clase($this->pedro,$this->chillan);
        $this->clase($this->compartido,$this->talcahuano);
        $this->clase($this->compartido,$this->chillan);
        $this->clase($this->sinSede,null);
    }

    private function docente(string $nombre,string $rut): Models\Profesor
    {
        return Models\Profesor::create(['universidad_id'=>$this->periodo->universidad_id,'nombre'=>$nombre,'apellido'=>'Prueba','rut'=>$rut,'correo_institucional'=>str_replace(' ','',$nombre).'@example.test','estado'=>true]);
    }

    private function clase(Models\Profesor $p,?Models\Sede $sede,?Models\VersionPlanificacion $version=null): Models\Horario
    {
        $n=++$this->secuencia;
        $asignatura=Models\Asignatura::create(['universidad_id'=>$this->periodo->universidad_id,'codigo'=>'RAMO'.$n,'nombre'=>'Ramo '.$n]);
        $seccion=Models\Seccion::create(['periodo_academico_id'=>$this->periodo->id,'asignatura_id'=>$asignatura->id,'codigo'=>'SEC'.$n,'semestre'=>2,'estado_activo'=>true]);
        $actividad=Models\ActividadSeccion::create(['seccion_id'=>$seccion->id,'tipo'=>'Cátedra','cantidad_horas'=>1,'estado'=>true]);
        $grupo=Models\GrupoSeccion::create(['actividad_seccion_id'=>$actividad->id,'codigo'=>'G1','numero'=>1,'estado_activo'=>true]);
        $h=Models\Horario::create(['actividad_seccion_id'=>$actividad->id,'grupo_seccion_id'=>$grupo->id,'version_planificacion_id'=>($version??$this->version)->id,
            'dia_id'=>DB::table('dias')->value('id'),'bloque_horario_id'=>DB::table('bloques_horarios')->value('id'),'estado'=>true]);
        Models\HorarioProfesor::create(['horario_id'=>$h->id,'profesor_id'=>$p->id,'estado'=>true]);
        if($sede){
            $sala=Models\Sala::create(['universidad_id'=>$this->periodo->universidad_id,'sede_id'=>$sede->id,'tipo_sala_id'=>$this->tipoSala,'codigo'=>'SALA'.$n,'capacidad'=>30]);
            Models\AsignacionSala::create(['horario_id'=>$h->id,'sala_id'=>$sala->id,'estado'=>true]);
        }
        return $h;
    }

    private function lista(?int $sede=null,array $filtros=[])
    {
        return $this->get(route('disponibilidad.index',array_filter(['periodo_id'=>$this->periodo->id,'sede_id'=>$sede]+$filtros)));
    }

    private function envio(array $datos=[]): array
    {
        return $datos+['periodo_id'=>$this->periodo->id,'sede_id'=>$this->talcahuano->id,'lote'=>(string)Str::uuid(),
            'confirmar_envio'=>1,'cantidad_confirmada'=>2,'fecha_limite'=>today()->addDays(7)->toDateString()];
    }

    public function test_filtra_por_salas_reales_e_incluye_al_docente_compartido(): void
    {
        $r=$this->lista($this->talcahuano->id)->assertOk()->assertSee('Talcahuano')->assertSee('la programación semestral actual');
        $this->assertEqualsCanonicalizing([$this->ana->id,$this->compartido->id],$r->viewData('seleccionables'));
        $this->assertSame(2,$r->viewData('docentes')->total());
        $r->assertDontSee($this->pedro->correo_institucional)->assertDontSee($this->sinSede->correo_institucional);
        $this->assertSame(4,$this->lista()->viewData('docentes')->total());
        $this->assertEqualsCanonicalizing([$this->pedro->id,$this->compartido->id],$this->lista($this->chillan->id)->viewData('seleccionables'));
        $this->assertDatabaseCount('envios_accesos_docentes',0);
    }

    public function test_la_sede_de_la_carrera_no_amplia_los_destinatarios(): void
    {
        $carrera=Models\Carrera::create(['universidad_id'=>$this->periodo->universidad_id,'sede_id'=>$this->talcahuano->id,'nombre'=>'Carrera compartida']);
        $carrera->sedes()->sync([$this->talcahuano->id,$this->chillan->id]);
        Models\Seccion::query()->each(fn($s)=>$s->carreras()->attach($carrera->id));
        $this->assertEqualsCanonicalizing([$this->ana->id,$this->compartido->id],app(DocentesPorSede::class)->ids($this->periodo,$this->talcahuano->id));
    }

    public function test_seleccionar_todos_envia_unicamente_a_los_docentes_del_filtro(): void
    {
        $this->mock(EnviosAccesosDocentes::class,function(MockInterface $m){
            $m->shouldReceive('correoDisponible')->twice()->andReturn(true);
            $m->shouldReceive('encolar')->once()->withArgs(fn($p,$ids)=>$p->id===$this->periodo->id
                && count($ids)===2 && !array_diff($ids,[$this->ana->id,$this->compartido->id]))
                ->andReturn(['enviados'=>0,'pendientes'=>2,'fallidos'=>0,'resultados'=>[]]);
        });
        $r=$this->lista($this->talcahuano->id)->assertOk();
        $this->post(route('disponibilidad.accesos.enviar'),$this->envio(['todos'=>1,'seleccion_token'=>$r->viewData('seleccionToken')]))
            ->assertSessionHasNoErrors()->assertRedirect(route('disponibilidad.index',['periodo_id'=>$this->periodo->id,'sede_id'=>$this->talcahuano->id]));
    }

    public function test_rechaza_docentes_ajenos_a_la_sede_incluso_en_seleccion_individual(): void
    {
        $this->mock(EnviosAccesosDocentes::class,fn(MockInterface $m)=>$m->shouldNotReceive('encolar'));
        $this->post(route('disponibilidad.accesos.enviar'),$this->envio(['profesores'=>[$this->ana->id,$this->pedro->id]]))
            ->assertSessionHasErrors('profesores');
        $this->assertDatabaseCount('envios_accesos_docentes',0);
    }

    public function test_rechaza_cambiar_la_sede_de_una_seleccion_guardada(): void
    {
        $this->mock(EnviosAccesosDocentes::class,function(MockInterface $m){$m->shouldReceive('correoDisponible')->once()->andReturn(true);$m->shouldNotReceive('encolar');});
        $r=$this->lista($this->talcahuano->id)->assertOk();
        $this->post(route('disponibilidad.accesos.enviar'),$this->envio([
            'todos'=>1,'sede_id'=>$this->chillan->id,'seleccion_token'=>$r->viewData('seleccionToken'),
        ]))->assertSessionHasErrors('profesores');
    }

    public function test_si_una_clase_cambia_de_sede_se_exige_revisar_la_seleccion(): void
    {
        $this->mock(EnviosAccesosDocentes::class,function(MockInterface $m){$m->shouldReceive('correoDisponible')->once()->andReturn(true);$m->shouldNotReceive('encolar');});
        $r=$this->lista($this->talcahuano->id)->assertOk();
        $this->claseAna->asignacionesSalas->first()->sala->update(['sede_id'=>$this->chillan->id]);
        $this->post(route('disponibilidad.accesos.enviar'),$this->envio(['todos'=>1,'seleccion_token'=>$r->viewData('seleccionToken')]))
            ->assertSessionHasErrors('profesores');
    }

    public function test_incluye_laboratorios_sin_necesitar_una_asignacion_de_sala_duplicada(): void
    {
        $sala=$this->claseAna->asignacionesSalas->first()->sala;
        $this->claseAna->asignacionesSalas()->update(['estado'=>false]);
        $lab=Models\Laboratorio::create(['sala_id'=>$sala->id,'nombre'=>'Computación','capacidad'=>30,'estado'=>true]);
        Models\AsignacionLaboratorio::create(['horario_id'=>$this->claseAna->id,'laboratorio_id'=>$lab->id,'estado'=>true]);
        $this->assertContains($this->ana->id,app(DocentesPorSede::class)->ids($this->periodo,$this->talcahuano->id));
    }

    public function test_la_asignacion_del_modulo_prevalece_sobre_grupo_y_seccion(): void
    {
        Models\GrupoProfesor::create(['grupo_seccion_id'=>$this->claseAna->grupo_seccion_id,'profesor_id'=>$this->pedro->id,'estado'=>true]);
        Models\SeccionProfesor::create(['seccion_id'=>$this->claseAna->actividadSeccion->seccion_id,'profesor_id'=>$this->sinSede->id,'estado'=>true]);
        $servicio=app(DocentesPorSede::class);
        $this->assertNotContains($this->pedro->id,$servicio->ids($this->periodo,$this->talcahuano->id));
        $this->claseAna->profesores()->update(['estado'=>false]);
        $this->assertContains($this->pedro->id,$servicio->ids($this->periodo,$this->talcahuano->id));
        $this->assertNotContains($this->sinSede->id,$servicio->ids($this->periodo,$this->talcahuano->id));
        Models\GrupoProfesor::where('grupo_seccion_id',$this->claseAna->grupo_seccion_id)->update(['estado'=>false]);
        $this->assertContains($this->sinSede->id,$servicio->ids($this->periodo,$this->talcahuano->id));
    }

    public function test_usa_la_importacion_anterior_si_no_hay_semestral_actual(): void
    {
        $this->version->update(['tipo'=>'anterior']);
        $this->lista($this->talcahuano->id)->assertOk()->assertSee('la última programación anterior')
            ->assertViewHas('seleccionables',fn($ids)=>count($ids)===2);
        $this->version->update(['tipo'=>'semestral','es_actual'=>false]);
        $this->lista($this->talcahuano->id)->assertOk()->assertSee('aún no tiene programación')
            ->assertViewHas('seleccionables',[]);
    }

    public function test_ignora_horarios_y_asignaciones_inactivos_y_otras_versiones(): void
    {
        $otra=Models\VersionPlanificacion::create(['periodo_academico_id'=>$this->periodo->id,'nombre'=>'Borrador','tipo'=>'semestral','version'=>'2.0','es_actual'=>false]);
        $this->clase($this->pedro,$this->talcahuano,$otra);
        $this->claseAna->update(['estado'=>false]);
        $this->assertSame([$this->compartido->id],app(DocentesPorSede::class)->ids($this->periodo,$this->talcahuano->id));
    }

    public function test_el_historial_y_los_resumenes_respetan_la_sede(): void
    {
        foreach([$this->ana,$this->pedro] as $p){
            $s=Models\SolicitudDisponibilidad::create(['profesor_id'=>$p->id,'periodo_academico_id'=>$this->periodo->id,'estado'=>'Pendiente','fecha_limite'=>today()->addDays(7)]);
            $s->enviosAcceso()->create(['profesor_id'=>$p->id,'periodo_academico_id'=>$this->periodo->id,'enviado_por'=>$this->admin->id,'lote'=>(string)Str::uuid(),'estado'=>'enviado','enviado_en'=>now(),'correo'=>$p->correo_institucional]);
        }
        $this->lista($this->talcahuano->id)->assertOk()
            ->assertViewHas('resumen',fn($r)=>$r['enviados']===1&&$r['pendientes']===1)
            ->assertViewHas('historial',fn($h)=>$h->total()===1&&$h->first()->profesor_id===$this->ana->id);
    }

    public function test_una_sede_de_otra_institucion_no_puede_consultarse_ni_usarse_para_enviar(): void
    {
        $uni=Models\Universidad::create(['nombre'=>'Otra','sigla'=>'OTRA','estado'=>true]);
        $sede=Models\Sede::create(['universidad_id'=>$uni->id,'nombre'=>'Otra sede','estado'=>true]);
        $this->lista($sede->id)->assertSessionHasErrors('sede_id');
        $this->post(route('disponibilidad.accesos.enviar'),$this->envio(['sede_id'=>$sede->id,'profesores'=>[$this->ana->id],'cantidad_confirmada'=>1]))
            ->assertSessionHasErrors('sede_id');
        $this->assertDatabaseCount('envios_accesos_docentes',0);
    }

    public function test_el_filtro_conserva_la_sede_en_la_paginacion_y_en_todos_los_resultados(): void
    {
        for($i=0;$i<27;$i++)$this->clase($this->docente('Extra '.$i,(string)(40000000+$i)),$this->talcahuano);
        $r=$this->lista($this->talcahuano->id)->assertOk();
        $this->assertSame(29,$r->viewData('docentes')->total());
        $this->assertCount(25,$r->viewData('docentes')->items());
        $this->assertCount(29,$r->viewData('seleccionables'));
        $this->assertStringContainsString('sede_id='.$this->talcahuano->id,$r->viewData('docentes')->nextPageUrl());
        $this->lista($this->talcahuano->id,['page'=>2])->assertOk()->assertViewHas('docentes',fn($p)=>$p->count()===4);
    }
}
