// Store table comments globally for lookup table detection
var tableComments = {};

function adjustContentMargin() {
	var h = $('#pageHeader').outerHeight(true);
	$('#mainContent').css('margin-top', h + 'px');
}
$(document).ready(function() {
	adjustContentMargin();
	$(window).on('resize', adjustContentMargin);
});
// Store table stats globally (keyed by tableName)
var tableStatsAll = {};

// Core table type list — merged with any distinct values from the DB at runtime
var tableTypeCoreList = ['Archive','Data','Lookup','Other','Stock/Standard','System','Temp','Unknown'];

// Full table list cached on DB load so the dropdown can be rebuilt for type filters
var tableListCache = [];
var tableTypeAllCache = {};
var totalTableCount = 0;

function updateCountDisplay(currentCount) {
	var html = 'Total Tables: ' + totalTableCount;
	if (currentCount !== null) { html = 'Current Table: ' + currentCount + '<br>' + html; }
	$('#countDisplay').html(html);
}

// Returns true if a table's type matches the active type filter.
// BLANK matches tables where tableType is null/empty/undefined.
function tableMatchesTypeFilter(tType, typeFilter) {
	if (typeFilter === 'BLANK') { return !tType; }
	return tType === typeFilter;
}

function buildTableDropdown(typeFilter) {
	var $sel = $('#tableName');
	var currentVal = $sel.val();
	$sel.empty();

	if (!typeFilter) {
		// Full dropdown
		$sel.append($('<option>', { value: '0', text: 'Choose a Table' }));
		//$sel.append($('<option>', { value: 'ALL', text: 'ALL Tables + Columns' }));
	}
	$sel.append($('<option>', { value: 'ALLTables', text: 'Table List' }));
	$sel.append($('<option>', { value: 'DEPRECATED', text: 'Deprecated' }));
	$sel.append($('<option>', { value: 'SEP1', text: '----------------------' }));
	$.each(['Data','Lookup','Archive','Unknown','BLANK'], function(i, t) {
		$sel.append($('<option>', { value: 'TYPE:' + t, text: 'Type:' + t }));
	});
	$sel.append($('<option>', { value: 'ICON:With',    text: 'With Icons' }));
	$sel.append($('<option>', { value: 'ICON:Without', text: 'Without Icons' }));
	$sel.append($('<option>', { value: 'SEP2', text: '----------------------' }));
	$sel.append($('<optgroup>', { label: '── Tables ──────────────────' }));

	$.each(tableListCache, function() {
		if (typeFilter && !tableMatchesTypeFilter(tableTypeAllCache[this['tableName']], typeFilter)) { return true; }
		var displayName = this['tableName'];
		var comment = tableComments[this['tableName']] || '';
		if (comment.toLowerCase().indexOf('lookup table') === 0) { displayName += ' *'; }
		var tStats = tableStatsAll[this['tableName']];
		if (tStats) { displayName += '  (' + Number(tStats.rowCount).toLocaleString() + ' rows, ' + tStats.sizeMB + ' MB)'; }
		$sel.append($('<option>', { value: this['tableName'], text: displayName, 'data-tablename': this['tableName'] }));
	});

	// Restore selection
	if (currentVal) { $sel.val(currentVal); }
}

function buildTableTypeOpts(dbList) {
	var merged = tableTypeCoreList.slice();
	if (dbList && dbList.length) {
		$.each(dbList, function(i, v) {
			if (v && merged.indexOf(v) === -1) { merged.push(v); }
		});
	}
	merged.sort();
	return merged;
}

// =============================================================================
// Table Prev / Next Navigation
// =============================================================================

// Returns the list of navigable option values (actual table names only)
function getNavigableOptions() {
	var skip = { '0': 1, 'ALLTables': 1, 'ALL': 1, 'DEPRECATED': 1, 'SEP1': 1, 'SEP2': 1 };
	var opts = [];
	$('#tableName option').each(function() {
		var v = $(this).val();
		if (skip[v] || v.indexOf('TYPE:') === 0 || v.indexOf('ICON:') === 0 || v.indexOf('---') === 0 || $(this).text().indexOf('──') !== -1) return;
		opts.push(v);
	});
	return opts;
}

function navigateTable(dir) {
	var opts = getNavigableOptions();
	if (!opts.length) return;
	var current = $('#tableName').val();
	var idx = opts.indexOf(current);
	var next;
	if (idx === -1) {
		next = (dir > 0) ? opts[0] : opts[opts.length - 1];
	} else {
		next = opts[idx + dir];
		if (!next) next = (dir > 0) ? opts[0] : opts[opts.length - 1]; // wrap around
	}
	$('#tableName').val(next);
	tableUpdated();
}

$(document).on('click', '#btnTablePrev', function() { navigateTable(-1); });
$(document).on('click', '#btnTableNext', function() { navigateTable(1); });

$(document).on('mouseenter', '.tableNavBtn', function() { $(this).css('opacity', '1'); });
$(document).on('mouseleave', '.tableNavBtn', function() { $(this).css('opacity', '0.8'); });

// =============================================================================
// Refresh Table Counts
// =============================================================================
function refreshTableCounts() {
	var DBID = $('#databaseName').val();
	if (DBID && DBID != '0') {
		$.ajax({
			type: 'post',
			url: 'xhr/tableList.php',
			dataType: 'json',
			data: { databaseName: DBID },
			success: function(data) {
				if (data['success'] == 1) {
					totalTableCount = data['tableCount'];
				}
			}
		});
	}
}

$('#databaseName').on('change',function(e) {
	console.log("Database Changed");
	databaseUpdated(0);
});

databaseUpdated = function(TP){
	DBID = $('#databaseName').val();
	$('#dbSelected').val(DBID);

	// Save to session
	$.ajax({
		type: 'post',
		url: 'xhr/sessionSet.php',
		dataType: 'json',
		data: {
			varName: 'dbSelected',
			varValue: DBID
		}
	});

	if ( DBID != 0 ) {
		$.ajax({
			type:'post',
			url: 'xhr/tableList.php',
			dataType: 'json',
			data: {
				databaseName:DBID
			},
			success:function(data){
				//console.log(data);

				if (data['success'] == 1){
					$('#rTable').show();

					// Store table comments and stats globally
					tableComments = data['tableComments'] || {};
					tableStatsAll = data['tableStatsAll'] || {};
					tableListCache = data['tableList'] || [];
					tableTypeAllCache = {};

					buildTableDropdown(null);

					$("#SQLContent").html('');

					if (TP == 1){
						console.log('TP=1')
						y = $('#tableSelected').val();
						console.log('tableSelected: '+y)
						if (y != ''){
							// console.log('2')
							$('#tableName').val(y);
							tableUpdated();
						}
					}

					// Set Counts
					totalTableCount = data['tableCount'];
					updateCountDisplay(null);

				} else {
					alert("Invalid API Call");
				}
			},
			fail:function(data){ 
				console.log(data);
				alert("Error");
			}
		})
	} else {
		$('#rTable').hide();
	}
};

$('#tableName').on('change',function(e) {
	console.log("Table Changed");
	var val = $(this).val();
	if (val === 'SEP1' || val === 'SEP2') {
		// Auto-advance to the option immediately after the separator
		var $opts = $(this).find('option');
		var idx = $opts.index($opts.filter(':selected'));
		if (idx + 1 < $opts.length) {
			$(this).val($opts.eq(idx + 1).val());
		}
	}
	tableUpdated();
});

tableUpdated = function(){
	DBID = $('#databaseName').val();
	TBID = $('#tableName').val();
	TBNM = $("#tableName option:selected").text();

	// Remove * suffix if present (lookup table indicator)
	if (TBNM.endsWith(' *')) {
		TBNM = TBNM.slice(0, -2);
	}

	console.log('TBID: '+TBID);

	$('#tableSelected').val(TBID);

	// Save to session
	$.ajax({
		type: 'post',
		url: 'xhr/sessionSet.php',
		dataType: 'json',
		data: {
			varName: 'tableSelected',
			varValue: TBID
		}
	});

	// Refresh table counts
	refreshTableCounts();

	if ( DBID != 0 && TBID != 0) {

		// ICON: filter — show lookup tables with/without icon values
		if (TBID.indexOf('ICON:') === 0) {
			var iconType = TBID.substring(5); // 'With' or 'Without'
			$('#SQLContent').html('<p style="color:#888;">Loading icon data…</p>');
			$.ajax({
				type: 'post',
				url: 'xhr/iconFilter.php',
				dataType: 'json',
				data: { databaseName: DBID },
				success: function(idata) {
					var filterList = (iconType === 'With')
						? (idata.tablesWithIcons    || [])
						: (idata.tablesWithoutIcons || []);
					var label = (iconType === 'With') ? 'LOOKUP TABLES — WITH ICONS' : 'LOOKUP TABLES — WITHOUT ICONS';
					var tlb = '<p><span class="header1">' + label + '</span></p>';
					tlb += '<p>';
					if (filterList.length > 0) {
						$.each(filterList, function(i, tbl) {
							// Open in a new browser tab — uses an <a target="_blank"> anchor
							// so Cmd/Ctrl/middle-click all work naturally too.
							tlb += '<a class="tbl-name-link-newtab" target="_blank" rel="noopener"'
							    +  ' href="sql1.php?tableSelected=' + encodeURIComponent(tbl) + '"'
							    +  ' style="color:#0ea5e9; text-decoration:underline; margin-right:20px; display:inline-block; margin-bottom:6px;">'
							    +  tbl + '</a>';
						});
					} else {
						tlb += '<em style="color:#888;">No matching lookup tables found.</em>';
					}
					tlb += '</p>';
					$('#SQLContent').html(tlb);
				},
				error: function() {
					$('#SQLContent').html('<p style="color:#dc2626;">Error loading icon data.</p>');
				}
			});
			return;
		}

		// TYPE: and DEPRECATED options are virtual — call ALLTables endpoint
		var isDeprecated = (TBID === 'DEPRECATED');
		var apiTableId = (TBID.indexOf('TYPE:') === 0 || isDeprecated) ? 'ALLTables' : TBID;
		var typeFilter  = (TBID.indexOf('TYPE:') === 0) ? TBID.substring(5) : null;

		$.ajax({
			type:'post',
			url: 'xhr/tableInfo.php',
			dataType: 'json',
			data: {
				databaseName:DBID,
				tableId:apiTableId,
				tableName:TBNM
			},
			success:function(data){
				//console.log(data);
				if (data['success'] == 1){

					// If a type filter was applied, update cache and rebuild dropdown
					if (typeFilter && data['tableTypeAll']) {
						tableTypeAllCache = data['tableTypeAll'];
						buildTableDropdown(typeFilter);
						$('#tableName').val(TBID);
					} else if (isDeprecated) {
						// Deprecated filter — no dropdown rebuild needed
					} else if (!typeFilter && TBID !== 'ALL' && TBID !== 'ALLTables') {
						// Specific table selected — restore full dropdown
						tableTypeAllCache = {};
						buildTableDropdown(null);
						$('#tableName').val(TBID);
					}

					$("#SQLContent").html('');
					content = '';
					tlb = "";

					console.log("***"+TBID);
					if ( TBID == "ALL"){

						tlb += '<p><span>Database - All Tables</span><br>';
						tlb += '<div style="border: 1px solid gray; width:100%; padding:3px; overflow: auto;" >';
						$.each(data['tableNameList'], function(){
							tlb += this['tableName']+',';
						});
						tlb += "</div><br>";

						tlb += '<div style="border: 1px solid gray; width:50%; height: 300px; padding:3px; overflow: auto;" >';
						$.each(data['tableNameList'], function(){
							tlb += this['tableName']+'<br>';
						});
						tlb += "</div><br>";
						

						tlb += '<table id="colTbl" cellpadding="2" cellspacing="0" >';
						tlb += '<tr id="tblHeader">';
						tlb += '<td>Table</td>';
						tlb += '<td>Column</td>';
						tlb += '<td>Type</td>';
						tlb += '<td>Length</td>';
						tlb += '</tr>';

						tbName = '';
						c = 0;
						rowNum = 0;

						$.each(data['tableInfo']['columns'], function(){
							if ( tbName != this['tableName']){
								// Show column count for previous table
								if (c > 0){
									tlb += '<tr class="tblColCount"><td colspan="4">';
									tlb += '&nbsp;&nbsp;&nbsp; Columns: '+c;
									tlb += '</td></tr>';
								}
								// Table separator row
								tbName = this['tableName'];
								tlb += '<tr class="tblSeparator"><td colspan="4">';
								tlb += '<strong>' + tbName + '</strong>';
								tlb += '</td></tr>';
								c = 0;
								rowNum = 0;
							}
							c = c + 1;
							rowNum = rowNum + 1;

							// Alternating row colors
							var rowClass = (rowNum % 2 === 0) ? 'tblRowEven' : 'tblRowOdd';
							tlb += '<tr class="' + rowClass + '">';
							tlb += '<td> ' + this['tableName'] + ' </td>';
							var colIcons = '';
							if (this['COLUMN_KEY'] === 'PRI') {
								colIcons += '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icon_PK_1a.png" title="Primary Key" style="width:40px; height:40px; vertical-align:middle; margin-right:2px;">';
							}
							if (this['EXTRA'] && this['EXTRA'].indexOf('auto_increment') !== -1) {
								colIcons += '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icon-autoIncrement_64.png" title="Auto Increment" style="width:40px; height:40px; vertical-align:middle;">';
							}
							tlb += '<td> ' + this['columnName'] + (colIcons ? '<br>' + colIcons : '') + ' </td>';
							tlb += '<td> ' + this['columnType'] + ' </td>';
							tlb += '<td align="right"> ' + this['length'] + ' </td>';
							tlb += '</tr>';
							tlb += '<tr class="tblCommentRow">';
							tlb += '<td></td>';
							tlb += '<td colspan="3"><textarea class="colDesc" maxlength="1024" style="margin:0; height:110px;" id="' + data['databaseName'] + '---' + this['tableName'] + '___' + this['columnName'] + '" data-databaseName="' + data['databaseName'] + '" data-tablename="' + this['tableName'] + '" data-columnName="' + this['columnName'] + '">' + (this['desc'] || '') + '</textarea></td>';
							tlb += '</tr>';
						});

						tlb += '<tr class="tblColCount"><td colspan="4">';
						tlb += '&nbsp;&nbsp;&nbsp; Columns: '+c;
						tlb += '</td></tr>';

						tlb += "</table></p>";
						content += tlb;

						// Set HTML
						$("#SQLContent").html(content);
						window.scrollTo(0, 0);

						// Bind change handlers for saving
						$('.colDesc').on('change', function(e) {
							columnDescUpdated($(this));
						});

					} else if ( TBID == "ALLTables" || typeFilter || isDeprecated) {
						// ALLTables / Type filter / Deprecated - Show table comment section for matching tables

						// Build deprecated set for fast lookup
						var deprecatedSet = {};
						if (isDeprecated && data['deprecatedTables']) {
							$.each(data['deprecatedTables'], function(i, t) { deprecatedSet[t] = true; });
						}

						// Build table list and lookup table list
						var tableListCSV = '';
						var tableListStats = '';
						var lookupTableListCSV = '';
						$.each(data['allTableDescs'], function(){
							if (typeFilter && !tableMatchesTypeFilter((data['tableTypeAll'] || {})[this['tableName']], typeFilter)) { return true; }
							if (isDeprecated && !deprecatedSet[this['tableName']]) { return true; }
							tableListCSV += this['tableName'] + ',';
							// Build stats line: tableName  (N rows, X.XX MB)
							var tStats = data['tableStatsAll'] && data['tableStatsAll'][this['tableName']];
							if (tStats) {
								tableListStats += this['tableName'] + '  (' + Number(tStats.rowCount).toLocaleString() + ' rows, ' + tStats.sizeMB + ' MB)\n';
							} else {
								tableListStats += this['tableName'] + '\n';
							}
							// Check if tableDesc starts with "Lookup Table"
							if ( this['tableDesc'] && this['tableDesc'].toLowerCase().indexOf('lookup table') === 0 ){
								lookupTableListCSV += this['tableName'] + ',';
							}
						});
						// Remove trailing commas
						tableListCSV = tableListCSV.slice(0, -1);
						tableListStats = tableListStats.trimEnd();
						lookupTableListCSV = lookupTableListCSV.slice(0, -1);
						var currentCount = tableListCSV ? tableListCSV.split(',').length : 0;
						updateCountDisplay(currentCount);

						// First section - Table Name List with copy buttons
						tlb += '<div class="' + (isDeprecated ? 'deprecated-section-bg' : '') + '" style="margin-bottom: 20px;' + (isDeprecated ? ' padding: 12px; border-radius: 4px;' : '') + '">';
						tlb += '<p style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1px; padding:1px 20px;">';
						tlb += '<span class="header1" style="padding:1px 12px;">' + (typeFilter ? 'TYPE: ' + typeFilter : (isDeprecated ? 'DEPRECATED' : 'TABLE NAME LIST')) + '</span>';
						tlb += '<span>';
						tlb += '<button type="button" id="btnCopyTableList" class="btn-copy" style="margin-right: 8px; padding:2px 8px;" title="Copy Table List to Clipboard">Copy Tables</button>';
						tlb += '<button type="button" id="btnCopyLookupList" class="btn-copy" style="padding:2px 8px;"title="Copy Lookup Tables to Clipboard">Copy Lookup</button>';
						tlb += '</span>';
						tlb += '</p>';
						tlb += '<input type="hidden" id="tableListCSV" value="' + tableListCSV.replace(/"/g, '&quot;') + '">';
						tlb += '<textarea class="colDesc" maxlength="65535" style="margin:0; height: 220px; overflow-x: scroll; text-overflow: ellipsis;" data-gramm="false" wrap="off" ';
						tlb += 'id="tableListTextarea" ';
						tlb += 'data-databaseName="' + data['databaseName'] + '" ';
						tlb += 'data-columnName="TABLELIST" readonly>';
						tlb += tableListStats;
						tlb += '</textarea>';
						tlb += '</div>';

						// Second section - Lookup Tables List (hidden when type/deprecated filter active)
						if (!typeFilter && !isDeprecated) {
							tlb += '<div style="margin-bottom: 20px;">';
							tlb += '<p><span class="header1">LOOKUP TABLES</span></p>';
							tlb += '<textarea class="colDesc" maxlength="2048" style="margin:0; height: 110px; overflow-x: scroll; text-overflow: ellipsis;" data-gramm="false" wrap="off" ';
							tlb += 'id="lookupTableListTextarea" ';
							tlb += 'data-databaseName="' + data['databaseName'] + '" ';
							tlb += 'data-columnName="LOOKUPTABLELIST" readonly>';
							tlb += lookupTableListCSV;
							tlb += '</textarea>';
							tlb += '</div>';
						}

						tlb += '<div style="background-color: black; height: 5px; width: 100%; margin-bottom: 20px;"></div>';

						var tableTypeAll = data['tableTypeAll'] || {};
						var tableTypeOpts = buildTableTypeOpts(data['tableTypeList']);
						$.each(data['allTableDescs'], function(){
							if (typeFilter && !tableMatchesTypeFilter(tableTypeAll[this['tableName']], typeFilter)) { return true; }
							if (isDeprecated && !deprecatedSet[this['tableName']]) { return true; }
							var tStats = data['tableStatsAll'] && data['tableStatsAll'][this['tableName']];
							var statsSpan = tStats
								? '<span style="margin-left:12px; font-size:0.82em; color:#888; font-weight:normal;">Rows: ' + Number(tStats.rowCount).toLocaleString() + ' &nbsp;|&nbsp; Size: ' + tStats.sizeMB + ' MB</span>'
								: '';
							var currentType = tableTypeAll[this['tableName']] || '';
							var typeControl;
							if (typeFilter) {
								var radioName = 'tableType_' + this['tableName'].replace(/[^a-zA-Z0-9]/g, '_');
								var radioAttrs = ' class="tableTypeRadio" data-databasename="' + data['databaseName'] + '" data-tablename="' + this['tableName'] + '"';
								typeControl = '<span style="font-size:0.82em; display:flex; flex-wrap:wrap; gap:4px 10px; padding-left:10px;">';
								// Blank / unset option
								typeControl += '<label style="cursor:pointer; white-space:nowrap;">'
									+ '<input type="radio" name="' + radioName + '" value=""' + radioAttrs + (currentType === '' ? ' checked' : '') + ' style="margin-right:3px; cursor:pointer; vertical-align:middle;">—'
									+ '</label>';
								$.each(tableTypeOpts, function(i, opt) {
									typeControl += '<label style="cursor:pointer; white-space:nowrap;">'
										+ '<input type="radio" name="' + radioName + '" value="' + opt + '"' + radioAttrs + (currentType === opt ? ' checked' : '') + ' style="margin-right:3px; cursor:pointer; vertical-align:middle;">' + opt
										+ '</label>';
								});
								typeControl += '</span>';
							} else {
								typeControl = '<select class="tableTypeSelect" data-databasename="' + data['databaseName'] + '" data-tablename="' + this['tableName'] + '" title="Table Type" style="padding:2px 6px; font-size:0.82em; vertical-align:middle;">';
								typeControl += '<option value="">-- Table Type --</option>';
								$.each(tableTypeOpts, function(i, opt) {
									typeControl += '<option value="' + opt + '"' + (currentType === opt ? ' selected' : '') + '>' + opt + '</option>';
								});
								typeControl += '</select>';
							}
							tlb += '<div style="margin-bottom: 20px;">';
							tlb += '<p name="spacer1" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">';
							tlb += '<span><span class="header1 tbl-name-link" style="text-transform:none; cursor:pointer; text-decoration:underline;" data-tablename="' + this['tableName'] + '">' + this['tableName'] + '</span>' + statsSpan + '</span>';
							tlb += '<span name="tableTypeInputArea1" style="display:inline-block; width:55%;' + (typeFilter ? '' : ' text-align:right;') + '">' + typeControl + '</span>';
							tlb += '</p>';
							tlb += '<textarea class="colDesc" maxlength="2048" style="margin:0; height: 120px; border: 0.5 px solid #B5FCFA !important;" data-gramm="false" ';
							tlb += 'id="' + data['databaseName'] + '---' + this['tableName'] + '___TABLEDEFINITION" ';
							tlb += 'data-databaseName="' + data['databaseName'] + '" ';
							tlb += 'data-tablename="' + this['tableName'] + '" ';
							tlb += 'data-columnName="TABLEDEFINITION">';
							tlb += this['tableDesc'];
							tlb += '</textarea>';
							if (isDeprecated) {
								var depCols = (data['deprecatedColumns'] || {})[this['tableName']] || [];
								if (depCols.length > 0) {
									tlb += '<table style="width:100%; margin-top:8px; border-collapse:collapse; font-size:0.88em;">';
									tlb += '<tr class="dep-col-header"><th style="text-align:left; padding:4px 8px;">Column</th><th style="text-align:left; padding:4px 8px;">Comment</th></tr>';
									$.each(depCols, function(i, col) {
										var rowClass = (i % 2 === 0) ? 'dep-col-row-even' : 'dep-col-row-odd';
										tlb += '<tr class="' + rowClass + '">';
										tlb += '<td class="dep-col-cell" style="padding:4px 8px; white-space:nowrap;">' + col['columnName'] + '</td>';
										tlb += '<td class="dep-col-cell" style="padding:4px 8px;">' + col['comment'] + '</td>';
										tlb += '</tr>';
									});
									tlb += '</table>';
								}
							}
							tlb += '</div>';
						});

						content += tlb;

						// Set HTML
						$("#SQLContent").html(content);
						window.scrollTo(0, 0);

						// Bind change handlers for saving
						$('.colDesc').on('change',function(e) {
							columnDescUpdated($(this));
						});

					} else {

						// Table
						// Check if this is a lookup table first
						updateCountDisplay(1);
						var tableComment = data['tableDesc'] || '';
						var isLookupTable = tableComment.toLowerCase().indexOf('lookup table') === 0;

						if ( data['tableName'] == 'calendarEventStatus' ){
							isLookupTable = 0;
						}

						// Required columns for lookup tables — check early so warning can appear in stats line
						var LOOKUP_REQUIRED_COLS = ['title', 'description', 'abr', 'displayorder', 'displayordergroup', 'displayclass', 'userid', 'orgid', 'deleted', 'deletable', 'icon', 'additionalinfo1', 'additionalinfo2', 'adminonly'];
						var lookupMissingCols = [];
						if (isLookupTable && data['tableInfo'] && data['tableInfo']['columns']) {
							var existingColsEarly = [];
							$.each(data['tableInfo']['columns'], function() {
								existingColsEarly.push(this['columnName'].toLowerCase());
							});
							// Check for an id field (any column ending in 'id')
							var hasIdField = existingColsEarly.some(function(c) { return c.slice(-2) === 'id'; });
							if (!hasIdField) { lookupMissingCols.push('(id field)'); }
							$.each(LOOKUP_REQUIRED_COLS, function(i, col) {
								if (existingColsEarly.indexOf(col) === -1) { lookupMissingCols.push(col); }
							});
						}

						var statsHtml = '';
					if (data['tableStats']) {
						var rowCount = data['tableStats']['rowCount'] || 0;
						var sizeMB   = data['tableStats']['sizeMB']   || 0;
						var warnIcon = (isLookupTable && lookupMissingCols.length > 0)
							? ' <img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-warning-64.png"'
							+ ' style="width:30px; height:30px; vertical-align:middle; margin-left:6px; cursor:help;"'
							+ ' title="Missing required lookup columns: ' + lookupMissingCols.join(', ') + '">'
							: '';
						statsHtml = '<span style="margin-left:16px; font-size:0.82em; color:#888; font-weight:normal;">'
							+ 'Rows: ' + Number(rowCount).toLocaleString()
							+ ' &nbsp;|&nbsp; '
							+ 'Size: ' + sizeMB + ' MB'
							+ warnIcon
							+ '</span>';
					}
					// Table Type dropdown (built first, placed right-aligned in header)
						var tableTypeVal = data['tableType'] || '';
						var tableTypeDdl = '<select id="tableTypeSelect" data-databasename="' + data['databaseName'] + '" data-tablename="' + data['tableName'] + '" title="Table Type" style="padding:2px 6px; font-size:0.85em; vertical-align:middle;">';
						tableTypeDdl += '<option value="">-- Table Type --</option>';
						$.each(buildTableTypeOpts(data['tableTypeList']), function(i, opt) {
							var selected = (tableTypeVal === opt) ? ' selected' : '';
							tableTypeDdl += '<option value="' + opt + '"' + selected + '>' + opt + '</option>';
						});
						tableTypeDdl += '</select>';

						var appendBtn = '';
						if (isLookupTable) {
							appendBtn = '<span class="tooltip tooltip-info" style="margin-left: 8px;">'
								+ '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-module-64.png" id="btnAppendColumnInfo" class="toolbarBtn" style="width:30px; height:30px; cursor:pointer; vertical-align:middle; opacity:0.7;">'
								+ '<span class="tooltiptext">'
								+ '<strong>Append Column Info</strong><br>'
								+ 'Reads the first two columns of this lookup table and appends a sample of values to the table comment.<br><br>'
								+ 'Useful for documenting what values this lookup table contains. Replaces any existing values block.<br><br>'
								+ '<strong>Example — Table Comment After:</strong><br>'
								+ '<code style="display:block;background:#1e293b;color:#7dd3fc;padding:6px 8px;border-radius:4px;font-size:11px;margin-top:4px;white-space:pre-wrap;">Lookup Table&#10;Values: 1-Active, 2-Inactive, 3-Pending, 4-Archived...</code>'
								+ '</span>'
								+ '</span>';
						}

						var autoFillBtn = '<span class="tooltip tooltip-info">'
							+ '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-detail-color-96.png" id="btnAutoFillComments" class="toolbarBtn" style="width:30px; height:30px; cursor:pointer; vertical-align:middle; opacity:0.85;">'
							+ '<span class="tooltiptext">'
							+ '<strong>Auto-Fill Blank Comments</strong><br>'
							+ 'Fills missing column comments using these rules:<br><br>'
							+ '&bull; <strong>Primary Key</strong> — always set to "Auto-Assigned Primary Key" if not already set<br>'
							+ '&bull; <strong>Foreign Keys</strong> — always set to "Foreign Key to TABLE.COLUMN" if not already set<br>'
							+ '&bull; <strong>Standard columns</strong> (createdDate, deleted, etc.) — always filled<br>'
							+ '&bull; <strong>All others</strong> — only filled if blank<br><br>'
							+ '<strong>Example — Column Comments After:</strong><br>'
							+ '<code style="display:block;background:#1e293b;color:#7dd3fc;padding:6px 8px;border-radius:4px;font-size:11px;margin-top:4px;white-space:pre-wrap;">statusId    → Auto-Assigned Primary Key&#10;docTopicId  → Foreign Key to docTopic.docTopicId&#10;createdDate → Date record was created&#10;deleted     → 0=Active 1=Deleted&#10;displayOrder→ Sort order (asc)</code>'
							+ '</span>'
							+ '</span>';

						tlb = '<p style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">';
						tlb += '<span style="display:flex; align-items:center; gap:4px;"><span class="header1">TABLE COMMENT</span>' + appendBtn + statsHtml + '</span>';
						tlb += '<span style="display:flex; align-items:center; gap:8px;">';
						tlb += autoFillBtn;
						tlb += tableTypeDdl;
						tlb += '</span>';
						tlb += '</p>';

						// Only show lookup table features if this is a lookup table
						if (isLookupTable) {
							// Use the already-computed lookupMissingCols from above
							if (lookupMissingCols.length > 0) {
								tlb += '<span class="missing-columns-msg">Missing columns: ' + lookupMissingCols.join(', ') + '</span>';
								tlb += '<button type="button" class="btn-add-columns" data-dbname="' + data['databaseName'] + '" data-tblname="' + data['tableName'] + '">Fix Table - Add Columns</button>';
							}
						}

						tlb += "<br>";
						tlb += '<textarea class="colDesc" maxlength="2048" style="margin:0; height: 60px;" data-gramm="false" id="' + data['databaseName'] + '---' + data['tableName'] + '___TABLEDEFINITION" data-databaseName="' + data['databaseName'] + '" data-tablename="'+data['tableName']+'" data-columnName="TABLEDEFINITION"  >' + data['tableDesc'] + '</textarea>';

						// Icon gallery — lookup tables only, rows where icon is not null
						if (isLookupTable && data['lookupIconRows'] && data['lookupIconRows'].length > 0) {
							tlb += '<p style="margin-top:14px;">'
								+ '<span class="header1">ICONS</span>'
								+ '<span class="tooltip tooltip-info" style="margin-left:6px; vertical-align:middle;">'
								+ '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icons8-info-64.png" style="width:30px; height:30px; vertical-align:middle; cursor:help; opacity:0.75;">'
								+ '<span class="tooltiptext">'
								+ '<strong>Icon Source</strong><br><br>'
								+ 'These icons come from rows in this lookup table where the <code>icon</code> column is not null.<br><br>'
								+ 'Each icon displays the row\'s <strong>title</strong> label and the image stored in the <strong>icon</strong> column.<br><br>'
								+ 'To add or change an icon, update the <code>icon</code> field on the lookup table row.'
								+ '</span>'
								+ '</span>'
								+ '</p>';
							tlb += '<div style="display:flex; flex-wrap:nowrap; overflow-x:auto; gap:16px; padding:10px 4px 14px 4px; align-items:flex-start;">';
							$.each(data['lookupIconRows'], function() {
								var safeTitle = $('<div>').text(this['title']).html();
								var safeIcon  = $('<div>').text(this['icon']).html();
								tlb += '<div style="display:flex; flex-direction:column; align-items:center; min-width:64px; text-align:center;">';
								tlb += '<span class="iconText" style="font-size:11px; color:#444; margin-bottom:5px; white-space:nowrap;">' + safeTitle + '</span>';
								tlb += '<img src="' + safeIcon + '"'
								    +  ' class="lookupIconImg"'
								    +  ' data-iconurl="' + safeIcon + '"'
								    +  ' data-icontitle="' + safeTitle + '"'
								    +  ' style="width:48px; height:48px; object-fit:contain; cursor:help;">';
								tlb += '</div>';
							});
							tlb += '</div>';
						}

						// tlb += "<p><span class='header1'>COLUMNS</span><table id='colTbl' cellpadding='2' cellspacing='0' >";
						tlb += "<table id='colTbl' cellpadding='2' cellspacing='0' >";
						tlb += '<tr id="tblHeader">';
						tlb += '<td style="color:#CEE3E1;">Column</td>';
						tlb += '<td style="color:#CEE3E1;">Type</td>';
						tlb += '<td style="color:#CEE3E1;">Length</td>';
						tlb += '<td style="color:#CEE3E1;" width="80%">Column Comment</td>';
						tlb += '</tr>';

						var pkFkRefs = data['pkFkReferences'] || {};

						$.each(data['tableInfo']['columns'], function(){
							var colIcons = '';
							if (this['COLUMN_KEY'] === 'PRI') {
								var colName = this['columnName'];
								var refs = pkFkRefs[colName] || [];
								var pkTitle, pkImgStyle;
								if (refs.length > 0) {
									var refLines = refs.map(function(r) {
										return r['referencingTable'] + '.' + r['referencingColumn'];
									}).join('&#10;');
									pkTitle = 'Primary Key&#10;&#10;Referenced by (' + refs.length + '):&#10;' + refLines;
									pkImgStyle = 'width:40px; height:40px; vertical-align:middle; margin-right:2px; cursor:help;';
								} else {
									pkTitle = 'Primary Key&#10;&#10;No foreign keys reference this column.';
									pkImgStyle = 'width:40px; height:40px; vertical-align:middle; margin-right:2px;';
								}
								colIcons += '<span class="pk-icon-wrap">'
									+ '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icon_PK_1a.png" class="pk-icon" data-pk-title="' + pkTitle + '" style="' + pkImgStyle + '">'
									+ '<span class="pk-tooltip" style="display:none;"></span>'
									+ '</span>';
							}
							if (this['EXTRA'] && this['EXTRA'].indexOf('auto_increment') !== -1) {
								colIcons += '<img src="https://cb9-09.s3.us-east-1.amazonaws.com/sitegraphics/icon_AutoIncr_64.png" title="Auto Increment" style="width:40px; height:40px; vertical-align:middle;">';
							}
							tlb += '<tr id="tblRow">';
							tlb += '<td> ' + this['columnName'] + (colIcons ? '<br>' + colIcons : '') + ' </td>';
							tlb += '<td> ' + this['columnType'] + ' </td>';
							tlb += '<td align="right"> ' + this['length'] + ' </td>';
							tlb += '<td><textarea class="colDesc" maxlength="1024" style="margin:0; height:120px;" id="' + data['databaseName'] + '---' + data['tableName'] + '___' + this['columnName'] + '" data-databaseName="' + data['databaseName'] + '" data-tablename="'+data['tableName']+'" data-columnName="'+this['columnName']+'">' + this['desc'] + '</textarea></td>';
							tlb += '</tr>';
						});
						tlb += "</table></p>";
						content += tlb;

						// Column List
						columnList = '';
						columnList1 = '';
						columnList1a = '';
						columnList2 = '';
						columnListUpdate = '';
						columnListWhere = '';
						$.each(data['tableInfo']['columns'], function(){
							//console.log(this['columnName']);
							columnList += ', ' + this['columnName'];
							columnList1 += '<br>' + this['columnName'];
							columnList1a += '<br>$SQLInsertFilter[\':' + this['columnName'] + '\'] = $rowMS[\''+ this['columnName'] + '\'];';
							columnList2 +=  "', '" + this['columnName'];
							columnListUpdate +=  this['columnName'] + " = '" + this['columnName'] + "', ";
							columnListWhere +=  'and ' + this['columnName'] + " = '" + this['columnName'] + "' ";
						});
						columnList = columnList.substr(2,columnList.length-2);
						columnList2 = columnList2.substr(3,columnList2.length-3) + "'";
						columnListUpdate = columnListUpdate.substr(0,columnListUpdate.length-2)
						columnListWhere = columnListWhere.substr(4,columnListWhere.length-4)




						// content += '<p style="border-top:none;"><span class="header1">COLUMN LIST</span><br> ' + columnList + '</p>';

						content += '<p style="border-top:none;"><span class="header1">COLUMN LIST</span><br> ' + columnList + '<br><br>'+ columnList1 + '<br><br>'+ columnList1a +'</p>';

						// Alter Table - Add Columns
						var alterCols = [];
						$.each(data['tableInfo']['columns'], function(){
							if (this['COLUMN_KEY'] === 'PRI') return;
							var colDef = this['columnTypeFull'] || this['columnType'];
							var nullable = (this['isNullable'] === 'YES') ? ' NULL' : ' NOT NULL';
							var defClause = '';
							if (this['columnDefault'] !== null && this['columnDefault'] !== undefined) {
								var dv = this['columnDefault'];
								var dvUp = dv.toUpperCase();
								if (dvUp === 'CURRENT_TIMESTAMP' || dvUp === 'NULL' || /^\d/.test(dv)) {
									defClause = ' DEFAULT ' + dv;
								} else {
									defClause = " DEFAULT '" + dv.replace(/'/g, "''") + "'";
								}
							} else if (this['isNullable'] === 'YES') {
								defClause = ' DEFAULT NULL';
							}
							var extraRaw = this['EXTRA'] ? this['EXTRA'].replace(/DEFAULT_GENERATED\s*/i, '').trim() : '';
							var extraClause = extraRaw ? ' ' + extraRaw.toUpperCase() : '';
							alterCols.push('&nbsp;&nbsp;ADD COLUMN ' + this['columnName'] + ' ' + colDef + nullable + defClause + extraClause);
						});
						if (alterCols.length > 0) {
							content += '<p><span class="header1">ALTER TABLE</span><br>ALTER TABLE ' + data['tableName'] + '<br>' + alterCols.join(',<br>') + ';</p>';
						}

						// Triggers
						if (data['tableTriggers'] && data['tableTriggers'].length > 0) {
							var trigHtml = '';
							$.each(data['tableTriggers'], function(){
								trigHtml += '<br><b>' + this['actionTiming'] + ' ' + this['triggerEvent'] + '</b> — ' + this['triggerName'] + '<br>';
								trigHtml += '<pre style="margin:4px 0 8px 0; white-space:pre-wrap; font-size:0.85em;">' + this['triggerBody'] + '</pre>';
							});
							content += '<p><span class="header1">TRIGGERS</span>' + trigHtml + '</p>';
						} else {
							content += '<p><span class="header1">TRIGGERS</span><br><span style="color:#999;">No triggers on this table.</span></p>';
						}

						// Select
						select = '<p><span class="header1">* SELECT</span><br>select ' + columnList + ' from ' + data['tableName'] + ';</p>';
						content += select;

						// Insert
						insert = '<p><span class="header1">INSERT</span><br>insert into ' + data['tableName'] + ' ( ' + columnList + ' ) values ( ' + columnList2 + ');</p>';
						content += insert;

						// Insert PHP 
						insert = '<p><span class="header1">PHP INSERT</span><br>insert into ' + data['tableName'] + ' ( ' + columnList + ' ) values ( ';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;
							if (counter > 1){
								insert += ', ';
							}
							insert += ':' + this['columnName'];
						});

						insert += ');</p>';
						content += insert;

						// Update
						upd = '<p><span class="header1">UPDATE</span><br>update ' + data['tableName'] + ' set ' + columnListUpdate;
						upd += ' where ' + columnListWhere + '</p>';
						content += upd;

						// Update PHP
						upd = '<p><span class="header1">PHP Update</span><br>update ' + data['tableName'] + ' set ';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;
							if (counter > 1){
								upd += ', ';
							}
							upd += this['columnName'] + '= :' + this['columnName'];
						});

						upd += ';</p>';
						content += upd;

						// Delete
						del = '<p ><span class="header1"><b>DELETE</span><br>delete from ' + data['tableName'];
						del += ';</p>';
						content += del;

						// Where 
						wh = '<p><span class="header1">Where</span><br>where ';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;
							if (counter > 1){
								wh += ' and ';
							}
							wh += this['columnName'] + "= '" + this['columnName'] + "'";
						});

						wh += ';</p>';
						content += wh;

						// Where PHP
						wh = '<p><span class="header1">PHP Where</span><br>where ';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;
							if (counter > 1){
								wh += ' and ';
							}
							wh += this['columnName'] + '= :' + this['columnName'];
						});

						wh += ';</p>';
						content += wh;


						// PHP Binding
						bind = '<p><span class="header1">PHP BINDING VARIABLES</span><br>';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;

							bind += "$statement->bindParam(':" + this['columnName'] + "', $" + this['columnName'] + ", PDO::PARAM_STR);<br>";
						});

						bind += '</p>';
						content += bind;



						// Coldfusion Binding
						/*
						bind = '<p><span class="header1">COLDFUSION BINDING VARIABLES</span><br>';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;

							bind += '&#60;cfqueryparam value="#' + this['columnName'] + '#" cfsqltype="cf_sql_varchar" &#62;<br>';
						});

						bind += '</p>';
						content += bind;
						*/

						// COLDFUSION FUNCTION ARGUMENTS<
						/*
						bind = '<p><span class="header1">COLDFUSION FUNCTION ARGUMENTS</span><br>';

						bind += '&#60;cffunction name="FUNCTION" output="no"  returntype="struct" verifyclient="no" securejson="false"&#62;<br>';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;

							bind += '&#60;cfargument name="' + this['columnName'] + '" type="string" required="yes" &#62;<br>';
						});

						bind += '&#60;/cffunction &#62;<br>';

						bind += '</p>';
						content += bind;
						*/

						// Coldfusion Invoke Argument
						/*
						bind = '<p class="SQLContentLast"><span class="header1">COLDFUSION INVOKE ARGUMENTS</span><br>';
						bind += '&#60;cfinvoke component="/cfc/CFC" method="FUNCTION" returnvariable="result"&#62;<br>';

						counter = 0;
						$.each(data['tableInfo']['columns'], function(){
							counter = counter + 1;

							bind += '&#60;cfinvokeargument name="' + this['columnName'] + '" value="#' + this['columnName'] + '#"  &#62;<br>';
						});

						bind += '&#60;/cfinvoke &#62;<br>';

						bind += '</p>';
						content += bind;
						*/

						// Set HTML
						$("#SQLContent").html(content);
						window.scrollTo(0, 0);

						// Bind to JS Function
						$('.colDesc').on('change',function(e) {
							columnDescUpdated($(this));
						});

						// PK icon — show FK-references tooltip on mouseover (300ms delay)
						$('#SQLContent').on('mouseenter', '.pk-icon', function() {
							var $icon = $(this);
							var timer = setTimeout(function() {
								var title = $icon.attr('data-pk-title') || 'Primary Key';
								title = title.replace(/&#10;/g, '\n');
								$icon.siblings('.pk-tooltip').text(title).show();
							}, 300);
							$icon.data('pk-timer', timer);
						}).on('mouseleave', '.pk-icon', function() {
							clearTimeout($(this).data('pk-timer'));
							$(this).siblings('.pk-tooltip').hide();
						});
					}
					$(document).prop('title', 'SQLAssist-'+TBNM);
				} else {
					alert("Invalid API Call");
				}
			},
			fail:function(data){ 
				console.log(data);
				alert("Error");
			}
		})
	} else {
		
	}

};

columnDescUpdated = function(t){
	// console.log( t.attr('data-databaseName') );
	// console.log( t.attr('data-tableName') );
	// console.log( t.attr('data-columnName') );
	$.ajax({
        type:'GET',
        url: 'xhr/colDescUpdate.php',
        dataType: 'json',
        data: {
        	databaseName:t.attr('data-databaseName'),
        	tableName:t.attr('data-tableName'),
        	columnName:t.attr('data-columnName'),
        	desc:t.val()
        },
        success: function(data){
        	console.log("OK");
        }
    })
}

// Restore previous session: try setting database from saved session value
x = $('#dbSelected').val();
if (x != '' && x != '0') {
	$('#databaseName').val(x);
}

// Call databaseUpdated(1) exactly once: either the session value matched an option,
// or there is only one database to auto-select. Never call it twice.
if ($('#databaseName').val() != '0') {
	// Session value was restored successfully
	databaseUpdated(1);
} else if ($('#databaseName option').length == 2) {
	// Only one database available — auto-select it
	$('#databaseName option:nth-child(2)').prop('selected', true);
	databaseUpdated(1);
}

keepAlive = function(){
	$.ajax({
        type:'GET',
        url: 'xhr/keepAlive.php',
        dataType: 'json',
        data: {},
        success: function(data){
        	if (data['success'] == 0){
        		window.location = "index.php";
        	}
        }
    })
}
window.setInterval(function(){
  keepAlive();
}, 30000);

// =============================================================================
// Snackbar Notification
// =============================================================================
var snackbarTimeout = null;
function snackbarAlert(msg) {
	var x = document.getElementById('snackbar');
	if (!x) return;
	if (snackbarTimeout) { clearTimeout(snackbarTimeout); snackbarTimeout = null; }
	x.className = '';
	$('#snackbar').html(msg);
	setTimeout(function() { x.className = 'show'; }, 10);
	snackbarTimeout = setTimeout(function() {
		x.className = x.className.replace('show', 'hide');
		snackbarTimeout = null;
	}, 3000);
}

// =============================================================================
// Confirmation Modal Functions
// =============================================================================
var confirmModalCallback = null;

function showConfirmModal(title, message, callback) {
	console.log('showConfirmModal called');
	console.log('Modal element exists:', $('#confirmModal').length > 0);
	$('#confirmModalTitle').text(title);
	$('#confirmModalMessage').html(message);
	confirmModalCallback = callback;
	$('#confirmModal').fadeIn(200);
	console.log('Modal should be visible now');
}

function hideConfirmModal() {
	$('#confirmModal').fadeOut(200);
	confirmModalCallback = null;
}

$(document).on('click', '#confirmModalCancel', function() {
	console.log('Cancel clicked');
	hideConfirmModal();
});

$(document).on('click', '#confirmModalContinue', function() {
	console.log('Continue clicked, callback exists:', !!confirmModalCallback);
	var callbackToRun = confirmModalCallback;
	hideConfirmModal();
	if (callbackToRun) {
		try {
			callbackToRun();
		} catch(e) {
			console.log('Callback error:', e);
		}
	}
});

// Close modal on background click
$(document).on('click', '#confirmModal', function(e) {
	if (e.target === this) {
		hideConfirmModal();
	}
});

// =============================================================================
// Append Column Info Button
// =============================================================================
$(document).on('click', '#btnAppendColumnInfo', function() {
	var DBID = $('#databaseName').val();
	var TBID = $('#tableName').val();
	var TBNM = $("#tableName option:selected").text();

	// Remove * suffix if present
	if (TBNM.endsWith(' *')) {
		TBNM = TBNM.slice(0, -2);
	}

	if (DBID == '0' || TBID == '0' || TBID == 'ALL' || TBID == 'ALLTables') {
		alert('Please select a specific table first');
		return;
	}

	// Function to execute the append
	var executeAppend = function() {
		$.ajax({
			type: 'POST',
			url: 'xhr/appendColumnInfo.php',
			dataType: 'json',
			data: {
				databaseName: DBID,
				tableName: TBID
			},
			success: function(data) {
				if (data['success'] == 1) {
					tableUpdated();
				} else {
					alert('Error: ' + data['msg']);
				}
			},
			error: function() {
				alert('Error: Could not complete the request');
			}
		});
	};

	// Check if current comment already contains values
	var currentComment = $('.colDesc[data-columnName="TABLEDEFINITION"]').val() || '';

	if (currentComment.indexOf('Values: 1') !== -1) {
		// Values already exist, show confirmation
		showConfirmModal(
			'Append Column Info',
			'This will replace the existing values in the table comment for <strong>' + TBNM + '</strong>.<br><br>Continue?',
			executeAppend
		);
	} else {
		// No existing values, execute immediately
		executeAppend();
	}
});

// =============================================================================
// Lookup-table Icon Hover — show floating info popup with the icon's image URL
// =============================================================================
(function() {
	var $popup = null;
	var hideTimer = null;

	function ensurePopup() {
		if ($popup && $popup.length) return $popup;
		$popup = $(
			'<div id="lookupIconUrlPopup" style="' +
			  'position:absolute; display:none; z-index:9999;' +
			  ' background:#1f2937; color:#e8eaf0;' +
			  ' border:1px solid #5791d0; border-radius:6px;' +
			  ' padding:10px 12px; max-width:560px; min-width:200px;' +
			  ' box-shadow:0 4px 16px rgba(0,0,0,0.35);' +
			  ' font-size:12px; line-height:1.5;' +
			'">' +
			  '<div style="font-weight:700; color:#93c5fd; margin-bottom:4px;">' +
			    '<span class="lookupIconUrlPopup-title"></span>' +
			  '</div>' +
			  '<div style="color:#9aa0b0; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">Image URL</div>' +
			  '<div class="lookupIconUrlPopup-url" style="' +
			    'word-break:break-all; user-select:text; -webkit-user-select:text;' +
			    ' font-family:Menlo,Consolas,monospace;' +
			  '"></div>' +
			  '<div class="lookupIconUrlPopup-copy" style="' +
			    'margin-top:8px; font-size:10px; color:#9aa0b0; cursor:pointer; user-select:none;' +
			  '">📋 Click to copy</div>' +
			'</div>'
		).appendTo('body');

		// Cancel hide when mouse enters the popup so the user can copy text
		$popup.on('mouseenter', function() {
			if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
		});
		$popup.on('mouseleave', function() {
			schedulePopupHide();
		});

		// Click anywhere in the popup to copy the URL
		$popup.on('click', function() {
			var url = $popup.find('.lookupIconUrlPopup-url').text();
			if (!url) return;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(function() {
					$popup.find('.lookupIconUrlPopup-copy').text('✓ Copied');
					setTimeout(function() {
						$popup.find('.lookupIconUrlPopup-copy').text('📋 Click to copy');
					}, 1200);
				});
			}
		});

		return $popup;
	}

	function schedulePopupHide() {
		if (hideTimer) clearTimeout(hideTimer);
		hideTimer = setTimeout(function() {
			if ($popup) $popup.fadeOut(120);
		}, 250);
	}

	$(document).on('mouseenter', '.lookupIconImg', function(e) {
		if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
		var url   = $(this).data('iconurl')   || $(this).attr('src') || '';
		var title = $(this).data('icontitle') || '';
		var p = ensurePopup();
		p.find('.lookupIconUrlPopup-title').text(title);
		p.find('.lookupIconUrlPopup-url').text(url);
		p.find('.lookupIconUrlPopup-copy').text('📋 Click to copy');

		// Position to the right of the icon, vertically centered, then clamp
		// inside the viewport so it never gets clipped on the right edge.
		var $img = $(this);
		var off  = $img.offset();
		var iw   = $img.outerWidth();
		var ih   = $img.outerHeight();
		p.css({ display: 'block', visibility: 'hidden', top: 0, left: 0 }); // measure
		var pw = p.outerWidth();
		var ph = p.outerHeight();
		var left = off.left + iw + 10;
		var top  = off.top + (ih / 2) - (ph / 2);
		var $w = $(window);
		if (left + pw > $w.scrollLeft() + $w.width() - 12) {
			left = off.left - pw - 10; // flip to the left of the icon
		}
		if (top < $w.scrollTop() + 8) top = $w.scrollTop() + 8;
		p.css({ top: top + 'px', left: left + 'px', visibility: 'visible' }).stop(true, true).fadeIn(120);
	});

	$(document).on('mouseleave', '.lookupIconImg', function() {
		schedulePopupHide();
	});
})();

// =============================================================================
// Table Name Link — click in ALLTables / Table List view to select that table
// =============================================================================
$(document).on('click', '.tbl-name-link', function() {
	var tblName = $(this).data('tablename');
	if (!tblName) return;
	$('#tableName').val(tblName);
	tableUpdated();
});

// =============================================================================
// Table Type Dropdown
// =============================================================================
$(document).on('change', '#tableTypeSelect, .tableTypeSelect', function() {
	var dbName  = $(this).data('databasename');
	var tblName = $(this).data('tablename');
	var tblType = $(this).val();
	$.ajax({
		type: 'post',
		url: 'xhr/tableDefUpdate.php',
		dataType: 'json',
		data: { databaseName: dbName, tableName: tblName, tableType: tblType },
		success: function(data) {
			if (data['success'] == 1) {
				snackbarAlert('Table type saved');
			} else {
				snackbarAlert('Failed to save table type');
			}
		}
	});
});

$(document).on('change', '.tableTypeRadio', function() {
	var dbName  = $(this).data('databasename');
	var tblName = $(this).data('tablename');
	var tblType = $(this).val();
	$.ajax({
		type: 'post',
		url: 'xhr/tableDefUpdate.php',
		dataType: 'json',
		data: { databaseName: dbName, tableName: tblName, tableType: tblType },
		success: function(data) {
			if (data['success'] == 1) {
				snackbarAlert('Table type saved');
			} else {
				snackbarAlert('Failed to save table type');
			}
		}
	});
});

// Auto-Fill Blank Column Comments Button
// =============================================================================
$(document).on('click', '#btnAutoFillComments', function() {
	var DBID = $('#databaseName').val();
	var TBID = $('#tableName').val();
	var TBNM = $("#tableName option:selected").text();

	// Remove * suffix if present
	if (TBNM.endsWith(' *')) {
		TBNM = TBNM.slice(0, -2);
	}

	if (DBID == '0' || TBID == '0' || TBID == 'ALL' || TBID == 'ALLTables') {
		alert('Please select a specific table first');
		return;
	}

	var $btn = $(this);
	$btn.prop('disabled', true).css('opacity', '0.3');

	$.ajax({
		type: 'POST',
		url: 'xhr/autoFillColumnComments.php',
		dataType: 'json',
		data: {
			databaseName: DBID,
			tableName: TBID
		},
		success: function(data) {
			$btn.prop('disabled', false).css('opacity', '0.85');
			if (data['success'] == 1) {
				if (data['total'] > 0) {
					snackbarAlert('Done! ' + data['msg'] + ' (' + data['total'] + ' column(s) updated)');
					tableUpdated();
				} else {
					snackbarAlert(data['msg']);
				}
			} else {
				snackbarAlert('Error: ' + data['msg']);
			}
		},
		error: function() {
			$btn.prop('disabled', false).css('opacity', '0.85');
			snackbarAlert('Error: Could not complete the request');
		}
	});
});

// =============================================================================
// Toolbar Help Modal
$('#btnToolbarHelp').on('click', function() {
	$('#toolbarHelpModal').fadeIn(150);
});
$(document).on('click', '#toolbarHelpClose, #toolbarHelpClose2', function() {
	$('#toolbarHelpModal').fadeOut(150);
});
$(document).on('click', '#toolbarHelpModal', function(e) {
	if ($(e.target).is('#toolbarHelpModal')) { $('#toolbarHelpModal').fadeOut(150); }
});

// Mark Lookup Tables Button
// =============================================================================
$('#btnMarkLookupTables').on('click', function() {
	var DBID = $('#databaseName').val();

	if (DBID == '0' || !DBID) {
		alert('Please select a database first');
		return;
	}

	var $btn = $(this);

	showConfirmModal(
		'Apply Settings',
		'This will run the following operations on <strong>' + DBID + '</strong>:<br><br>' +
		'<strong>Step 1 — Mark Lookup Tables:</strong><br>' +
		'&bull; Tables whose comment starts with "Lookup Table" or whose type is set to "Lookup" are tagged as lookup tables<br><br>' +
		'<strong>Step 2 — Auto-Fill Column Comments:</strong><br>' +
		'&bull; Always filled: title, description, abr, deletable, icon, createdDate, createdBy, lastModifiedDate, lastModifiedBy, deleted, displayOrder, displayOrderGroup, displayClass, userId/orgId (lookup tables)<br>' +
		'&bull; Filled if blank: primary keys &rarr; "Auto-Assigned Primary Key", foreign keys &rarr; "Foreign Key to TABLE.COLUMN", [NotUsed] table columns<br><br>' +
		'<strong>Step 3 — Append Column Info (all lookup tables):</strong><br>' +
		'&bull; Reads the first two columns of each lookup table and appends a values sample to its comment (e.g. 1-Active, 2-Inactive…)<br><br>' +
		'Continue?',
		function() {
			$btn.prop('disabled', true);

			// Step 1 — Mark lookup tables
			$.ajax({
				type: 'POST',
				url: 'xhr/markLookupTables.php',
				dataType: 'json',
				data: { databaseName: DBID },
				success: function(data1) {
					if (data1['success'] != 1) {
						$btn.prop('disabled', false);
						alert('Error in Step 1: ' + data1['msg']);
						return;
					}

					// Step 2 — Auto-fill all table comments
					$.ajax({
						type: 'POST',
						url: 'xhr/autoFillAllTableComments.php',
						dataType: 'json',
						data: { databaseName: DBID },
						success: function(data2) {
							if (data2['success'] != 1) {
								$btn.prop('disabled', false);
								alert('Error in Step 2: ' + data2['msg']);
								return;
							}

							// Step 3 — Append column info to all lookup tables
							$.ajax({
								type: 'POST',
								url: 'xhr/appendAllColumnInfo.php',
								dataType: 'json',
								data: { databaseName: DBID },
								success: function(data3) {
									$btn.prop('disabled', false);
									if (data3['success'] == 1) {
										databaseUpdated(1);
									} else {
										alert('Error in Step 3: ' + data3['msg']);
									}
								},
								error: function() {
									$btn.prop('disabled', false);
									alert('Error: Could not complete Step 3');
								}
							});
						},
						error: function() {
							$btn.prop('disabled', false);
							alert('Error: Could not complete Step 2');
						}
					});
				},
				error: function() {
					$btn.prop('disabled', false);
					alert('Error: Could not complete Step 1');
				}
			});
		}
	);
});

// =============================================================================
// Add Missing Lookup Columns Button
// =============================================================================
$(document).on('click', '.btn-add-columns', function(e) {
	e.preventDefault();
	e.stopPropagation();

	var $btn = $(this);
	var dbName = $btn.attr('data-dbname');
	var tblName = $btn.attr('data-tblname');

	console.log('Add Columns clicked - DB:', dbName, 'Table:', tblName);

	if (!dbName || !tblName) {
		alert('Error: Missing database or table name');
		return;
	}

	showConfirmModal(
		'Fix Table - Add Columns',
		'This will add the missing lookup table columns to <strong>' + tblName + '</strong>.<br><br>' +
		'Required columns: title, description, abr, displayOrder, displayClass, userId, orgId, deleted, deletable, icon, additionalInfo1, additionalInfo2<br><br>' +
		'Continue?',
		function() {
			console.log('Calling API with DB:', dbName, 'Table:', tblName);
			$.ajax({
				type: 'POST',
				url: 'xhr/addLookupColumns.php',
				dataType: 'json',
				data: {
					databaseName: dbName,
					tableName: tblName
				},
				success: function(response) {
					console.log('API Response:', response);
					if (response['success'] == 1) {
						// location.reload(); // Refresh the page
						newLocation = "sql1.php?tableSelected="+tblName;
						window.location = newLocation;

					} else {
						alert('Error: ' + response['msg']);
					}
				},
				error: function(xhr, status, error) {
					console.log('AJAX Error:', status, error);
					console.log('Response:', xhr.responseText);
					alert('Error: Could not complete the request');
				}
			});
		}
	);

	TBNM = $("#tableName option:selected").text();
	console.log('***TBNM: '+TBNM);
	if (TBNM != ''){
		tableUpdated();
	}
});

// =============================================================================
// Copy Table List / Lookup Tables Buttons
// =============================================================================
$(document).on('click', '#btnCopyTableList', function() {
	// Read from hidden CSV input (tableListTextarea shows stats-enhanced version)
	var tableList = $('#tableListCSV').val() || $('#tableListTextarea').val();
	navigator.clipboard.writeText(tableList).then(function() {
		var $btn = $('#btnCopyTableList');
		var originalText = $btn.text();
		$btn.text('Copied!');
		setTimeout(function() {
			$btn.text(originalText);
		}, 1500);
	}).catch(function(err) {
		console.error('Failed to copy: ', err);
		alert('Failed to copy to clipboard');
	});
});

$(document).on('click', '#btnCopyLookupList', function() {
	var lookupList = $('#lookupTableListTextarea').val();
	navigator.clipboard.writeText(lookupList).then(function() {
		var $btn = $('#btnCopyLookupList');
		var originalText = $btn.text();
		$btn.text('Copied!');
		setTimeout(function() {
			$btn.text(originalText);
		}, 1500);
	}).catch(function(err) {
		console.error('Failed to copy: ', err);
		alert('Failed to copy to clipboard');
	});
});
