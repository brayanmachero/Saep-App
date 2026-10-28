<?php

namespace App\Console\Commands;

use App\Models\ConsentimientoDatos;
use App\Models\ReservaVehiculo;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\VehiculoActaService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class PrepararDemoVehiculos extends Command
{
    protected $signature = 'vehiculos:demo-local';
    protected $description = 'Crea datos ficticios solo en la base SQLite aislada vehiculos-preview.sqlite';

    public function handle(): int
    {
        if (!app()->environment(['local', 'vehiculos-preview']) || config('database.default') !== 'sqlite'
            || basename(config('database.connections.sqlite.database')) !== 'vehiculos-preview.sqlite') {
            $this->error('Este comando solo funciona en la base SQLite local vehiculos-preview.sqlite.');
            return self::FAILURE;
        }
        Http::preventStrayRequests();
        config(['vehiculos_actas.mail_enabled' => false, 'vehiculos_actas.calendar_enabled' => false]);
        $user = User::firstOrCreate(['email' => 'bodega.demo@example.test'], [
            'name' => 'Bodega Demostración', 'rol_id' => Rol::where('codigo', 'BODEGA_VEHICULOS')->firstOrFail()->id,
            'password' => bcrypt('DemoVehiculos2026!'), 'activo' => true, 'must_change_password' => false,
        ]);
        ConsentimientoDatos::firstOrCreate(['user_id' => $user->id, 'vigente' => true], [
            'version_politica' => 'DEMO-LOCAL', 'texto_aceptado' => 'Registro ficticio para probar exclusivamente el entorno local aislado.',
            'ip_address' => '127.0.0.1', 'user_agent' => 'Demo local', 'fecha_aceptacion' => now(),
        ]);
        $actas = app(VehiculoActaService::class);
        foreach (Vehiculo::limit(3)->get() as $i => $vehiculo) {
            $reserva = ReservaVehiculo::firstOrCreate(['codigo' => 'RV-DEMO-'.($i+1)], [
                'vehiculo_id' => $vehiculo->id, 'solicitante_nombre' => 'Conductor Demo '.($i+1),
                'solicitante_email' => 'conductor'.($i+1).'@example.test', 'inicio' => now()->startOfHour(),
                'termino' => now()->addHours(3), 'destino' => 'Centro de operación · Demostración',
                'motivo' => 'Datos ficticios para revisión local', 'estado' => 'CONFIRMADA',
            ]);
            if ($i === 0 || $reserva->actasSaep()->exists()) continue;
            $photo = imagecreatetruecolor(900, 500);
            imagefill($photo, 0, 0, imagecolorallocate($photo, 226, 233, 242));
            $ink = imagecolorallocate($photo, 30, 48, 76);
            imagefilledrectangle($photo, 170, 200, 710, 330, $ink);
            imagefilledpolygon($photo, [260,200,330,130,570,130,650,200], $ink);
            $wheel = imagecolorallocate($photo, 90, 110, 140);
            imagefilledellipse($photo, 290, 330, 90, 90, $wheel); imagefilledellipse($photo, 610, 330, 90, 90, $wheel);
            imagestring($photo, 5, 190, 420, 'IMAGEN SINTETICA - DEMOSTRACION LOCAL - SIN VALIDEZ', $ink);
            $tmp = tempnam(sys_get_temp_dir(), 'saep-demo'); imagepng($photo, $tmp); imagedestroy($photo);
            $sig = imagecreatetruecolor(700, 200); imagefill($sig, 0, 0, imagecolorallocate($sig,255,255,255));
            imagestring($sig, 5, 30, 60, 'FIRMA DE PRUEBA - DEMO SIN VALIDEZ', imagecolorallocate($sig,20,30,50));
            ob_start(); imagepng($sig); $signature = 'data:image/png;base64,'.base64_encode(ob_get_clean()); imagedestroy($sig);
            $request = Request::create('/demo', 'POST', [], [], ['fotos' => [new UploadedFile($tmp, 'demo.png', 'image/png', null, true)]]);
            $request->setUserResolver(fn () => $user);
            $data = [
                'accion' => 'preparar', 'revision' => 0, 'conductor_nombre' => $reserva->solicitante_nombre, 'conductor_email' => $reserva->solicitante_email,
                'fecha' => now()->subMinutes(5)->format('Y-m-d H:i'), 'kilometraje' => 45280, 'combustible' => '3/4',
                'condiciones' => array_fill_keys(array_keys(VehiculoActaService::PARTES), 'Bueno'),
                'kit' => array_fill_keys(array_keys(VehiculoActaService::KIT), 'Presente'),
                'observaciones' => 'REGISTRO FICTICIO PARA REVISIÓN LOCAL. Fotografía y firmas sintéticas, sin validez. Ejemplo: rayón superficial en puerta trasera derecha.',
                'firma_encargado' => $signature,
            ];
            $data['condiciones']['carroceria'] = 'Con observaciones';
            try { $acta = $actas->guardar($reserva, 'entrega', $data, $request); }
            finally { @unlink($tmp); }
            if ($i === 2) {
                $actas->firmar($acta, ['contenido_hash' => $acta->contenido_hash, 'nombre_firmante' => $reserva->solicitante_nombre, 'firma_conductor' => $signature], $request, ['modalidad' => 'PRESENCIAL_ATESTIGUADA_BODEGA', 'email' => $reserva->solicitante_email]);
            }
        }
        $this->info('Demo local lista. Usuario: bodega.demo@example.test · Clave de prueba: DemoVehiculos2026!');
        return self::SUCCESS;
    }
}
