<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>SQLAssist</title>
<script type="text/javascript" src="http://code.jquery.com/jquery-latest.min.js" charset="utf-8"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>
<link type="text/css" rel="stylesheet" href="css/main.css" />
<link type="text/css" rel="stylesheet" href="css/forms.css" />
</head>
<body>

<div id="mainContent">
	<div class="logo">
		<img src="image/SQLAssistLogoSm.png" id="logo">
	</div>
	<div class="logout">
		<a href="logout.php">Logout</a>
	</div>

	<div id="errorMsg">
		There was an error in communicating with your MySQL database.<br>
		Please check your configuration settings in the db.php file.<br><br>
		<a href="sql1.php">Click here to try again</a>
	</div>
	
</div>
<footer>
	<p>Copyright &copy; <?php echo date("Y"); ?> Cabibbo Inc.</p>
</footer>
</body>
</html>