<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailable', 200)->nullable();   // nombre corto de la clase Mailable
            $table->string('subject', 500)->nullable();
            $table->string('to_email', 300);
            $table->string('to_name', 200)->nullable();
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error_message')->nullable();
            $table->longText('body_html')->nullable();     // HTML para preview
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('to_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
