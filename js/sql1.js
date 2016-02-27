$('#databaseName').on('change',function(e) {
	databaseUpdated(0);
});

databaseUpdated = function(TP){
	DBID = $('#databaseName').val();
	$('#dbSelected').val(DBID);


	if ( DBID != 0 ) {
		$.ajax({
			type:'post',
			url: 'api/tableList.php',
			dataType: 'json',
			data: {
				databaseName:DBID
			},
			success:function(data){ 
				console.log(data);

				if (data['success'] == 1){
					$('#rTable').show();

					$('#tableName').empty();
					$('#tableName').append($('<option>', {
					    value: '0',
					    text: 'Choose a Table'
					}));


					$.each(data['tableList'], function(){
						$('#tableName').append($('<option>', {
						    value: this['tableName'],
						    text: this['tableName']
						}));
					});

					$("#SQLContent").html('');

					if (TP == 1){
						console.log('1')
						y = $('#tableSelected').val();
						if (y != ''){
							console.log('2')
							$('#tableName').val(y);
							tableUpdated();
						}
					}

				} else {
					alert("Invalid API Call");
				}
			},
			fail:function(data){ 
				console.log(data);
				alert("Invalid Login");
			}
		})
	} else {
		$('#rTable').hide();
	}
};

$('#tableName').on('change',function(e) {
	tableUpdated();
});

tableUpdated = function(){
	DBID = $('#databaseName').val();
	TBID = $('#tableName').val();

	$('#tableSelected').val(TBID);

	if ( DBID != 0 && TBID != 0) {
		$.ajax({
			type:'post',
			url: 'api/tableInfo.php',
			dataType: 'json',
			data: {
				databaseName:DBID,
				tableName:TBID
			},
			success:function(data){ 
				console.log(data);
				if (data['success'] == 1){
					
					$("#SQLContent").html('');
					content = '';

					// Column List
					columnList = '';
					columnList2 = '';
					columnListUpdate = '';
					columnListWhere = '';
					$.each(data['tableInfo']['columns'], function(){
						columnList += ', ' + this['columnName']
						columnList2 +=  "', '" + this['columnName']
						columnListUpdate +=  this['columnName'] + " = '" + this['columnName'] + "', ";
						columnListWhere +=  'and ' + this['columnName'] + " = '" + this['columnName'] + "' ";
					});
					columnList = columnList.substr(2,columnList.length-2);
					columnList2 = columnList2.substr(3,columnList2.length-3) + "'";
					columnListUpdate = columnListUpdate.substr(0,columnListUpdate.length-2)
					columnListWhere = columnListWhere.substr(4,columnListWhere.length-4)

					content = '<p><span class="header1">COLUMNS</span><br> ' + columnList + '</p>' + content;

					// Select
					select = '<p><span class="header1">SELECT</span><br>select ' + columnList + ' from ' + data['tableName'] + ';</p>';
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
					bind = '<p class="SQLContentLast"><span >PHP BINDING VARIABLES</span><br>';

					counter = 0;
					$.each(data['tableInfo']['columns'], function(){
						counter = counter + 1;

						bind += "$statement->bindParam(':" + this['columnName'] + "', $" + this['columnName'] + ", PDO::PARAM_STR);<br>";
					});

					bind += '</p>';
					content += bind;


					// Table
					tlb = "<table id='colTbl' cellpadding='5' cellspacing='0' >";
					tlb += '<tr id="tblHeader">';
					tlb += '<td>Column</td>';
					tlb += '<td>Type</td>';
					tlb += '<td>Length</td>';
					tlb += '</tr>';

					$.each(data['tableInfo']['columns'], function(){
						tlb += '<tr id="tblRow">';
						tlb += '<td> ' + this['columnName'] + ' </td>';
						tlb += '<td> ' + this['columnType'] + ' </td>';
						tlb += '<td align="right"> ' + this['length'] + ' </td>';
						tlb += '</tr>';
					});
					tlb += "</table>";
					content += tlb;

					$("#SQLContent").html(content);

				} else {
					alert("Invalid API Call");
				}
			},
			fail:function(data){ 
				console.log(data);
				alert("Invalid Login");
			}
		})
	} else {
		
	}

};

x = $('#dbSelected').val();
if (x != ''){
	$('#databaseName').val(x);
	databaseUpdated(1);
}



