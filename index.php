<?php
/*
   SQL Assist

   Copyright © 2026 Cloud Box 9 Inc. All rights reserved.

   This software is proprietary and confidential. Unauthorized copying,
   distribution, or use of this file, via any medium, is strictly prohibited.
   See the LICENSE file for full terms.
*/
require_once 'setting.php';

if ( $allowedIPList != '*' ){
	$allowedIPList = str_replace(' ','',$allowedIPList);
	$allowedIPArray = explode(',', $allowedIPList);
	$approved = 0;
	foreach($allowedIPArray as $ip){
		if ( $_SERVER['REMOTE_ADDR'] == $ip) { $approved = 1; }
	}
	if ( $approved == 0) { echo 'Access Denied'; exit(); }
}

// Check if already logged into SQL Assist
if ( isset($_SESSION['login']) ){
	header( 'Location: sql1.php' );
	exit();

// Auto-login if user has valid DocInfo session
} else if ( isset($_SESSION['userId']) && $_SESSION['userId'] > 0 ){
	$_SESSION['login'] = 1;
	$date = new DateTime();
	$_SESSION['userSessionLogin'] = $date->format('Y-m-d H:i:s');
	$_SESSION['userSessionExpire'] = date("Y-m-d H:i:s", strtotime('+2851200 seconds'));
	header( 'Location: sql1.php' );
	exit();

// Manual login with username/password
} else if ( isset($_POST['username']) && isset($_POST['pwd']) && $_POST['username'] == $loginUsername && $_POST['pwd'] == $loginPassword){
	$_SESSION['login'] = 1;
	$date = new DateTime();
	$_SESSION['userSessionLogin'] = $date->format('Y-m-d H:i:s');
	$_SESSION['userSessionExpire'] = date("Y-m-d H:i:s", strtotime('+2851200 seconds'));
	header( 'Location: sql1.php' );
	exit();

} else {
	require_once 'viewClass.php';

	$view = new viewClass();

	$data = '';
	$dataHeader['pageTitle'] = $pageTitle;

	$view->getView('header.inc',$dataHeader);
	$view->getView('index.inc',$data);
	$view->getView('footer.inc',$dataHeader);
}?>