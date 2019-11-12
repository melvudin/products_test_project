<?php
	require("constants/constants.php");
	require("utilities/utilities.php");
?>
<?php
	
	if(!function_exists('postNewComment')){
		function postNewComment($connection, $dbname, $product_id, $username, $name, $text, $email, $timestamp){
			
			$connection->autocommit(false);
			if($stmt = $connection -> prepare("INSERT ".$dbname.".comments (comment_id, product_id, username, name, text, email, timestamp) VALUES((SELECT max(tmp.comment_id)+1 as next_id_incremented from ".$dbname.".comments as tmp), ?, ?, ?, ?, ?, ?);")){
				$stmt->bind_param("ssssss", $product_id, $username, $name, $text, $email, $timestamp);
				$stmt->execute();
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
			$connection->commit();
			$connection->autocommit(true);
			return true;
		}
	}
	
	if(!function_exists('checkUser')){
		function checkUser($connection, $db_name, $username){
			if($stmt = $connection -> prepare("SELECT password FROM ".$db_name.".users WHERE username = ?;")){
				$stmt -> bind_param("s", $username);
				$stmt -> execute();
				$stmt->bind_result($password);
				$results = array();
				while($stmt->fetch()){
					$results = $password;
				}
				$stmt -> close();
				return $results;
			}else{
				return null;
			}
		}
	}
	
	if(!function_exists('getProductsInfo')){
		function getProductsInfo($connection, $db_name){
			
			if($stmt = $connection -> prepare("SELECT P.id as product_id, P.image_path, P.title as product_title, P.description as product_description, COALESCE(C.comment_id, '') as comment_id, COALESCE(C.username, '') as username, COALESCE(C.name, '') as name, COALESCE(C.text, '') as text, COALESCE(C.email, '') as email, COALESCE(C.timestamp, '') as timestamp, CAST(COALESCE(C.is_approved, 0) AS UNSIGNED ) AS is_approved FROM ".$db_name.".products as P left join ".$db_name.".comments as C on C.product_id = P.id;")){
				$stmt -> execute();
				$stmt -> bind_result($product_id, $image_path, $product_title, $product_description, $comment_id, $username, $name, $text, $email, $timestamp, $is_approved);
				while($stmt->fetch()){
					$results[$product_id]["product_id"] = $product_id;
					$results[$product_id]["image_path"] = $image_path;
					$results[$product_id]["product_title"] = utf8_encode($product_title);
					$results[$product_id]["product_description"] = utf8_encode($product_description);
					$results[$product_id]["username"] = $username;
					
					if($comment_id != null && $comment_id != ""){
						$results[$product_id]["comments"][$comment_id]["comment_id"] = $comment_id;
						$results[$product_id]["comments"][$comment_id]["username"] = $username;
						$results[$product_id]["comments"][$comment_id]["name"] = utf8_encode($name);
						$results[$product_id]["comments"][$comment_id]["text"] = utf8_encode($text);
						$results[$product_id]["comments"][$comment_id]["email"] = $email;
						$results[$product_id]["comments"][$comment_id]["timestamp"] = $timestamp;
						$results[$product_id]["comments"][$comment_id]["is_approved"] = $is_approved;
						
					}
				}
				$stmt -> close();
				return $results;
			}else{
				return null;
			}
		}
	}
	
	if(!function_exists('getUserInfo')){
		function getUserInfo($connection, $db_name, $username){
			if($stmt = $connection -> prepare("SELECT username, first_name, last_name, email, type FROM ".$db_name.".users WHERE username = ?;")){
				$stmt -> bind_param("s", $username);
				$stmt -> execute();
				$stmt->bind_result($username, $first_name, $last_name, $email, $type);
				$results = array();
				while($stmt->fetch()){
					$results['username'] = $username;
					$results['first_name'] = utf8_encode($first_name);
					$results['last_name'] = utf8_encode($last_name);
					$results['email'] = $email;
					$results['type'] = $type;
				}
				$stmt -> close();
				return $results;
			}else{
				return null;
			}
		}
	}
	
	if(!function_exists('commentApproval')){
		function commentApproval($connection, $dbname, $comment_id, $product_id, $username){
			
			$connection->autocommit(false);
			if($stmt = $connection -> prepare("UPDATE ".$dbname.".comments SET is_approved = 1 WHERE comment_id = ? AND product_id = ? AND username = ?;")){
				$stmt->bind_param("iss", $comment_id, $product_id, $username);
				$stmt->execute();
				if(mysqli_errno($connection)==0){
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
			$connection->commit();
			$connection->autocommit(true);
			return true;
		}
	}
	
	$DOMAIN = 'TEST';
	$PAGE_TITLE = 'TEST PAGE';
	$ADMIN_PRIVILEGES_STATE = 1;
	$APPROVED_STATE_OF_COMMENT = 1;
	$logged_in = false;
	$username = '';
	
	$user_info = array();
	$products = array();
	
	$connection = null;
	
	session_start();
	error_reporting(E_ALL);
	
	
	date_default_timezone_set($DEFAULT_TIMEZONE);
	
	//CHECK DB CONNECTION
	if(!openDBUser($connection)){
		//little time => simple messages
		$result["status"] = "KO";
		$result["message"] = "SERVER ERROR";
		echo json_encode($result);
		die();
	}
	
	//CHECK SESSION
	if(isset($_SESSION["username"]) && isset($_SESSION["token"])){
		if(!validateLoggedUser($connection, $DEFAULT_DB, $_SESSION['username'], $_SESSION['token'])){
			//save LOG action to db if something is wrong - idea
			
			session_unset();
			session_destroy();
			
			$result["status"] = "KO";
			$result["message"] = "Failed to login!";
			echo json_encode($result);
		}else{
			$logged_in = true;
			$username = $_SESSION['username'];
			$user_info = getUserInfo($connection, $DEFAULT_DB, $username);
		}
	}
	
	if($_SERVER['REQUEST_METHOD'] == 'POST'){
		
		if(isset($_POST['username']) && isset($_POST['password']) && $_POST['username'] != '' && $_POST['password'] != ''){
		//little time => simplest field cleaning
			$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
			$password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
			
			$result_password = checkUser($connection, $DEFAULT_DB, $username);
			if($result_password == null){
				session_unset();
				session_destroy();
				
				$result["status"] = "KO";
				$result["message"] = "User doesn't exist";
				echo json_encode($result);
			}else{
				if(!password_verify($password, $result_password)){
					
					session_unset();
					session_destroy();
					
					$result["status"] = "KO";
					$result["message"] = "Wrong credentials";
					echo json_encode($result);
				}else{
					//CREATE TOKEN AND STORE
					$token = bin2hex(openssl_random_pseudo_bytes(64));
					if(!insertLoggedUser($connection, $DEFAULT_DB, $username, $token, date("Y-m-d H:i:s"))){
						$result['status'] = 'KO';
						$result['message'] = 'SERVER ERROR1';
						echo json_encode($result);
						die();
					}else{
						$_SESSION['username'] = $username;
						$_SESSION['token'] = $token;
						//exit post
						header('Location: '.$_SERVER['PHP_SELF']);
						die();
					}
				}
			}
		}else if(isset($_POST['product_id']) && isset($_POST['username']) && isset($_POST['name']) && isset($_POST['email']) && isset($_POST['text']) && $_POST['product_id'] != '' && $_POST['username'] != '' && $_POST['name'] != '' && $_POST['email'] != '' && $_POST['text'] != '' && $username != ''){
			//INSUFFICIENT CLEANING, NO TIME FOR BETTER
			$product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_STRING);
			$name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
			$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
			$text = filter_var($_POST['text'], FILTER_SANITIZE_STRING);
			
			$current_timestamp = date('Y-m-d H:i:s');
			
			if(!postNewComment($connection, $DEFAULT_DB, $product_id, $username, $name, $text, $email, $current_timestamp)){
				$result["status"] = "KO";
				$result["message"] = "Server error";
				echo json_encode($result);
			}else{
				header('Location: '.$_SERVER['PHP_SELF']);
			}
		}else if(isset($_POST['approve_comment']) && isset($_POST['product_id']) && isset($_POST['comment_id']) && $_POST['username'] != '' && $_POST['product_id'] != '' && $_POST['comment_id'] != '' && intval($_POST['approve_comment']) == 1 && $user_info != null && $user_info['type'] == $ADMIN_PRIVILEGES_STATE){
			
			$product_id = filter_var($_POST['product_id'], FILTER_SANITIZE_STRING);
			$comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_STRING);
			$username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
			
			if(!commentApproval($connection, $DEFAULT_DB, $comment_id, $product_id, $username)){
				$result["status"] = "KO";
				$result["message"] = "Server error2";
				echo json_encode($result);
			}else{
				header('Location: '.$_SERVER['PHP_SELF']);
			}
		}else{
			$result["status"] = "KO";
			$result["message"] = "Invalid data, user must be logged in!";
			echo json_encode($result);
		}
	}
	
	if(isset($_GET['code']) && $_GET['code'] == '1' ){
		echo json_encode('Successfull registration!');
	}
	//GET LIST OF PRODUCTS
	$products = getProductsInfo($connection, $DEFAULT_DB);
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
			
			function logout(username){
				if(username != ''){
					$.ajax({
						type: 'POST',
						url: 'controller/controller.php',
						data: {
							username: username,
							action: '<?php echo $LOGOUT_USER_ACTION;?>'
						},
						error: function(data, textStatus, errorThrown){
							alert('Server error');//no time=>simple response
							console.log(errorThrown);
							console.log(textStatus);
						},
						success: function(data){
							data=jQuery.parseJSON(data);
							if(data.status != 'OK'){
								alert('Server error2');//no time=>simple response
							}else{
								location.reload();
							}
						}
					});
				}else{
					alert('Bad request');//no time=>simple response
				}
			}
			
		</script>
	</head>
	<body>
		
		
		<div class="col-xs-12">
			<p style="text-align:center;font-weight:bold;"><?php echo $PAGE_TITLE;?></p>
		</div>
		
		<br />
		<div class="container-fluid mobilereduce">
			<?php
			if(!$logged_in){
			?>
			
				<button type="button" class="btn btn-info" data-toggle="modal" data-target="#modal">Login</button>
				<input type="button" class="btn btn-info" value="Register" onclick="window.location.href='registration.php'" />
				
				<div class="modal fade" id="modal" role="dialog">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<div class="modal-header">
								<h4 class="modal-title">Log in</h4>
							</div>
							<div class="modal-body">
								<form id="login_form" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
									<input id="username" name="username" class="form-control" placeholder="USERNAME" style="text-align:center;" required />
									<input id="password" name="password" class="form-control" placeholder="PASSWORD" style="text-align:center;" type="password" required />
									<button class="btn btn-default" title="Login" type="submit">Login</button>
								</form>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>
				<!-- SPACING -->
				<div class="col-xs-12 emptydivs"></div>
			<?php
			}else{
			?>
				<div class="col-xs-12 zerosidepadding">
					<button type="button" class="btn btn-info" onclick="logout('<?php echo $username;?>');">Logout</button>
				</div>
				<?php
				if($user_info != null && $user_info['type'] == $ADMIN_PRIVILEGES_STATE){
					if($products != null){
						?>
						<div class="col-xs-12">Comments to approve:</div>
						<div class="col-xs-12">
							<div class="col-xs-12 col-sm-3 col-md-2 col-lg-1"><p>Username:</p></div>
							<div class="col-xs-12 col-sm-7 col-md-8 col-lg-10"><p>Comment:</p></div>
							<div class="col-xs-12 col-sm-2 col-md-2 col-lg-1"><p></p></div>
						</div>
						<?php
						foreach($products as $p){
							if($p['comments'] != null){
								foreach($p['comments'] as $comment){
									if($comment['is_approved'] != $APPROVED_STATE_OF_COMMENT){
										?>
											<div class="col-xs-12">
												<div class="col-xs-12 col-sm-3 col-md-2 col-lg-1"><p><?php echo $p['username'];?></p></div>
												<div class="col-xs-12 col-sm-7 col-md-8 col-lg-10"><p><?php echo $comment['text'];?></p></div>
												<div class="col-xs-12 col-sm-2 col-md-2 col-lg-1">
													<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
														<button class="btn btn-success">Approve</button>
														<input name="product_id" value="<?php echo $p['product_id'];?>" type="hidden" />
														<input name="username" value="<?php echo $p['username'];?>" type="hidden" />
														<input name="comment_id" value="<?php echo $comment['comment_id'];?>" type="hidden" />
														<input name="approve_comment" value="1" type="hidden" />
													</form>
												</div>
											</div>
										<?php
									}
								}
							}
						}
					}
				}
				?>
			<?php
			}
			?>
			
			<?php
			if($products != null){
			?>
				<div class="col-xs-12">
					<div class="col-xs-4 centered">Product</div>
					<div class="col-xs-4 centered">Name</div>
					<div class="col-xs-4 centered">Description</div>
				</div>
			<?php
				foreach($products as $p){
			?>
					<div class="col-xs-12 solid rounded">
						<div class="col-xs-4 centered">
							<img src="<?php echo $p['image_path'];?>" style="max-height:150px;">
						</div>
						<div class="col-xs-4 centered">
							<p><?php echo $p['product_title'];?></p>
						</div>
						<div class="col-xs-4 centered">
							<p><?php echo $p['product_description'];?></p>
						</div>
						<div class="col-xs-12">
							<button class="btn btn-default" onclick="document.getElementById('hiddendivid<?php echo $p['product_id'];?>').style.display=='none' ? document.getElementById('hiddendivid<?php echo $p['product_id'];?>').style.display='block' : document.getElementById('hiddendivid<?php echo $p['product_id'];?>').style.display='none';">View comments</button>
						</div>
						<div id="hiddendivid<?php echo $p['product_id'];?>" class="col-xs-12" style="display:none;">
							<?php
							if($p['comments'] != null){
								foreach($p['comments'] as $comment){
									if($comment['is_approved'] == $APPROVED_STATE_OF_COMMENT){
							?>
									<div class="col-xs-12 zerosidepadding">
										<p><?php echo $comment['username']." : ".$comment['text'];?></p>
									</div>
							<?php
									}
								}
							}
							?>
							<div class="col-xs-12 zerosidepadding">
								<?php
								if($logged_in){
								?>
								<form method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
									<input name="product_id" value="<?php echo $p['product_id'];?>" type="hidden" />
									<input name="username" value="<?php echo $p['username'];?>" type="hidden" />
									<input name="name" class="form-control" placeholder="NAME" style="text-align:center;" autocomplete="off" required />
									<input name="email" class="form-control" placeholder="EMAIL" style="text-align:center;" type="email" autocomplete="off" required />
									<input name="text" class="form-control" placeholder="COMMENT" style="text-align:center;" type="text" autocomplete="off" required />
									<button class="btn btn-default" type="submit">Comment</button>
								</form>
								<?php
								}
								?>
							</div>
						</div>
					</div>
					<!-- SPACING -->
					<div class="col-xs-12 emptydivs"></div>
			<?php
				}
			?>
			<?php
			}
			?>
			
			
		</div>
	</body>
</html>