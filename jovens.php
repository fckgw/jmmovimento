<?php
/**
 * JMM SYSTEM - REDIRECIONAMENTO DE MÓDULO
 * Este arquivo apenas redireciona o usuário para a aba correspondente na gincana.php
 */

// Captura os parâmetros da URL (como busca, página, etc)
$query_string = $_SERVER['QUERY_STRING'];

// Monta o destino para a aba de jovens
$destino = "gincana.php?tab=jovens";

// Se houver parâmetros (p=2, f_jovem=Felipe), anexa eles ao link
if (!empty($query_string)) {
    $destino .= "&" . $query_string;
}

// Redireciona
header("Location: " . $destino);
exit;