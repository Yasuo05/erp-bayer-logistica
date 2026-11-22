<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !in_array('--qa', $argv, true)) { fwrite(STDERR,"Uso: php tests/logistica_sedes.php --qa\n"); exit(1); }
require dirname(__DIR__).'/core/auth.php';
require dirname(__DIR__).'/modulos/logistica/stock_lib.php';
require dirname(__DIR__).'/modulos/logistica/workflow.php';
$results=[];$baseline=null;$rolledBack=false;
function branch_check(string $name,bool $ok): void {
    global $results;
    $results[]=['caso'=>$name,'ok'=>$ok];
    if (!$ok) throw new RuntimeException($name);
}
function branch_snapshot(PDO $pdo): array {
    return [
        'sedes'=>$pdo->query('SELECT id,estado FROM sucursales ORDER BY id')->fetchAll(),
        'asignaciones'=>$pdo->query('SELECT * FROM usuario_sucursal ORDER BY usuario_id,sucursal_id')->fetchAll(),
        'saldos'=>$pdo->query('SELECT * FROM g7_saldos ORDER BY id')->fetchAll(),
        'envios'=>$pdo->query('SELECT * FROM g7_envios ORDER BY despacho_id')->fetchAll(),
    ];
}
try {
    $config=parse_ini_file(dirname(__DIR__).'/.env');
    $normalize=fn(string $s)=>strtolower(rtrim(str_replace('\\','/',$s),'/'));
    $actual=(string)$pdo->query('SELECT @@datadir')->fetchColumn();
    $expected=dirname(__DIR__).'/.runtime/mariadb';
    if (($config['DB_HOST']??'')!=='127.0.0.1' || (int)($config['DB_PORT']??0)!==43327 || $normalize($actual)!==$normalize($expected)) throw new RuntimeException('Sólo se permite el directorio QA local exacto.');
    $baseline=branch_snapshot($pdo);
    $actor=$pdo->query("SELECT u.id,u.nombre,LOWER(r.nombre) rol FROM usuarios u JOIN usuario_rol ur ON ur.usuario_id=u.id JOIN roles r ON r.id=ur.rol_id WHERE u.estado=1 AND LOWER(r.nombre)='administrador' ORDER BY u.id LIMIT 1")->fetch();
    if (!$actor) throw new RuntimeException('Falta administrador de ejemplo.');
    $_SESSION['usuario']=$actor;unset($_SESSION['sede_activa']);
    $pdo->beginTransaction();
    $branch=(int)$pdo->query('SELECT id FROM sucursales WHERE estado=1 ORDER BY id DESC LIMIT 1')->fetchColumn();
    $pdo->prepare('DELETE FROM usuario_sucursal WHERE usuario_id=?')->execute([$actor['id']]);
    $pdo->prepare('INSERT INTO usuario_sucursal(usuario_id,sucursal_id) VALUES(?,?)')->execute([$actor['id'],$branch]);
    $pdo->prepare('UPDATE sucursales SET estado=0 WHERE id=?')->execute([$branch]);
    branch_check('Asignación únicamente inactiva no concede otras sedes',sedes_permitidas()===[]);
    branch_check('Filtro general sin sedes no devuelve operaciones',sede_filtro('sucursal_id')[0]===' AND 1 = 0');
    branch_check('Almacenes permitidos vacíos no significan acceso global',g7_almacenes_permitidos($pdo)===[]);
    branch_check('Lista logística queda vacía',g7_envios_listar($pdo)===[]);
    $id=(int)$pdo->query('SELECT despacho_id FROM g7_envios ORDER BY despacho_id LIMIT 1')->fetchColumn();
    $denied=false;
    try { g7_envio_expediente($pdo,$id); } catch (ApiError $e) { $denied=$e->statusCode===403; }
    branch_check('Consulta directa de expediente sin sede rechazada',$denied);
    $denied=false;
    try { g7_envio_operar($pdo,$id,'configurar',['version'=>1,'idempotency_key'=>'qa-inactive-sede','destino'=>'No guardar','transporte'=>'No guardar']); } catch (ApiError $e) { $denied=$e->statusCode===403; }
    branch_check('Comando sin sede se rechaza antes del replay',$denied);
} catch (Throwable $e) { $error=$e->getMessage(); }
finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); $rolledBack=true; }
    if ($baseline!==null && $rolledBack) branch_check('Rollback conserva sedes, asignaciones, existencias y envíos',$baseline===branch_snapshot($pdo));
}
$output=['ok'=>!isset($error)&&$rolledBack,'pasadas'=>count(array_filter($results,fn($r)=>$r['ok'])),'rollback_final'=>$rolledBack,'casos'=>$results];
if (isset($error)) $output['error']=$error;
if ($baseline!==null) file_put_contents(dirname(__DIR__).'/.runtime/resultado-sedes.json',json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($output['ok']?0:1);
