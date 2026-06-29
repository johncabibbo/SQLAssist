$(function(){

	var isIOS = ((/iphone|ipad/gi).test(navigator.appVersion));
	var myDown = isIOS ? "touchstart" : "mousedown";
	var myUp = isIOS ? "touchend" : "mouseup";


	/* -- NAVIGATION COLLAPSE -- */
	/* -------------------------------------------------------------------------------------- 
	$("header span").on(myUp, function(e) {
		if ( $('#content').hasClass('open') == true ) {
			$('.open').removeClass('open', 500, "easeOutQuint", function(){
				$(this).removeAttr("class");
			});					
		} else {

			$('#content').addClass('open', 500, "easeInQuint");
			$('header').addClass('open', 500, "easeInQuint");
			e.stopPropagation();
		}
	});
	*/

	/* -- SWAP SEARCH FILTERS -- */
	/* -------------------------------------------------------------------------------------- */
	$("#filters > ul li").on(myUp, function(e) {
		$(this).parent().children('li').removeClass('current');	
		$(this).addClass('current');

		$("#filters fieldset").hide();
		if ( $(this).attr('data-filter') == '1' ) {
			$("#filters fieldset:nth-of-type(1)").show();
			$("#filters form > p").show();
			$('#pageSelected').val('1');
			$("#data").show();
		}
		if ( $(this).attr('data-filter') == '2' ) {
			// $("#filters fieldset:nth-of-type(1)").hide();
			$( "#mainTab li:nth-child(2)" ).removeAttr( "style" );
			$("#filters fieldset:nth-of-type(2)").show();
			$("#filters form > p").hide();
			$('#pageSelected').val('2');
			$("#data").show();
			
		}
		if ( $(this).attr('data-filter') == '3' ) {
			$("#filters fieldset:nth-of-type(3)").show();
			$("#filters form > p").hide();
			$('#pageSelected').val('3');
			$("#data").hide();
		}
		if ( $(this).attr('data-filter') == '4' ) {
			$("#filters fieldset:nth-of-type(4)").show();
			$("#filters form > p").show();
			$('#pageSelected').val('4');
			$("#data").show();
		}
		return false;
	});

	/* -- SWAP ASC & DESC -- */
	/* -------------------------------------------------------------------------------------- */
	$("th").on(myUp, function(e) {
		
		col = this.getAttribute('data-col');
		$("#sortCol").val(col);
		
		if ( $(this).attr('class') == 'asc' ) {
			$(this).attr('class', 'desc');
			$("#sortDirection").val('desc');
			console.log('desc');
			
		} else {
			$('th').removeClass();
			$(this).addClass('asc');
			$("#sortDirection").val('asc')
			console.log('asc');
		}
		
		mainLegResult();
		
		return false;
	});

	/* -- PAGINATION SELECTION -- */
	/* -------------------------------------------------------------------------------------- */
	$("#data > ul li").on(myUp, function(e) {

		$('#data > ul li').removeClass();
		$(this).addClass('current');

		return false;
	});


	/* -- INPUTS -- hiding placeholder text -- */
	/* -------------------------------------------------------------------------------------- */
	$("body").on({
		focus: function() {
			$(this).data('placeholder', $(this).attr('placeholder')); // Store for blur
	    	$(this).attr('placeholder','');
	    },
	    blur: function() {
	        $(this).attr('placeholder',$(this).data('placeholder'));
	    }
	}, "input");		


	/* -- INPUTS -- prevent alpha characters in numeric field -- */
	/* -------------------------------------------------------------------------------------- */
	$("body").on({
		keypress: function(e) {
		
			//if the letter is not digit then return false and don’t type anything
			if( e.which!=8 && e.which!=0 && (e.which<48 || e.which>57)) {
				return false;
			}
			
		}
	}, ".numeric");
	
	pu = function(pg){
		setTimeout(function(){
			var myWindow = window.open(pg, "_blank", "location=0, titlebar=0, toolbar=no, scrollbars=no, resizable=no, top=20, left=20, width=300, height=200");
		 }, 500);
	}
});