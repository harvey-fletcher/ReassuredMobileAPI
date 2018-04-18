<?php

    //First of all, we need to include all API settings
    include_once('api_settings.php');

    //Include common functions
    include_once('common_functions.php');

    //Take input from the correct header, decode it
    $GLOBALS['_POST'] = json_decode(trim(urldecode(file_get_contents('php://input')), "=data"), true);

    //If we are in debug, set api_settings.php, log the data
    if($GLOBALS['debug']){
        file_put_contents(__DIR__ . '/data.log', $GLOBALS['_POST']);
    }

    //Now we need to check that the user has provided all the necessary credentials to access this service
    if( !isset($GLOBALS['_POST']['email']) || !isset($GLOBALS['_POST']['password']) ){
        echo '[{"status":"403","info":"You must provide a username and password"}]';
    } else {
        //Auth using the function in common_functions.php
        auth($GLOBALS['_POST']['email'], $GLOBALS['_POST']['password']);
    }

    //Check the user specified an action
    if(!isset($GLOBALS['_POST']['action'])){
        stdout(array("error" => 400, "info" => "You need to supply an action"));
    }

    //If the requested action doesn't exist, error
    if(!function_exists($GLOBALS['_POST']['action'])){
        stdout(array("error" => 400, "info" => "Action does not exist."));
    }

    //Execute the requested function
    $GLOBALS['_POST']['action']();

    //This will send a notification to all registered devices, and request their locations
    function RequestAll(){
        //Build the request notification
        $Notification = array(
                "data" => array(
                        "notification_type" => "locationrequest",
                        "information" => "The server has requested the location of this device",
                    ),
            );

        //Send the notification using the common_functions.php
        sendFCM(array(1), $Notification);

        //Return a success
        stdout(array(array("status" => 200, "info" => "Requested device locations")));
    }

    //This code block finds all users that are within a five mile radius of the requesting user's current location.
    function FindNearMe(){
        if(!isset($GLOBALS['_POST']['latitude']) || !isset($GLOBALS['_POST']['longitude'])){
            stdout(array(array("status" => 400, "info" => "You must supply latitude and longitude values")));
        }

        //This query will find all users within a five mile radius of the supplied location
        $query = "SELECT u.id, u.firstname, u.lastname,	u.last_known_lat, u.last_known_long, ( ACOS( COS( RADIANS( ".$GLOBALS['_POST']['latitude']." ) ) * COS ( RADIANS (u.last_known_lat) ) * COS ( RADIANS (u.last_known_long) - RADIANS( ".$GLOBALS['_POST']['longitude']." ) ) + SIN ( RADIANS( ".$GLOBALS['_POST']['latitude']." ) ) * SIN ( RADIANS( u.last_known_lat ) )	) * 3959 ) AS distance FROM users u WHERE u.id!=". $GLOBALS['USER']['id'] ." AND display_location=1 ORDER BY distance ASC LIMIT 100";
        $result = mysqli_query($GLOBALS['conn'], $query);

        //If we are in debug, set api_settings.php, log the query
        if($GLOBALS['debug']){
            file_put_contents(__DIR__ . '/data.log', $query);
        }

        //Initialise the data array so that we can add items into it.
        $data = array();

        //For each user within five miles, add them to the dataset
        while($user_details = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            if($user_details['distance'] < 5){
                unset($user_details['distance']);
                unset($user_details['last_known_lat']);
                unset($user_details['last_known_long']);
                array_push($data, $user_details);
            }
        }

        //Return a result
        stdout($data);
    }

    function SendLocation(){
        if(!isset($GLOBALS['_POST']['latitude']) || !isset($GLOBALS['_POST']['longitude'])){
            stdout(array(array("status" => "400", "info" => "You need to supply a latitude and longitude")));
        }

        //Update the user's location on the DB
        $query = "UPDATE users SET last_known_lat='" . $GLOBALS['_POST']['latitude'] . "', last_known_long='". $GLOBALS['_POST']['longitude'] ."', display_location=". $GLOBALS['_POST']['show'] ." WHERE id=" . $GLOBALS['USER']['id'];
        $result = mysqli_query($GLOBALS['conn'], $query);

        //If we are in debug, set api_settings.php, log the query
        if($GLOBALS['debug']){
            file_put_contents(__DIR__ . '/data.log', $query);
        }

        stdout(array("status" => 200, "info" => "Request Successful"));
    }

    //Sending out one of these will cause a new local conversation to be initialised on the requesting device and the receiving device
    function SendNewJourneyRequest(){
        //Get the details of the user we are sending the message to
        $ThirdParty = mysqli_fetch_array(mysqli_query($GLOBALS['conn'], "SELECT * FROM users WHERE id=" . $GLOBALS['_POST']['to_user']), MYSQLI_ASSOC);

        //We need to build the name of the requesting user
        $SelfName = $GLOBALS['USER']['firstname'] . " " . $GLOBALS['USER']['lastname'];

        //Build the message we are going to send
        $InitialMessage =  "Hello " . $ThirdParty['firstname'] . ",\nI would like to car share with you. Please message me back so we can arrange this.\n\nThanks,\n" . $SelfName;

        //We are going to send an FCM notification so will need some data
        $Notifications = array(
                0 => array(
                        "data" => array(
                                "notification_type" => "message",
                                "from_user_name" => $SelfName,
                                "from_user_id" => $GLOBALS['USER']['id'],
                                "message_body" => $InitialMessage,
                                "direction" => 0,
                                "sent_time" => date('H:i'),
                            ),
                    ),
                1 => array(
                        "data" => array(
                                "notification_type" => "message",
                                "from_user_name" => $ThirdParty['firstname'] . " " . $ThirdParty['lastname'],
                                "from_user_id" => $GLOBALS['_POST']['to_user'],
                                "message_body" => $InitialMessage,
                                "direction" => 1,
                                "sent_time" => date('H:i'),
                            ),
                        ),
            );
        //Send each message individually
        sendFCM(array(3, $ThirdParty['id']), $Notifications[0]);
        sendFCM(array(3, $GLOBALS['USER']['id']), $Notifications[1]);

        //We want to insert the message to user_messages so it is displayed on refresh
        $query = "INSERT INTO user_messages (`from_user_id`,`to_user_id`,`sent_time`,`message_body`) VALUES (". $GLOBALS['USER']['id'] . ", ". $ThirdParty['id'] .", '". date('Y-m-d H:i:s') ."', '". $InitialMessage ."')";
        mysqli_query($GLOBALS['conn'], $query);

        //If we are in debug, set api_settings.php, log the query
        if($GLOBALS['debug']){
            file_put_contents(__DIR__ . '/data.log', $query);
        }

        //If there's an error, output it
        if(mysqli_error($GLOBALS['conn'])){
            $Outcome = mysqli_error($GLOBALS['conn']);
        } else {
            $Outcome = "Notifications Sent!";
        }

        stdout(array(array("status" => 200 , "info" => $Outcome)));

    }
?>
