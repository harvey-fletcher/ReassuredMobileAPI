
<?php
	//The database connection and url stub are in this file
	include_once('../api_settings.php');

        //We need the common functions file
        include_once('../common_functions.php');

	//What is today's date?
	$date_today = date('Y-m-d');

	//This query will select calendar events for today
	$query = "SELECT * FROM company_calendar WHERE event_start BETWEEN '". $date_today  ." 00:00:01' AND '". $date_today  ." 23:59:59'";
	$events_today = mysqli_num_rows(mysqli_query($conn, $query));

	//If there is an event, send a notification to all users.
	if($events_today > 0){
            //Build the data to send
            $data = array(
                    "data" => array(
                            "notification_type" => "events",
                            "count" => (int)$events_today + 1,
                        ),
                );

            //Send the notification, this function is in common_functions.php
            sendFCM(array(1), $data);
	}

?>
