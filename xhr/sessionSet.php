<?php
/**
 * API: Save session variable
 *
 * Inputs:
 *   varName - Session variable name (dbSelected, tableSelected)
 *   varValue - Value to save
 */

require_once '../setting.php';

header('Content-Type: application/json');

$returnObject = array();

if (!isset($_SESSION['login'])) {
    $returnObject['success'] = '0';
    $returnObject['msg'] = 'Not logged in';
} elseif (!isset($_POST['varName']) || !isset($_POST['varValue'])) {
    $returnObject['success'] = '0';
    $returnObject['msg'] = 'Missing parameters';
} else {
    $varName = $_POST['varName'];
    $varValue = $_POST['varValue'];

    // Only allow specific session variables to be set
    $allowedVars = ['dbSelected', 'tableSelected'];

    if (in_array($varName, $allowedVars)) {
        $_SESSION[$varName] = $varValue;
        $returnObject['success'] = '1';
        $returnObject['msg'] = 'Session updated';
        $returnObject['varName'] = $varName;
        $returnObject['varValue'] = $varValue;
    } else {
        $returnObject['success'] = '0';
        $returnObject['msg'] = 'Invalid variable name';
    }
}

echo json_encode($returnObject);
?>
