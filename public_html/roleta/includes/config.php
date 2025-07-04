<?php
// /roleta/includes/config.php

// Para chamadas do index.php: ../bank/db.php
// Para chamadas da api/: ../../bank/db.php

if (file_exists('../bank/db.php')) {
    require '../bank/db.php';  // Chamado do index.php
} elseif (file_exists('../../bank/db.php')) {
    require '../../bank/db.php';  // Chamado da api/
} else {
    // Último recurso - caminho absoluto
    require '/storage/emulated/0/Download/telegram/finverpro.shop/public_html/bank/db.php';
}
?>