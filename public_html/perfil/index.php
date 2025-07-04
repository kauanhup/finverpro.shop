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
$stmt = $pdo->query("SELECT logo, tela_login, tela_avatar FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Define valores padrão caso não encontre no banco
$logo = $result['logo'] ?? '3.png';
$tela_perfil = $result['tela_perfil'] ?? '1.jpg';
$tela_avatar = $result['tela_avatar'] ?? '4.jpg';

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

// Obter ID do usuário da sessão
$user_id = $_SESSION['user_id'];

// Consultar dados do usuário incluindo nível VIP e foto de perfil
$query = "SELECT u.saldo, u.telefone, u.valor_investimento, u.codigo_referencia, u.salario_total, u.nivel_vip_id, u.nome, u.foto_perfil,
                 nv.nivel, nv.nome as nivel_nome, nv.cor_badge, nv.icone, nv.emoji
          FROM usuarios u 
          LEFT JOIN niveis_vip nv ON u.nivel_vip_id = nv.id 
          WHERE u.id = :user_id";
$stmt = $conn->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifique se as variáveis foram obtidas corretamente e adicione proteção contra null
$saldo = $user['saldo'] ?? 0;
$telefone = $user['telefone'] ?? 'N/A';
$valor_investido = $user['valor_investimento'] ?? 0;
$codigo_referencia = $user['codigo_referencia'] ?? null;
$salario_total = $user['salario_total'] ?? 0;
$nome_usuario = !empty($user['nome']) ? $user['nome'] : 'Investidor';
$foto_perfil = $user['foto_perfil'] ?? null;

// Array de frases motivacionais
$frases_motivacionais = [
    "Construindo seu futuro financeiro 💰",
    "Cada investimento é um passo rumo ao sucesso 🚀",
    "Transformando sonhos em realidade 💎",
    "Seu patrimônio crescendo a cada dia 📈",
    "Investindo no que realmente importa ⭐",
    "Rumo à independência financeira 🎯",
    "Fazendo o dinheiro trabalhar por você 💸",
    "Seu sucesso é nosso compromisso 🏆"
];

// Escolher uma frase aleatória baseada no ID do usuário (sempre a mesma para o mesmo usuário)
$frase_motivacional = $frases_motivacionais[$user_id % count($frases_motivacionais)];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Perfil</title>
    
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

        /* Header Redesenhado - Avatar lado esquerdo, info lado direito */
        .header-section {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            padding: 25px 20px;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--info-color));
        }

        .profile-container {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            max-width: 350px;
            margin: 0 auto;
        }

        /* Avatar menor, quadrado - LADO ESQUERDO */
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            box-shadow: 
                0 10px 20px rgba(0, 0, 0, 0.3),
                0 0 0 3px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .profile-avatar::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shine 3s ease-in-out infinite;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 
                0 15px 30px rgba(0, 0, 0, 0.4),
                0 0 0 4px rgba(255, 255, 255, 0.15);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 17px;
        }

        .camera-overlay {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 25px;
            height: 25px;
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            border: 3px solid var(--background-color);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .profile-avatar:hover .camera-overlay {
            opacity: 1;
        }

        /* Informações do Perfil - LADO DIREITO em coluna */
        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
            justify-content: center;
            min-height: 80px;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
            line-height: 1.2;
        }

        /* Texto motivacional no lugar do badge VIP */
        .motivational-text {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            line-height: 1.3;
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-style: italic;
        }

        /* Animações */
        @keyframes shine {
            0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Seção do Saldo */
        .balance-section {
            padding: 40px 20px;
            text-align: center;
        }

        .balance-title {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 15px;
        }

        .balance-amount {
            font-size: 48px;
            font-weight: 800;
            color: var(--text-color);
            margin-bottom: 25px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .balance-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .balance-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .balance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        /* Menu Section */
        .menu-section {
            padding: 0 20px 30px;
        }

        .menu-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 20px;
            text-align: center;
        }

        .menu-grid {
            display: grid;
            gap: 12px;
        }

        .menu-item {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(51, 93, 103, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .menu-item:hover::before {
            left: 100%;
        }

        .menu-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--secondary-color);
            background: rgba(255, 255, 255, 0.12);
        }

        .menu-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 18px;
            font-size: 18px;
            color: white;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .menu-icon.gift { background: linear-gradient(135deg, var(--pink-color), var(--purple-color)); }
        .menu-icon.pix { background: linear-gradient(135deg, var(--success-color), #059669); }
        .menu-icon.withdraw { background: linear-gradient(135deg, var(--warning-color), var(--orange-color)); }
        .menu-icon.reports { background: linear-gradient(135deg, var(--info-color), var(--secondary-color)); }
        .menu-icon.invite { background: linear-gradient(135deg, var(--purple-color), var(--pink-color)); }
        .menu-icon.support { background: linear-gradient(135deg, var(--secondary-color), var(--info-color)); }
        .menu-icon.logout { background: linear-gradient(135deg, var(--error-color), #DC2626); }
        .menu-icon.camera { background: linear-gradient(135deg, var(--purple-color), var(--pink-color)); }

        .menu-item:hover .menu-icon {
            transform: scale(1.1) rotate(10deg);
        }

        .menu-text {
            flex: 1;
            font-size: 16px;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }

        .menu-arrow {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease;
        }

        .menu-item:hover .menu-arrow {
            transform: translateX(5px);
            color: var(--secondary-color);
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
            color: var(--secondary-color);
            background: rgba(51, 93, 103, 0.15);
        }

        .bottom-nav a i {
            font-size: 20px;
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

        .header-section,
        .balance-section,
        .menu-section {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .profile-container {
                gap: 15px;
            }

            .profile-avatar {
                width: 70px;
                height: 70px;
                font-size: 28px;
            }

            .profile-name {
                font-size: 20px;
            }

            .motivational-text {
                font-size: 12px;
            }

            .balance-amount {
                font-size: 36px;
            }

            .menu-item {
                padding: 15px 18px;
            }

            .menu-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
                margin-right: 15px;
            }

            .balance-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Header Redesenhado -->
    <div class="header-section">
        <div class="profile-container">
            <div class="profile-avatar" onclick="window.location.href='./alterar-foto/'">
                <?php if (!empty($foto_perfil) && file_exists("../uploads/perfil/" . $foto_perfil)): ?>
                    <img src="../uploads/perfil/<?= htmlspecialchars($foto_perfil) ?>" alt="Foto de Perfil">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
                <div class="camera-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <div class="profile-info">
                <h2 class="profile-name">Olá, <?= htmlspecialchars($nome_usuario) ?>!</h2>
                <div class="motivational-text"><?= htmlspecialchars($frase_motivacional) ?></div>
            </div>
        </div>
    </div>

    <!-- Seção do Saldo -->
    <div class="balance-section">
        <div class="balance-title">Conta de Capital (R$)</div>
        <div class="balance-amount">
            R$ <?php echo number_format((float)$saldo, 2, ',', '.'); ?>
        </div>
        
        <!-- Botões de Ação -->
        <div class="balance-actions">
            <a href="../investimentos/" class="balance-btn btn-primary">
                <i class="fas fa-plus"></i>
                Investir
            </a>
            <a href="../retirar/dinheiro/" class="balance-btn btn-secondary">
                <i class="fas fa-wallet"></i>
                Sacar
            </a>
        </div>
    </div>

    <!-- Menu Section -->
    <div class="menu-section">
        <h3 class="menu-title">Navegação Geral</h3>
        <div class="menu-grid">
            <a href="./alterar-foto/" class="menu-item">
                <div class="menu-icon camera">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="menu-text">Alterar Foto</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="../bonus/" class="menu-item">
                <div class="menu-icon gift">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="menu-text">Código de Bônus</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="../vincular/pix/" class="menu-item">
                <div class="menu-icon pix">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="menu-text">Vincular Pix</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="../relatorios/" class="menu-item">
                <div class="menu-icon reports">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="menu-text">Relatório Geral</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="#" class="menu-item" id="invite-link" data-ref="<?php echo htmlspecialchars($codigo_referencia ?? ''); ?>">
                <div class="menu-icon invite">
                    <i class="fas fa-users"></i>
                </div>
                <div class="menu-text">Link de Convite</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="<?= htmlspecialchars($linkSuporte, ENT_QUOTES, 'UTF-8'); ?>" class="menu-item">
                <div class="menu-icon support">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="menu-text">Central de Ajuda</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>

            <a href="../finalizar/sessao/" class="menu-item">
                <div class="menu-icon logout">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="menu-text">Sair</div>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
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
        <a href="../team/">
            <i class="fas fa-users"></i>
            Equipe
        </a>
        <a href="./" class="active">
            <i class="fas fa-user"></i>
            Perfil
        </a>
    </nav>

    <script>
        // Link de convite functionality
        document.getElementById('invite-link').addEventListener('click', function(event) {
            event.preventDefault();
            const refCode = this.getAttribute('data-ref');
            
            if (!refCode || refCode === '') {
                Swal.fire({
                    title: 'Atenção!',
                    text: 'Código de referência não encontrado. Entre em contato com o suporte.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            const inviteUrl = `<?= htmlspecialchars($link_site) ?>/cadastro/?ref=${refCode}`;
            
            navigator.clipboard.writeText(inviteUrl).then(function() {
                Swal.fire({
                    title: 'Link Copiado!',
                    text: 'Seu link de convite foi copiado para a área de transferência.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            }, function(err) {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Falha ao copiar o link. Tente novamente.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });

        // Click animations
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.id === 'invite-link') return;
                
                const ripple = document.createElement('div');
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(51, 93, 103, 0.3);
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

        // Animação do avatar
        document.querySelector('.profile-avatar').addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    </script>
</body>
</html>