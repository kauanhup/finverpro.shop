<?php
session_start();

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se cookies de sessão estiverem sendo usados, excluí-los
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Log da ação
error_log("Admin logout realizado em: " . date('Y-m-d H:i:s'));

// Redirecionar para login
header("Location: ../loginadmin.php?msg=logout_sucesso");
exit;
?>
