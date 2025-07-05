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

// CORREÇÃO: Buscar chaves PIX usando 'usuario_id' em vez de 'user_id'
$stmt = $pdo->prepare("SELECT * FROM chaves_pix WHERE usuario_id = ? AND status = 'ativo' ORDER BY ativa DESC, created_at DESC");
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

        .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--blur-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--secondary-color);
            color: white;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
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

        .container > * {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../perfil/" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="header-title">Chaves PIX</h1>
        </div>

        <!-- Banner -->
        <div class="banner-section">
            <img src="../../assets/images/banners/<?= htmlspecialchars($tela_pix) ?>" alt="PIX Banner" class="banner-image">
        </div>

        <!-- Status das Chaves -->
        <div class="status-card">
            <p class="status-text">
                <i class="fas fa-key"></i>
                Você tem <span class="status-counter"><?= $totalChaves ?>/3</span> chaves cadastradas
            </p>
        </div>

        <!-- Lista de Chaves Existentes -->
        <?php if (!empty($chavesExistentes)): ?>
        <div class="chaves-list">
            <div class="chaves-header">
                <h2 class="chaves-title">
                    <i class="fas fa-list"></i>
                    Suas Chaves PIX
                </h2>
                <button class="toggle-btn" onclick="toggleChaves()">
                    <i class="fas fa-eye" id="toggle-icon"></i>
                    <span id="toggle-text">Mostrar</span>
                </button>
            </div>

            <div class="chaves-container" id="chaves-container">
                <?php foreach ($chavesExistentes as $chave): ?>
                <div class="chave-card <?= $chave['ativa'] ? 'ativa' : '' ?>">
                    <div class="chave-header">
                        <div class="chave-tipo">
                            <i class="fas fa-<?= $chave['tipo'] === 'cpf' ? 'id-card' : ($chave['tipo'] === 'email' ? 'envelope' : ($chave['tipo'] === 'celular' ? 'phone' : 'key')) ?>"></i>
                            <?= strtoupper($chave['tipo']) ?>
                        </div>
                        <?php if ($chave['ativa']): ?>
                        <span class="badge-ativa">✅ Ativa para Saques</span>
                        <?php endif; ?>
                    </div>

                    <div class="chave-info">
                        <p><strong>Chave:</strong> <?= htmlspecialchars($chave['chave']) ?></p>
                        <p><strong>Titular:</strong> <?= htmlspecialchars($chave['nome_titular']) ?></p>
                        <?php if ($chave['apelido']): ?>
                        <p><strong>Apelido:</strong> <?= htmlspecialchars($chave['apelido']) ?></p>
                        <?php endif; ?>
                        <p><strong>Cadastrada em:</strong> <?= date('d/m/Y', strtotime($chave['created_at'])) ?></p>
                    </div>

                    <?php if (!$chave['ativa']): ?>
                    <div class="chave-actions">
                        <button class="btn-action btn-ativar" onclick="ativarChave(<?= $chave['id'] ?>)">
                            <i class="fas fa-check"></i>
                            Ativar para Saques
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="chave-permanente">
                        <i class="fas fa-check-circle"></i>
                        Esta é sua chave ativa para saques! Ela será usada para receber seus saques.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulário para Nova Chave -->
        <?php if ($podeAdicionar): ?>
        <div class="form-card">
            <h2 class="form-title">
                <i class="fas fa-plus-circle"></i>
                Adicionar Nova Chave PIX
            </h2>

            <form id="form-chave-pix">
                <div class="form-group">
                    <label class="form-label">Tipo da Chave PIX</label>
                    <select name="tipo_pix" id="tipo_pix" class="form-select" required>
                        <option value="">Selecione o tipo</option>
                        <option value="cpf">CPF</option>
                        <option value="email">E-mail</option>
                        <option value="celular">Celular</option>
                        <option value="chave_aleatoria">Chave Aleatória</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Chave PIX</label>
                    <input type="text" name="chave_pix" id="chave_pix" class="form-input" required placeholder="Digite sua chave PIX">
                </div>

                <div class="form-group">
                    <label class="form-label">Nome do Titular</label>
                    <input type="text" name="nome_titular" id="nome_titular" class="form-input" required placeholder="Nome completo do titular">
                </div>

                <div class="form-group">
                    <label class="form-label">Apelido (Opcional)</label>
                    <input type="text" name="apelido" id="apelido" class="form-input" placeholder="Ex: Conta Principal, Poupança...">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i>
                    Adicionar Chave PIX
                </button>
            </form>

            <div class="aviso-permanente">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Apenas uma chave pode ficar ativa para saques por vez. As chaves são permanentes após o cadastro.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="form-card">
            <h2 class="form-title">
                <i class="fas fa-info-circle"></i>
                Limite Atingido
            </h2>
            <div class="aviso-permanente">
                <i class="fas fa-ban"></i>
                <p>Você já possui 3 chaves PIX cadastradas. Este é o limite máximo permitido.</p>
            </div>
        </div>
        <?php endif; ?>
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

    <script>
        // Função para mostrar/esconder chaves
        function toggleChaves() {
            const container = document.getElementById('chaves-container');
            const icon = document.getElementById('toggle-icon');
            const text = document.getElementById('toggle-text');
            
            if (container.classList.contains('expanded')) {
                container.classList.remove('expanded');
                icon.className = 'fas fa-eye';
                text.textContent = 'Mostrar';
            } else {
                container.classList.add('expanded');
                icon.className = 'fas fa-eye-slash';
                text.textContent = 'Ocultar';
            }
        }

        // Função para ativar chave
        async function ativarChave(chaveId) {
            try {
                const result = await Swal.fire({
                    title: 'Ativar Chave PIX?',
                    text: 'Esta chave será usada para seus saques. Deseja continuar?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10B981',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: 'Sim, ativar',
                    cancelButtonText: 'Cancelar'
                });

                if (result.isConfirmed) {
                    const response = await fetch('gerenciar_chaves.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=ativar&chave_id=${chaveId}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            title: 'Sucesso!',
                            text: 'Esta chave agora será usada para seus saques.',
                            icon: 'success',
                            confirmButtonColor: '#10B981'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Erro ao ativar chave');
                    }
                }
            } catch (error) {
                Swal.fire({
                    title: 'Erro!',
                    text: error.message || 'Erro ao ativar chave',
                    icon: 'error',
                    confirmButtonColor: '#EF4444'
                });
            }
        }

        // Formulário de nova chave
        document.getElementById('form-chave-pix').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'adicionar');

            try {
                const response = await fetch('gerenciar_chaves.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: 'Chave PIX adicionada com sucesso!',
                        icon: 'success',
                        confirmButtonColor: '#10B981'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Erro ao adicionar chave');
                }
            } catch (error) {
                Swal.fire({
                    title: 'Erro!',
                    text: error.message || 'Erro ao adicionar chave',
                    icon: 'error',
                    confirmButtonColor: '#EF4444'
                });
            }
        });

        // Formatação dinâmica da chave PIX
        document.getElementById('tipo_pix').addEventListener('change', function() {
            const chaveInput = document.getElementById('chave_pix');
            const tipo = this.value;
            
            switch(tipo) {
                case 'cpf':
                    chaveInput.placeholder = '000.000.000-00';
                    chaveInput.maxLength = 14;
                    break;
                case 'email':
                    chaveInput.placeholder = 'exemplo@email.com';
                    chaveInput.maxLength = 255;
                    break;
                case 'celular':
                    chaveInput.placeholder = '+5511999999999';
                    chaveInput.maxLength = 15;
                    break;
                case 'chave_aleatoria':
                    chaveInput.placeholder = 'Cole aqui sua chave aleatória';
                    chaveInput.maxLength = 255;
                    break;
                default:
                    chaveInput.placeholder = 'Digite sua chave PIX';
                    chaveInput.maxLength = 255;
            }
            chaveInput.value = '';
        });
    </script>
</body>
</html>