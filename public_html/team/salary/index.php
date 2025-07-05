<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../');
    exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../../bank/db.php';

// Criar a conexão
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Obtém o id do usuário logado
$user_id = $_SESSION['user_id'];

// =============================================================================
// FUNÇÃO 1 - Cálculo de pessoas ATIVAS (com investimentos ativos)
// =============================================================================

function getActiveTeamStats($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN nivel = 1 THEN user_id END) as nivel1_pessoas,
            COUNT(DISTINCT CASE WHEN nivel = 2 THEN user_id END) as nivel2_pessoas,
            COUNT(DISTINCT CASE WHEN nivel = 3 THEN user_id END) as nivel3_pessoas,
            COALESCE(SUM(CASE WHEN nivel = 1 THEN valor_investido END), 0) as nivel1_valor_ativo,
            COALESCE(SUM(CASE WHEN nivel = 2 THEN valor_investido END), 0) as nivel2_valor_ativo,
            COALESCE(SUM(CASE WHEN nivel = 3 THEN valor_investido END), 0) as nivel3_valor_ativo
        FROM (
            -- Nível 1 - Apenas investimentos ATIVOS
            SELECT 1 as nivel, u.id as user_id, i.valor_investido
            FROM usuarios u 
            INNER JOIN investimentos i ON u.id = i.usuario_id 
            WHERE u.referenciado_por = ? AND i.status = 'ativo'
            
            UNION ALL
            
            -- Nível 2 - Apenas investimentos ATIVOS
            SELECT 2 as nivel, u2.id as user_id, i.valor_investido
            FROM usuarios u1
            INNER JOIN usuarios u2 ON u2.referenciado_por = u1.id
            INNER JOIN investimentos i ON u2.id = i.usuario_id 
            WHERE u1.referenciado_por = ? AND i.status = 'ativo'
            
            UNION ALL
            
            -- Nível 3 - Apenas investimentos ATIVOS
            SELECT 3 as nivel, u3.id as user_id, i.valor_investido  
            FROM usuarios u1
            INNER JOIN usuarios u2 ON u2.referenciado_por = u1.id
            INNER JOIN usuarios u3 ON u3.referenciado_por = u2.id
            INNER JOIN investimentos i ON u3.id = i.usuario_id 
            WHERE u1.referenciado_por = ? AND i.status = 'ativo'
        ) team_data
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return [
            'nivel1_pessoas' => 0,
            'nivel1_valor_ativo' => 0.0,
            'nivel2_pessoas' => 0, 
            'nivel2_valor_ativo' => 0.0,
            'nivel3_pessoas' => 0,
            'nivel3_valor_ativo' => 0.0,
            'total_pessoas_ativas' => 0,
            'total_valor_ativo' => 0.0
        ];
    }
    
    return [
        // Dados por nível
        'nivel1_pessoas' => (int)($result['nivel1_pessoas'] ?? 0),
        'nivel1_valor_ativo' => (float)($result['nivel1_valor_ativo'] ?? 0),
        'nivel2_pessoas' => (int)($result['nivel2_pessoas'] ?? 0), 
        'nivel2_valor_ativo' => (float)($result['nivel2_valor_ativo'] ?? 0),
        'nivel3_pessoas' => (int)($result['nivel3_pessoas'] ?? 0),
        'nivel3_valor_ativo' => (float)($result['nivel3_valor_ativo'] ?? 0),
        
        // Totais
        'total_pessoas_ativas' => (int)(($result['nivel1_pessoas'] ?? 0) + ($result['nivel2_pessoas'] ?? 0) + ($result['nivel3_pessoas'] ?? 0)),
        'total_valor_ativo' => (float)(($result['nivel1_valor_ativo'] ?? 0) + ($result['nivel2_valor_ativo'] ?? 0) + ($result['nivel3_valor_ativo'] ?? 0))
    ];
}

// =============================================================================
// FUNÇÃO 2 - Cálculo do valor TOTAL da equipe (ativo + inativo) - CORRIGIDA
// =============================================================================

function getTotalTeamValue($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN nivel = 1 THEN valor_investido END), 0) as valor_total_nivel1,
            COALESCE(SUM(CASE WHEN nivel = 2 THEN valor_investido END), 0) as valor_total_nivel2,
            COALESCE(SUM(CASE WHEN nivel = 3 THEN valor_investido END), 0) as valor_total_nivel3
        FROM (
            -- Nível 1 - TODOS os investimentos (ativo + inativo + cancelado + concluido)
            SELECT 1 as nivel, i.valor_investido
            FROM usuarios u 
            INNER JOIN investimentos i ON u.id = i.usuario_id 
            WHERE u.referenciado_por = ?
            
            UNION ALL
            
            -- Nível 2 - TODOS os investimentos
            SELECT 2 as nivel, i.valor_investido
            FROM usuarios u1
            INNER JOIN usuarios u2 ON u2.referenciado_por = u1.id
            INNER JOIN investimentos i ON u2.id = i.usuario_id 
            WHERE u1.referenciado_por = ?
            
            UNION ALL
            
            -- Nível 3 - TODOS os investimentos
            SELECT 3 as nivel, i.valor_investido
            FROM usuarios u1
            INNER JOIN usuarios u2 ON u2.referenciado_por = u1.id
            INNER JOIN usuarios u3 ON u3.referenciado_por = u2.id
            INNER JOIN investimentos i ON u3.id = i.usuario_id 
            WHERE u1.referenciado_por = ?
        ) team_data
    ");
    
    $stmt->execute([$user_id, $user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return 0.0;
    }
    
    $total = (float)(($result['valor_total_nivel1'] ?? 0) + ($result['valor_total_nivel2'] ?? 0) + ($result['valor_total_nivel3'] ?? 0));
    
    return $total;
}

// Processar transferência de salário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_salary') {
    header('Content-Type: application/json');
    
    try {
        // Buscar salários disponíveis do usuário
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total_disponivel 
            FROM salary_payments 
            WHERE user_id = ? AND status = 'disponivel'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_disponivel = $result['total_disponivel'] ?? 0;
        
        if ($total_disponivel <= 0) {
            echo json_encode(['success' => false, 'message' => 'Você não possui salários disponíveis para transferir.']);
            exit();
        }
        
        $conn->beginTransaction();
        
        // Atualizar status dos salários para transferido
        $stmt = $conn->prepare("
            UPDATE salary_payments 
            SET status = 'transferido', transfer_date = NOW() 
            WHERE user_id = ? AND status = 'disponivel'
        ");
        $stmt->execute([$user_id]);
        
        // Adicionar valor ao saldo do usuário
        $stmt = $conn->prepare("
            UPDATE usuarios 
            SET saldo = saldo + ? 
            WHERE id = ?
        ");
        $stmt->execute([$total_disponivel, $user_id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Salários transferidos para sua carteira com sucesso!',
            'amount' => $total_disponivel
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

// Processar solicitação de salário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_salary') {
    header('Content-Type: application/json');
    
    try {
        $level_id = $_POST['level_id'];
        $user_message = $_POST['user_message'] ?? '';
        
        // Verificar se já existe solicitação pendente
        $stmt = $conn->prepare("SELECT id FROM salary_requests WHERE user_id = ? AND status = 'pendente'");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Você já possui uma solicitação pendente. Aguarde a resposta do administrador.']);
            exit();
        }
        
        // Verificar cooldown de 30 dias para o mesmo nível
        $stmt = $conn->prepare("
            SELECT request_date FROM salary_requests 
            WHERE user_id = ? AND level_id = ? 
            ORDER BY request_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $level_id]);
        $last_request = $stmt->fetch();

        if ($last_request) {
            $last_date = new DateTime($last_request['request_date']);
            $now = new DateTime();
            $days_diff = $now->diff($last_date)->days;
            
            if ($days_diff < 30) {
                $remaining_days = 30 - $days_diff;
                $next_date = clone $last_date;
                $next_date->add(new DateInterval('P30D'));
                $next_date_formatted = $next_date->format('d/m/Y');
                
                echo json_encode([
                    'success' => false, 
                    'message' => "Você pode solicitar este nível novamente em {$remaining_days} dias (a partir de {$next_date_formatted})."
                ]);
                exit();
            }
        }
        
        // Buscar dados do nível
        $stmt = $conn->prepare("SELECT * FROM salary_levels WHERE id = ? AND is_active = 1");
        $stmt->execute([$level_id]);
        $level = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$level) {
            echo json_encode(['success' => false, 'message' => 'Nível não encontrado ou inativo.']);
            exit();
        }
        
        // ✅ NOVA VALIDAÇÃO CORRETA
        $active_team_stats = getActiveTeamStats($user_id, $conn);
        $total_team_value = getTotalTeamValue($user_id, $conn);
        
        $current_active_people = $active_team_stats['total_pessoas_ativas'];
        
        // Verificar se atende os critérios com mensagens específicas
        if ($current_active_people < $level['min_people']) {
            $faltam_pessoas = $level['min_people'] - $current_active_people;
            echo json_encode([
                'success' => false, 
                'message' => "Você precisa de {$level['min_people']} pessoas ATIVAS com investimentos. Atualmente você tem {$current_active_people}. Faltam {$faltam_pessoas} pessoas ativas."
            ]);
            exit();
        }

        if ($total_team_value < $level['min_team_value']) {
            $falta_valor = $level['min_team_value'] - $total_team_value;
            echo json_encode([
                'success' => false, 
                'message' => "Você precisa de R$ " . number_format($level['min_team_value'], 2, ',', '.') . " em valor TOTAL da equipe. Atualmente você tem R$ " . number_format($total_team_value, 2, ',', '.') . ". Faltam R$ " . number_format($falta_valor, 2, ',', '.')
            ]);
            exit();
        }
        
        // Criar solicitação com dados corretos
        $stmt = $conn->prepare("
            INSERT INTO salary_requests (user_id, level_id, level_code, requested_amount, current_team_people, current_team_value, user_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $level['id'],
            $level['level_code'],
            $level['monthly_salary'],
            $current_active_people,    // ✅ Pessoas ativas
            $total_team_value,         // ✅ Valor total da equipe
            $user_message
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Solicitação enviada com sucesso! Aguarde a análise do administrador.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

// Buscar dados do usuário
$stmt = $conn->prepare("SELECT nome, email, saldo, saldo_comissao FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ CALCULAR ESTATÍSTICAS DA EQUIPE - CORRIGIDO
$active_team_stats = getActiveTeamStats($user_id, $conn);
$total_team_value = getTotalTeamValue($user_id, $conn);

// Buscar níveis disponíveis
$stmt = $conn->query("SELECT * FROM salary_levels WHERE is_active = 1 ORDER BY sort_order, min_people");
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar solicitações do usuário
$stmt = $conn->prepare("
    SELECT sr.*, sl.level_name, sl.icon, sl.color
    FROM salary_requests sr
    JOIN salary_levels sl ON sr.level_id = sl.id
    WHERE sr.user_id = ?
    ORDER BY sr.request_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar pagamentos do usuário
$stmt = $conn->prepare("
    SELECT sp.*, sl.level_name, sl.icon, sl.color
    FROM salary_payments sp
    JOIN salary_levels sl ON sp.level_id = sl.id
    WHERE sp.user_id = ?
    ORDER BY sp.release_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total disponível para transferência
$stmt = $conn->prepare("
    SELECT SUM(amount) as total_disponivel 
    FROM salary_payments 
    WHERE user_id = ? AND status = 'disponivel'
");
$stmt->execute([$user_id]);
$salary_balance = $stmt->fetch(PDO::FETCH_ASSOC);
$total_salary_available = $salary_balance['total_disponivel'] ?? 0;

// Verificar se tem solicitação pendente
$pending_request = null;
foreach ($user_requests as $request) {
    if ($request['status'] === 'pendente') {
        $pending_request = $request;
        break;
    }
}

// Buscar configurações
$stmt = $conn->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT logo FROM personalizar_imagens LIMIT 1");
$images = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

// Valores padrão
$titulo_site = $config['titulo_site'] ?? 'MLM System';
$logo = $images['logo'] ?? '3.png';
$defaultColors = [
    'cor_1' => '#121A1E', 'cor_2' => 'white', 'cor_3' => '#152731',
    'cor_4' => '#335D67', 'cor_5' => '#152731'
];
$cores = $cores ?: $defaultColors;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site) ?> - Meus Salários</title>
    
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

        .back-btn {
            background: var(--primary-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
        }

        .back-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .header-title {
            flex: 1;
            text-align: center;
        }

        .header-title h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
        }

        .header-title p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 2px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--success-color), #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            overflow: hidden;
        }

        /* Container */
        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Team Stats Card - LAYOUT SIMPLIFICADO */
        .team-stats-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .team-stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--purple-color), var(--pink-color));
        }

        .stats-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--purple-color), var(--pink-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stats-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 4px;
        }

        .stats-info p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Stats Grid - SIMPLIFICADO (só 2 cards) */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 20px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
        }

        /* Salary Balance Card */
        .salary-balance-card {
            background: linear-gradient(135deg, var(--success-color), #059669);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .salary-balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .balance-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .balance-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .balance-info h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .balance-info p {
            font-size: 12px;
            opacity: 0.9;
        }

        .transfer-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .transfer-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .balance-amount {
            font-size: 32px;
            font-weight: 800;
            position: relative;
            z-index: 2;
        }

        /* Section Title */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Level Cards */
        .levels-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }

        .level-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            position: relative;
            transition: all 0.3s ease;
        }

        .level-card.eligible {
            border-color: var(--success-color);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .level-card.qualified {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border-color: var(--success-color);
        }

        .level-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .level-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .level-info {
            flex: 1;
        }

        .level-name {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .level-icon {
            font-size: 20px;
        }

        .level-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-color);
        }

        .level-code {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 8px;
        }

        .level-description {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.4;
        }

        .level-salary {
            text-align: right;
            background: var(--success-color);
            color: white;
            padding: 8px 12px;
            border-radius: var(--border-radius-sm);
            font-weight: 700;
            font-size: 14px;
        }

        .level-requirements {
            margin-bottom: 15px;
        }

        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .requirement.met {
            color: var(--success-color);
        }

        .requirement.not-met {
            color: var(--warning-color);
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 12px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), #059669);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .level-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }

        .btn-primary {
            background: var(--success-color);
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
        }

        .btn-pending {
            background: var(--warning-color);
            color: white;
        }

        .btn-cooldown {
            background: var(--info-color);
            color: white;
            cursor: not-allowed;
        }

        /* Pending Request Alert */
        .pending-alert {
            background: linear-gradient(135deg, var(--warning-color), #D97706);
            color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pending-alert i {
            font-size: 20px;
        }

        .pending-alert-content h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .pending-alert-content p {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 4px;
            margin-bottom: 20px;
        }

        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: var(--border-radius-sm);
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: var(--success-color);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* History Items */
        .history-item {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 12px;
            position: relative;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .history-level {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-level .icon {
            font-size: 16px;
        }

        .history-level .name {
            font-weight: 600;
            color: var(--text-color);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pendente {
            background: var(--warning-color);
            color: white;
        }

        .status-aprovado {
            background: var(--success-color);
            color: white;
        }

        .status-rejeitado {
            background: var(--error-color);
            color: white;
        }

        .status-disponivel {
            background: var(--info-color);
            color: white;
        }

        .status-transferido {
            background: var(--purple-color);
            color: white;
        }

        .history-details {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
        }

        .history-details .amount {
            font-weight: 700;
            color: var(--success-color);
        }

        .history-details .date {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.7);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            width: 100%;
            max-width: 400px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
        }

        .close-btn {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: var(--dark-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-color);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            .team-stats-card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-item {
                padding: 15px 10px;
            }

            .stat-value {
                font-size: 20px;
            }

            .levels-grid {
                gap: 12px;
            }

            .level-card {
                padding: 15px;
            }

            .modal-content {
                margin: 10px;
                max-height: 90vh;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header-section">
        <div class="header-content">
            <button class="back-btn" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="header-title">
                <h1>💰 Meus Salários</h1>
                <p>Sistema MLM de Recompensas</p>
            </div>
            <div class="user-avatar">
                <?= strtoupper(substr($user_data['nome'] ?? 'U', 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Team Stats Card - DADOS CORRIGIDOS -->
        <div class="team-stats-card">
            <div class="stats-header">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-info">
                    <h3>Status da sua Equipe</h3>
                    <p>Estatísticas da rede MLM</p>
                </div>
            </div>
            
            <div class="stats-grid">
                <!-- ✅ PESSOAS ATIVAS -->
                <div class="stat-item">
                    <div class="stat-value"><?= $active_team_stats['total_pessoas_ativas'] ?></div>
                    <div class="stat-label">Pessoas Ativas</div>
                </div>
                <!-- ✅ VALOR TOTAL DA EQUIPE -->
                <div class="stat-item">
                    <div class="stat-value">R$ <?= number_format($total_team_value, 0, ',', '.') ?></div>
                    <div class="stat-label">Valor Total Equipe</div>
                </div>
            </div>
        </div>

        <!-- Salários Disponíveis Card -->
        <?php if ($total_salary_available > 0): ?>
        <div class="salary-balance-card">
            <div class="balance-header">
                <div class="balance-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="balance-info">
                    <h3>Salários Disponíveis</h3>
                    <p>Prontos para transferir</p>
                </div>
                <button class="transfer-btn" onclick="transferSalaryToWallet()">
                    <i class="fas fa-arrow-right"></i>
                    Transferir
                </button>
            </div>
            <div class="balance-amount">R$ <?= number_format($total_salary_available, 2, ',', '.') ?></div>
        </div>
        <?php endif; ?>

        <!-- Pending Request Alert -->
        <?php if ($pending_request): ?>
        <div class="pending-alert">
            <i class="fas fa-clock"></i>
            <div class="pending-alert-content">
                <h4>Solicitação Pendente</h4>
                <p>Você tem uma solicitação de <?= htmlspecialchars($pending_request['level_name']) ?> aguardando análise.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Salary Levels -->
        <h2 class="section-title">
            <i class="fas fa-trophy"></i>
            Níveis de Salário
        </h2>
        
        <div class="levels-grid">
            <?php foreach ($levels as $level): 
                // ✅ NOVA VALIDAÇÃO CORRETA
                $meets_people = $active_team_stats['total_pessoas_ativas'] >= $level['min_people'];
                $meets_value = $total_team_value >= $level['min_team_value'];
                $is_eligible = $meets_people && $meets_value;
                $is_default = $level['is_default'];
                
                // Calcular progresso baseado nos critérios corretos
                $people_progress = $level['min_people'] > 0 ? min(100, ($active_team_stats['total_pessoas_ativas'] / $level['min_people']) * 100) : 100;
                $value_progress = $level['min_team_value'] > 0 ? min(100, ($total_team_value / $level['min_team_value']) * 100) : 100;
                
                // Verificar cooldown
                $stmt = $conn->prepare("
                    SELECT request_date FROM salary_requests 
                    WHERE user_id = ? AND level_id = ? 
                    ORDER BY request_date DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $level['id']]);
                $last_request = $stmt->fetch();
                
                $in_cooldown = false;
                $cooldown_days = 0;
                if ($last_request) {
                    $last_date = new DateTime($last_request['request_date']);
                    $now = new DateTime();
                    $days_diff = $now->diff($last_date)->days;
                    if ($days_diff < 30) {
                        $in_cooldown = true;
                        $cooldown_days = 30 - $days_diff;
                    }
                }
            ?>
            <div class="level-card <?= $is_eligible ? 'eligible' : '' ?> <?= $is_default ? 'qualified' : '' ?>">
                <div class="level-header">
                    <div class="level-info">
                        <div class="level-name">
                            <span class="level-icon"><?= htmlspecialchars($level['icon']) ?></span>
                            <span class="level-title"><?= htmlspecialchars($level['level_name']) ?></span>
                        </div>
                        <div class="level-code"><?= htmlspecialchars($level['level_code']) ?></div>
                        <div class="level-description"><?= htmlspecialchars($level['level_description']) ?></div>
                    </div>
                    <div class="level-salary">
                        R$ <?= number_format($level['monthly_salary'], 2, ',', '.') ?>
                    </div>
                </div>

                <div class="level-requirements">
                    <!-- ✅ PESSOAS ATIVAS -->
                    <div class="requirement <?= $meets_people ? 'met' : 'not-met' ?>">
                        <span>👥 Pessoas ativas</span>
                        <strong><?= $active_team_stats['total_pessoas_ativas'] ?>/<?= $level['min_people'] ?></strong>
                    </div>
                    <?php if ($level['min_people'] > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $people_progress ?>%"></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- ✅ VALOR TOTAL DA EQUIPE -->
                    <div class="requirement <?= $meets_value ? 'met' : 'not-met' ?>">
                        <span>💰 Valor total equipe</span>
                        <strong>R$ <?= number_format($total_team_value, 0, ',', '.') ?>/R$ <?= number_format($level['min_team_value'], 0, ',', '.') ?></strong>
                    </div>
                    <?php if ($level['min_team_value'] > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $value_progress ?>%"></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="level-actions">
                    <?php if ($is_default): ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-check"></i> Nível Inicial
                        </button>
                    <?php elseif ($pending_request): ?>
                        <button class="btn btn-pending" disabled>
                            <i class="fas fa-clock"></i> Solicitação Pendente
                        </button>
                    <?php elseif ($in_cooldown): ?>
                        <button class="btn btn-cooldown" disabled>
                            <i class="fas fa-hourglass-half"></i> Aguarde <?= $cooldown_days ?> dias
                        </button>
                    <?php elseif ($is_eligible): ?>
                        <button class="btn btn-primary" onclick="requestSalary(<?= $level['id'] ?>, '<?= htmlspecialchars($level['level_name']) ?>', <?= $level['monthly_salary'] ?>)">
                            <i class="fas fa-paper-plane"></i> Solicitar Salário
                        </button>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-lock"></i> Critérios não atendidos
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- History Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('requests')">
                <i class="fas fa-paper-plane"></i> Solicitações
            </button>
            <button class="tab" onclick="switchTab('payments')">
                <i class="fas fa-money-bill-wave"></i> Pagamentos
            </button>
        </div>

        <!-- Requests Tab Content -->
        <div class="tab-content active" id="requests-content">
            <?php if (empty($user_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-paper-plane"></i>
                <h3>Nenhuma solicitação ainda</h3>
                <p>Suas solicitações de salário aparecerão aqui</p>
            </div>
            <?php else: ?>
            <?php foreach ($user_requests as $request): ?>
            <div class="history-item">
                <div class="history-header">
                    <div class="history-level">
                        <span class="icon"><?= htmlspecialchars($request['icon']) ?></span>
                        <span class="name"><?= htmlspecialchars($request['level_name']) ?></span>
                    </div>
                    <span class="status-badge status-<?= $request['status'] ?>">
                        <?= ucfirst($request['status']) ?>
                    </span>
                </div>
                <div class="history-details">
                    <div>Valor: <span class="amount">R$ <?= number_format($request['requested_amount'], 2, ',', '.') ?></span></div>
                    <div>Equipe: <?= $request['current_team_people'] ?> pessoas ativas | R$ <?= number_format($request['current_team_value'], 0, ',', '.') ?> total</div>
                    <div class="date">Solicitado em <?= date('d/m/Y H:i', strtotime($request['request_date'])) ?></div>
                    <?php if ($request['admin_notes']): ?>
                    <div style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; font-size: 12px;">
                        <strong>Observações:</strong> <?= htmlspecialchars($request['admin_notes']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Payments Tab Content -->
        <div class="tab-content" id="payments-content">
            <?php if (empty($user_payments)): ?>
            <div class="empty-state">
                <i class="fas fa-money-bill-wave"></i>
                <h3>Nenhum pagamento ainda</h3>
                <p>Seus salários liberados aparecerão aqui</p>
            </div>
            <?php else: ?>
            <?php foreach ($user_payments as $payment): ?>
            <div class="history-item">
                <div class="history-header">
                    <div class="history-level">
                        <span class="icon"><?= htmlspecialchars($payment['icon']) ?></span>
                        <span class="name"><?= htmlspecialchars($payment['level_name']) ?></span>
                    </div>
                    <span class="status-badge status-<?= $payment['status'] ?>">
                        <?= $payment['status'] === 'disponivel' ? 'Disponível' : 'Transferido' ?>
                    </span>
                </div>
                <div class="history-details">
                    <div>Valor: <span class="amount">R$ <?= number_format($payment['amount'], 2, ',', '.') ?></span></div>
                    <div class="date">Liberado em <?= date('d/m/Y H:i', strtotime($payment['release_date'])) ?></div>
                    <?php if ($payment['transfer_date']): ?>
                    <div class="date">Transferido em <?= date('d/m/Y H:i', strtotime($payment['transfer_date'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../../inicio/">
            <i class="fas fa-home"></i>
            Início
        </a>
        <a href="../../investimentos/">
            <i class="fas fa-wallet"></i>
            Investimentos
        </a>
        <a href="../">
            <i class="fas fa-users"></i>
            Equipe
        </a>
        <a href="../../perfil/">
            <i class="fas fa-user"></i>
            Perfil
        </a>
    </nav>

    <!-- Request Modal -->
    <div class="modal" id="requestModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Solicitar Salário</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="requestForm">
                <input type="hidden" id="level_id" name="level_id">
                <input type="hidden" name="action" value="request_salary">
                
                <div class="form-group">
                    <label class="form-label">Nível Selecionado</label>
                    <div id="selected_level" style="padding: 12px; background: var(--dark-background); border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensagem (Opcional)</label>
                    <textarea class="form-control" name="user_message" placeholder="Adicione uma mensagem para o administrador..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Enviar Solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Transfer salary to wallet function
        function transferSalaryToWallet() {
            const totalSalary = <?= $total_salary_available ?>;
            
            if (totalSalary <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Nenhum Salário Disponível!',
                    html: `
                        <div style="background: var(--dark-background); border-radius: 8px; padding: 15px; margin: 10px 0; border: 1px solid var(--border-color);">
                            <p>Você não possui salários disponíveis para transferir.</p>
                            <p style="margin-top: 10px;"><span style="color: var(--info-color); font-weight: 700;">Solicite salários qualificando-se nos níveis MLM!</span></p>
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
                title: '💰 Confirmar Transferência',
                html: `
                    <div style="background: var(--dark-background); border-radius: 8px; padding: 15px; margin: 10px 0; border: 1px solid var(--border-color);">
                        <p>Deseja transferir <span style="color: var(--success-color); font-weight: 700;">R$ ${totalSalary.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span> de salários MLM para sua carteira?</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, transferir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--success-color)',
                cancelButtonColor: '#6B7280',
                background: 'var(--primary-color)',
                color: 'var(--text-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processando transferência...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=transfer_salary'
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '🎉 Transferência Realizada!',
                                html: `
                                    <div style="background: var(--dark-background); border-radius: 8px; padding: 20px; margin: 15px 0; border: 1px solid var(--border-color);">
                                        <div style="background: var(--success-color); border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white;">
                                            ✅
                                        </div>
                                        <p style="font-size: 16px; margin-bottom: 15px;">
                                            <strong>R$ ${data.amount.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong> foram transferidos para sua carteira!
                                        </p>
                                        <p style="font-size: 14px;">
                                            💡 <span style="color: var(--info-color); font-weight: 700;">Agora você pode usar esse valor para investir ou solicitar saque.</span>
                                        </p>
                                    </div>
                                `,
                                showConfirmButton: true,
                                confirmButtonText: '🚀 Ir para Carteira',
                                confirmButtonColor: 'var(--success-color)',
                                width: '90%',
                                background: 'var(--primary-color)',
                                color: 'var(--text-color)'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '❌ Erro!',
                                text: data.message,
                                confirmButtonText: 'OK',
                                background: 'var(--primary-color)',
                                color: 'var(--text-color)'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Erro:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '❌ Erro!',
                            text: 'Erro interno do servidor',
                            confirmButtonText: 'OK',
                            background: 'var(--primary-color)',
                            color: 'var(--text-color)'
                        });
                    });
                }
            });
        }

        // Tab switching
        function switchTab(tabName) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected tab and content
            event.target.classList.add('active');
            document.getElementById(tabName + '-content').classList.add('active');
        }

        // Modal functions
        function requestSalary(levelId, levelName, salary) {
            document.getElementById('level_id').value = levelId;
            document.getElementById('selected_level').innerHTML = `
                <strong>${levelName}</strong><br>
                <span style="color: var(--success-color); font-weight: 700;">R$ ${salary.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            `;
            document.getElementById('requestModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('requestModal').classList.remove('show');
            document.getElementById('requestForm').reset();
        }

        function goBack() {
            window.history.back();
        }

        // Form submission
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Show loading
            Swal.fire({
                title: 'Enviando solicitação...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '🎉 Solicitação Enviada!',
                        text: data.message,
                        confirmButtonText: 'OK',
                        background: 'var(--primary-color)',
                        color: 'var(--text-color)'
                    }).then(() => {
                        closeModal();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Erro!',
                        text: data.message,
                        confirmButtonText: 'OK',
                        background: 'var(--primary-color)',
                        color: 'var(--text-color)'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: '❌ Erro!',
                    text: 'Erro interno do servidor',
                    confirmButtonText: 'OK',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
            });
        });

        // Close modal when clicking outside
        document.getElementById('requestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>

    <!-- Pop-up e anúncios -->
    <?php if (!empty($config['pop_up'])): ?>
    <script>
        setTimeout(function() {
            Swal.fire({
                html: `<?= addslashes($config['pop_up']) ?>`,
                showConfirmButton: true,
                confirmButtonText: 'Fechar',
                background: 'var(--primary-color)',
                color: 'var(--text-color)'
            });
        }, 3000);
    </script>
    <?php endif; ?>

    <?php if (!empty($config['anuncio'])): ?>
    <div style="position: fixed; bottom: 90px; left: 20px; right: 20px; background: var(--blur-bg); backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 15px; z-index: 99; text-align: center;">
        <?= $config['anuncio'] ?>
        <button onclick="this.parentElement.style.display='none'" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--text-color); font-size: 18px; cursor: pointer;">&times;</button>
    </div>
    <?php endif; ?>

</body>
</html>