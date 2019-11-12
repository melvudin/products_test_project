<?php

	if(!function_exists('openDBUser')){
		function openDBUser(&$connection){
			
			include(dirname(__FILE__)."/../constants/constants.php");
			
			$connection = mysqli_connect($DEFAULT_DB_HOST, $DEFAULT_DB_USERNAME, $DEFAULT_DB_PASSWORD);
			if($connection == null || !$connection){
				return false;
			}else{
				return true;
			}
		}
	}
	
	if(!function_exists('validateLoggedUser')){
		function validateLoggedUser($connection, $db, $username, $token){
			
			if($stmt = $connection -> prepare("SELECT login_time FROM ".$db.".logged_users WHERE username = ? AND token = ? ;")){
				$stmt->bind_param("ss", $username, $token);
				$stmt->execute();
				$stmt->bind_result($login_timeDB);
				
				if($stmt->fetch()){
					$stmt -> close();
					return true;
				}else{
					$stmt -> close();
					return false;
				}
			}else{
				return false;
			}      
		}
	}
	
	if(!function_exists('insertLoggedUser')){
		function insertLoggedUser($connection, $db_name, $username, $token, $login_time){
			
			$connection->autocommit(false);
			if($stmt = $connection -> prepare("INSERT INTO ".$db_name.".logged_users (username, token, login_time) VALUES ( ?, ?, ?);")){
				$stmt->bind_param("sss", $username, $token, $login_time);
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
	
?>