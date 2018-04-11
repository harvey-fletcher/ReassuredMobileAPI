<?php

	//The api settings
	include_once('api_settings.php');

	//Initialise data array
	$data = array();

	//Endpoints that don't require a login
	if((isset($_GET['teams']) || isset($_GET['locations'])) && !isset($_GET['social'])){
		$has_login = 1;
	} else {
		$has_login = 0;
	};

	if((isset($_GET['email']) && isset($_GET['password'])) || $has_login == 1){
		if(isset($_GET['list_users'])){
			//This endpoint requires a search term
			if(isset($_GET['search'])){
				//Assign the searchTerm variable because it is shorter
				$searchTerm = $_GET['search'];

				//This is the query that gets used for searching users
				$query = "SELECT u.id, u.firstname, u.lastname, l.location_name FROM users u JOIN locations l ON u.location_id=l.id WHERE u.firstname LIKE '%". $searchTerm."%' OR u.lastname LIKE '%". $searchTerm."%' OR u.email LIKE '%". $searchTerm."%' OR CONCAT(u.firstname, ' ', u.lastname) LIKE '%". $searchTerm  ."%'";

				//Run the query on the server
				$result = mysqli_query($conn, $query);

				//For each of the returned results, add them to the output array.
				while($user_result = mysqli_fetch_array($result, MYSQLI_ASSOC)){
					array_push($data, $user_result);
				}
			} else {
				$data = array(
					"status" => "",
					"reason" => "please specify a search term"
				);
			}
		} else if(isset($_GET['teams'])){

			if(isset($_GET['team_id'])){
				$result = mysqli_query($conn, "SELECT * FROM teams WHERE id=". $_GET['team_id']);
			} else {
				$result = mysqli_query($conn, "SELECT * FROM teams");
			}

			array_push($data, array("status" => "200", "reason" => "Returned a list of teams"));

			while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
				array_push($data, $row);
			}

		} else if(isset($_GET['locations'])){

                        if(isset($_GET['location_id'])){
                                $result = mysqli_query($conn, "SELECT * FROM locations WHERE id=". $_GET['location_id']);
                        } else {
                                $result = mysqli_query($conn, "SELECT * FROM locations");
                        }

                        array_push($data, array("status" => "200", "reason" => "Returned a list of locations"));

                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                                array_push($data, $row);
                        }


		} else if(isset($_GET['social'])) {
		} else {
			$error = array(
				"status" => "403",
				"reason" => "You must provide a primary endpoint (teams, social or list_users)"
			);

			array_push($data, $error);
		};
	} else {
		$error = array(
			"status" => "403",
			"reason" => "The endpoint you are attempting to access requires login credentials."
		);

		array_push($data, $error);
	};

	echo json_encode($data);

?>
