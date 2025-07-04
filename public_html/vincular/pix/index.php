<?php
session_start();

if (!isset($_SESSION['user_id'])) {
   header('Location: ../');
   exit();
}

require '../../bank/db.php';

$pdo = getDBConnection();

// Consulta configurações
$stmt = $pdo->query("SELECT link_suporte, pop_up, anuncio, titulo_site, descricao_site, keywords_site, link_site FROM configurar_textos LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$linkSuporte = $result['link_suporte'] ?? '/';
$popUp = $result['pop_up'] ?? '';
$anuncio = $result['anuncio'] ?? '';
$titulo_site = $result['titulo_site'] ?? '';
$descricao_site = $result['descricao_site'] ?? '';
$keywords_site = $result['keywords_site'] ?? '';
$link_site = $result['link_site'] ?? '';

$stmt = $pdo->query("SELECT logo, tela_pix FROM personalizar_imagens LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$logo = $result['logo'] ?? '3.png';
$tela_pix = $result['tela_pix'] ?? '1.jpg';

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

$userId = $_SESSION['user_id'];

// Buscar chaves PIX existentes
$stmt = $pdo->prepare("SELECT * FROM chaves_pix WHERE user_id = ? AND status = 'ativo' ORDER BY ativa DESC, created_at DESC");
$stmt->execute([$userId]);
$chavesExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalChaves = count($chavesExistentes);
$podeAdicionar = $totalChaves < 3;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_site, ENT_QUOTES, 'UTF-8'); ?> - Gerenciar Chaves PIX</title>
    <meta name="description" content="<?= htmlspecialchars($descricao_site, ENT_QUOTES, 'UTF-8'); ?>">
    
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

        /* Status Card */
        .status-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
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

        .status-text {
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }

        .status-counter {
            background: var(--success-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }

        /* Chaves PIX Cards */
        .chaves-list {
            margin-bottom: 25px;
        }

        .chaves-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 5px;
        }

        .chaves-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-btn {
            background: var(--secondary-color);
            border: none;
            color: var(--text-color);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .toggle-btn:hover {
            background: var(--success-color);
            transform: translateY(-1px);
        }

        .chaves-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
            opacity: 0;
        }

        .chaves-container.expanded {
            max-height: 2000px; /* Altura suficiente para comportar as chaves */
            opacity: 1;
            transition: max-height 0.5s ease-out, opacity 0.3s ease-out 0.1s;
        }

        .chave-card {
            background: var(--blur-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .chaves-container.expanded .chave-card {
            transform: translateY(0);
        }

        .chave-card.ativa {
            border-color: var(--success-color);
        }

        .chave-card.ativa::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--success-color);
        }

        .chave-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chave-tipo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 16px;
        }

        .badge-ativa {
            background: var(--success-color);
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .chave-info {
            margin-bottom: 15px;
        }

        .chave-info p {
            margin: 8px 0;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }

        .chave-info strong {
            color: var(--text-color);
        }

        .chave-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-ativar {
            background: var(--success-color);
            color: white;
        }

        .btn-ativar:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-ativar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Aviso de chave permanente */
        .chave-permanente {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            font-size: 12px;
            color: var(--success-color);
            margin-top: 10px;
        }

        /* Form Card */
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
            background: linear-gradient(90deg, var(--info-color), var(--success-color));
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

        .form-select,
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

        .form-select:focus,
        .form-input:focus {
            outline: none;
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-select option {
            background: var(--dark-background);
            color: var(--text-color);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--success-color), #059669);
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

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Aviso importante */
        .aviso-permanente {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning-color);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
        }

        .aviso-permanente i {
            color: var(--warning-color);
            margin-right: 8px;
        }

        .aviso-permanente p {
            color: var(--warning-color);
            font-size: 14px;
            font-weight: 600;
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
            color: var(--success-color);
            background: rgba(16, 185, 129, 0.15);
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
        .chave-card,
        .form-card {
            animation: fadeInUp 0.6s ease-out;
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .status-card,
            .chave-card,
            .form-card {
                padding: 20px;
            }

            .chave-actions {
                flex-direction: column;
            }

            .btn-action {
                padding: 12px;
            }

            .toggle-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Status Card -->
        <div class="status-card">
            <div class="status-text">
                <i class="fas fa-credit-card" style="color: var(--success-color); margin-right: 8px;"></i>
                Chaves Cadastradas: 
                <span class="status-counter"><?= $totalChaves ?>/3</span>
            </div>
        </div>

        <!-- Banner Image -->
        <div class="banner-section">
            <img src="../../assets/images/banners/<?= htmlspecialchars($tela_pix) ?>" alt="PIX" class="banner-image">
        </div>

        <!-- Lista de Chaves Existentes -->
        <?php if ($totalChaves > 0): ?>
        <div class="chaves-list">
            <div class="chaves-header">
                <h3 class="chaves-title">
                    <i class="fas fa-list"></i>Suas Chaves PIX
                </h3>
                <button class="toggle-btn" onclick="toggleChaves()">
                    <i class="fas fa-eye" id="toggle-icon"></i> 
                    <span id="toggle-text">Ver</span>
                </button>
            </div>
            
            <div class="chaves-container" id="chaves-container">
                <?php foreach ($chavesExistentes as $chave): ?>
                <div class="chave-card <?= $chave['ativa'] ? 'ativa' : '' ?>">
                    <div class="chave-header">
                        <div class="chave-tipo">
                            <?php
                            $icones = [
                                'cpf' => '📄',
                                'celular' => '📱',
                                'email' => '📧',
                                'chave-aleatoria' => '🔑'
                            ];
                            echo $icones[$chave['tipo_pix']] ?? '🔑';
                            echo ' ' . strtoupper($chave['tipo_pix']);
                            ?>
                        </div>
                        <?php if ($chave['ativa']): ?>
                        <span class="badge-ativa">ATIVA</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="chave-info">
                        <p><strong>Titular:</strong> <?= htmlspecialchars($chave['nome_titular']) ?></p>
                        <p><strong>Chave:</strong> <?= htmlspecialchars($chave['chave_pix']) ?></p>
                        <?php if ($chave['apelido']): ?>
                        <p><strong>Apelido:</strong> <?= htmlspecialchars($chave['apelido']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$chave['ativa']): ?>
                    <div class="chave-actions">
                        <button class="btn-action btn-ativar" onclick="ativarChave(<?= $chave['id'] ?>)">
                            <i class="fas fa-check"></i> Ativar para Saques
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="chave-permanente">
                        <i class="fas fa-lock"></i> Chave cadastrada permanentemente
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form para Nova Chave -->
        <?php if ($podeAdicionar): ?>
        <div class="form-card">
            <h2 class="form-title">
                <i class="fas fa-plus" style="color: var(--success-color); margin-right: 10px;"></i>
                Adicionar Nova Chave PIX
            </h2>
            
            <form id="pix-form">
                <div class="form-group">
                    <label for="apelido" class="form-label">
                        <i class="fas fa-tag" style="margin-right: 5px;"></i>
                        Apelido (Opcional)
                    </label>
                    <input type="text" id="apelido" name="apelido" class="form-input" placeholder="Ex: Conta Principal, Conta Pessoal..." maxlength="50">
                </div>

                <div class="form-group">
                    <label for="chave-pix" class="form-label">
                        <i class="fas fa-list" style="margin-right: 5px;"></i>
                        Tipo de Chave PIX
                    </label>
                    <select id="chave-pix" name="tipo_pix" class="form-select" required onchange="formatarCampoPix()">
                        <option value="" disabled selected>Selecione o tipo de chave</option>
                        <option value="cpf">📄 CPF</option>
                        <option value="celular">📱 Celular</option>
                        <option value="email">📧 Email</option>
                        <option value="chave-aleatoria">🔑 Chave Aleatória</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nome-titular" class="form-label">
                        <i class="fas fa-user" style="margin-right: 5px;"></i>
                        Nome do Titular
                    </label>
                    <input type="text" id="nome-titular" name="nome_titular" class="form-input" placeholder="Digite o nome completo do titular" required>
                </div>

                <div class="form-group">
                    <label for="chave-pix-valor" class="form-label">
                        <i class="fas fa-key" style="margin-right: 5px;"></i>
                        Chave PIX
                    </label>
                    <input type="text" id="chave-pix-valor" name="chave_pix" class="form-input" placeholder="Digite sua chave PIX" required>
                </div>

                <button type="button" class="submit-btn" onclick="salvarChavePix()">
                    <i class="fas fa-save" style="margin-right: 8px;"></i>
                    Salvar Chave PIX
                </button>
                
                <div class="aviso-permanente">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>ATENÇÃO: Após cadastrada, a chave PIX não poderá ser removida!</p>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="form-card">
            <div style="text-align: center; padding: 20px;">
                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success-color); margin-bottom: 15px;"></i>
                <h3 style="color: var(--text-color); margin-bottom: 10px;">Limite Completo</h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Você já cadastrou o máximo de 3 chaves PIX permanentes.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

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

    <script>
        // Função para mostrar/ocultar chaves - CORRIGIDA
        function toggleChaves() {
            const container = document.getElementById('chaves-container');
            const icon = document.getElementById('toggle-icon');
            const text = document.getElementById('toggle-text');
            const button = document.querySelector('.toggle-btn');
            
            if (container.classList.contains('expanded')) {
                // Fechando
                container.classList.remove('expanded');
                icon.className = 'fas fa-eye';
                text.textContent = 'Ver';
                button.style.background = 'var(--secondary-color)';
            } else {
                // Abrindo
                container.classList.add('expanded');
                icon.className = 'fas fa-eye-slash';
                text.textContent = 'Ocultar';
                button.style.background = 'var(--success-color)';
            }
        }

        // Função para ativar chave
        function ativarChave(chaveId) {
            Swal.fire({
                title: 'Ativar Chave PIX',
                text: 'Esta chave será usada para seus saques. Deseja continuar?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, Ativar',
                cancelButtonText: 'Cancelar',
                background: 'var(--primary-color)',
                color: 'var(--text-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('gerenciar_chaves.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            acao: 'ativar',
                            chave_id: chaveId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Chave Ativada!',
                                text: 'Esta chave agora será usada para seus saques.',
                                background: 'var(--primary-color)',
                                color: 'var(--text-color)'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro',
                                text: data.message,
                                background: 'var(--primary-color)',
                                color: 'var(--text-color)'
                            });
                        }
                    });
                }
            });
        }

        function formatarCampoPix() {
            const tipoPix = document.getElementById('chave-pix').value;
            const chavePixInput = document.getElementById('chave-pix-valor');
        
            chavePixInput.value = '';
            chavePixInput.removeAttribute('maxlength');
        
            if (tipoPix === 'cpf') {
                chavePixInput.setAttribute('placeholder', 'Digite seu CPF (000.000.000-00)');
                chavePixInput.setAttribute('maxlength', '14');
                chavePixInput.setAttribute('type', 'text');
                chavePixInput.oninput = formatarCPF;
            } else if (tipoPix === 'celular') {
                chavePixInput.setAttribute('placeholder', 'Digite seu celular (11 99999-9999)');
                chavePixInput.setAttribute('maxlength', '15');
                chavePixInput.setAttribute('type', 'text');
                chavePixInput.oninput = formatarCelular;
            } else if (tipoPix === 'email') {
                chavePixInput.setAttribute('placeholder', 'Digite seu email');
                chavePixInput.setAttribute('type', 'email');
                chavePixInput.oninput = null;
            } else if (tipoPix === 'chave-aleatoria') {
                chavePixInput.setAttribute('placeholder', 'Digite sua chave aleatória');
                chavePixInput.setAttribute('type', 'text');
                chavePixInput.oninput = null;
            }
        }
        
        function formatarCPF(e) {
            e.target.value = e.target.value
                .replace(/\D/g, '')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        
        function formatarCelular(e) {
            e.target.value = e.target.value
                .replace(/\D/g, '')
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d)/, '$1-$2');
        }
        
        function validarCPF(cpf) {
            cpf = cpf.replace(/\D/g, '');
            if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
        
            let soma = 0, resto;
            for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i-1, i)) * (11 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            if (resto !== parseInt(cpf.substring(9, 10))) return false;
        
            soma = 0;
            for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i-1, i)) * (12 - i);
            resto = (soma * 10) % 11;
            if (resto === 10 || resto === 11) resto = 0;
            return resto === parseInt(cpf.substring(10, 11));
        }

        function validarEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function validarCelular(celular) {
            const celularNumerico = celular.replace(/\D/g, '');
            return celularNumerico.length === 10 || celularNumerico.length === 11;
        }
        
        function salvarChavePix() {
            const tipoPix = document.getElementById('chave-pix').value;
            const nomeTitular = document.getElementById('nome-titular').value;
            const chavePix = document.getElementById('chave-pix-valor').value;
            const apelido = document.getElementById('apelido').value;
        
            if (!tipoPix || !nomeTitular || !chavePix) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ Campos Obrigatórios',
                    text: 'Por favor, preencha todos os campos obrigatórios.',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            // Validações específicas por tipo
            if (tipoPix === 'cpf' && !validarCPF(chavePix)) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ CPF Inválido',
                    text: 'Por favor, verifique o CPF e tente novamente.',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            if (tipoPix === 'email' && !validarEmail(chavePix)) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ Email Inválido',
                    text: 'Por favor, digite um email válido.',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            if (tipoPix === 'celular' && !validarCelular(chavePix)) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ Celular Inválido',
                    text: 'Por favor, verifique o número do celular.',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            if (tipoPix === 'chave-aleatoria' && chavePix.length < 10) {
                Swal.fire({
                    icon: 'error',
                    title: '❌ Chave Muito Curta',
                    text: 'A chave aleatória deve ter pelo menos 10 caracteres.',
                    background: 'var(--primary-color)',
                    color: 'var(--text-color)'
                });
                return;
            }

            // Confirmação antes de salvar (já que é permanente)
            Swal.fire({
                title: '⚠️ Confirmação',
                html: `
                    <div style="text-align: left; margin: 15px 0;">
                        <p style="margin-bottom: 15px;">Você está prestes a cadastrar uma chave PIX <strong>permanente</strong>:</p>
                        <div style="background: var(--dark-background); padding: 12px; border-radius: 8px; margin: 10px 0;">
                            <p><strong>Tipo:</strong> ${tipoPix.toUpperCase()}</p>
                            <p><strong>Chave:</strong> ${chavePix}</p>
                            <p><strong>Titular:</strong> ${nomeTitular}</p>
                            ${apelido ? `<p><strong>Apelido:</strong> ${apelido}</p>` : ''}
                        </div>
                        <p style="color: var(--warning-color); font-size: 14px;">
                            <strong>ATENÇÃO:</strong> Esta chave não poderá ser removida após o cadastro!
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, Cadastrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--warning-color)',
                background: 'var(--primary-color)',
                color: 'var(--text-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Processar o cadastro
                    fetch('gerenciar_chaves.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            acao: 'adicionar',
                            tipo_pix: tipoPix,
                            nome_titular: nomeTitular,
                            chave_pix: chavePix,
                            apelido: apelido
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '🎉 Chave PIX Cadastrada!',
                                html: `
                                    <div style="text-align: center; margin: 20px 0;">
                                        <div style="background: #10B981; border-radius: 50%; width: 80px; height: 80px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: white;">
                                            🔒
                                        </div>
                                        <p style="font-size: 16px; margin-bottom: 15px;">
                                            Sua chave PIX foi cadastrada permanentemente!
                                        </p>
                                        <div style="background: var(--dark-background); border-radius: 8px; padding: 15px; margin: 15px 0; border: 1px solid var(--success-color);">
                                            <p style="font-size: 14px; margin-bottom: 8px;"><strong>Tipo:</strong> ${tipoPix.toUpperCase()}</p>
                                            <p style="font-size: 14px; margin-bottom: 8px;"><strong>Titular:</strong> ${nomeTitular}</p>
                                            <p style="font-size: 14px;"><strong>Chave:</strong> ${chavePix}</p>
                                            ${apelido ? `<p style="font-size: 14px;"><strong>Apelido:</strong> ${apelido}</p>` : ''}
                                        </div>
                                        <p style="font-size: 14px; color: var(--success-color);">
                                            ${data.primeira_chave ? '✅ Esta é sua chave ativa para saques!' : '📝 Para usar nos saques, ative esta chave.'}
                                        </p>
                                    </div>
                                `,
                                confirmButtonText: 'Continuar',
                                background: 'var(--primary-color)',
                                color: 'var(--text-color)'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '❌ Erro ao Salvar',
                                text: data.message || 'Ocorreu um erro inesperado. Tente novamente.',
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
                            text: 'Não foi possível conectar ao servidor. Verifique sua conexão.',
                            background: 'var(--primary-color)',
                            color: 'var(--text-color)'
                        });
                    });
                }
            });
        }

        // Event listeners para melhor UX
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus no primeiro campo se o formulário estiver visível
            const form = document.getElementById('pix-form');
            if (form) {
                document.getElementById('apelido').focus();
            }
            
            // Validação em tempo real para nome
            const nomeTitular = document.getElementById('nome-titular');
            if (nomeTitular) {
                nomeTitular.addEventListener('input', function(e) {
                    // Remove números e caracteres especiais do nome
                    e.target.value = e.target.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '');
                });
            }
        });
    </script>

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