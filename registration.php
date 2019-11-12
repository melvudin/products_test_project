<?php
	require("constants/constants.php");
	require("utilities/utilities.php");
?>
<?php
	
	if(!function_exists('saveNewUser')){
		function saveNewUser($connection, $db_name, $username, $password, $first_name, $last_name, $email){
			
			$connection->autocommit(false);
			if($stmt = $connection -> prepare("INSERT INTO ".$db_name.".users (username, password, first_name, last_name, email) VALUES ( ?, ?, ?, ?, ?);")){
				$stmt->bind_param("sssss", $username, $password, $first_name, $last_name, $email);
				$stmt->execute();
				//CHECK IF QUERY IS PERFORMED CORRECTLY
				if (mysqli_errno($connection)==0){
					$stmt->close();
				}else{
					$stmt->close();
					$connection->autocommit(true);
					return false;
				}
			}else{
				$connection->autocommit(true);
				return false;
			}
			$connection->autocommit(true);
			return true;
		}
	}

	if(!function_exists('checkIfUserDoesntExists')){
		function checkIfUserDoesntExists($connection, $db_name, $username){
			/* Create a prepared statement to avoid SQL Injection */
			if($stmt = $connection -> prepare("SELECT username FROM ".$db_name.".users WHERE username = ?;")){
				/* Bind parameters
				s - string, b - blob, i - int, etc */
				$stmt -> bind_param("s", $username);
				/* Execute it */
				$stmt -> execute();
				/* Bind result */
				$stmt->bind_result($username);
				//OK, the user belongs to a correct session
				if ($stmt->fetch()){
					/* Close statement */
					$stmt -> close();
					return false;
				//Wrong
				}else{
					$stmt -> close();
					return true;
				}
			}else{
				return false;
			}
		}
	}
	
	$DOMAIN = 'Registration';
	$URL_REDIRECT = '';
	$FORM_ID = 'newregform';
	$connection = null;
	$pass_hash_bcrypt_options = ['cost' => 12];
	
	error_reporting(E_ALL);
	date_default_timezone_set($DEFAULT_TIMEZONE);
	
	//CHECK DB CONNECTION
	if(!openDBUser($connection)){
		$result["status"] = "KO";
		$result["message"] = "SERVER ERROR";
		echo json_encode($result);
		die();
	}
	
	if($_SERVER['REQUEST_METHOD'] == 'POST'){
		
		if(isset($_POST['username']) && 
			isset($_POST['password']) && 
			isset($_POST['first_name']) && 
			isset($_POST['last_name']) && 
			isset($_POST['email']) && 
			$_POST['username'] != '' && 
			$_POST['password'] != '' && 
			$_POST['first_name'] != '' && 
			$_POST['last_name'] != '' && 
			$_POST['email'] != ''
		){
		//little time => simplest field cleaning, much better is possible
			$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
			$password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
			$first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
			$last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
			$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
			
			session_start();
			if(!checkIfUserDoesntExists($connection, $DEFAULT_DB, $username)){
				//little time => simple quick messages
				$result["status"] = "KO";
				$result["message"] = "Username already exists";
				echo json_encode($result);
			}else{
				
				$password = password_hash($password, PASSWORD_BCRYPT, $pass_hash_bcrypt_options);
				
				if(!saveNewUser($connection, $DEFAULT_DB, $username, $password, $first_name, $last_name, $email)){
					$result['status'] = 'KO';
					$result['message'] = 'Registration failed!';
					echo json_encode($result);
					die();
				}else{
					//SEND email WELCOME MESSAGE idea
					
					
					//create token and store
					$token = bin2hex(openssl_random_pseudo_bytes(64));
					if(!insertLoggedUser($connection, $DEFAULT_DB, $username, $token, date("Y-m-d H:i:s"))){
						$result['status'] = 'KO';
						$result['message'] = 'SERVER ERROR!';
						echo json_encode($result);
						die();
					}else{
						
						$_SESSION['username'] = $username;
						$_SESSION['token'] = $token;
						
						//go back home logged in
						header('location: '.htmlspecialchars('index.php?code=1'));
						die();
					}
				}
			}
		}
		
	}
	
	
?>
<!DOCTYPE html>
<html lang="<?php echo $DEFAULT_LANG; ?>">
	<head>
		<title><?php echo $DOMAIN; ?></title>
		<meta charset="<?php echo $DEFAULT_CHARSET; ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
	<!-- CSS -->
		<link rel="stylesheet" href="<?php echo $DEFAULT_BOOTSTRAP_CSS_NAME; ?>" />
		<link rel="stylesheet" href="<?php echo $DEFAULT_CSS; ?>" />
	<!-- JS -->
		<script type="text/javascript" src="<?php echo $DEFAULT_JQUERY_JS_NAME; ?>"></script>
		<script type="text/javascript" src="<?php echo $DEFAULT_BOOTSTRAP_JS_NAME; ?>"></script>
		<script type="text/javascript">
			
			$(document).ready(function(){
				
			});
		</script>
	</head>
	<body>
		
		
		<div class="col-xs-12">
			<p style="text-align:center;font-weight:bold;">REGISTRATION</p>
		</div>
		
		
		<br />
		
		<div class="container-fluid mobilereduce">
			
			<form id="<?php echo $FORM_ID;?>" method="POST" autocomplete="off">
				<div class="col-xs-12 col-sm-12 col-md-6 col-lg-4">
					<div class="col-xs-12">
						<input class="form-control" name="username" placeholder="username" required />
					</div>
					<div class="col-xs-12">
						<input class="form-control" name="password" type="password" placeholder="password" required />
					</div>
					<div class="col-xs-12">
						<input class="form-control" name="first_name" placeholder="first name" required />
					</div>
					<div class="col-xs-12">
						<input class="form-control" name="last_name" placeholder="last name" required />
					</div>
					<div class="col-xs-12">
						<input class="form-control" name="email" type="email" placeholder="email" required />
					</div>
					<div class="col-xs-12">
						<button type="submit" class="btn btn-default">Register</button>
					</div>
				</div>
			</form>
			
			
			
			
			
			
			
			
			
			
			
			
			
		</div>
	</body>
</html>