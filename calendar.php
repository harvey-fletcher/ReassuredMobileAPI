<?php
    //Include the API settings file
    include_once('api_settings.php');

    //Include the common functions file
    include_once('common_functions.php');

    //This block of code gets executed for pagination on the calendar events list.
    //It affects the building of the query.
    if(isset($_GET['from_result'])){
        $onwards_of = $_GET['from_result'];
    } else {
        $onwards_of = 0;
    };

    if(isset($_GET['list'])){
        if(isset($_GET['start']) && isset($_GET['end']) ){
            $query = "SELECT c.id, u.firstname, u.lastname, c.event_start, c.event_location, c.event_name, c.event_information FROM company_calendar c JOIN users u ON c.event_organiser=u.id WHERE c.event_start BETWEEN '". $_GET['start']  ." 00:00:01' AND '". $_GET['end']  ." 23:59:59' ORDER BY event_start ASC LIMIT ". $onwards_of .", 6";

            if($result = mysqli_query($conn, $query)){
                //We need somewhere to store all the data we are outputting
                $data = array();

                while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                    $row['event_organiser'] = $row['firstname'] . " " . $row['lastname'];
                    array_push($data, $row);
                }

                //Output the data using the function in common_functions.php
                stdout($data);

            } else {
		stdout(array('Error', 'The server encountered an error whilst querying the database.'));
            }
        } else {
            stdout(array('Error', 'You have not specified a start and end date for the date range.'));
        }
    } else if(isset($_GET['add'])){
        if(isset($_GET['email']) && isset($_GET['password'])){
            //Auth the user here.
            auth($_GET['email'], $_GET['password']);
            $team_id = $GLOBALS['USER']['team_id'];

            if(($team_id == 1) || ($team_id == 2) || ($team_id == 3)){
                if( isset($_GET['event_name']) && isset($_GET['event_organiser']) && isset($_GET['event_start']) && isset($_GET['event_location']) && isset($_GET['event_information']) ){
                    $event_name = addslashes($_GET['event_name']);
                    $event_organiser = $_GET['event_organiser'];
                    $event_start = date("Y-m-d H:i:s", strtotime($_GET['event_start'] . '00:00:01'));
                    $event_location = $_GET['event_location'];
                    $event_information = addslashes($_GET['event_information']);

                    //Insert the row into the database
                    $query = "INSERT INTO company_calendar (`event_name`,`event_organiser`,`event_start`,`event_information`,`event_location`) VALUES ('". $event_name  ."','". $event_organiser ."','". $event_start  ."','". $event_information  ."','". $event_location ."')";
                    $result = mysqli_query($GLOBALS['conn'], $query);

                    if(mysqli_error($GLOBALS['conn'])){
                        stdout(array("status" => 500, "reason" => explode("'", mysqli_error( $GLOBALS['conn']))[1] . " field has an error." ) );
                    }

                    //This is the data we want to send in the notification
                    $data = array(
                            "data" => array(
                                    "notification_type" => "calendar",
                                ),
                        );

                    //This function is located in common_functions.php, it sends the notification
                    sendFCM(array(1), $data);

                    //Finish
                    stdout(array('status' => '200', 'reason' => 'Event received'));
                } else {
                    //Finish, not enough fields.
                    stdout(array('status' => '400', 'reason' => 'You have not specified enough fields.'));
                }
            } else {
                //Finish, permissions error, user group
                stdout(array('status' => '403', 'reason' => 'Your user group cannot perform this action'));
            }
        }
    } else if(isset($_GET['delete'])){
        if(isset($_GET['email']) && isset($_GET['password'])){
            //Auth the user using common_functions.php auth()
            auth($_GET['email'], $_GET['password']);
            $team_id = $GLOBALS['USER']['team_id'];

            //Does the user have the right permissions to delete an event from the calendar?
            if(($team_id == 1) || ($team_id == 2) || ($team_id == 3)){
                if(isset($_GET['id'])){
                    $query = "DELETE FROM company_calendar WHERE id=" . $_GET['id'];
                    $execute = mysqli_query($conn, $query);
                    $result = mysqli_affected_rows($conn);

                    if($result != 0){
                        //Finish, success, row deleted
                        stdout(array('status' => '200', 'reason' => 'Deleted ' . $result . ' event from the company calendar'));
                    } else {
                        //Finish, success, rows already gone.
                        stdout(array('status' => '200', 'reason' => 'Done, there were no rows to delete'));
                    };
                } else {
                    //Finish, bad request, no ID
                    stdout(array('status' => '400', 'reason' => 'Please provide an event ID'));
                }
            }
        } else {
            //Finish, no credentials
            stdout(array('status' => '403', 'reason' => 'You have not given username and password'));
        }
    } else {
        //Finish, no API mode
        stdout(array('status'=>'400', 'reason' => 'You have not specified an API mode. API modes: list, create, delete'));
    }
?>
