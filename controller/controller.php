<?php
	require("../constants/constants.php");
	require("../utilities/utilities.php");
?>
<?php
	if(!function_exists('logoutUser')){
		function logoutUser($conn, $dbname, $username, $token){
			$conn->autocommit(false);
			if($stmt = $conn -> prepare("DELETE FROM ".$dbname.".logged_users WHERE username = ? AND token = ?;")){
				$stmt->bind_param("ss", $username, $token);
				$stmt->execute();
				if (mysqli_errno($conn)==0){
					$stmt->close();
				}else{
					$stmt->close();
					$conn->autocommit(true);
					return false;
				}
			}else{
				$conn->autocommit(true);
				return false;
			}
			$conn->commit();
			$conn->autocommit(true);
			return true;
		}
	}
	
	session_start();
	error_reporting(E_ALL);
	date_default_timezone_set($DEFAULT_TIMEZONE);
	
	//SESSION
	if(!isset($_SESSION["username"]) || !isset($_SESSION["token"])){
		$result["status"] = "KO";						
		$result["message"] = "Session already expired";
		echo json_encode($result);
		die();
	}
	//DB
	if(!openDBUser($connection)){
		$result["status"] = "KO";
		$result["message"] = "Server error!";
		echo json_encode($result);
		die();
	}
	//SESSION VALIDATION
	if(!validateLoggedUser($connection, $DEFAULT_DB, $_SESSION['username'], $_SESSION['token'])){
		//save LOG action to db if something is wrong - idea
		$result["status"] = "KO";
		$result["message"] = "Session expired";
		echo json_encode($result);
		die();
	}
	
	switch ($_POST["action"]) {
		case $LOGOUT_USER_ACTION:
		
			if(isset($_POST["username"]) && $_POST["username"] != ''){
				
				$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);//easy clean, no time
			
				if($username == $_SESSION['username']){
					if(!logoutUser($connection, $DEFAULT_DB, $username, $_SESSION['token'])){
						$result['status'] = 'KO';
						$result['message'] = 'Logout failed!';
						//save LOG action to db if something is wrong - idea
						echo json_encode($result);
						die();
					}else{
						session_unset();
						session_destroy();
						
						$result['status'] = 'OK';
						$result['message'] = 'Logout successful!';
						echo json_encode($result);
						die();
					}
				}else{
					$result['status'] = 'KO';
					$result['message'] = 'Incorrect params!';
					//save LOG action to db if something is wrong - idea
					echo json_encode($result);
					die();
				}
			}else{
				$result['status'] = 'KO';
				$result['message'] = 'Params not set!';
				//save LOG action to db if something is wrong - idea
				echo json_encode($result);
				die();
			}
			
			break;
		default:
			$result["status"] = "KO";
			$result["message"] = "Invalid request!";
			echo json_encode($result);
			die();
	}
	
	
?>