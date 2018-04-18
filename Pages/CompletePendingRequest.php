<?php

    //We need the API Settings file for the DB connection
    include_once('../api_settings.php');

    //We need the common functions file for FCM
    include_once('../common_functions.php');

    //Get the pending action matching the passkey
    $result = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM pending_actions WHERE passkey='" . $_GET['PassKey'] . "' AND completed=0 AND created >= NOW() - INTERVAL 2 HOUR"), MYSQLI_ASSOC);

    //Execute the query that is in the pending action
    mysqli_query($conn, $result['action']);

    //How many rows
    if(mysqli_affected_rows($conn) == 1){
        $query = "UPDATE pending_actions SET completed=1 WHERE id=" . $result['id'];
        mysqli_query($conn, $query);

        $query = "SELECT * FROM users WHERE id=". $result['affects_user_id'];
        $USER = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);

        //We need to build a notification with the users new details
        $Notification = array(
                "data" => array(
                        "notification_type" => "pending_action_completion",
                        "email" => $USER['email'],
                        "password" => $USER['password'],
                        "firstname" => $USER['firstname'],
                        "lastname" => $USER['lastname'],
                        "team_id" => (int)$USER['team_id'],
                        "location_id" => (int)$USER['location_id'],
                    ),
            );

        //Send the notification to the users device
        sendFCM(array(3, $result['affects_user_id']), $Notification);

        include_once('PendingActionCompletedSuccess.html');
    } else {
        //Display a failure page
        include_once('PendingActionCompletedFailure.html');
    }
