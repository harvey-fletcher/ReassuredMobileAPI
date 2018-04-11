<?php
	//Include all the API settings
	include_once('api_settings.php');

	//This is where we store the details of the user making the request
	$user = array();
	
	//This is the data
	$data = array();

	//This is the action that the user has requested
	$action = "";

	//First of all, we will need the username and password, if we have them, check the user exists
	if(!isset($_GET['email']) || !isset($_GET['password']) ){
		$data = array("status"=>"403", "reason"=>"You must provide username and password");
		$data = array($data);
		done($data);
	} else {
		//This is the query used to select the data
		$query = "SELECT * FROM users WHERE email='". $_GET['email'] ."' AND password='". $_GET['password'] ."'";
		$result = mysqli_query($conn, $query);
		
		if(mysqli_num_rows($result) != 1){
			$data = array("status"=>"403", "reason"=>"Email or password incorrect");
        	        $data = array($data);
	                done($data);
		} else {
			$user = mysqli_fetch_array($result, MYSQLI_ASSOC);
		}
	}

	//We will also need an action
	if(!isset($_GET['action'])){
		$data = array("status"=>"403", "reason"=>"You must provide an action");
                $data = array($data);
                done($data);
	} else {
		$action = $_GET['action'];
	}

	//This block of code will add a new post
	if($action == 'post'){
		//We will need something to put into the post body
		if(!isset($_GET['post_body'])){
			$data = array("status"=>"400", "reason"=>"You must provide a post body");
        	        $data = array($data);
	                done($data);
		}

		//Insert the new post
		$query = "INSERT INTO bulletin_posts (`user_id`,`post_body`) VALUES ('". $user['id'] ."','". addslashes($_GET['post_body']) ."')";
		$result = mysqli_query($conn, $query);

		//Get the post so we can send it to all users
		$query = "SELECT bp.id AS 'postID', u.firstname, u.lastname, bp.created, l.location_name, t.team_name, bp.post_body FROM bulletin_posts bp JOIN users u ON bp.user_id=u.id JOIN teams t on u.team_id = t.id JOIN locations l on u.location_id=l.id ORDER BY bp.id DESC";
		$new_post = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);

		//We need to add a comments array so that the client can read it
		$new_post['comments'] = json_encode(array());

		//Make the post body friendly so that it doesn't break the JSONArray
		$new_post['post_body'] = str_replace("'", "<singlequote>", $new_post['post_body']);
		$new_post['post_body'] = str_replace("\"", "<doublequote>", $new_post['post_body']);

		//Now we need to put that into a json encoded string so we can return it to the clients
		$data = json_encode($new_post, JSON_FORCE_OBJECT);

		//We want to send this new post to all the clients, so we need to get their tokens
		$tokens = getAllTokens($conn);

		//This is what we will send in the FCM request
		$CURLdata = '{"data":{"notification_type":"myreassuredpost","post":' . json_encode($data) . '},"registration_ids":['. $tokens  .']}';

		//Send the FCM notification
		sendCURL($notifications_key, $CURLdata);
	}

	//This block of code will add a new post
        if($action == 'comment'){
                //We will need something to put into the post body
                if(!isset($_GET['comment_body'])){
                        $data = array("status"=>"400", "reason"=>"You must provide a comment body");
                        $data = array($data);
                        done($data);
                }

		//We will need to know the post id
                if(!isset($_GET['postID'])){
                        $data = array("status"=>"400", "reason"=>"You must provide a postID");
                        $data = array($data);
                        done($data);
                }

                //Insert the new comment
                $query = "INSERT INTO post_comments (`user_id`,`post_id`,`comment_body`) VALUES ('". $user['id'] ."','". $_GET['postID'] ."','". addslashes($_GET['comment_body']) ."')";
                $result = mysqli_query($conn, $query);

                //Get the comment back with some extra data
                $query = "SELECT c.post_id AS 'postID', c.created, c.comment_body, u.firstname, u.lastname, l.location_name, t.team_name FROM post_comments c JOIN users u on c.user_id=u.id JOIN teams t on t.id=u.team_id JOIN locations l on l.id=u.location_id WHERE c.user_id='". $user['id'] ."'ORDER BY c.id DESC";
                $new_comment = mysqli_fetch_array(mysqli_query($conn, $query), MYSQLI_ASSOC);

                //Make the post body friendly so that it doesn't break the JSONArray
                $new_comment['comment_body'] = str_replace("'", "<singlequote>", $new_comment['comment_body']);
                $new_comment['comment_body'] = str_replace("\"", "<doublequote>", $new_comment['comment_body']);

                //Now we need to put that into a json encoded string so we can return it to the clients
                $data = json_encode($new_comment, JSON_FORCE_OBJECT);

                //We want to send this new post to all the clients, so we need to get their tokens
                $tokens = getAllTokens($conn);

                //This is what we will send in the FCM request
                $CURLdata = '{"data":{"notification_type":"myreassuredcomment","comment":' . json_encode($data) . '},"registration_ids":['. $tokens  .']}';

                //Send the FCM notification
                sendCURL($notifications_key, $CURLdata);
        }

	if($action == 'OnDemandRefresh'){
		//Since we only want to send the posts to the user that requested them, build a new array with that single token.
		$tokens[] = mysqli_fetch_array(mysqli_query($conn, "SELECT application_token FROM application_tokens WHERE user_id=" . $user['id']), MYSQLI_ASSOC)['application_token'];

		//Get all the posts from the database
		$query = "SELECT bp.id AS 'postID', u.firstname, u.lastname, bp.created, l.location_name, t.team_name, bp.post_body FROM bulletin_posts bp JOIN users u ON bp.user_id=u.id JOIN teams t on u.team_id = t.id JOIN locations l on u.location_id=l.id ORDER BY bp.id ASC";
		$posts_result = mysqli_query($conn, $query);

		//For each post, go and get the comments, then send the post to the device
		while($post = mysqli_fetch_array($posts_result, MYSQLI_ASSOC)){
			//The post doesn't currently have a place for the comments to go, so we need to add it.
			$post['comments'] = array();

			//Get all the comments based on the current post ID
			$query = "SELECT c.created, c.comment_body, u.firstname, u.lastname, l.location_name, t.team_name FROM post_comments c JOIN users u ON c.user_id=u.id JOIN teams t ON t.id=u.team_id JOIN locations l ON l.id=u.location_id WHERE c.post_id=" . $post['postID'] . " ORDER BY c.id DESC";
			$comments_result = mysqli_query($conn, $query);

			//Push each comment into the comments section of that post
			while($comment = mysqli_fetch_array($comments_result, MYSQLI_ASSOC)){
				array_push($post['comments'], $comment);
			}

			//Build the data message for FCM to send out the notification
			$CURLdata = '{"data":{"notification_type":"myreassuredpost","post":' . json_encode($post) . '},"registration_ids":'. json_encode($tokens)  .'}';

			//Send out the notification
			SilentSendCURL($notifications_key, $CURLdata);
		}
		
		//Respond and die.
		echo '[{"status":"200","info":"The server sent you all the posts."}]';
		die();
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

	//This function will send the CURLdata via FCM but do so quietly
	function SilentSendCURL($notifications_key, $CURLdata){
                //Build the curl request command WITH the data in it
                $command = "curl -X POST --Header 'Authorization: key=". $notifications_key  ."' --Header 'Content-Type: application/json' -d '" . $CURLdata . "' 'http://fcm.googleapis.com/fcm/send'";

                //Execute the curl request $command and store it as an array
                $output = array();
                $output = json_decode(shell_exec($command));
        }

	//This function gets all the tokens
	function getAllTokens($conn){
		//This query gets ALL the tokens
		$query = "SELECT * FROM application_tokens";
		$result = mysqli_query($conn, $query);

		//A string of tokens
                $tokens = "";

                //How many rows
                $total_tokens = mysqli_num_rows($result);

                //Start at row 1
                $current_token = 1;

                //Build the string of tokens
                while($application_token = mysqli_fetch_array($result, MYSQLI_ASSOC)['application_token']){
                        //Join that token to the string
                        $tokens .= '"' . $application_token . '"';

                        //If there's a next row, separate the two with a comma
                        if($current_token < $total_tokens){
                                $tokens .= ",";
                        }

                        //Go to the next row
                        $current_token++;
                };

		//Return tokens
		return $tokens;
	}

	//Return the data in a JSON Array
	function done($data){
		echo json_encode($data);
		die();
	};

?>
