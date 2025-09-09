<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Auth.php';

header('Content-Type: text/html; charset=utf-8');
start_secure_session();

$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usr  = isset($_POST['usuario']) ? (string)$_POST['usuario'] : '';
    $pass = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
    //echo login($pdo, $usr, $pass);exit;
    if ($usr === '' || $pass === '') {
        $msg = 'Usuario y contraseña son requeridos.';
    } else if (login($pdo, $usr, $pass)) {
        // “Recordar usuario” (para autocompletar). No es la cookie de sesión.
        setcookie('usuario', strtoupper($usr), [
            'expires'  => time() + 86400*365*10,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        header('Location: /public/pru.php'); exit;
    } else {
        $msg = 'Usuario o contraseña incorrectos.';
    }
}

$usuarioCookie = isset($_COOKIE['usuario']) ? strtoupper((string)$_COOKIE['usuario']) : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Grupo Roche - VENTAS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: Verdana, Arial, sans-serif; padding: 24px; }
  .card { max-width: 420px; margin: 0 auto; border: 1px solid #ddd; padding: 18px; border-radius: 8px; }
  label { display:block; margin: 8px 0 4px; font-size: 12px; }
  input[type=text], input[type=password] { width: 100%; padding: 8px; }
  .err { color:#b00; margin-bottom:8px; font-size:12px; }
  .btn { margin-top: 12px; padding: 8px 12px; }
</style>
</head>
<body>
<div class="card">
  <h3>Acceso • Ventas</h3>
  <?php if ($msg): ?><div class="err"><b><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></b></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>Usuario</label>
    <input name="usuario" type="text" value="<?= htmlspecialchars($usuarioCookie, ENT_QUOTES, 'UTF-8') ?>">
    <label>Contraseña</label>
    <input name="pass" type="password">
    <button class="btn" type="submit">Entrar</button>
  </form>
</div>
</body>
</html>
