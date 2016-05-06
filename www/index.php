<?php
/*index.php
This php file has been written to execute all php functions associated
with the Billfold web application. This file will: handle post and get functions
for forms and redirects, query a database of users and their data, keep a running log of 
errors, and implement helper functions for carrying out the aforementioned functions.

Written by: Connar L Stone
Created: 4/16/2016
Last Updated: 5/6/2016

*/
session_start(); // start the session (sets up post/get/etc..)
/*A log function for errors.. this will start a log of errors 
in the root folder for all calls to var_log*/
function var_log($error){
	$date = new DateTime();
	$date->setTimestamp(time());
	error_log('['.date_format($date, 'Y-m-d H:i:s').'] => '.$error.PHP_EOL, 3, 'error.log');
}
//set some global variables for DB connections and actions
$url = 'localhost';
$usr = 'root';
$pass = '@SysParrot83Dog$';
$db = "billfold";

/*checks for the $_POST['action'] variable and executes the appropriate action as given by the form 
or ajax call. You could think of each if/else if as it's own function. If the action variable 
does not exist an error is returned to the call. Additionally this structure is responsible for 
$_GET['action'] as well which is used mostly for redirects so multiple calls to update the DB does
not occur.*/
if(isset($_POST['action'])){
	$action = $_POST['action'];
	if($action === "register"){
		$records = regUser(); //update DB with the registered user.
		$rtn['html'] = get_html('/login.html', $records);
	}
	else if($action === "login"){
		$records = checkLogin($_POST['pass'], $_POST['email']); //check to see if the user info provided is correct
		$html;
		//records have been validated
		if ($records['valid'] === TRUE){
			$uid = $records['records']->user_id;
			header("location: index.php?action=redirect&user=$uid&loc=/templates/mainScreen.html");//direct to the login page
			exit();//exit the php file
		}
		else{//records do not match
			$records['error'] = "The email address and/or password provided does not match any of our records. Please check your e-mail and password and try again";
			$html = get_html('/login.html', $records);
		}
		echo $html;
	}
	else if($action == "getTrans"){ //not currently used
		getTransactions("xaction.html");
	}
	else if($action == "deleteAccount"){ //deletes a specified account from DB then redirects to mainScreen.html
		$uid = $_POST['user'];
		$id = $_POST['accNum'];
		$sql = "DELETE FROM accounts WHERE account_id = '$id'";
		run_query_no_return($sql);
		header("location: index.php?action=redirect&user=$uid&loc=/templates/mainScreen.html");
		exit();
	}
	else if($action == "newAccount"){
		//initialize variables from post since we cannot include single quote marks in the sql string
		$uid = $_POST['userId'];
		$name = $_POST['accName'];
		$bal = $_POST['balance'];
		$accType = $_POST['accType'];
		$sql="INSERT INTO accounts (user_id, account_name, balance, account_type)";
		$sql.=" VALUES ('$uid', '$name', '$bal', '$accType')";
		run_query_no_return($sql);
		//NOTE TO SELF: this needs some serious security beefing up otherwise anyone can guess a uid and bypass login. Perhaps include
		//some sort of verification string for that user.
		header("location: index.php?action=redirect&user=$uid&loc=/templates/mainScreen.html"); //prevents reloads from submitting multiple database calls
		exit(); //exit the php file.
		
	}
	else if($action == "logout"){
		header("location: index.php?action=logout&loc=/login.html");//direct to the login page
		exit();//exit the php file
	}
	else{
		$rtn['error'] = 'Action not found'; 
	}
}
else if(isset($_GET['action'])){
	//retrieved from redirect url
	$action = $_GET['action']; 
	$loc = $_GET['loc'];
	
	//mostly used for mainScreen.html, queries DB for user info, parses the template and echoes the html
	if($action == "redirect"){ 
		//if we are doing a redirect we need the user info so we can pull up their information
		$uid = $_GET['user'];
		
		//Query the DB for accounts info linked to a specific user
		$sql="SELECT * FROM users JOIN accounts ON users.user_id = accounts.user_id WHERE users.user_id = '$uid'"; //sql statement for DB
		$records = selectDB($sql);
		
		//if there are no accounts we still need user info
		if($records == NULL){
			var_log('are we here?');
			$sql = "SELECT * FROM users WHERE user_id = '$uid'";
			$records = selectDB($sql);
		}
		//parse the data and echo it.
		$html = get_html($loc, $records);
		echo $html;
	}//end redirect
	else if($action == 'logout'){ //return the user to the main page this action only requires a location
		$tmplt = file_get_contents(dirname(__FILE__).'/'.$loc);
		echo $tmplt;
	}//end logout
}
//if we make it this far in PHP we need to know that everything is working.
//this is mostly for ajax calls. 
$rtn['success'] = TRUE;
if(isset($_GET['callback'])){
	var_log($_GET['callback'] . '('.json_encode($rtn).')');
	echo $_GET['callback'] . '('.json_encode($rtn).')'; // Return the data using jsonp method
}
die();// may print out a spurious zero without this - can be particularly bad if using json

// =============== PHP FUNCTIONS ====================
function checkLogin($password, $email){
	//allow specific global variables
	global $_POST, $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	//assume user is not valid
	$valid = FALSE;
	$rtnArray;
	//connection good
	if(!(mysqli_connect_errno())){
		$sql = "SELECT * FROM users WHERE user_email = '$email'";
		if($result = $link->query($sql)){ 
				if($result->num_rows > 0){
					$obj = $result->fetch_object(); //grab the record from the DB
					$dbPass = $obj->user_pass; //grab user pass from DB
					$salt = $obj->user_salt; //grab user salt from DB
					$saltedPass = $salt.$password; //combine salt and pass
					$hashedPass = hash('ripemd256', $saltedPass, FALSE); //hash combined salt/pass with ripmed256 encryption 
					if($dbPass === $hashedPass){ //the provided password was correct
						$valid = TRUE;
					}
					else{//found the email, but the password is wrong
						$rtnArray['error']="The email address and/or password provided does not match any of our records. Please check your e-mail and password and try again";
					}
				}
				else{ //no file on record for the email provided
					$rtnArray['error']="The email address and/or password you provided does not match any of our records. Please check your e-mail and password and try again";
				}
		}
		else{ //connection is bad
			var_log("Error: ".$sql." => ".$link->error);
		}
	}
	$rtnArray['valid']=$valid;
	$rtnArray['records']=$obj;
	//close result and link objects
	$result->close();
	$link->close();
	return $rtnArray;
}
function regUser(){
	//allow specific global variables
	global $_POST, $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	$rtnArray; //initializes the return array in case link or connection is bad
	//good connection
	if(!(mysqli_connect_errno())){
		//initialize variables from post since we cannot include single quote marks in the sql string
		$fname = $_POST['fname'];
		$lname = $_POST['lname'];
		$email = $_POST['email'];
		$salt = genSalt(); //generate a salt to store for the user
		$saltedPass= $salt.$_POST['pass']; //combine salt and password
		$pass = hash('ripemd256', $saltedPass, FALSE); //hash combined salt/pass with ripmed256 encryption 
		$valid = $_POST['valid']; //in future iterations this will indicate a validated email
		$secretQ = $_POST['secretQ']; //user's secret question
		$secretA = $_POST['secretA']; //user's secret answer... will be hashed in later iterations to ensure security
		
		//create the sql call
		$sql = "INSERT INTO users (fName, lName, user_email, user_pass, user_salt, user_validated, secretQ, secretA) ";
		$sql.= "VALUES ('$fname', '$lname', '$email', '$pass', '$salt', '$valid', '$secretQ', '$secretA')";
		
		//execute the sql
		if ($link->query($sql) === TRUE){
			//if successful, log the record and return a congrats message. 
			var_log("New Record Created: '$fname', '$lname', '$email', '$pass', '$salt', '$valid', '$secretQ', '$secretA'");
			$rtnArray['msg'] = "Congratulations ".$fname." on creating your Billfold account!";
			$rtnArray['created'] = TRUE;
		}
		else{
			//something went wrong and the record was not created
			var_log("Error: ".$sql." => ".$link->error);
			$rtnArray['created'] = FALSE;
		}
	}
	else{//connection was not made
		var_log('bad connection!');
		exit(); //leave index.php
	}
	//close the link and return the array
	$link->close();
	return $rtnArray;
};
//this function has not yet been used. The idea is to query the DB for transactions, but
//the transaction DB does not yet exist. This function will most likely be scrapped and 
//a different function such as selectDB will be used to carry out the sql. 
function getTransactions($template=false){
	//allow specific global variables
	global $_POST, $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	//good connection
	$html;
	
	$accountID = $_POST['accID'];
	if(!(mysqli_connect_errno())){
		$sql = "SELECT * FROM transactions WHERE user_id = '$accountID'";
		if ($result = $link->query($sql)){
			if ($template != false) {
				while($listing = $result->fetch_object()){
					$html .= get_html($template, $listing);
				}
			}
		}
		else{
			var_log("Error: ".$sql." => ".$link->error);
		}
	}
	else{
		var_log(' bad connection!');
		exit();
	}
	$link->close();
	return $html;
}

//Queries the DB with an SQL string. This function does
//NOT expect a return value for the call. This is best
//used for inserts and deletes. 
function run_query_no_return($sql){
	//allow specific global variables
	global $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	
	if(!(mysqli_connect_errno())){ //good connection
		if ($link->query($sql) === FALSE){//something went wrong with the call
			var_log("Error: ".$sql." => ".$link->error);
		}
	}
	else{
		//bad connection
		exit();
	}
	//close the link
	$link->close();
}
//Queries the DB for records and returns those records as an object. If that object holds no
//rows (or records) then instead a null is returned.
function selectDB($sql){
	//allow specific global variables
	global $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	//initialize result and assume no records will be found
	$result;
	$obj =NULL;
	
	//connection good
	if(!(mysqli_connect_errno())){
		//store records in result
		if ($result = $link->query($sql)){
			//check for row
			if($result->num_rows > 0){
				/*currently this only stores the first row. It will be necessary to store multiple rows later.
				but for now this works just fine.*/
				$obj = $result->fetch_object();
			}
		}
		else{
			var_log("Error: ".$sql." => ".$link->error);
			$obj = NULL;
		}
	}
	$link->close();
	return $obj;
}
//this function is called in the mainScreen.html template to load the accounts that have been linked to a specific user
//this function returns the account templates as a string which is then echoed in the mainScreen.html, This is how
//we get multiple accounts to show up on the same page. 
function loadAccounts($id){
	//allow specific global variables
	global $url, $usr, $pass, $db;
	$link = new mysqli($url , $usr, $pass, $db);
	$sql = "SELECT * FROM users JOIN accounts ON users.user_id = accounts.user_id WHERE users.user_id = '$id'";
	$obj = NULL;
	$html = "";
	//connection good
	if(!(mysqli_connect_errno())){
		if ($result = $link->query($sql)){
			while($obj = $result->fetch_object()){
				if ($result->num_rows > 0){
					$html .= get_html('/templates/account.html', $obj);
				}
			}
		}
		else{
			var_log("Error: ".$sql." => ".$link->error);
			$obj = NULL;
		}
	}
	$link->close();
	return $html;
}
//This is a helper function designed to parse through an html file that has PHP values in it.
//those php values are evaluated with the $recordSet variable which contains any data those
//html files might need. 
function get_html($page, $recordSet) {
	$tmplt = file_get_contents(dirname(__FILE__).$page);//retrieve the desired html file
	ob_start(); //start object buffer
	eval("?>$tmplt<?php "); //must be double quotes, parse the html
	$html = ob_get_contents(); //get html from the buffer
	ob_end_clean();//close the buffer
	return $html; //return the evaluated html
} //get_html()
//This function generates a salt using a know universe of character including the american alphabet
//both upper and lower case. the numbers 0-9 and the special characters !@#$%^&*. This string is 
//70 characters in length and each character in that string is randomly selected from the 
//aforementioned universe of characters. The 'salt' is then returned to the function that called it. 
function genSalt(){
    $salt=""; //initialize salt as an empty string
    $allchar="abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*"; //universe of characters
    for($i=0; $i < strlen($allchar); $i++){
       $randInt=rand(0, 69);//choose a random number between 0 and 69 
        $rc = $allchar[$randInt]; //grab that character from the universe
        $salt=$salt.$rc;//append the new character to the string
    }
    return $salt;
} //genSalt

?>