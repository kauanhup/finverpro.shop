<?php
/**
 * SCRIPT DE MIGRAÇÃO AUTOMÁTICA
 * FinverPro.shop - Migração do Banco Antigo para Estrutura Organizada
 * 
 * ATENÇÃO: Execute este script apenas UMA VEZ e em ambiente de teste primeiro!
 */

set_time_limit(0); // Remove limite de tempo
ini_set('memory_limit', '512M'); // Aumenta limite de memória

// Configurações do banco
$config = [
    'host' => 'localhost',
    'dbname' => 'meu_site',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    // Conectar ao banco
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    echo "🔗 Conectado ao banco de dados com sucesso!\n\n";
    
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage() . "\n");
}

/**
 * Função para executar SQL com log
 */
function executarSQL($pdo, $sql, $descricao) {
    echo "🔄 Executando: $descricao\n";
    try {
        $result = $pdo->exec($sql);
        echo "✅ Sucesso: $descricao\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ Erro em '$descricao': " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Função para fazer backup
 */
function fazerBackup($config) {
    echo "\n📦 Criando backup do banco atual...\n";
    
    $backupFile = 'backup_finverpro_' . date('Y-m-d_H-i-s') . '.sql';
    $command = "mysqldump -h{$config['host']} -u{$config['username']} ";
    
    if (!empty($config['password'])) {
        $command .= "-p{$config['password']} ";
    }
    
    $command .= "{$config['dbname']} > $backupFile";
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile)) {
        echo "✅ Backup criado: $backupFile\n";
        return $backupFile;
    } else {
        echo "❌ Erro ao criar backup\n";
        return false;
    }
}

/**
 * Função para migrar dados de uma tabela para outra
 */
function migrarDados($pdo, $tabelaOrigem, $tabelaDestino, $mapeamento, $condicao = '') {
    echo "\n🔄 Migrando dados: $tabelaOrigem → $tabelaDestino\n";
    
    // Verificar se tabela origem existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tabelaOrigem]);
    if (!$stmt->fetch()) {
        echo "⚠️  Tabela $tabelaOrigem não existe, pulando...\n";
        return;
    }
    
    // Construir SQL de migração
    $campos = implode(', ', array_keys($mapeamento));
    $valores = implode(', ', array_values($mapeamento));
    
    $sql = "INSERT IGNORE INTO $tabelaDestino ($campos) 
            SELECT $valores FROM $tabelaOrigem";
    
    if (!empty($condicao)) {
        $sql .= " WHERE $condicao";
    }
    
    try {
        $affected = $pdo->exec($sql);
        echo "✅ Migrados $affected registros de $tabelaOrigem\n";
    } catch (PDOException $e) {
        echo "❌ Erro na migração: " . $e->getMessage() . "\n";
    }
}

// ===========================================
// INÍCIO DA MIGRAÇÃO
// ===========================================

echo "🚀 INICIANDO MIGRAÇÃO DO FINVERPRO.SHOP\n";
echo "======================================\n\n";

// 1. FAZER BACKUP
if (php_sapi_name() === 'cli') {
    $backupFile = fazerBackup($config);
    if (!$backupFile) {
        echo "❌ Não foi possível criar backup. Abortando migração.\n";
        exit(1);
    }
}

// 2. CRIAR ESTRUTURA NOVA
echo "\n📊 Criando nova estrutura do banco...\n";

// Verificar se já existe estrutura nova
$stmt = $pdo->prepare("SHOW TABLES LIKE 'dashboard_stats'");
$stmt->execute();
if ($stmt->fetch()) {
    echo "⚠️  Nova estrutura já existe. Pulando criação...\n";
} else {
    
    // Ler e executar o arquivo de reestruturação
    if (file_exists('reestruturacao_completa_banco.sql')) {
        $sql = file_get_contents('reestruturacao_completa_banco.sql');
        
        // Dividir em comandos individuais
        $comandos = explode(';', $sql);
        
        foreach ($comandos as $comando) {
            $comando = trim($comando);
            if (!empty($comando) && !preg_match('/^(--|\s*$)/', $comando)) {
                try {
                    $pdo->exec($comando);
                } catch (PDOException $e) {
                    // Ignora erros de tabelas que já existem
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "❌ Erro: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "✅ Estrutura base criada\n";
    }
    
    // Executar extensões do admin
    if (file_exists('tabelas_admin_extras.sql')) {
        $sql = file_get_contents('tabelas_admin_extras.sql');
        
        // Dividir em comandos
        $comandos = explode(';', $sql);
        
        foreach ($comandos as $comando) {
            $comando = trim($comando);
            if (!empty($comando) && !preg_match('/^(--|\s*$)/', $comando)) {
                try {
                    $pdo->exec($comando);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "❌ Erro: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "✅ Extensões do admin criadas\n";
    }
}

// 3. MIGRAR DADOS DAS TABELAS ANTIGAS
echo "\n📂 Iniciando migração de dados...\n";

// Migrar usuários (se necessário ajustar estrutura)
migrarDados($pdo, 'usuarios', 'usuarios', [
    'nome' => 'nome',
    'telefone' => 'telefone', 
    'senha' => 'senha',
    'cargo' => 'cargo',
    'status' => 'COALESCE(status, "ativo")',
    'created_at' => 'COALESCE(data_criacao, NOW())'
]);

// Migrar investimentos consolidados
migrarDados($pdo, 'investidores', 'investimentos', [
    'usuario_id' => 'id_usuario',
    'produto_id' => 'produto_investido',
    'valor_investido' => 'COALESCE(renda_total, 0)',
    'renda_diaria' => 'COALESCE(renda_diaria, 0)',
    'dias_restantes' => '30',
    'status' => '"ativo"',
    'created_at' => 'COALESCE(data_investimento, NOW())'
]);

// Migrar carteiras (criar se não existir)
echo "\n💰 Criando carteiras para usuários...\n";
$sql = "INSERT IGNORE INTO carteiras (usuario_id, saldo_principal, saldo_bonus, saldo_comissao, created_at)
        SELECT id, 0, 0, 0, NOW() FROM usuarios";
$pdo->exec($sql);
echo "✅ Carteiras criadas\n";

// Migrar transações de depósitos
migrarDados($pdo, 'pagamentos', 'transacoes', [
    'usuario_id' => 'usuario_id',
    'tipo' => '"deposito"',
    'valor' => 'valor',
    'status' => 'CASE WHEN status = "Aprovado" THEN "concluido" ELSE "pendente" END',
    'metodo_pagamento' => 'COALESCE(gateway, "pix")',
    'created_at' => 'COALESCE(data, NOW())'
]);

// Migrar transações de saques
migrarDados($pdo, 'saques', 'transacoes', [
    'usuario_id' => 'usuario_id',
    'tipo' => '"saque"',
    'valor' => 'valor',
    'status' => 'CASE WHEN status = "Aprovado" THEN "concluido" ELSE "pendente" END',
    'metodo_pagamento' => '"pix"',
    'dados_pagamento' => 'JSON_OBJECT("chave_pix", COALESCE(chave_pix, ""))',
    'created_at' => 'COALESCE(data, NOW())'
]);

// Migrar configurações
echo "\n⚙️  Migrando configurações...\n";
$configsAntigos = [
    ['sistema', 'nome_site', 'FinverPro'],
    ['sistema', 'logo_url', '/assets/logo.png'],
    ['financeiro', 'valor_minimo_saque', '10'],
    ['financeiro', 'valor_minimo_deposito', '10'],
    ['sistema', 'modo_manutencao', '0']
];

foreach ($configsAntigos as $config) {
    $sql = "INSERT IGNORE INTO configuracoes (categoria, chave, valor) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($config);
}
echo "✅ Configurações básicas criadas\n";

// 4. ATUALIZAR ESTATÍSTICAS
echo "\n📊 Atualizando estatísticas...\n";
try {
    $pdo->exec("CALL AtualizarDashboardStats()");
    echo "✅ Estatísticas atualizadas\n";
} catch (PDOException $e) {
    echo "⚠️  Erro ao atualizar estatísticas: " . $e->getMessage() . "\n";
}

// 5. CRIAR USUÁRIO ADMIN PADRÃO
echo "\n👤 Verificando usuário admin...\n";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE cargo IN ('admin', 'super_admin')");
$stmt->execute();
$adminCount = $stmt->fetchColumn();

if ($adminCount == 0) {
    echo "🔑 Criando usuário admin padrão...\n";
    $sql = "INSERT INTO usuarios (nome, telefone, senha, cargo, admin_role_id, created_at) 
            VALUES ('Super Admin', '11999999999', MD5('admin123'), 'super_admin', 1, NOW())";
    $pdo->exec($sql);
    echo "✅ Usuário admin criado - Login: 11999999999 | Senha: admin123\n";
} else {
    echo "✅ Usuário admin já existe\n";
}

// 6. LIMPAR TABELAS ANTIGAS (OPCIONAL)
echo "\n🗑️  Deseja remover tabelas antigas? (s/N): ";
if (php_sapi_name() === 'cli') {
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) === 's') {
        $tabelasAntigas = [
            'investidores', 'historico_transacoes', 'bonus_codigos', 
            'bonus_resgatados', 'saques_comissao'
        ];
        
        foreach ($tabelasAntigas as $tabela) {
            $sql = "DROP TABLE IF EXISTS $tabela";
            if (executarSQL($pdo, $sql, "Removendo tabela $tabela")) {
                echo "🗑️  Tabela $tabela removida\n";
            }
        }
    }
}

// ===========================================
// RELATÓRIO FINAL
// ===========================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 MIGRAÇÃO CONCLUÍDA COM SUCESSO!\n";
echo str_repeat("=", 50) . "\n\n";

// Estatísticas finais
echo "📊 ESTATÍSTICAS DA MIGRAÇÃO:\n";
echo "----------------------------\n";

$stats = [
    'usuarios' => "SELECT COUNT(*) FROM usuarios",
    'carteiras' => "SELECT COUNT(*) FROM carteiras", 
    'investimentos' => "SELECT COUNT(*) FROM investimentos",
    'transacoes' => "SELECT COUNT(*) FROM transacoes",
    'configuracoes' => "SELECT COUNT(*) FROM configuracoes",
    'admin_roles' => "SELECT COUNT(*) FROM admin_roles",
    'admin_permissions' => "SELECT COUNT(*) FROM admin_permissions"
];

foreach ($stats as $nome => $query) {
    try {
        $count = $pdo->query($query)->fetchColumn();
        echo sprintf("%-15s: %d registros\n", ucfirst($nome), $count);
    } catch (PDOException $e) {
        echo sprintf("%-15s: Erro ao contar\n", ucfirst($nome));
    }
}

echo "\n✅ PRÓXIMOS PASSOS:\n";
echo "1. Substitua o dashboard antigo pelo novo arquivo\n";
echo "2. Teste todas as funcionalidades do admin\n";
echo "3. Configure as permissões dos usuários admin\n";
echo "4. Personalize as configurações do sistema\n";

if (isset($backupFile)) {
    echo "\n💾 BACKUP SALVO EM: $backupFile\n";
    echo "⚠️  Mantenha o backup em local seguro!\n";
}

echo "\n🚀 Seu FinverPro.shop agora tem um banco organizado e admin moderno!\n\n";

?>