<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Preparo la acción que se ejecuta al escribir php artisan inspire
$mostrarFrase = function () {
    $frase = Inspiring::quote();
    $this->comment($frase);
};

$comandoInspire = Artisan::command('inspire', $mostrarFrase);
$comandoInspire->purpose('Display an inspiring quote');
