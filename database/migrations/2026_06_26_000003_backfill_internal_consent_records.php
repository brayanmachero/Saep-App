<?php

use App\Support\PrivacyPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('consentimientos_datos')) {
            return;
        }

        if (! Schema::hasColumn('users', 'acepta_politica_datos')) {
            return;
        }

        $now = now();

        DB::table('users')
            ->where('acepta_politica_datos', true)
            ->orderBy('id')
            ->select('id', 'fecha_aceptacion_politica')
            ->chunkById(100, function ($users) use ($now) {
                foreach ($users as $user) {
                    $hasActiveConsent = DB::table('consentimientos_datos')
                        ->where('user_id', $user->id)
                        ->where('vigente', true)
                        ->whereNull('fecha_revocacion')
                        ->exists();

                    if ($hasActiveConsent) {
                        continue;
                    }

                    $acceptedAt = $user->fecha_aceptacion_politica ?: $now;

                    DB::table('consentimientos_datos')->insert([
                        'user_id' => $user->id,
                        'version_politica' => PrivacyPolicy::VERSION,
                        'texto_aceptado' => PrivacyPolicy::internalConsentText(),
                        'ip_address' => null,
                        'user_agent' => 'Backfill migracion consentimiento interno',
                        'fecha_aceptacion' => $acceptedAt,
                        'fecha_revocacion' => null,
                        'vigente' => true,
                        'created_at' => $acceptedAt,
                        'updated_at' => $now,
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        DB::table('consentimientos_datos')
            ->where('user_agent', 'Backfill migracion consentimiento interno')
            ->delete();
    }
};
