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

    //This function is used to authenticate the Reassured CRM
    function CRMAuth(){
        //Check a token was provided, die if not
        if(!isset($GLOBALS['Parameters']['token'])){
            stdout(array("error" => 403, "info" => "Please tell me who you are..."));
        }

        //Does the token match what should be here
        if($GLOBALS['Parameters']['token'] != $GLOBALS['external_keys']['crm']){
            stdout(array("error" => 403, "info" => "Wait! You're not the CRM! Your token is wrong!"));
        }
    }

    //This function builds a meeting invite for outlook.
    function outlookMeetingRequest($invited, $location, $date, $startTime, $endTime, $subject, $desc){
        /**
            INPUT PARAMETERS

            $attendees = array(
                    user_id_of_a_user,
                    user_id_of_another_user,
                    and_so_on
                );

            $location = where your meeting is

            $startTime = Ymd\THis
            $endTime = Ymd\THis

            $subject = the name of your meeting

            $desc = description of your meeting

        **/

        $attendees = array();

        //If the user has specified offline invitees, build their details here
        if(isset($_POST['offlineInvitees'])){
            foreach($_POST['offlineInvitees'] as $olInvitee){
                $invitee = explode('.', $olInvitee);
                array_push($attendees, array($invitee[0] . " " . $invitee[1], $invitee[0] . '.' . $invitee[1] . '@reassured.co.uk'));
            }
        }

        //Get the details of all the online invitees
        foreach($invited as $invitee){
            //Online invitees do NOT have the id of 0
            if($invitee != 0){
                $invitee = mysqli_fetch_array(mysqli_query($GLOBALS['conn'], "SELECT firstname, lastname, email FROM users WHERE id =" . $invitee), MYSQLI_ASSOC);
                array_push($attendees, array($invitee['firstname'] . " " . $invitee['lastname'], $invitee['email']));
            };
        }

        //This is the organiser details
        $organizer          = $GLOBALS['USER']['firstname'] . " " . $GLOBALS['USER']['lastname'];
        $organizer_email    = $GLOBALS['USER']['email'];

        $headers = 'Content-Type:text/calendar; Content-Disposition: inline; charset=utf-8;\r\n';

        $message =  "BEGIN:VCALENDAR\r\n";
        $message .= "VERSION:2.0\r\n";
        $message .= "PRODID:-//Deathstar-mailer//theforce/NONSGML v1.0//EN\r\n";
        $message .= "METHOD:REQUEST\r\n";
        $message .= "BEGIN:VEVENT\r\n";
        $message .= "UID:" . md5(uniqid(mt_rand(), true)) . "example.com\r\n";
        $message .= "DTSTAMP:" . gmdate('Ymd').'T'. gmdate('His') . "Z\r\n";

        $message .= "DTSTART:". $startTime ."Z\r\n";
        $message .= "DTEND:". $endTime . "Z\r\n";

        $message .= "SUMMARY:".$subject."\r\n";
        $message .= "ORGANIZER;CN=".$organizer.":mailto:".$organizer_email."\r\n";
        $message .= "LOCATION:".$location."\r\n";
        $message .= "DESCRIPTION:".$desc."\r\n";

        foreach($attendees as $attendee){
            $message .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN".$attendee[0].";X-NUM-GUESTS=0:MAILTO:".$attendee[1]."\r\n";
        }
        $message .= "END:VEVENT\r\n";
        $message .= "END:VCALENDAR\r\n";

        $headers .= $message;

        foreach($attendees as $attendee){
            mail($attendee[1], $subject, $message, $headers);
        }
    }
