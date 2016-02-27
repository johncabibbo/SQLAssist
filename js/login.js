$("#loginButton").click(function(e){
	
	username = $("#username").val();
	pwd = $("#pwd").val();
	
	if (username.length <= 3 ){
		alert("Username must be 4 characters or longer");
	} else if (pwd.length <= 3){
		alert("Password must be 4 characters or longer");
	} else{
		$("#loginForm").submit();
	}
	event.preventDefault();
})