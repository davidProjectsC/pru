<?php
declare(strict_types=1);

/**
 * Auth helpers (PHP 7.4). Uses $_SESSION.
 * Note: For immediate compatibility we verify using the same legacy scheme the ASP used:
 *   md5("KnTZyc0MBadRkAA" . strtoupper($pass) . "0skkrlFuO/i")
 * But you should migrate your Usuario.Contrasena to password_hash() a la brevedad.
 */

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Secure session cookie defaults (adjust in php.ini if desired)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_login(): void {
    start_secure_session();
    if (empty($_SESSION['usuario'])) {
        header('Location: /portales/pru/public/login.php?msg=' . urlencode('Inicia sesión')); exit;
    }
}

function legacy_hash(string $pass): string {
    // Emula el cálculo cliente para compatibilidad temporal
    $p = strtolower(md5(strtoupper($pass)));
    $p = "KnTZyc0MBadRkAA".$p."0skkrlFuO/i";
    return (md5($p));
}

function login(PDO $pdo, string $usr, string $pass) {
    start_secure_session();
    $usrU = strtoupper(trim($usr));
    if ($usrU === '' || $pass === '') return false;

    $stmt = $pdo->prepare("SELECT Usuario, Contrasena FROM Usuario WHERE UPPER(Usuario) = ?");
    $stmt->execute([$usrU]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $stored = ((string)$row['Contrasena']);

    // 1) Soporte temporal del hash legado
    if ($stored === legacy_hash($pass)) {
        $_SESSION['usuario'] = $usrU;
        return true;
    }

    // 2) Si ya migraste a password_hash(), descomenta:
    // if (password_verify($pass, $stored)) {
    //     $_SESSION['usuario'] = $usrU;
    //     return true;
    // }
    return false;
    return $stored. '|'. legacy_hash($pass);
    //return false;
}

function logout_and_redirect(): void {
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /portales/pru/public/login.php?msg=' . urlencode('Sesión cerrada'));
    exit;
}
