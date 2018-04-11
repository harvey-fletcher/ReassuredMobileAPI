<?php
	include_once('api_settings.php');

	if(isset($_GET['email']) && isset($_GET['password'])){
		$data = array();
		
		if(isset($_GET['login']) && isset($_GET['token'])){
			$query = "SELECT * FROM users WHERE email='". $_GET['email'] ."' AND password='" . $_GET['password'] . "'";
			$result = mysqli_query($conn, $query);
			$data = mysqli_fetch_array($result, MYSQLI_ASSOC);
		
			if(mysqli_num_rows($result) != 1){
				$data = array(
					"status"=>"403", 
					"code"=>"2", 
					"reason"=>"Username or password incorrect"
				);
			} else {
				//Everything is OK
				$data["status"] = "200";

				//Insert a new token or refresh the existing one
				$query = "SELECT * FROM application_tokens WHERE user_id='". $data['id']  ."'";

				//Delete any rows with matching tokens so that notifications are only displayed on the currently signed in user.
				$query = "DELETE FROM application_tokens WHERE application_token='" . $_GET['token']  . "' OR user_id = " . $data['id'];
				$result = mysqli_query($conn, $query);

				//Insert the new token
				$query = "INSERT INTO application_tokens (`user_id`,`application_token`) VALUES ('". $data['id']  ."','". $_GET['token']  ."')";
				$result = mysqli_query($conn, $query);
			}
		} else if(isset($_GET['create'])){
			if(isset($_GET['firstname']) && isset($_GET['lastname']) && isset($_GET['team_id']) && isset($_GET['location_id'])){
				//Store the variables as shortnames for ease
				$email = $_GET['email'];
				$password = $_GET['password'];
				$firstname = $_GET['firstname'];
				$lastname = $_GET['lastname'];
				$team = $_GET['team_id'];
				$location = $_GET['location_id'];

				$valid = array(1,"Everything OK");

				//Check the email is for reassured
				if(substr($email, -16) != '@reassured.co.uk'){
					$valid = array(0, "Please use your reassured email address");
				}

				if($firstname == ""){
					$valid = array(0, "Firstname cant be blank");
				}

				if($lastname == ""){
					$valid = array(0, "Lastname cant be blank");
				}

				if($password == ""){
					$valid = array(0, "Password cant be null");
				}

				if($team == ""){
					$valid = array(0, "You must supply a team ID");
				}

				if($location == ""){
					$location = 1;
				}

				//If everything is OK insert the user
				if($valid[0] == 1){
					//Check if that email is already used
					$user_exists = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE email='" . $email . "'"));

					//Error if there is 1 or more rows
					if($user_exists == 0){
						//The query to insert users
						$insert_user = "INSERT INTO users (`email`,`password`,`firstname`,`lastname`,`team_id`,`location_id`) VALUES ('". $email  ."','". $password  ."','". $firstname  ."','". $lastname  ."','". $team ."','". $location  ."')";

						//Execute that query
						$run_query = mysqli_query($conn, $insert_user);
						
						//How many rows were affected?
						$success = mysqli_affected_rows($conn);
						
						//Was it successful?
						if($success == 1){
							$data = array(
								"status" => "200",
								"reason" => "New user created."
							);
						} else {
							$data = array(
								"status" => "500",
								"reason" => "Something went wrong. Please try again"
							);
						}
					} else {
						$data = array(
                	                                "status" => "500",
        	                                        "reason" => "That email address is already in use"
	                                        );
					}
				} else {
					$data = array(
						"status" => "500",
						"reason" => $valid[1]
					);
				}
			} else {
				$data = array(
					"status" => "500",
					"reason" => "User creation failed because you did not specify enough values. Please consult the API docs"
				);
			};
		} else if(isset($_GET['login']) && !isset($_GET['token'])){
			$data = array("status"=>"403", "code"=>"1", "reason"=>"Please provide a username and password and an application instance token, which can be null.");
		} else {
			$data = array(
				"status" => "500",
				"reason" => "You have not specified an endpoint. Endpoints are login, create. Please consult the API docs for the users API"
			);
		}
	} else {
		$data = array("status"=>"403", "code"=>"1", "reason"=>"Please provide a username and password and an application instance token, which can be null.");
	}

	echo json_encode($data);
?>
