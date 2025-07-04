<?php
session_start(); // Inicia a sessão

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Se não estiver logado, redireciona para a página de login
    header('Location: ../');
    exit(); // Encerra o script
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

// Conexão com o banco de dados
$pdo = getDBConnection();

// Consulta as colunas link_suporte, pop_up e anuncio na tabela configurar_textos
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

// Consulta as colunas logo e tela_login na tabela personalizar_imagens
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT logo, checklist_image FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$logo = $result['logo'] ?? '3.png';
$checklist_image = $result['checklist_image'] ?? '1.jpg';

// Consulta as cores do banco de dados
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT cor_1, cor_2, cor_3, cor_4, cor_5 FROM personalizar_cores LIMIT 1");
$cores = $stmt->fetch(PDO::FETCH_ASSOC);

// Define as cores padrão caso nenhuma cor seja encontrada
$defaultColors = [
   'cor_1' => '#121A1E',
   'cor_2' => 'white',
   'cor_3' => '#152731',
   'cor_4' => '#335D67',
   'cor_5' => '#152731',
];

$cores = $cores ?: $defaultColors;

// Criar a conexão
try {
    $conn = getDBConnection(); // Chama a função para obter a conexão
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage()); // Mensagem de erro
}

// Recuperar o ID do usuário e buscar o progresso do checklist
$id_usuario = $_SESSION['user_id'];
$sql = "SELECT checklist, data_checklist FROM usuarios WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$checklist_dia = $userData ? $userData['checklist'] : 0;
$data_checklist = $userData ? $userData['data_checklist'] : null;
$hoje = date("Y-m-d");

// *** LÓGICA DE RESET AUTOMÁTICO ***
if ($data_checklist && $data_checklist < $hoje && $checklist_dia >= 6) {
    // Se passou um dia e o usuário completou o ciclo, resetar
    $sql_reset = "UPDATE usuarios SET checklist = 0 WHERE id = :id";
    $stmt_reset = $conn->prepare($sql_reset);
    $stmt_reset->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt_reset->execute();
    
    // Atualizar a variável para refletir o reset
    $checklist_dia = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta Tags -->
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<style>
    :root {
        --bg: <?= htmlspecialchars($cores['cor_1']) ?>;
        --text: <?= htmlspecialchars($cores['cor_2']) ?>;
        --primary: <?= htmlspecialchars($cores['cor_3']) ?>;
        --secondary: <?= htmlspecialchars($cores['cor_4']) ?>;
        --dark: <?= htmlspecialchars($cores['cor_5']) ?>;
        --success: #10B981;
        --warning: #F59E0B;
        --error: #EF4444;
        --border-radius: 16px;
        --border-radius-sm: 8px;
        --blur-bg: rgba(255, 255, 255, 0.08);
        --border-color: rgba(255, 255, 255, 0.15);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, var(--bg), var(--dark));
        min-height: 100vh;
        color: var(--text);
        position: relative;
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
            radial-gradient(circle at 80% 80%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
        pointer-events: none;
        z-index: -1;
    }

    .container {
        max-width: 360px;
        margin: 0 auto;
        padding: 16px;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .header {
        text-align: center;
        margin-bottom: 24px;
        padding: 16px 0;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--secondary), var(--success));
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
        margin: 0 auto 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .header h1 {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .header p {
        color: rgba(255,255,255,0.7);
        font-size: 13px;
    }

    .progress {
        background: rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .progress-text {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .progress-bar {
        height: 6px;
        background: rgba(255,255,255,0.1);
        border-radius: 6px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--secondary), var(--success));
        width: <?= ($checklist_dia / 6) * 100 ?>%;
        border-radius: 6px;
        transition: width 0.5s ease;
    }

    .checklist {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 24px;
        flex: 1;
    }

    .day-card {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px;
        padding: 16px 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .day-number {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .day-label {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
    }

    .day-icon {
        position: absolute;
        top: 6px;
        right: 8px;
        font-size: 12px;
    }

    .day-card.unlocked {
        background: linear-gradient(135deg, var(--success), #059669);
        border-color: var(--success);
        color: white;
        box-shadow: 0 6px 20px rgba(16,185,129,0.25);
    }

    .day-card.unlocked:hover {
        transform: translateY(-4px) scale(1.02);
    }

    .day-card.available {
        background: linear-gradient(135deg, var(--warning), #D97706);
        border-color: var(--warning);
        color: white;
        animation: pulse 2s infinite;
        box-shadow: 0 6px 20px rgba(245,158,11,0.25);
    }

    .day-card.available:hover {
        transform: translateY(-6px) scale(1.05);
        animation: none;
    }

    .day-card.locked {
        background: rgba(255,255,255,0.04);
        border-color: rgba(255,255,255,0.05);
        color: rgba(255,255,255,0.3);
        cursor: not-allowed;
    }

    .day-card.locked:hover {
        transform: translateY(-2px);
        border-color: rgba(239, 68, 68, 0.3);
        background: rgba(239, 68, 68, 0.05);
    }

    .footer {
        text-align: center;
        margin-bottom: 80px;
    }

    .footer-card {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        padding: 16px;
        color: rgba(255,255,255,0.8);
        font-size: 13px;
        line-height: 1.4;
    }

    .cycle-complete {
        background: linear-gradient(135deg, var(--success), #059669);
        border-color: var(--success);
        color: white;
        animation: celebration 3s ease-in-out;
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

    .bottom-nav a:hover {
        color: var(--secondary);
        background: rgba(51, 93, 103, 0.15);
    }

    .bottom-nav a i { font-size: 20px; }

    @keyframes pulse {
        0%, 100% { 
            transform: scale(1);
            box-shadow: 0 6px 20px rgba(245,158,11,0.25);
        }
        50% { 
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(245,158,11,0.4);
        }
    }

    @keyframes celebration {
        0%, 100% { transform: scale(1); }
        25% { transform: scale(1.05) rotate(2deg); }
        50% { transform: scale(1.1); }
        75% { transform: scale(1.05) rotate(-2deg); }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .day-card {
        animation: fadeInUp 0.6s ease-out;
    }

    .day-card:nth-child(1) { animation-delay: 0s; }
    .day-card:nth-child(2) { animation-delay: 0.1s; }
    .day-card:nth-child(3) { animation-delay: 0.2s; }
    .day-card:nth-child(4) { animation-delay: 0.3s; }
    .day-card:nth-child(5) { animation-delay: 0.4s; }
    .day-card:nth-child(6) { animation-delay: 0.5s; }

    /* Custom SweetAlert2 Styles */
    .custom-swal-popup {
        background: rgba(255,255,255,0.08) !important;
        backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        border-radius: 12px !important;
        color: var(--text) !important;
    }

    .custom-confirm-button {
        background: linear-gradient(135deg, var(--secondary), var(--success)) !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        padding: 12px 24px !important;
        border: none !important;
    }

    @media (max-width: 380px) {
        .container { padding: 12px; }
        .day-card { padding: 12px 6px; min-height: 70px; }
        .day-number { font-size: 18px; }
    }
</style>

<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h1>Checklist Diário</h1>
            <p>Complete as tarefas para ganhar recompensas</p>
        </div>

        <div class="progress">
            <div class="progress-text">
                <span>Progresso</span>
                <span><?= $checklist_dia ?>/6</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <div class="checklist">
            <?php
            for ($i = 1; $i <= 6; $i++) {
                if ($i <= $checklist_dia) {
                    $class = 'unlocked';
                    $icon = '✓';
                    $label = 'Feito';
                } elseif ($i == $checklist_dia + 1) {
                    $class = 'available';
                    $icon = '⭐';
                    $label = 'Agora';
                } else {
                    $class = 'locked';
                    $icon = '🔒';
                    $label = 'Bloq';
                }
                
                // Se completou o ciclo, adicionar classe especial
                $extraClass = ($checklist_dia >= 6) ? ' cycle-complete' : '';
                
                echo "<div id='day-$i' class='day-card $class$extraClass' onclick='desbloquearDia($i)'>
                        <div class='day-icon'>$icon</div>
                        <div class='day-number'>$i</div>
                        <div class='day-label'>$label</div>
                      </div>";
            }
            ?>
        </div>

        <div class="footer">
            <div class="footer-card">
                <?php if ($checklist_dia >= 6): ?>
                    🎉 Parabéns! Você completou todo o ciclo! Amanhã um novo ciclo começará.
                <?php else: ?>
                    💎 Volte amanhã para desbloquear o próximo dia e ganhar mais recompensas!
                <?php endif; ?>
            </div>
        </div>

        <nav class="bottom-nav">
            <a href="../inicio/"><i class="fas fa-home"></i> Início</a>
            <a href="../investimentos/"><i class="fas fa-wallet"></i> Investimentos</a>
            <a href="../team/"><i class="fas fa-users"></i> Equipe</a>
            <a href="../perfil/"><i class="fas fa-user"></i> Perfil</a>
        </nav>
    </div>

    <script>
        function desbloquearDia(dia) {
            fetch('checklist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ dia: dia })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`day-${dia}`).classList.remove('locked');
                    document.getElementById(`day-${dia}`).classList.add('unlocked');
                    
                    // Atualizar o progresso
                    const progressFill = document.querySelector('.progress-fill');
                    const progressText = document.querySelector('.progress-text span:last-child');
                    const newProgress = data.checklist_dia || dia;
                    progressFill.style.width = (newProgress / 6) * 100 + '%';
                    progressText.textContent = newProgress + '/6';
                    
                    // Atualizar ícone e label
                    const dayIcon = document.querySelector(`#day-${dia} .day-icon`);
                    const dayLabel = document.querySelector(`#day-${dia} .day-label`);
                    dayIcon.textContent = '✓';
                    dayLabel.textContent = 'Feito';
                    
                    // Verificar se completou o ciclo
                    if (data.ciclo_completo) {
                        // Adicionar animação de celebração
                        document.querySelectorAll('.day-card').forEach(card => {
                            card.classList.add('cycle-complete');
                        });
                        
                        // Atualizar mensagem do footer
                        const footerCard = document.querySelector('.footer-card');
                        footerCard.innerHTML = '🎉 Parabéns! Você completou todo o ciclo! Amanhã um novo ciclo começará.';
                    } else {
                        // Verificar se há próximo dia para disponibilizar
                        if (dia < 6) {
                            const nextDay = document.getElementById(`day-${dia + 1}`);
                            if (nextDay && nextDay.classList.contains('locked')) {
                                nextDay.classList.remove('locked');
                                nextDay.classList.add('available');
                                nextDay.querySelector('.day-icon').textContent = '⭐';
                                nextDay.querySelector('.day-label').textContent = 'Agora';
                            }
                        }
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: data.message,
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'custom-swal-popup',
                            confirmButton: 'custom-confirm-button'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aviso',
                        text: data.message,
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'custom-swal-popup',
                            confirmButton: 'custom-confirm-button'
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Conexão',
                    text: 'Não foi possível conectar ao servidor.',
                    customClass: {
                        popup: 'custom-swal-popup',
                        confirmButton: 'custom-confirm-button'
                    }
                });
            });
        }

        // Adicionar efeito ripple nos cards
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.day-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.3);
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Verificar se completou o ciclo na inicialização
            const progress = <?= $checklist_dia ?>;
            if (progress >= 6) {
                document.querySelectorAll('.day-card').forEach(card => {
                    card.classList.add('cycle-complete');
                });
            }
        });
    </script>

    <style>
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</body>
</html>