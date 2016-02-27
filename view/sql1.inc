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

	<div id="rDatabase" class="row1">
		<select id="databaseName" name="databaseName">
			<option value="0">Choose a Database</option>
			<?php foreach ($data['databaseList'] as $db) {
				echo '<option value="'.$db['databaseName'].'">'.$db['databaseName'].'</option>';
			} ?>
		</select>
	</div>

	<div id="rTable" class="row1 rowHide">
		<select id="tableName" name="tableName">
			<option value="0">Choose a Table</option>
		</select>
	</div>

	<input type="hidden" id="dbSelected" value="<?php echo $_SESSION['dbSelected'] ?>">
	<input type="hidden" id="tableSelected" value="<?php echo $_SESSION['tableSelected'] ?>">

	<div id="SQLContent"></div>
</div>
<script type="text/javascript" src="js/sql1.js"></script>