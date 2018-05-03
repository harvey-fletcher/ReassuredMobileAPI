<?php
    //CRM Requires a header
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    //Include the sitesettings and common functions file
    include_once('api_settings.php');
    include_once('common_functions.php');

    //Store the input as a variable
    $input = explode('&', file_get_contents('php://input') );

    //These are the variables
    $Parameters = array();

    //Break up the input parameters
    foreach($input as $variable){
        $variable = explode('=', urldecode($variable));
        $GLOBALS['Parameters'][$variable[0]] = $variable[1];
    }

    //Decide if we can run the requested function
    if(!isset($Parameters['function'])){
        stdout(array("error" => "400", "info" => "You have not specified a function"));
    } else if(!function_exists($Parameters['function'])){
        stdout(array("error" => "400", "info" => "The function you have specified does not exist"));
    } else {
        //Before we do anything, ensure that their is a token provided by CRM
        CRMAuth();

        //Call the requested function
        $GLOBALS['Parameters']['function']();
    }

    //This function will be called when the user takes a lead.
    function TakeLead(){
        //Global Variables
        global $Parameters;
        global $conn;

        //In order to do this, a firstname and surname needs to be provided
        if(!isset($Parameters['firstname']) || !isset($Parameters['surname'])){
            stdout(array("status" => 400, "error" => "You need to provide a firstname and a surname"));
        }

        //The surname might contain +(mgr) or +(sales), remove it
        if(strpos($Parameters['surname'], '+') !== false){
            $Parameters['surname'] = substr($Parameters['surname'], 0, strpos($Parameters['surname'], '+'));
        }

        //Insert the announcement as a MyReassured post
        $query = "INSERT INTO bulletin_posts (`user_id`,`post_body`) VALUES ('1', '" . $Parameters['firstname'] . ' ' . $Parameters['surname']  . " has just taken a lead!\n\nGreat work " . $Parameters['firstname'] . "!')";
        mysqli_query($conn, $query);

        //Get the ID of the inserted row
        $PostBody = mysqli_fetch_array(mysqli_query($conn, "SELECT bp.id AS 'postID', u.firstname, u.lastname, bp.created, l.location_name, t.team_name, bp.post_body FROM bulletin_posts bp JOIN users u ON bp.user_id=u.id JOIN teams t on u.team_id = t.id JOIN locations l on u.location_id=l.id WHERE bp.id=" . mysqli_insert_id($conn)), MYSQLI_ASSOC);

        //Build the data to send over FCM
        $Notification = array(
                "data" => array(
                        "notification_type" => "myreassuredpost",
                        "post" => $PostBody
                    ),
            );

        //Send that over FCM
        sendFCM(array(1), $Notification);
    }

    //Return something
    echo '{"status":"done"}';
