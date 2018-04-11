
<?php
	//The database connection and url stub are in this file
	include_once('api_settings.php');

	//What is today's date?
	$date_today = date('Y-m-d');

	//This query will select calendar events for today
	$query = "SELECT * FROM company_calendar WHERE event_start BETWEEN '". $date_today  ." 00:00:01' AND '". $date_today  ." 23:59:59'";
	$events_today = mysqli_num_rows(mysqli_query($conn, $query));

	//If there is an event, send a notification to all users.
	if($events_today > 0){
		$notify = shell_exec("curl '" . $api_hostname . "notifications.php?email=" . $administrator_email . "&password=" . $administrator_password . "&type=everyone&notify=calendar&timeframe=today'");
	}

?>
