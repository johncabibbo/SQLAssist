<?php
/* Settings - Use shared session configuration */
require_once 'setting.php';
require_once 'viewClass.php';
require_once 'xhr/CFDump.php'; 

$view = new viewClass();

$data = array();

?>

<style>
.dump{ font-size:1.0rem; }
.dump th{ font-size:1.0rem; }
.dump td{ font-size:1.0rem; }
#wrapperCenter1 { 
	min-width: 300px; 
	width: 80%;
	margin-left: 10%;
	margin-top: 30px;
	margin-bottom: 50px;
	padding:  15px;
	border: 4px solid #63b8ff;

	font-family: 'Montserrat',sans-serif;
	font-weight: 400;
	font-size: 17px;
	line-height: 28px;
}
</style>

<?php
echo "<h3>Session Debug Info</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Cookie Domain:</strong> " . (ini_get('session.cookie_domain') ?: '(not set)') . "</p>";
echo "<p><strong>HTTP Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "</p>";
echo "<p><strong>Session Cookie Params:</strong></p>";
echo "<pre>";
print_r(session_get_cookie_params());
echo "</pre>";
echo "<h3>Session Variables</h3>";
new CFDump($_SESSION);
?>