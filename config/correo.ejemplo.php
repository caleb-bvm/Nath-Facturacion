<?php
/* Copie este archivo fuera del directorio público y configure
 * FACTURACION_CORREO_PRIVADO con la ruta absoluta de la copia. */
return [
    'host' => 'mail.ourpxi.dev',
    'puerto' => 465,
    'cifrado' => 'ssl', // TLS implícito en el puerto 465.
    'auth' => 'LOGIN', // También admite PLAIN.
    'usuario' => '',
    'clave' => '',
    'remitente' => '', // Dirección de correo autorizada por el servidor.
];
