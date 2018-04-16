<?php

    //Every page that uses this, assume we also need api_settings
    include_once('api_settings.php');

    //This function is for authorising users
    function auth($email, $password){
        //This is the query used to select user details
        $query = "SELECT * FROM users WHERE email='" . $email . "' AND password='". $password ."' AND activated='1'";

        //Execute the query
        $result = mysqli_query($GLOBALS["conn"], $query);

        //We only want to success if there is a distinct match
        if(mysqli_num_rows($result) != 1){
            stdout(array("error" => "username or password incorrect", "status" => "403"));
        }

        //Fetch the user details and store them as a global
        $GLOBALS['USER'] = mysqli_fetch_array($result, MYSQLI_ASSOC);

        //Say that the user authentication is OK
        $GLOBALS['USER']['status'] = 200;
    }

    //This function sends a message to the FCM service and sends it to the specified $tokens
    function sendFCM($group, $data){
        /**
            Here is a list of groups. Groups are specified by an array.

            1 - This group sends to everyone. `$group = array(1)`
            2 - This group is everyone in a team. `$group = array(2, $team_id)`
            3 - This group sends to a specific user ID. `$group = array(3, $user_id)`
            4 - This group sends to everyone at a specified location. `$group = array(4, $location_id)`
        **/

        //Each different group requires a different query.
        if($group[0] == 1)$query = "SELECT * FROM application_tokens";
        if($group[0] == 2)$query = "SELECT a.* FROM application_tokens a JOIN users u ON u.id=a.user_id WHERE u.team_id=" . $group[1];
        if($group[0] == 3)$query = "SELECT * FROM application_tokens WHERE user_id=" . $group[1];
        if($group[0] == 4)$query = "SELECT a.* FROM application_tokens a JOIN users u ON u.id=a.user_id WHERE a.location_id=" . $group[1];

        //Execute the query
        $mysqli_tokens = mysqli_query($GLOBALS['conn'], $query);

        //This will be an array of tokens
        $tokens = array();

        //For each result in the resultset, add the token on to the $tokens array
        while($row = mysqli_fetch_array($mysqli_tokens, MYSQLI_ASSOC)){
            array_push($tokens, $row['application_token']);
        }

        //Add the registration IDs to the data
        $data["registration_ids"] = $tokens;

        //Build up the post request
        $curl = curl_init();
        curl_setopt_array($curl, array(
                CURLOPT_URL => $GLOBALS['external_endpoints']['fcm'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => array(
                        "Authorization: key=" . $GLOBALS['external_keys']['fcm'],
                        "Cache-Control: no-cache",
                        "Content-Type: application/json",
                    ),
            ));

        //Make the post request to the FCM service
        curl_exec($curl);
    }

    //Send an email in HTML format
    function HTMLMailer($to, $body, $subject, $sender){
        //Set up some headers for the mail.
        $headers = "From: " . $sender . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        //Send the HTML formatted email
        mail($to, $subject, $body, $headers);
    }

    //This function prints and then dies
    function stdout($data){
        echo json_encode($data);
        die();
    }
