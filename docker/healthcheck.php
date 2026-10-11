<?php
$contexto = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
$respuesta = @file_get_contents('http://127.0.0.1/api/health.php', false, $contexto);
exit($respuesta !== false && str_contains($http_response_header[0] ?? '', ' 200 ') &&
    json_decode($respuesta, true) === ['status' => 'ok'] ? 0 : 1);
