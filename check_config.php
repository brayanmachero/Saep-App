<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Mail\BienvenidaUsuarioMail;
use Illuminate\Support\Facades\Mail;

$val = App\Models\Configuracion::get('notificaciones_email', 'NO_EXISTE');
echo "notificaciones_email = [{$val}]\n";
echo "MAIL_MAILER = " . config('mail.default') . "\n";
echo "MAIL_FROM = " . config('mail.from.address') . "\n";

// Test rendering the mailable
$user = User::first();
echo "\nTest user: {$user->name} ({$user->email})\n";

try {
    $mailable = new BienvenidaUsuarioMail($user, 'TEST123abc');
    $rendered = $mailable->render();
    echo "Mailable renders OK (" . strlen($rendered) . " bytes)\n";
} catch (\Throwable $e) {
    echo "RENDER ERROR: " . $e->getMessage() . "\n";
}

// Try actually sending
try {
    Mail::to($user->email)->send(new BienvenidaUsuarioMail($user, 'TEST123abc'));
    echo "Mail::send() completed without exception\n";
} catch (\Throwable $e) {
    echo "SEND ERROR: " . get_class($e) . " => " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
