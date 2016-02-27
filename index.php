<?php
/*
   Copyright 2016 Cabibbo Inc.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

   This software is open and free for non-commercial use.
*/
ini_set("session.cookie_lifetime","28800");
session_set_cookie_params(28800);
session_start();

require_once 'db.php';

if ( $allowedIPList != '*' ){
   $allowedIPList = str_replace(' ','',$allowedIPList);
   $allowedIPArray = explode(',', $allowedIPList);
   $approved = 0;
   foreach($allowedIPArray as $ip){
      if ( $_SERVER['REMOTE_ADDR'] == $ip) { $approved = 1; }
   }
   if ( $approved == 0) { echo 'Access Denied'; exit(); }
}

if ( isset($_SESSION['login']) ){
   header( 'Location: sql1.php' );

} else if ( isset($_POST['username']) && isset($_POST['pwd']) && $_POST['username'] == $loginUsername && $_POST['pwd'] == $loginPassword){
   $_SESSION['login'] = 1;
   header( 'Location: sql1.php' );

} else {
   require_once 'viewClass.php';

   $view = new viewClass();

   $data = '';
   $dataHeader['pageTitle'] = $pageTitle;

   $view->getView('header.inc',$dataHeader);
   $view->getView('index.inc',$data);
   $view->getView('footer.inc',$dataHeader);
}?>