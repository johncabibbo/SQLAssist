/* Set the date we're counting down to
  <div id="countDownExp"></div>
  <input type="hidden" id="userSessionLogin" value="<?php echo $_SESSION['userSessionLogin'] ?>">
  <input type="hidden" id="userSessionExpire" value="<?php echo $_SESSION['userSessionExpire'] ?>">

  $_SESSION['userSessionLogin'] = $date->format('Y-m-d H:i:s');
  $_SESSION['userSessionExpire'] = date("Y-m-d H:i:s", strtotime('+2851200 seconds'));
*/
d = $("#userSessionExpire").val();

//console.log('Expires: '+d);

var countDownDate = new Date(d).getTime();

// Update the count down every 1 second
var x = setInterval(function() {

  // Get today's date and time
  var now = new Date().getTime();

  // Find the distance between now and the count down date
  var distance = countDownDate - now;

  // Time calculations for days, hours, minutes and seconds
  var days = Math.floor(distance / (1000 * 60 * 60 * 24));
  var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
  var seconds = Math.floor((distance % (1000 * 60)) / 1000);

  // Display the result in the element with id="countDownExp"
  document.getElementById("countDownExp").innerHTML = "Logout in:<br>" + days + "d " + hours + "h "
  + minutes + "m " + seconds + "s ";

  // If the count down is finished, write some text
  if (distance < 0) {
    clearInterval(x);
    document.getElementById("countDownExp").innerHTML = "EXPIRED";
  }
}, 1000);