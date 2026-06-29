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

$('.modelFunctionDesc').on('change',function(e) {

  	modelFunctionDesc = $(this).val();
    docModelFunctionId = this.getAttribute('data-id');

    // console.log('docModelFunctionId: '+docModelFunctionId);
    // console.log('modelFunctionDesc: '+modelFunctionDesc);
    
    $.ajax({
        type:'GET',
        url: 'xhr/modelFunctUpdate.php',
        dataType: 'json',
        data: {
          docModelFunctionId:docModelFunctionId,
          modelFunctionDesc:modelFunctionDesc
        },
        success: function(data){
          console.log("OK");
        }
    })
});


docModelFunctionAddUpdate = function(){
  $.ajax({
        type:'GET',
        url: 'xhr/docModelFunctionAddUpdate.php',
        dataType: 'json',
        data: {},
        success: function(data){
        }
    })
}

$( document ).ready(function() {
  var isIOS = ((/iphone|ipad/gi).test(navigator.appVersion));
  var myDown = isIOS ? "touchstart" : "mousedown";
  var myUp = isIOS ? "touchend" : "mouseup";

  docModelFunctionAddUpdate();

  /*  -------------------------------------------------
    Start Bindings
    -------------------------------------------------
  */

  $(".modelFuntA").on(myUp, function(e) {
    id = $(this).attr("data-id")    

    $("#modelFunctSimple_"+id).hide();
    $("#modelFunctEditable_"+id).show();

    event.preventDefault();
  });

  $(".modelFuntB").on(myUp, function(e) {
    id = $(this).attr("data-id")   

    $("#modelFunctSimple_"+id).show();
    $("#modelFunctEditable_"+id).hide(); 

    event.preventDefault();
  });

  $('#modelSelected').on('change', function () {
    $("#modelForm").submit();

    event.preventDefault();
  });

  $('#showAll').on(myUp, function(e) {
    console.log("Show");
    $(".modelFunctSimple").hide();
    $(".modelFunctEditable").show(); 

    docModelFunctionAddUpdate();

    event.preventDefault();
  });    

  $('#hideAll').on(myUp, function(e) {
    console.log("Hide");
    $(".modelFunctSimple").show();
    $(".modelFunctEditable").hide(); 

    event.preventDefault();
  });

  $('#sync').on(myUp, function(e) {
    window.open('https://mbr.teamfl.org/xhr/docModelFunctionAddUpdate.php');

    event.preventDefault();
  }); 


  // docModelFunctionAddUpdate();
});
