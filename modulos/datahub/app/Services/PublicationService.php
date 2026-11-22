<?php
namespace App\Services;

/**
 * Flujo de estados BORRADOR → VALIDADO → PUBLICADO con reglas Bayer y trazabilidad
 * (publicaciones, detalle_publicacion, validaciones, auditoría). Todo en transacción.
 */
class PublicationService
{
    public function __construct(private BayerRulesService $rules = new BayerRulesService()) {}

    /**
     * Cambia el estado de varios registros de un módulo.
     * - PUBLICADO: sólo registros que pasan las reglas Bayer.
     * - Revertir un PUBLICADO sólo lo puede hacer ADMIN.
     * @return array{changed:int, rejected:int, skipped:int, errors:array<string,int>}
     */
    public function changeStatus(string $module, array $ids, string $status, int $userId, bool $isAdmin): array
    {
        $m = BayerRulesService::module($module);
        $status = in_array($status, ['BORRADOR', 'VALIDADO', 'PUBLICADO'], true) ? $status : 'BORRADOR';
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $res = ['changed' => 0, 'rejected' => 0, 'skipped' => 0, 'errors' => []];
        if (!$ids) return $res;
        if ($status === 'PUBLICADO') return $this->publish($module, $ids, $userId);

        $pdo = \db();
        $pdo->beginTransaction();
        try {
            foreach (array_chunk($ids, 1000) as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                $guard = $isAdmin ? '' : " AND estado_registro<>'PUBLICADO'";
                $st = $pdo->prepare("UPDATE {$m['table']} SET estado_registro=?, published_at=NULL WHERE id IN ($in)$guard AND estado_registro<>?");
                $st->execute(array_merge([$status], $chunk, [$status]));
                $res['changed'] += $st->rowCount();
            }
            if ($status === 'VALIDADO') $this->logValidations($module, $ids, $userId, 'VALIDADO', 'Validación manual');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $res['skipped'] = count($ids) - $res['changed'];
        return $res;
    }

    /** Publica los registros que cumplen reglas; registra rechazos en validaciones. */
    public function publish(string $module, array $ids, int $userId): array
    {
        $m = BayerRulesService::module($module);
        $ev = $this->rules->evaluate($module, $ids);
        $ok = [];
        $bad = [];
        $errors = [];
        foreach ($ev as $id => $r) {
            if ($r['ok']) $ok[] = $id;
            else { $bad[$id] = $r['errors']; foreach ($r['errors'] as $e) $errors[$e] = ($errors[$e] ?? 0) + 1; }
        }
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            $changed = [];
            foreach (array_chunk($ok, 1000) as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                $st = $pdo->prepare("SELECT id FROM {$m['table']} WHERE id IN ($in) AND estado_registro<>'PUBLICADO'");
                $st->execute($chunk);
                $toPub = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
                if (!$toPub) continue;
                $in2 = implode(',', array_fill(0, count($toPub), '?'));
                $pdo->prepare("UPDATE {$m['table']} SET estado_registro='PUBLICADO', published_at=NOW() WHERE id IN ($in2)")->execute($toPub);
                array_push($changed, ...$toPub);
            }
            if ($changed) {
                $pdo->prepare("INSERT INTO publicaciones(modulo,usuario_id,estado) VALUES(?,?,'PUBLICADO')")->execute([$module, $userId]);
                $pub = (int)$pdo->lastInsertId();
                foreach (array_chunk($changed, 500) as $chunk) {
                    $vals = implode(',', array_fill(0, count($chunk), '(?,?,?)'));
                    $p = [];
                    foreach ($chunk as $id) array_push($p, $pub, $id, $m['dataset']);
                    $pdo->prepare("INSERT INTO detalle_publicacion(publicacion_id,registro_id,dataset) VALUES $vals")->execute($p);
                }
            }
            foreach (array_chunk($bad, 500, true) as $chunk) {
                $vals = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?)'));
                $p = [];
                foreach ($chunk as $id => $errs) {
                    array_push($p, $module, $id, $userId, 'RECHAZADO', implode('; ', array_map(fn($e) => BayerRulesService::RULES[$e] ?? $e, $errs)));
                }
                $pdo->prepare("INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion) VALUES $vals")->execute($p);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        arsort($errors);
        return ['changed' => count($changed), 'rejected' => count($bad), 'skipped' => count($ok) - count($changed), 'errors' => $errors];
    }

    private function logValidations(string $module, array $ids, int $userId, string $result, string $obs): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            $vals = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?)'));
            $p = [];
            foreach ($chunk as $id) array_push($p, $module, $id, $userId, $result, $obs);
            \db()->prepare("INSERT INTO validaciones(modulo,registro_id,usuario_id,resultado,observacion) VALUES $vals")->execute($p);
        }
    }

    /** Mensaje legible para flash a partir del resultado. */
    public static function message(array $r, string $status): string
    {
        $msg = "{$r['changed']} registro(s) pasaron a $status.";
        if ($r['rejected']) {
            $top = array_slice($r['errors'], 0, 3, true);
            $why = implode(', ', array_map(fn($k, $v) => (BayerRulesService::RULES[$k] ?? $k)." ($v)", array_keys($top), $top));
            $msg .= " {$r['rejected']} rechazado(s) por reglas Bayer: $why.";
        }
        if ($r['skipped']) $msg .= " {$r['skipped']} sin cambios.";
        return $msg;
    }

    public function history(int $limit = 15): array
    {
        $st = \db()->prepare('SELECT p.id,p.modulo,p.fecha_publicacion,p.estado,u.nombre usuario,COUNT(dp.id) registros FROM publicaciones p JOIN usuarios u ON u.id=p.usuario_id LEFT JOIN detalle_publicacion dp ON dp.publicacion_id=p.id GROUP BY p.id ORDER BY p.id DESC LIMIT ?');
        $st->bindValue(1, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
