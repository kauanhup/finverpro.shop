<?php
session_start();

// Verificação de acesso (ajuste conforme seu sistema de admin)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: ../../login/');
    exit();
}

require '../../bank/db.php';

$message = "";
$messageType = "";

try {
    $conn = getDBConnection();
    
    // Buscar configuração atual
    $stmt = $conn->prepare("SELECT * FROM config_saques WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não existir configuração, criar uma padrão
    if (!$config) {
        $stmt = $conn->prepare("
            INSERT INTO config_saques (valor_minimo, taxa_percentual, limite_diario, horario_inicio, horario_fim, 
                                     segunda_feira, terca_feira, quarta_feira, quinta_feira, sexta_feira, sabado, domingo,
                                     requer_investimento_ativo, quantidade_min_investimentos, tempo_processamento_min, tempo_processamento_max,
                                     mensagem_fora_horario, mensagem_limite_diario, mensagem_sem_investimento, mensagem_saldo_insuficiente)
            VALUES (30.00, 8.00, 1, '09:00:00', '18:00:00', 1, 1, 1, 1, 1, 0, 0, 1, 1, 2, 24,
                   'Saques só podem ser realizados de segunda a sexta, das 9h às 18h.',
                   'Você já realizou um saque hoje. Limite de 1 saque por dia.',
                   'Você precisa ter pelo menos 1 investimento ativo antes de solicitar um saque.',
                   'Seu saldo é insuficiente para saque.')
        ");
        $stmt->execute();
        
        // Buscar a configuração recém-criada
        $stmt = $conn->prepare("SELECT * FROM config_saques WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validações básicas
            $valor_minimo = floatval($_POST['valor_minimo']);
            $valor_maximo = !empty($_POST['valor_maximo']) ? floatval($_POST['valor_maximo']) : null;
            $taxa_percentual = floatval($_POST['taxa_percentual']);
            
            if ($valor_minimo < 0) throw new Exception("Valor mínimo não pode ser negativo");
            if ($valor_maximo && $valor_maximo < $valor_minimo) throw new Exception("Valor máximo deve ser maior que o mínimo");
            if ($taxa_percentual < 0 || $taxa_percentual > 100) throw new Exception("Taxa deve estar entre 0% e 100%");
            
            // Atualizar configuração (CAMPOS SIMPLIFICADOS)
            $sql = "UPDATE config_saques SET 
                valor_minimo = :valor_minimo,
                valor_maximo = :valor_maximo,
                taxa_percentual = :taxa_percentual,
                taxa_fixa = :taxa_fixa,
                limite_diario = :limite_diario,
                limite_semanal = :limite_semanal,
                limite_mensal = :limite_mensal,
                horario_inicio = :horario_inicio,
                horario_fim = :horario_fim,
                segunda_feira = :segunda_feira,
                terca_feira = :terca_feira,
                quarta_feira = :quarta_feira,
                quinta_feira = :quinta_feira,
                sexta_feira = :sexta_feira,
                sabado = :sabado,
                domingo = :domingo,
                requer_investimento_ativo = :requer_investimento_ativo,
                quantidade_min_investimentos = :quantidade_min_investimentos,
                requer_chave_pix = :requer_chave_pix,
                tempo_processamento_min = :tempo_processamento_min,
                tempo_processamento_max = :tempo_processamento_max,
                mensagem_sucesso = :mensagem_sucesso,
                mensagem_fora_horario = :mensagem_fora_horario,
                mensagem_limite_diario = :mensagem_limite_diario,
                mensagem_sem_investimento = :mensagem_sem_investimento,
                mensagem_saldo_insuficiente = :mensagem_saldo_insuficiente,
                calculo_taxa = :calculo_taxa,
                aplicar_taxa_sobre = :aplicar_taxa_sobre,
                arredondar_centavos = :arredondar_centavos,
                permitir_mesmo_dia_deposito = :permitir_mesmo_dia_deposito,
                considerar_feriados = :considerar_feriados,
                bloquear_dezembro = :bloquear_dezembro,
                bloquear_janeiro = :bloquear_janeiro,
                atualizado_em = NOW(),
                atualizado_por = :atualizado_por
                WHERE id = :id";
                
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':valor_minimo' => $valor_minimo,
                ':valor_maximo' => $valor_maximo,
                ':taxa_percentual' => $taxa_percentual,
                ':taxa_fixa' => floatval($_POST['taxa_fixa']),
                ':limite_diario' => intval($_POST['limite_diario']),
                ':limite_semanal' => !empty($_POST['limite_semanal']) ? intval($_POST['limite_semanal']) : null,
                ':limite_mensal' => !empty($_POST['limite_mensal']) ? intval($_POST['limite_mensal']) : null,
                ':horario_inicio' => $_POST['horario_inicio'],
                ':horario_fim' => $_POST['horario_fim'],
                ':segunda_feira' => isset($_POST['segunda_feira']) ? 1 : 0,
                ':terca_feira' => isset($_POST['terca_feira']) ? 1 : 0,
                ':quarta_feira' => isset($_POST['quarta_feira']) ? 1 : 0,
                ':quinta_feira' => isset($_POST['quinta_feira']) ? 1 : 0,
                ':sexta_feira' => isset($_POST['sexta_feira']) ? 1 : 0,
                ':sabado' => isset($_POST['sabado']) ? 1 : 0,
                ':domingo' => isset($_POST['domingo']) ? 1 : 0,
                ':requer_investimento_ativo' => isset($_POST['requer_investimento_ativo']) ? 1 : 0,
                ':quantidade_min_investimentos' => intval($_POST['quantidade_min_investimentos']),
                ':requer_chave_pix' => isset($_POST['requer_chave_pix']) ? 1 : 0,
                ':tempo_processamento_min' => intval($_POST['tempo_processamento_min']),
                ':tempo_processamento_max' => intval($_POST['tempo_processamento_max']),
                ':mensagem_sucesso' => $_POST['mensagem_sucesso'],
                ':mensagem_fora_horario' => $_POST['mensagem_fora_horario'],
                ':mensagem_limite_diario' => $_POST['mensagem_limite_diario'],
                ':mensagem_sem_investimento' => $_POST['mensagem_sem_investimento'],
                ':mensagem_saldo_insuficiente' => $_POST['mensagem_saldo_insuficiente'],
                ':calculo_taxa' => $_POST['calculo_taxa'],
                ':aplicar_taxa_sobre' => $_POST['aplicar_taxa_sobre'],
                ':arredondar_centavos' => isset($_POST['arredondar_centavos']) ? 1 : 0,
                ':permitir_mesmo_dia_deposito' => isset($_POST['permitir_mesmo_dia_deposito']) ? 1 : 0,
                ':considerar_feriados' => isset($_POST['considerar_feriados']) ? 1 : 0,
                ':bloquear_dezembro' => isset($_POST['bloquear_dezembro']) ? 1 : 0,
                ':bloquear_janeiro' => isset($_POST['bloquear_janeiro']) ? 1 : 0,
                ':atualizado_por' => $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1,
                ':id' => $config['id']
            ]);
            
            $message = "Configurações de saque atualizadas com sucesso!";
            $messageType = "success";
            
            // Recarregar configuração atualizada
            $stmt = $conn->prepare("SELECT * FROM config_saques WHERE id = :id");
            $stmt->execute([':id' => $config['id']]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $message = "Erro ao atualizar: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
} catch (Exception $e) {
    $message = "Erro de conexão: " . $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações de Saque - Dashboard</title>
    
    <!-- CSS Dependencies -->
    <link rel="icon" href="../../assets/images/favicon.svg" type="image/x-icon" />
    <link rel="stylesheet" href="../../assets/fonts/inter/inter.css" id="main-font-link" />
    <link rel="stylesheet" href="../../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../../assets/fonts/feather.css" />
    <link rel="stylesheet" href="../../assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="../../assets/fonts/material.css" />
    <link rel="stylesheet" href="../../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="../../assets/css/style-preset.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr" data-pc-theme_contrast="" data-pc-theme="dark">

    <!-- Page Loader -->
    <div class="page-loader">
        <div class="bar"></div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="../../dashboard/" class="b-brand text-primary">
                    <img src="../../assets/images/logo-white.svg" alt="logo" class="logo logo-lg" />
                </a>
            </div>
            <div class="navbar-content">
                <ul class="pc-navbar">
                    <li class="pc-item">
                        <a href="../../dashboard/" class="pc-link">
                            <span class="pc-micon"><i class="ph-duotone ph-gauge"></i></span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="pc-item pc-caption">
                        <label>Configurações</label>
                    </li>
                    <li class="pc-item">
                        <a href="../" class="pc-link">
                            <span class="pc-micon"><i class="ph-duotone ph-gear-six"></i></span>
                            <span class="pc-mtext">Configurações Gerais</span>
                        </a>
                    </li>
                    <li class="pc-item active">
                        <a href="index.php" class="pc-link">
                            <span class="pc-micon"><i class="ph-duotone ph-currency-dollar"></i></span>
                            <span class="pc-mtext">Editar Saques</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <header class="pc-header">
        <div class="header-wrapper">
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="ms-auto">
                <ul class="list-unstyled">
                    <li class="dropdown pc-h-item">
                        <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
                            <i class="ph-duotone ph-user-circle"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
                            <a href="../../dashboard/" class="dropdown-item">
                                <i class="ph-duotone ph-gauge"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="#!" class="dropdown-item">
                                <i class="ph-duotone ph-sign-out"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="pc-container">
        <div class="pc-content">
            
            <!-- Breadcrumb -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../../dashboard/">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="../">Configurações</a></li>
                                <li class="breadcrumb-item" aria-current="page">Editar Saques</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0 animate__animated animate__fadeInDown">💰 Configurações de Saque</h2>
                                <p class="text-muted animate__animated animate__fadeInUp">Configure valores, horários e regras do sistema de saques</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="row justify-content-center">
                <div class="col-xl-12">
                    
                    <!-- Hero Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 hero-card-saque">
                                <div class="card-body text-center py-4">
                                    <div class="hero-icon mb-3">
                                        <i class="ph-duotone ph-currency-dollar f-40 text-warning"></i>
                                    </div>
                                    <h4 class="text-white mb-2">Sistema de Saque Simplificado</h4>
                                    <p class="text-white-75 mb-0">
                                        Configure valores, horários e regras básicas - validações complexas removidas para melhor performance
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="configForm">
                        <div class="row">
                            
                            <!-- SEÇÃO 1: VALORES E TAXAS -->
                            <div class="col-lg-6 mb-4">
                                <div class="card config-section animate__animated animate__fadeInLeft">
                                    <div class="card-header bg-gradient-warning">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-currency-dollar me-2"></i>
                                            Valores e Taxas
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Valor Mínimo (R$)</label>
                                                <input type="number" step="0.01" min="0" name="valor_minimo" 
                                                       class="form-control" value="<?= $config['valor_minimo'] ?>" required>
                                                <small class="text-muted">Valor mínimo para saque</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Valor Máximo (R$)</label>
                                                <input type="number" step="0.01" min="0" name="valor_maximo" 
                                                       class="form-control" value="<?= $config['valor_maximo'] ?>" placeholder="Sem limite">
                                                <small class="text-muted">Deixe vazio para sem limite</small>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Taxa Percentual (%)</label>
                                                <input type="number" step="0.01" min="0" max="100" name="taxa_percentual" 
                                                       class="form-control" value="<?= $config['taxa_percentual'] ?>" required>
                                                <small class="text-muted">Taxa em percentual (ex: 8.00 = 8%)</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Taxa Fixa (R$)</label>
                                                <input type="number" step="0.01" min="0" name="taxa_fixa" 
                                                       class="form-control" value="<?= $config['taxa_fixa'] ?>">
                                                <small class="text-muted">Taxa fixa adicional</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Tipo de Cálculo</label>
                                                <select name="calculo_taxa" class="form-select">
                                                    <option value="percentual" <?= $config['calculo_taxa'] == 'percentual' ? 'selected' : '' ?>>Apenas Percentual</option>
                                                    <option value="fixo" <?= $config['calculo_taxa'] == 'fixo' ? 'selected' : '' ?>>Apenas Fixo</option>
                                                    <option value="hibrido" <?= $config['calculo_taxa'] == 'hibrido' ? 'selected' : '' ?>>Percentual + Fixo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Aplicar Taxa Sobre</label>
                                                <select name="aplicar_taxa_sobre" class="form-select">
                                                    <option value="valor_bruto" <?= $config['aplicar_taxa_sobre'] == 'valor_bruto' ? 'selected' : '' ?>>Valor Bruto</option>
                                                    <option value="valor_liquido" <?= $config['aplicar_taxa_sobre'] == 'valor_liquido' ? 'selected' : '' ?>>Valor Líquido</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 2: LIMITES TEMPORAIS -->
                            <div class="col-lg-6 mb-4">
                                <div class="card config-section animate__animated animate__fadeInRight">
                                    <div class="card-header bg-gradient-info">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-clock me-2"></i>
                                            Limites Temporais
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label fw-bold">Limite Diário</label>
                                                <input type="number" min="1" name="limite_diario" 
                                                       class="form-control" value="<?= $config['limite_diario'] ?>" required>
                                                <small class="text-muted">Saques por dia</small>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label fw-bold">Limite Semanal</label>
                                                <input type="number" min="1" name="limite_semanal" 
                                                       class="form-control" value="<?= $config['limite_semanal'] ?>" placeholder="Sem limite">
                                                <small class="text-muted">Saques por semana</small>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label fw-bold">Limite Mensal</label>
                                                <input type="number" min="1" name="limite_mensal" 
                                                       class="form-control" value="<?= $config['limite_mensal'] ?>" placeholder="Sem limite">
                                                <small class="text-muted">Saques por mês</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Horário Início</label>
                                                <input type="time" name="horario_inicio" 
                                                       class="form-control" value="<?= $config['horario_inicio'] ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Horário Fim</label>
                                                <input type="time" name="horario_fim" 
                                                       class="form-control" value="<?= $config['horario_fim'] ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Processamento Min (horas)</label>
                                                <input type="number" min="1" name="tempo_processamento_min" 
                                                       class="form-control" value="<?= $config['tempo_processamento_min'] ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Processamento Max (horas)</label>
                                                <input type="number" min="1" name="tempo_processamento_max" 
                                                       class="form-control" value="<?= $config['tempo_processamento_max'] ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 3: DIAS DA SEMANA -->
                            <div class="col-lg-12 mb-4">
                                <div class="card config-section animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                                    <div class="card-header bg-gradient-primary">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-calendar me-2"></i>
                                            Dias da Semana Permitidos
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <?php 
                                            $dias = [
                                                'segunda_feira' => 'Segunda',
                                                'terca_feira' => 'Terça', 
                                                'quarta_feira' => 'Quarta',
                                                'quinta_feira' => 'Quinta',
                                                'sexta_feira' => 'Sexta',
                                                'sabado' => 'Sábado',
                                                'domingo' => 'Domingo'
                                            ];
                                            
                                            foreach ($dias as $campo => $nome): ?>
                                            <div class="col-md-auto mb-3">
                                                <div class="form-check form-switch day-switch">
                                                    <input class="form-check-input" type="checkbox" name="<?= $campo ?>" 
                                                           id="<?= $campo ?>" <?= $config[$campo] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="<?= $campo ?>">
                                                        <?= $nome ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 4: REQUISITOS -->
                            <div class="col-lg-6 mb-4">
                                <div class="card config-section animate__animated animate__fadeInLeft" style="animation-delay: 0.3s">
                                    <div class="card-header bg-gradient-success">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-shield-check me-2"></i>
                                            Requisitos Básicos
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="requer_investimento_ativo" 
                                                           id="requer_investimento_ativo" <?= $config['requer_investimento_ativo'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="requer_investimento_ativo">
                                                        Requer Investimento Ativo
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label fw-bold">Quantidade Mínima de Investimentos</label>
                                                <input type="number" min="1" name="quantidade_min_investimentos" 
                                                       class="form-control" value="<?= $config['quantidade_min_investimentos'] ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="requer_chave_pix" 
                                                           id="requer_chave_pix" <?= $config['requer_chave_pix'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="requer_chave_pix">
                                                        Requer Chave PIX
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 5: CONFIGURAÇÕES ESPECIAIS -->
                            <div class="col-lg-6 mb-4">
                                <div class="card config-section animate__animated animate__fadeInRight" style="animation-delay: 0.3s">
                                    <div class="card-header bg-gradient-danger">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-calendar-x me-2"></i>
                                            Configurações Especiais
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="arredondar_centavos" 
                                                           id="arredondar_centavos" <?= $config['arredondar_centavos'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="arredondar_centavos">
                                                        Arredondar Centavos no Valor Final
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="permitir_mesmo_dia_deposito" 
                                                           id="permitir_mesmo_dia_deposito" <?= $config['permitir_mesmo_dia_deposito'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="permitir_mesmo_dia_deposito">
                                                        Permitir Saque no Mesmo Dia do Depósito
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="considerar_feriados" 
                                                           id="considerar_feriados" <?= $config['considerar_feriados'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="considerar_feriados">
                                                        Considerar Feriados Nacionais
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="bloquear_dezembro" 
                                                           id="bloquear_dezembro" <?= $config['bloquear_dezembro'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="bloquear_dezembro">
                                                        Bloquear Saques em Dezembro
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="bloquear_janeiro" 
                                                           id="bloquear_janeiro" <?= $config['bloquear_janeiro'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label fw-bold" for="bloquear_janeiro">
                                                        Bloquear Saques em Janeiro
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 6: MENSAGENS PERSONALIZADAS -->
                            <div class="col-lg-12 mb-4">
                                <div class="card config-section animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
                                    <div class="card-header bg-gradient-secondary">
                                        <h5 class="text-white mb-0">
                                            <i class="ph-duotone ph-chat-text me-2"></i>
                                            Mensagens Personalizadas
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Mensagem de Sucesso</label>
                                                <textarea name="mensagem_sucesso" class="form-control" rows="3" 
                                                          placeholder="Mensagem exibida quando o saque é solicitado com sucesso"><?= htmlspecialchars($config['mensagem_sucesso'] ?? '') ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Mensagem Fora do Horário</label>
                                                <textarea name="mensagem_fora_horario" class="form-control" rows="3" 
                                                          placeholder="Mensagem quando fora do horário de funcionamento"><?= htmlspecialchars($config['mensagem_fora_horario'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Mensagem Limite Diário</label>
                                                <textarea name="mensagem_limite_diario" class="form-control" rows="3" 
                                                          placeholder="Mensagem quando limite diário atingido"><?= htmlspecialchars($config['mensagem_limite_diario'] ?? '') ?></textarea>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Mensagem Sem Investimento</label>
                                                <textarea name="mensagem_sem_investimento" class="form-control" rows="3" 
                                                          placeholder="Mensagem quando não tem investimento ativo"><?= htmlspecialchars($config['mensagem_sem_investimento'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Mensagem Saldo Insuficiente</label>
                                                <textarea name="mensagem_saldo_insuficiente" class="form-control" rows="3" 
                                                          placeholder="Mensagem quando saldo insuficiente"><?= htmlspecialchars($config['mensagem_saldo_insuficiente'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- BOTÕES DE AÇÃO -->
                            <div class="col-lg-12 mb-4">
                                <div class="card config-section animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
                                    <div class="card-body text-center">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <button type="button" class="btn btn-secondary btn-lg w-100" onclick="resetForm()">
                                                    <i class="ph-duotone ph-arrow-clockwise me-2"></i>
                                                    Resetar Formulário
                                                </button>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <button type="button" class="btn btn-info btn-lg w-100" onclick="previewChanges()">
                                                    <i class="ph-duotone ph-eye me-2"></i>
                                                    Visualizar Alterações
                                                </button>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <button type="submit" class="btn btn-success btn-lg w-100">
                                                    <i class="ph-duotone ph-floppy-disk me-2"></i>
                                                    Salvar Configurações
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>

                    <!-- Informações Úteis -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-0 info-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 text-center">
                                            <div class="info-icon">
                                                <i class="ph-duotone ph-info f-40 text-info"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <h5 class="text-info mb-2">💡 Sistema Simplificado</h5>
                                            <p class="mb-2">
                                                <strong>Performance:</strong> Validações complexas removidas para melhor velocidade.
                                            </p>
                                            <p class="mb-2">
                                                <strong>Segurança:</strong> Sistema baseado na confiança das chaves PIX cadastradas (máx. 3).
                                            </p>
                                            <p class="mb-0">
                                                <strong>Eficiência:</strong> Todas as alterações são aplicadas instantaneamente.
                                            </p>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="pulse-animation">
                                                <i class="ph-duotone ph-check-circle f-24 text-success"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botão Voltar -->
                    <div class="row mt-4 mb-5">
                        <div class="col-12 text-center">
                            <a href="../" class="btn btn-outline-light btn-lg back-btn animate__animated animate__fadeInUp" style="animation-delay: 0.6s;">
                                <i class="ph-duotone ph-arrow-left me-2"></i>
                                Voltar às Configurações
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/plugins/popper.min.js"></script>
    <script src="../../assets/js/plugins/simplebar.min.js"></script>
    <script src="../../assets/js/plugins/bootstrap.min.js"></script>
    <script src="../../assets/js/fonts/custom-font.js"></script>
    <script src="../../assets/js/pcoded.js"></script>
    <script src="../../assets/js/plugins/feather.min.js"></script>

    <script>
        layout_change('dark');

        // Função para resetar o formulário
        function resetForm() {
            Swal.fire({
                title: 'Resetar Formulário?',
                text: 'Todas as alterações não salvas serão perdidas!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, resetar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('configForm').reset();
                    Swal.fire('Resetado!', 'O formulário foi resetado.', 'success');
                }
            });
        }

        // Função para visualizar alterações
        function previewChanges() {
            const form = document.getElementById('configForm');
            const formData = new FormData(form);
            let changes = '<div class="text-start">';
            
            // Valores principais
            changes += `<h6 class="text-primary">💰 Valores e Taxas:</h6>`;
            changes += `<p>• Valor Mínimo: R$ ${formData.get('valor_minimo')}</p>`;
            changes += `<p>• Taxa Percentual: ${formData.get('taxa_percentual')}%</p>`;
            changes += `<p>• Tipo de Cálculo: ${formData.get('calculo_taxa')}</p>`;
            
            // Horários
            changes += `<h6 class="text-info mt-3">⏰ Horários:</h6>`;
            changes += `<p>• Funcionamento: ${formData.get('horario_inicio')} às ${formData.get('horario_fim')}</p>`;
            changes += `<p>• Limite Diário: ${formData.get('limite_diario')} saque(s)</p>`;
            
            // Dias da semana
            const dias = ['segunda_feira', 'terca_feira', 'quarta_feira', 'quinta_feira', 'sexta_feira', 'sabado', 'domingo'];
            const nomes = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
            const diasAtivos = [];
            
            dias.forEach((dia, index) => {
                if (formData.has(dia)) {
                    diasAtivos.push(nomes[index]);
                }
            });
            
            changes += `<h6 class="text-success mt-3">📅 Dias Permitidos:</h6>`;
            changes += `<p>• ${diasAtivos.join(', ')}</p>`;
            
            // Requisitos
            changes += `<h6 class="text-warning mt-3">🔒 Requisitos:</h6>`;
            changes += `<p>• Investimento Ativo: ${formData.has('requer_investimento_ativo') ? 'Sim' : 'Não'}</p>`;
            changes += `<p>• Chave PIX: ${formData.has('requer_chave_pix') ? 'Obrigatória' : 'Opcional'}</p>`;
            
            changes += '</div>';

            Swal.fire({
                title: '👀 Visualizar Alterações',
                html: changes,
                icon: 'info',
                confirmButtonText: 'Entendi',
                customClass: {
                    popup: 'swal-wide'
                }
            });
        }

        // Validação do formulário
        document.getElementById('configForm').addEventListener('submit', function(e) {
            const valorMinimo = parseFloat(document.querySelector('input[name="valor_minimo"]').value);
            const valorMaximo = parseFloat(document.querySelector('input[name="valor_maximo"]').value);
            const taxaPercentual = parseFloat(document.querySelector('input[name="taxa_percentual"]').value);

            if (valorMaximo && valorMaximo < valorMinimo) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Validação',
                    text: 'O valor máximo deve ser maior que o valor mínimo.'
                });
                return;
            }

            if (taxaPercentual < 0 || taxaPercentual > 100) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erro de Validação',
                    text: 'A taxa percentual deve estar entre 0% e 100%.'
                });
                return;
            }

            // Verificar se pelo menos um dia da semana está selecionado
            const diasSelecionados = document.querySelectorAll('input[type="checkbox"][name$="_feira"], input[type="checkbox"][name="sabado"], input[type="checkbox"][name="domingo"]');
            let algumDiaSelecionado = false;
            
            diasSelecionados.forEach(dia => {
                if (dia.checked) {
                    algumDiaSelecionado = true;
                }
            });

            if (!algumDiaSelecionado) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Selecione pelo menos um dia da semana para permitir saques.'
                });
                return;
            }

            // Confirmar antes de salvar
            e.preventDefault();
            Swal.fire({
                title: '💾 Salvar Configurações?',
                text: 'As novas configurações entrarão em vigor imediatamente.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sim, salvar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });

        // Atualizar preview de cálculos em tempo real
        function calcularPreview() {
            const valorMin = document.querySelector('input[name="valor_minimo"]');
            const taxaPerc = document.querySelector('input[name="taxa_percentual"]');
            const taxaFixa = document.querySelector('input[name="taxa_fixa"]');
            const tipoCalculo = document.querySelector('select[name="calculo_taxa"]');
            
            if (valorMin && taxaPerc) {
                [valorMin, taxaPerc, taxaFixa].forEach(input => {
                    if (input) input.addEventListener('input', atualizarPreview);
                });
                if (tipoCalculo) tipoCalculo.addEventListener('change', atualizarPreview);
            }
        }

        function atualizarPreview() {
            // Implementar preview de cálculo em tempo real (opcional)
            console.log('Preview atualizado');
        }

        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            calcularPreview();
        });
    </script>

    <!-- SweetAlert Messages -->
    <?php if ($message): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: '<?= $messageType == "success" ? "success" : "error" ?>',
                title: '<?= $messageType == "success" ? "Sucesso!" : "Erro!" ?>',
                text: '<?= addslashes($message) ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
    <?php endif; ?>

    <style>
        /* Gradientes personalizados */
        .bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
        .bg-gradient-danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .bg-gradient-secondary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        /* Hero card */
        .hero-card-saque {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 20px;
            overflow: hidden;
        }

        /* Config sections */
        .config-section {
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .config-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* Day switches */
        .day-switch .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }

        /* Info card */
        .info-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Pulse animation */
        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Back button */
        .back-btn {
            border-radius: 50px;
            padding: 15px 30px;
            border: 2px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            color: #ffffff;
            text-decoration: none;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.7);
            transform: translateY(-2px);
            color: #ffffff;
            text-decoration: none;
        }

        /* SweetAlert custom width */
        .swal-wide {
            width: 600px !important;
        }

        /* Text utilities */
        .text-white-75 {
            color: rgba(255,255,255,0.75);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .config-section {
                margin-bottom: 20px;
            }
            
            .hero-card-saque .card-body {
                padding: 30px 20px;
            }
        }
    </style>

</body>
</html>