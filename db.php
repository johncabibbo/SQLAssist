<?php
// MySQL Connection - The MySQL user does not need to be root but does require read access to the information_schema database.
$mysql_dsn = "mysql:host=127.0.0.1;port=8889;dbname=information_schema;";
$mysql_user = "root";
$mysql_pass = "root";

// Login Username & Password
$loginUsername = 'root';
$loginPassword = 'root';

// IP Based Security
// If allowedIPList = *, allow connections from anywhere.
// To limit the connection by IP address, set allowedIPList to a comma delimited list of IP addresses.
$allowedIPList = '*';
//$allowedIPList = '127.0.0.1';

// Do not change
$pageTitle = "SQLAssist";
?>