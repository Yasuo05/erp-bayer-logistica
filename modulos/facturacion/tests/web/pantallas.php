<?php
// Pantallas y seguridad por HTTP: todas las opciones del menú, archivos privados y protección CSRF.
declare(strict_types=1);

use Tests\Prueba;

return function (Prueba $t, string $base): void {
    /** @return array{0:int,1:string,2:string,3:array} código, cuerpo, cookie de sesión, cabeceras */
    $pedir = static function (string $ruta, ?array $post = null, string $cookie = '') use ($base): array {
        $opciones = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20, 'header' => $cookie !== '' ? "Cookie: {$cookie}\r\n" : ''];
        if ($post !== null) {
            $opciones['method'] = 'POST';
            $opciones['content'] = http_build_query($post);
            $opciones['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        }
        $cuerpo = (string) @file_get_contents($base . $ruta, false, stream_context_create(['http' => $opciones]));
        $cabeceras = $http_response_header ?? [];
        preg_match('#HTTP/\S+ (\d+)#', $cabeceras[0] ?? '', $m);
        foreach ($cabeceras as $c) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $c, $g)) $cookie = $g[1];
        }
        return [(int) ($m[1] ?? 0), $cuerpo, $cookie, $cabeceras];
    };
    $sinErrores = static fn (string $html): bool => !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Uncaught)/', $html);

    $t->grupo('Pantallas del menú');
    $rutas = ['/'];
    foreach (config('menu') as $seccion) {
        foreach ($seccion['items'] as $item) $rutas[] = $item['ruta'];
    }
    foreach (array_unique($rutas) as $ruta) {
        [$codigo, $html] = $pedir($ruta);
        $t->verdadero($codigo === 200 && $sinErrores($html), "{$ruta} carga sin errores", "HTTP {$codigo}");
    }
    [$codigo] = $pedir('/no-existe');
    $t->igual(404, $codigo, 'una ruta inexistente responde 404');

    $t->grupo('Archivos privados');
    foreach (['/../.env', '/../config/app.php', '/app/Core/Router.php', '/storage/certificados/certificado-demo.pfx', '/vendor/autoload.php'] as $privado) {
        [$codigo] = $pedir($privado);
        $t->verdadero($codigo !== 200, "{$privado} no es accesible", "HTTP {$codigo}");
    }

    $t->grupo('Protección CSRF');
    [, $html, $cookie, $cabeceras] = $pedir('/configuracion/series');
    $formularios = preg_match_all('/<form\b[^>]*method="post"/i', $html);
    preg_match_all('/name="_csrf" value="([0-9a-f]{64})"/', $html, $tokens);
    $t->verdadero($formularios > 0 && $formularios === count($tokens[1]), "los {$formularios} formularios POST llevan el código");
    $t->verdadero((bool) preg_grep('/^Set-Cookie:.*HttpOnly.*SameSite=Lax/i', $cabeceras), 'la cookie de sesión es HttpOnly y SameSite=Lax');
    // Formulario inofensivo: id=0 no existe, así que con un código válido solo vuelve con un aviso
    [$codigo] = $pedir('/envio/observacion/revisar', ['id' => 0, 'nota' => 'x']);
    $t->igual(403, $codigo, 'un envío sin código se rechaza');
    [$codigo] = $pedir('/envio/observacion/revisar', ['id' => 0, 'nota' => 'x', '_csrf' => str_repeat('0', 64)], $cookie);
    $t->igual(403, $codigo, 'un envío con un código falso se rechaza');
    [$codigo] = $pedir('/envio/observacion/revisar', ['id' => 0, 'nota' => 'x', '_csrf' => $tokens[1][0] ?? ''], $cookie);
    $t->igual(302, $codigo, 'un envío con el código de la sesión se procesa');

    $t->grupo('Parámetros de facturación');
    [$codigo, $html] = $pedir('/configuracion/parametros');
    $t->verdadero($codigo === 200 && str_contains($html, 'Certificado de firma digital') && str_contains($html, 'Migraciones'), 'muestra facturación, envío, certificado y base de datos');
    $t->verdadero(!str_contains($html, (string) \App\Core\Env::get('FIRMA_CLAVE', 'clave-que-no-existe')), 'no muestra la contraseña del certificado');
};
