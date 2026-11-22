<?php
function base_path(string $path = ''): string { return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''); }
function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key,$value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}
function env(string $key, mixed $default=null): mixed { $v = $_ENV[$key] ?? getenv($key); return ($v === false || $v === null || $v === '') ? $default : $v; }
function config(string $key, mixed $default=null): mixed {
    static $configs=[];
    [$file,$item] = array_pad(explode('.', $key, 2),2,null);
    if (!isset($configs[$file])) $configs[$file] = require base_path("config/$file.php");
    return $item ? ($configs[$file][$item] ?? $default) : $configs[$file];
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c=config('database');
    $dsn="mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo=new PDO($dsn,$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
/**
 * URL interna relativa al host actual (sólo se usa la ruta base de APP_URL).
 * Evita perder la sesión cuando se accede por 127.0.0.1 y APP_URL apunta a localhost.
 */
function url(string $path=''): string { $base=rtrim((string)(parse_url(config('app.url',''),PHP_URL_PATH) ?? ''),'/'); return $base . '/' . ltrim($path,'/'); }
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function request_method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
function request_path(): string { $p=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/'; $base=rtrim((string)(parse_url(config('app.url',''),PHP_URL_PATH) ?? ''),'/'); if($base && str_starts_with($p,$base)) $p=substr($p,strlen($base)) ?: '/'; return $p; }
function input(string $key, mixed $default=null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }
/** Lee un parámetro GET como string recortado (seguro ante arrays). */
function query_str(string $key, string $default=''): string { $v=$_GET[$key] ?? $default; return is_string($v) ? trim($v) : $default; }
function flash(string $key, ?string $value=null): ?string { if ($value!==null){$_SESSION['_flash'][$key]=$value; return null;} $v=$_SESSION['_flash'][$key]??null; unset($_SESSION['_flash'][$key]); return $v; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if(request_method()==='POST' && !hash_equals($_SESSION['_csrf']??'', (string)($_POST['_csrf']??''))){ http_response_code(419); exit('CSRF token inválido o sesión expirada. Recargue la página.'); } }
/** Rol del ERP (tabla roles.nombre, p. ej. «Bayer») → rol del Data Hub (ADMIN, DIGITADOR, SUPERVISOR, GERENCIA, BAYER). Null si no tiene acceso. */
function rol_datahub(string $rolErp): ?string {
    $rolMap = ['administrador' => 'ADMIN', 'digitador' => 'DIGITADOR', 'supervisor' => 'SUPERVISOR', 'gerencia' => 'GERENCIA', 'bayer' => 'BAYER'];
    return $rolMap[strtolower(trim($rolErp))] ?? null;
}
function auth_user(): ?array {
    if (!empty($_SESSION['auth_user'])) return $_SESSION['auth_user'];
    if (!empty($_SESSION['usuario'])) {
        $u = $_SESSION['usuario'];
        // Un rol del ERP que no esté mapeado no recibe acceso (antes caía por defecto en ADMIN)
        $rol = rol_datahub((string)($u['rol'] ?? ''));
        if ($rol === null) return null;
        $_SESSION['auth_user'] = ['id' => $u['id'], 'nombre' => $u['nombre'], 'email' => (string)($u['email'] ?? ''), 'role' => $rol];
        return $_SESSION['auth_user'];
    }
    return null;
}
function has_role(string ...$roles): bool { $u=auth_user(); return $u && in_array($u['role']??'', $roles, true); }
function require_auth(): void { if(!auth_user()) redirect('/login'); }
function require_role(string ...$roles): void { require_auth(); if(!has_role(...$roles)){http_response_code(403); view('errors.403'); exit;} }
function view(string $name, array $data=[]): void { extract($data); $view=base_path('app/Views/'.str_replace('.','/',$name).'.php'); require base_path('app/Views/layouts/header.php'); require $view; require base_path('app/Views/layouts/footer.php'); }
/** Renderiza una vista parcial sin layout. */
function partial(string $name, array $data=[]): void { extract($data); require base_path('app/Views/'.str_replace('.','/',$name).'.php'); }
function json_response(mixed $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function log_event(string $message, array $context=[]): void { @file_put_contents(base_path('storage/logs/app.log'), '['.date('c').'] '.$message.' '.json_encode($context,JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND); }
function audit(string $module,string $action,?int $entityId=null): void { $u=auth_user(); if(!$u)return; try{$st=db()->prepare('INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora) VALUES(?,?,?,?,NOW())');$st->execute([$u['id'],$module,mb_substr($action,0,30),$entityId]);}catch(Throwable $e){log_event('audit_error',['e'=>$e->getMessage()]);} }

// ---------------------------------------------------------------------------
// Presentación
// ---------------------------------------------------------------------------
function fmt_num(mixed $n, int $dec=0): string { return number_format((float)$n, $dec, '.', ','); }
function fmt_money(mixed $n, string $cur='PEN'): string { return ($cur==='PEN'?'S/ ':$cur.' ').number_format((float)$n, 2, '.', ','); }
function fmt_date(?string $d): string { if(!$d) return '—'; $t=strtotime($d); return $t?date('d/m/Y',$t):e($d); }
function fmt_compact(float $n): string { $a=abs($n); if($a>=1e6) return fmt_num($n/1e6,1).' M'; if($a>=1e3) return fmt_num($n/1e3,1).' k'; return fmt_num($n); }
function status_badge(string $s): string {
    $map=['BORRADOR'=>['secondary','pencil','Borrador'],'VALIDADO'=>['info','check2-circle','Validado'],'PUBLICADO'=>['success','cloud-check','Publicado'],'ANULADA'=>['danger','x-octagon','Anulada'],'APROBADA'=>['success','check-circle','Aprobada'],'COMPLETADO'=>['success','check-circle','Completado'],'FALLIDO'=>['danger','x-circle','Fallido'],'EN_PROCESO'=>['warning','hourglass-split','En proceso']];
    [$c,$i,$l]=$map[$s] ?? ['light','dot',$s];
    return '<span class="badge-status badge-'.$c.'"><i class="bi bi-'.$i.'"></i>'.e($l).'</span>';
}
/** Construye querystring conservando filtros actuales. */
function qs(array $override=[], array $drop=[]): string { $q=array_merge($_GET,$override); foreach($drop as $d) unset($q[$d]); $q=array_filter($q,fn($v)=>$v!=='' && $v!==null); return $q?'?'.http_build_query($q):''; }
function page_param(): int { return max(1,(int)query_str('page','1')); }
function per_page_param(int $default=25): int { $p=(int)query_str('per',(string)$default); return in_array($p,[10,25,50,100],true)?$p:$default; }
function valid_date(string $v): bool { $d=DateTime::createFromFormat('Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }

// ---------------------------------------------------------------------------
// Polyfills: el entorno puede no tener mbstring habilitado.
// ---------------------------------------------------------------------------
if (!function_exists('mb_substr')) { function mb_substr(string $s,int $start,?int $len=null,?string $enc=null): string { $r=iconv_substr($s,$start,$len??iconv_strlen($s,'UTF-8'),'UTF-8'); return $r===false?'':$r; } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s,?string $enc=null): int { $r=iconv_strlen($s,'UTF-8'); return $r===false?strlen($s):$r; } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $s,?string $enc=null): string { return strtr(strtoupper($s),['á'=>'Á','é'=>'É','í'=>'Í','ó'=>'Ó','ú'=>'Ú','ñ'=>'Ñ','ü'=>'Ü']); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s,?string $enc=null): string { return strtr(strtolower($s),['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ñ'=>'ñ','Ü'=>'ü']); } }
if (!function_exists('mb_check_encoding')) { function mb_check_encoding(?string $s=null,?string $enc=null): bool { return $s===null || (bool)preg_match('//u',$s); } }
/**
 * Trae al Data Hub la operación real del ERP (comprobantes, despachos y stock) antes de mostrar las vistas.
 * Es idempotente y rápida cuando no hay cambios; si falla, registra el error y la vista se muestra con lo ya sincronizado.
 */
function sync_erp(): ?array {
    static $hecho = null;
    if ($hecho !== null) return $hecho;
    try { return $hecho = (new App\Services\ErpSyncService())->run(); }
    catch (Throwable $e) { log_event('erp_sync_error', ['e' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]); return $hecho = []; }
}
