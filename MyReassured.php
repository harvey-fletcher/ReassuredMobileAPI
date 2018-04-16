<?php
    //Include all the API settings
    include_once('api_settings.php');

    //Include all the common functions
    include_once('common_functions.php');

    //This is the action that the user has requested
    $action = "";

    //First of all, we will need the username and password, if we have them, check the user exists
    if(!isset($_GET['email']) || !isset($_GET['password']) ){
        stdout(array(array("status" => 403, "reason" => "You must provide a username and password")));
    } else {
        auth($_GET['email'], $_GET['password']);
        $user = $GLOBALS['USER'];
    }

    //We will also need an action
    if(!isset($_GET['action'])){
        stdout(array(array("status" => 400, "reason" => "You must provide an action")));
    } else {
        $action = $_GET['action'];
    }

    //This block of code will add a new post
    if($action == 'post'){
        //We will need something to put into the post body
        if(!isset($_GET['post_body'])){
            stdout(array(array("status"=>400, "reason"=>"You must provide a post body")));
        }

        //Insert the new post
        $query = "INSERT INTO bulletin_posts (`user_id`,`post_body`) VALUES ('". $user['id'] ."','". addslashes($_GET['post_body']) ."')";
        $result = mysqli_query($conn, $query);

        //Get the post so we can send it to all users
        $query = "SELECT bp.id AS 'postID', u.firstname, u.lastname, bp.created, l.location_name, t.team_name, bp.post_body FROM bulletin_posts bp JOIN users u ON bp.user_id=u.id JOIN teams t on u.team_id = t.id JOIN locations l on u.location_id=l.id WHERE bp.id=" . mysqli_insert_id($conn);
        $new_post = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);

        //We need to add a comments array so that the client can read it
        $new_post['comments'] = json_encode(array());

        //Build the notification that will be sent to all the clients
        $Notification = array(
                "data" => array(
                        "notification_type" => "myreassuredpost",
                        "post" => $new_post,
                    ),
            );

        //Send the notifications to all the clients using the function in common_functions.php
        sendFCM(array(1), $Notification);
    }

    //This block of code will add a new post
    if($action == 'comment'){
        //We will need something to put into the post body
        if(!isset($_GET['comment_body'])){
            stdout(array(array("status"=>"400", "reason"=>"You must provide a comment body")));
        }

        //We will need to know the post id
        if(!isset($_GET['postID'])){
            stdout(array(array("status"=>"400", "reason"=>"You must provide a postID")));
        }

        //Insert the new comment
        $query = "INSERT INTO post_comments (`user_id`,`post_id`,`comment_body`) VALUES ('". $user['id'] ."','". $_GET['postID'] ."','". addslashes($_GET['comment_body']) ."')";
        $result = mysqli_query($conn, $query);

        //Get the comment back with some extra data
        $query = "SELECT c.post_id AS 'postID', c.created, c.comment_body, u.firstname, u.lastname, l.location_name, t.team_name FROM post_comments c JOIN users u on c.user_id=u.id JOIN teams t on t.id=u.team_id JOIN locations l on l.id=u.location_id WHERE c.user_id='". $user['id'] ."'ORDER BY c.id DESC";
        $new_comment = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);

        //Build a notification to send over FCM
        $Notification = array(
                "data" => array(
                        "notification_type" => "myreassuredcomment",
                        "comment" => $new_comment,
                    ),
            );

        //Make the request to FCM using common_functions.php function
        sendFCM(array(1), $Notification);
    }

    if($action == 'OnDemandRefresh'){
        //Get the 25 most recent posts from the database
        $query = "SELECT bp.id AS 'postID', u.firstname, u.lastname, bp.created, l.location_name, t.team_name, bp.post_body FROM bulletin_posts bp JOIN users u ON bp.user_id=u.id JOIN teams t on u.team_id = t.id JOIN locations l on u.location_id=l.id ORDER BY bp.id DESC LIMIT 25";
        $posts_result = mysqli_query($conn, $query);

        //Since ordering the posts like that makes them display in the wrong order on clients, the array needs flipping
        $posts = array();
        while($post = mysqli_fetch_array($posts_result, MYSQLI_ASSOC)){
            array_unshift($posts, $post);
        }

        //For each post, go and get the comments, then send the post to the device
        foreach($posts as $post){
            //The post doesn't currently have a place for the comments to go, so we need to add it.
            $post['comments'] = array();

            //Get all the comments based on the current post ID
            $query = "SELECT c.created, c.comment_body, u.firstname, u.lastname, l.location_name, t.team_name FROM post_comments c JOIN users u ON c.user_id=u.id JOIN teams t ON t.id=u.team_id JOIN locations l ON l.id=u.location_id WHERE c.post_id=" . $post['postID'] . " ORDER BY c.id DESC";
            $comments_result = mysqli_query($conn, $query);

            //Push each comment into the comments section of that post
            while($comment = mysqli_fetch_array($comments_result, MYSQLI_ASSOC)){
                array_push($post['comments'], $comment);
            }

            //Build the notification to send over FCM
            $Notification = array(
                    "data" => array(
                            "notification_type" => "myreassuredpost",
                            "post" => $post,
                        ),
                );

            //Send the notification over FCM. This function is in common_functions.php
           sendFCM(array(3, $user['id']), $Notification);
        }

        //Respond and die.
        stdout(array(array("status" => 200, "info"=>"The server sent you all the posts")));
    }

?>
