<?php
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/crud_base.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare('SELECT u.id, u.nombre, u.email, u.password_hash, LOWER(r.nombre) AS rol
        FROM usuarios u
        JOIN usuario_rol ur ON ur.usuario_id = u.id
        JOIN roles r ON r.id = ur.rol_id
        WHERE u.email = ? AND u.estado = 1 LIMIT 1');
    $st->execute([trim($_POST['email'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['clave'] ?? '', $u['password_hash'])) {
        if ($u['rol'] === 'bayer') { $err = 'Bayer solo consulta datos publicados.'; }
        else {
            session_regenerate_id(true);
            $_SESSION['usuario'] = ['id' => $u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol']];
            // Contexto que la plataforma (G4) entrega a los módulos: usuario y sede activa
            // (primera sede asignada; «Todas» si el usuario no tiene sedes asignadas)
            unset($_SESSION['sede_activa']);
            sede_activa();
            auditar('plataforma', 'inicio_sesion', (int)$u['id']);
            header('Location: ' . BASE_URL . '/index.php'); exit;
        }
    } else {
        $err = 'Correo o contraseña incorrectos.';
        // Intento fallido: se registra sin usuario de sesión, directamente (no guarda la contraseña)
        if ($u) { try { $pdo->prepare("INSERT INTO auditoria_acciones (usuario_id, modulo, accion, fecha_hora, ip, resultado) VALUES (?, 'plataforma', 'inicio_sesion_fallido', NOW(), ?, 'rechazado')")->execute([$u['id'], mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]); } catch (Throwable $e) {} }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingreso al Sistema · Bayer-Pucchún ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
  body.login-page {
    background: radial-gradient(circle at 80% 20%, rgba(46, 139, 61, 0.35) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(56, 182, 234, 0.25) 0%, transparent 50%),
                linear-gradient(135deg, #0B2535 0%, #10384F 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .login-card {
    background: #ffffff;
    border-radius: var(--radius-xl);
    box-shadow: 0 25px 50px -12px rgba(11, 37, 53, 0.5);
    width: min(440px, 100%);
    padding: 40px 36px;
    border-top: 5px solid var(--bayer-green);
    position: relative;
  }
  .login-header {
    text-align: center;
    margin-bottom: 28px;
  }
  .login-logo {
    width: 68px;
    height: 68px;
    background: #ffffff;
    border-radius: 18px;
    padding: 6px;
    box-shadow: 0 4px 16px rgba(16, 56, 79, 0.12);
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-subtle);
  }
  .login-logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }
  .login-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--bayer-navy);
    margin: 0 0 4px;
    letter-spacing: -0.5px;
  }
  .login-desc {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
  }
  .input-label {
    display: block;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 700;
    color: var(--bayer-navy);
  }
  .input-label input {
    margin-top: 6px;
    padding: 11px 14px;
    font-size: 14px;
  }
  .login-btn {
    width: 100%;
    justify-content: center;
    padding: 12px;
    font-size: 14px;
    margin-top: 8px;
  }
</style>
</head>
<body class="login-page">

<div class="login-card">
  <div class="login-header">
    <div class="login-logo">
      <img src="<?= BASE_URL ?>/assets/img/bayer.png" alt="Bayer" onerror="this.style.display='none'">
    </div>
    <h2 class="login-title">Bayer · Pucchún</h2>
    <p class="login-desc">Plataforma de Gestión y Homologación de Maestros</p>
  </div>

  <?php if ($err): ?>
    <div class="alerta err" style="margin-bottom: 20px; font-size: 13px;">
      <?= h($err) ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <label class="input-label">
      Correo electrónico
      <input type="email" name="email" placeholder="usuario@bayer.com" required autofocus autocomplete="username">
    </label>

    <label class="input-label">
      Contraseña
      <input type="password" name="clave" placeholder="••••••••" required autocomplete="current-password">
    </label>

    <button type="submit" class="btn login-btn">
      Iniciar Sesión
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
  </form>
</div>

</body>
</html>
