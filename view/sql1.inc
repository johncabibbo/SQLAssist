<link type="text/css" rel="stylesheet" href="css/main.css" />
<link type="text/css" rel="stylesheet" href="css/forms.css" />
</head>
<body>

<div id="pageHeader">
	<div class="logo">
		<img src="/image/SQLAssistLogoSm.png" id="logo">
	</div>
	<div class="logout">
		<!--
		<a href="sql1.php" id="SQLMode" class="button-link selectedItem">SQL</a>
		<a href="modelFunct.php" id="ModelFunctMode" class="button-link" target="_blank">ModelFunct</a>
		<a href="logout.php">Logout</a>-->
	</div>
	<button id="darkModeToggle" title="Toggle Dark Mode">🌙 Dark</button>
	<div id="countDownExp" style="text-align: center;"></div>
	<div id="rDatabase" class="row1">
		<select id="databaseName" name="databaseName" style="width:150px;">
			<option value="0">Choose a Database</option>
			<?php foreach ($data['databaseList'] as $db) {
				echo '<option value="'.$db['databaseName'].'">'.$db['databaseName'].'</option>';
			} ?>
		</select>
	</div>

	<div id="rTable" class="row1 rowHide">
		<select id="tableName" name="tableName" style="width:280px;">
			<option value="0">Choose a Table</option>
			<option value="ALL">ALL Tables + Columns</option>
			<option value="ALLTables">Table List</option>
		</select>
		<span style="display:inline-block; vertical-align:middle; margin-left:6px;">
			<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/b_arrowLeft.png"
				id="btnTablePrev" class="tableNavBtn" title="Previous Table"
				style="width:30px; height:30px; cursor:pointer; vertical-align:middle; opacity:0.8;">
			<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/b_arrowRight.png"
				id="btnTableNext" class="tableNavBtn" title="Next Table"
				style="width:30px; height:30px; cursor:pointer; vertical-align:middle; margin-left:2px; opacity:0.8;">
		</span>
		<span class="tooltip" style="margin-left: 40px;">
			<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-process-64.png" id="btnMarkLookupTables" class="toolbarBtn" style="width:30px; height:30px; cursor:pointer; vertical-align:middle; opacity:0.7;">
			<span class="tooltiptext" style="width:320px; text-align:left; padding:10px;">
				<strong>Apply Settings</strong> — 3-step database-wide operation:<br><br>
				<strong>1.</strong> Mark tables as Lookup type<br>
				<strong>2.</strong> Auto-fill blank column comments (PKs, FKs, standard cols)<br>
				<strong>3.</strong> Append value samples to each lookup table comment<br><br>
				<strong>Example — After running:</strong><br>
				<code style="display:block;background:#1e293b;color:#7dd3fc;padding:5px 7px;border-radius:4px;font-size:11px;margin-top:4px;white-space:pre-wrap;">Table comment:
  Lookup Table
  Values: 1-Active, 2-Inactive...

Column comments:
  id          → Auto-Assigned Primary Key
  deleted     → 0=Active 1=Deleted
  displayOrder→ Sort order (asc)</code>
			</span>
		</span>
		<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-help-64.png"
			id="btnToolbarHelp" title="Toolbar Button Help"
			style="width:30px; height:30px; cursor:pointer; vertical-align:middle; margin-left:8px; opacity:0.7;">
	</div>
	<div id="countDisplay" style="font-size:12px; display: block; float: left; margin: 16px 10px; height: auto; width: 120px;">
	</div>
	<div style="clear:both;"></div>
</div><!-- /#pageHeader -->

<div id="mainContent">

	<!-- Snackbar Notification -->
	<div id="snackbar"></div>

	<!-- Confirmation Modal -->
	<div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
		<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; min-width:400px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
			<h3 id="confirmModalTitle" style="margin:0 0 15px 0; color:#1e293b;">Confirm Action</h3>
			<p id="confirmModalMessage" style="margin:0 0 20px 0; color:#64748b;"></p>
			<div style="text-align:right;">
				<button id="confirmModalCancel" style="padding:8px 20px; margin-right:10px; background:#e2e8f0; border:none; border-radius:4px; cursor:pointer;">Cancel</button>
				<button id="confirmModalContinue" style="padding:8px 20px; background:#0ea5e9; color:white; border:none; border-radius:4px; cursor:pointer;">Continue</button>
			</div>
		</div>
	</div>

	<!-- Toolbar Help Modal -->
	<div id="toolbarHelpModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); z-index:1100;">
		<div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px 34px; border-radius:10px; width:580px; max-width:95vw; max-height:85vh; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,0.35);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:2px solid #e2e8f0; padding-bottom:12px;">
				<h2 style="margin:0; color:#1e293b; font-size:1.4rem;">Toolbar Button Reference</h2>
				<button id="toolbarHelpClose" style="background:none; border:none; font-size:1.6rem; cursor:pointer; color:#64748b; line-height:1;">&#x2715;</button>
			</div>

			<div style="margin-bottom:24px;">
				<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
					<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-process-64.png" style="width:30px; height:30px;">
					<strong style="color:#1e293b; font-size:1.1rem;">Apply Settings</strong>
					<span style="font-size:0.85rem; color:#64748b;">(runs on the whole database)</span>
				</div>
				<p style="margin:0 0 6px 0; color:#374151;">A two-step operation that prepares your database for use with this tool:</p>
				<p style="margin:0 0 4px 16px; color:#374151;"><strong>Step 1 — Mark Lookup Tables:</strong> Scans all tables in the selected database. Any table whose comment starts with <em>"Lookup Table"</em> or whose type is set to <em>Lookup</em> is tagged as a lookup table. This drives the <code>*</code> indicator in the table dropdown and the lookup-specific features (icon gallery, missing-column check, etc.).</p>
				<p style="margin:8px 0 4px 16px; color:#374151;"><strong>Step 2 — Auto-Fill All Column Comments:</strong> Iterates every table in the database and fills in missing column comments using standard rules. Always fills: <code>title, description, abr, deletable, icon, createdDate, createdBy, lastModifiedDate, lastModifiedBy, deleted, displayOrder, displayOrderGroup, displayClass, userId, orgId</code>. Also fills blank primary keys (<em>"Auto-Assigned Primary Key"</em>) and foreign keys (<em>"Foreign Key to TABLE.COLUMN"</em>).</p>
				<p style="margin:8px 0 4px 16px; color:#374151;"><strong>Step 3 — Append Column Info (all lookup tables):</strong> For every lookup table found in Step 1, reads the first two columns and builds a compact values list which is appended to the table comment (e.g. <em>1-Active, 2-Inactive, 3-Pending…</em>). This is the same operation as the <strong>Append Column Info</strong> button, applied to all lookup tables at once.</p>
			</div>

			<div style="margin-bottom:24px;">
				<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
					<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-module-64.png" style="width:30px; height:30px;">
					<strong style="color:#1e293b; font-size:1.1rem;">Append Column Info</strong>
					<span style="font-size:0.85rem; color:#64748b;">(lookup tables only — current table)</span>
				</div>
				<p style="margin:0 0 6px 0; color:#374151;">Reads the <strong>first two columns</strong> of the current lookup table (typically the ID and title columns) and queries all rows to build a compact value list. That list is then appended to the table's comment field in the format:</p>
				<p style="margin:6px 0 6px 20px; font-family:monospace; font-size:0.9rem; background:#f1f5f9; padding:6px 10px; border-radius:4px; color:#0f172a;">Values: 1-Active, 2-Inactive, 3-Pending, 4-Archived, 5-Deleted…</p>
				<p style="margin:0; color:#374151;">Useful for documenting what values a lookup table contains directly in its comment so it's visible in the table list view. If a values block already exists it is replaced. The operation only runs on the currently selected table.</p>
			</div>

			<div style="margin-bottom:8px;">
				<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
					<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-detail-color-96.png" style="width:30px; height:30px;">
					<strong style="color:#1e293b; font-size:1.1rem;">Auto-Fill Column Comments</strong>
					<span style="font-size:0.85rem; color:#64748b;">(current table only)</span>
				</div>
				<p style="margin:0 0 6px 0; color:#374151;">Fills in missing column comments for the currently selected table using these rules:</p>
				<ul style="margin:0 0 0 16px; color:#374151; line-height:1.9;">
					<li><strong>Primary key columns</strong> — always set to <em>"Auto-Assigned Primary Key"</em> if not already set</li>
					<li><strong>Foreign key columns</strong> — always set to <em>"Foreign Key to TABLE.COLUMN"</em> if not already set</li>
					<li><strong>Standard columns</strong> (createdDate, deleted, displayOrder, userId, etc.) — always filled with canonical descriptions</li>
					<li><strong>All other blank columns</strong> — filled with a generic description derived from the column name</li>
					<li><strong>Columns that already have a comment</strong> — never overwritten</li>
				</ul>
			</div>

			<div style="text-align:right; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
				<button id="toolbarHelpClose2" style="padding:8px 24px; background:#0ea5e9; color:white; border:none; border-radius:6px; cursor:pointer; font-size:1rem;">Close</button>
			</div>
		</div>
	</div>

	<input type="hidden" id="userSessionLogin" value="<?php echo $_SESSION['userSessionLogin'] ?>">
	<input type="hidden" id="userSessionExpire" value="<?php echo $_SESSION['userSessionExpire'] ?>">
	<input type="hidden" id="dbSelected" value="<?php echo $_SESSION['dbSelected'] ?>">
	<input type="hidden" id="tableSelected" value="<?php echo $_SESSION['tableSelected'] ?>">

	<div id="SQLContent"></div>
</div>
<script type="text/javascript" src="js/sql1.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'].'/js/sql1.js'); ?>"></script>
<script type="text/javascript" src="js/countDown.js"></script>
<script type="text/javascript" src="js/darkMode.js"></script>
