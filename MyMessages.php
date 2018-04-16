<?php

    /**
        This API file receives data from clients, and sends them to their intended recipient.
        Messages that cross through this API will also be stored in the database table `user_messages`,
        so that the users are able to refresh their own messages after a sign in, and if
        the need arises, the company can check back through the message logs.
    **/

    //First of all, we need to include the API settings file, which contains the database connection, and all external service keys.
    include_once('api_settings.php');

    //We need to include the common settings file
    include_once('common_functions.php');

    //For some reason, the postman program used for testing was having difficulty putting things in post['data'] so we've added
    //this line so that it takes it from regular PHP input
    if(!isset($_POST['data']))$_POST['data'] = file_get_contents("php://input");
    $_POST = json_decode( $_POST['data'], 1 );

    //We need to check that the client is authorised to make the request, to do this, they need a username and password.
    if( !isset($_POST['email']) || !isset($_POST['password'])  ){
        stdout(array(array("status"=>"403","info"=>"You need to supply a username and password.")));
    } else {
        auth($_POST['email'], $_POST['password']);
    }

    //Check the requested function exists, if yes, do it, else error.
    if(function_exists($_POST['action'])){
        $_POST['action']();
    } else {
        stdout(array("status" => "400", "info" => "function doesn't exist"));
    }

    //This is the code block for sending a new message out over FCM to a specific user
    function send(){
        //We are going to insert the new message into the database, assign all the variables a shortname
        $from_id = $GLOBALS['USER']['id'];
        $to_id = $_POST['to_user_id'];
        $body = $_POST['message_body'];
        $from_name = $GLOBALS['USER']['firstname'] . ' ' . $GLOBALS['USER']['lastname'];

        //Build a query to insert into the `user_messages` table
        $query = "INSERT INTO `user_messages` (`from_user_id`,`to_user_id`,`message_body`) VALUES ('". $from_id ."','". $to_id ."','". mysqli_escape_string($GLOBALS['conn'], $body) ."')";

        //Execute the query to insert the new message
        mysqli_query($GLOBALS['conn'], $query);

        //We need to build the data that will be sent in the messae
        $NotificationData = array(
                "data" => array(
                        "notification_type" => "message",
                        "from_user_id" => $from_id,
                        "from_user_name" => $from_name,
                        "sent_time" => date('H:i'),
                        "message_body" => $body,
                    ),
            );

        //Broadcast the message, this function is in common_functions.php
        SendFCM(array(3, $to_id), $NotificationData);

        //Echo success
        stdout(array(array("status" => "200", "info" => "The message has been sent.")));
    }

    function refresh(){
        //Build a query to select ALL the messages that user has sent or receieved.
        $query = "SELECT to_user_id as to_id, from_user_id as from_id, sent_time as sent, message_body as message FROM `user_messages` WHERE to_user_id=" . $GLOBALS['USER']['id'] . " OR from_user_id=". $GLOBALS['USER']['id'];
        $messages = mysqli_query($GLOBALS['conn'], $query);

        //Loop through all the messages to build the array of conversations
        $user_conversations_with = array();
        $conversations_array = array();

        //For every message, structure it with additional detail such as firstname and lastname
        while($message = mysqli_fetch_array($messages, MYSQLI_ASSOC)){
            //If the requesting user did not send the message, we will need to execute this
            if($message['from_id'] != $GLOBALS['USER']['id']){
                //There is a chance that MYSQL put the id as a string, change it to an int
                $message['user_id'] = (int)$message['from_id'];

                //Find the user details of the user that sent the message
                $query = "SELECT * FROM users WHERE id=" . $message['from_id'];
                $result = mysqli_query($GLOBALS['conn'], $query);
                $UD = mysqli_fetch_array($result, MYSQLI_ASSOC);

                //Build up the full name of the user that sent the message
                $message['user_name'] = $UD['firstname'] . ' ' . $UD['lastname'];

                //The message was inbound (client side uses this to chosse alignment and colour)
                $message['direction'] = 0;
            } else {
                //This block is when the requesting user DID send the message
                //There is a chance that MYSQL put the id as a string, change it to an int
                $message['user_id'] = (int)$message['to_id'];

                //Find the user details of the user that sent the message
                $query = "SELECT * FROM users WHERE id=" . $message['to_id'];
                $result = mysqli_query($GLOBALS['conn'], $query);
                $UD = mysqli_fetch_array($result, MYSQLI_ASSOC);

                //Assemble the full name of the user that sent the message
                $message['user_name'] = $UD['firstname'] . ' ' . $UD['lastname'];

                //The message was outward. (client side uses this to chosse alignment and colour)
                $message['direction'] = 1;
             }

             //This stops a notification being generated on the client
             $message['notification_id'] = 0;

             //Put the timestamp in the right format
             $message['sent'] = date('H:i', strtotime($message['sent']));

             //Client side has arrays so that new messages can be added at specific
             //positions and re ordered to most recent. Ensure that we are sending the
             //messages in the correct order so that they associate to the right conversation.
             if(!in_array((int)$message['user_id'], $user_conversations_with)){
                 array_push($user_conversations_with, (int)$message['user_id']);
                 array_push($conversations_array, array($message));
             } else {
                 $position = array_search($message['user_id'], $user_conversations_with);
                 array_push($conversations_array[$position], $message);
             }
        }

        //Build the message array
        $NotificationData = array(
                "data" => array(
       	                "notification_type" => "refreshMessages",
                        "user_conversations_with" => $user_conversations_with,
               	      	"conversations_array" => $conversations_array,
       	       	    ),
            );

        //Broadcast the message
        sendFCM(array(3, $GLOBALS['USER']['id']), $NotificationData);

	//Echo success
        stdout(array(array("status" => "200", "info" => "The message has been sent.")));
    }
?>
