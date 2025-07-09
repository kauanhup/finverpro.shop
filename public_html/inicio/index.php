<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

// Incluir o arquivo de conexão com o banco de dados
require '../bank/db.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// ===========================================
// BUSCAR DADOS DO USUÁRIO
// ===========================================
$stmt = $pdo->prepare("SELECT id, nome, telefone, codigo_referencia, nivel_vip, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$nome_usuario = $usuario['nome'] ?? 'Usuário';
$codigo_referencia = $usuario['codigo_referencia'] ?? '';
$nivel_vip = $usuario['nivel_vip'] ?? 'V0';
$foto_perfil = $usuario['foto_perfil'] ?? null;

// ===========================================
// BUSCAR SALDO DA CARTEIRA
// ===========================================
$stmt = $pdo->prepare("SELECT saldo_principal, saldo_bonus, saldo_comissao, total_investido FROM carteiras WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$carteira = $stmt->fetch(PDO::FETCH_ASSOC);

$saldo_principal = $carteira['saldo_principal'] ?? 0;
$saldo_bonus = $carteira['saldo_bonus'] ?? 0;
$saldo_comissao = $carteira['saldo_comissao'] ?? 0;
$total_investido = $carteira['total_investido'] ?? 0;

// Calcular patrimônio total
$patrimonio_total = $saldo_principal + $saldo_bonus + $saldo_comissao;

// ===========================================
// BUSCAR CONFIGURAÇÕES DO SISTEMA
// ===========================================
$configuracoes = [];
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE categoria IN ('sistema', 'financeiro', 'afiliacao')");
while ($config = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $configuracoes[$config['chave']] = $config['valor'];
}

// Definir valores padrão
$nome_site = $configuracoes['nome_site'] ?? 'FinverPro';
$url_site = $configuracoes['url_site'] ?? 'https://finverpro.shop';
$email_contato = $configuracoes['email_contato'] ?? 'contato@finverpro.shop';
$telefone_suporte = $configuracoes['telefone_suporte'] ?? 'https://t.me/finverpro';

// ===========================================
// BUSCAR PERSONALIZAÇÃO (CORES, IMAGENS)
// ===========================================
$personalizacao = [];
$stmt = $pdo->query("SELECT categoria, elemento, valor FROM personalizacao");
while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $personalizacao[$item['categoria']][$item['elemento']] = $item['valor'];
}

// Cores padrão
$cores = [
    'cor_1' => $personalizacao['cores']['cor_1'] ?? '#121A1E',
    'cor_2' => $personalizacao['cores']['cor_2'] ?? 'white',
    'cor_3' => $personalizacao['cores']['cor_3'] ?? '#152731',
    'cor_4' => $personalizacao['cores']['cor_4'] ?? '#335D67',
    'cor_5' => $personalizacao['cores']['cor_5'] ?? '#152731',
];

// Imagens
$logo = $personalizacao['imagens']['logo'] ?? '3.png';
$background_inicio = $personalizacao['imagens']['inicio'] ?? '2.jpg';

// ===========================================
// BUSCAR INVESTIMENTOS DO USUÁRIO
// ===========================================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_investimentos,
        COALESCE(SUM(valor_investido), 0) as valor_total_investido,
        COALESCE(SUM(rendimento_acumulado), 0) as rendimento_total
    FROM investimentos 
    WHERE usuario_id = ? AND status = 'ativo'
");
$stmt->execute([$user_id]);
$investimentos_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_investimentos = $investimentos_stats['total_investimentos'] ?? 0;
$valor_total_investido = $investimentos_stats['valor_total_investido'] ?? 0;
$rendimento_total = $investimentos_stats['rendimento_total'] ?? 0;

// ===========================================
// BUSCAR PRODUTOS EM DESTAQUE
// ===========================================
$stmt = $pdo->query("
    SELECT 
        id, titulo, valor_minimo, rendimento_diario, 
        tipo_rendimento, categoria, codigo_produto
    FROM produtos 
    WHERE status = 'ativo' 
    ORDER BY vendidos DESC 
    LIMIT 3
");
$produtos_destaque = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===========================================
// BUSCAR DADOS DA REDE DE AFILIAÇÃO
// ===========================================
$stmt = $pdo->prepare("
    SELECT 
        total_indicacoes_diretas, 
        total_volume_equipe, 
        total_comissoes
    FROM rede_afiliacao 
    WHERE usuario_id = ?
");
$stmt->execute([$user_id]);
$rede_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_indicacoes = $rede_stats['total_indicacoes_diretas'] ?? 0;
$volume_equipe = $rede_stats['total_volume_equipe'] ?? 0;
$total_comissoes = $rede_stats['total_comissoes'] ?? 0;

// ===========================================
// INCLUIR TEMPLATE HTML
// ===========================================
include 'template.html';
?>