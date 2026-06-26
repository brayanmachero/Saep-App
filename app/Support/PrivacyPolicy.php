<?php

namespace App\Support;

class PrivacyPolicy
{
    public const VERSION = '1.1';

    public static function internalConsentText(): string
    {
        return 'Acepto la política de tratamiento de datos personales de SAEP conforme a la Ley 19.628 y su reforma por Ley 21.719.';
    }

    public static function publicHiringConsentText(): string
    {
        return 'Acepto que SAEP trate mis datos personales y documentos de postulación para verificar mi identidad, gestionar el proceso de contratación, contactar a RRHH y cumplir obligaciones legales laborales.';
    }

    public static function publicLeyKarinConsentText(): string
    {
        return 'Acepto que SAEP trate mis datos personales y antecedentes de la denuncia para gestionar, investigar y documentar el canal Ley Karin, con confidencialidad y acceso restringido.';
    }

    public static function publicArcoConsentText(): string
    {
        return 'Acepto que SAEP trate los datos de contacto e identificación informados en esta solicitud para verificar mi identidad, tramitar el ejercicio de derechos de protección de datos y comunicar el resultado del proceso.';
    }
}
