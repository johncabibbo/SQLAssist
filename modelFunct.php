<?php
require_once 'setting.php';
require_once 'xhr/CFDump.php';

if ( $allowedIPList != '*' ){
	$allowedIPList = str_replace(' ','',$allowedIPList);
	$allowedIPArray = explode(',', $allowedIPList);
	$approved = 0;
	foreach($allowedIPArray as $ip){
		if ( $_SERVER['REMOTE_ADDR'] == $ip) { $approved = 1; }
	}
	if ( $approved == 0) { echo 'Access Denied'; exit(); }
}

// Auto-login if user has valid DocInfo session
if ( !isset($_SESSION['login']) && isset($_SESSION['userId']) && $_SESSION['userId'] > 0 ){
	$_SESSION['login'] = 1;
	$date = new DateTime();
	$_SESSION['userSessionLogin'] = $date->format('Y-m-d H:i:s');
	$_SESSION['userSessionExpire'] = date("Y-m-d H:i:s", strtotime('+2851200 seconds'));
}

if ( !isset($_SESSION['login']) ){
	header( 'Location: index.php' );
	exit();

} else {

	require_once 'model/SQLStruct.php';
	require_once 'viewClass.php';

	$view = new viewClass();
	$data = array();

	if ( isset($_POST['modelSelected']) ){
		$_SESSION['modelSelected'] = $_POST['modelSelected'];
	}
	if ( !isset($_SESSION['modelSelected']) ){
		$_SESSION['modelSelected'] = '';
	}
	$data['modelSelected'] = $_SESSION['modelSelected'];

	$SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass, $mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
	$data['modelFunctionList'] = $SQLStructModel->modelFunctionList($_SESSION['modelSelected']);

	$data['modelList'] = $SQLStructModel->modelList();

	$dataHeader['pageTitle'] = $pageTitle.'-Models';
	if ($_SESSION['modelSelected'] != ''){
		$dataHeader['pageTitle'] .= '-'.$_SESSION['modelSelected'];
	}

	$view->getView('header.inc',$dataHeader);
	$view->getView('modelFunct.inc',$data);
	$view->getView('footer.inc',$dataHeader);

	// new CFDump($data);
}
?>