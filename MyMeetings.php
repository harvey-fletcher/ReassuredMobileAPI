<?php
    //Include all the API settings
    include_once('api_settings.php');

    //Include common functions so we can auth the user
    include_once('common_functions.php');

    //This is the data
    $data = array();

    //This is the action that the user has requested
    $action = "";

    //Take the received JSON array and convert it into a useable php array.
    $_POST = json_decode($_POST['data'], true);

    //Authorise the user
    auth($_POST['email'], $_POST['password']);

    //We will also need an action
    if(!isset($_POST['action'])){
        $data = array("status"=>"403", "reason"=>"You must provide an action");
        $data = array($data);
        done($data);
    } else {
        //Give action a shortname
	$action = $_POST['action'];
    }

    if($action == 'ListPersonalMeetings'){
        //Set up the date ranges that we need
        $tomorrow_start = date('Y-m-d H:i:s', mktime(0, 0, 00, date('m'), date('d')+1, date('Y')));
        $tomorrow_end = date('Y-m-d H:i:s', mktime(23, 59, 59, date('m'), date('d')+1, date('Y')));

        //We want to structure the data so that there are 3 categories. Today, tomorrow, and beyond
        $data = array(
            "today" => array(),
            "tomorrow" => array(),
            "future" => array()
        );

        //Used to shorten queries
        $get_meetings = "SELECT * FROM scheduled_meetings m WHERE m.start_time ";

        //Get the meetings for today.
        $query = $get_meetings . "BETWEEN '". date('Y-m-d H:i:s') ."' AND '". date('Y-m-d') ." 23:59:59'";
        $result = mysqli_query($conn, $query);

        //Put today's meetings into the array
        while($meeting = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            //We need to add all the attendees on the meeting
            $invited = json_decode($meeting['invited']);
            $attending = json_decode($meeting['attending']);
            $declined = json_decode($meeting['declined']);

            //Can the user accept the meeting?
            if(in_array($GLOBALS['USER']['id'], $invited) && (!in_array($GLOBALS['USER']['id'], $attending) || in_array($GLOBALS['USER']['id'], $declined))){
                $meeting['can_accept'] = 1;
            } else {
                $meeting['can_accept'] = 0;
            }

            //If the user is the organizer, is invited to, or attending the meeting, display it in the today[] array.
            if(($GLOBALS['USER']['id'] == $meeting['organizer']) || in_array($GLOBALS['USER']['id'], $invited) || in_array($GLOBALS['USER']['id'], $attending)){
                array_push($data['today'], json_encode($meeting, JSON_FORCE_OBJECT));
            }
        }

        //Get the meetings for tomorrow.
        $query = $get_meetings . "BETWEEN '". $tomorrow_start ."' AND '". $tomorrow_end ."'";
        $result = mysqli_query($conn, $query);

        //Put tomorrow's meetings into the array
        while($meeting = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            //We need to add all the attendees on the meeting
            $invited = json_decode($meeting['invited']);
            $attending = json_decode($meeting['attending']);
            $declined = json_decode($meeting['declined']);

            //Can the user accept the meeting?
            if(in_array($GLOBALS['USER']['id'], $invited) && (!in_array($GLOBALS['USER']['id'], $attending) || in_array($GLOBALS['USER']['id'], $declined))){
                $meeting['can_accept'] = 1;
            } else {
                $meeting['can_accept'] = 0;
            }

            if(($GLOBALS['USER']['id'] == $meeting['organizer']) || in_array($GLOBALS['USER']['id'], $invited) || in_array($GLOBALS['USER']['id'], $attending)){
                array_push($data['tomorrow'], json_encode($meeting, JSON_FORCE_OBJECT));
            }
        }

        //Get the meetings for dates in the future
        $query = $get_meetings . "> '". $tomorrow_end ."'";
        $result = mysqli_query($conn, $query);

        //Put tomorrows meetings on the array
        while($meeting = mysqli_fetch_array($result, MYSQLI_ASSOC)){
	    //We need to add all the attendees on the meeting
            $invited = json_decode($meeting['invited']);
            $invited_user_ids = $meeting['invited'];
            $attending = json_decode($meeting['attending']);
	    $attending_user_ids = $meeting['attending'];
            $declined = json_decode($meeting['declined']);

            //Can the user accept the meeting?
            if(in_array($GLOBALS['USER']['id'], $invited) && (!in_array($GLOBALS['USER']['id'], $attending) || in_array($GLOBALS['USER']['id'], $declined))){
                $meeting['can_accept'] = 1;
            } else {
                $meeting['can_accept'] = 0;
            }

            if(($GLOBALS['USER']['id'] == $meeting['organizer']) || in_array($GLOBALS['USER']['id'], $invited) || in_array($GLOBALS['USER']['id'], $attending)){
                array_push($data['future'], json_encode($meeting, JSON_FORCE_OBJECT));
            }
        }

        //Return the data
        echo json_encode(array($data));
    }

    //This code block will accept a meeting
    if($action == 'AcceptMeeting'){
        if(!isset($_POST['meetingID'])){
            $data = array("status"=>"403", "reason"=>"You must provide a meetingID");
            $data = array($data);
	    done($data);
        }

        //Get the current accepted users for that meeting
        $query = "SELECT * FROM scheduled_meetings WHERE id=" . $_POST['meetingID'];
        $result = mysqli_query($conn, $query);
        $currentlyaccepted = mysqli_fetch_array($result, MYSQLI_ASSOC)['attending'];

        $currentlyaccepted = json_decode($currentlyaccepted);

        array_push($currentlyaccepted, (int)$GLOBALS['USER']['id']);

        $currentlyaccepted = json_encode(array_values($currentlyaccepted), 1);

        //Update the database
        $query = "UPDATE scheduled_meetings SET attending='" . $currentlyaccepted . "' WHERE id=". $_POST['meetingID'];
        $result = mysqli_query($conn, $query);

        echo '[{"status":"200","reason":"Success"}]';

    }
	
    //This code block will decline a meeting
    if($action == 'DeclineMeeting'){
        if(!isset($_POST['meetingID'])){
            $data = array("status"=>"403", "reason"=>"You must provide a meetingID");
            $data = array($data);
            done($data);
        }

        //Get the current accepted users for that meeting
        $query = "SELECT * FROM scheduled_meetings WHERE id=" . $_POST['meetingID'];
        $result = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);
        $currentlyinvited = $result['invited'];
        $currentlydeclined = $result['declined'];
        $currentlyaccepted = $result['attending'];

        $currentlyinvited = json_decode($currentlyinvited);
        $currentlydeclined = json_decode($currentlydeclined);
        $currentlyaccepted = json_decode($currentlyaccepted);

	//Delete the user from the accepted array if it exists
        if (($key = array_search($GLOBALS['USER']['id'], $currentlyaccepted)) !== false) {
            unset($currentlyaccepted[$key]);
        }

        //Remove the user from the invited list
        if (($key = array_search($GLOBALS['USER']['id'], $currentlyinvited)) !== false) {
            unset($currentlyinvited[$key]);
        }

        //Insert the user into the declined array
        array_push($currentlydeclined, (int)$GLOBALS['USER']['id']);

        //Turn the arrays back into JSON for storage
        $currentlyaccepted = json_encode(array_values($currentlyaccepted), 1);
	$currentlydeclined = json_encode(array_values($currentlydeclined), 1);
	$currentlyinvited = json_encode(array_values($currentlyinvited), 1);

        //Update the database
        $query = "UPDATE scheduled_meetings SET invited='". $currentlyinvited ."', attending='" . $currentlyaccepted . "', declined='". $currentlydeclined ."' WHERE id=". $_POST['meetingID'];
        $result = mysqli_query($conn, $query);

        echo '[{"status":"200","reason":"Success"}]';

    }

    if($action == 'usersearch'){
        if(!isset($_POST['searchterm'])){
            $data = '{"status":"400","info":"You must specify a search term"}';
            done($data);
        } else {
	    $searchTerm = $_POST['searchterm'];
        }

        //Get the users that match the search term
        $query = "SELECT u.id, u.firstname, u.lastname, l.location_name FROM users u JOIN locations l ON u.location_id=l.id WHERE u.firstname LIKE '%". $searchTerm."%' OR u.lastname LIKE '%". $searchTerm."%' OR u.email LIKE '%". $searchTerm."%' OR CONCAT(u.firstname, ' ', u.lastname) LIKE '%". $searchTerm  ."%'";
        $results = mysqli_query($conn, $query);

        $data = array();

        while($result = mysqli_fetch_array($results, MYSQLI_ASSOC)){
            array_push($data, json_encode($result, JSON_FORCE_OBJECT));
        }

        echo json_encode($data);

    }

    if($action == 'CheckAvailabilityRooms'){
        if(!isset($_POST['eventStart'])){
            $data = '{"status":"400","info":"You must specify a eventStart"}';
            done($data);
        }

        if(!isset($_POST['duration'])){
            $data = '{"status":"400","info":"You must specify a duration"}';
            done($data);
        }

        //Initialise a curl
        $curl = curl_init();

        //We need to set some options up now
        $PostFields = "eventStart=" . $_POST["eventStart"] . '&duration=' . $_POST["duration"];
        JoanSetOpts($curl, "find", $PostFields);

        //Execute the curl to get a list of available rooms
        $AvailableRooms = json_decode(curl_exec($curl), true)['rooms'];

        //Return the output
        echo '[{"AvailableRooms":"' . addslashes(json_encode($AvailableRooms)) . '"}]';
    }

    if($action == "MeetingRoomBook"){
        //Check we have all the data that we need to book out the meeting room
        if(!isset($_POST['start_time'])){
            $data = '{"status":"400","info":"You must specify a start_time"}';
            done($data);
        }

        if(!isset($_POST['duration'])){
            $data = '{"status":"400","info":"You must specify a duration"}';
            done($data);
        }

	if(!isset($_POST['venue'])){
            $data = '{"status":"400","info":"You must specify a venue"}';
            done($data);
        }

        if(!isset($_POST['name'])){
            $data = '{"status":"400","info":"You must specify a name"}';
            done($data);
        }

        //Since we have all the required data, store them as short names
        $eventStart = $_POST['start_time'];
        $interval = "PT" . $_POST['duration']. "M";
        $end = (new DateTime($eventStart))->add(new DateInterval($interval))->format('Y-m-d H:i:s');
        $source = $_POST['venue'];
        $title = $_POST['name'];

        //Initialise a curl
        $curl = curl_init();

        //We need to set some options up now
        $PostFields = "source=" . $source . "&start=" . $eventStart . "&end=" . $end . "&title=" . $title;
        JoanSetOpts($curl, "book", $PostFields);

        //Execute the curl to get a list of available rooms
        $JoanResponse = json_decode(curl_exec($curl), true);

        //Display a success to say the meeting is booked.
	echo '[{"status":"200"}]';

	//We want to invite the other people to the meeting
	InviteAttendees($conn, $GLOBALS['USER']['firstname'], $GLOBALS['USER']['lastname'], $_POST['invitees'], $notifications_key);

	//Insert the new meeting into our table
	$query = "INSERT INTO scheduled_meetings (`organizer_id`,`location`,`title`,`start_time`,`duration`,`invited`) VALUES ('".$GLOBALS['USER']['id']."','".$_POST['venueName']."','".$_POST['name']."','". $_POST['start_time'] ."','".$_POST['duration']."','".json_encode($_POST['invitees'])."')";
	mysqli_query($conn, $query);

        //Done
        die();
    }

    //This function gets user IDs of those invited, gets their tokens, and sends them a meeting notification
    function InviteAttendees($conn, $firstname, $lastname, $invitees, $notifications_key){
        //This is a string of tokens for FCM notifications
        $tokens = array();

        //Add each user's token to the list so that they will get a notificaation
        foreach($invitees as $invitee){
            $query = "SELECT application_token FROM application_tokens WHERE user_id=".$invitee;
            $result = mysqli_query($conn, $query);

            while($item = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                array_push($tokens, $item['application_token']);
            }
        }

        $CURLdata = '{"data":{"notification_type":"meeting","information":"' . $firstname . ' ' . $lastname . ' has invited you to their meeting."},"registration_ids":'. json_encode($tokens) .'}';

	sendCURL($notifications_key, $CURLdata);
    }


    //This function will send the CURLdata via FCM;
    function sendCURL($notifications_key, $CURLdata){
        //Build the curl request command WITH the data in it
        $command = "curl -X POST --Header 'Authorization: key=". $notifications_key  ."' --Header 'Content-Type: application/json' -d '" . $CURLdata . "' 'http://fcm.googleapis.com/fcm/send'";

        //Execute the curl request $command and store it as an array
        $output = array();
        $output = json_decode(shell_exec($command));

        echo '[{"status":"200","info":"notifications sent"}]';
    }

    //Return the data in a JSON Array
    function done($data){
        echo json_encode($data);
        die();
    };

    // This function is only used in this controller so we have not put it in common functions
    // it sets up the required options for making a curl request into the JOAN api.
    function JoanSetOpts($curl, $page, $postfields){
        curl_setopt($curl, CURLOPT_URL, $GLOBALS["external_endpoints"]["joan"][$page]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer " . $GLOBALS['JoanAuthToken'],
                "Cache-Control: no-cache",
                "Content-Type: application/x-www-form-urlencoded",
            )
        );

        return $curl;
    }

?>
