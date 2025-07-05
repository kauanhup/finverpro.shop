<?php
/**
 * ========================================
 * FINVER PRO - LOGOUT ADMINISTRATIVO
 * Encerramento de Sessão Seguro
 * ========================================
 */

require_once 'includes/auth.php';

// Registrar logout no log de auditoria
logAdminAction('admin.logout', 'Logout administrativo realizado');

// Executar logout
adminLogout();
?>