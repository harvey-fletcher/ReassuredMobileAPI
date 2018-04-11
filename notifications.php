<?php

	//Include all our passwords and stuff
	include_once('api_settings.php');

	//Do we have the required URL variables set?
	if(isset($_GET['email']) && $_GET['password']){
		$credentials = 1;
	} else {
		echo '[{"status":"403","info":"you must specify an email and password"}]';
		die();
	}

	//Check if the user exists
	if($credentials == 1){
		//The query that will be used
		$query = "SELECT * FROM users WHERE email='" . $_GET['email']  . "' AND password='" . $_GET['password'] . "'";

		//Execute that query
		$result = mysqli_query($conn, $query);

		//Is the username and password an exact match to 1 row in the database
		if(mysqli_num_rows($result) == 1){
			$authenticated = 1;
		} else {
			echo '[{"status":"403","info":"username or password is incorrect"}]';
			die();
		}
	}

	//What notification do we want to send, and is it a valid value?
	if(isset($_GET['notification_type'])){
		//Store that number in something easy to type
		$type = $_GET['notification_type'];
		
		//Supported notification types
		if($type == "traffic" || $type == "lockout" || $type == "myreassured" || $type == "message" || $type == "late" || $type == "calendar" || $type == "meeting"){
			$valid_type = 1;
		} else {
			echo '[{"status":"403","info":"that is not a valid notification_type"}]';
			die();
		}
	} else {
		echo '[{"status":"400","info":"you must specify a notification_type value"}]';
		die();
	}

	//Has the user specified a user group to send the notification to and is it valid?
	if(isset($_GET['to_group'])){
		$tg = $_GET['to_group'];
		if($tg == "team" || $tg == "all" || $tg == "individual"){
			$has_group == 1;
		} else {
			echo '[{"status":"400","info":"that is not a valid to_group value"}]';
	                die();
		}
	} else {
		echo '[{"status":"400","info":"you must specify a to_group value"]}';
                die();
	}

	//Construct data array for mode team application_tokens constructor
	if($_GET['to_group'] == "team"){

		//This function needs the team_id parameter
		if(!isset($_GET['team_id'])){
			echo '[{"status":"400","info":"you must specify a team_id value"]}';
	                die();
		}

		$query = "SELECT at.application_token FROM application_tokens at JOIN users u ON u.id=at.user_id WHERE u.team_id = " . $_GET['team_id'];
		$result = mysqli_query($conn, $query);

		//A string of tokens
		$tokens = "";

		//How many rows
		$total_tokens = mysqli_num_rows($result);

		//Start at row 1
		$current_token = 1;

		//Build the string of tokens
		while($application_token = mysqli_fetch_array($result, MYSQLI_ASSOC)['application_token']){
			//Join that token to the string
			$tokens .= '"' . $application_token . '"';
			
			//If there's a next row, separate the two with a comma
			if($current_token < $total_tokens){
				$tokens .= ",";
			}

			//Go to the next row
			$current_token++;
		};
	}

	//This block will list all the tokens to send to everyone
	if($_GET['to_group'] == 'all'){
		$query = "SELECT at.application_token FROM application_tokens at JOIN users u ON u.id=at.user_id";
                $result = mysqli_query($conn, $query);

                //A string of tokens
                $tokens = "";

                //How many rows
                $total_tokens = mysqli_num_rows($result);

                //Start at row 1
                $current_token = 1;

                //Build the string of tokens
                while($application_token = mysqli_fetch_array($result, MYSQLI_ASSOC)['application_token']){
                        //Join that token to the string
                        $tokens .= '"' . $application_token . '"';

                        //If there's a next row, separate the two with a comma
                        if($current_token < $total_tokens){
                                $tokens .= ",";
                        }

                        //Go to the next row
                        $current_token++;
                };
	}

        //Construct data array for mode single_user application_tokens constructor
        if($_GET['to_group'] == "individual"){

                //This function needs the team_id parameter
                if(!isset($_GET['user_id'])){
                        echo '[{"status":"400","info":"you must specify a user_id value"]}';
                        die();
                }

                $query = "SELECT at.application_token FROM application_tokens at JOIN users u ON u.id=at.user_id WHERE u.id = " . $_GET['user_id'];
                $result = mysqli_query($conn, $query);
		$application_token = mysqli_fetch_array($result, MYSQLI_ASSOC)['application_token'];


                //Join that token to the string
                $tokens = '"' . $application_token . '"';
        }


	//Different message types require different data
	if($_GET['notification_type'] == 'message'){
		//Sending a private message requires the message_body parameter
		if(!isset($_GET['message_body'])){
                        echo '[{"status":"400","info":"you must specify a message_body value"]}';
                        die();
                }

		//Build the identity of the user that sent the message
		$query = "SELECT id, firstname, lastname FROM users WHERE email = '" . $_GET['email'] . "' AND password = '" . $_GET['password'] . "'";	
		$result = mysqli_query($conn, $query);
		$user_array = mysqli_fetch_array($result, MYSQLI_ASSOC);
		$from_user_id = $user_array['id'];
		$from_user_name = $user_array['firstname'] . " " . $user_array['lastname'];
		$sent_time = date('H:i');

		//Build the request
		$data = '{"data":{"notification_type":"'.$_GET['notification_type'].'","from_user_id":"'. $from_user_id  .'","from_user_name":"'. $from_user_name  .'","sent_time":"'. $sent_time  .'","message_body":"' . $_GET['message_body']  . '"},"registration_ids":[' . $tokens  . ']}';

	} else if($_GET['notification_type'] == 'late'){
		if(!isset($_GET['user_id'])){
			echo '[{"status":"400","info":"you must specify a user_id value"]}';
                        die();
		}

		//Get the affected user's name
		$query = "SELECT firstname, lastname FROM users WHERE id = " . $_GET['user_id'];
		$result = mysqli_query($conn, $query);
		$user_details = mysqli_fetch_array($result, MYSQLI_ASSOC);
		$affected_user_name = $user_details['firstname'] . " " . $user_details['lastname'];

		$data = '{"data":{"notification_type":"'.$_GET['notification_type'].'","affected_user":"' . $affected_user_name  . '"},"registration_ids":['. $tokens  .']}';
	} else {
		//This is the data that gets sent in the curl request is built, except for private message, done above
		$data = '{"data":{"notification_type":"'.$_GET['notification_type'].'"},"registration_ids":['. $tokens  .']}';
	}
	
	//Build the curl request command WITH the data in it
	$command = "curl -X POST --Header 'Authorization: key=". $notifications_key  ."' --Header 'Content-Type: application/json' -d '" . $data . "' 'http://fcm.googleapis.com/fcm/send'";

	//Execute the curl request $command and store it as an array
        $output = array();
	$output = json_decode(shell_exec($command));

	echo '[{"status":"200","info":"notifications sent"}]';

?>
