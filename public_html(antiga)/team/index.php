<?php
session_start();

if (!isset($_SESSION['user_id'])) {
   header('Location: ../');
   exit();
}

require '../bank/db.php';

$pdo = getDBConnection();

// Consultas de configuração (mantém igual)
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

$stmt = $pdo->query("SELECT logo FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$logo = $result['logo'] ?? '3.png';

$stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

$defaultColors = [
 'cor_1' => '#121A1E',
 'cor_2' => 'white',
 'cor_3' => '#152731',
 'cor_4' => '#335D67',
 'cor_5' => '#152731',
];

$cores = $cores ?: $defaultColors;

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];

    // 1. Código de referência e foto de perfil
    $sqlCodigo = "SELECT codigo_referencia, foto_perfil, nome FROM usuarios WHERE id = ?";
    $stmtCodigo = $conn->prepare($sqlCodigo);
    $stmtCodigo->execute([$userId]);
    $codigoData = $stmtCodigo->fetch(PDO::FETCH_ASSOC);
    $codigoReferencia = $codigoData['codigo_referencia'] ?? '';
    $foto_perfil = $codigoData['foto_perfil'] ?? null;
    $nome_usuario = !empty($codigoData['nome']) ? $codigoData['nome'] : 'Investidor';

    // 2. SALDO DISPONÍVEL PARA TRANSFERÊNCIA (dos niveis_convite - pode zerar)
    $sqlSaldoDisponivel = "
        SELECT COALESCE((total_nivel1 + total_nivel2 + total_nivel3), 0) as saldo_disponivel
        FROM niveis_convite 
        WHERE user_id = ?
    ";
    $stmtSaldo = $conn->prepare($sqlSaldoDisponivel);
    $stmtSaldo->execute([$userId]);
    $saldoDisponivel = $stmtSaldo->fetchColumn() ?? 0;

    // 3. TOTAL DE GANHOS ACUMULADOS (da tabela comissoes - NUNCA ZERA)
    $sqlTotalGanhos = "
        SELECT COALESCE(SUM(valor_comissao), 0) as total_ganhos_acumulados
        FROM comissoes 
        WHERE user_id = ? AND status = 'processado'
    ";
    $stmtTotalGanhos = $conn->prepare($sqlTotalGanhos);
    $stmtTotalGanhos->execute([$userId]);
    $totalGanhosAcumulados = $stmtTotalGanhos->fetchColumn() ?? 0;

    // 4. GANHOS DE HOJE (da tabela comissoes - apenas hoje)
    $sqlGanhosHoje = "
        SELECT COALESCE(SUM(valor_comissao), 0) as ganhos_hoje
        FROM comissoes 
        WHERE user_id = ? 
          AND status = 'processado'
          AND DATE(data_comissao) = CURRENT_DATE()
    ";
    $stmtGanhosHoje = $conn->prepare($sqlGanhosHoje);
    $stmtGanhosHoje->execute([$userId]);
    $ganhosHoje = $stmtGanhosHoje->fetchColumn() ?? 0;

    // 5. DADOS DA EQUIPE POR NÍVEL (nunca zeram)
    $sqlNiveis = "SELECT nivel_1, nivel_2, nivel_3 FROM niveis_convite WHERE user_id = ?";
    $stmtNiveis = $conn->prepare($sqlNiveis);
    $stmtNiveis->execute([$userId]);
    $dadosNiveis = $stmtNiveis->fetch(PDO::FETCH_ASSOC);

    $nivel1 = $dadosNiveis['nivel_1'] ?? 0;
    $nivel2 = $dadosNiveis['nivel_2'] ?? 0;
    $nivel3 = $dadosNiveis['nivel_3'] ?? 0;
    $totalConvidados = $nivel1 + $nivel2 + $nivel3;

    // 6. VALORES INVESTIDOS PELA EQUIPE (da tabela investimentos - NUNCA ZERA)
    $sqlValoresEquipe = "
        SELECT 
            -- Nível 1: Indicados diretos
            COALESCE(SUM(CASE 
                WHEN u.referenciado_por = ? OR u.referenciador_id = ? THEN i.valor_investido 
                ELSE 0 
            END), 0) as valor_nivel1,
            
            -- Total geral da equipe
            COALESCE(SUM(i.valor_investido), 0) as valor_total_equipe
        FROM usuarios u
        INNER JOIN investimentos i ON u.id = i.usuario_id
        WHERE u.referenciado_por = ? 
           OR u.referenciador_id = ?
           OR u.id IN (
               SELECT u2.id FROM usuarios u1 
               INNER JOIN usuarios u2 ON u1.id = u2.referenciado_por OR u1.id = u2.referenciador_id
               WHERE u1.referenciado_por = ? OR u1.referenciador_id = ?
           )
           OR u.id IN (
               SELECT u3.id FROM usuarios u1 
               INNER JOIN usuarios u2 ON u1.id = u2.referenciado_por OR u1.id = u2.referenciador_id
               INNER JOIN usuarios u3 ON u2.id = u3.referenciado_por OR u2.id = u3.referenciador_id
               WHERE u1.referenciado_por = ? OR u1.referenciador_id = ?
           )
    ";
    $stmtValores = $conn->prepare($sqlValoresEquipe);
    $stmtValores->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $valoresEquipe = $stmtValores->fetch(PDO::FETCH_ASSOC);

    $valorNivel1 = $valoresEquipe['valor_nivel1'] ?? 0;
    $valorTotalEquipe = $valoresEquipe['valor_total_equipe'] ?? 0;

    // Para nível 2 e 3, vou calcular separadamente para simplicidade
    $valorNivel2 = 0; // Você pode implementar a lógica específica depois
    $valorNivel3 = 0; // Você pode implementar a lógica específica depois

} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Equipe</title>
   <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
   <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
   <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
   <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
   
   <!-- Favicon -->
   <link rel="apple-touch-icon" sizes="120x120" href="../assets/images/favicon/apple-touch-icon.png">
   <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon/favicon-32x32.png">
   <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon/favicon-16x16.png">
   <link rel="manifest" href="../assets/images/favicon/site.webmanifest">
   <meta name="msapplication-TileColor" content="#ffffff">
   <meta name="theme-color" content="#ffffff">
   
   <!-- Fonts & Icons -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
   <style>
       :root {
           --background-color: <?= htmlspecialchars($cores['cor_1']) ?>;
           --text-color: <?= htmlspecialchars($cores['cor_2']) ?>;
           --primary-color: <?= htmlspecialchars($cores['cor_3']) ?>;
           --secondary-color: <?= htmlspecialchars($cores['cor_4']) ?>;
           --dark-background: <?= htmlspecialchars($cores['cor_5']) ?>;
           --success-color: #10B981;
           --error-color: #EF4444;
           --warning-color: #F59E0B;
           --info-color: #3B82F6;
           --purple-color: #8B5CF6;
           --pink-color: #EC4899;
           --orange-color: #F97316;
           --border-radius: 16px;
           --border-radius-sm: 8px;
           --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
           --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
           --blur-bg: rgba(255, 255, 255, 0.08);
           --border-color: rgba(255, 255, 255, 0.15);
       }

       * {
           margin: 0;
           padding: 0;
           box-sizing: border-box;
       }

       body {
           font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
           background: linear-gradient(135deg, var(--background-color) 0%, var(--dark-background) 100%);
           min-height: 100vh;
           color: var(--text-color);
           padding: 0 0 80px 0;
       }

       body::before {
           content: '';
           position: fixed;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background: 
               radial-gradient(circle at 20% 20%, rgba(51, 93, 103, 0.15) 0%, transparent 50%),
               radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
           pointer-events: none;
           z-index: -1;
       }

       /* Header */
       .header-section {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border-bottom: 1px solid var(--border-color);
           padding: 20px;
           position: relative;
       }

       .header-section::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 4px;
           background: linear-gradient(90deg, var(--secondary-color), var(--success-color));
       }

       .header-content {
           display: flex;
           align-items: center;
           justify-content: space-between;
           max-width: 400px;
           margin: 0 auto;
           gap: 15px;
       }

       .user-info {
           display: flex;
           align-items: center;
           gap: 15px;
           flex: 1;
           min-width: 0;
       }

       .logo-img {
           width: 50px;
           height: 50px;
           border-radius: 12px;
           background: linear-gradient(135deg, var(--success-color), #059669);
           display: flex;
           align-items: center;
           justify-content: center;
           color: white;
           font-size: 20px;
           overflow: hidden;
           flex-shrink: 0;
       }

       .logo-img img {
           width: 100%;
           height: 100%;
           object-fit: cover;
           border-radius: 12px;
       }

       .user-details {
           display: flex;
           flex-direction: column;
           gap: 4px;
           flex: 1;
           min-width: 0;
       }

       .uid {
           font-size: 14px;
           color: rgba(255, 255, 255, 0.9);
           font-weight: 600;
           white-space: nowrap;
           overflow: hidden;
           text-overflow: ellipsis;
       }

       .codigo-convite {
           font-size: 12px;
           color: rgba(255, 255, 255, 0.7);
           white-space: nowrap;
           overflow: hidden;
           text-overflow: ellipsis;
       }

       .share-btn {
           background: var(--success-color);
           color: white;
           border: none;
           padding: 12px 16px;
           border-radius: 12px;
           font-size: 13px;
           font-weight: 600;
           cursor: pointer;
           transition: all 0.3s ease;
           display: flex;
           align-items: center;
           gap: 8px;
           flex-shrink: 0;
           min-width: fit-content;
       }

       .share-btn:hover {
           background: #059669;
           transform: translateY(-2px);
       }

       /* Main Content */
       .container {
           max-width: 400px;
           margin: 0 auto;
           padding: 20px;
       }

       /* Benefícios Card */
       .benefits-card {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 25px;
           margin-bottom: 25px;
           position: relative;
           overflow: hidden;
       }

       .benefits-card::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           height: 3px;
           background: linear-gradient(90deg, var(--success-color), #059669);
       }

       .benefits-header {
           display: flex;
           justify-content: space-between;
           align-items: center;
           margin-bottom: 15px;
           flex-wrap: wrap;
           gap: 10px;
       }

       .benefits-title {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
       }

       .transfer-btn {
           background: var(--success-color);
           color: white;
           border: none;
           padding: 8px 16px;
           border-radius: 20px;
           font-size: 12px;
           font-weight: 600;
           cursor: pointer;
           transition: all 0.3s ease;
       }

       .transfer-btn:hover {
           background: #059669;
           transform: scale(1.05);
       }

       .balance-amount {
           font-size: 36px;
           font-weight: 800;
           color: var(--text-color);
           margin-bottom: 20px;
       }

       .wave-divider {
           height: 80px;
           background: linear-gradient(135deg, var(--success-color), #059669);
           border-radius: 0 0 var(--border-radius) var(--border-radius);
           margin: 0 -25px -25px -25px;
           position: relative;
           display: flex;
           align-items: flex-start;
           padding-top: 25px;
       }

       .wave-divider::before {
           content: '';
           position: absolute;
           top: -20px;
           left: 0;
           right: 0;
           height: 40px;
           background: var(--success-color);
           border-radius: 50% 50% 0 0 / 100% 100% 0 0;
       }

       .today-stats {
           display: flex;
           justify-content: space-between;
           align-items: flex-start;
           color: white;
           padding: 0 25px;
           position: relative;
           z-index: 2;
           width: 100%;
       }

       .today-stat {
           text-align: center;
           flex: 1;
           display: flex;
           flex-direction: column;
           justify-content: flex-start;
           align-items: center;
       }

       .today-stat .value {
           font-size: 22px;
           font-weight: 700;
           line-height: 1.2;
           margin-bottom: 2px;
       }

       .today-stat .label {
           font-size: 11px;
           opacity: 0.9;
           line-height: 1.1;
           font-weight: 500;
       }

       /* Salary Action Button */
       .salary-action {
           margin-bottom: 25px;
           text-align: center;
       }

       .salary-btn {
           background: linear-gradient(135deg, var(--purple-color), var(--pink-color));
           color: white;
           text-decoration: none;
           border: none;
           padding: 18px 32px;
           border-radius: var(--border-radius);
           font-size: 16px;
           font-weight: 700;
           cursor: pointer;
           transition: all 0.4s ease;
           display: inline-flex;
           align-items: center;
           gap: 12px;
           position: relative;
           overflow: hidden;
           box-shadow: var(--shadow);
           min-width: 200px;
           justify-content: center;
       }

       .salary-btn::before {
           content: '';
           position: absolute;
           top: 0;
           left: -100%;
           width: 100%;
           height: 100%;
           background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
           transition: left 0.5s ease;
       }

       .salary-btn:hover::before {
           left: 100%;
       }

       .salary-btn:hover {
           transform: translateY(-4px) scale(1.02);
           box-shadow: var(--shadow-lg);
           background: linear-gradient(135deg, #9333EA, #DB2777);
       }

       .salary-btn i {
           font-size: 20px;
           padding: 8px;
           border-radius: 50%;
           background: rgba(255, 255, 255, 0.2);
           backdrop-filter: blur(10px);
       }

       .salary-btn .btn-text {
           display: flex;
           flex-direction: column;
           align-items: flex-start;
           gap: 2px;
       }

       .salary-btn .btn-title {
           font-size: 16px;
           font-weight: 700;
           line-height: 1;
       }

       .salary-btn .btn-subtitle {
           font-size: 11px;
           opacity: 0.9;
           line-height: 1;
       }

       /* Section Title */
       .section-title {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 15px;
       }

       /* Stats Grid */
       .stats-grid {
           display: grid;
           grid-template-columns: repeat(4, 1fr);
           gap: 10px;
           margin-bottom: 25px;
       }

       .stat-item {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 15px 10px;
           text-align: center;
           transition: all 0.3s ease;
       }

       .stat-item:hover {
           transform: translateY(-3px);
           box-shadow: var(--shadow);
       }

       .stat-icon {
           width: 40px;
           height: 40px;
           border-radius: 50%;
           background: var(--success-color);
           display: flex;
           align-items: center;
           justify-content: center;
           margin: 0 auto 8px;
           color: white;
           font-size: 14px;
           font-weight: 700;
       }

       .stat-value {
           font-size: 18px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 4px;
       }

       .stat-label {
           font-size: 11px;
           color: rgba(255, 255, 255, 0.7);
           line-height: 1.2;
       }

       /* Team Stats - Larger Grid */
       .team-stats {
           display: grid;
           grid-template-columns: repeat(4, 1fr);
           gap: 10px;
           margin-bottom: 25px;
       }

       .team-stat {
           background: var(--blur-bg);
           backdrop-filter: blur(20px);
           border: 1px solid var(--border-color);
           border-radius: var(--border-radius);
           padding: 20px 10px;
           text-align: center;
           transition: all 0.3s ease;
       }

       .team-stat:hover {
           transform: translateY(-3px);
           box-shadow: var(--shadow);
       }

       .team-stat .icon {
           width: 50px;
           height: 50px;
           border-radius: 50%;
           background: var(--success-color);
           display: flex;
           align-items: center;
           justify-content: center;
           margin: 0 auto 12px;
           color: white;
           font-size: 16px;
           font-weight: 700;
       }

       .team-stat .value {
           font-size: 20px;
           font-weight: 700;
           color: var(--text-color);
           margin-bottom: 6px;
       }

       .team-stat .label {
           font-size: 12px;
           color: rgba(255, 255, 255, 0.7);
           line-height: 1.2;
       }

       /* Bottom Navigation */
       .bottom-nav {
           position: fixed;
           bottom: 0;
           left: 0;
           right: 0;
           background: var(--blur-bg);
           backdrop-filter: blur(25px);
           border-top: 1px solid var(--border-color);
           padding: 15px 0;
           display: flex;
           justify-content: space-around;
           z-index: 100;
       }

       .bottom-nav a {
           color: rgba(255, 255, 255, 0.7);
           text-decoration: none;
           display: flex;
           flex-direction: column;
           align-items: center;
           gap: 5px;
           font-size: 12px;
           font-weight: 500;
           transition: all 0.3s ease;
           padding: 8px 12px;
           border-radius: var(--border-radius-sm);
       }

       .bottom-nav a.active,
       .bottom-nav a:hover {
           color: var(--success-color);
           background: rgba(16, 185, 129, 0.15);
       }

       .bottom-nav a i {
           font-size: 20px;
       }

       /* SweetAlert2 Customização */
       .swal2-popup {
           background: var(--primary-color) !important;
           color: var(--text-color) !important;
           border: 1px solid var(--border-color) !important;
           border-radius: var(--border-radius) !important;
       }

       .swal2-title {
           color: var(--text-color) !important;
           font-size: 18px !important;
           font-weight: 700 !important;
       }

       .swal2-html-container {
           color: var(--text-color) !important;
           font-size: 14px !important;
       }

       .swal2-html-container div {
           color: var(--text-color) !important;
       }

       .swal2-html-container span {
           color: inherit !important;
       }

       .swal2-html-container p {
           color: var(--text-color) !important;
       }

       .swal2-confirm {
           background: var(--success-color) !important;
           color: white !important;
           border: none !important;
           border-radius: 8px !important;
           padding: 10px 20px !important;
           font-weight: 600 !important;
       }

       .swal2-cancel {
           background: var(--error-color) !important;
           color: white !important;
           border: none !important;
           border-radius: 8px !important;
           padding: 10px 20px !important;
           font-weight: 600 !important;
       }

       .custom-alert-content {
           background: var(--dark-background) !important;
           border-radius: 8px !important;
           padding: 15px !important;
           margin: 10px 0 !important;
           border: 1px solid var(--border-color) !important;
       }

       .custom-alert-content * {
           color: var(--text-color) !important;
       }

       .success-highlight {
           color: var(--success-color) !important;
           font-weight: 700 !important;
       }

       .warning-highlight {
           color: var(--warning-color) !important;
           font-weight: 700 !important;
       }

       .info-highlight {
           color: var(--info-color) !important;
           font-weight: 700 !important;
       }

       /* Animations */
       @keyframes fadeInUp {
           from {
               opacity: 0;
               transform: translateY(30px);
           }
           to {
               opacity: 1;
               transform: translateY(0);
           }
       }

       .benefits-card,
       .stat-item,
       .team-stat,
       .salary-action {
           animation: fadeInUp 0.6s ease-out;
       }

       .stat-item:nth-child(2) { animation-delay: 0.1s; }
       .stat-item:nth-child(3) { animation-delay: 0.2s; }
       .stat-item:nth-child(4) { animation-delay: 0.3s; }

       .team-stat:nth-child(2) { animation-delay: 0.1s; }
       .team-stat:nth-child(3) { animation-delay: 0.2s; }
       .team-stat:nth-child(4) { animation-delay: 0.3s; }

       /* Responsive */
       @media (max-width: 480px) {
           .container {
               padding: 15px;
           }

           .benefits-card {
               padding: 20px;
               margin-bottom: 20px;
           }

           .balance-amount {
               font-size: 32px;
           }

           .stats-grid,
           .team-stats {
               gap: 8px;
           }

           .stat-item,
           .team-stat {
               padding: 12px 8px;
           }

           .header-content {
               flex-wrap: nowrap;
               gap: 10px;
           }

           .user-details {
               min-width: 0;
           }

           .uid {
               font-size: 13px;
           }

           .codigo-convite {
               font-size: 11px;
           }

           .share-btn {
               padding: 10px 12px;
               font-size: 12px;
           }

           .share-btn i {
               font-size: 14px;
           }

           .salary-btn {
               padding: 16px 24px;
               min-width: 180px;
           }

           .salary-btn .btn-title {
               font-size: 15px;
           }
       }

       @media (max-width: 360px) {
           .header-content {
               gap: 8px;
           }
           
           .share-btn span {
               display: none;
           }
           
           .share-btn {
               padding: 10px;
               min-width: 40px;
           }

           .salary-btn {
               padding: 14px 20px;
               min-width: 160px;
           }
       }
   </style>
</head>

<body>
   <!-- Header -->
   <div class="header-section"><div class="header-content">
           <div class="user-info">
               <div class="logo-img">
                   <?php if (!empty($foto_perfil) && file_exists("../uploads/perfil/" . $foto_perfil)): ?>
                       <img src="../uploads/perfil/<?= htmlspecialchars($foto_perfil) ?>" alt="Foto de Perfil">
                   <?php else: ?>
                       <i class="fas fa-user-circle"></i>
                   <?php endif; ?>
               </div>
               <div class="user-details">
                   <div class="uid">UID: <?php echo $userId; ?> - <?php echo htmlspecialchars($nome_usuario); ?></div>
                   <div class="codigo-convite">Código de Convite: <?php echo htmlspecialchars($codigoReferencia); ?></div>
               </div>
           </div>
           <button class="share-btn" onclick="shareLink()">
               <i class="fas fa-share"></i>
               <span>Promover</span>
           </button>
       </div>
   </div>

   <div class="container">
       <!-- Benefícios da Equipe -->
       <div class="benefits-card">
           <div class="benefits-header">
               <h2 class="benefits-title">Benefícios da Equipe</h2>
               <button class="transfer-btn" onclick="transferToWallet()">Transfer out</button>
           </div>
           <!-- MOSTRA O TOTAL ACUMULADO (nunca zera) -->
           <div class="balance-amount">R$ <?php echo number_format($totalGanhosAcumulados, 2, ',', '.'); ?></div>
           
           <div class="wave-divider">
               <div class="today-stats">
                   <div class="today-stat">
                       <!-- GANHOS DE HOJE (apenas hoje) -->
                       <div class="value"><?php echo number_format($ganhosHoje, 2, ',', '.'); ?></div>
                       <div class="label">Ganhos de Hoje</div>
                   </div>
                   <div class="today-stat">
                       <!-- TOTAL DE PESSOAS DA EQUIPE (nunca zera) -->
                       <div class="value"><?php echo $totalConvidados; ?></div>
                       <div class="label">Total da Equipe</div>
                   </div>
               </div>
           </div>
       </div>

       <!-- Botão de Salários -->
       <div class="salary-action">
           <a href="./salary/" class="salary-btn">
               <i class="fas fa-money-bill-wave"></i>
               <div class="btn-text">
                   <div class="btn-title">Meus Salários</div>
                   <div class="btn-subtitle">Gerencie seus ganhos MLM</div>
               </div>
           </a>
       </div>

       <!-- Número de Registros (PESSOAS da equipe por nível) -->
       <h3 class="section-title">Número de Registros</h3>
       <div class="stats-grid">
           <div class="stat-item">
               <div class="stat-icon">👥</div>
               <div class="stat-value"><?php echo $totalConvidados; ?></div>
               <div class="stat-label">(Total)</div>
           </div>
           <div class="stat-item">
               <div class="stat-icon">A</div>
               <div class="stat-value"><?php echo $nivel1; ?></div>
               <div class="stat-label">(Nível 1)</div>
           </div>
           <div class="stat-item">
               <div class="stat-icon">B</div>
               <div class="stat-value"><?php echo $nivel2; ?></div>
               <div class="stat-label">(Nível 2)</div>
           </div>
           <div class="stat-item">
               <div class="stat-icon">C</div>
               <div class="stat-value"><?php echo $nivel3; ?></div>
               <div class="stat-label">(Nível 3)</div>
           </div>
       </div>

       <!-- Tamanho da Equipe (VALORES INVESTIDOS pela equipe) -->
       <h3 class="section-title">Tamanho da Equipe (Investimentos)</h3>
       <div class="team-stats">
           <div class="team-stat">
               <div class="icon">R$</div>
               <div class="value"><?php echo number_format($valorTotalEquipe, 0, ',', '.'); ?></div>
               <div class="label">Valor Total</div>
           </div>
           <div class="team-stat">
               <div class="icon">A</div>
               <div class="value"><?php echo number_format($valorNivel1, 0, ',', '.'); ?></div>
               <div class="label">(Nível 1)</div>
           </div>
           <div class="team-stat">
               <div class="icon">B</div>
               <div class="value"><?php echo number_format($valorNivel2, 0, ',', '.'); ?></div>
               <div class="label">(Nível 2)</div>
           </div>
           <div class="team-stat">
               <div class="icon">C</div>
               <div class="value"><?php echo number_format($valorNivel3, 0, ',', '.'); ?></div>
               <div class="label">(Nível 3)</div>
           </div>
       </div>
   </div>

   <!-- Bottom Navigation -->
   <nav class="bottom-nav">
       <a href="../inicio/">
           <i class="fas fa-home"></i>
           Início
       </a>
       <a href="../investimentos/">
           <i class="fas fa-wallet"></i>
           Investimentos
       </a>
       <a href="./" class="active">
           <i class="fas fa-users"></i>
           Equipe
       </a>
       <a href="../perfil/">
           <i class="fas fa-user"></i>
           Perfil
       </a>
   </nav>

   <script>
       function shareLink() {
           const codigoReferencia = "<?php echo htmlspecialchars($codigoReferencia); ?>";
           const inviteLink = `<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>/cadastro/?ref=${codigoReferencia}`;
           
           navigator.clipboard.writeText(inviteLink).then(() => {
               Swal.fire({
                   icon: 'success',
                   title: '🎉 Link de Convite Copiado!',
                   html: `
                       <div class="custom-alert-content">
                           <p style="font-size: 16px; margin-bottom: 15px;">Seu link de afiliado foi copiado com sucesso!</p>
                           <div style="background: var(--dark-background); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 12px; word-break: break-all; border: 1px solid var(--border-color);">
                               ${inviteLink}
                           </div>
                           <p style="margin-top: 15px; font-size: 14px;">
                               💰 <span class="success-highlight">Compartilhe este link e ganhe comissões de cada pessoa que se cadastrar!</span>
                           </p>
                       </div>
                   `,
                   showConfirmButton: true,
                   confirmButtonText: '✨ Entendi',
                   confirmButtonColor: '#10B981',
                   width: '90%',
                   background: 'var(--primary-color)',
                   color: 'var(--text-color)'
               });
           }).catch(err => {
               console.error('Erro ao copiar o link: ', err);
               Swal.fire({
                   icon: 'error',
                   title: '❌ Ops! Algo deu errado',
                   html: `
                       <div class="custom-alert-content">
                           <p style="font-size: 16px; margin-bottom: 15px;">Não conseguimos copiar o link automaticamente.</p>
                           <div style="background: var(--dark-background); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 12px; word-break: break-all; margin-bottom: 15px; border: 1px solid var(--border-color);">
                               ${inviteLink}
                           </div>
                           <p style="font-size: 14px;">
                               📋 <span class="warning-highlight">Copie o link acima manualmente e compartilhe com seus amigos!</span>
                           </p>
                       </div>
                   `,
                   showConfirmButton: true,
                   confirmButtonText: '📋 Copiar Manualmente',
                   confirmButtonColor: '#F59E0B',
                   width: '90%',
                   background: 'var(--primary-color)',
                   color: 'var(--text-color)'
               });
           });
       }

       function transferToWallet() {
           // MOSTRA O SALDO DISPONÍVEL (que pode zerar)
           const saldoDisponivel = <?php echo $saldoDisponivel; ?>;
           
           if (saldoDisponivel <= 0) {
               Swal.fire({
                   icon: 'warning',
                   title: '⚠️ Saldo Insuficiente!',
                   html: `
                       <div class="custom-alert-content">
                           <p>Você não possui saldo de comissões disponível para transferir.</p>
                           <p style="margin-top: 10px;"><span class="info-highlight">Aguarde novas comissões ou convide mais pessoas!</span></p>
                       </div>
                   `,
                   showConfirmButton: true,
                   confirmButtonText: 'OK',
                   background: 'var(--primary-color)',
                   color: 'var(--text-color)'
               });
               return;
           }

           Swal.fire({
               title: 'Confirmar Transferência',
               html: `
                   <div class="custom-alert-content">
                       <p>Deseja transferir <span class="success-highlight">R$ ${saldoDisponivel.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span> de comissões disponíveis para sua carteira?</p>
                       <p style="margin-top: 10px; font-size: 12px; opacity: 0.8;">
                           💡 Seus ganhos totais continuarão sendo exibidos normalmente.
                       </p>
                   </div>
               `,
               icon: 'question',
               showCancelButton: true,
               confirmButtonText: 'Sim, transferir',
               cancelButtonText: 'Cancelar',
               confirmButtonColor: '#10B981',
               cancelButtonColor: '#6B7280',
               background: 'var(--primary-color)',
               color: 'var(--text-color)'
           }).then((result) => {
               if (result.isConfirmed) {
                   // Chamar o arquivo de transferência
                   fetch('processar_transferencia.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/json',
                       }
                   })
                   .then(response => response.json())
                   .then(data => {
                       if (data.success) {
                           Swal.fire({
                               icon: 'success',
                               title: '🎉 Transferência Realizada!',
                               html: `
                                   <div class="custom-alert-content">
                                       <div style="background: #10B981; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white;">
                                           ✅
                                       </div>
                                       <p style="font-size: 16px; margin-bottom: 15px;">
                                           <strong>R$ ${data.dados.valor_transferido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong> foram transferidos para sua carteira!
                                       </p>
                                       <p style="font-size: 14px;">
                                           💡 <span class="info-highlight">Novo saldo na carteira: R$ ${data.dados.novo_saldo_principal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                                       </p>
                                   </div>
                               `,
                               showConfirmButton: true,
                               confirmButtonText: '🚀 Atualizar Página',
                               confirmButtonColor: '#10B981',
                               width: '90%',
                               background: 'var(--primary-color)',
                               color: 'var(--text-color)'
                           }).then(() => {
                               window.location.reload();
                           });
                       } else {
                           Swal.fire({
                               icon: 'error',
                               title: '❌ Erro na Transferência',
                               text: data.message,
                               confirmButtonColor: '#EF4444',
                               background: 'var(--primary-color)',
                               color: 'var(--text-color)'
                           });
                       }
                   })
                   .catch(error => {
                       console.error('Erro:', error);
                       Swal.fire({
                           icon: 'error',
                           title: '❌ Erro de Conexão',
                           text: 'Erro ao processar transferência. Tente novamente.',
                           confirmButtonColor: '#EF4444',
                           background: 'var(--primary-color)',
                           color: 'var(--text-color)'
                       });
                   });
               }
           });
       }
   </script>

   <!-- Pop-up e anúncios -->
   <?php if (!empty($popUp)): ?>
   <script>
       setTimeout(function() {
           Swal.fire({
               html: `<?= addslashes($popUp) ?>`,
               showConfirmButton: true,
               confirmButtonText: 'Fechar',
               background: 'var(--primary-color)',
               color: 'var(--text-color)'
           });
       }, 3000);
   </script>
   <?php endif; ?>

   <?php if (!empty($anuncio)): ?>
   <div style="position: fixed; bottom: 90px; left: 20px; right: 20px; background: var(--blur-bg); backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 15px; z-index: 99; text-align: center;">
       <?= $anuncio ?>
       <button onclick="this.parentElement.style.display='none'" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--text-color); font-size: 18px; cursor: pointer;">&times;</button>
   </div>
   <?php endif; ?>

</body>
</html>