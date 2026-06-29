<?php
require_once '../setting.php';

$returnObject = array();
$returnObject['success'] = '1';

// https://mbr.teamfl.org/xhr/docModelFunctionAddUpdate.php
$handle = curl_init();
$url = "https://mbr.teamfl.org/xhr/docModelFunctionAddUpdate.php";
 
// Set the url
curl_setopt($handle, CURLOPT_URL, $url);

// Set the result output to be a string.
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

// curl_setopt($handle, CURLINFO_HEADER_OUT, true);
 
curl_exec($handle);

$output = curl_getinfo($handle);
 
curl_close($handle);

$returnObject['curlOutput'] = $output;

echo json_encode($returnObject);
?>