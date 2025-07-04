<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../../bank/db.php';

// ===============================================
// FUNÇÕES AUXILIARES
// ===============================================

// Buscar configurações de saque
function getConfigSaques($conn) {
    $stmt = $conn->prepare("SELECT * FROM config_saques WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Verificar se é feriado nacional
function ehFeriado($data, $considerar_feriados) {
    if (!$considerar_feriados) return false;
    
    $diaMes = date('m-d', strtotime($data));
    
    // Feriados nacionais fixos do Brasil
    $feriadosFixos = [
        '01-01', // Confraternização Universal
        '04-21', // Tiradentes
        '05-01', // Dia do Trabalhador
        '09-07', // Independência do Brasil
        '10-12', // Nossa Senhora Aparecida
        '11-02', // Finados
        '11-15', // Proclamação da República
        '12-25'  // Natal
    ];
    
    return in_array($diaMes, $feriadosFixos);
}

// Verificar se está em horário comercial
function isWithdrawTime($config) {
    date_default_timezone_set('America/Sao_Paulo');
    
    $now = new DateTime();
    $dayOfWeek = $now->format('N'); // 1 = Monday, 7 = Sunday
    $hour = $now->format('H:i:s');
    $hoje = $now->format('Y-m-d');
    $mes = $now->format('m');
    
    // Verificar bloqueios especiais
    if ($config['bloquear_dezembro'] && $mes == '12') return false;
    if ($config['bloquear_janeiro'] && $mes == '01') return false;
    if (ehFeriado($hoje, $config['considerar_feriados'])) return false;
    
    // Verificar dias da semana permitidos
    $diasPermitidos = [
        1 => $config['segunda_feira'],
        2 => $config['terca_feira'],
        3 => $config['quarta_feira'],
        4 => $config['quinta_feira'],
        5 => $config['sexta_feira'],
        6 => $config['sabado'],
        7 => $config['domingo']
    ];
    
    if (!$diasPermitidos[$dayOfWeek]) return false;
    
    // Verificar horário
    return ($hour >= $config['horario_inicio'] && $hour <= $config['horario_fim']);
}

// Verificar limite de saques
function hasWithdrawLimit($user_id, $conn, $config) {
    date_default_timezone_set('America/Sao_Paulo');
    $hoje = date('Y-m-d');
    $semanaAtual = date('Y-W');
    $mesAtual = date('Y-m');
    
    // Verificar limite diário
    if ($config['limite_diario']) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM saques WHERE user_id = ? AND DATE(data) = ?");
        $stmt->execute([$user_id, $hoje]);
        if ($stmt->fetchColumn() >= $config['limite_diario']) {
            return ['limit' => true, 'type' => 'diario'];
        }
    }
    
    // Verificar limite semanal
    if ($config['limite_semanal']) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM saques WHERE user_id = ? AND YEARWEEK(data) = YEARWEEK(?)");
        $stmt->execute([$user_id, $hoje]);
        if ($stmt->fetchColumn() >= $config['limite_semanal']) {
            return ['limit' => true, 'type' => 'semanal'];
        }
    }
    
    // Verificar limite mensal
    if ($config['limite_mensal']) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM saques WHERE user_id = ? AND DATE_FORMAT(data, '%Y-%m') = ?");
        $stmt->execute([$user_id, $mesAtual]);
        if ($stmt->fetchColumn() >= $config['limite_mensal']) {
            return ['limit' => true, 'type' => 'mensal'];
        }
    }
    
    return ['limit' => false];
}

// Calcular taxa de saque
function calcularTaxa($valor, $config) {
    $taxa = 0;
    
    switch ($config['calculo_taxa']) {
        case 'percentual':
            $taxa = ($valor * $config['taxa_percentual']) / 100;
            break;
            
        case 'fixo':
            $taxa = $config['taxa_fixa'];
            break;
            
        case 'hibrido':
            $taxaPercentual = ($valor * $config['taxa_percentual']) / 100;
            $taxa = $taxaPercentual + $config['taxa_fixa'];
            break;
    }
    
    $valorLiquido = $valor - $taxa;
    
    // Arredondar centavos se configurado
    if ($config['arredondar_centavos']) {
        $valorLiquido = round($valorLiquido, 2);
    }
    
    return [
        'valor_bruto' => $valor,
        'taxa' => $taxa,
        'valor_liquido' => $valorLiquido
    ];
}

// ===============================================
// INICIALIZAÇÃO
// ===============================================

// Conexão com o banco de dados
$pdo = getDBConnection();

// Buscar configurações do site
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// Buscar imagens personalizadas
$stmt = $pdo->query("SELECT logo, tela_retirada FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$logo = $result['logo'] ?? '3.png';
$tela_retirada = $result['tela_retirada'] ?? '1.jpg';

// Buscar cores do sistema
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

$message = "";

// ===============================================
// LÓGICA PRINCIPAL
// ===============================================

try {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    // BUSCAR CONFIGURAÇÕES DE SAQUE
    $config = getConfigSaques($conn);
    
    if (!$config) {
        throw new Exception("Configurações de saque não encontradas. Entre em contato com o suporte.");
    }
    
    // Buscar dados do usuário
    $stmt = $conn->prepare("SELECT saldo, nome, telefone FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar chave PIX ativa (se requerida)
    if ($config['requer_chave_pix']) {
        $stmtPix = $conn->prepare("SELECT tipo_pix, chave_pix, nome_titular, apelido FROM chaves_pix WHERE user_id = ? AND status = 'ativo' AND ativa = 1 LIMIT 1");
        $stmtPix->execute([$user_id]);
        $pixData = $stmtPix->fetch(PDO::FETCH_ASSOC);

        if (!$pixData) {
            // Verificar se tem chaves cadastradas mas nenhuma ativa
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM chaves_pix WHERE user_id = ? AND status = 'ativo'");
            $stmtCheck->execute([$user_id]);
            $totalChaves = $stmtCheck->fetchColumn();
            
            if ($totalChaves > 0) {
                header('Location: ../../vincular/pix/?erro=nenhuma_ativa');
            } else {
                header('Location: ../../vincular/pix/?erro=sem_chaves');
            }
            exit();
        }
        
        $userData = array_merge($userData, $pixData);
    }

    if (!$userData) {
        throw new Exception("Usuário não encontrado.");
    }

    // ===============================================
    // PROCESSAR FORMULÁRIO
    // ===============================================
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // 1. VERIFICAR HORÁRIO E DISPONIBILIDADE
            if (!isWithdrawTime($config)) {
                $mensagem = $config['mensagem_fora_horario'] ?: 'Saques não disponíveis no momento.';
                throw new Exception($mensagem);
            }
            
            // 2. VERIFICAR LIMITES
            $limitCheck = hasWithdrawLimit($user_id, $conn, $config);
            if ($limitCheck['limit']) {
                $mensagens = [
                    'diario' => $config['mensagem_limite_diario'] ?: 'Limite diário de saques atingido.',
                    'semanal' => 'Limite semanal de saques atingido.',
                    'mensal' => 'Limite mensal de saques atingido.'
                ];
                throw new Exception($mensagens[$limitCheck['type']]);
            }
            
            // 3. VALIDAR VALOR
            $valor_solicitado = floatval($_POST['valor_pix']);
            
            if ($valor_solicitado < $config['valor_minimo']) {
                throw new Exception("Valor mínimo para saque é R$ " . number_format($config['valor_minimo'], 2, ',', '.'));
            }
            
            if ($config['valor_maximo'] && $valor_solicitado > $config['valor_maximo']) {
                throw new Exception("Valor máximo para saque é R$ " . number_format($config['valor_maximo'], 2, ',', '.'));
            }
            
            if ($userData['saldo'] < $valor_solicitado) {
                $mensagem = $config['mensagem_saldo_insuficiente'] ?: 'Saldo insuficiente.';
                throw new Exception($mensagem);
            }
            
            // 4. VERIFICAR INVESTIMENTOS (se requerido)
            if ($config['requer_investimento_ativo']) {
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM investidores WHERE id_usuario = ? AND status = 'ativo'");
                $stmt->execute([$user_id]);
                $totalInvestimentos = $stmt->fetchColumn();

                if ($totalInvestimentos < $config['quantidade_min_investimentos']) {
                    $mensagem = $config['mensagem_sem_investimento'] ?: 'Você precisa ter investimentos ativos.';
                    throw new Exception($mensagem);
                }
            }
            
            // 5. CALCULAR TAXA
            $calculo = calcularTaxa($valor_solicitado, $config);
            
            // 6. PROCESSAR SAQUE
            $stmt = $conn->prepare("INSERT INTO saques (tipo_pix, chave_pix, nome_titular, user_id, valor, status, data, numero_telefone) VALUES (?, ?, ?, ?, ?, 'Pendente', NOW(), ?)");
            
            if ($stmt->execute([
                $userData['tipo_pix'] ?? '',
                $userData['chave_pix'] ?? '',
                $userData['nome_titular'] ?? $userData['nome'],
                $user_id,
                $calculo['valor_liquido'],
                $userData['telefone']
            ])) {
                // Atualizar saldo
                $novo_saldo = $userData['saldo'] - $valor_solicitado;
                $updateSaldo = $conn->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
                $updateSaldo->execute([$novo_saldo, $user_id]);

                // Mensagem de sucesso personalizada
                $mensagemSucesso = $config['mensagem_sucesso'] ?: 
                    "Saque solicitado com sucesso! Você receberá R$ " . number_format($calculo['valor_liquido'], 2, ',', '.') . " em sua chave PIX.";
                
                $message = "Swal.fire({ 
                    icon: 'success', 
                    title: '🎉 Saque Solicitado!', 
                    html: `
                        <div style='text-align: center; margin: 20px 0;'>
                            <div style='background: #10B981; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white;'>
                                💰
                            </div>
                            <p style='font-size: 16px; margin-bottom: 15px;'>
                                " . addslashes($mensagemSucesso) . "
                            </p>
                            <div style='background: var(--dark-background); border-radius: 8px; padding: 15px; margin: 15px 0; border: 1px solid var(--success-color);'>
                                <p style='font-size: 14px; margin-bottom: 8px;'><strong>Valor Solicitado:</strong> R$ " . number_format($valor_solicitado, 2, ',', '.') . "</p>
                                <p style='font-size: 14px; margin-bottom: 8px;'><strong>Taxa Cobrada:</strong> R$ " . number_format($calculo['taxa'], 2, ',', '.') . "</p>
                                <p style='font-size: 14px; margin-bottom: 8px;'><strong>Valor a Receber:</strong> R$ " . number_format($calculo['valor_liquido'], 2, ',', '.') . "</p>
                                <p style='font-size: 14px;'><strong>Processamento:</strong> " . $config['tempo_processamento_min'] . " a " . $config['tempo_processamento_max'] . " horas</p>
                            </div>
                        </div>
                    `,
                    background: 'var(--primary-color)', 
                    color: 'var(--text-color)' 
                });";
                
                // Atualizar dados do usuário
                $userData['saldo'] = $novo_saldo;
            } else {
                throw new Exception("Erro ao processar saque. Tente novamente.");
            }
            
        } catch (Exception $e) {
            $message = "Swal.fire({ icon: 'error', title: 'Erro', text: '" . addslashes($e->getMessage()) . "', background: 'var(--primary-color)', color: 'var(--text-color)' });";
        }
    }
    
} catch (Exception $e) {
    $message = "Swal.fire({ icon: 'error', title: 'Erro de conexão', text: '" . addslashes($e->getMessage()) . "', background: 'var(--primary-color)', color: 'var(--text-color)' });";
}

// ===============================================
// VERIFICAÇÕES PARA EXIBIÇÃO
// ===============================================

$horarioDisponivel = isWithdrawTime($config);
$limitCheck = hasWithdrawLimit($user_id, $conn, $config);
$temLimite = $limitCheck['limit'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Solicitar Saque</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords_site, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="canonical" href="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($link_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
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
            padding: 20px 0 80px 0;
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

        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .banner-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .banner-image {
            width: 100%;
            max-width: 350px;
            height: 200px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .status-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--info-color), var(--purple-color));
        }

        .time-status {
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
        }

        .time-open {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .time-closed {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }

        .chave-ativa-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--success-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .chave-ativa-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--success-color);
        }

        .chave-ativa-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chave-ativa-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 14px;
        }

        .chave-ativa-info div {
            background: rgba(16, 185, 129, 0.1);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .chave-ativa-info strong {
            color: var(--success-color);
            display: block;
            margin-bottom: 5px;
        }

        .form-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--warning-color), var(--success-color));
        }

        .form-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
            text-align: center;
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

        .form-input {
            width: 100%;
            padding: 15px;
            background: var(--dark-background);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-color);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--warning-color);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .form-input:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--warning-color), #D97706);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .instructions-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 25px;
            position: relative;
            overflow: hidden;
        }

        .instructions-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--error-color), var(--warning-color));
        }

        .instructions-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions-list {
            list-style: none;
            padding: 0;
        }

        .instructions-list li {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .instructions-list li:last-child {
            border-bottom: none;
        }

        .instructions-list li::before {
            content: '⚠️';
            flex-shrink: 0;
            margin-top: 2px;
        }

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
            justify-content: space-around;z-index: 100;
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

        .bottom-nav a:hover {
            color: var(--warning-color);
            background: rgba(245, 158, 11, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
        }

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

        .status-card,
        .chave-ativa-card,
        .form-card,
        .instructions-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .chave-ativa-card {
            animation-delay: 0.1s;
        }

        .form-card {
            animation-delay: 0.2s;
        }

        .instructions-card {
            animation-delay: 0.3s;
        }

        .custom-swal-popup {
            background: rgba(255,255,255,0.08) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 12px !important;
            color: var(--text-color) !important;
        }

        .custom-confirm-button {
            background: linear-gradient(135deg, var(--secondary-color), var(--success-color)) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 12px 24px !important;
            border: none !important;
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .status-card,
            .chave-ativa-card,
            .form-card,
            .instructions-card {
                padding: 20px;
            }

            .chave-ativa-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Status Card -->
        <div class="status-card">
            <div class="time-status <?= $horarioDisponivel ? 'time-open' : 'time-closed' ?>">
                <?= $horarioDisponivel ? '🟢 ABERTO' : '🔴 FECHADO' ?>
            </div>
            <?php if ($temLimite): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--error-color); border-radius: 8px; padding: 12px; text-align: center; margin-top: 15px;">
                <i class="fas fa-exclamation-triangle" style="color: var(--error-color); margin-right: 8px;"></i>
                <span style="color: var(--error-color); font-size: 14px; font-weight: 600;">
                    <?php
                    $mensagensLimite = [
                        'diario' => $config['mensagem_limite_diario'] ?: 'Limite diário atingido',
                        'semanal' => 'Limite semanal atingido',
                        'mensal' => 'Limite mensal atingido'
                    ];
                    echo $mensagensLimite[$limitCheck['type']];
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Banner Image -->
        <div class="banner-section">
            <img src="../assets/images/banners/<?= htmlspecialchars($tela_retirada) ?>" alt="Saque PIX" class="banner-image">
        </div>

        <!-- Chave PIX Ativa Card -->
        <?php if ($config['requer_chave_pix'] && isset($userData['chave_pix'])): ?>
        <div class="chave-ativa-card">
            <h3 class="chave-ativa-title">
                <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                Chave PIX Ativa para Saques
            </h3>
            <div class="chave-ativa-info">
                <div>
                    <strong>Tipo:</strong>
                    <?php
                    $icones = [
                        'cpf' => '📄 CPF',
                        'celular' => '📱 Celular',
                        'email' => '📧 Email',
                        'chave-aleatoria' => '🔑 Chave Aleatória'
                    ];
                    echo $icones[$userData['tipo_pix']] ?? '🔑 ' . strtoupper($userData['tipo_pix']);
                    ?>
                </div>
                <div>
                    <strong>Titular:</strong>
                    <?= htmlspecialchars($userData['nome_titular']) ?>
                </div>
                <div style="grid-column: span 2;">
                    <strong>Chave:</strong>
                    <?= htmlspecialchars($userData['chave_pix']) ?>
                    <?php if (!empty($userData['apelido'])): ?>
                        <br><small style="color: rgba(255,255,255,0.7);">Apelido: <?= htmlspecialchars($userData['apelido']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <h2 class="form-title">
                <i class="fas fa-money-bill-wave" style="color: var(--warning-color); margin-right: 10px;"></i>
                Solicitar Saque
            </h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="valor_pix" class="form-label">
                        <i class="fas fa-dollar-sign" style="margin-right: 5px;"></i>
                        Valor do Saque
                    </label>
                    <input 
                        type="number" 
                        name="valor_pix" 
                        id="valor_pix" 
                        class="form-input" 
                        min="<?= $config['valor_minimo'] ?>" 
                        max="<?= $config['valor_maximo'] ?: $userData['saldo'] ?>" 
                        placeholder="Insira o valor de retirada (mín. R$ <?= number_format($config['valor_minimo'], 2, ',', '.') ?>)" 
                        required
                        <?= (!$horarioDisponivel || $temLimite) ? 'disabled' : '' ?>
                    >
                    <div style="margin-top: 8px; font-size: 12px; color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                        Saldo disponível: <strong style="color: var(--success-color);">R$ <?= number_format($userData['saldo'], 2, ',', '.') ?></strong>
                    </div>
                    <div style="margin-top: 8px; font-size: 12px; color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-calculator" style="margin-right: 5px;"></i>
                        Você receberá: <span id="valor-liquido" style="color: var(--success-color); font-weight: 600;">R$ 0,00</span>
                        <?php if ($config['calculo_taxa'] == 'percentual'): ?>
                            (desconto de <?= $config['taxa_percentual'] ?>%)
                        <?php elseif ($config['calculo_taxa'] == 'fixo'): ?>
                            (taxa fixa de R$ <?= number_format($config['taxa_fixa'], 2, ',', '.') ?>)
                        <?php else: ?>
                            (taxa: <?= $config['taxa_percentual'] ?>% + R$ <?= number_format($config['taxa_fixa'], 2, ',', '.') ?>)
                        <?php endif; ?>
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="submit-btn"
                    <?= (!$horarioDisponivel || $temLimite) ? 'disabled' : '' ?>
                >
                    <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>
                    <?php if (!$horarioDisponivel): ?>
                        Fora do Horário de Funcionamento
                    <?php elseif ($temLimite): ?>
                        Limite de Saques Atingido
                    <?php else: ?>
                        Solicitar Saque PIX
                    <?php endif; ?>
                </button>
            </form>
        </div>

        <!-- Instructions Card -->
        <div class="instructions-card">
            <h3 class="instructions-title">
                <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i>
                Regras de Saque
            </h3>
            <ul class="instructions-list">
                <li>O valor mínimo de saque é R$ <?= number_format($config['valor_minimo'], 2, ',', '.') ?></li>
                <?php if ($config['valor_maximo']): ?>
                <li>O valor máximo de saque é R$ <?= number_format($config['valor_maximo'], 2, ',', '.') ?></li>
                <?php endif; ?>
                <li>Limite de <?= $config['limite_diario'] ?> saque(s) por dia</li>
                <?php if ($config['limite_semanal']): ?>
                <li>Limite de <?= $config['limite_semanal'] ?> saque(s) por semana</li>
                <?php endif; ?>
                <?php if ($config['limite_mensal']): ?>
                <li>Limite de <?= $config['limite_mensal'] ?> saque(s) por mês</li>
                <?php endif; ?>
                <li>Horário de funcionamento: <?= date('H:i', strtotime($config['horario_inicio'])) ?> às <?= date('H:i', strtotime($config['horario_fim'])) ?></li>
                <li>
                    Dias permitidos: 
                    <?php
                    $diasAtivos = [];
                    $nomesDias = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
                    $camposDias = ['segunda_feira', 'terca_feira', 'quarta_feira', 'quinta_feira', 'sexta_feira', 'sabado', 'domingo'];
                    
                    for ($i = 0; $i < 7; $i++) {
                        if ($config[$camposDias[$i]]) {
                            $diasAtivos[] = $nomesDias[$i];
                        }
                    }
                    echo implode(', ', $diasAtivos);
                    ?>
                </li>
                <li>
                    Taxa cobrada: 
                    <?php if ($config['calculo_taxa'] == 'percentual'): ?>
                        <?= $config['taxa_percentual'] ?>%
                    <?php elseif ($config['calculo_taxa'] == 'fixo'): ?>
                        R$ <?= number_format($config['taxa_fixa'], 2, ',', '.') ?>
                    <?php else: ?>
                        <?= $config['taxa_percentual'] ?>% + R$ <?= number_format($config['taxa_fixa'], 2, ',', '.') ?>
                    <?php endif; ?>
                </li>
                <li>Processamento em <?= $config['tempo_processamento_min'] ?> a <?= $config['tempo_processamento_max'] ?> horas úteis</li>
                <?php if ($config['requer_investimento_ativo']): ?>
                <li>Você deve ter pelo menos <?= $config['quantidade_min_investimentos'] ?> investimento(s) ativo(s)</li>
                <?php endif; ?>
                <?php if ($config['requer_chave_pix']): ?>
                <li>O saque será enviado para a chave PIX ativa exibida acima</li>
                <?php endif; ?>
                <?php if ($config['considerar_feriados']): ?>
                <li>Saques não são permitidos em feriados nacionais</li>
                <?php endif; ?>
                <?php if ($config['bloquear_dezembro']): ?>
                <li>Saques bloqueados durante o mês de dezembro</li>
                <?php endif; ?>
                <?php if ($config['bloquear_janeiro']): ?>
                <li>Saques bloqueados durante o mês de janeiro</li>
                <?php endif; ?>
            </ul>
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
        <a href="../../team/">
            <i class="fas fa-users"></i>
            Equipe
        </a>
        <a href="../../perfil/">
            <i class="fas fa-user"></i>
            Perfil
        </a>
    </nav>

    <!-- JavaScript -->
    <script>
        // Configurações da tabela para JavaScript
        const configSaque = <?= json_encode($config) ?>;
        
        // Calcula valor líquido em tempo real
        document.getElementById('valor_pix').addEventListener('input', function() {
            const valorBruto = parseFloat(this.value) || 0;
            let taxa = 0;
            
            // Calcular taxa baseado na configuração
            switch (configSaque.calculo_taxa) {
                case 'percentual':
                    taxa = (valorBruto * configSaque.taxa_percentual) / 100;
                    break;
                case 'fixo':
                    taxa = parseFloat(configSaque.taxa_fixa);
                    break;
                case 'hibrido':
                    const taxaPercentual = (valorBruto * configSaque.taxa_percentual) / 100;
                    taxa = taxaPercentual + parseFloat(configSaque.taxa_fixa);
                    break;
            }
            
            let valorLiquido = valorBruto - taxa;
            
            // Arredondar se configurado
            if (configSaque.arredondar_centavos == 1) {
                valorLiquido = Math.round(valorLiquido * 100) / 100;
            }
            
            document.getElementById('valor-liquido').textContent = 
                'R$ ' + valorLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });

        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const valor = parseFloat(document.getElementById('valor_pix').value);
            const saldoDisponivel = <?= $userData['saldo'] ?? 0 ?>;
            const valorMinimo = parseFloat(configSaque.valor_minimo);
            const valorMaximo = configSaque.valor_maximo ? parseFloat(configSaque.valor_maximo) : null;
            
            if (valor < valorMinimo) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Valor Inválido',
                    text: `O valor mínimo para saque é R$ ${valorMinimo.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`,
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }
            
            if (valorMaximo && valor > valorMaximo) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Valor Inválido',
                    text: `O valor máximo para saque é R$ ${valorMaximo.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`,
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }
            
            if (valor > saldoDisponivel) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Saldo Insuficiente',
                    text: `Você possui apenas R$ ${saldoDisponivel.toLocaleString('pt-BR', {minimumFractionDigits: 2})} disponível`,
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            // Calcular valores para confirmação
            let taxa = 0;
            switch (configSaque.calculo_taxa) {
                case 'percentual':
                    taxa = (valor * configSaque.taxa_percentual) / 100;
                    break;
                case 'fixo':
                    taxa = parseFloat(configSaque.taxa_fixa);
                    break;
                case 'hibrido':
                    const taxaPercentual = (valor * configSaque.taxa_percentual) / 100;
                    taxa = taxaPercentual + parseFloat(configSaque.taxa_fixa);
                    break;
            }
            
            let valorLiquido = valor - taxa;
            if (configSaque.arredondar_centavos == 1) {
                valorLiquido = Math.round(valorLiquido * 100) / 100;
            }

            // Confirmação antes de enviar
            e.preventDefault();
            
            Swal.fire({
                title: '💰 Confirmar Saque',
                html: `
                    <div style="text-align: left; margin: 15px 0;">
                        <p style="margin-bottom: 15px;">Confirme os dados do seu saque:</p>
                        <div style="background: var(--dark-background); padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid var(--warning-color);">
                            <p style="margin-bottom: 8px;"><strong>Valor Solicitado:</strong> R$ ${valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                            <p style="margin-bottom: 8px;"><strong>Taxa Cobrada:</strong> R$ ${taxa.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                            <p style="margin-bottom: 8px;"><strong>Valor a Receber:</strong> R$ ${valorLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                            <?php if ($config['requer_chave_pix'] && isset($userData['chave_pix'])): ?>
                            <p style="margin-bottom: 8px;"><strong>Chave PIX:</strong> <?= htmlspecialchars($userData['chave_pix']) ?></p>
                            <p><strong>Titular:</strong> <?= htmlspecialchars($userData['nome_titular'] ?? $userData['nome']) ?></p>
                            <?php endif; ?>
                        </div>
                        <p style="color: var(--warning-color); font-size: 14px; text-align: center;">
                            <strong>⏰ Processamento em ${configSaque.tempo_processamento_min} a ${configSaque.tempo_processamento_max} horas úteis</strong>
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmar Saque',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--warning-color)',
                background: 'var(--primary-color)',
                color: 'var(--text-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submete o formulário
                    this.submit();
                }
            });
        });

        // Atualizar cálculo inicial
        document.addEventListener('DOMContentLoaded', function() {
            const valorInput = document.getElementById('valor_pix');
            if (valorInput.value) {
                valorInput.dispatchEvent(new Event('input'));
            }
        });
    </script>

    <!-- SweetAlert Messages -->
    <?php if ($message): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?= $message ?>
        });
    </script>
    <?php endif; ?>

    <!-- Pop-up de anúncio (se configurado) -->
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

    <!-- Anúncio fixo (se configurado) -->
    <?php if (!empty($anuncio)): ?>
    <div style="position: fixed; bottom: 90px; left: 20px; right: 20px; background: var(--blur-bg); backdrop-filter: blur(20px); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 15px; z-index: 99; text-align: center;">
        <?= $anuncio ?>
        <button onclick="this.parentElement.style.display='none'" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--text-color); font-size: 18px; cursor: pointer;">&times;</button>
    </div>
    <?php endif; ?>

</body>
</html>