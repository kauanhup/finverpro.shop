<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../');
    exit();
}

require '../bank/db.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Erro de conexão: " . $e->getMessage());
}

function verificarConvidados($userId, $conn) {
    try {
        // CORREÇÃO: Campo mantido
        $stmt = $conn->prepare("SELECT codigo_referencia FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Usuário não encontrado.");
        }

        $codigoReferencia = $user['codigo_referencia'];

        // CORREÇÃO: Campo 'referencia_convite' mudou para 'referenciado_por'
        // Obtém os usuários que foram indicados por este usuário (Nível 1)
        $stmt = $conn->prepare("SELECT id, codigo_referencia FROM usuarios WHERE referenciado_por = ?");
        $stmt->execute([$userId]);
        $convidadosNivel1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $quantidadeConvidadosNivel1 = count($convidadosNivel1);
        $quantidadeConvidadosValidadosNivel1 = 0;
        $totalComissaoNivel1 = 0;

        $quantidadeConvidadosNivel2 = 0;
        $quantidadeConvidadosValidadosNivel2 = 0;
        $totalComissaoNivel2 = 0;

        foreach ($convidadosNivel1 as $convidadoNivel1) {
            $convidadoId = $convidadoNivel1['id'];

            // CORREÇÃO: Tabela 'pagamentos' → 'operacoes_financeiras'
            // Calcula a soma dos depósitos aprovados para cada convidado de Nível 1
            $stmt = $conn->prepare("SELECT valor_liquido FROM operacoes_financeiras WHERE usuario_id = ? AND status = 'aprovado' AND tipo = 'deposito'");
            $stmt->execute([$convidadoId]);
            $pagamentosAprovados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pagamentosAprovados as $pagamento) {
                $comissao = $pagamento['valor_liquido'] * 0.10; // 10% conforme configuração padrão
                $totalComissaoNivel1 += $comissao;
                $quantidadeConvidadosValidadosNivel1++; // Conta cada pagamento aprovado como validado
            }

            // CORREÇÃO: Obtém e valida os convidados de Nível 2 usando 'referenciado_por'
            $stmtNivel2 = $conn->prepare("SELECT id FROM usuarios WHERE referenciado_por = ?");
            $stmtNivel2->execute([$convidadoId]);
            $convidadosNivel2 = $stmtNivel2->fetchAll(PDO::FETCH_ASSOC);

            $quantidadeConvidadosNivel2 += count($convidadosNivel2);

            foreach ($convidadosNivel2 as $convidadoNivel2) {
                $convidadoIdNivel2 = $convidadoNivel2['id'];

                // CORREÇÃO: Tabela 'pagamentos' → 'operacoes_financeiras'
                // Calcula a soma dos depósitos aprovados para cada convidado de Nível 2
                $stmt = $conn->prepare("SELECT valor_liquido FROM operacoes_financeiras WHERE usuario_id = ? AND status = 'aprovado' AND tipo = 'deposito'");
                $stmt->execute([$convidadoIdNivel2]);
                $pagamentosAprovadosNivel2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($pagamentosAprovadosNivel2 as $pagamentoNivel2) {
                    $comissaoNivel2 = $pagamentoNivel2['valor_liquido'] * 0.06; // 6% conforme configuração padrão
                    $totalComissaoNivel2 += $comissaoNivel2;
                    $quantidadeConvidadosValidadosNivel2++; // Conta cada pagamento aprovado como validado
                }
            }
        }

        // Atualizar ou inserir registro na tabela de comissões (se existir)
        try {
            // Verificar se existe registro na tabela niveis_convite
            $stmt = $conn->prepare("SELECT COUNT(*) FROM niveis_convite WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existeRegistro = $stmt->fetchColumn() > 0;

            if ($existeRegistro) {
                // Atualiza o registro existente
                $stmt = $conn->prepare("UPDATE niveis_convite SET nivel_1 = ?, total_nivel1 = ?, nivel_2 = ?, total_nivel2 = ? WHERE user_id = ?");
                $stmt->execute([$quantidadeConvidadosNivel1, $totalComissaoNivel1, $quantidadeConvidadosNivel2, $totalComissaoNivel2, $userId]);
            } else {
                // Insere um novo registro se não existir
                $stmt = $conn->prepare("INSERT INTO niveis_convite (user_id, nivel_1, total_nivel1, nivel_2, total_nivel2) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $quantidadeConvidadosNivel1, $totalComissaoNivel1, $quantidadeConvidadosNivel2, $totalComissaoNivel2]);
            }
        } catch (Exception $e) {
            // Tabela niveis_convite pode não existir, continuar
            error_log("Erro ao atualizar niveis_convite: " . $e->getMessage());
        }

        // CORREÇÃO: Atualizar saldo de comissão na tabela 'carteiras'
        $saldoTotalComissao = $totalComissaoNivel1 + $totalComissaoNivel2;
        $stmt = $conn->prepare("UPDATE carteiras SET saldo_comissao = ? WHERE usuario_id = ?");
        $stmt->execute([$saldoTotalComissao, $userId]);

        return [
            'quantidadeConvidadosNivel1' => $quantidadeConvidadosNivel1,
            'quantidadeConvidadosValidadosNivel1' => $quantidadeConvidadosValidadosNivel1,
            'totalComissaoNivel1' => $totalComissaoNivel1,
            'quantidadeConvidadosNivel2' => $quantidadeConvidadosNivel2,
            'quantidadeConvidadosValidadosNivel2' => $quantidadeConvidadosValidadosNivel2,
            'totalComissaoNivel2' => $totalComissaoNivel2,
            'totalConvidados' => $quantidadeConvidadosNivel1 + $quantidadeConvidadosNivel2,
            'totalComissao' => $totalComissaoNivel1 + $totalComissaoNivel2,
            'totalConvidadosValidados' => $quantidadeConvidadosValidadosNivel1 + $quantidadeConvidadosValidadosNivel2
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = verificarConvidados($_SESSION['user_id'], $conn);

    // Verifica se há erro
    if (isset($resultado['error'])) {
        // Caso haja erro, você pode redirecionar para uma página de erro ou exibir uma mensagem personalizada
        header('Location: ./');
        exit();
    } else {
        // Se não houver erro, redireciona de volta para a página inicial (./)
        header('Location: ./');
        exit();
    }
}
?>