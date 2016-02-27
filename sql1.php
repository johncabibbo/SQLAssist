<?php
ini_set("session.cookie_lifetime","28800");
session_set_cookie_params(28800);
session_start();

require_once 'db.php';

if ( !isset($_SESSION['dbSelected']) ){
	$_SESSION['dbSelected'] = '';
}

if ( !isset($_SESSION['tableSelected']) ){
	$_SESSION['tableSelected'] = '';
}

if ( $allowedIPList != '*' ){
	$allowedIPList = str_replace(' ','',$allowedIPList);
	$allowedIPArray = explode(',', $allowedIPList);
	$approved = 0;
	foreach($allowedIPArray as $ip){
		if ( $_SERVER['REMOTE_ADDR'] == $ip) { $approved = 1; }
	}
	if ( $approved == 0) { echo 'Access Denied'; exit(); }
}

if ( !isset($_SESSION['login']) ){
	header( 'Location: index.php' );
	exit();

} else {

	require_once 'model/SQLStruct.php';
	require_once 'viewClass.php';

	$view = new viewClass();

	$SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass);

	$data['databaseList'] = $SQLStructModel->databaseList();

	$dataHeader['pageTitle'] = $pageTitle;

	$view->getView('header.inc',$dataHeader);
	$view->getView('sql1.inc',$data);
	$view->getView('footer.inc',$dataHeader);
}
?>