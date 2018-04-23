<?php

    /**
        W A R N I N G

        This file has been deprecated (as of 2018-04-23) and should no longer be used.

        Instead of using this file, all API endpoints should now include common_functions.php and
        make calls into the sendFCM() function. That would be done as follows:

            //for $to_recipients, see grouping in common_functions.php
            $to_recipients = array(1, 1);

            $notification = array(
                    "data" => array(
                            "notification_type" => "notification type as string",
                            "message" => $message
                        ),
                );

            sendFCM($to_recipients, $notification);

        DO NOT MAKE ANY CALLS TO THIS ENDPOINT FROM THE APPLICATION SIDE

    **/

    //Get the settings file
    include_once('api_settings.php');

    //Get the common_functions file
    include_once('common_functions.php');

    //Do we have the required URL variables set?
    if(!isset($_GET['email']) || !$_GET['password']){
        //Echo error and exit program.
        stdout(array(array("status" => 400, "info" => "you must specify an email address and password")));
    }

    //Auth the user because we need to check they have permission to access this endpoint.
    auth($_GET['email'], $_GET['password']);

    //What notification do we want to send, and is it a valid value?
    if(isset($_GET['notification_type'])){
        //Store that number in something easy to type
        $type = $_GET['notification_type'];

        //Supported notification types
        if($type != "late"){
	    stdout(array(array("status" => 400, "info" => "that notification_type is deprecated or does not exist")));
	}
    } else {
        stdout(array(array("status" => 400, "info" => "you must specify a notification_type value")));
    }

    //Has the user specified a user group to send the notification to and is it valid?
    if(isset($_GET['to_group'])){
        if($_GET['to_group'] != "team"){
            stdout(array(array("status" => 400, "info" => "that endpoint is deprecated or does not exist")));
        }
    } else {
        stdout(array(array("status" => 400, "info" => "you must specify a to_group")));
    }

    //Build a notification to send
    $Notification = array(
            "data" => array(
                    "notification_type" => "late",
                    "affected_user" => $GLOBALS['USER']['firstname'] . " " . $GLOBALS['USER']['lastname'],
                ),
        );

    //Make a call to common_functions.php to send a notification
    sendFCM(array(2, $GLOBALS['USER']['team_id']), $Notification);

    //Return a success
    stdout(array(array("status" => "200", "info" => "Your team has been informed")));
?>
