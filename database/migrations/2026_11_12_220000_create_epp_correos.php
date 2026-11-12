<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('epp_correos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('solicitudes_epp');
            $t->string('clave')->unique();
            $t->json('mensaje');
            $t->timestamp('primer_intento')->nullable();
            $t->timestamp('proximo_intento')->nullable()->index();
            $t->string('resend_id')->nullable();
            $t->string('error')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('epp_correos');
    }
};
