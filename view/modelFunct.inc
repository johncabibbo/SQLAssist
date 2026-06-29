<link type="text/css" rel="stylesheet" href="css/main.css" />
<link type="text/css" rel="stylesheet" href="css/forms.css" />
</head>
<body>

<div id="mainContent">
	<div class="logo">
		<img src="image/SQLAssistLogoSm.png" id="logo">
	</div>

	<div class="logout">
		<a href="sql1.php">SQL</a> - 
		<a href="modelFunct.php">ModelFunct</a> - 
		<a href="logout.php">Logout</a>
	</div>
	<div id="countDownExp"></div>
	<form id="modelForm" action="?" method="post">
	<div id="rDatabase" class="row1">
		<select id="modelSelected" name="modelSelected">
			<option value="">Choose a Model</option>
			<?php foreach ($data['modelList'] as $r) {
				$s = '';
				if ( $data['modelSelected'] == $r['model'] ){ $s = 'selected';}; 
				echo '<option value="'.$r['model'].'" '.$s.' >'.$r['model'].'</option>';
			} ?>
		</select>
		<span id="showAll"> Show All </span>
		<span id="hideAll"> Hide All </span>
		<spam id="sync">Force Sync</span>
	</div>
	</form>

	<div id="modelFunctContent">
	<?php
		// new CFDump($data['modelFunctionList']);
		// <input type="text" value="" readonly="" style="color:gray">
		foreach ( $data['modelFunctionList'] as $mf ){
			echo '<div class="modelFunctWrapper">';

			echo '<div class="modelFunctSimple" id="modelFunctSimple_'.$mf['docModelFunctionId'].'">';

			echo '<div class="modelLabel modelFuntA" data-id="'.$mf['docModelFunctionId'].'">Model:</div>';
			echo '<div class="modelValue">'.trim($mf['model']).'</div>';
			
			echo '<div class="functNameLabel">Function:</div>';
			echo '<div class="functNameValue">'.htmlspecialchars(trim($mf['functName'])).'</div>';

			echo '</div>';


			echo '<div class="modelFunctEditable" id="modelFunctEditable_'.$mf['docModelFunctionId'].'">';

			echo '<div class="modelLabel modelFuntB" data-id="'.$mf['docModelFunctionId'].'">Model:</div>';
			echo '<div class="modelValue"><input type="text" value="'.trim($mf['model']).'" disabled style="color:gray"></div>';
			
			echo '<div class="functNameLabel modelFuntB">Function:</div>';
			echo '<div class="functNameValue"><input type="text" value="'.htmlspecialchars(trim($mf['functName'])).'" disabled style="color:gray"></div>';

			echo '<div class="functLabel">Full Function:</div>';
			echo '<div class="functValue"><input type="text" value="'.htmlspecialchars(trim($mf['funct'])).'" disabled style="color:gray"></div>';
			
			echo '<div class="functDescLabel">&nbsp;</div>';
			echo '<div class="functDescValue">';
			echo '<textarea class="modelFunctionDesc" id="modelFunction_'.trim($mf['docModelFunctionId']).'" data-id="'.trim($mf['docModelFunctionId']).'" data-gramm="false" >'.$mf['functDesc'].'</textarea></div>';

			echo '</div></div>';
		}
	?>
	</div>
	<input type="hidden" id="userSessionLogin" value="<?php echo $_SESSION['userSessionLogin'] ?>">
	<input type="hidden" id="userSessionExpire" value="<?php echo $_SESSION['userSessionExpire'] ?>">
</div>
<script type="text/javascript" src="js/modelFunct1.js"></script>
<script type="text/javascript" src="js/countDown.js"></script>
<style type="text/css">
	input:disabled, textarea:disabled {
	    filter: progid:DXImageTransform.Microsoft.Alpha(Opacity=20);
	    opacity: 1;
	}
</style>