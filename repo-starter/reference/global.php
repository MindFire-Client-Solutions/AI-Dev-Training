<?php
date_default_timezone_set("UTC"); 
require_once('config.php');
ini_set("memory_limit", "-1");
set_time_limit(0);
require_once('conversionUtil.php');



function getPartnerCredentials($endpoint, $request) {
	$logger = new FileLogger("callService2.log");
	$logger->log("endpoint : $endpoint");
	$PartnerCredentials = $request['Credentials'];
	
	if( !@$_SESSION['LOGINCREDENTIALS'] ){	
		
		$Ticket = $PartnerCredentials['Ticket'];
		$PartnerCredentials = getDVPartnerCredentials($Ticket);		
		
		if ($PartnerCredentials) {
			$logger->log("From DV_PartnerCredentials");
		}
	} else {
		
		$logger->log("From Session");
		$PartnerCredentials = $_SESSION['LOGINCREDENTIALS'];	
	}
	
	if ($PartnerCredentials) {
	} else {
		$logger->log("From Request");
		$PartnerCredentials = $request['Credentials'];
	}
	
	$logger->log("PartnerCredentials = ". json_encode($PartnerCredentials) );
	
	return $PartnerCredentials;
}

function callServiceJson($endpoint, $request){
	$loggerService = new FileLogger("CallServiceJson.log");
	
	$LoginCredentials = $request['Credentials'];
	$Ticket = $LoginCredentials['Ticket'];
	$loggerService->log("endpoint : $endpoint");
	
	if ($Ticket) { } else { $Ticket = ''; }
	
	if ($Ticket == '') {
		$loggerService->log("FN[callServiceJson] - Does not use ticket.");
		return callServiceCredentialJson($endpoint, $request);
		exit;
	}	
	$ResultCredentials = callServiceCredentialJson($endpoint, $request);
	$request['Credentials'] = $ResultCredentials;
	return $ResultCredentials; 
	
}

function callServiceCredentialJson($endpoint, $request)
{
    $loggerService = new FileLogger("CallServiceJson.log");
	
	$request_string = json_encode($request); 
	
	$studioMode = GetCurrentConfig()->studioMode;
	$studioEndpointAPI = 'https://studiowebapi.mdl.io/Studio/';
	$serviceURL = $studioEndpointAPI.$endpoint;
	
	$loggerService->log("FN[callServiceCredentialJson] serviceURL = ". $serviceURL );
	
	$service = curl_init($serviceURL);
    curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   
	$response_string = curl_exec($service);
    
	$loggerService->log("FN[callServiceCredentialJson] response = ". $response_string );
    
	$response = json_decode($response_string);

	$logger = new FileLogger("updateAccount.log");
	$logger->log("serviceURL = $serviceURL");
	$logger->log("request = ". json_encode($request) );
	$logger->log("response = ". json_encode($response) );
	return($response);
}


function callService($endpoint, $request){
	$logger = new FileLogger("callService3.log");
	
	$LoginCredentials = $request['Credentials'];
	$Ticket = $LoginCredentials['Ticket'];
	
	//$logger->log("endpoint : $endpoint");
	
	if ($Ticket) { } else { $Ticket = ''; }
	
	if ($Ticket == '') {
		
		//$logger->log("Does not use ticket.");
		return callServiceCredential($endpoint, $request);
		exit;
	}	
	
	$LoginCredentials = getPartnerCredentials($endpoint, $request);
	$request['Credentials'] = $LoginCredentials;
	
	//$logger->log("request = ". json_encode($request) );
	
	return callServiceCredential($endpoint, $request);
}

function getStudioEndpointAPI() {
	
	$studioMode = GetCurrentConfig()->studioMode;
	$serverName = GetCurrentConfig()->serverName;
	
	if ($studioMode == 'qa') {
		$studioEndpointAPI = 'http://studio.scale.mdl.io/REST/';
	} else {
		$studioEndpointAPI = 'https://studio.mdl.io/REST/';
	}
	
	return $studioEndpointAPI;
}


function callServiceCredential($endpoint, $request)
{

	$request_string = json_encode($request); 
	
	$studioMode = GetCurrentConfig()->studioMode;
	$studioEndpointAPI = getStudioEndpointAPI();
	$serviceURL = $studioEndpointAPI.$endpoint;
	
	$service = curl_init($serviceURL);    
    curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   
	$response_string = curl_exec($service);

	$response = json_decode($response_string);
	
	//if ($studioMode == 'qa') {
		$logger = new FileLogger("updateAccount.log");
		$logger->log("serviceURL = $serviceURL");
		$logger->log("request = ". json_encode($request) );
		$logger->log("response = ". json_encode($response) );
	//}
	
	return($response);
}

function callReportService($endpoint, $request) {
	$request_string = json_encode($request); 
	$service = curl_init("https://studio.dashboard.mdl.io/api/Report/".$endpoint);   
    curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   
	$response_string = curl_exec($service);

	$response = json_decode($response_string);
	return($response);

}

function getTicketCurl($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword) {
	if ($_SESSION['STUDIOLOGIN'] == "Y") {
		$userTicket = $_SESSION['STUDIOTICKET'];
	} else {
		$Authenticate = getAuthenticateCurl($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword);
		$userTicket = $Authenticate['userTicket'];
	}
	return $userTicket;
}

function getAuthenticateCurl($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword) {
	//$logger = new FileLogger("getTicketCurl.log"); 
	//$logger->log(" 1 getAuthenticateCurl SelectedAccountID=[$SelectedAccountID] email=[$email]  pwd=[$pwd]  PartnerGuid=[$PartnerGuid]  PartnerPassword=[$PartnerPassword]  " );
	$authRequest = array
	(
		"SelectedAccountID" => $SelectedAccountID,
		"Email" => $email, 
		"Password" => $pwd, 
		"PartnerGuid" => $PartnerGuid, 
		"PartnerPassword" => $PartnerPassword
	);

	$authResponse = callService("userservice/Authenticate", $authRequest);
	//$logger->log("2 getAuthenticateCurl authResponse = ". json_encode($authResponse) );
	$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
	
	if ($_SESSION['STUDIOLOGIN'] == "Y") {
		$logger = new FileLogger("portalLogin.log");
		$logger->log("2 getAuthenticateCurl authRequest = ". json_encode($authRequest) );
		$logger->log("2 getAuthenticateCurl authResponse = ". json_encode($authResponse) );
	}
	
	if ($ErrorCode == "") {	
		$userTicket = $authResponse->{"Credentials"}->{"Ticket"};
		$PartnerTicket = $authResponse->{"Credentials"}->{"PartnerTicket"};
		$ParentAccountID = $authResponse->{"ParentAccountID"};
		if ($ParentAccountID) {
			
		} else {
			$ParentAccountID = $SelectedAccountID;
		}
		$ParentUiAccess = $authResponse->{"ParentUiAccess"};
		$PartnerID = $authResponse->{"PartnerID"};
		$Credentials = $authResponse->{"Credentials"};
		$UiAccess = $authResponse->{"UiAccess"};
		$IsPowerUser = $authResponse->{"IsPowerUser"};
		$errorMessage = '';
		
		saveDVPartnerCredentials($userTicket, $Credentials);
		
		
	} else {
		$errorMessage = "Authenticate ERROR : ".$authResponse->{"Result"}->{"ErrorMessage"};
		$userTicket = '';
		$PartnerTicket = '';
		$ParentAccountID = '';
		$ParentUiAccess = '';
		$PartnerID = '';
		$Credentials = null;
		$IsPowerUser = false;
		$UiAccess = POWERUSERUIACCESS;
	}
	$Authenticate['userTicket'] = $userTicket;
	$Authenticate['SelectedAccountID'] = $SelectedAccountID;
	$Authenticate['ErrorCode'] = $ErrorCode;
	$Authenticate['errorMessage'] = $errorMessage;
	$Authenticate['PartnerTicket'] = $PartnerTicket;
	$Authenticate['ParentAccountID'] = $ParentAccountID;
	$Authenticate['ParentUiAccess'] = $ParentUiAccess;
	$Authenticate['PartnerID'] = $PartnerID;
	$Authenticate['Credentials'] = $Credentials;
	$Authenticate['UiAccess'] = $UiAccess;
	$Authenticate['IsPowerUser'] = $IsPowerUser;
	
	
	
	//$logger->log("3 getAuthenticateCurl Authenticate = ". json_encode($Authenticate) );
	
	return $Authenticate;
}

function getAcrossAuthenticateCurl($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword) {
	$logger = new FileLogger("getAcrossAuthenticateCurl.log"); 
	$logger->log(" 1 getAuthenticateCurl SelectedAccountID=[$SelectedAccountID] email=[$email]  pwd=[$pwd]  PartnerGuid=[$PartnerGuid]  PartnerPassword=[$PartnerPassword]  " );
	$authRequest = array
	(
		"SelectedAccountID" => $SelectedAccountID,
		"Email" => $email, 
		"Password" => $pwd, 
		"PartnerGuid" => $PartnerGuid, 
		"PartnerPassword" => $PartnerPassword
	);

	$authResponse = callServiceCredential("userservice/Authenticate", $authRequest);
	$logger->log("2 getAuthenticateCurl authRequest = ". json_encode($authRequest) );
	$logger->log("2 getAuthenticateCurl authResponse = ". json_encode($authResponse) );
	$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
	
	if ($_SESSION['STUDIOLOGIN'] == "Y") {
		$logger = new FileLogger("portalLogin.log");
		$logger->log("2 getAuthenticateCurl authRequest = ". json_encode($authRequest) );
		$logger->log("2 getAuthenticateCurl authResponse = ". json_encode($authResponse) );
	}
	
	if ($ErrorCode == "") {	
		$userTicket = $authResponse->{"Credentials"}->{"Ticket"};
		$PartnerTicket = $authResponse->{"Credentials"}->{"PartnerTicket"};
		$ParentAccountID = $authResponse->{"ParentAccountID"};
		if ($ParentAccountID) {
			
		} else {
			$ParentAccountID = $SelectedAccountID;
		}
		$PartnerID = $authResponse->{"PartnerID"};
		$Credentials = $authResponse->{"Credentials"};
		
		$errorMessage = '';
		$UiAccess = $authResponse->{"UiAccess"};
		$IsPowerUser = $authResponse->{"IsPowerUser"};
		saveDVPartnerCredentials($userTicket, $Credentials);
		
		
	} else {
		$errorMessage = "Authenticate ERROR : ".$authResponse->{"Result"}->{"ErrorMessage"};
		$userTicket = '';
		$PartnerTicket = '';
		$ParentAccountID = '';
		$PartnerID = '';
		$Credentials = null;
		$IsPowerUser = false;
		$UiAccess = POWERUSERUIACCESS;
	}
	$Authenticate['userTicket'] = $userTicket;
	$Authenticate['ErrorCode'] = $ErrorCode;
	$Authenticate['errorMessage'] = $errorMessage;
	$Authenticate['PartnerTicket'] = $PartnerTicket;
	$Authenticate['ParentAccountID'] = $ParentAccountID;
	$Authenticate['PartnerID'] = $PartnerID;
	$Authenticate['Credentials'] = $Credentials;
	$Authenticate['UiAccess'] = $UiAccess;
	$Authenticate['authRequest'] = $authRequest;
	$Authenticate['authResponse'] = $authResponse;
	$Authenticate['IsPowerUser'] = $IsPowerUser;
	
	
	$logger->log("3 getAuthenticateCurl Authenticate = ". json_encode($Authenticate) );
	
	return $Authenticate;
}


function getDVPartnerCredentials($Ticket) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	try {  
		$jsonRow = null;
		$sqltxt = "select * from DV_PartnerCredentials where ticket = ? limit 1";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($Ticket));
		$affected_rows = $Stmt->rowCount();
		
		while ($row2 = $Stmt->fetch()) {		
			$partnerCredentials = $row2['partnerCredentials'];
			$jsonRow = json_decode($partnerCredentials);
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
	}
	
	return $jsonRow;
}

function saveDVPartnerCredentials($ticket, $Credentials) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sql = 'INSERT INTO DV_PartnerCredentials (ticket, partnerCredentials, lastUpdate) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE partnerCredentials=?, lastUpdate=NOW()';
	$CredentialsData = json_encode($Credentials);
	try {
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($ticket, $CredentialsData, $CredentialsData));
	} catch(Exception $e){
		$success = false;	
		$ErrorMessage = $e->getMessage();
	}
	return true;
}

function checkLogin($TEST, $email, $pwd) {
	if ($TEST == 't') {
		echo "<br>checkLogin<br>";
		echo "email :$email<br>";
		echo "pwd :$pwd<br>";
	}
	$SelectedAccountID = null;
	$studioParam = GetStudioAPIParam();
    $PARTNERGUID = $studioParam['guid'];
    $PARTNERPASSWORD = $studioParam['password'];
	$authRequest = array
	(
		"SelectedAccountID" => $SelectedAccountID,
		"Email" => $email, 
		"Password" => $pwd, 
		"PartnerGuid" => $PARTNERGUID, 
		"PartnerPassword" => $PARTNERPASSWORD
	);

	$authResponse = callService("userservice/Authenticate", $authRequest);
	if ($TEST == 't') {
		echo "<br>authResponse<br>";
		var_dump($authResponse);
		echo "<br>";
	}
	
	if ($_SESSION['STUDIOLOGIN'] == "Y") {
		$logger = new FileLogger("portalLogin.log");
		$logger->log("2 checkLogin authRequest = ". json_encode($authRequest) );
		$logger->log("2 checkLogin authResponse = ". json_encode($authResponse) );
	}

	$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {	
		$success = true;
		$errorMessage = "";
		$sutdioMessage = "";
	} else {
		$success = false;
		$errorMessage = "You seem to have an account with us already. Please use your current password.";		
		$sutdioMessage = "checkLogin ERROR : <br> ErrorMessage -> ".$authResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$authResponse->{"Result"}->{"ExceptionMessage"};	
	}

	
	//$success = true;
	//$errorMessage = "";
	//$sutdioMessage = "";
	
	return array('success'=>$success, 'errorMessage' => $errorMessage, 'sutdioMessage' => $sutdioMessage);
}

function checkTokenOnly($token, $acctID) {
	$logger = new FileLogger("checkTokenOnly.log"); 
	
	$DVCodeConfig = getDVCodeConfig();
	$IDENTITYSERVICES = $DVCodeConfig['IDENTITYSERVICES'];
	
	$logger->log("IDENTITYSERVICES : $IDENTITYSERVICES");
	
	$CURLOPT_URL = $IDENTITYSERVICES."/api/identity/v1/login/".$acctID."?validate=true";
	
	$logger->log("CURLOPT_URL : $CURLOPT_URL");
	
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => $CURLOPT_URL,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'GET',
	  CURLOPT_HTTPHEADER => array(
	    'Authorization: bearer '.$token
	  ),
	));
	
	$response = curl_exec($curl);
	
	$logger->log("response : $response");
	
	curl_close($curl);
	return $response;
}

function getWebTrackingIdentifier($TEST, $accID){
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from DV_TaskStatus where acctID = ? and task = 'CreateWebTracking' limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($accID));
	$affected_rows = $Stmt->rowCount();
	while ($row2 = $Stmt->fetch()) {		
		$description = $row2['description'];
		$jsonRow = json_decode($description);
		$id = $jsonRow->identifier;
		if ($TEST == 't') {
			echo "<br>description<br>";
			var_dump($description);
			echo "<br>";
			echo "<br>id<br>";
			var_dump($id);
			echo "<br>";
		}
	}
	if ($id) { } else { $id = ''; }
	return $id;
}


function testCloudConnect($TEST, $accID) {

	
	$id = getWebTrackingIdentifier($TEST, $accID);
	
	$serverMode = GetCurrentConfig()->serverName;
	$jsFile = GetCurrentConfig()->dvTrackingEndpoint;
	

	$reportURL = "https://track.my-dv.com/$jsFile?id=$id&aid=$accID&oid=1&track=false";		

	$ch = curl_init($reportURL);

	//curl_setopt($ch, CURLOPT_MUTE, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$rows = curl_exec($ch);

	curl_close($ch);

	$jsonRow = json_decode($rows);
	$Response = $jsonRow->Response;
	if ("Connection_Successful!" == $Response) {
		$success = true;
	} else {
		$success = false;
	}
	return array('success'=>$success, 'Response'=>$Response, 'reportURL'=>$reportURL );
}


function getTicket($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword) {
	if ($_SESSION['STUDIOTICKET']) {		
		$userTicket = $_SESSION['STUDIOTICKET'];
	} else {
		$userTicket = getTicketCurl($SelectedAccountID, $email, $pwd, $PartnerGuid, $PartnerPassword);
	}

	return $userTicket;
}

function getTokenCurl($ACCOUNTID, $EMAIL, $PWD) {
	
	$parentID = getStudioParentID($ACCOUNTID);
	if ($parentID != '-1') {
		$ACCOUNTID = $parentID;
	}
	
	if ($PWD) { } else {	$PWD = ''; }
	if ($EMAIL) { } else {	$EMAIL = ''; }
	
	if (($EMAIL == '') || ($PWD == '')) {
		$studioParam = GetStudioAPIParam();
   	$EMAIL = $studioParam['email'];
   	$PWD = $studioParam['pwd'];
	}
	
	$logger2 = new FileLogger("getReportCampaignStat.log");
	$logger2->log("getTokenCurl EMAIL : $EMAIL, PWD : $PWD, ACCOUNTID : $ACCOUNTID");
	
	$tokenReq = array(
        "AccountID"=> $ACCOUNTID,
		  "UserEmail"=> $EMAIL,
        "UserPass"=> $PWD
    );
	
	$tokenRes = callReportService("Authenticate", $tokenReq);
	$token = $tokenRes->{"Token"};
	return $token;
}

function getToken($ACCOUNTID, $EMAIL, $PWD) {
	$logger2 = new FileLogger("getReportCampaignStat.log");
	
	if ($_SESSION['STUDIOTOKEN']) {		
		$logger2->log("token : session");
		$token = $_SESSION['STUDIOTOKEN'];
	} else {
		$logger2->log("token : getTokenCurl");
		$token = getTokenCurl($ACCOUNTID, $EMAIL, $PWD);
	}
	return $token;	
}

function getAndCheckToken($ACCOUNTID, $EMAIL, $PWD) {
    error_log("-SESSION- getAndCheckToken");
	if ($_SESSION['STUDIOTOKEN']) {		
		$token = $_SESSION['STUDIOTOKEN'];
		$ip = get_client_ip();		
		$reportURL = "https://studio.dashboard.mdl.io/api/Report/ValidateToken?ai=$ACCOUNTID&ip=$ip";		

		$ThisURL = "$reportURL&token=$token";
	
		$ch = curl_init($ThisURL);

		//curl_setopt($ch, CURLOPT_MUTE, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$rows = curl_exec($ch);

		curl_close($ch);
		
		if ($rows == 'OK') {

		} else {
			$token = getTokenCurl($ACCOUNTID, $EMAIL, $PWD);
			$_SESSION['STUDIOTOKEN'] = $token;
		}

	} else {
		$token = getTokenCurl($ACCOUNTID, $EMAIL, $PWD);
	}
	return $token;	
}

function getxmediaAPI($account) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from xmediaAPI where accountname = ? limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($account));
	$affected_rows = $Stmt->rowCount();
	$FieldNames = array();
	if ($affected_rows > 0) {
		while ($row2 = $Stmt->fetch()) {	
			$FieldNames[] = $row2['PartnerGuid'];
			$FieldNames[] = $row2['PartnerGuid'];
		}	
	} else {
		$FieldNames[] = '';
		$FieldNames[] = '';
	}
	return $FieldNames;
}

function getxmediaAPIaccount() {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from xmediaAPI";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute();
	$affected_rows = $Stmt->rowCount();	
	$AccountNames = array();
	if ($affected_rows > 0) {
		while ($row2 = $Stmt->fetch()) {	
			$r1 = $row2['accountname'];
			$AccountNames[] = $r1;
		}	
	}
	return $AccountNames;
}


function getxmediaAPICouchDB($account) {
	if($db == null){
        $db = GetPDOMySQL();
    }
	$FieldNames = array();
	try {  
		$stmt = $db->query("SELECT * FROM DV_studioAPI where accountname='$account'");
		$row2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

		
		
		if ($row2) {
			foreach( $row2 as $row1 ) {		
				if(strtolower($account) == strtolower($row1['accountname'])){
					$PartnerGuid = $row1['PartnerGuid'];
					$FieldNames[] = secured_decrypt($PartnerGuid);
					$PartnerPwd = $row1['PartnerPwd'];
					$FieldNames[] = secured_decrypt($PartnerPwd);
					return $FieldNames;
				}
			}
		} else {
			$FieldNames[] = '';
			$FieldNames[] = '';
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
		$FieldNames[] = '';
		$FieldNames[] = '';
	}

   return $FieldNames;
}

function getxmediaAPIAcctID($acctID) {
	if($db == null){
        $db = GetPDOMySQL();
    }
	$FieldNames = array();
	try {  
		$stmt = $db->query("SELECT * FROM DV_studioAPI where acctID='$acctID'");
		$row2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

		
		
		if ($row2) {
			foreach( $row2 as $row1 ) {		
				if(strtolower($acctID) == strtolower($row1['acctID'])){
					$PartnerGuid = $row1['PartnerGuid'];
					$FieldNames[] = secured_decrypt($PartnerGuid);
					$PartnerPwd = $row1['PartnerPwd'];
					$FieldNames[] = secured_decrypt($PartnerPwd);
					return $FieldNames;
				}
			}
		} else {
			$FieldNames[] = '';
			$FieldNames[] = '';
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
		$FieldNames[] = '';
		$FieldNames[] = '';
	}

   return $FieldNames;
}


function getxmediaAPIaccountCouchDB() {
    
	if($db == null){
        $db = GetPDOMySQL();
    }
    $stmt = $db->query("SELECT * FROM DV_studioAPI");
    $row2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$AccountNames = array();
	if ($row2) {
		foreach( $row2 as $row1 ) {			
			$r1 = $row1['accountname'];
			$AccountNames[] = $r1;
		}
	}
	return $AccountNames;
}

function getxmediaAPIaccountIDCouchDB() {
    
	if($db == null){
        $db = GetPDOMySQL();
    }
    $stmt = $db->query("SELECT * FROM DV_studioAPI");
    $row2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$AccountIDs = array();
	if ($row2) {
		foreach( $row2 as $row1 ) {			
			$r1 = $row1['acctID'];
			$AccountIDs[] = $r1;
		}
	}
	return $AccountIDs;
}


define ('UTF32_BIG_ENDIAN_BOM'   , chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF));
define ('UTF32_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00));
define ('UTF16_BIG_ENDIAN_BOM'   , chr(0xFE) . chr(0xFF));
define ('UTF16_LITTLE_ENDIAN_BOM', chr(0xFF) . chr(0xFE));
define ('UTF8_BOM'               , chr(0xEF) . chr(0xBB) . chr(0xBF));

function ToUTF8NoBOM($filename) {
    $mode = "";
    $text = file_get_contents($filename);
    //logDebug("ToUTF8NoBOM fileName [$filename]");
    //logDebug("ToUTF8NoBOM text [$text]");
    $first2 = substr($text, 0, 2);
    $first3 = substr($text, 0, 3);
    $first4 = substr($text, 0, 3);
    
    if ($first3 == UTF8_BOM) $mode = 'UTF-8';
    elseif ($first4 == UTF32_BIG_ENDIAN_BOM) $mode =  'UTF-32BE';
    elseif ($first4 == UTF32_LITTLE_ENDIAN_BOM) $mode =  'UTF-32LE';
    elseif ($first2 == UTF16_BIG_ENDIAN_BOM) $mode =  'UTF-16BE';
    elseif ($first2 == UTF16_LITTLE_ENDIAN_BOM) $mode =  'UTF-16LE';
    
    //logDebug("BOM=$mode");
    if($mode == 'UTF-8'){
        return substr($text, 3);
    }
    if(($mode == 'UTF-16LE')||($mode == 'UTF-16BE')){
        return mb_convert_encoding(substr($text, 2), "UTF-8", $mode);
    }
    //return $text;
    // Assume Windows-1252 
    return mb_convert_encoding($text, "UTF-8", "Windows-1252");
}


function ImportContacts($TEST, $FileName, $Mapping, $importName, $userTicket, $email, $file, $recordCount, $importType, $accountName, $ACCOUNTID) {
	$logger = new FileLogger("doImportFile2.log"); 

	$importPath = 'import/importFile';
	
	$csvName = basename($file);
	$zipName = str_ireplace('.csv', '.zip', $csvName);
	
	
	$logger->log(" csvName : $csvName , zipName : $zipName , file : $file" );
	
	$result = zipImportFile($importPath, $zipName, $csvName);
	$zipFile = $result['zipFile'];
	
	$logger->log("zipImportFile = ".  json_encode($result) );

	$output = file_get_contents($zipFile);
	
	//$lines = file($file);
	//$lines[0] = str_replace('/', "", $lines[0]);
	//$output = implode("",$lines);
	

	$byteArr = str_split($output);
	foreach ($byteArr as $key=>$val) { 
		$byteArr[$key] = ord($val); 
	}
   //Correct Mapping incase of have /"' in mapping remove it
   $Mapping = str_replace("'", "", $Mapping);
   $Mapping = str_replace('"', "", $Mapping);
   $Mapping = str_replace('/', "", $Mapping);
   
	$ImportRequest   = array
	(
		"FileName" => $FileName,
		"Filterable" => true,
		"Mapping" => $Mapping,
		"Mode" => 1,
		"Name" => $importName,
		"NotificationEmail" => $email,
		"CSV" => $byteArr,
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		),
		"CsvFormat" => 0
	);

	$logger->log(" 1 ImportContacts [ImportRequest] : ".json_encode($ImportRequest) );

    //file_put_contents("logs/kkk.txt",json_encode($ImportRequest));
    //return; 
    
	$ImportResponse = callService("contactservice/ImportContacts", $ImportRequest);
	$logger->log(" 1 ImportContacts [ImportResponse] : ".json_encode($ImportResponse) );
	$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
    logDebug($ErrorCode);
	if ($TEST == 't') {
		echo "ImportContacts $ACCOUNTID, $importName, $filename, $contactCount<br>\n";
	}
	if ($ErrorCode == "") {
		$errorMessage = '';
		$ExceptionMessage = '';	

		
	} else {
		//$errorMessage = "ImportResponse ERROR : <br> ErrorMessage -> ".$ImportResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		//"ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
        /*$errorCodeMap = [
            "ImportNotAllowedOnTOFEnabledAccounts"=>"The account is Tof Enabled, the import is not allowed",
            "EmptyCsvData"=> "There is no CSV content",
            "ImportNameRequired"=>"You set the Filterable to True and do not provide an Import Name",
            "ImportNameNotUnique"=>"The provided Import Name is not unique",
            "EmptyFileName"=>"Provided FileName is empty",
            "InvalidCsvFormat"=> "Provided CSV content has invalid format or system could not open the content as a CSV file",
            "UnableToUploadToCloud"=>"There was an issue to upload the CSV file to S3.",
            "InvalidCsvColumnName"=>"CSV column name contains invalid character",
            "DuplicateCsvColumnMapping"=>"CSV column mapped to an invalid field",
            "NoCsvColumnMapped"=>"There is no CSV field mapping",
            "ImportFileNameTooLong"=>"File Name too long (>99)",
            "ImportFullNameTooLong"=>"CSV file address too long (>255)",
            "ImportNameTooLong"=>"Name too long (> 255)",
            "ImportNotificationEmailTooLong"=>"Provided notification email is too long (> 255)",
            "GeneralException"=>"An exception happened during the process",
            "UnableToLockService"=>"Unable to lock the service call. Another concurrent import taking too long.",
            "InvalidUser"=>"Provided user credentials is not valid or user does not have enough permission",
        ];*/
        
		$errorMessage = $ImportResponse->{"Result"}->{"ErrorMessage"};
		$ExceptionMessage = $ImportResponse->{"Result"}->{"ExceptionMessage"};
        //ExceptionMessage"Message: 'zip' is not a valid contact field name. ...
        if (strpos($ExceptionMessage, 'is not a valid contact field name') !== false) {
            preg_match("/'([^']+)'/", $ExceptionMessage, $matches);
            $errorMessage = "Duplicate database field name [".$matches[1]."]";
            logDebug($matches);
        }
	}
	

	$result['ExceptionMessage'] = $ExceptionMessage;
	$result['errorMessage'] = $errorMessage;
	return $result;
	//return $errorMessage;
}

function GetContactFieldList($TEST, $userTicket){
	return GetContactFieldListType($TEST, $userTicket, 0);
}

function GetContactFieldListType($TEST, $userTicket,$fieldType=0){
	
	
	$logger = new FileLogger("login1.log");
	$logger->log(" GetContactFieldListType TEST : $TEST , userTicket : $userTicket , fieldType : $fieldType");
	
    error_log("-SESSION- GetContactFieldListType");
	$sess_contactlist = "CONTACTFIELDLIST-$fieldType"; 
	
	$logger->log(" sess_contactlist : $sess_contactlist");
	
	if ($_SESSION[$sess_contactlist]) {		
		$rows = $_SESSION[$sess_contactlist];
		$logger->log("From session");
		$logger->log("rows ".json_encode($rows));
	} else {
		$rows = GetContactFieldListAPI($TEST, $userTicket,$fieldType);
		$_SESSION[$sess_contactlist] = $rows;
		$logger->log("From studio");
	}	
	return $rows;
}

function GetContactFieldListAPI($TEST, $userTicket,$fieldType=0){
	$logger = new FileLogger("login1.log"); 	
	$logger->log(" GetContactFieldListAPI userTicket : $userTicket, fieldType : $fieldType");
	$rows = array();
	$GetContactFieldListRequest   = array
	(		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		),
		"Type" => $fieldType
	);
	
	
	$logger->log(" GetContactFieldListAPI GetContactFieldListRequest : ".json_encode($GetContactFieldListRequest));
	
	$GetContactFieldListResponse = callService("contactservice/GetContactFieldList", $GetContactFieldListRequest);
	
	$logger->log(" GetContactFieldListAPI GetContactFieldListResponse : ".json_encode($GetContactFieldListResponse));
	
	$ErrorCode = $GetContactFieldListResponse->{"Result"}->{"ErrorCode"};
	$logger->log(" ErrorCode : $ErrorCode ");
	if ($ErrorCode == "") {
		$ImportList = $GetContactFieldListResponse->{"FieldList"};
		foreach ($ImportList as $x){
			//echo "Name = ".$ImportList->{'Name'}."<br>";
			$importName = $x->{'Name'};
			if (($importName != '') && ($importName != 'importlistname')) {
				$group = array(					
					'Name'=>$x->{'Name'},
					'Value'=>$x->{'Name'},
					'DataType_ID'=>$x->{'DataType_ID'},
					'DataTypeName'=>$x->{'DataTypeName'},
					'Description'=>$x->{'Description'},
					'IsEncrypted'=>$x->{'IsEncrypted'},
					'PURLIndex'=>$x->{'PURLIndex'},
					'DedupGroupIndex'=>$x->{'DedupGroupIndex'}
				);					
				$rows[] = $group;
			}			
		}
		
		if ($fieldType == 0) {
			$group = array(					
				'Name'=>'List Name',
				'Value'=>'importname',
				'DataType_ID'=>'1',
				'DataTypeName'=>'String',
				'Description'=>'importname',
				'IsEncrypted'=>false,
				'PURLIndex'=>0,
				'DedupGroupIndex'=>0
			);					
			$rows[] = $group;
		}
		array_sort($rows, 'Name', SORT_ASC);
	} else {
		$errorMessage = "GetContactFieldListResponse ERROR : <br> ErrorMessage -> ".$GetContactFieldListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GetContactFieldListResponse->{"Result"}->{"ExceptionMessage"};

		//echo $errorMessage;
		
	}

	return $rows;
}

function GetContactFieldListCredentials($TEST, $Credentials,$fieldType=0){
	$logger = new FileLogger("login1.log"); 	
	$logger->log(" GetContactFieldListCredentials () fieldType=$fieldType , Credentials : ".json_encode($Credentials));
	$rows = array();
	$GetContactFieldListRequest   = array
	(		
		"Credentials" => $Credentials,
		"Type" => $fieldType
	);
	$logger->log(" GetContactFieldListCredentials () GetContactFieldListRequest : ".json_encode($GetContactFieldListRequest));	

	$GetContactFieldListResponse = callServiceCredential("contactservice/GetContactFieldList", $GetContactFieldListRequest);	
	$logger->log(" GetContactFieldListCredentials () GetContactFieldListResponse : ".json_encode($GetContactFieldListResponse));
	
	$ErrorCode = $GetContactFieldListResponse->{"Result"}->{"ErrorCode"};
	$logger->log(" ErrorCode : $ErrorCode ");
	if ($ErrorCode == "") {
		$ImportList = $GetContactFieldListResponse->{"FieldList"};
		foreach ($ImportList as $x){
			//echo "Name = ".$ImportList->{'Name'}."<br>";
			$importName = $x->{'Name'};
			if (($importName != '') && ($importName != 'importlistname')) {
				$group = array(					
					'Name'=>$x->{'Name'},
					'Value'=>$x->{'Name'},
					'DataType_ID'=>$x->{'DataType_ID'},
					'DataTypeName'=>$x->{'DataTypeName'},
					'Description'=>$x->{'Description'},
					'IsEncrypted'=>$x->{'IsEncrypted'},
					'PURLIndex'=>$x->{'PURLIndex'},
					'DedupGroupIndex'=>$x->{'DedupGroupIndex'}
				);					
				$rows[] = $group;
			}			
		}
		
		if ($fieldType == 0) {
			$group = array(					
				'Name'=>'List Name',
				'Value'=>'importname',
				'DataType_ID'=>'1',
				'DataTypeName'=>'String',
				'Description'=>'importname',
				'IsEncrypted'=>false,
				'PURLIndex'=>0,
				'DedupGroupIndex'=>0
			);					
			$rows[] = $group;
		}
		array_sort($rows, 'Name', SORT_ASC);
	} else {
		$errorMessage = "GetContactFieldListResponse ERROR : <br> ErrorMessage -> ".$GetContactFieldListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GetContactFieldListResponse->{"Result"}->{"ExceptionMessage"};
		//echo $errorMessage;		
	}
	return $rows;
}

function GetUTMField($studioFields) {
	$group = array(					
		'Name'=>'outboundcontact_id',
		'Value'=>'outboundcontact_id',
		'DataType_ID'=>'1',
		'DataTypeName'=>'String',
		'Description'=>'outboundcontact_id',
		'IsEncrypted'=>false,
		'PURLIndex'=>0,
		'DedupGroupIndex'=>0
	);					
	$studioFields[] = $group;
	
	return $studioFields;
}

function GetContactFieldListToken($TEST, $acctID, $token, $fieldType=0){
	$logger = new FileLogger("login1.log"); 	
	$logger->log(" GetContactFieldListToken acctID : $acctID, token : $token, fieldType : $fieldType");
	
	$rows = array();
	$GetContactFieldListRequest   = array
	(		
		"Credentials" => array
		(
			"AccountID" => $acctID,        
			"IdentityToken" => $token,
		),
		"Type" => $fieldType
	);
	
	$request_string = json_encode($GetContactFieldListRequest); 
	
	$logger->log(" request_string : $request_string ");
	
	$studioEndpointAPI = getStudioEndpointAPI();
	
	$service = curl_init($studioEndpointAPI."contactservice/GetContactFieldList");   
    curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   
	$response_string = curl_exec($service);
	
	$logger->log(" response_string : $response_string ");
	
	$GetContactFieldListResponse = json_decode($response_string);
	
	$ErrorCode = $GetContactFieldListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$ImportList = $GetContactFieldListResponse->{"FieldList"};
		foreach ($ImportList as $x){
			//echo "Name = ".$ImportList->{'Name'}."<br>";
			$importName = $x->{'Name'};
			if (($importName != '') && ($importName != 'importlistname')) {
				$group = array(					
					'Name'=>$x->{'Name'},
					'Value'=>$x->{'Name'},
					'DataType_ID'=>$x->{'DataType_ID'},
					'DataTypeName'=>$x->{'DataTypeName'},
					'Description'=>$x->{'Description'},
					'IsEncrypted'=>$x->{'IsEncrypted'},
					'PURLIndex'=>$x->{'PURLIndex'},
					'DedupGroupIndex'=>$x->{'DedupGroupIndex'}
				);					
				$rows[] = $group;
			}			
		}
		
		if ($fieldType == 0) {
			$group = array(					
				'Name'=>'List Name',
				'Value'=>'importname',
				'DataType_ID'=>'1',
				'DataTypeName'=>'String',
				'Description'=>'importname',
				'IsEncrypted'=>false,
				'PURLIndex'=>0,
				'DedupGroupIndex'=>0
			);					
			$rows[] = $group;
		}
		array_sort($rows, 'Name', SORT_ASC);
	} else {
		$errorMessage = "GetContactFieldListResponse ERROR : <br> ErrorMessage -> ".$GetContactFieldListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GetContactFieldListResponse->{"Result"}->{"ExceptionMessage"};

		//echo $errorMessage;
		
	}

	return $rows;
}


function GetContactCountCredentials($TEST, $Credentials, $filter, $ACCOUNTID){
	
	
	
	
	$logger = new FileLogger("GetContactCountCredentials.log"); 	
	
	$filterEncoded = filterEncoded($filter, $logger);
	
	$logger->log(" GetContactCountCredentials filterEncoded :  " . $filterEncoded); 
	$rows = array();
	$Count = "0";
	$ContactListCountRequest   = array(	
		"Credentials" => $Credentials,	  
		"Account_ID" => $ACCOUNTID,
		"Filter" => $filterEncoded
	);
	$logger->log(" ContactListCountRequest :  " . json_encode($ContactListCountRequest));
	$ContactListCountResponse = callServiceCredential("contactservice/GetContactListCount", $ContactListCountRequest);
	
	$ErrorCode = $ContactListCountResponse->{"Result"}->{"ErrorCode"};


	if ($TEST == 't') {
		echo "<br>ContactListCountRequest<br>";
		var_dump($ContactListCountRequest);
		echo "<br>";
		echo "<br>ContactListCountResponse<br>";
		var_dump($ContactListCountResponse);
		echo "<br>";
	}

	$logger->log(" ErrorCode :  " . $ErrorCode);
	if ($ErrorCode == "") {
		$Count = $ContactListCountResponse->{"Count"};		
	} else {
		$errorMessage = "ContactListCountResponse ERROR : <br> ErrorMessage -> ".$ContactListCountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListCountResponse->{"Result"}->{"ExceptionMessage"};		
	}
	$logger->log(" Count :  " . $Count);
	return $Count;
}

function GetContactCount($TEST, $userTicket, $filter, $ACCOUNTID){
	$rows = array();
	$Count = "0";
	
	
	
	$logger = new FileLogger("GetContactCount.log"); 	
	
	$filterEncoded = filterEncoded($filter, $logger);
	
	$logger->log(" GetContactCount filterEncoded :  " . $filterEncoded);
	
	$ContactListCountRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		),
		"Account_ID" => $ACCOUNTID,
		"Filter" => $filterEncoded
	);
	$ContactListCountResponse = callService("contactservice/GetContactListCount", $ContactListCountRequest);
	$ErrorCode = $ContactListCountResponse->{"Result"}->{"ErrorCode"};
	if ($TEST == 't') {
		echo "<br>ContactListCountRequest<br>";
		var_dump($ContactListCountRequest);
		echo "<br>";
		echo "<br>ContactListCountResponse<br>";
		var_dump($ContactListCountResponse);
		echo "<br>";
	}
	if ($ErrorCode == "") {
		$Count = $ContactListCountResponse->{"Count"};		
	} else {
		$errorMessage = "ContactListCountResponse ERROR : <br> ErrorMessage -> ".$ContactListCountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListCountResponse->{"Result"}->{"ExceptionMessage"};		
	}

	return $Count;
}

function GetContact($TEST, $userTicket, $filter, $ACCOUNTID){	
	
	$FieldNames = array("FirstName","LastName","Email","PURL","Phone","Address1","City","State","Zip","Company","Mobile","Country");
	$result = GetContactWithFields($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, "Y");

	return $result;

}

function GetContactWithFields($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, $saveFile, $AliasNames=null){

	$logger = new FileLogger("GetContactWithFields.log"); 	

	$logger->log("userTicket = $userTicket , ACCOUNTID = $ACCOUNTID , filter=$filter , $saveFile/$AliasNames , fieldname ". json_encode($FieldNames)  );	

	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';

	//$FieldNames = array("FirstName","LastName","Email","Phone","Address1","City","State","Zip");
	$hasAlias = false; 
	if($AliasNames!=null){
		$hasAlias = true; 
		$logger->log("fn[GetContactWithFields]: hasAlias = [$hasAlias]"  );	
		$aHeader = implode(",",$AliasNames); 
	}
	$filterEncoded = filterEncoded($filter, $logger);
	$ContactListRequest   = array	(		
		"Credentials" => array(
			"Ticket" => $userTicket        
		),	
		"FieldNames" => $FieldNames,
		"Filter" => $filterEncoded,
		"OutputType" => 1,
	);

	$ContactListResponse = callService("contactservice/GetContactList", $ContactListRequest);
	
	$logger->log( "ALL ContactListRequest = " . json_encode($ContactListRequest) ); 
	$logger->log( "ALL ContactListResponse = " . json_encode($ContactListResponse) ); 
	
	$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$Contacts = $ContactListResponse->{"Contacts"};		
		foreach ($Contacts as $chr) {
			$Contact .= chr($chr);
		}	

		$t = date("mdY-His",time());
		$tmpName = $t.'.csv';
		//$filename = IMPORTPATH.'contactFile/'.$tmpName;
		$filename = 'import/contactFile/'.$tmpName;
		$Contact = trim($Contact);

		if($hasAlias){
			$Contact = $aHeader . "\r\n". stripFirstLine($Contact); 
		}
		
		$logger->log("fn[GetContactWithFields]: Contact = ". json_encode($Contact)  );	

		file_put_contents( $filename, $Contact);			
		$Contactarray = array_map("str_getcsv", explode("\n", $Contact));

		$logger->log("fn[GetContactWithFields]: Contactarray = ". json_encode($Contactarray)  );	

		array_splice($Contactarray, 0, 1);

		$columns = rtrim(strtok($Contact, "\n"));		
		
		$logger->log("fn[GetContactWithFields]: columns = ". $columns );	

		$arr = explode(",", $columns);
		$columns = array();
		if ($arr) {
			foreach( $arr as $row1 ) {	
				$arr1 = array(					
					'title'=>$row1
				);	
				$columns[] = $arr1;
			}
		}
		if ($saveFile != "Y") {
			unlink($filename);
		}
		$success = true;

	}else{
		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> ".$ContactListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListResponse->{"Result"}->{"ExceptionMessage"};
		//$errorMessage = "ImportResponse ERROR : <br> ErrorMessage -> ".$ImportResponse->{"Result"}->{"ErrorMessage"};
		//echo $errorMessage."<BR>";

		$success = false;
	}
	$result = json_encode( array('success'=>$success, 'ticket' => $userTicket, 'Contact' => $Contactarray, 'columns' => $columns, 'errorMessage' => $errorMessage, 'filename' => $filename, 'tmpName' => $tmpName, 'CharContacts' => $Contacts));

	return $result;

}


function GetContactWithFieldsCredentials($TEST, $Credentials, $filter, $ACCOUNTID, $FieldNames, $saveFile, $AliasNames=null){

	$logger = new FileLogger("GetContactWithFieldsCredentials.log"); 	

	$logger->log("fn[GetContactWithFieldsCredentials]:: Credentials = $Credentials , ACCOUNTID = $ACCOUNTID , filter=$filter , $saveFile/$AliasNames , fieldname ". json_encode($FieldNames)  );	

	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';

	//$FieldNames = array("FirstName","LastName","Email","Phone","Address1","City","State","Zip");
	$hasAlias = false; 
	if($AliasNames!=null){
		$hasAlias = true; 
		$logger->log("fn[GetContactWithFieldsCredentials]: hasAlias = [$hasAlias]"  );	
		$aHeader = implode(",",$AliasNames); 
	}
	$filterEncoded = filterEncoded($filter, $logger);
	$ContactListRequest   = array	(		
		"Credentials" => $Credentials,	  
		"FieldNames" => $FieldNames,
		"Filter" => $filterEncoded,
		"OutputType" => 1,
	);
	$ContactListResponse = callServiceCredential("contactservice/GetContactList", $ContactListRequest);	
	$logger->log( "fn[GetContactWithFieldsCredentials]:: ContactListRequest = " . json_encode($ContactListRequest) ); 
	$logger->log( "fn[GetContactWithFieldsCredentials]:: ContactListResponse = " . json_encode($ContactListResponse) ); 
	
	$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$Contacts = $ContactListResponse->{"Contacts"};		
		foreach ($Contacts as $chr) {
			$Contact .= chr($chr);
		}	
		$t = date("mdY-His",time());
		$tmpName = $t.'.csv';
		//$filename = IMPORTPATH.'contactFile/'.$tmpName;
		$filename = 'import/contactFile/'.$tmpName;
		$Contact = trim($Contact);

		if($hasAlias){
			$Contact = $aHeader . "\r\n". stripFirstLine($Contact); 
		}
		
		$logger->log("fn[GetContactWithFieldsCredentials]::  Contact = ". json_encode($Contact)  );	

		file_put_contents( $filename, $Contact);			
		$Contactarray = array_map("str_getcsv", explode("\n", $Contact));

		$logger->log("fn[GetContactWithFieldsCredentials]:: Contactarray = ". json_encode($Contactarray)  );	

		array_splice($Contactarray, 0, 1);

		$columns = rtrim(strtok($Contact, "\n"));		
		
		$logger->log("fn[GetContactWithFieldsCredentials]:: columns = ". $columns );	

		$arr = explode(",", $columns);
		$columns = array();
		if ($arr) {
			foreach( $arr as $row1 ) {	
				$arr1 = array(					
					'title'=>$row1
				);	
				$columns[] = $arr1;
			}
		}
		if ($saveFile != "Y") {
			unlink($filename);
		}
		$success = true;

	}else{
		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> ".$ContactListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListResponse->{"Result"}->{"ExceptionMessage"};
		$success = false;
	}

	return json_encode( array('success'=>$success, 'ticket' => $userTicket, 'Contact' => $Contactarray, 'columns' => $columns, 'errorMessage' => $errorMessage, 'filename' => $filename, 'tmpName' => $tmpName, 'CharContacts' => $Contacts));
}



function GetAcrossContactWithFields($TEST, $Credentials, $filter, $ACCOUNTID, $FieldNames, $saveFile, $AliasNames=null){
	$logger = new FileLogger("global.log"); 	
	$logger->log("userTicket = $userTicket , ACCOUNTID = $ACCOUNTID , filter=$filter , $saveFile/$AliasNames , fieldname ". json_encode($FieldNames)  );	

	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';

	//$FieldNames = array("FirstName","LastName","Email","Phone","Address1","City","State","Zip");
	$hasAlias = false; 
	if($AliasNames!=null){
		$hasAlias = true; 
		$logger->log("fn[GetContactWithFields]: hasAlias = [$hasAlias]"  );	
		$aHeader = implode(",",$AliasNames); 
	}
	$filterEncoded = filterEncoded($filter, $logger);
	$ContactListRequest   = array	(		
		"Credentials" => $Credentials,	
		"FieldNames" => $FieldNames,
		"Filter" => $filterEncoded,
		"OutputType" => 1,
	);

	$ContactListResponse = callServiceCredential("contactservice/GetContactList", $ContactListRequest);
	$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$Contacts = $ContactListResponse->{"Contacts"};		
		foreach ($Contacts as $chr) {
			$Contact .= chr($chr);
		}	

		$t = date("mdY-His",time());
		$tmpName = $t.'.csv';
		//$filename = IMPORTPATH.'contactFile/'.$tmpName;
		$filename = 'import/contactFile/'.$tmpName;
		$Contact = trim($Contact);

		if($hasAlias){
			$Contact = $aHeader . "\r\n". stripFirstLine($Contact); 
		}
		
		$logger->log("fn[GetContactWithFields]: Contact = ". json_encode($Contact)  );	

		file_put_contents( $filename, $Contact);			
		$Contactarray = array_map("str_getcsv", explode("\n", $Contact));

		$logger->log("fn[GetContactWithFields]: Contactarray = ". json_encode($Contactarray)  );	

		array_splice($Contactarray, 0, 1);

		$columns = rtrim(strtok($Contact, "\n"));		
		
		$logger->log("fn[GetContactWithFields]: columns = ". $columns );	

		$arr = explode(",", $columns);
		$columns = array();
		if ($arr) {
			foreach( $arr as $row1 ) {	
				$arr1 = array(					
					'title'=>$row1
				);	
				$columns[] = $arr1;
			}
		}
		if ($saveFile != "Y") {
			unlink($filename);
		}
		$success = true;

	}else{
		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> ".$ContactListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListResponse->{"Result"}->{"ExceptionMessage"};
		$success = false;
	}

	return array('success'=>$success, 'ticket' => $userTicket, 'Contact' => $Contactarray, 'columns' => $columns, 'errorMessage' => $errorMessage, 'filename' => $filename, 'tmpName' => $tmpName, 'CharContacts' => $Contacts);
}


function GetContactsByPage($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, $AliasNames=null, $EMAIL,$moredata) {
	$logger = new FileLogger("GetContactsByPage.log"); 	
	$LISTNAME = $moredata['LISTNAME'];
	
	$logger->log("userTicket = $userTicket , EMAIL = $EMAIL, LISTNAME = $LISTNAME , ACCOUNTID = $ACCOUNTID , filter=$filter ,  $AliasNames , fieldname ". json_encode($FieldNames)  );	
	
	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';
	$Contact1 = "";
	
	$count = GetContactCount($TEST, $userTicket, $filter, $ACCOUNTID);
    $contentTotal = $count; 
    $maxRows = 5000 ;
    // $maxRows = 50 ; 
    $pages = ceil($count / $maxRows );
	$logger->log("Count[$count] / maxRow[$maxRows] = totalPages[" .$pages."]" );
	
	$groupID = 1;
	
	for($i = 0;$i < $pages;$i++){
        // $logger->log("i[" .$i . "] listname[".$listname."]" );
        $filterEncoded = filterEncoded($filter, $logger);
        $ContactListRequest   = array	(
    		"Credentials" => array(
    			"Ticket" => $userTicket        
    		),
            "Account_ID" => $ACCOUNTID,
    		"FieldNames" => $FieldNames,
    		"Filter" => $filterEncoded,
            "MaxRows" => $maxRows,
            "StartRowIndex" => $i ,   // actually this is page index rowindex = StartRowIndex*MaxRows
    	);
    	$logger->log("Person i[".$i."]-total count[$count], listname[".$listname."]"); 
     	
    	$ContactListResponse = callService("contactservice/GetContactsByPage", $ContactListRequest);
    	$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
        $logger->log("ErrorCode by page = ".$ErrorCode); 
        if ($ErrorCode == "") {
        	try{
        	    // $totalContact = $ContactListResponse->{"ContactsCount"};
        		$Contact1 = $ContactListResponse->{"ContactsCsv"};
        		$Contact1 = str_replace("<","&lt;",$Contact1);
        		if ($groupID > 1) {
        			$Contact1 = stripFirstLine($Contact1);
        		}
        		$groupID++;
        		$Contact1 = trim($Contact1);
        		
        		//$logger->log("Contact1");
        		//$logger->log($Contact1 ); 
    	        //$logger->log("List user = ".$Contact1); 
    	        
    	        
        		$Contactarray1 = array_map("str_getcsv", explode("\n", $Contact1));
        		array_splice($Contactarray1, 0, 1);      // Remove Header
                array_splice($Contactarray1, -1, 1);     // Remove last empty record
                
                $Contactarray = array_merge($Contactarray, $Contactarray1); 
                $Contact = $Contact."\r\n".$Contact1; 
    	        
        	}catch(PDOException $er){
    		    $logger->log("ERROR catch fn[GetContactsByPage]" .$er->getMessage() );   
        	}
        }else{
    		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> ".$ContactListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
    		"ExceptionMessage : ".$ContactListResponse->{"Result"}->{"ExceptionMessage"};
    		break; 
        }
	}
	
	$logger->log("Done");
	$Contact = trim($Contact);
	
	//$Contacts = string2ByteArray($Contact);
	
	$result['Contact'] = $Contact;
	$result['Contactarray'] = $Contactarray;
	$result['pages'] = $pages;
	//$result['Contacts'] = $Contacts;
	
	return $result;
}

//#2130
function GetContactWithFieldsToS3($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, $AliasNames=null, $EMAIL,$moredata) {
	$logger = new FileLogger("GetContactWithFieldsToS3.log"); 	
	$LISTNAME = $moredata['LISTNAME'];
	
	$logger->log("userTicket = $userTicket , EMAIL = $EMAIL, LISTNAME = $LISTNAME , ACCOUNTID = $ACCOUNTID , filter=$filter ,  $AliasNames , fieldname ". json_encode($FieldNames)  );	
	
	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';
	$Contact1 = "";
	

	//$FieldNames = array("FirstName","LastName","Email","Phone","Address1","City","State","Zip");
	$hasAlias = false; 
	if($AliasNames!=null){
		$hasAlias = true; 
		$logger->log("fn[GetContactWithFieldsToS3]: hasAlias = [$hasAlias]"  );	
		$aHeader = implode(",",$AliasNames); 
	}
	
	$ContactsByPage = GetContactsByPage($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, $AliasNames=null, $EMAIL,$moredata);
	$Contact = $ContactsByPage['Contact'];
	$Contactarray = $ContactsByPage['Contactarray'];
	$pages = $ContactsByPage['pages'];
	
	
	//$logger->log("Contactarray = ". json_encode($Contactarray)  );	
	$ErrorCode = '';
	
	$logger->log("fn[GetContactWithFieldsToS3]: ErrorCode = [$ErrorCode]"  );	
	
	if ($ErrorCode == "") {
			

		$t = date("mdY-His",time());
		$tmpName = $t.'.csv';
		//$filename = IMPORTPATH.'contactFile/'.$tmpName;	
		//$filename = 'import/contactFile/'.$tmpName;
		$filename = '/dvExportContact/'.$tmpName;

		$Contact = trim($Contact);
		if($hasAlias){
			$Contact = stripFirstLine($Contact);
			$Contact = trim($Contact);
			$Contact = $aHeader."\r\n".$Contact; 
		}		
		//$logger->log("fn[GetContactWithFields]: Contact = ". json_encode($Contact)  );	
		//file_put_contents( $filename, $Contact);	
		$logger->log("fn[GetContactWithFieldsToS3]: s3_put_contents 1"  );
		
		$S3File = s3_put_contents($filename ,$Contact);
		
		$logger->log("fn[GetContactWithFieldsToS3]: s3_put_contents 2"  );

		//$Contactarray = array_map("str_getcsv", explode("\n", $Contact));
		//$logger->log("fn[GetContactWithFields]: Contactarray = ". json_encode($Contactarray)  );	

		array_splice($Contactarray, 0, 1);
		$columns = rtrim(strtok($Contact, "\n"));				
		$logger->log("fn[GetContactWithFields]: columns = ". $columns );	
		$logger->log("fn[GetContactWithFields]: S3File = ". $S3File );

		$arr = explode(",", $columns);
		$columns = array();
		if ($arr) {
			foreach( $arr as $row1 ) {	
				$arr1 = array(					
					'title'=>$row1
				);	
				$columns[] = $arr1;
			}
		}
		
		$userInfo = getDVUserinfo($ACCOUNTID);
		$acctname = $userInfo['studioAccountName'];
		
		$S3FileURL = $S3File;
		
		
		$reportData['mode'] = 'downloadcsv';			
		$reportData['acctID'] = $ACCOUNTID;
		$reportData['S3FileURL'] = $S3FileURL;
		$result = createDownloadCSV($reportData);
		$S3FileURL	= $result['stripeReport'];
		
		
		$content = "Click <a href='".$S3FileURL."'>here</a> to download the exported file.<br>Please note that these files will remain available for up to 30 days.";
		$logger->log("S3FileURL = [$S3FileURL]  ===  content=$content");
		
		$subject = "Your Export file [".$LISTNAME."] for account [$acctname].";
		//mail($EMAIL, $subject, $content);			
		$logger->log("MAIL TO =$EMAIL , subject = $subject ");
		$emailRet = sendsocketlabsEmail($EMAIL, $subject, $content, '');
		
		
		$noticeType = 'Export file';
		$NotificationTitle = 'Your export file ['.$LISTNAME.'] is ready.';
		$Notification = $NotificationTitle;
		$NotificationLink = $S3FileURL;
		$moreData['actionHandler'] = 'ON';
		$moreData['NotificationLink'] = $NotificationLink;
		
		$NotificationResult = saveNotification1('', $EMAIL, $ACCOUNTID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink, $moreData);
		
		$success = true;

	}else{
		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> <br> ExceptionMessage : ";
		$success = false;
	}
	return array('success'=>$success, 'ticket' => $userTicket, 'Contact' => $Contactarray, 'columns' => $columns, 'errorMessage' => $errorMessage, 'S3FileURL' => $S3File, 'tmpName' => $tmpName);
}

function GetContactWithFieldsCompleteURLToS3($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames,$domainName,$AliasNames,$EMAIL,$moredata){
	$logger = new FileLogger("exportContact.log"); 	
	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';
	$LISTNAME = $moredata['LISTNAME'];
	$completeURL = $moredata['completeURL'];

	$ContactsByPage = GetContactsByPage($TEST, $userTicket, $filter, $ACCOUNTID, $FieldNames, $AliasNames=null, $EMAIL,$moredata);
	$Contact1 = $ContactsByPage['Contact'];
	$Contactarray = $ContactsByPage['Contactarray'];
	$pages = $ContactsByPage['pages'];
	$ErrorCode = '';
	
	
	if ($ErrorCode == "") {
		//$Contacts = $ContactListResponse->{"Contacts"};
		
		
		
		$logger->log("completeURL = $completeURL");
		$logger->log("genericDomain = $genericDomain");
		
		if ($completeURL == 'Y') {
			
			
			$dname = explode('://', $domainName);
			$canGen2 = false; 
			if(count($dname) ==2 ){
				$domainName2 = $dname[1] ; 
				//$domainName2 = ltrim($domainName2, 'www.');
				$d = explode('www.',trim($domainName2));
				if(count($d)>1){
					$domainName2 = $d[1]; 
				}
				$htp = $dname[0] . "://"; 
				if( (strtolower($dname[0]) == "http") || (strtolower($dname[0]) == "https") ){
					$canGen2 = true; 
				}
			}
			
			
			$domainName3 = explode("/", $domainName2);
			$logger->log("count2 = ".count($domainName3));
			if(count($domainName3) > 1){
				$canGen2 = false;
			} else{
				 	
			}
			
			$logger->log("dname : ".json_encode($dname)." , domainName2 = $domainName2 , canGen2 = $canGen2 ");
			
			$Contacts1 = explode("\r\n", $Contact1);
			
			$logger->log("count = ".sizeof($Contacts1));
			
			
			$rowCount = 0;
			foreach( $Contacts1 as $lines ) {
				
				$target = substr(strrchr($lines, ","), 1);
				$target2 = substr(strrchr($lines, ","), 1);
				$headContact = substr( $lines , 0 , strrpos($lines, ",") ) ;
				
				$logger->log("1 lines : $lines , target : $target , target2 : $target2 , headContact : $headContact");				
				if ($rowCount == 0) {
					
					if($AliasNames!=null){
						$aHeader = implode(",",$AliasNames); 
						$headContact = $aHeader ; 
					}
	
					$target="Complete PURL" ; 
					if($canGen2){
						$target2=",Complete PURL 2" ; 
					}else{
						$target2 = ""; 
					}
					
					$lines = str_lreplace('PURL', $target, $lines);
					
					//$logger->log("1 target = $target , target2 = $target2 , lines = $lines");
					
				} else {
					$purl = $target;
					$target= $domainName."/".$target; 
					if($canGen2){								
						$target2= ",".$htp . $target2. "." .$domainName2; 								
					}else{
						$target2 = ""; 
					}
					
					$logger->log("2 target = $target , target2 = $target2 , lines = $lines");
					
					$lines = str_lreplace($purl, $target, $lines);
				}
				$rowCount++;
				$lines = $lines.$target2."\r\n";
				
				$logger->log("2 lines : $lines");
				
				$Contact = $Contact.$lines;
			}
			$logger->log("All rowCount = $rowCount");
		} else {
			$Contact = $ContactsByPage['Contact'];
			$Contact = trim($Contact);
			if($hasAlias){
				$Contact = stripFirstLine($Contact);
				$Contact = trim($Contact);
				$Contact = $aHeader."\r\n".$Contact; 
			}	
		}
		
		$t = date("mdY-His",time());
		$tmpName = $t.'.csv';
		//$filename = 'import/contactFile/'.$tmpName;
		$filename = '/dvExportContact/'.$tmpName;  //s3

		$Contact = trim($Contact);
		//file_put_contents( $filename, $Contact);	
		$logger->log("fn[GetContactWithFieldsCompleteURLToS3]: s3_put_contents 1"  );
		$S3File = s3_put_contents($filename ,$Contact);
		$logger->log("fn[GetContactWithFieldsCompleteURLToS3]: s3_put_contents 2"  );

		$Contactarray = array_map("str_getcsv", explode("\n", $Contact));		
		array_splice($Contactarray, 0, 1);
		$columns = rtrim(strtok($Contact, "\n"));			
		$arr = explode(",", $columns);
		$columns = array();
		if ($arr) {
			foreach( $arr as $row1 ) {	
				$arr1 = array(					
					'title'=>$row1
				);	
				$columns[] = $arr1;
			}
		}		
		
		$userInfo = getDVUserinfo($ACCOUNTID);
		$acctname = $userInfo['studioAccountName'];
		
		$S3FileURL = $S3File;
		
		$reportData['mode'] = 'downloadcsv';			
		$reportData['acctID'] = $ACCOUNTID;
		$reportData['S3FileURL'] = $S3FileURL;
		$result = createDownloadCSV($reportData);
		$S3FileURL	= $result['stripeReport'];
		
		$content = "Click <a href='".$S3FileURL."'>here</a> to download the exported file.<br>Please note that these files will remain available for up to 30 days.";		
		
		$subject = "Your Export file [".$LISTNAME."] for account [$acctname].";
		//mail($EMAIL, $subject, $content);			
		$logger->log("MAIL TO =$EMAIL , subject = $subject ");
		$logger->log("=======END==== S3 Excel LINK = [$S3FileURL]");
		$emailRet = sendsocketlabsEmail($EMAIL, $subject, $content, '');
		
		$noticeType = 'Export file';
		$NotificationTitle = 'Your export file ['.$LISTNAME.'] is ready.';
		$Notification = $NotificationTitle;
		$NotificationLink = $S3FileURL;
		$moreData['actionHandler'] = 'ON';
		$moreData['NotificationLink'] = $NotificationLink;
		
		$NotificationResult = saveNotification1('', $EMAIL, $ACCOUNTID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink, $moreData);
		
		$success = true;

		
	}	
	
	return array('success'=>$success, 'ticket' => $userTicket, 'Contact' => $Contactarray, 'columns' => $columns, 'errorMessage' => $errorMessage, 'S3FileURL' => $S3File, 'tmpName' => $tmpName);
}

function array_sort($array, $on, $order=SORT_ASC){
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
            break;
            case SORT_DESC:
                arsort($sortable_array);
            break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}

function arraySortValue($array, $on, $order=SORT_ASC){
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }
        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
            break;
            case SORT_DESC:
                arsort($sortable_array);
            break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[] = $array[$k];
        }
    }

    return $new_array;
}

function mapOperator($op) {
	$ret = 'Contains';
	if ($op == 'eq') {
		$ret = 'Equal';
	} else if ($op == 'ne') {
		$ret = 'NotEqual';
	} else if ($op == 'bw') {
		$ret = 'StartsWith';
	} else if ($op == 'bn') {
		$ret = 'NotStartsWith';
	} else if ($op == 'ew') {
		$ret = 'EndsWith';
	} else if ($op == 'en') {
		$ret = 'NotEndsWith';
	} else if ($op == 'cn') {
		$ret = 'Contains';
	} else if ($op == 'nc') {
		$ret = 'NotContains';
	} else if ($op == 'lt') {
		$ret = 'Before';
	} else if ($op == 'le') {
		$ret = 'BeforeEqual';
	} else if ($op == 'gt') {
		$ret = 'After';
	} else if ($op == 'ge') {
		$ret = 'AfterEqual';
	} else if ($op == 'in') {
		$ret = 'Contains';
	} else if ($op == 'ni') {
		$ret = 'NotContains';
	}

	return $ret;

}


function getJsonFilter($jsonFilters) {
	$Filter = '';

	if ($jsonFilters) {
		$JoinOperator = "";
		$CriteriaRow = "";

		$row = 0;

		
		
		$JoinOperator = $jsonFilters->{"groupOp"};
		if ($JoinOperator == 'AND') {
			$JoinOperator = "&amp;";
		} else if ($JoinOperator == 'OR') {
			$JoinOperator = "|";
		}

		$rules = $jsonFilters->{"rules"};
		foreach ($rules as $rule) {
			$field	= $rule->{"field"};
			$op		= $rule->{"op"};
			//$op		= mapOperator($op);
			$data	= $rule->{"data"};
			//echo "field = $field, op = $op, data = $data<br>";
			$row++;
			$data = htmlspecialchars($data);  //ISSUE 1356 
			$CriteriaRow1 .= "Criteria Row=\"$row\" Field=\"$field\" Operator=\"$op\" Value=\"$data\" <br>";
			$CriteriaRow .= "<Criteria Row=\"$row\" Field=\"$field\" Operator=\"$op\" Value=\"$data\" />";
			
		}
		
		
			
		

		$Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
		$CriteriaRow1 = "Filter CriteriaJoinOperator=\"$JoinOperator\" $CriteriaRow1 Filter<br>";
		
	} else {
		$CriteriaRow1 = "Filter CriteriaJoinOperator=\"&amp;\" <br>";		
		$Filter = "<Filter CriteriaJoinOperator=\"&amp;\" />";		

		//echo "CriteriaRow1 = $CriteriaRow1<br>";
	}
	
	//echo "JoinOperator = $JoinOperator<br>CriteriaRow1 = $CriteriaRow1<br>";
	
	
	
	return $Filter;

}


function GetTimeZone($TEST, $userTicket){
	$rows = array();
	$Count = "0";
	$TimeZoneRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		)
	);
	$TimeZoneResponse = callService("programservice/GetTimeZone", $TimeZoneRequest);
	$ErrorCode = $TimeZoneResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$TimeZoneList = $TimeZoneResponse->{"TimeZoneList"};		
	} else {
		$errorMessage = "TimeZoneResponse ERROR : <br> ErrorMessage -> ".$TimeZoneResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$TimeZoneResponse->{"Result"}->{"ExceptionMessage"};		
	}

	return $TimeZoneList;
}

function object2array($object) { 
	return @json_decode(@json_encode($object),1); 
} 

function GetUserinfo($TEST, $userTicket){
	$rows = array();
	$username = "";
	$GetUserRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		)		
	);
	$GetUserResponse = callService("userservice/GetUser", $GetUserRequest);
	$ErrorCode = $GetUserResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$FirstName = $GetUserResponse->{"FirstName"};		
		$LastName = $GetUserResponse->{"LastName"};	
		if ( strtolower($LastName) == 'unknown') {
			$LastName = '';
		}
		$username = "$FirstName $LastName";
		$username = trim($username);
	} else {
		$errorMessage = "GetUserResponse ERROR : <br> ErrorMessage -> ".$GetUserResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GetUserResponse->{"Result"}->{"ExceptionMessage"};		
	}

	return $username;
}

function GetUserinfoArray($TEST, $userTicket){
	$userInfo = array();
	$username = "";
	$FirstName = "";
	$LastName = "";
	$GetUserRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		)		
	);
	$GetUserResponse = callService("userservice/GetUser", $GetUserRequest);
	$ErrorCode = $GetUserResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$FirstName = $GetUserResponse->{"FirstName"};		
		$LastName = $GetUserResponse->{"LastName"};	
		if ( strtolower($LastName) == 'unknown') {
			$LastName = '';
		}
		$username = "$FirstName $LastName";
		$username = trim($username);
	} else {
		$errorMessage = "GetUserResponse ERROR : <br> ErrorMessage -> ".$GetUserResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GetUserResponse->{"Result"}->{"ExceptionMessage"};		
	}
	
	$userInfo['username'] = $username;
	$userInfo['FirstName'] = $FirstName;
	$userInfo['LastName'] = $LastName;

	return $userInfo;
}

function ForgotPassword($TEST, $Email){
	$rows = array();
	$username = "";
	$ForgotPasswordRequest   = array
	(

		"Email" => $Email
	);
	$ForgotPasswordResponse = callService("userservice/RequestForgotPassword", $ForgotPasswordRequest);
	$ErrorCode = $ForgotPasswordResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$message = "";
		$success = true;
	} else {
		$message = "ForgotPasswordResponse ERROR : <br> ErrorMessage -> ".$ForgotPasswordResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ForgotPasswordResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}

	return json_encode( array('success'=>$success, 'message' => $message, 'ErrorCode' => $ErrorCode ));
}

function execReport($TEST, $reportURL) {		
	
	//$ThisURL = "$reportURL&token=$token";
	
	$ch = curl_init($reportURL);
	if ($TEST == 't') {
		echo "<br>execReport reportURL : $reportURL<br>";
	}

	//echo "execReport : $reportURL<br>";

	//curl_setopt($ch, CURLOPT_MUTE, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	$rows = curl_exec($ch);

	curl_close($ch);	

	return $rows;

}

function runReport($TEST, $reportURL, $token) {		
	$moreInfo['ACCOUNTID'] = $_SESSION['ACCOUNTID'];
	$moreInfo['EMAIL'] = $_SESSION['EMAIL'];
	$moreInfo['PWD'] = $_SESSION['PWD'];
	return runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);

}

function runReportMoreInfo($TEST, $reportURL, $token, $moreInfo) {		
    error_log("-SESSION- runReportMoreInfo");
	$ThisURL = "$reportURL&token=$token";
	$preventTimeout = $moreInfo['preventTimeout'];
	if ($preventTimeout) { } else { $preventTimeout = ''; }
	if ($TEST == 't') {
		echo "<br>runReportMoreInfo : $ThisURL<br>";
	}
	if ($preventTimeout == 'preventTimeout') {
		$logger2 = new FileLogger("getReportCampaignStat.log");
		preventTimeout();
	}

	$rows = execReport($TEST, $ThisURL);	
	if ($TEST == 't') {
		echo "<br>rows : $rows<br>";
	}
	
	$jsonRow = json_decode($rows);	
	$message = $jsonRow->Message;
	if ($TEST == 't') {
		//echo "<br>1170 message : $message<br>";
	}
	if ($preventTimeout == 'preventTimeout') {
		$logger2->log("1179 runReportMoreInfo preventTimeout");		
		$logger2->log($message);
		$logger2->log("token : $token, ThisURL : $ThisURL");
		preventTimeout();
	}
	
	if ($message) {
		if (strpos($message, 'Your session has expired') !== false) {
			if ($TEST == 't') {
				echo "<br>strpos : true<br>";
			}
			unset($_SESSION['STUDIOTOKEN']);
			$ACCOUNTID = $moreInfo['ACCOUNTID'];
			$EMAIL = $moreInfo['EMAIL'];
			$PWD = $moreInfo['PWD'];
			$token = getToken($ACCOUNTID, $EMAIL, $PWD);			
			$ThisURL = "$reportURL&token=$token";
			if ($TEST == 't') {
				echo "<br>token : $token<br>";
				echo "<br>ThisURL : $ThisURL<br>";
				echo "<br>ACCOUNTID : $ACCOUNTID<br>";
				echo "<br>EMAIL : $EMAIL<br>";
				echo "<br>PWD : $PWD<br>";
			}
			if ($preventTimeout == 'preventTimeout') {
				$logger2->log("token : $token, ThisURL : $ThisURL, ACCOUNTID : $ACCOUNTID, EMAIL : $EMAIL , PWD : $PWD");
			}
			$rows = execReport($TEST, $ThisURL);
			
			$jsonRow = json_decode($rows);	
			$message = $jsonRow->Message;
			if ($preventTimeout == 'preventTimeout') {				
				$logger2->log("1199 runReportMoreInfo preventTimeout");
				$logger2->log($message);
				//$logger2->log($rows);
				preventTimeout();
			}
			if ($TEST == 't') {
				echo "<br>1194 message : $message<br>";
			}
		} else {
			if ($TEST == 't') {
				echo "<br>strpos : false<br>";
			}
		}
	}

	return $rows;

}

function GetCampaignReportPage($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $DVPROGRAMID){	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Submits = 0;
	
	$logger2 = new FileLogger("GetCampaignReportPage.log");
	$logger2->log(" Start : $TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $DVPROGRAMID ");

	if ($PROGRAMID) {
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$result = allprogram_allmspagesl_summary($TEST, $token, $PROGRAMID, $ACCOUNTID, $fd, $td, $moreInfo);
		$rows = $result ['rows'];
		$welcome = $result ['welcome'];
		$download = $result ['download'];
		$submit = $result ['submit'];
		$Submits = $result ['submit'];
		$Exit = $result ['Exit'];
		
		$logger2->log(" allprogram_allmspagesl_summary result - ".json_encode($result));
		
		$success = true;
		
	} else {
		$success = false;
		$rows = null;
		
		$logger2->log(" allprogram_allmspagesl_summary result - null");
	}
	
	if ($TEST == 't') {
		echo "welcome : $welcome<br>";
		echo "submit : $submit<br>";
		echo "download : $download<br>";
	}

	$Reach = $welcome;
	if ( ($submit!=0) && ($welcome!=0) ) {
		if ($welcome!=0) {
			//$download = 2; $welcome = 3;
			$conversion = ($submit * 100) / $welcome;
			$conversion = round($conversion, 2);
		} else {
			$conversion = '0.00';
		}
	}
	
	
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$sqltxt = "select * from DV_CampaignMeasures where acctID = ? and campaignID = ? limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($ACCOUNTID,$DVPROGRAMID));
	$affected_rows = $Stmt->rowCount();
	$ReachSave = 0;
	$SubmitsSave = 0;
	$conversionSave = 0;
	if ($affected_rows == 0) {
	} else {
		while ($row = $Stmt->fetch()) {
			$ReachSave = $row['CLReach'];
			$SubmitsSave = $row['CLSubmits'];
			$conversionSave = $row['CLConversion'];
		}
	}
	if ($ReachSave) { } else { $ReachSave = 0; }
	if ($SubmitsSave) { } else { $SubmitsSave = 0; }
	if ($conversionSave) { } else { $conversionSave = 0; }
	
	if ($Reach == 0) { $Reach = $ReachSave; }
	if ($Submits == 0) { $Submits = $SubmitsSave; }
	if ($conversion == 0) { $conversion = $conversionSave; }
	
	if ($Reach == '0') {
		$ClickedRate = "0";
	} else {
		$ClickedRate = 100 * ($Submits / $Reach);						
	}
	
	$campaignType = 'PromoteEbook';
	$sql = 'update DV_CampaignMeasures set lastUpdateDate=NOW(),campaignName=?,CLReach=?,CLSubmits=?,CLConversion=?,studioID=?,campaignName=?,campaignType=?,Reach=?,Click=?,ClickedRate=? where acctID=? and campaignID=?';
		try {
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($campaignName,$Reach,$Submits,$conversion,$PROGRAMID,$campaignName,$campaignType,$Reach,$Submits,$ClickedRate,$ACCOUNTID,$DVPROGRAMID));
		
		$logger2->log(" update DV_CampaignMeasures sql - $sql");
		$logger2->log(" $campaignName,$Reach,$Submits,$conversion,$PROGRAMID,$campaignName,$campaignType,$Reach,$Submits,$ClickedRate,$ACCOUNTID,$DVPROGRAMID");
		
		$saveSuccess = true;
		$saveErrorMessage = '';
	} catch(Exception $e){
		$saveSuccess = false;	
		$saveErrorMessage = $e->getMessage();
	}
	
	if ($TEST == 't') {
		echo "<br>$campaignName,$Reach,$Submits,$ACCOUNTID,$PROGRAMID<br>";
		echo "<br>1293 GetCampaignReportPage saveErrorMessage : $saveErrorMessage<br>";
	}
	
	$result = json_encode( array(
		'success'=>$success, 
		'rows' => $rows, 
		'Reach' => $Reach, 
		'conversion' => $conversion, 
		'download' => $download, 
		'welcome' => $welcome, 
		'submit' => $submit, 
		'Submits' => $Submits, 
		'reportURL' => $reportURL
	));
	$logger2->log(" Finish result - ".json_encode($result));
	
	return $result;

}

function GetCampaignReportPageBA($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $totalEmail, $EMAIL, $PWD, $DVPROGRAMID, $AllEmails){	
	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Sent = '0';
	$Exit = '0';
	$Complete = '0';
	$Unsubscribed = '0';
	$Delivered = '0';
	$Visitors = '0';
	$Submits = '0';
	$Clicks = '0';
	$Responses = '0';
	//$logger2 = new FileLogger("measuresCron.log");
	if ($PROGRAMID) {		
		if ($AllEmails) {
			
		} else {
			$AllEmails = GetReportAllEmail($TEST, $ACCOUNTID, $fd, $td, $token, $PROGRAMID, $EMAIL, $PWD);
		}
		$tempArrays = $AllEmails;
		
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$result = allprogram_allmspagesl_summary($TEST, $token, $PROGRAMID, $ACCOUNTID, $fd, $td, $moreInfo);
		$rows = $result ['rows'];
		$welcome = $result ['welcome'];
		$download = $result ['download'];
		$submit = $result ['submit'];
		$Exit = $result ['Exit'];

		
		foreach ($tempArrays as $tempArray){
			$tempProgram = $tempArray['Program'];		
			$tempProgramID = $tempArray['ProgramID'];		
			//if ($tempProgram == $campaignName) {
			if ($TEST == 't1') {
				echo "tempProgramID : $tempProgramID<br>";			
				echo "tempProgram : $tempProgram<br>";
			}
			if ($tempProgramID == $PROGRAMID) {
				$Sent = $tempArray['Sent'];
				if ($tempArray['Delivered']) {
					$Delivered = $tempArray['Delivered'];
				}
				
				if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder' ) { //#1185 #2176
					$Click = $tempArray['Clicked'];
				} else {
					$Click = $tempArray['Submits'];
				}
				$Open = $tempArray['Opened'];
				$Unsubscribed = $tempArray['Unsubscribed'];
				if ($Sent == '0') {
					$ClickedRate = "0";
					$UnsubscribedRate = "0";
				} else {
					$ClickedRate = 100 * ($Click / $Sent);
					$UnsubscribedRate = 100 * ($Unsubscribed / $Sent);						
				}
				break;
			}
		}
		
		$ReportEmails = json_decode(GetCampaignReportEmail($TEST, $ACCOUNTID, $PROGRAMID, '', $campaignName, $fd, $td, $token, '', $EMAIL, $PWD));
		$ReportEmailsRows = $ReportEmails->rows;
		//$numberOfEmailsSent = $ReportEmails->numberOfEmailsSent;
		$numberOfEmailsSent = $totalEmail;
		//$logger2->log("1426 totalEmail = $totalEmail ");
		$str = '';
		foreach ($ReportEmailsRows as $ReportEmailsRow) {
			$emailName = strtolower($ReportEmailsRow->Email);
			$str .= $emailName."<br>";
			//$logger2->log("1426 = $emailName ". $ReportEmailsRow->Delivered );
			if ($emailName == 'email'.$numberOfEmailsSent) {
				$Complete = $ReportEmailsRow->Delivered;
				break;
			}
		}
		$success = true;
		
	} else {
		$success = true;
		$rows = [];
	}
	

	if ($TEST == 't') {
		echo "welcome : $welcome<br>";
		echo "download : $download<br>";
		echo "Delivered : $Delivered<br>";
	}
	
	if ($EMAIL == '') { $EMAIL = $_SESSION['EMAIL']; }
	if ($PWD == '') { $PWD = $_SESSION['PWD']; }
	$BA_Array = getBA_Reach($TEST, $EMAIL, $PWD, $ACCOUNTID, $PROGRAMID, $fd, $td);
	$welcome = $BA_Array['purlCount'];

	$Reach = $welcome;
	if ( ($submit!=0) && ($welcome!=0) ) {
		
		if ($welcome!=0) {
			//$download = 2; $welcome = 3;
			$conversion = ($submit * 100) / $welcome;
			$conversion = round($conversion, 2);
		} else {
			$conversion = '0.00';
		}
	}
	
	if ($Reach == 0) {
		$DeliveredRate = 0;
	} else {
		$DeliveredRate = 100 * ($Delivered / $Reach);		
	}
	
	if ($Delivered == 0) {
		$CompleteRate = 0;
		$ExitRate = 0;
		$UnsubscribedRate = 0;
	} else {
		$CompleteRate = 100 * ($Complete / $Delivered);
		$ExitRate = 100 * ($Exit / $Delivered);		
		$UnsubscribedRate = 100 * ($Unsubscribed / $Delivered);		
	}
	
	if ($TEST == 't') {
		echo "Reach : $Reach<br>";
		echo "Complete : $Complete<br>";
		echo "Delivered : $Delivered<br>";
		echo "Exit : $Exit<br>";
		echo "CompleteRate : $CompleteRate<br>";
		echo "ExitRate : $ExitRate<br>";
		echo "UnsubscribedRate : $UnsubscribedRate<br>";
	}
	
	$sqltxt = "select * from DV_CampaignMeasures where acctID = ? and campaignID = ? limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($ACCOUNTID,$DVPROGRAMID));
	$affected_rows = $Stmt->rowCount();
	$ReachSave = 0;
	$DeliveredSave = 0;
	$ExitSave = 0;
	$CompleteSave = 0;
	$UnsubscribedSave = 0;
	if ($affected_rows == 0) {
	} else {
		while ($row = $Stmt->fetch()) {
			$ReachSave = $row['BAReach'];
			$DeliveredSave = $row['BADelivered'];
			$ExitSave = $row['BAExit'];
			$CompleteSave = $row['BAComplete'];
			$UnsubscribedSave = $row['BAUnsubscribed'];
		}
	}
	
	if ($ReachSave) { } else { $ReachSave = 0; }
	if ($DeliveredSave) { } else { $DeliveredSave = 0; }
	if ($ExitSave) { } else { $ExitSave = 0; }
	if ($CompleteSave) { } else { $CompleteSave = 0; }
	if ($UnsubscribedSave) { } else { $UnsubscribedSave = 0; }
	
	if ($Reach == 0) { $Reach = $ReachSave; }
	if ($Delivered == 0) { $Delivered = $DeliveredSave; }
	if ($Exit == 0) { $Exit = $ExitSave; }
	if ($Complete == 0) { $Complete = $CompleteSave; }
	if ($Unsubscribed == 0) { $Unsubscribed = $UnsubscribedSave; }
	
	if ($Reach == '0') {
		$ClickedRate = "0";
	} else {
		$ClickedRate = 100 * ($Complete / $Reach);					
	}
	
	$campaignType = 'BuildAwareness';
	$sql = 'update DV_CampaignMeasures set lastUpdateDate=NOW(),campaignName=?,BAReach=?,BADelivered=?,BAExit=?,BAComplete=?,BAUnsubscribed=?,studioID=?,campaignName=?,campaignType=?,Reach=?,Click=?,ClickedRate=? where acctID=? and campaignID=?';
		try {
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($campaignName,$Reach,$Delivered,$Exit,$Complete,$Unsubscribed,$PROGRAMID,$campaignName,$campaignType,$Reach,$Complete,$ClickedRate,$ACCOUNTID,$DVPROGRAMID));
		$saveSuccess = true;
		$saveErrorMessage = '';
	} catch(Exception $e){
		$saveSuccess = false;	
		$saveErrorMessage = $e->getMessage();
	}
	
	if ($TEST == 't') {
		echo "<br>$campaignName,$Reach,$Submits,$ACCOUNTID,$PROGRAMID<br>";
		echo "<br>1490 GetCampaignReportPageBA saveErrorMessage : $saveErrorMessage<br>";
	}
	

	$result = json_encode( array(
		'success'=>$success, 
		'rows' => $rows,
		'Sent' => $Sent, 
		'Delivered' => $Delivered, 
		'Complete' => $Complete, 
		'Exit' => $Exit, 
		'Reach' => $Reach, 
		'conversion' => $conversion, 
		'download' => $download, 
		'welcome' => $welcome, 
		'Submits' => $Submits, 
		'reportURL' => $reportURL,
		'UnsubscribedRate' => $UnsubscribedRate,
		'DeliveredRate' => $DeliveredRate,
		'ExitRate' => $ExitRate,
		'CompleteRate' => $CompleteRate,
		'Unsubscribed' => $Unsubscribed,
		'AllEmails' => $AllEmails,
		'ReportEmails' => $ReportEmails,
		'str' => $str,
		'totalEmail' => $totalEmail,
		
	));
	return $result;

}

function GetMinutesSinceLastEvent($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token){
	// Dave created this to support #284 & #311

	//$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=dv_last_activity_datetime&si=0&ai=$ACCOUNTID&st=0&fd=11/01/2000&td=11/16/2917&seed=false&datasourceType=redshift";	
	$td = date('m/d/Y');
	$fd = date('m/d/Y', strtotime('-7 days'));	
	
	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=dv_last_activity_datetime&si=0&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";
	$result['token'] = $token;
	$result['lastActivityURL'] = $reportURL;
	$Acts = runReport($TEST, $reportURL, $token);
	$Act = json_decode($Acts, true);
	$lastActivity = $Act['0']['LastActivityDateUTC'];

	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=sq_getdwinfo&si=0&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=sql";
	$result['sweepURL'] = $reportURL;
	
	
	$Sweeps = runReport($TEST, $reportURL, $token);
	$Sweep = json_decode($Sweeps, true);
	$lastSweep = $Sweep['0']['DwFinished']; 
	
	$result['LastSweep'] = $lastSweep;
	$result['LastActivity'] = $lastActivity;
	$result['ACCOUNTID'] = $ACCOUNTID;

	if ($lastActivity == '0') {
		$result['toastr'] = '';
	} else {
		 $result['lastActivity1'] = strtotime($lastActivity);
		 $result['lastActivity1day'] = strtotime('-24 hours');

		if(strtotime('-24 hours') < strtotime($lastActivity)){
		  $result['toastr'] = 'show';
		}else{
		  $result['toastr'] = '';
		}
		
	}
	
	return json_encode($result);
}

function GetCampaignReportEmail($TEST, $ACCOUNTID, $PROGRAMID, $progID, $campaignName, $fd, $td, $token, $mode, $EMAIL, $PWD){	
	$reportURL = '';
	$reportURL1 = '';
	$numberOfEmailsSent = 0;
	$totalEmail = 0;
	if ($PROGRAMID) {
		$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemail_detail&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
		//$logger2 = new FileLogger("measuresCron.log");
		//$logger2->log("1583 rows : $rows");
		if ($TEST == 't') {
			echo "rows<br>";
			echo gettype($rows);
			echo "<br>";
			var_dump($rows);
			echo "<br>";
		}
		$jsonRow = json_decode($rows);
		if ($TEST == 't') {
			echo "jsonRow<br>";
			echo gettype($jsonRow);
			echo "<br>";
			var_dump($jsonRow);
			echo "<br>";
		}
				
		$filter1Name = 'Schedule1';
		$filter2Name = 'Schedule2';
		$filter3Name = 'Schedule3';
		$filter4Name = 'Schedule4';
		$filter5Name = 'Schedule5';
		$filter6Name = 'Schedule6';
		if ($mode == 'showschedule') {
			$reportURL1 = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemailschedule_detail&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

			$moreInfo['ACCOUNTID'] = $ACCOUNTID;
			$moreInfo['EMAIL'] = $EMAIL;
			$moreInfo['PWD'] = $PWD;
			$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
			
			if ($TEST == 't') {
				echo "rows2<br>";
				echo gettype($rows);
				echo "<br>";
				var_dump($rows2);
				echo "<br>";
			}
			$jsonRow2 = json_decode($rows);
			if ($TEST == 't') {
				echo "jsonRow2 decode<br>";
				echo gettype($jsonRow2);
				echo "<br>";
				var_dump($jsonRow2);
				echo "<br>";
			}
			try {  
				if($db == null){
					$db = GetPDOMySQL();
				}
				
				$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_CampaignList WHERE acctID=? AND campaignID=? limit 1");
				$Stmt->execute(array($ACCOUNTID, $progID));
				$affected_rows = $Stmt->rowCount();
				while ($row = $Stmt->fetch()) {
					$campaignType = $row['campaignType'];
				}
				if ($campaignType == 'PromoteEbook') {
					$tablename = 'DV_Document_PromoteEbook';
					$emailCount = 10;
				} else if ($campaignType == 'PromoteBlog') {
					$tablename = 'DV_Document_PromoteBlog';
					$emailCount = 6;
				} else if ($campaignType == 'EventReminder') { //#1185
					$tablename = 'DV_Document_EventReminder';
					$emailCount = 6;	
				} else if ($campaignType == 'PQR') { 
					$tablename = 'DV_Document_PQR';
					$emailCount = 10;
				} else if ($campaignType == 'CL2') { 
					$tablename = 'DV_Document_CL2';
					$emailCount = 10;
				} else if ($campaignType == 'CL21') { 
					$tablename = 'DV_Document_CL21';
					$emailCount = 10;
				} else if ($campaignType == 'AT2') {   //#2459
					$tablename = 'DV_Document_AT2';
					$emailCount = 10;					
				} else if ($campaignType == 'AT21') {
					$tablename = 'DV_Document_AT21';
					$emailCount = 10;						
				} else if ($campaignType == 'CL30') { 
					$tablename = 'DV_Document_CL30';
					$emailCount = 10;	
				} else if ($campaignType == 'PromoteDT') { 
					$tablename = 'DV_Document_PromoteDT';
					$emailCount = 10;
				} else {	
					$tablename = 'DV_Document_BuildAwareness';
					$emailCount = 10;
				} 

				$Stmt = MySQL_Prepare($db,"SELECT * FROM $tablename WHERE acctID=? AND ID=? limit 1");
				$Stmt->execute(array($ACCOUNTID, $progID));
				$affected_rows = $Stmt->rowCount();
				$found = false;
				while ($row = $Stmt->fetch()) {
					$docData = json_decode($row['data'],true);
					$filter1Name = $docData['filter1Name'];
					$filter2Name = $docData['filter2Name'];
					$filter3Name = $docData['filter3Name'];
					$filter4Name = $docData['filter4Name'];
					$filter5Name = $docData['filter5Name'];
					$filter6Name = $docData['filter6Name'];
					$filter7Name = $docData['filter7Name'];
					$filter8Name = $docData['filter8Name'];
					$filter9Name = $docData['filter9Name'];
					$filter10Name = $docData['filter10Name'];
					$EMAIL1SUBJECT = $docData['EMAIL1-SUBJECT'];
					$EMAIL2SUBJECT = $docData['EMAIL2-SUBJECT'];
					$EMAIL3SUBJECT = $docData['EMAIL3-SUBJECT'];
					$EMAIL4SUBJECT = $docData['EMAIL4-SUBJECT'];
					$EMAIL5SUBJECT = $docData['EMAIL5-SUBJECT'];
					$EMAIL6SUBJECT = $docData['EMAIL6-SUBJECT'];
					$EMAIL7SUBJECT = $docData['EMAIL7-SUBJECT'];
					$EMAIL8SUBJECT = $docData['EMAIL8-SUBJECT'];
					$EMAIL9SUBJECT = $docData['EMAIL9-SUBJECT'];
					$EMAIL10SUBJECT = $docData['EMAIL10-SUBJECT'];
					$totalEmail = $docData['totalEmail'];
					if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder') { //#1185
						$tz = $docData['EMAIL1-SCHEDULE1-TIMEZONE'];
					} else {	
						$tz = '';
					}
				}				
			}  
			catch(exception $e) {
				$errorMessage = $e->getMessage();
				$affected_rows = -1;	
			}
		}

		$DVTimezones = getDVTimezone();
		foreach( $DVTimezones as $DVTimezone ) {	
			if ($tz == $DVTimezone['timezoneStudio']) {
				$tz = $DVTimezone['timezoneMoment'];
				break;
			}
		}

		$rows = array();

		foreach( $jsonRow as $row1 ) {			
			if ($TEST == 't') {
				var_dump($row1);
				echo "<br>";
			}
			$Program = $row1->{'Program'};
			$Email = $row1->{'Email'};
			$Sent = $row1->{'Sent'};
			$Delivered = $row1->{'Delivered'};
			$Opened = $row1->{'Opened'};
			$Clicked = $row1->{'Clicked'};
			$FailedToProcess = $row1->{'FailedToProcess'};
			$HardBounced = $row1->{'HardBounced'};
			$SoftBounced = $row1->{'SoftBounced'};
			$Suppressed = $row1->{'Suppressed'};
			$MarkedAsSpam = $row1->{'MarkedAsSpam'};
			$Unsubscribed = $row1->{'Unsubscribed'};			
			$ScheduleDate = $row1->{'Schedule Date'};			
			$ScheduleDate = convert_to_server_date($ScheduleDate, 'UTC', $tz, 'Y-m-d H:i:s');
			$mainGroup = 'T';		
			$EmailSchedule = '';
			$EmailScheduleSent = '';
			$EmailCheck1 = strtolower($Email);
			$OutboundScheduleID = '';		

			if ($TEST == 't') {
				echo "002 Sent : $Sent<br>";
			}
			for ($i = 1; $i <= $emailCount; $i++) {			
				$pos = strpos($EmailCheck1, "email$i");
				if ($TEST == 't') {
					echo "002-1 pos : $pos, $EmailCheck1, email$i<br>";
				}
				if ($pos === false) {

				} else {
					if ($i == 10) {
						$EmailSubject = $EMAIL10SUBJECT;
					} else if ($i == 9) {
						$EmailSubject = $EMAIL9SUBJECT;
					} else if ($i == 8) {
						$EmailSubject = $EMAIL8SUBJECT;
					} else if ($i == 7) {
						$EmailSubject = $EMAIL7SUBJECT;
					} else if ($i == 6) {
						$EmailSubject = $EMAIL6SUBJECT;
					} else if ($i == 5) {
						$EmailSubject = $EMAIL5SUBJECT;
					} else if ($i == 4) {
						$EmailSubject = $EMAIL4SUBJECT;
					} else if ($i == 3) {
						$EmailSubject = $EMAIL3SUBJECT;
					} else if ($i == 2) {
						$EmailSubject = $EMAIL2SUBJECT;
					} else { 
						$EmailSubject = $EMAIL1SUBJECT;
					}
					break;
				}
			}
			
			$emailName = "Email$i";
			$sqlDate = " insertTime >= STR_TO_DATE('$fd', '%m/%d/%Y') AND insertTime <= STR_TO_DATE('$td', '%m/%d/%Y') ";
			if ($TEST == 't') {
				echo "002-1 sqlDate : $sqlDate<br>";
			}
			$conversionEmail = getUniqueConversionEmail($ACCOUNTID, $PROGRAMID, $emailName, $sqlDate);
			
			$group = array(					
				'Program'=>$Program,
				'Email'=>$Email,
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'conversionEmail'=>$conversionEmail,
				'Unsubscribed'=>$Unsubscribed,
				'mainGroup'=>$mainGroup,
				'EmailSchedule'=>$EmailSchedule,
				'EmailSubject'=>$EmailSubject,
				'EmailScheduleSent'=>$EmailScheduleSent,
				'OutboundScheduleID'=>$OutboundScheduleID,
				'ScheduleDate'=>$ScheduleDate,
			);					
			$rows[] = $group;
			$numberOfEmailsSent++;

			if ($mode == 'showschedule') {
				if ($TEST == 't') {
					echo "<br>mode : $mode<br>";
				}
				foreach( $jsonRow2 as $row1 ) {	
					if ($TEST == 't') {
						echo "row1<br>";
						var_dump($row1);
						echo "<br>";
					}
					$Program = $row1->{'Program'};
					$OutboundScheduleID = $row1->{'OutboundSchedule_ID'};					
					$Email = $row1->{'Email'};
					if ($tablename == 'DV_Document_BuildAwareness') {
						$EmailSchedule = '';
					} else {
						$EmailSchedule = $row1->{'EmailSchedule'};
					}
					$Sent = $row1->{'Sent'};
					$Delivered = $row1->{'Delivered'};
					$Opened = $row1->{'Opened'};
					$Clicked = $row1->{'Clicked'};
					$FailedToProcess = $row1->{'FailedToProcess'};
					$HardBounced = $row1->{'HardBounced'};
					$SoftBounced = $row1->{'SoftBounced'};
					$Suppressed = $row1->{'Suppressed'};
					$MarkedAsSpam = $row1->{'MarkedAsSpam'};
					$Unsubscribed = $row1->{'Unsubscribed'};	
					$ScheduleDate = $row1->{'Schedule Date'};
					$ScheduleDate = convert_to_server_date($ScheduleDate, 'UTC', $tz, 'Y-m-d H:i:s');
					//$ScheduleDate = convert_to_server_date($ScheduleDate, 'Europe/London', $tz, 'Y-m-d H:i:s');					
					$mainGroup = 'F';	
					$EmailScheduleSent = '';
					$EmailCheck2 = strtolower($Email);			
					$EmailSubject = '';

					
					
					if ($EmailCheck1 == $EmailCheck2) {

						if ($TEST == 't') {
							echo "001 Sent : $Sent<br>";
							echo "001 filter1Name : $filter1Name<br>";
							echo "001 filter2Name : $filter2Name<br>";
							echo "001 filter3Name : $filter3Name<br>";
							echo "001 filter4Name : $filter4Name<br>";
							echo "001 filter5Name : $filter5Name<br>";
							echo "001 filter6Name : $filter6Name<br>";
							echo "001 EMAIL1SUBJECT : $EMAIL1SUBJECT<br>";
							echo "001 EMAIL2SUBJECT : $EMAIL2SUBJECT<br>";
							echo "001 EMAIL3SUBJECT : $EMAIL3SUBJECT<br>";
							echo "001 EmailSchedule : $EmailSchedule<br>";
						}
						$EmailScheduleUpper = strtoupper($EmailSchedule);
						for ($i = 1; $i <= $emailCount; $i++) {			
							$pos = strpos($EmailScheduleUpper, "SCHEDULE$i");
							if ($TEST == 't') {
								echo "003 pos : $pos, $EmailScheduleUpper, SCHEDULE$i<br>";
							}
							if ($pos === false) {

							} else {
								if ($i == 10) {
									$EmailSchedule = $filter10Name;
								} else if ($i == 9) {
									$EmailSchedule = $filter9Name;
								} else if ($i == 8) {
									$EmailSchedule = $filter8Name;
								} else if ($i == 7) {
									$EmailSchedule = $filter7Name;
								} else if ($i == 6) {
									$EmailSchedule = $filter6Name;
								} else if ($i == 5) {
									$EmailSchedule = $filter5Name;
								} else if ($i == 4) {
									$EmailSchedule = $filter4Name;
								} else if ($i == 3) {
									$EmailSchedule = $filter3Name;
								} else if ($i == 2) {
									$EmailSchedule = $filter2Name;
								} else { 
									$EmailSchedule = $filter1Name;
								}
								break;
							}
						}


						$group = array(					
							'Program'=>$Program,
							'Email'=>$Email,
							'Sent'=>$Sent,
							'Delivered'=>$Delivered,
							'Opened'=>$Opened,
							'Clicked'=>$Clicked,
							'FailedToProcess'=>$FailedToProcess,
							'HardBounced'=>$HardBounced,
							'SoftBounced'=>$SoftBounced,
							'Suppressed'=>$Suppressed,
							'MarkedAsSpam'=>$MarkedAsSpam,
							'Unsubscribed'=>$Unsubscribed,
							'mainGroup'=>$mainGroup,
							'conversionEmail'=>'0',
							'EmailSchedule'=>$EmailSchedule,
							'EmailSubject'=>$EmailSubject,
							'EmailScheduleSent'=>$EmailScheduleSent,
							'OutboundScheduleID'=>$OutboundScheduleID,
							'ScheduleDate'=>$ScheduleDate,
						);					
						$rows[] = $group;							
					}
				}		

			}
			
		}
		$success = true;
		if ($tablename == 'DV_Document_BuildAwareness') {
			for ($i = $numberOfEmailsSent+1; $i <= $totalEmail; $i++) {
				$Program = "";
				$Email = "Email #".$i;
				$Sent = "0";
				$Delivered = "0";
				$Opened = "0";
				$Clicked = "0";
				$FailedToProcess = "0";
				$HardBounced = "0";
				$SoftBounced = "0";
				$Suppressed = "0";
				$MarkedAsSpam = "0";
				$Unsubscribed = "0";
				$mainGroup = "T";
				$EmailSchedule = "";
				$EmailSubject = $docData['EMAIL'.$i.'-SUBJECT'];
				$EmailScheduleSent = "";
				$OutboundScheduleID = "";
				$ScheduleDate = "";
				$group = array(					
					'Program'=>$Program,
					'Email'=>$Email,
					'Sent'=>$Sent,
					'Delivered'=>$Delivered,
					'Opened'=>$Opened,
					'Clicked'=>$Clicked,
					'FailedToProcess'=>$FailedToProcess,
					'HardBounced'=>$HardBounced,
					'SoftBounced'=>$SoftBounced,
					'Suppressed'=>$Suppressed,
					'MarkedAsSpam'=>$MarkedAsSpam,
					'Unsubscribed'=>$Unsubscribed,
					'mainGroup'=>$mainGroup,
					'EmailSchedule'=>$EmailSchedule,
					'EmailSubject'=>$EmailSubject,
					'EmailScheduleSent'=>$EmailScheduleSent,
					'OutboundScheduleID'=>$OutboundScheduleID,
					'ScheduleDate'=>$ScheduleDate,
				);					
				$rows[] = $group;	
				
				$mainGroup = "F";
				$group = array(					
					'Program'=>$Program,
					'Email'=>$Email,
					'Sent'=>$Sent,
					'Delivered'=>$Delivered,
					'Opened'=>$Opened,
					'Clicked'=>$Clicked,
					'FailedToProcess'=>$FailedToProcess,
					'HardBounced'=>$HardBounced,
					'SoftBounced'=>$SoftBounced,
					'Suppressed'=>$Suppressed,
					'MarkedAsSpam'=>$MarkedAsSpam,
					'Unsubscribed'=>$Unsubscribed,
					'mainGroup'=>$mainGroup,
					'EmailSchedule'=>$EmailSchedule,
					'EmailSubject'=>$EmailSubject,
					'EmailScheduleSent'=>$EmailScheduleSent,
					'OutboundScheduleID'=>$OutboundScheduleID,
					'ScheduleDate'=>$ScheduleDate,
				);					
				$rows[] = $group;	
				
				
			}
			$numberOfEmailsSent = $totalEmail;
		}
		
	} else {
		$success = false;
		$rows = null;
	}	
	
	

	if ($numberOfEmailsSent > 1) {
		$numberOfEmailsUnit = "emails";
	} else {
		$numberOfEmailsUnit = "email";
	}
	$result = json_encode( 
		array(
			'success'=>$success, 
			'rows' => $rows, 
			'reportURL' => $reportURL, 
			'reportURL1' => $reportURL1, 
			'token' => $token,
			'numberOfEmailsSent' => $numberOfEmailsSent,
			'numberOfEmailsUnit' => $numberOfEmailsUnit
			)
		);
		
	//$logger2->log("2000 resultName = ". $result );
	return $result;

}

function GetCampaignReportEmailDW($TEST, $ACCOUNTID, $PROGRAMID, $progID, $campaignName, $fd, $td, $token, $mode){	
	$reportURL = '';
	$reportURL1 = '';
	$numberOfEmailsSent = 0;
	$totalEmail = 0;
	if ($PROGRAMID) {
		$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemail_detail&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

		$rows = runReport($TEST, $reportURL, $token);
		if ($TEST == 't') {
			//echo "rows<br>";
			//echo gettype($rows);
			//echo "<br>";
			//var_dump($rows);
			//echo "<br>";
		}
		$jsonRow = json_decode($rows);
		if ($TEST == 't') {
			//echo "jsonRow<br>";
			//echo gettype($jsonRow);
			//echo "<br>";
			//var_dump($jsonRow);
			//echo "<br>";
		}
				
		$filter1Name = 'Schedule1';
		$filter2Name = 'Schedule2';
		$filter3Name = 'Schedule3';
		$filter4Name = 'Schedule4';
		$filter5Name = 'Schedule5';
		$filter6Name = 'Schedule6';
		if ($mode == 'showschedule') {
			$reportURL1 = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemailschedule_detail&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

			$rows = runReport($TEST, $reportURL1, $token);
			if ($TEST == 't') {
				//echo "rows2<br>";
				//echo gettype($rows);
				//echo "<br>";
				//var_dump($rows2);
				//echo "<br>";
			}
			$jsonRow2 = json_decode($rows);
			if ($TEST == 't') {
				//echo "jsonRow2 decode<br>";
				//echo gettype($jsonRow2);
				//echo "<br>";
				//var_dump($jsonRow2);
				//echo "<br>";
			}
			try {  
				if($db == null){
					$db = GetPDOMySQL();
				}
				
				$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_CampaignList WHERE acctID=? AND campaignID=? limit 1");
				$Stmt->execute(array($ACCOUNTID, $progID));
				$affected_rows = $Stmt->rowCount();
				while ($row = $Stmt->fetch()) {
					$campaignType = $row['campaignType'];
				}
				if ($campaignType == 'PromoteEbook') {
					$tablename = 'DV_Document_PromoteEbook';
					$emailCount = 10;
				} else if ($campaignType == 'PromoteBlog') {
					$tablename = 'DV_Document_PromoteBlog';
					$emailCount = 6;
				} else if ($campaignType == 'EventReminder') { //#1185
					$tablename = 'DV_Document_EventReminder';
					$emailCount = 6;	
				} else if ($campaignType == 'PQR') {  
					$tablename = 'DV_Document_PQR';
					$emailCount = 10;		
				} else if ($campaignType == 'CL2') {  
					$tablename = 'DV_Document_CL2';
					$emailCount = 10;
				} else if ($campaignType == 'CL21') {  
					$tablename = 'DV_Document_CL21';
					$emailCount = 10;		
				} else if ($campaignType == 'AT2') {  
					$tablename = 'DV_Document_AT2';
					$emailCount = 10;							
				} else if ($campaignType == 'AT21') {  
					$tablename = 'DV_Document_AT21';
					$emailCount = 10;							
				} else if ($campaignType == 'CL30') {  
					$tablename = 'DV_Document_CL30';
					$emailCount = 10;			
				} else if ($campaignType == 'PromoteDT') {  
					$tablename = 'DV_Document_PromoteDT';
					$emailCount = 6;	
				} else {	
					$tablename = 'DV_Document_BuildAwareness';
					$emailCount = 10;
				} 

				$Stmt = MySQL_Prepare($db,"SELECT * FROM $tablename WHERE acctID=? AND ID=? limit 1");
				$Stmt->execute(array($ACCOUNTID, $progID));
				$affected_rows = $Stmt->rowCount();
				$found = false;
				while ($row = $Stmt->fetch()) {
					$docData = json_decode($row['data'],true);
					$filter1Name = $docData['filter1Name'];
					$filter2Name = $docData['filter2Name'];
					$filter3Name = $docData['filter3Name'];
					$filter4Name = $docData['filter4Name'];
					$filter5Name = $docData['filter5Name'];
					$filter6Name = $docData['filter6Name'];
					$filter7Name = $docData['filter7Name'];
					$filter8Name = $docData['filter8Name'];
					$filter9Name = $docData['filter9Name'];
					$filter10Name = $docData['filter10Name'];
					$EMAIL1SUBJECT = $docData['EMAIL1-SUBJECT'];
					$EMAIL2SUBJECT = $docData['EMAIL2-SUBJECT'];
					$EMAIL3SUBJECT = $docData['EMAIL3-SUBJECT'];
					$EMAIL4SUBJECT = $docData['EMAIL4-SUBJECT'];
					$EMAIL5SUBJECT = $docData['EMAIL5-SUBJECT'];
					$EMAIL6SUBJECT = $docData['EMAIL6-SUBJECT'];
					$EMAIL7SUBJECT = $docData['EMAIL7-SUBJECT'];
					$EMAIL8SUBJECT = $docData['EMAIL8-SUBJECT'];
					$EMAIL9SUBJECT = $docData['EMAIL9-SUBJECT'];
					$EMAIL10SUBJECT = $docData['EMAIL10-SUBJECT'];
					$totalEmail = $docData['totalEmail'];
					if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder') {
						$tz = $docData['EMAIL1-SCHEDULE1-TIMEZONE'];
					} else {	
						$tz = '';
					}
				}				
			}  
			catch(exception $e) {
				$errorMessage = $e->getMessage();
				$affected_rows = -1;	
			}
		}

		$DVTimezones = getDVTimezone();
		foreach( $DVTimezones as $DVTimezone ) {	
			if ($tz == $DVTimezone['timezoneStudio']) {
				$tz = $DVTimezone['timezoneMoment'];
				break;
			}
		}

		$rows = array();

		foreach( $jsonRow as $row1 ) {			
			if ($TEST == 't') {
				var_dump($row1);
				echo "<br>";
			}
			$Program = $row1->{'Program'};
			$Email = $row1->{'Email'};
			$Sent = $row1->{'Sent'};
			$Delivered = $row1->{'Delivered'};
			$Opened = $row1->{'Opened'};
			$Clicked = $row1->{'Clicked'};
			$FailedToProcess = $row1->{'FailedToProcess'};
			$HardBounced = $row1->{'HardBounced'};
			$SoftBounced = $row1->{'SoftBounced'};
			$Suppressed = $row1->{'Suppressed'};
			$MarkedAsSpam = $row1->{'MarkedAsSpam'};
			$Unsubscribed = $row1->{'Unsubscribed'};			
			$ScheduleDate = $row1->{'Schedule Date'};			
			$ScheduleDate = convert_to_server_date($ScheduleDate, 'UTC', $tz, 'Y-m-d H:i:s');
			$mainGroup = 'T';		
			$EmailSchedule = '';
			$EmailScheduleSent = '';
			$EmailCheck1 = strtolower($Email);
			$OutboundScheduleID = '';		

			if ($TEST == 't') {
				echo "002 Sent : $Sent<br>";
				echo "002 mainGroup : $mainGroup<br>";
			}
			for ($i = 1; $i <= $emailCount; $i++) {			
				$pos = strpos($EmailCheck1, "email$i");
				if ($TEST == 't') {
					echo "002-1 pos : $pos, $EmailCheck1, email$i<br>";
				}
				if ($pos === false) {

				} else {
					if ($i == 10) {
						$EmailSubject = $EMAIL10SUBJECT;
					} else if ($i == 9) {
						$EmailSubject = $EMAIL9SUBJECT;
					} else if ($i == 8) {
						$EmailSubject = $EMAIL8SUBJECT;
					} else if ($i == 7) {
						$EmailSubject = $EMAIL7SUBJECT;
					} else if ($i == 6) {
						$EmailSubject = $EMAIL6SUBJECT;
					} else if ($i == 5) {
						$EmailSubject = $EMAIL5SUBJECT;
					} else if ($i == 4) {
						$EmailSubject = $EMAIL4SUBJECT;
					} else if ($i == 3) {
						$EmailSubject = $EMAIL3SUBJECT;
					} else if ($i == 2) {
						$EmailSubject = $EMAIL2SUBJECT;
					} else { 
						$EmailSubject = $EMAIL1SUBJECT;
					}
					break;
				}
			}
			
			$emailName = "Email$i";
			$sqlDate = " insertTime >= STR_TO_DATE('$fd', '%m/%d/%Y') AND insertTime <= STR_TO_DATE('$td', '%m/%d/%Y') ";
			if ($TEST == 't') {
				echo "002-1 sqlDate : $sqlDate<br>";
			}
			$conversionEmail = getUniqueConversionEmail($ACCOUNTID, $PROGRAMID, $emailName, $sqlDate);
			
			$group = array(					
				'Program'=>$Program,
				'Email'=>$Email,
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'conversionEmail'=>$conversionEmail,
				'Unsubscribed'=>$Unsubscribed,
				'mainGroup'=>$mainGroup,
				'EmailSchedule'=>$EmailSchedule,
				'EmailSubject'=>$EmailSubject,
				'EmailScheduleSent'=>$EmailScheduleSent,
				'OutboundScheduleID'=>$OutboundScheduleID,
				'ScheduleDate'=>$ScheduleDate,
			);					
			$rows[] = $group;
			$numberOfEmailsSent++;

			if ($mode == 'showschedule') {
				if ($TEST == 't') {
					echo "<br>mode : $mode<br>";
				}
				foreach( $jsonRow2 as $row1 ) {	
					if ($TEST == 't') {
						echo "row1<br>";
						var_dump($row1);
						echo "<br>";
					}
					$Program = $row1->{'Program'};
					$OutboundScheduleID = $row1->{'OutboundSchedule_ID'};					
					$Email = $row1->{'Email'};
					if ($tablename == 'DV_Document_BuildAwareness') {
						$EmailSchedule = '';
					} else {
						$EmailSchedule = $row1->{'EmailSchedule'};
					}
					$Sent = $row1->{'Sent'};
					$Delivered = $row1->{'Delivered'};
					$Opened = $row1->{'Opened'};
					$Clicked = $row1->{'Clicked'};
					$FailedToProcess = $row1->{'FailedToProcess'};
					$HardBounced = $row1->{'HardBounced'};
					$SoftBounced = $row1->{'SoftBounced'};
					$Suppressed = $row1->{'Suppressed'};
					$MarkedAsSpam = $row1->{'MarkedAsSpam'};
					$Unsubscribed = $row1->{'Unsubscribed'};	
					$ScheduleDate = $row1->{'Schedule Date'};
					$ScheduleDate = convert_to_server_date($ScheduleDate, 'UTC', $tz, 'Y-m-d H:i:s');
					//$ScheduleDate = convert_to_server_date($ScheduleDate, 'Europe/London', $tz, 'Y-m-d H:i:s');					
					$mainGroup = 'F';	
					$EmailScheduleSent = '';
					$EmailCheck2 = strtolower($Email);			
					$EmailSubject = '';

					
					
					if ($EmailCheck1 == $EmailCheck2) {

						if ($TEST == 't') {
							echo "001 Sent : $Sent<br>";
							echo "001 filter1Name : $filter1Name<br>";
							echo "001 filter2Name : $filter2Name<br>";
							echo "001 filter3Name : $filter3Name<br>";
							echo "001 filter4Name : $filter4Name<br>";
							echo "001 filter5Name : $filter5Name<br>";
							echo "001 filter6Name : $filter6Name<br>";
							echo "001 EMAIL1SUBJECT : $EMAIL1SUBJECT<br>";
							echo "001 EMAIL2SUBJECT : $EMAIL2SUBJECT<br>";
							echo "001 EMAIL3SUBJECT : $EMAIL3SUBJECT<br>";
							echo "001 EmailSchedule : $EmailSchedule<br>";
						}
						$EmailScheduleUpper = strtoupper($EmailSchedule);
						for ($i = 1; $i <= $emailCount; $i++) {			
							$pos = strpos($EmailScheduleUpper, "SCHEDULE$i");
							if ($TEST == 't') {
								echo "003 pos : $pos, $EmailScheduleUpper, SCHEDULE$i<br>";
							}
							if ($pos === false) {

							} else {
								if ($i == 10) {
									$EmailSchedule = $filter10Name;
								} else if ($i == 9) {
									$EmailSchedule = $filter9Name;
								} else if ($i == 8) {
									$EmailSchedule = $filter8Name;
								} else if ($i == 7) {
									$EmailSchedule = $filter7Name;
								} else if ($i == 6) {
									$EmailSchedule = $filter6Name;
								} else if ($i == 5) {
									$EmailSchedule = $filter5Name;
								} else if ($i == 4) {
									$EmailSchedule = $filter4Name;
								} else if ($i == 3) {
									$EmailSchedule = $filter3Name;
								} else if ($i == 2) {
									$EmailSchedule = $filter2Name;
								} else { 
									$EmailSchedule = $filter1Name;
								}
								break;
							}
						}


						$group = array(					
							'Program'=>$Program,
							'Email'=>$Email,
							'Sent'=>$Sent,
							'Delivered'=>$Delivered,
							'Opened'=>$Opened,
							'Clicked'=>$Clicked,
							'FailedToProcess'=>$FailedToProcess,
							'HardBounced'=>$HardBounced,
							'SoftBounced'=>$SoftBounced,
							'Suppressed'=>$Suppressed,
							'MarkedAsSpam'=>$MarkedAsSpam,
							'Unsubscribed'=>$Unsubscribed,
							'mainGroup'=>$mainGroup,
							'conversionEmail'=>'0',
							'EmailSchedule'=>$EmailSchedule,
							'EmailSubject'=>$EmailSubject,
							'EmailScheduleSent'=>$EmailScheduleSent,
							'OutboundScheduleID'=>$OutboundScheduleID,
							'ScheduleDate'=>$ScheduleDate,
						);					
						$rows[] = $group;							
					}
				}		

			}
			
		}
		$success = true;
		if ($tablename == 'DV_Document_BuildAwareness') {
			for ($i = $numberOfEmailsSent+1; $i <= $totalEmail; $i++) {
				$Program = "";
				$Email = "Email #".$i;
				$Sent = "0";
				$Delivered = "0";
				$Opened = "0";
				$Clicked = "0";
				$FailedToProcess = "0";
				$HardBounced = "0";
				$SoftBounced = "0";
				$Suppressed = "0";
				$MarkedAsSpam = "0";
				$Unsubscribed = "0";
				$mainGroup = "T";
				$EmailSchedule = "";
				$EmailSubject = $docData['EMAIL'.$i.'-SUBJECT'];
				$EmailScheduleSent = "";
				$OutboundScheduleID = "";
				$ScheduleDate = "";
				$group = array(					
					'Program'=>$Program,
					'Email'=>$Email,
					'Sent'=>$Sent,
					'Delivered'=>$Delivered,
					'Opened'=>$Opened,
					'Clicked'=>$Clicked,
					'FailedToProcess'=>$FailedToProcess,
					'HardBounced'=>$HardBounced,
					'SoftBounced'=>$SoftBounced,
					'Suppressed'=>$Suppressed,
					'MarkedAsSpam'=>$MarkedAsSpam,
					'Unsubscribed'=>$Unsubscribed,
					'mainGroup'=>$mainGroup,
					'EmailSchedule'=>$EmailSchedule,
					'EmailSubject'=>$EmailSubject,
					'EmailScheduleSent'=>$EmailScheduleSent,
					'OutboundScheduleID'=>$OutboundScheduleID,
					'ScheduleDate'=>$ScheduleDate,
				);					
				$rows[] = $group;	
				
				$mainGroup = "F";
				$group = array(					
					'Program'=>$Program,
					'Email'=>$Email,
					'Sent'=>$Sent,
					'Delivered'=>$Delivered,
					'Opened'=>$Opened,
					'Clicked'=>$Clicked,
					'FailedToProcess'=>$FailedToProcess,
					'HardBounced'=>$HardBounced,
					'SoftBounced'=>$SoftBounced,
					'Suppressed'=>$Suppressed,
					'MarkedAsSpam'=>$MarkedAsSpam,
					'Unsubscribed'=>$Unsubscribed,
					'mainGroup'=>$mainGroup,
					'EmailSchedule'=>$EmailSchedule,
					'EmailSubject'=>$EmailSubject,
					'EmailScheduleSent'=>$EmailScheduleSent,
					'OutboundScheduleID'=>$OutboundScheduleID,
					'ScheduleDate'=>$ScheduleDate,
				);					
				$rows[] = $group;	
				
				
			}
			$numberOfEmailsSent = $totalEmail;
		}
		
	} else {
		$success = false;
		$rows = null;
	}	
	
	

	if ($numberOfEmailsSent > 1) {
		$numberOfEmailsUnit = "emails";
	} else {
		$numberOfEmailsUnit = "email";
	}
	$result = json_encode( 
		array(
			'success'=>$success, 
			'rows' => $rows, 
			'reportURL' => $reportURL, 
			'reportURL1' => $reportURL1, 
			'token' => $token,
			'numberOfEmailsSent' => $numberOfEmailsSent,
			'numberOfEmailsUnit' => $numberOfEmailsUnit
			)
		);
	return $result;

}

function GetFoodOrderPage($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $reportMode){	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Submits = 0;
	

	
	
	$usersTimezone = getUserTimeZone($ACCOUNTID);
	
	if ($reportMode) { } else { $reportMode = 'Lifetime'; }
	
	if ($reportMode != '') {
	
		$serverFoodOrder = getServerFoodOrder($reportMode, $usersTimezone);
		$fd = $serverFoodOrder['fd'];
		$td = $serverFoodOrder['td'];
	}

	if ($fd) { 
		$fd = "$fd 00:00:00";
		$fdLA = convert_to_server_date($fd, $usersTimezone, 'America/Los_Angeles', 'Y-m-d H:i:s');
		$fdUTC = convert_to_server_date($fd, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	} else { 
		$fd = '';
	}
	if ($td) { 
		$td = "$td 23:59:59";
		$tdLA = convert_to_server_date($td, $usersTimezone, 'America/Los_Angeles', 'Y-m-d H:i:s');
		$tdUTC = convert_to_server_date($td, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	} else { 
		$td = '';
	}
	

	if ($PROGRAMID) {	
		
		$sql2 = "SELECT count(*) cnt FROM DV_Conversion WHERE (accountID=? and programID=? and visitpage LIKE '%welcome.html%') ";
		if ($fd <> '') {
			$sql2 .= " AND insertTime >= '$fdUTC' ";
		}
		if ($td <> '') {
			$sql2 .= " AND insertTime <= '$tdUTC' ";
		}
		$Stmt = MySQL_Prepare($db,$sql2);
		$Stmt->execute(array($ACCOUNTID, $PROGRAMID));
		$affected_rows = $Stmt->rowCount();
		$rows = array();
		while ($row = $Stmt->fetch()) {
			$welcome = $row['cnt'];
		}

		$sql2 = "SELECT COUNT(*) cnt FROM DV_orderDetail WHERE acctID=? AND programID=? ";
		if ($fd <> '') {
			$sql2 .= " AND createDate >= '$fdUTC' ";
		}
		if ($td <> '') {
			$sql2 .= " AND createDate <= '$tdUTC' ";
		}
		$Stmt = MySQL_Prepare($db,$sql2);
		$Stmt->execute(array($ACCOUNTID, $PROGRAMID));
		$affected_rows = $Stmt->rowCount();
		$rows = array();
		while ($row = $Stmt->fetch()) {
			$Submits = $row['cnt'];
		}
		$success = true;
		
		$group = array(					
			'Submits'=>$Submits
		);					
		$rows[] = $group;
		
	} else {
		$success = false;
		$rows = null;
	}
	
	if ($TEST == 't') {
		echo "welcome : $welcome<br>";
		echo "download : $download<br>";
	}

	$Reach = $welcome;
	$submit = $Submits;
	if ( ($submit!=0) && ($welcome!=0) ) {
		if ($welcome!=0) {
			//$download = 2; $welcome = 3;
			$conversion = ($submit * 100) / $welcome;
			$conversion = round($conversion, 2);
		} else {
			$conversion = '0.00';
		}
	}
	
	$result = json_encode( array('success'=>$success, 'rows' => $rows, 'Reach' => $Reach, 'conversion' => $conversion, 'download' => $download, 'welcome' => $welcome, 'Submits' => $Submits, 'reportURL' => $reportURL));
	return $result;

}

function GetCampaignReportPageDW($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token){	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Submits = 0;
	

	if ($PROGRAMID) {		
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $_SESSION['EMAIL'];
		$moreInfo['PWD'] = $_SESSION['PWD'];
		$result = allprogram_allmspagesl_summary($TEST, $token, $PROGRAMID, $ACCOUNTID, $fd, $td, $moreInfo);
		
		$rows = $result ['rows'];
		$welcome = $result ['welcome'];
		$download = $result ['download'];
		$submit = $result ['submit'];
		$Exit = $result ['Exit'];

		
		$success = true;
		
	} else {
		$success = false;
		$rows = null;
	}
	
	if ($TEST == 't') {
		echo "welcome : $welcome<br>";
		echo "download : $download<br>";
	}

	$Reach = $welcome;
	$Submits = $submit;
	if ( ($submit!=0) && ($welcome!=0) ) {
		if ($welcome!=0) {
			//$download = 2; $welcome = 3;
			$conversion = ($submit * 100) / $welcome;
			$conversion = round($conversion, 2);
		} else {
			$conversion = '0.00';
		}
	}
	
	$result = json_encode( array('success'=>$success, 'rows' => $rows, 'Reach' => $Reach, 'conversion' => $conversion, 'download' => $download, 'welcome' => $welcome, 'Submits' => $Submits, 'reportURL' => $reportURL));
	return $result;

}

function GetCampaignReportPageBADW($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $totalEmail, $EMAIL, $PWD){	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Sent = '0';
	$Exit = '0';
	$Complete = '0';
	$Unsubscribed = '0';
	$Delivered = '0';

	if ($PROGRAMID) {		
		
		$AllEmails = GetReportAllEmail($TEST, $ACCOUNTID, $fd, $td, $token, $PROGRAMID, $EMAIL, $PWD);
		$tempArrays = $AllEmails;
		if ($TEST == 't') {
			echo "2762 tempArrays <br>";
			var_dump($tempArrays);
			echo "<br>";
		}
		
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$result = allprogram_allmspagesl_summary($TEST, $token, $PROGRAMID, $ACCOUNTID, $fd, $td, $moreInfo);
		$rows = $result ['rows'];
		$welcome = $result ['welcome'];
		$download = $result ['download'];
		$submit = $result ['submit'];
		$Exit = $result ['Exit'];

		
		foreach ($tempArrays as $tempArray){
			$tempProgram = $tempArray['Program'];		
			$tempProgramID = $tempArray['ProgramID'];		
			//if ($tempProgram == $campaignName) {
			if ($TEST == 't1') {
				echo "tempProgramID : $tempProgramID<br>";			
				echo "tempProgram : $tempProgram<br>";
			}
			if ($tempProgramID == $PROGRAMID) {
				$Sent = $tempArray['Sent'];
				if ($tempArray['Delivered']) {
					$Delivered = $tempArray['Delivered'];
				}
				
				if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder') {
					$Click = $tempArray['Clicked'];
				} else {
					$Click = $tempArray['Submits'];
				}
				$Open = $tempArray['Opened'];
				$Unsubscribed = $tempArray['Unsubscribed'];
				if ($Sent == '0') {
					$ClickedRate = "0";
					$UnsubscribedRate = "0";
				} else {
					$ClickedRate = 100 * ($Click / $Sent);
					$UnsubscribedRate = 100 * ($Unsubscribed / $Sent);						
				}
				break;
			}
		}
		
		$ReportEmails = json_decode(GetCampaignReportEmailDW($TEST, $ACCOUNTID, $PROGRAMID, '', $campaignName, $fd, $td, $token, ''));
		if ($TEST == 't') {
			echo "2853 ReportEmails <br>";
			var_dump($ReportEmails);
			echo "<br>";
		}
		$ReportEmailsRows = $ReportEmails->rows;
		//$numberOfEmailsSent = $ReportEmails->numberOfEmailsSent;
		$numberOfEmailsSent = $totalEmail;
		$str = '';
		foreach ($ReportEmailsRows as $ReportEmailsRow) {
			$emailName = strtolower($ReportEmailsRow->Email);
			$str .= $emailName."<br>";
			if ($emailName == 'email'.$numberOfEmailsSent) {
				$Complete = $ReportEmailsRow->Delivered;
				break;
			}
		}
		$success = true;
		
	} else {
		$success = true;
		$rows = [];
	}
	

	if ($TEST == 't') {
		echo "welcome : $welcome<br>";
		echo "download : $download<br>";
		echo "Delivered : $Delivered<br>";
	}

	$Reach = $welcome;
	if ( ($submit!=0) && ($welcome!=0) ) {
		
		if ($welcome!=0) {
			//$download = 2; $welcome = 3;
			$conversion = ($submit * 100) / $welcome;
			$conversion = round($conversion, 2);
		} else {
			$conversion = '0.00';
		}
	}
	
	if ($Sent == 0) {
		$DeliveredRate = 0;
	} else {
		$DeliveredRate = 100 * ($Delivered / $Sent);		
	}
	
	if ($Delivered == 0) {
		$CompleteRate = 0;
		$ExitRate = 0;
		$UnsubscribedRate = 0;
	} else {
		$CompleteRate = 100 * ($Complete / $Delivered);
		$ExitRate = 100 * ($Exit / $Delivered);		
		$UnsubscribedRate = 100 * ($Unsubscribed / $Delivered);		
	}
	
	if ($TEST == 't') {
		echo "Reach : $Reach<br>";
		echo "Complete : $Complete<br>";
		echo "Delivered : $Delivered<br>";
		echo "Exit : $Exit<br>";
		echo "CompleteRate : $CompleteRate<br>";
		echo "ExitRate : $ExitRate<br>";
		echo "UnsubscribedRate : $UnsubscribedRate<br>";
	}
	
	$result = json_encode( array(
		'success'=>$success, 
		'rows' => $rows,
		'Sent' => $Sent, 
		'Delivered' => $Delivered, 
		'Complete' => $Complete, 
		'Exit' => $Exit, 
		'Reach' => $Sent, 
		'conversion' => $conversion, 
		'download' => $download, 
		'welcome' => $welcome, 
		'Submits' => $Submits, 
		'reportURL' => $reportURL,
		'UnsubscribedRate' => $UnsubscribedRate,
		'DeliveredRate' => $DeliveredRate,
		'ExitRate' => $ExitRate,
		'CompleteRate' => $CompleteRate,
		'Unsubscribed' => $Unsubscribed,
		'AllEmails' => $AllEmails,
		'ReportEmails' => $ReportEmails,
		'str' => $str,
		'totalEmail' => $totalEmail,
		
	));
	return $result;
}



function GetCampaignReportEmailSummary($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $DVPROGRAMID, $moreParam){
	
	$logger2 = new FileLogger("sf-error.log"); 
	$logger2->log("[GetCampaignReportEmailSummary] moreParam = ". json_encode($moreParam));	
	
	if ($PROGRAMID) {
		$Targeted = 0;
		$Recipiants = 0;
		$reportSent = GetCampaignReportSent($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $DVPROGRAMID);
		$CampaignMeasure = json_decode($reportSent);
        if ($CampaignMeasure) {
        	$Targeted = $CampaignMeasure->rows[0]->Targeted;
    		$Recipiants = $CampaignMeasure->rows[0]->Recipiants;
        }
		
		$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemail_summary&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";
		
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$reportRows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
		$jsonRow = json_decode($reportRows);
		$rows = array();
		
		$conversionResult = getConversionReport($TEST, $ACCOUNTID, $PROGRAMID, $DVPROGRAMID);
		$conversion = $conversionResult['affected_rows2'];	
		foreach( $jsonRow as $row1 ) {
			if ($TEST == 't') {
				echo "row1<br>";
				var_dump($row1);
				echo "<br>";
			}
			$Email = $row1->{'Email'};
			//$Sent = $row1->{'Sent'};
			if ($Targeted) {
				$Sent = $Targeted;
			} else {
				$Sent = $row1->{'Sent'};
			}
			$Delivered = $row1->{'Delivered'};
			$Opened = $row1->{'Opened'};
			$Clicked = $row1->{'Clicked'};
			$FailedToProcess = $row1->{'FailedToProcess'};
			$HardBounced = $row1->{'HardBounced'};
			$SoftBounced = $row1->{'SoftBounced'};
			$Suppressed = $row1->{'Suppressed'};
			$MarkedAsSpam = $row1->{'MarkedAsSpam'};
			$Unsubscribed = $row1->{'Unsubscribed'};
			
			if($db == null){
				$db = GetPDOMySQL();
			}
			$sqltxt = "select * from DV_CampaignMeasures where acctID = ? and campaignID = ? limit 1";
			$Stmt = MySQL_Prepare($db,$sqltxt);		
			$Stmt->execute(array($ACCOUNTID,$DVPROGRAMID));
			$affected_rows = $Stmt->rowCount();
			$SentSave = 0;
			$DeliveredSave = 0;
			$OpenedSave = 0;
			$ClickedSave = 0;
			$FailedToProcessSave = 0;
			$HardBouncedSave = 0;
			$SoftBouncedSave = 0;
			$SuppressedSave = 0;
			$MarkedAsSpamSave = 0;
			$UnsubscribedSave = 0;
			$conversionSave = 0;
			if ($affected_rows == 0) {
			} else {
				while ($row = $Stmt->fetch()) {
					$SentSave = $row['DTSent'];
					$DeliveredSave = $row['DTDelivered'];
					$OpenedSave = $row['DTOpened'];
					$ClickedSave = $row['DTClicked'];
					$FailedToProcessSave = $row['DTFailedToProcess'];
					$HardBouncedSave = $row['DTHardBounced'];
					$SoftBouncedSave = $row['DTSoftBounced'];
					$SuppressedSave = $row['DTSuppressed'];
					$MarkedAsSpamSave = $row['DTMarkedAsSpam'];
					$UnsubscribedSave = $row['DTUnsubscribed'];
					$conversionSave = $row['DTConversion'];
				}
			}
			
			if ($SentSave) { } else { $SentSave = 0; }
			if ($DeliveredSave) { } else { $DeliveredSave = 0; }
			if ($OpenedSave) { } else { $OpenedSave = 0; }
			if ($ClickedSave) { } else { $ClickedSave = 0; }
			if ($FailedToProcessSave) { } else { $FailedToProcessSave = 0; }
			if ($HardBouncedSave) { } else { $HardBouncedSave = 0; }
			if ($SoftBouncedSave) { } else { $SoftBouncedSave = 0; }
			if ($SuppressedSave) { } else { $SuppressedSave = 0; }
			if ($MarkedAsSpamSave) { } else { $MarkedAsSpamSave = 0; }
			if ($UnsubscribedSave) { } else { $UnsubscribedSave = 0; }
			if ($conversionSave) { } else { $conversionSave = 0; }
			
			if ($Sent == 0) { $Sent = $SentSave; }
			if ($Delivered == 0) { $Delivered = $DeliveredSave; }
			if ($Opened == 0) { $Opened = $OpenedSave; }
			if ($Clicked == 0) { $Clicked = $ClickedSave; }
			if ($FailedToProcess == 0) { $FailedToProcess = $FailedToProcessSave; }
			if ($HardBounced == 0) { $HardBounced = $HardBouncedSave; }
			if ($SoftBounced == 0) { $SoftBounced = $SoftBouncedSave; }
			if ($Suppressed == 0) { $Suppressed = $SuppressedSave; }
			if ($MarkedAsSpam == 0) { $MarkedAsSpam = $MarkedAsSpamSave; }
			if ($Unsubscribed == 0) { $Unsubscribed = $UnsubscribedSave; }
			if ($conversion == 0) { $conversion = $conversionSave; }
			
			
			if ($Sent == '0') {
				$ClickedRate = "0";
			} else {
				$ClickedRate = 100 * ($Clicked / $Sent);						
			}
			
			if ($TEST == 't') {
				echo "ClickedRate : $ClickedRate<br>";
			}
			
			//$campaignType = 'PromoteBlog';
			$campaignType = $moreParam['campaignType'];  //#1185
			$logger2->log("DV_CampaignMeasures campaignType = ". $campaignType);	
			
			$sql = 'UPDATE DV_CampaignMeasures set lastUpdateDate=NOW(),campaignName=?,DTSent=?,DTDelivered=?,DTOpened=?,DTClicked=?,DTFailedToProcess=?,DTHardBounced=?,DTSoftBounced=?,DTSuppressed=?,DTMarkedAsSpam=?,DTUnsubscribed=?,DTConversion=?,studioID=?,campaignName=?,campaignType=?,Reach=?,Click=?,ClickedRate=? where acctID=? and campaignID=?';
			try {
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($campaignName,$Sent,$Delivered,$Opened,$Clicked,$FailedToProcess,$HardBounced,$SoftBounced,$Suppressed,$MarkedAsSpam,$Unsubscribed,$conversion,$PROGRAMID,$campaignName,$campaignType,$Sent,$Clicked,$ClickedRate,$ACCOUNTID,$DVPROGRAMID));
				$saveSuccess = true;
				$saveErrorMessage = '';
			} catch(Exception $e){
				$saveSuccess = false;	
				$saveErrorMessage = $e->getMessage();
			}
			$logger2->log("saveSuccess = ". $saveSuccess);	
			if ($TEST == 't') {
				echo "<br>$campaignName,$Sent,$Delivered,$Opened,$Clicked,$FailedToProcess,$HardBounced,$SoftBounced,$Suppressed,$MarkedAsSpam,$Unsubscribed,$conversion,$ACCOUNTID,$PROGRAMID<br>";
				echo "<br>1990 GetCampaignReportEmailSummary saveErrorMessage : $saveErrorMessage<br>";
			}
			
			$group = array(									
				'Email'=>$Email,
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'Unsubscribed'=>$Unsubscribed,
				'conversion'=>$conversion
			);					
			$rows[] = $group;
			
		}
		$success = true;
		
		if ($TEST == 't') {
			echo "sizeof ".sizeof($rows)."<br>";
		}
		if (sizeof($rows) == 0) {
			$sql = 'UPDATE DV_CampaignMeasures set lastUpdateDate=NOW() where acctID=? and campaignID=?';
			try {
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($ACCOUNTID,$DVPROGRAMID));
				$saveSuccess = true;
				$saveErrorMessage = '';
			} catch(Exception $e){
				$saveSuccess = false;	
				$saveErrorMessage = $e->getMessage();
			}
		}
		
	} else {
		$success = false;
		$rows = null;
	}
	
	$result = json_encode( array(
		'success'=>$success, 
		'rows' => $rows, 
		'reportURL' => $reportURL, 
		'token' => $token, 
		'saveErrorMessage' => $saveErrorMessage,
		'DVPROGRAMID' => $DVPROGRAMID,
		'PROGRAMID' => $PROGRAMID
	));
	return $result;

}


function GetCampaignReportSent($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $DVPROGRAMID){	

	if ($PROGRAMID) {
		
		
		$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=directmailtotals&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";
		
		
		$rows = runReport($TEST, $reportURL, $token);
		if ($TEST =='t') {
			var_dump($rows);
			echo "<br>";
		}
		$jsonRow = json_decode($rows);
		if ($TEST =='t') {
			var_dump($jsonRow);
			echo "<br>";
		}
		$rows = array();

		foreach( $jsonRow as $row1 ) {	
			if ($TEST =='t') {
				var_dump($row1);
				echo "<br>";
			}
			
			$ProgramID = $row1->{'ProgramID'};
			$ProgramName = $row1->{'ProgramName'};
			$Targeted = $row1->{'Targeted'};
			$Recipiants = $row1->{'Recipiants'};
			$IMBScanned = $row1->{'IMBScanned'};
			
		
			
			$group = array(									
				'ProgramID'=>$ProgramID,
				'ProgramName'=>$ProgramName,
				'Targeted'=>$Targeted,
				'Recipiants'=>$Recipiants,
				'IMBScanned'=>$IMBScanned,
			);					
			$rows[] = $group;
			
		}
		$success = true;
		
	} else {
		$success = false;
		$rows = null;
	}
	
	$result = json_encode( array('success'=>$success, 'rows' => $rows, 'reportURL' => $reportURL, 'token' => $token));
	return $result;

}


function GetCampaignReportEmailSummaryDW($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $DVPROGRAMID){	

	if ($PROGRAMID) {
		$Targeted = 0;
		$Recipiants = 0;
		$reportSent = GetCampaignReportSent($TEST, $ACCOUNTID, $PROGRAMID, $campaignName, $fd, $td, $token, $DVPROGRAMID);
		$CampaignMeasure = json_decode($reportSent);
        if ($CampaignMeasure) {
        	$Targeted = $CampaignMeasure->rows[0]->Targeted;
    		$Recipiants = $CampaignMeasure->rows[0]->Recipiants;
        }
		
		$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemail_summary&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

		$rows = runReport($TEST, $reportURL, $token);
			//var_dump($rows);
			//echo "<br>";
		$jsonRow = json_decode($rows);
			//var_dump($jsonRow);
			//echo "<br>";
		$rows = array();

		$conversionResult = getConversionReport($TEST, $ACCOUNTID, $PROGRAMID, $DVPROGRAMID);
		$conversion = $conversionResult['affected_rows2'];	
		foreach( $jsonRow as $row1 ) {	
			//var_dump($row1);
			//echo "<br>";
			$Email = $row1->{'Email'};
			//$Sent = $row1->{'Sent'};
			if ($Targeted) {
				$Sent = $Targeted;
			} else {
				$Sent = $row1->{'Sent'};
			}
			$Delivered = $row1->{'Delivered'};
			$Opened = $row1->{'Opened'};
			$Clicked = $row1->{'Clicked'};
			$FailedToProcess = $row1->{'FailedToProcess'};
			$HardBounced = $row1->{'HardBounced'};
			$SoftBounced = $row1->{'SoftBounced'};
			$Suppressed = $row1->{'Suppressed'};
			$MarkedAsSpam = $row1->{'MarkedAsSpam'};
			$Unsubscribed = $row1->{'Unsubscribed'};

			
			$group = array(									
				'Email'=>$Email,
				'Reach'=>$Sent,
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'Unsubscribed'=>$Unsubscribed,
				'conversion'=>$conversion
			);					
			$rows[] = $group;
			
		}
		$success = true;
		
	} else {
		$success = false;
		$rows = null;
	}
	
	$result = json_encode( array('success'=>$success, 'rows' => $rows, 'reportURL' => $reportURL, 'token' => $token));
	return $result;

}


function GetReportAllEmail($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $EMAIL, $PWD){
	return GetReportAllEmailMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, '', $EMAIL, $PWD);
}

function GetReportAllEmailMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $mode, $EMAIL, $PWD){
	$logger2 = new FileLogger("GetReportAllEmailMode.log");
	error_log("-SESSION- GetReportAllEmailMode $ACCOUNTID $ProgramID ,$mode");
	if (!$ProgramID) {
		$ProgramID = 0;
	}
	
	$Targeted = 0;
	$Recipiants = 0;
	$DVPROGRAMID = '';
	//$logger2->log("before GetCampaignReportSent 3428 $ACCOUNTID");
	$reportSent = GetCampaignReportSent($TEST, $ACCOUNTID, $ProgramID, $campaignName, $fd, $td, $token, $DVPROGRAMID);
	//$logger2->log("after GetCampaignReportSent 3430 $ACCOUNTID");
	if ($TEST == 't') {
		echo "3419 GetCampaignReportSent<br>";
		var_dump ($reportSent);
		echo "<br>";	
	}
	$CampaignMeasure = json_decode($reportSent);
    if ($CampaignMeasure) {
    	$Targeted = $CampaignMeasure->rows[0]->Targeted;
    	$Recipiants = $CampaignMeasure->rows[0]->Recipiants;
    }
    if ($TEST == 't') {
    	echo "3430 Targeted : $Targeted Recipiants : $Recipiants<br>";
    }
	$logger2->log("3430 Targeted : $Targeted Recipiants : $Recipiants");
	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allemail_summary&si=$ProgramID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

	if ($TEST == 't') {
		unset($_SESSION["REPORTALLEMAIL_$ProgramID"]);
		$ThisURL = "$reportURL&token=$token";
		echo "GetReportAllEmail : $ThisURL<br>";

	}
	$logger2->log("GetReportAllEmail : $reportURL");
	if ($mode == 'preventTimeout') {
		$logger2->log("preventTimeout");
		preventTimeout();
	}
	
	if ($ProgramID == 0) {
		if ($mode == 'Account_Stat') {
			unset($_SESSION["REPORTALLEMAIL_$ProgramID"]);
		}
		if ($_SESSION["REPORTALLEMAIL_$ProgramID"]) {
			$rows = $_SESSION["REPORTALLEMAIL_$ProgramID"];
		} else {
			$moreInfo['ACCOUNTID'] = $ACCOUNTID;
			$moreInfo['EMAIL'] = $EMAIL;
			$moreInfo['PWD'] = $PWD;
			if ($mode == 'Account_Stat') {
				$moreInfo['preventTimeout'] = '';
			} else {
				$moreInfo['preventTimeout'] = 'preventTimeout';
			}
			$logger2->log("before runReportMoreInfo 3468 $ACCOUNTID");
			$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
			$logger2->log("after runReportMoreInfo 3470 $ACCOUNTID");
			$_SESSION["REPORTALLEMAIL_$ProgramID"] = $rows;
		}
	} else {
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$moreInfo['preventTimeout'] = 'preventTimeout';
		$logger2->log("before runReportMoreInfo 3478 $ACCOUNTID");
		$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
		$logger2->log("before runReportMoreInfo 3480 $ACCOUNTID");
	}
		
	$jsonRow = json_decode($rows);
	$rows = array();
	foreach( $jsonRow as $row1 ) {	
		//var_dump($row1);
		//echo "<br>";
		$ProgramID = $row1->{'Program_ID'};
		$Program = $row1->{'Program'};
		$Email = $row1->{'Email'};
		//$Sent = $row1->{'Sent'};
		if ($Targeted) {
			$Sent = $Targeted;
		} else {
			$Sent = $row1->{'Sent'};
		}
		$Delivered = $row1->{'Delivered'};
		$Opened = $row1->{'Opened'};
		$Clicked = $row1->{'Clicked'};
		$FailedToProcess = $row1->{'FailedToProcess'};
		$HardBounced = $row1->{'HardBounced'};
		$SoftBounced = $row1->{'SoftBounced'};
		$Suppressed = $row1->{'Suppressed'};
		$MarkedAsSpam = $row1->{'MarkedAsSpam'};
		$Unsubscribed = $row1->{'Unsubscribed'};			
		
		$group = array(									
			'ProgramID'=>$ProgramID,
			'Program'=>$Program,
			'Email'=>$Email,
			'Sent'=>$Sent,
			'Delivered'=>$Delivered,
			'Opened'=>$Opened,
			'Clicked'=>$Clicked,
			'FailedToProcess'=>$FailedToProcess,
			'HardBounced'=>$HardBounced,
			'SoftBounced'=>$SoftBounced,
			'Suppressed'=>$Suppressed,
			'MarkedAsSpam'=>$MarkedAsSpam,
			'Unsubscribed'=>$Unsubscribed,
			'conversion'=>'0'
		);					
		$rows[] = $group;
		
	}	
	return $rows;
}

function GetReportAllPage($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $EMAIL, $PWD) {
	return GetReportAllPageMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, '', $EMAIL, $PWD);
}

function GetReportAllPageMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $mode, $EMAIL, $PWD) {
    error_log("-SESSION- GetReportAllPageMode");
	if (!$ProgramID) {
		$ProgramID = 0;
	}

	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	
	if ($TEST == 't') {
		echo "2179 GetReportAllPageMode $TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $mode, $EMAIL, $PWD<br>";
	}
	
	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allmspagesl_detail&si=$ProgramID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";
	
	if ($ProgramID == 0) {
		if ($mode == 'Account_Stat') {
			unset($_SESSION["REPORTALLPAGE_$ProgramID"]);
		}		
		if ($_SESSION["REPORTALLPAGE_$ProgramID"]) {
			$rows = $_SESSION["REPORTALLPAGE_$ProgramID"];
		} else {
			if ($TEST == 't') {
				echo "2192 runReportMoreInfo $TEST, $reportURL, $token<br>";
			}
			$moreInfo['ACCOUNTID'] = $ACCOUNTID;
			$moreInfo['EMAIL'] = $EMAIL;
			$moreInfo['PWD'] = $PWD;
			$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
			$_SESSION["REPORTALLPAGE_$ProgramID"] = $rows;
		}
	} else {
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
	}
	
	if ($TEST == 't') {
		echo "AAA<br>";
		echo "001-> $reportURL<br>";
		var_dump($rows);
		echo "<br>";
	}
	$jsonRow = json_decode($rows);
	if ($TEST == 't') {
		echo "BBB<br>";
		var_dump($jsonRow);
		echo "<br>";
	}
	$rows = array();
	$Visitors = '0';
	$Submits = '0';
	$Clicks = '0';
	$Responses = '0';
	$Sent = '0';
	$Clicks = '0';
	

	foreach( $jsonRow as $row1 ) {
		$Program = $row1->{'Program'};
		$ProgramID = $row1->{'Program_ID'};
		$Page = $row1->{'Page'};				
		$PageUpper = strtoupper($Page);
		if ($PageUpper == 'WELCOME.HTML') {
			$orderid = '1';			
			$Visitors = $row1->{'Visitors'};
			$Submits = $row1->{'Submits'};			
			$Responses = $row1->{'Responses'};	
		} else if ($PageUpper == 'DOWNLOAD.HTML') {
			$orderid = '2';
			$Clicks = $row1->{'Clicks'};			
			$download = $Clicks;				
		} else {
			
		}
		
		if (array_key_exists($Program, $rows)) {			
			if ($PageUpper == 'WELCOME.HTML') {
				$orderid = '1';			
				$rows[$Program]['Visitors'] = $Visitors;
				$rows[$Program]['Sent']  = $Visitors;
				$rows[$Program]['Submits'] = $Submits;
				$rows[$Program]['Responses'] = $Responses;
			} else if ($PageUpper == 'DOWNLOAD.HTML') {
				$orderid = '2';
				$download = $Clicks;				
				$rows[$Program]['Clicks'] = $Clicks;
				$rows[$Program]['Clicked'] = $Clicks;
			} else {
				
			}					
			
		} else {
			$group = array(				
				'ProgramID'=>$ProgramID,
				'Program'=>$Program,
				'Visitors'=>$Visitors,
				'Submits'=>$Submits,
				'Clicks'=>$Clicks,
				'Responses'=>$Responses,
				'Sent'=>$Visitors,
				'Clicked'=>$Clicks			
			);
			$rows[$Program] = $group;
		}
	}	
	
	return $rows;
}

function GetReportAllsmsMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, $mode, $EMAIL, $PWD){
	error_log("-SESSION- GetReportAllsmsMode $ACCOUNTID $ProgramID $mode");
	$logger2 = new FileLogger("GetReportAllsmsMode.log");
	if (!$ProgramID) {
		$ProgramID = 0;
	}	
	
	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allsms_summary&si=$ProgramID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

	if ($TEST == 't') {
		unset($_SESSION["REPORTALLSMS_$ProgramID"]);
		$ThisURL = "$reportURL&token=$token";
		echo "GetReportAllEmail : $ThisURL<br>";

	}

	if ($mode == 'preventTimeout') {		
		$logger2->log("preventTimeout");
		preventTimeout();
	}
	
	if ($ProgramID == 0) {
		if ($mode == 'Account_Stat') {
			unset($_SESSION["REPORTALLSMS_$ProgramID"]);
		}
		if ($_SESSION["REPORTALLSMS_$ProgramID"]) {
			$rows = $_SESSION["REPORTALLSMS_$ProgramID"];
		} else {
			$moreInfo['ACCOUNTID'] = $ACCOUNTID;
			$moreInfo['EMAIL'] = $EMAIL;
			$moreInfo['PWD'] = $PWD;
			$moreInfo['preventTimeout'] = '';
			$logger2->log("before runReportMoreInfo 3673 : ACCOUNTID");
			$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
			$logger2->log("after runReportMoreInfo 3676 : ACCOUNTID");
			$_SESSION["REPORTALLSMS_$ProgramID"] = $rows;
		}
	} else {
		$moreInfo['ACCOUNTID'] = $ACCOUNTID;
		$moreInfo['EMAIL'] = $EMAIL;
		$moreInfo['PWD'] = $PWD;
		$moreInfo['preventTimeout'] = '';
		$logger2->log("before runReportMoreInfo 3683 : ACCOUNTID");
		$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
		$logger2->log("before runReportMoreInfo 3686 : ACCOUNTID");
	}
		
	$jsonRow = json_decode($rows);
	$rows = array();
	
	$SentAll = 0;
	
	foreach( $jsonRow as $row1 ) {	
		//var_dump($row1);
		//echo "<br>";
		
		
		//rows : [{"Program_ID":127,"Program":"CL 021503","Delivered":"0","Sent":2,"Queued":0,"Rejected":0,"OptedOut":0,"InvalidRecord":2}]
		
		$ProgramID = $row1->{'Program_ID'};
		$Program = $row1->{'Program'};
		$Delivered = $row1->{'Delivered'};
		$Sent = $row1->{'Sent'};
		$Queued = $row1->{'Queued'};
		$Rejected = $row1->{'Rejected'};
		$OptedOut = $row1->{'OptedOut'};
		$InvalidRecord = $row1->{'InvalidRecord'};
		
		$SentAll = $SentAll+$Sent;
		
		$group = array(									
			'ProgramID'=>$ProgramID,
			'Program'=>$Program,
			'Delivered'=>$Delivered,
			'Sent'=>$Sent,
			'Queued'=>$Queued,
			'Rejected'=>$Rejected,
			'OptedOut'=>$OptedOut,
			'InvalidRecord'=>$InvalidRecord,
		);					
		$rows[] = $group;
		
	}	
	$result['rows'] = $rows;
	$result['SentAll'] = $SentAll; 
	return $result;
}

function getDV_ProgramInfo($account) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from DV_ProgramInfo where acctID = ?";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($account));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}

	return $row2;
}

function getDV_ProgramID($account, $progID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from DV_ProgramInfo where acctID = ? and progID = ? limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($account, $progID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}
	return $row2;
}


function addSeedContact($userTicket, $firstname, $lastname, $email) {
	$Credentials = array
	(
		"Ticket" => $userTicket
	);
	return addSeedContactCredentials($Credentials, $firstname, $lastname, $email);
}

function addSeedContactCredentials($Credentials, $firstname, $lastname, $email) {
	
	$logger2 = new FileLogger("addSeedContactCredentials.log");
	
	$contactDataAPI = array(
		array("Key" => "firstname", "Value" => $firstname),
		array("Key" => "lastname", "Value" => $lastname),
		array("Key" => "email", "Value" => $email),
		array("Key" => "isseed", "Value" => 'True')							
	);
	$addContact  = array // The new Contact array; I grabbed this from Dustin's email
			(
				"Credentials" => $Credentials, 
				"KeyValueList" => $contactDataAPI
			);
	//error_log("[global.php: ".date('m.d.Y h:i:s')."] About to call function addSeedContact \n userTicket:$userTicket, firstname:$firstname, lastname:$lastname, email :$email", 3, "/efs/log/my-errors.log");

	$logger2->log(" addContact - ".json_encode($addContact));

	$newContact = callServiceCredential("contactservice/CreateContact", $addContact);
	
	$logger2->log(" newContact - ".json_encode($newContact));

	$ErrorCode = $newContact->{"Result"}->{"ErrorCode"};
	$errorMessage = "";
	$purl = "";

	if ($ErrorCode == "") {
		$purl=$newContact->{'Purl'}; // The Contact's newly issued PURL	
		$message = "$firstname $lastname was added.  Let's do another!";
		$errorMessage = '';
		$success = true;
		
	} else {
		//$errorMessage = "addSeedContact ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
		//$message = $newContact->{"Result"}->{"ExceptionMessage"};
		
		$peopleDataAPI = array(
			'firstname'=>$firstname,
			'lastname'=>$lastname,
			'email'=>$email,
			'isseed'=>'True'
		);
		
		$logger2->log(" peopleDataAPI - ".json_encode($peopleDataAPI));
		
		$updateContactResult = updateContactCredentials($Credentials, $peopleDataAPI);
		
		$logger2->log(" updateContactResult - ".json_encode($updateContactResult));
		
		$success = $updateContactResult['success'];
		$errorMessage = $updateContactResult['errorMessage'];
		$message = $updateContactResult['message'];
	}
	return array('success'=>$success, 'message' => $message, 'errorMessage' => $errorMessage, 'purl' => $purl, 'addContact'=> $addContact, 'newContact'=> $newContact);
}

function addContact($userTicket, $contactData) {
	$aName = array_keys($contactData);
    $aValue = array_values($contactData);
	$KeyValueList = array();
	for($i=0;$i<count($aName);$i++){
		array_push($KeyValueList,array("Key" => $aName[$i], "Value" => $aValue[$i]));
	}
	
	$addContact  = array(
		"Credentials" => array(
			"Ticket" => $userTicket
		), 
		"KeyValueList" => $KeyValueList	
	);
	logDebug("[global.php]::fn[addContact] About to call function addContact userTicket:$userTicket, firstname:".$contactData['FirstName'].", lastname:".$contactData['LastName'].", email :".$contactData['Email'] ); 
	//error_log("[global.php: ".date('m.d.Y h:i:s')."] About to call function addContact \n userTicket:$userTicket, firstname:$firstname, lastname:$lastname, email :$email", 3, "/efs/log/my-errors.log");

	$newContact = callService("contactservice/CreateContact", $addContact);


	$ErrorCode = $newContact->{"Result"}->{"ErrorCode"};
	$errorMessage = "";
	$purl = "";

	if ($ErrorCode == "") {
		$purl=$newContact->{'Purl'}; // The Contact's newly issued PURL	
		$message = $contactData['FirstName'].' '.$contactData['LastName'].' was added.';
		$errorMessage = '';
		$success = true;
		
	} else {
		$errorMessage = "addContact ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
		$message = $newContact->{"Result"}->{"ExceptionMessage"};	
		$success = false;
	}
	return array('success'=>$success, 'message' => $message, 'errorMessage' => $errorMessage, 'purl' => $purl);
}

function updateContact($userTicket, $contactData) {
	$Credentials = array
	(
		"Ticket" => $userTicket
	);
	return updateContactCredentials($Credentials, $contactData);
}

function updateContactCredentials($Credentials, $contactData) {
	
	$logger2 = new FileLogger("updateContact.log");
	
	$aName = array_keys($contactData);
    $aValue = array_values($contactData);
	$KeyValueList = array();
	for($i=0;$i<count($aName);$i++){
		array_push($KeyValueList,array("Key" => $aName[$i], "Value" => $aValue[$i]));
	}
	$updateContact  = array(
		"Credentials" => $Credentials,
		"KeyValueList" => $KeyValueList	
	);
	
	$logger2->log("updateContact request - ".json_encode($updateContact));
	
	$editContact = callServiceCredential("contactservice/UpdateContact", $updateContact);
	
	$logger2->log("updateContact result - ".json_encode($editContact));

	$ErrorCode = $editContact->{"Result"}->{"ErrorCode"};
	$errorMessage = "";

	if ($ErrorCode == "") {
		$message = $contactData['FirstName'].' '.$contactData['LastName'].' '.$contactData['PURL'].' was updated.';
		$errorMessage = '';
		$success = true;
		
	} else {
		$errorMessage = "updateContact ErrorMessage : ".$editContact->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$editContact->{"Result"}->{"ExceptionMessage"};
		$message = $editContact->{"Result"}->{"ExceptionMessage"};	
		$success = false;
	}
	return array('success'=>$success, 'message' => $message, 'errorMessage' => $errorMessage);
}

function createSubAccount($mode, $POWERACCOUNTID, $CLONEACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD, $studioDomain, $signUpAccount, $signUpFirstName, $signUpLastName, $signUpEmail, $signUpPassword, $moreParam, $moredata) {
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$logger3 = new FileLogger("createSubAccount.log");  

	$logger3->log(" Start");
	$logger3->log("mode, POWERACCOUNTID, CLONEACCOUNTID, EMAIL, PWD, PARTNERGUID, PARTNERPASSWORD, studioDomain, signUpAccount, signUpFirstName, signUpLastName, signUpEmail, signUpPassword");
	$logger3->log("$mode, $POWERACCOUNTID, $CLONEACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD, $studioDomain, $signUpAccount, $signUpFirstName, $signUpLastName, $signUpEmail, $signUpPassword");
	$logger3->log("moreParam = ". json_encode($moreParam));
	$logger3->log("moredata = ". json_encode($moredata));
	
	$objDateTime = new DateTime('NOW');
	$startTime = $objDateTime->format('Y-m-d H:i:s.u');
	$success = false;
	
	$Authentication_PowerUser_status = "";	
	$CreateAccount_status = "";
	$couchDB_status = "";
	$insertAssetDirectory_status = "";
	$insertFormFieldOption_status = "";
	$insertOptionOrder_status = "";
	$s3_create_assetDir_status = "";
	$Authentication_AccountUser_status = "";
	$AddSeedContact_status = "";
	$AddDomain_status = "";
	$cloudConnect_status = "";
	$AddRole_status = "";
	
	$ErrorCode = '';
	$errorMessage = '';
	
    $studioParam = GetStudioAPIParam();
    $POWERACCOUNTID = $studioParam['accountID'];
    $EMAIL = $studioParam['email'];
    $PWD = $studioParam['pwd'];
    $PARTNERGUID = $studioParam['guid'];
    $PARTNERPASSWORD = $studioParam['password'];
    
    $productTier = $moredata['productTier'];
	$ProductD = getProductData($productTier);
	$productData = $ProductD['productData'];
	$payperiod = $ProductD['payperiod'];
	$amount = $ProductD['amount'];
	$productCode = $ProductD['productCode'];
	
	
	
    
	$logger3->log("About to call function getTicketCurl POWERACCOUNTID : $POWERACCOUNTID ");
	$powerTicket = getTicketCurl($POWERACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	$logger3->log("After to call function getTicketCurl powerTicket : $powerTicket ");
	
	
	
	$Credentials = array
	(
		"Ticket"=> $powerTicket    // getticket from info in step 0
	);
	
	$logMode = $moredata['logMode'];
	$cloneFromProd = true;
	if ($logMode == 'subaccount') {
		$UiAccess = $moredata['UiAccessSub'];
	} else {
		 $UiAccess = $ProductD['UiAccess'];
	}
	
	
	$logger3->log("CLONEACCOUNTID : $CLONEACCOUNTID , ParentAccountID : $ParentAccountID");
	$logger3->log("Credentials = ". json_encode($Credentials));
	
	$signUpAccount2 = $signUpAccount;
	if ($mode == 'retry') {
		$cnt = 1;		
		while($cnt > 0) {
			$aID = rand(0,10000);
			$signUpAccount1 = $signUpAccount2.'_'.$aID;		
			$result = CheckNewAccount($powerTicket, $signUpAccount1);
			$cnt =  $result['cnt'];	
		} 
		$signUpAccount = $signUpAccount1;
	}
	
	$Authentication_PowerUser_status = '';
	$Authentication_AccountUser_status = '';
	$CreateAccount_status = '';
	$AddDomain_status = '';
	$AddRole_status = '';
	$RegisterAndActivateUser_status = '';
	$RemovePowerUser_status = '';
	$AddSeedContact_status = '';
	$couchDB_status = '';
	$cloudConnect_status = '';
	$CloneDatabase_status = '';
	$SaveAPIKey_status = '';
	$SaveSignupInfo_status = '';
	$ErrorMessageStudio = '';
	$insertAssetDirectory_status = '';
	$insertFormFieldOption_status = '';
	$insertOptionOrder_status = '';	
	$s3_create_assetDir_status = '';

	

	if ($powerTicket == '') {
		$Authentication_PowerUser_status = "Fail";	
		$errorMessage = "Authentication ErrorMessage : Fail to get power user's ticket";
		$ErrorMessageStudio = "Fail to get power user's ticket";
	} else {
		$Authentication_PowerUser_status = "Success";	
		$studioRequest = GetCurrentConfig()->studioRequest;
		if ($studioRequest == 'live') {
			$CreateAccountRequest   = array
			(
				"ClientType"=> 0,
				"CustomReportName"=> null,
				"FocusArea_ID"=> 0,
				"Name"=> $signUpAccount,    // from signup page
				"PasswordRenewalFrequency"=> 0,
				"PasswordStrengthLevel"=> 0,
				"ServicePackage_ID"=> 2,
				"SqlServerName"=> null,
				"SqlServer_ID"=> 0,
				"StatementDay"=> 0,
				"Status"=> 1,
				"TimeoutMin"=> 30,
				"Credentials" => $Credentials,
				"InheritFromAccountID"=> $CLONEACCOUNTID,    // dv-production
				"PowerUserEmail"=> $signUpEmail,
				"PowerUserFirstName"=> $signUpFirstName,
				"PowerUserLastName"=> $signUpLastName,
				"PowerUserPassword"=> $signUpPassword,
				"UiAccess"=> $UiAccess
			);
		} else {
			$CreateAccountRequest   = array
			(
				"ClientType"=> 0,
				"CustomReportName"=> null,
				"FocusArea_ID"=> 0,
				"Name"=> $signUpAccount,    // from signup page
				"PasswordRenewalFrequency"=> 0,
				"PasswordStrengthLevel"=> 0,
				"ServicePackage_ID"=> 2,
				"SqlServerName"=> null,
				"SqlServer_ID"=> 0,
				"StatementDay"=> 0,
				"Status"=> 1,
				"TimeoutMin"=> 30,
				"Credentials" => $Credentials,
				"InheritFromAccountID"=> $CLONEACCOUNTID,    // dv-production
				"PowerUserEmail"=> $signUpEmail,
				"PowerUserFirstName"=> $signUpFirstName,
				"PowerUserLastName"=> $signUpLastName,
				"PowerUserPassword"=> $signUpPassword,
				"UiAccess"=> $UiAccess
			);
		}
		$logger3->log("About to call function callService for accountservice/CreateAccount");
		$logger3->log("CreateAccountRequest = ". json_encode($CreateAccountRequest));

		$stop = false;
		$loopCount = 0;
		while($stop == false){
			$ImportResponse = callService("accountservice/CreateAccount", $CreateAccountRequest);
			$CreateAccountResponse = $ImportResponse;
			$logger3->log("Finished calling function callService for accountservice/CreateAccount $loopCount");
			if ($ImportResponse) {
				$logger3->log("CreateAccountResponse = ". json_encode($ImportResponse));
			}
			//$logger3->log("$loopCount CreateAccountResponse = ". json_encode($CreateAccountResponse));
			$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
			$loopCount++;
			if ($ErrorCode == "") {
				$stop = true;
			} else {
				if ($loopCount == 1) {
					sleep(5);
				} else if ($loopCount == 2) {
					sleep(10);
				} else if ($loopCount == 3) {
					sleep(20);
				} else if ($loopCount == 4) {
					sleep(40);
				} else if ($loopCount == 5) {
					$stop = true;
				}			
			}
		}

		
		
		$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			$CreateAccount_status = "Success";
			$errorMessage = '';
			$AccountID = $ImportResponse->{"AccountID"};
			
		} else {
			$CreateAccount_status = "Fail";
			$errorMessage = "CreateAccount ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
			$ErrorMessageStudio = $ImportResponse->{"Result"}->{"ErrorMessage"};
			$AccountID = "-1";
		}		

		$logger3->log("AccountID : $AccountID");

		if ($AccountID == '-1') {
		} else {

			//couchDB_createAccount($accountID);
			$logger3->log("About to call function MySQL_createAccount");
			$config = GetCurrentConfig();
			$result = MySQL_createAccount($AccountID,$signUpAccount,$signUpEmail,$signUpFirstName,$signUpLastName,$moreParam);
			$MySQL_createAccountResult = $result;
			$logger3->log("Finished calling function MySQL_createAccount ". json_encode($MySQL_createAccountResult));

			
			$CloneDatabase_status = $result['CloneDatabase_status'];
			$SaveAPIKey_status = $result['SaveAPIKey_status'];
			$SaveSignupInfo_status = $result['SaveSignupInfo_status'];
			$errorMessage = $result['message'];
			$ErrorMessageStudio = $result['message'];

			if ($result['success']) {
				$couchDB_status = "Success";
			} else {
				$couchDB_status = "Fail";			
			}

			$logger3->log("About to call function insertAssetDirector");
			$result = insertAssetDirectory($AccountID);
			$insertAssetDirectoryResult = $result;
			$logger3->log("Finished calling function insertAssetDirector ". json_encode($insertAssetDirectoryResult));
			if ($result['success']) {
				$insertAssetDirectory_status = "Success";
				$errorMessage = '';
				$userID = '37';
			} else {
				$insertAssetDirectory_status = "Fail";
				$errorMessage = $result['ErrorMessage'];
				$ErrorMessageStudio = $result['ErrorMessage'];
				$userID = '-1';
			}

			$logger3->log("About to call function insertFormFieldOption");
			$result = insertFormFieldOption($AccountID);
			$insertFormFieldOptionResult = $result;
			$logger3->log("Finished calling function insertFormFieldOption ". json_encode($insertFormFieldOptionResult));
			if ($result['success']) {
				$insertFormFieldOption_status = "Success";
				$errorMessage = '';
				$userID = '37';
			} else {
				$insertFormFieldOption_status = "Fail";
				$errorMessage = $result['ErrorMessage'];
				$ErrorMessageStudio = $result['ErrorMessage'];
				$userID = '-1';
			}
			
			
			$insertOptionOrder_status = "Success";  // #2332 remove insert country/state/gender 
			$insertOptionOrderResult = []; 

// 			$logger3->log("About to call function insertOptionOrder");
// 			$result = insertOptionOrder($AccountID);
// 			$insertOptionOrderResult = $result;
// 			$logger3->log("Finished calling function insertOptionOrder ". json_encode($insertOptionOrderResult));
// 			if ($result['success']) {
// 				$insertOptionOrder_status = "Success";
// 				$errorMessage = '';
// 				$userID = '37';
// 			} else {
// 				$insertOptionOrder_status = "Fail";
// 				$errorMessage = $result['ErrorMessage'];
// 				$ErrorMessageStudio = $result['ErrorMessage'];
// 				$userID = '-1';
// 			}

			$logger3->log("About to call function s3_create_assetDir");
			//$result = s3_create_assetDirSQS($AccountID);  
			$result = s3_create_assetDir($AccountID);  
			$s3_create_assetDirResult = $result;
			$logger3->log("Finished calling function s3_create_assetDir ". json_encode($s3_create_assetDirResult));
			$results3 = $result;
			if ($result['success']) {
				$s3_create_assetDir_status = "Success";
				$errorMessage = '';
				$userID = '37';
			} else {
				$s3_create_assetDir_status = "Fail";
				$errorMessage = $result['errorMessage'];
				$ErrorMessageStudio = $result['errorMessage'];
				$userID = '-1';
			}
			
			$logger3->log("About to call function getTicketCurl");
			//$accountTicket = getTicketCurl($AccountID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
			
			
			$Authenticate = getAcrossAuthenticateCurl($AccountID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
			$logger3->log("Authenticate = ". json_encode($Authenticate) );
			$PartnerCredentials = $Authenticate['Credentials'];
			$accountTicket = $Authenticate['userTicket'];
			
			
			$logger3->log("Finished calling function getTicketCurl");
			if ($accountTicket == '') {
				$Authentication_AccountUser_status = "Fail";	
				$errorMessage = "Authentication ErrorMessage : Fail to get account user's ticket";
				$ErrorMessageStudio = "Fail to get account user's ticket";
			} else {
				$Authentication_AccountUser_status = "Success";

				if ($couchDB_status == 'Fail') {
				} else {	
					
					// add seed contact
					$logger3->log("About to call function addSeedContact");
					//$result = addSeedContactSQS($AccountID,$accountTicket, $signUpFirstName, $signUpLastName, $signUpEmail);									
					$result = addSeedContactCredentials($PartnerCredentials, $signUpFirstName, $signUpLastName, $signUpEmail);
					$addSeedContactResult = $result;
					$logger3->log("Finished calling function addSeedContact");					
					if ($result) {
						if ($result['success']) {
							$AddSeedContact_status = "Success";
						} else {
							$AddSeedContact_status = "Fail";
						}
						$errorMessage = $result['errorMessage'];
						$ErrorMessageStudio = $result['errorMessage'];
					} else {
						$AddSeedContact_status = "Fail";
					}

					$domain = "$AccountID.$studioDomain";
					$domainResult = createDomain($logger3, $PartnerCredentials,$domain);
					$AddDomain_status = $domainResult['status'];
					$errorMessage = $domainResult['errorMessage'];
					$domainID = $domainResult['roleID'];
					$ErrorMessageStudio = $domainResult['ErrorMessageStudio'];

					if ($domainID == "-1") {

					} else {
						
						$roleResult = createRole($logger3, $PartnerCredentials); 
                        $AddRole_status = $roleResult['status'];
                        $errorMessage = $roleResult['errorMessage'];
                        $roleID = $roleResult['roleID'];
                        $ErrorMessageStudio = $roleResult['ErrorMessageStudio'];
                        
                        $goalResult = createGoal($logger3, $PartnerCredentials); 
						
						// publish cloudconnect
						$logger3->log("About to call function createWebTracking accountTicket : $accountTicket , AccountID : $AccountID");
                        $createWebTrackingRet =  createWebTrackingSQS($accountTicket,$AccountID);
                        //createWebTracking($accountTicket,$AccountID);
                        $logger3->log("createWebTrackingRet = ". json_encode($createWebTrackingRet));
						$logger3->log("Finished calling function createWebTracking");

                        if($createWebTrackingRet['success']){
                            $cloudConnect_status = "Success";
                        }else{
                            $cloudConnect_status = "Fail";
                            $errorMessage = $createWebTrackingRet['message'];
							$ErrorMessageStudio = $createWebTrackingRet['message'];
                        }
                                              
													
					}
				}
			}
			
		}
	}

			//$AddDomain_status = "Fail";
    if ( (($Authentication_PowerUser_status == "Success") && ($CreateAccount_status == "Success") && ($couchDB_status == "Success") && ($insertAssetDirectory_status == "Success") && ($insertFormFieldOption_status == "Success") && ($insertOptionOrder_status == "Success") && ($s3_create_assetDir_status == "Success") && ($Authentication_AccountUser_status == "Success") && ($AddSeedContact_status == "Success") && ($AddDomain_status == "Success") && ($cloudConnect_status == "Success") && ($AddRole_status == "Success")) ) {
		$success = true;
		
		

		$emailLimit = $ProductD['emailLimit'];
		$emailOverage = $ProductD['emailOverage'];
		$contactLimit = $ProductD['contactLimit'];
		$contactOverage = $ProductD['contactOverage'];
		$smsLimit = $ProductD['smsLimit'];
		$smsOverage = $ProductD['smsOverage'];
		$enrichmentLimit = $ProductD['enrichmentLimit'];
		$enrichmentOverage = $ProductD['enrichmentOverage'];
		$contentSearchLimit = $ProductD['contentSearchLimit'];
		$contentSearchOverage = $ProductD['contentSearchOverage'];
		$contentLaunchLimit = $ProductD['contentLaunchLimit'];
		$contentLaunchOverage = $ProductD['contentLaunchOverage'];
		
		$recurringErrorMessage = '';
		
		if ($AccountID > 0) {
			$sql = 'UPDATE DV_UserInfo set recurringAmount=?, recurringPeriod=?, recurringProduct=?, emailLimit=?, emailOverage=?, contactLimit=?, contactOverage=?, smsLimit=?, smsOverage=?, enrichmentLimit=?,enrichmentOverage=?,contentSearchLimit=?,contentSearchOverage=?,contentLaunchLimit=?,contentLaunchOverage=? where acctID=?';
			//echo "$sql<br>";
			try {
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($amount, $payperiod, $productCode, $emailLimit, $emailOverage, $contactLimit, $contactOverage, $smsLimit, $smsOverage, $enrichmentLimit, $enrichmentOverage, $contentSearchLimit, $contentSearchOverage, $contentLaunchLimit, $contentLaunchOverage, $AccountID));
			} catch(Exception $e){
				$recurringErrorMessage = $e->getMessage();
			}
		}
		
		//if ($amount > 0) {
			$eventData['accountName'] = $signUpAccount;
			$eventData['userName'] = $signUpEmail;
			$eventData['firstName'] = $signUpFirstName;
			$eventData['lastName'] = $signUpLastName;
			$eventData['recurringAmount'] = $amount;
			$eventData['recurringPeriod'] = $payperiod;
			$eventData['recurringProduct'] = $productCode;
	 		
	 		$ipAddress = $moredata['signupIP'];
	 		
	 		
	 		$logger3->log("ipAddress $ipAddress");
	 		
	 		$eventData['ipAddress'] = $ipAddress;
	 		
	 		$servermode = GetCurrentConfig()->serverName;
	 		
	 		/*
	 		$query = getIPAPIServer($ipAddress,$servermode);
		
			$status = $query['status'];
		
		
			if($status == 'success') {
			
				$eventData['city'] = $query['city'];
				$eventData['state'] = $query['state'];
				$eventData['zip'] = $query['zip'];
				$eventData['country'] = $query['country'];
				$eventData['company'] = $query['org'];
				$eventData['isp'] = $query['isp'];
				$eventData['as'] = $query['as'];
				
			}
	 		*/
	 		
	 		
	 		$eventData['city'] = '';
			$eventData['state'] = '';
			$eventData['zip'] = '';
			$eventData['country'] = '';
			$eventData['company'] = '';
			$eventData['isp'] = '';
			$eventData['as'] = '';
	 		
	 		$activityDetail['userName'] = $signUpEmail;
	 		$activityDetail['eventName'] = 'Initial SignUp';
	 		$activityDetail['accountID'] = $AccountID;
	 		$activityDetail['accountName'] = $signUpAccount;
	 		$activityDetail['contentID'] = '';
	 		$activityDetail['contentName'] = '';
	 		$activityDetail['eventData'] = $eventData;
	 		
	 		$saveResult = SaveSiteActivity($activityDetail);
		//}
	}
	$objDateTime = new DateTime('NOW');
	$endTime = $objDateTime->format('Y-m-d H:i:s.u');
	return array(
		'success'=>$success, 
		'startTime' => $startTime, 
		'endTime' => $endTime, 
		'ErrorCode' => $ErrorCode, 
		'errorMessage' => $errorMessage, 
		'ErrorMessageStudio' => $ErrorMessageStudio,
		'AccountID' => $AccountID, 
		'signUpAccount' => $signUpAccount,
		'ipAddress' => $ipAddress,
		'Authentication_PowerUser_status' => $Authentication_PowerUser_status,
		'Authentication_AccountUser_status' => $Authentication_AccountUser_status,		
		'CreateAccount_status' => $CreateAccount_status,
		'AddDomain_status' => $AddDomain_status,
		'AddRole_status' => $AddRole_status,
		'RemovePowerUser_status' => $RemovePowerUser_status,
		'AddSeedContact_status' => $AddSeedContact_status,
		'CloneDatabase_status' => $CloneDatabase_status,
		'SaveAPIKey_status' => $SaveAPIKey_status,
		'SaveSignupInfo_status' => $SaveSignupInfo_status,
		'couchDB_status' => $couchDB_status,
		'cloudConnect_status' => $cloudConnect_status,	
		'insertAssetDirectory_status' => $insertAssetDirectory_status,
		'insertFormFieldOption_status' => $insertFormFieldOption_status,
		'insertOptionOrder_status' => $insertOptionOrder_status,		
		's3_create_assetDir_status' => $s3_create_assetDir_status,
        'accountTicket'=>$accountTicket,
		'CreateAccountRequest'=>$CreateAccountRequest,
		'CreateAccountResponse'=>$CreateAccountResponse,		
		'AddDomainRequest'=>$AddDomainRequest,
		'AddDomainResponse'=>$AddDomainResponse,		
		'AddRoleRequest'=>$AddRoleRequest,
		'AddRoleResponse'=>$AddRoleResponse,	
		'createWebTrackingRet'=>$createWebTrackingRet,	
		'addSeedContactResult'=>$addSeedContactResult,			
		's3_create_assetDirResult'=>$s3_create_assetDirResult,		
		'insertOptionOrderResult'=>$insertOptionOrderResult,				
		'insertFormFieldOptionResult'=>$insertFormFieldOptionResult,	
		'insertAssetDirectoryResult'=>$insertAssetDirectoryResult,			
		'MySQL_createAccountResult'=>$MySQL_createAccountResult,			
		'results3'=>$results3,
		'recurringErrorMessage'=>$recurringErrorMessage
	);	

}

function CheckNewAccount($accountTicket, $signUpAccount) {
	$AccountRequest   = array
	(
		"Credentials" => array
		(
			"Ticket" => $accountTicket        
		),		
		"SearchString" => $signUpAccount,
        "Status" => 255,
        "MaxRows" => 10000,
        "SortExpression" => "",
        "StartRowIndex" =>0,
	);
	$AccountResponse = callService("accountservice/GetAccount", $AccountRequest);
	$ErrorCode = $AccountResponse->{"Result"}->{"ErrorCode"};
	$cnt = 0;
	if ($ErrorCode == "") {
		$errorMessage = '';		
		$AccountList = $AccountResponse->{"AccountList"};
		if ($AccountList) {
			$cnt = sizeof($AccountList);
		}
		if ($cnt == 0) {
			$success = true;
		} else {
			$success = false;
		}
	} else {
		$errorMessage = "AccountResponse ERROR : <br> ErrorMessage -> ".$AccountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$AccountResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}
	return array(
		'success'=>$success, 
		'accountTicket' => $accountTicket,
		'signUpAccount' => $signUpAccount,
		'errorMessage' => $errorMessage,
		'cnt' => $cnt
	);
}


function convert_to_server_date($date, $userTimeZone = 'America/Los_Angeles', $serverTimeZone = 'UTC', $format = 'Y-m-d H:i:s')
{
    $dateTime3 = '';
	if ($serverTimeZone == '') {
		return $dateTime3;
	}
	try {
		$dateTime = new DateTime ($date, new DateTimeZone($userTimeZone));
		//echo "1 dateTime<br>";	var_dump($dateTime);	echo "<br>";
		$dateTime2 = $dateTime->setTimezone(new DateTimeZone($serverTimeZone));
		//echo "2 dateTime2<br>";	var_dump($dateTime2);	echo "<br>";
		$dateTime3 = $dateTime2->format($format);
		//echo "2 dateTime2<br>";	var_dump($dateTime3);	echo "<br>";
	} catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
    return $dateTime3;
}


function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}


function insertAssetDirectory($acctID) {
	$CurrentConfig = davinciSetConfig();
	$dbConfig = $CurrentConfig->dbConfig;
	$dbName = $dbConfig->dbName;
	return insertAssetDirectoryDB($acctID, $dbName);
}

function insertAssetDirectoryDB($acctID, $dbName) {
	$success = true;
	$ErrorMessage = '';	
	$addArray = ["Images","Videos","Audio","Documents","HTML", "AI_JSON_Data"];  //#NPI96
	if($db == null){
		$db = GetPDOMySQL();
	}
	for ($i = 0; $i <= sizeof($addArray)-1; $i++) {
		$sql = "INSERT INTO $dbName.AssetDirectory (id, acctID, name, parent, sort, top, link) VALUES (NULL, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE acctID=?, name=?, parent=?, sort=?, top=?, link=?";
		//echo "$sql<br>";
		 try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($acctID, $addArray[$i], '0', $i+1, $addArray[$i], '', $acctID, $addArray[$i], '0', $i+1, $addArray[$i], ''));
		 } catch(Exception $e){
			$success = false;	
			$ErrorMessage = $e->getMessage();
	     }		
	}
	return array(
		'success'=>$success, 
		'ErrorMessage'=>$ErrorMessage,
	);	
}

function insertFormFieldOption($acctID) {
	$success = true;
	$ErrorMessage = '';
	if ($acctID > 0) {
		if($db == null){
			$db = GetPDOMySQL();
		}
		$sql = "INSERT INTO DV_FormFieldType (accountID, fieldID, fieldType, fieldOption) SELECT $acctID,fieldID,fieldType,fieldOption  FROM DV_FormFieldType WHERE  accountID = 0 ";
		 try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute();
		 } catch(Exception $e){
			$success = false;	
			$ErrorMessage = $e->getMessage();
		 }
	}
	return array(
		'success'=>$success, 
		'ErrorMessage'=>$ErrorMessage,
	);
}

function insertOptionOrder($acctID) {
	$success = true;
	$ErrorMessage = '';
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sql1 = "SELECT * FROM DV_FormFieldType where fieldID in ('State','Country','Gender') and accountID = $acctID" ; 
	//error_log("[insertOptionOrder $acctID: ".date('m.d.Y h:i:s')."] sql1 : $sql1\n", 3, "/efs/log/createSubAccount.log");
	$Stmt = MySQL_Prepare($db,$sql1);		
	$Stmt->execute();
	$affected_rows = $Stmt->rowCount();
	while ($rowForm = $Stmt->fetch()) {
		$fID = $rowForm['ID'] ; 
		$fType = $rowForm['fieldType'] ; 
		$sql = "SELECT * FROM DV_defaultOptionDB0 where fieldType = '$fType'" ; 
		//error_log("[insertOptionOrder $acctID: ".date('m.d.Y h:i:s')."] sql : $sql\n", 3, "/efs/log/createSubAccount.log");
		$Stmt2 = MySQL_Prepare($db,$sql);		
		$Stmt2->execute();

		while ($row1 = $Stmt2->fetch()) {		
			$optext = $row1['optionText'];
			$valtext =  $row1['valueText'];
			$ords = $row1['orders'];
			$inssql = "INSERT INTO DV_OptionOrder (formFieldID,options,val, orders) VALUES ($fID,'$optext','$valtext',$ords) ";
			//error_log("[insertOptionOrder $acctID: ".date('m.d.Y h:i:s')."] inssql : $inssql\n", 3, "/efs/log/createSubAccount.log");
			//echo "<br> ins sql = ". $inssql; 
			 try {
				$Stmt1 = MySQL_Prepare($db,$inssql);
				$Stmt1->execute();
			 } catch(Exception $e){
				$success = false;	
				$ErrorMessage .= " ***** ERROR**** sql[" .$inssql. "] e:" . $e->getMessage();
				//error_log("[insertOptionOrder $acctID: ".date('m.d.Y h:i:s')."] ErrorMessage : $ErrorMessage\n", 3, "/efs/log/createSubAccount.log");
			 }				
		}//end foreach row2		
	}			
	//echo "<br> ErrorMessage = ".$ErrorMessage; 
	return array(
		'success'=>$success, 
		'ErrorMessage'=>$ErrorMessage,
	);			
}


function  s3_create_assetDir($dbName){    
		$assetDirs = ["Images","Videos","Audio","Documents","HTML", "AI_JSON_Data"]  ; 		
		$errorMessage = ""; 
		$success = true; 
		foreach ($assetDirs as $assetDir) {
			//echo  "assetDir = " . $assetDir  . "<br>" ; 
			$resultDir = s3_put_asset("" ,"",$dbName,$assetDir);		
			if($resultDir == ""){
				$errorMessage = $errorMessage . $assetDir . "," ; 
				$success = false; 
			}
		}
		//phpinfo();
		return array(
			'success'=>$success, 
			'errorMessage'=>$errorMessage,
		);
}

function getGoalList($userTicket){
	$GoalListRequest   = array(		
		"Credentials" => array(
			"Ticket" => $userTicket        
		)
	);
	$rows = array();
	$GoalListResponse = callService("configurationservice/GetGoalList", $GoalListRequest);
	$ErrorCode = $GoalListResponse->{"Result"}->{"ErrorCode"};	

	logDebug(" ErrorCode = " . $ErrorCode  );
	if ($ErrorCode == "") {
		$GoalList = $GoalListResponse->{"GoalList"};	
		logDebug(" GoalListResponse = " . json_encode($GoalList)  );
		
		foreach ($GoalList as $x){			
			$goal = $x->{'Name'} ; 
			$goalID = $x->{'ID'} ; 			
			$group = array(					
				'goalID'=>$goalID,
				'goal'=>$goal,
			);					
			$rows[] = $group;			
		}
		$success = true;
		$errorMessage = "";
	} else {
		$errorMessage = "GoalListResponse ERROR : <br> ErrorMessage -> ".$GoalListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GoalListResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}

	logDebug(" GoalListResponse rows = " . json_encode($rows)  );
	return array('success'=>$success, 'rows' => $rows, 'errorMessage' => $errorMessage, 'logDebug' => logDebug() );
}

function getGoalListCredentials($Credentials){
	$GoalListRequest   = array(		
		"Credentials" => $Credentials
	);
	$rows = array();
	$GoalListResponse = callServiceCredential("configurationservice/GetGoalList", $GoalListRequest);
	$ErrorCode = $GoalListResponse->{"Result"}->{"ErrorCode"};	

	logDebug(" ErrorCode = " . $ErrorCode  );
	if ($ErrorCode == "") {
		$GoalList = $GoalListResponse->{"GoalList"};	
		logDebug(" GoalListResponse = " . json_encode($GoalList)  );
		
		foreach ($GoalList as $x){			
			$goal = $x->{'Name'} ; 
			$goalID = $x->{'ID'} ; 			
			$group = array(					
				'goalID'=>$goalID,
				'goal'=>$goal,
			);					
			$rows[] = $group;			
		}
		$success = true;
		$errorMessage = "";
	} else {
		$errorMessage = "GoalListResponse ERROR : <br> ErrorMessage -> ".$GoalListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$GoalListResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}
	return array('success'=>$success, 'rows' => $rows, 'errorMessage' => $errorMessage, 'logDebug' => logDebug() );
}

function checkDomainCredentials($Credentials,$domain) {
	$DomainListRequest   = array
	(
		
		"Credentials" => $Credentials
	);
	$DomainListResponse = callServiceCredential("configurationservice/GetDomainList", $DomainListRequest);
	$ErrorCode = $DomainListResponse->{"Result"}->{"ErrorCode"};
	if ($TEST == 't') {
		echo "<br>DomainListResponse<br>";
		var_dump($DomainListResponse);
		echo "<br>";
	}
	$rows = array();
	
	$domainID = -5;
	
	if ($ErrorCode == "") {
		$DomainList = $DomainListResponse->{"DomainList"};	
		$success = true;
		foreach ($DomainList as $x){
			$domainName = $x->{'Name'};
			
			if (strtolower($domainName) == strtolower($domain)) {
				$domainID = $x->{'ID'};
				break;
			}
		}
		$errorMessage = '';
	} else {
		$success = false;
		$errorMessage = "DomainListResponse ERROR : <br> ErrorMessage -> ".$DomainListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$DomainListResponse->{"Result"}->{"ExceptionMessage"};		
		
	}
	return array('success'=>$success, 'domainList' => $DomainList, 'domainID'=> $domainID, 'errorMessage' => $errorMessage );
}


function getStudioDomainListVue($TEST, $Credentials, $AccountID, $studioDomain)
{
	$logger = new FileLogger("globalVue.log");  
	$DomainListRequest   = array	(		
		"Credentials" => $Credentials,
	);

	$logger->log("fn[getStudioDomainListVue] :: DomainListRequest = " . json_encode($DomainListRequest) );

	$DomainListResponse = callServiceCredential("configurationservice/GetDomainList", $DomainListRequest);
	$logger->log("fn[getStudioDomainListVue] :: DomainListResponse = " . json_encode($DomainListResponse) );

	$ErrorCode = $DomainListResponse->{"Result"}->{"ErrorCode"};
	$logger->log("fn[getStudioDomainListVue] :: ErrorCode = " . $ErrorCode );

	if ($TEST == 't') {
		echo "<br>DomainListResponse<br>";
		var_dump($DomainListResponse);
		echo "<br>";
	}
	$rows = array();
	$defaultDomain = "$AccountID.$studioDomain";	
	if ($ErrorCode == "") {
		$DomainList = $DomainListResponse->{"DomainList"};	

		foreach ($DomainList as $x){
			$domain = $x->{'Name'};
			$DnsStatus = $x->{'DnsStatus'};
			if ($DnsStatus == '0') {
				$notready = false;
				$domainname = $domain;
				$domainvalue = $domain;
			} else {
				$domainname = $domain." (Not Ready: Point this domain to MindFire using the instructions link below.)";
				$notready = false;	
				$domainvalue = $domain;				
			}
			$group = array(					
				'realdomain'=>$domain,
				'domainvalue'=>$domainvalue,
				'domainname'=>$domainname,
				'notready'=>$notready,
			);					
			$rows[] = $group;			
		}
		$success = true;
		$errorMessage = "";
	} else {
		$errorMessage = "DomainListResponse ERROR : <br> ErrorMessage -> ".$DomainListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$DomainListResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}
	
	$logger->log("fn[getStudioDomainListVue] :: success = " . $success );

//	$MicrositeResult = []; 
//	$MicrositeResult = getMicrositeUrls($userTicket);

	$MicrositeUrlsRequest   = array(		
		"Credentials" => $Credentials,
	);

	$logger->log("fn[getStudioDomainListVue] :: MicrositeUrlsRequest = " . json_encode($MicrositeUrlsRequest) );

	$MicrositeUrlsResponse = callServiceCredential("configurationservice/GetMicrositeUrls", $MicrositeUrlsRequest);
	$logger->log("fn[getStudioDomainListVue] :: MicrositeUrlsResponse = " . json_encode($MicrositeUrlsResponse) );

	//	$MicrositeResult['MicrositeUrlsRequest'] = $MicrositeUrlsRequest;
	//	$MicrositeResult['MicrositeUrlsResponse'] = $MicrositeUrlsResponse;

	
	$ErrorCode = $MicrositeUrlsResponse->{"Result"}->{"ErrorCode"};
	$logger->log("fn[getStudioDomainListVue] :: ErrorCode = " . $ErrorCode );

	$MicrositeUrls = $MicrositeUrlsResponse->{"MicrositeUrls"};
	$logger->log("fn[getStudioDomainListVue] :: MicrositeUrls = " . json_encode($MicrositeUrls) );
	$logger->log("fn[getStudioDomainListVue] :: errorMessage = " . $errorMessage );
	
	return array('success'=>$success, 'rows' => $rows, 'MicrositeUrls' => $MicrositeUrls, 'errorMessage' => $errorMessage);
}





function getMicrositeUrls($ticket) 
{	
	$MicrositeUrlsRequest   = array
	(		
		"Credentials" => array
		(
			"Ticket" => $ticket        
		)
	);
	$MicrositeUrlsResponse = callService("configurationservice/GetMicrositeUrls", $MicrositeUrlsRequest);
	$ErrorCode = $MicrositeUrlsResponse->{"Result"}->{"ErrorCode"};
	
	$result['MicrositeUrlsRequest'] = $MicrositeUrlsRequest;
	$result['MicrositeUrlsResponse'] = $MicrositeUrlsResponse;
	return $result;
}	

function getStudioDomainList($TEST, $userTicket, $mode, $AccountID, $studioDomain)
{
	$DomainListRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $userTicket        
		)
	);
	$DomainListResponse = callService("configurationservice/GetDomainList", $DomainListRequest);
	$ErrorCode = $DomainListResponse->{"Result"}->{"ErrorCode"};
	if ($TEST == 't') {
		echo "<br>DomainListResponse<br>";
		var_dump($DomainListResponse);
		echo "<br>";
	}
	$rows = array();
	$defaultDomain = "$AccountID.$studioDomain";
	if ($mode == 'ebook') {
		/*
		$group = array(					
			'realdomain'=>$defaultDomain,
			'domainvalue'=>$defaultDomain,
			'domainname'=>'Purchase domain (coming soon)',
			'notready'=>true,
		);					
		$rows[] = $group;
		*/
	}
	if ($ErrorCode == "") {
		$DomainList = $DomainListResponse->{"DomainList"};	

		foreach ($DomainList as $x){
			$domain = $x->{'Name'};
			$DnsStatus = $x->{'DnsStatus'};
			if ($DnsStatus == '0') {
				$notready = false;
				$domainname = $domain;
				$domainvalue = $domain;
			} else {
				$domainname = $domain." (Not Ready: Point this domain to MindFire using the instructions link below.)";
				//#1040 [CL] Allow NOT READY domains to be used in Microsites
				//$notready = true;				
				//$domainvalue = $defaultDomain;
					$notready = false;	
					$domainvalue = $domain;
				
			}
			$group = array(					
				'realdomain'=>$domain,
				'domainvalue'=>$domainvalue,
				'domainname'=>$domainname,
				'notready'=>$notready,
			);					
			$rows[] = $group;			
		}
		$success = true;
		$errorMessage = "";
	} else {
		$errorMessage = "DomainListResponse ERROR : <br> ErrorMessage -> ".$DomainListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$DomainListResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}
	
	$result = getMicrositeUrls($userTicket);
	$MicrositeUrls = $result['MicrositeUrlsResponse']->MicrositeUrls;
	
	return array('success'=>$success, 'rows' => $rows, 'MicrositeUrls' => $MicrositeUrls, 'errorMessage' => $errorMessage);
}


function getAudienceList($ACCOUNTID,$type='') {	
	
	$loggerSub = new FileLogger("getAuduenceData.log");  

	if($db == null){
        $db = GetPDOMySQL();
    }
	if($type == ''){ // select Only removeFlag <> 'Y' 
		$sql = "SELECT * FROM DV_AudienceList where acctID=$ACCOUNTID and removeFlag <> 'Y' " ; 

	}else if($type == 'csv'){ //ISSUE1225 // only import 
		$sql = "SELECT * FROM DV_AudienceList where acctID=$ACCOUNTID and LISTIDHASH = 'imported' and removeFlag <> 'Y'  " ; 
		
	}else if($type == 'crm'){ //#1823 add opens/leads option without BA campaign.	
		$sql = "SELECT * FROM DV_AudienceList where acctID=$ACCOUNTID and removeFlag <> 'Y' order by LISTIDHASH, LISTNAME ASC" ; 
		
	}else if($type == '1823'){ //#1823 add opens/leads option without BA campaign.
		$sql = "SELECT * FROM DV_AudienceList where acctID=$ACCOUNTID and removeFlag <> 'Y' and LISTIDHASH = 'OP' ".
		"and LISTNAME NOT IN  (SELECT campaignName FROM DV_CampaignList WHERE acctID=$ACCOUNTID and campaignType='BuildAwareness') ".
		"order by LISTIDHASH, LISTNAME ASC " ; 
	
	}else{ //ISSUE989 All audienceList 
		$sql = "SELECT * FROM DV_AudienceList where acctID=$ACCOUNTID" ; 
	}	
	$loggerSub->log("fn[getAudienceList] :: sql = $sql");
	$stmt = $db->query($sql);  

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($records)>0) {
        return $records;
    }else{
        return [];
    }  	
	
}

function getEbookDomainList($TEST, $ACCOUNTID) {
	$DomainList[] = array('domain'=>"",'campaignName'=>"",'campaignID'=>"",'campaignKey'=>"",'campaignShow'=>"Not Required");
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		
		$Stmt = MySQL_Prepare($db,"SELECT DV_CampaignList.campaignID,DV_CampaignList.campaignName,DV_Document_PromoteEbook.data FROM DV_CampaignList, DV_Document_PromoteEbook WHERE DV_CampaignList.acctID=DV_Document_PromoteEbook.acctID AND DV_CampaignList.campaignID=DV_Document_PromoteEbook.ID and DV_CampaignList.acctID=? and DV_CampaignList.campaignType='PromoteEbook' and (DV_CampaignList.campaignStatus='Active' or DV_CampaignList.campaignStatus='Start') and DV_CampaignList.publishDate IS NOT NULL");

		$Stmt->execute(array($ACCOUNTID));
		$affected_rows = $Stmt->rowCount();
		$found = false;
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);
			$campaignID = $row['campaignID'] ; 
			$campaignName = $row['campaignName'] ; 
			$domain = $docData['LANDINGPAGE-DOMAIN'];
			$campaignShow = "$campaignName ($domain)";
			$campaignKey = $campaignID;
			
			$group = array(					
				'domain'=>$domain,
				'campaignName'=>$campaignName,
				'campaignID'=>$campaignID,
				'campaignKey'=>$campaignKey,
				'campaignShow'=>$campaignShow				
			);					
			$DomainList[] = $group;
		}		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $DomainList;
}

function getEbookCampaignList($TEST, $ACCOUNTID, $landingPagesCampaign, $landingPagesCampaignName, $POSTURL, $caller='PromoteBlog', $skipCampaignID=null) {
	//$logger = new FileLogger("getEbookCampaignList.log");
	$CampaignList = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$found = false;
		$listBlueprint = array(
			array(
				'blueprint'=> 'PromoteEbook',
				'tableName'=> 'DV_Document_PromoteEbook'
			),
			array(
				'blueprint'=> 'CL2',
				'tableName'=> 'DV_Document_CL2'
			),
			array(
				'blueprint'=> 'CL21',
				'tableName'=> 'DV_Document_CL21'
			),
			array(
				'blueprint'=> 'AT2',
				'tableName'=> 'DV_Document_AT2'
			),
			array(
				'blueprint'=> 'AT21',
				'tableName'=> 'DV_Document_AT21'
			)
		);
		if ($caller=='PromoteBlog' || $caller=='CL21' || $caller=='AT2' || $caller=='AT21' || $caller=='CRM') {
			$listBlueprint[] = array(
				'blueprint'=> 'PQR',
				'tableName'=> 'DV_Document_PQR'
			);
		}
		$sqlSkipCampaignID = '';
		if (!empty($skipCampaignID)) {
			$sqlSkipCampaignID = "AND DV_CampaignList.campaignID <> '$skipCampaignID'";
		}
		//$logger->log("caller: $caller , skipCampaignID: $skipCampaignID , listBlueprint: ".json_encode($listBlueprint));
		for($i=0; $i<count($listBlueprint); $i++){
			$blueprint = $listBlueprint[$i]['blueprint'];
			$tableName = $listBlueprint[$i]['tableName'];
			//$logger->log("SQL: SELECT DV_CampaignList.campaignID,DV_CampaignList.campaignName,DV_CampaignList.campaignType,$tableName.data FROM DV_CampaignList, $tableName WHERE DV_CampaignList.acctID=$tableName.acctID AND DV_CampaignList.campaignID=$tableName.ID and DV_CampaignList.acctID=? and DV_CampaignList.campaignType='$blueprint' and (DV_CampaignList.campaignStatus='Active' or DV_CampaignList.campaignStatus='Start') and DV_CampaignList.publishDate IS NOT NULL $sqlSkipCampaignID");
			$Stmt = MySQL_Prepare($db,"SELECT DV_CampaignList.campaignID,DV_CampaignList.campaignName,DV_CampaignList.campaignType,$tableName.data FROM DV_CampaignList, $tableName WHERE DV_CampaignList.acctID=$tableName.acctID AND DV_CampaignList.campaignID=$tableName.ID and DV_CampaignList.acctID=? and DV_CampaignList.campaignType='$blueprint' and (DV_CampaignList.campaignStatus='Active' or DV_CampaignList.campaignStatus='Start') and DV_CampaignList.publishDate IS NOT NULL $sqlSkipCampaignID");
			$Stmt->execute(array($ACCOUNTID));
			$affected_rows = $Stmt->rowCount();
			
			while ($row = $Stmt->fetch()) {
				$docData = json_decode($row['data'],true);
				$domain = $docData['LANDINGPAGE-DOMAIN'];
				$campaignName = $row['campaignName']; 
				$campaignID = $row['campaignID'];
				$campaignShow = "$campaignName ($domain)";
				$campaignKey = $campaignID;
				$campaignType = $row['campaignType']; 
				$fbImage = $docData['FACEBOOK-HERO-IMAGE'];
				$fbTitle = $docData['FACEBOOK-TITLE-TEXT'];
				$fbDescription = $docData['FACEBOOK-DESCRIPTION-TEXT'];
				$fbDomain = (!empty($docData['FACEBOOK-FULLDOMAIN'])) ? $docData['FACEBOOK-FULLDOMAIN'] : GetCurrentConfig()->davinciDomain;
				if ($TEST=='t') {
					echo "domain : $domain, campaignName : $campaignName<br>";
				}
				if ($landingPagesCampaign == $campaignID) {
					$found = true;
				}
				$group = array(					
					'domain'=>$domain,
					'domainPQR'=>$domain.'/##purl##',
					'campaignName'=>$campaignName,
					'campaignID'=>$campaignID,
					'campaignKey'=>$campaignKey,
					'campaignShow'=>$campaignShow,
					'campaignType'=>$campaignType,
					'userURL'=>'N',
					'userGroup'=>'Lead Capture Campaigns',
					'fbImage'=>$fbImage,
					'fbTitle'=>$fbTitle,
					'fbDescription'=>$fbDescription,
					'fbDomain'=>$fbDomain,
					'displayShareButton'=>'table'
				);					
				$CampaignList[] = $group;
			}			
		}		
		if ($found) {

		} else {
			if ($landingPagesCampaign == '') {
				$POSTURL = str_replace('/##purl##', '', $POSTURL);
				$landingPagesCampaign = md5($POSTURL); 
			}
			if ($landingPagesCampaignName == '') {
				$landingPagesCampaignName = $POSTURL;
			}
			$domain = $landingPagesCampaignName;
			$campaignName = $landingPagesCampaignName; 
			$campaignID = $landingPagesCampaign;
			$campaignShow = "$campaignName";
			$campaignKey = $campaignID;
			$group = array(					
				'domain'=>$domain,
				'domainPQR'=>$domain,
				'campaignName'=>$campaignName,
				'campaignID'=>$campaignID,
				'campaignKey'=>$campaignKey,
				'campaignShow'=>$campaignShow,
				'campaignType'=>"externalLink",
				'userURL'=>'Y',
				'userGroup'=>"Destinations You've Used Before",
				'fbImage'=>'',
				'fbTitle'=>'',
				'fbDescription'=>'',
				'fbDomain'=>'',
				'displayShareButton'=>'none'
			);					
			$CampaignList[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage(); 
		$affected_rows = -1;	
	}
	return $CampaignList;
}


function getEbookCampaignListVue($type, $ACCOUNTID, $landingPagesCampaign, $landingPagesCampaignName, $POSTURL) {
	$logger = new FileLogger("getAcctDomainListVue.log");  

	$logger->log("getEbookCampaignListVue ()   type = ". $type );

	$CampaignSingleList = array();
	$CampaignMultiList = array();
	$clList = array();
	$exList = array();
	try {  		
		$found = false;
		$cnt = 0; 
		for($i=1;$i<=6;$i++){
			if ($i == 1) {
				$blueprint = 'PromoteEbook';
				$tableName = 'DV_Document_PromoteEbook';
			} else if ($i == 2) {
				$blueprint = 'CL2';
				$tableName = 'DV_Document_CL2';
			} else if ($i == 3) {
				$blueprint = 'CL21';
				$tableName = 'DV_Document_CL21';
			} else if ($i == 4) {
				$blueprint = 'AT2';
				$tableName = 'DV_Document_AT2';				
			} else if ($i == 5) {
				$blueprint = 'AT21';
				$tableName = 'DV_Document_AT21';					
			} else {
				$blueprint = 'CL30';
				$tableName = 'DV_Document_CL30';
			}
			$sql = "SELECT DV_CampaignList.campaignID,DV_CampaignList.campaignName,DV_CampaignList.campaignType,$tableName.data FROM DV_CampaignList, $tableName WHERE DV_CampaignList.acctID=$tableName.acctID AND DV_CampaignList.campaignID=$tableName.ID and DV_CampaignList.acctID=? and DV_CampaignList.campaignType='$blueprint' and (DV_CampaignList.campaignStatus='Active' or DV_CampaignList.campaignStatus='Start') and DV_CampaignList.publishDate IS NOT NULL" ; 

			$logger->log("SQL :: getEbookCampaignListVue () = ". $sql );

			$Stmt = MySQL_Prepare($db,$sql);
			$Stmt->execute(array($ACCOUNTID));
			$affected_rows = $Stmt->rowCount();

			$logger->log("getEbookCampaignListVue () rows count = ". $affected_rows );

			while ($row = $Stmt->fetch()) {
				$docData = json_decode($row['data'],true);
				$domain = $docData['LANDINGPAGE-DOMAIN'];
				$campaignName = $row['campaignName']; 
				$campaignID = $row['campaignID'];
				$campaignShow = "$campaignName ($domain)";
				$campaignKey = $campaignID;
				$campaignType = $row['campaignType']; 
				$fbImage = $docData['FACEBOOK-HERO-IMAGE'];
				$fbTitle = $docData['FACEBOOK-TITLE-TEXT'];
				$fbDescription = $docData['FACEBOOK-DESCRIPTION-TEXT'];
				$fbDomain = (!empty($docData['FACEBOOK-FULLDOMAIN'])) ? $docData['FACEBOOK-FULLDOMAIN'] : GetCurrentConfig()->davinciDomain;

				if ($landingPagesCampaign == $campaignID) {
					$found = true;
				}
				$group = array(					
					'domain'=>$domain,
					'domainPQR'=>$domain.'/##purl##',
					'campaignName'=>$campaignName,
					'campaignID'=>$campaignID,
					'campaignKey'=>$campaignKey,
					'campaignShow'=>$campaignShow,
					'campaignType'=>$campaignType,
					'userURL'=>'N',
					'userGroup'=>'Lead Capture Campaigns',
					'fbImage'=>$fbImage,
					'fbTitle'=>$fbTitle,
					'fbDescription'=>$fbDescription,
					'fbDomain'=>$fbDomain,
					'displayShareButton'=>'table'
				);			

				if($cnt==0)	$CampaignSingleList[] = array('header'=>'Lead Capture Campaigns'); 
				$CampaignSingleList[] = $group;
				$clList[] = $group; 
				$cnt++; 
			}

			$logger->log("clList $blueprint =  " . json_encode($clList) );

		} // end for group cl campaign. 	

		if (!$found) {

			$logger->log("not found = " . json_encode ($group) );

			if ($landingPagesCampaign == '') {
				$POSTURL = str_replace('/##purl##', '', $POSTURL);
				$landingPagesCampaign = md5($POSTURL); 
			}
			if ($landingPagesCampaignName == '') {
				$landingPagesCampaignName = $POSTURL;
			}
			$domain = $landingPagesCampaignName;
			$campaignName = $landingPagesCampaignName; 
			$campaignID = $landingPagesCampaign;
			$campaignShow = "$campaignName";
			$campaignKey = $campaignID;
			$group = array(					
				'domain'=>$domain,
				'domainPQR'=>$domain,
				'campaignName'=>$campaignName,
				'campaignID'=>$campaignID,
				'campaignKey'=>$campaignKey,
				'campaignShow'=>$campaignShow,
				'campaignType'=>"externalLink",
				'userURL'=>'Y',
				'userGroup'=>"Destinations You've Used Before",
				'fbImage'=>'',
				'fbTitle'=>'',
				'fbDescription'=>'',
				'fbDomain'=>'',
				'displayShareButton'=>'none'
			);		

			if($domain !=""){			
				$CampaignSingleList[] = array('header'=>"Destinations You've Used Before"); 
				$CampaignSingleList[] = $group;
				$exList[] = $group; 
				$logger->log(" Ex Group = " . json_encode ($exList) );
			}
		}

		$clList = arraySortValue($clList, 'campaignName', SORT_ASC);
		$exList = arraySortValue($exList, 'campaignName', SORT_ASC);

		$CampaignMultiList[] = array('group'=>'Lead Capture Campaigns','items'=>$clList); 
		$CampaignMultiList[] = array('group'=>'Destinations You\'ve Used Before','items'=>$exList); 

		$logger->log("RRR CampaignSingleList =  " . json_encode($CampaignSingleList) );
		$logger->log("RRR CampaignMultiList =  " . json_encode($CampaignMultiList) );


	}catch(exception $e) {
		$errorMessage = $e->getMessage(); 
		$affected_rows = -1;	
	}
	$CampaignList = $CampaignSingleList; 
	if($type=='multi'){
		$CampaignList = $CampaignMultiList; 
	}

	$logger->log("RRR CampaignList =  " . json_encode($CampaignList) );

	return $CampaignList;
}


function getProductType() {
	$ProductType = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Preload_Type order by ProductName");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$code = $row['code'];
			$ProductName = $row['ProductName'];
			$group = array(					
				'code'=>$code,
				'ProductName'=>$ProductName
			);					
			$ProductType[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $ProductType;
}

function getDVTimezone() {
	$timezone = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Timezone order by timezoneOrder, timezoneDV");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$timezoneDV = $row['timezoneDV'];
			$timezoneStudio = $row['timezoneStudio'];
			$timezoneMoment = $row['timezoneMoment'];
			$timezoneOrder = $row['timezoneOrder'];
			$timezoneOffset = $row['timezoneOffset'];
			$group = array(					
				'timezoneDV'=>$timezoneDV,
				'timezoneStudio'=>$timezoneStudio,
				'timezoneMoment'=>$timezoneMoment,
				'timezoneOrder'=>$timezoneOrder,
				'timezoneOffset'=>$timezoneOffset,
			);					
			$timezone[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $timezone;
}

function getDVdomain($acctID) {
	$domain = array();
	$format = "m/d/Y h:i A";
	$usersTimezone = getUserTimeZone($acctID);
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from AWS_Certificate_Request where accountID=$acctID  and DeleteFlag<>'Y' order by domainsRequest");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			
			
			
			$AWS_Certificate_Id = $row['AWS_Certificate_Id'];
			$accountID = $row['accountID'];
			$requestBy = $row['requestBy'];
			$firstname = $row['firstname'];
			$lastname = $row['lastname'];
			$domainType = $row['domainType'];
			$domainsRequest = $row['domainsRequest'];
			$domainsSSL = $row['domainsSSL'];
			// $CertificateStatus = $row['CertificateStatus'];
			$CertificateStatus = $row['sslLiveStatus'];
			$CertificateStatusLower = strtoupper($CertificateStatus);
			if ($CertificateStatus == 'SUCCESS')  {
				$CertificateStatusShow = 'Success';
			} else if ($CertificateStatus == 'PENDING_VALIDATION')  {
				$CertificateStatusShow = 'Pending';
			} else if ($CertificateStatus == 'EXPIRED')  {
				$CertificateStatusShow = 'Expired';
			} else if ($CertificateStatus == 'UNVERIFIED')  {
				$CertificateStatusShow = 'Unverified';
			} else {
				$CertificateStatusShow = $CertificateStatus;
			}
			
			
			
			$SSLDomain = $row['SSLDomain'];
			$createDate = $row['createDate'];
			$LastEditDate = $row['LastEditDate'];
			
			$createDateUS = getReportDetailDateFormat($createDate, $usersTimezone, $format);
			$LastEditDateUS = getReportDetailDateFormat($LastEditDate, $usersTimezone, $format);
			
			
			
			if ($CertificateStatus == 'SUCCESS') {
				
			} else {
				$LastEditDate = '';	
				$LastEditDateUS = '';
			}
			
			$group = array(					
				'AWS_Certificate_Id'=>$AWS_Certificate_Id,
				'accountID'=>$accountID,
				'requestBy'=>$requestBy,
				'firstname'=>$firstname,
				'lastname'=>$lastname,
				'domainType'=>$domainType,
				'domainsRequest'=>$domainsRequest,
				'domainsSSL'=>$domainsSSL,
				'CertificateStatus'=>$CertificateStatus,
				'CertificateStatusShow'=>$CertificateStatusShow,
				'SSLDomain'=>$SSLDomain,
				'createDate'=>$createDate,
				'LastEditDate'=>$LastEditDate,
				'createDateUS'=>$createDateUS,
				'LastEditDateUS'=>$LastEditDateUS,
			);					
			$domain[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $domain;
}

function getDVemail($acctID) {
	$domain = array();
	$format = "m/d/Y h:i A";
	$usersTimezone = getUserTimeZone($acctID);
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from AWS_Email_Request where accountID=$acctID and DeleteFlag<>'Y' order by domainsRequest");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			
			
			$ID = $row['ID'];
			$accountID = $row['accountID'];
			$requestBy = $row['requestBy'];
			$firstname = $row['firstname'];
			$lastname = $row['lastname'];
			$domainsRequest = $row['domainsRequest'];
			$createDate = $row['createDate'];
			$LastEditDate = $row['LastEditDate'];
			$CnameContent = $row['CnameContent'];
			$status = $row['status'];
			
			$statusLower = strtoupper($status);
			if ($status == 'SUCCESS')  {
				$statusShow = 'Success';
			} else if ($status == 'PENDING_VALIDATION')  {
				$statusShow = 'Pending';
			} else if ($status == 'EXPIRED')  {
				$statusShow = 'Expired';
			} else if ($status == 'FAILED')  {
				$statusShow = 'Failed';
			} else if ($status == 'NOT FOUND')  {
				$statusShow = 'Not Found';
			} else {
				$statusShow = $status;
			}
			
			$createDate = $row['createDate'];
			$LastEditDate = $row['LastEditDate'];
			
			$createDateUS = getReportDetailDateFormat($createDate, $usersTimezone, $format);
			$LastEditDateUS = getReportDetailDateFormat($LastEditDate, $usersTimezone, $format);
			
			
			
			if ($status == 'SUCCESS') {
				
			} else {
				$LastEditDate = '';	
				$LastEditDateUS = '';
			}
			
			$group = array(					
				'ID'=>$ID,
				'accountID'=>$accountID,
				'requestBy'=>$requestBy,
				'firstname'=>$firstname,
				'lastname'=>$lastname,
				'domainsRequest'=>$domainsRequest,
				'status'=>$status,
				'statusShow'=>$statusShow,
				'createDate'=>$createDate,
				'LastEditDate'=>$LastEditDate,
				'createDateUS'=>$createDateUS,
				'LastEditDateUS'=>$LastEditDateUS,
			);					
			$domain[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $domain;
}


function getDelinquentAccount() {
	$DelinquentAccount = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_UserInfo where paidAccount='Y' order by studioAccountName");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$AccountName = $row['studioAccountName'];
			$code = $row['acctID'];
			$group = array(					
				'AccountName'=>$AccountName,
				'code'=>$code,
			);					
			$DelinquentAccount[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $DelinquentAccount;
}

function getReinstateAccount() {
	$ReinstateAccount = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_UserInfo where userStatus = 'Cancelled' order by studioAccountName");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$AccountName = $row['studioAccountName'];
			$code = $row['acctID'];
			$group = array(					
				'AccountName'=>$AccountName,
				'code'=>$code,
			);					
			$ReinstateAccount[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $ReinstateAccount;
}

function setDelinquentAccount($billAccount) {
	try {  
		if ($billAccount > 0) {
			if($db == null){
				$db = GetPDOMySQL();
			}	
			$stmt = $db->prepare("UPDATE DV_UserInfo set userStatus='' where acctID=?");
			$stmt->execute(array($billAccount));
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return true;
}

function getDVSubAccount($TEST, $AccountID) {
	$SubAccount = array();
	$errorMessage = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT distinct t1.acctID acctID,t1.parentID,t1.`data` AccountData ,t2.`data` UserData,DATE_FORMAT(t3.signupDatetime, '%c/%e/%Y') signupDatetime FROM DV_Account t1 LEFT JOIN DV_UserInfo t2 ON (t1.acctID = t2.acctID) LEFT JOIN DV_signup t3 ON (t1.acctID = t3.studioAccountID) WHERE t1.parentID=?  and (t1.acctID <> -1 and t1.acctID <> 0) and t2.userStatus<>'Cancelled'");
		$Stmt->execute(array($AccountID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$acctID = $row['acctID'];
			$signupDatetime = $row['signupDatetime'];
			$AccountData = json_decode($row['AccountData'],true);
			$UserData = json_decode($row['UserData'],true);	
			$defCompanyLogo = $UserData['defCompanyLogo'];
			$defCompanyName = $UserData['defCompanyName'];
			$defCompanyAddress = $UserData['defCompanyAddress'];
			$defCompanyPhone = $UserData['defCompanyPhone'];
			$defCompanyWebsite = $UserData['defCompanyWebsite'];
			$defCompanyIcon = $UserData['defCompanyIcon'];
			$defButtonColor = $UserData['defButtonColor'];
			$defTimezone = $UserData['defTimezone'];
			$group = array(					
				'acctID'=>$acctID,
				'signupDatetime'=>$signupDatetime,	
				'defCompanyLogo'=>$defCompanyLogo,
				'defCompanyName'=>$defCompanyName,
				'defCompanyAddress'=>$defCompanyAddress,
				'defCompanyPhone'=>$defCompanyPhone,
				'defCompanyWebsite'=>$defCompanyWebsite,
				'defCompanyIcon'=>$defCompanyIcon,
				'defButtonColor'=>$defButtonColor,
				'defTimezone'=>$defTimezone,
				
			);					
			$SubAccount[] = $group;
			
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	if ($TEST == 't') {
		echo "errorMessage : $errorMessage<br>";
		echo "affected_rows : $affected_rows<br>";

	}
	$result['DVSubAccount'] = $SubAccount;
	$result['DVSubAccountCount'] = $affected_rows;
	return $result;
}

function getDVSubAccountCount($TEST, $AccountID) {
	$SubAccount = 1;
	$errorMessage = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Account WHERE acctID=?");
		$Stmt->execute(array($AccountID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$parentID = $row['parentID'];
			if ( ($parentID) && (($parentID != '') || ($parentID != '0')) ) {
				$SubAccount = 0;
			}
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	if ($TEST == 't') {
		echo "errorMessage : $errorMessage<br>";
		echo "affected_rows : $affected_rows<br>";

	}
	return $SubAccount;
}

function getRenewalOffer($acctID) {
	$RenewalOffer = array();
	$errorMessage = '';
	$DVRenewalOfferCount = -1;
	$DVRenewalOfferSelect = -1;
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_renewal_offers where AccountID=? and (OfferDate <= CURDATE() and ExpirationDate >= CURDATE())");		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();
		$DVRenewalOfferCount = $affected_rows;
		while ($row = $Stmt->fetch()) {
			$OfferID = $row['OfferID'];
			$OfferDate = $row['OfferDate'];
			$ExpirationDate = $row['ExpirationDate'];
			$OfferDescription = $row['OfferDescription'];
			$OfferAmount = $row['OfferAmount'];
			$PayPeriodsNum = $row['PayPeriodsNum'];
			$PayPeriod = $row['PayPeriod'];
			$AffiliateCode = $row['AffiliateCode'];

			$group = array(					
				'OfferID'=>$OfferID,
				'OfferDate'=>$OfferDate,	
				'ExpirationDate'=>$ExpirationDate,
				'OfferDescription'=>$OfferDescription,
				'OfferAmount'=>$OfferAmount,
				'PayPeriodsNum'=>$PayPeriodsNum,
				'PayPeriod'=>$PayPeriod,
				'AffiliateCode'=>$AffiliateCode,
				
			);					
			$RenewalOffer[] = $group;
		}
		$DVRenewalOfferSelect = getRenewalOfferSelect($acctID);
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	if ($TEST == 't') {
		echo "errorMessage : $errorMessage<br>";
		echo "affected_rows : $affected_rows<br>";

	}
	$result['DVRenewalOffer'] = $RenewalOffer;
	$result['DVRenewalOfferError'] = $errorMessage;
	$result['DVRenewalOfferCount'] = $DVRenewalOfferCount;
	$result['DVRenewalOfferSelect'] = $DVRenewalOfferSelect;
	return $result;
}

function getParentID($TEST, $AccountID) {
	$parentID = '';
	$errorMessage = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Account WHERE acctID=?");
		$Stmt->execute(array($AccountID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$parentID = $row['parentID'];
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	if ($TEST == 't') {
		echo "errorMessage : $errorMessage<br>";
		echo "affected_rows : $affected_rows<br>";

	}
	return $parentID;
}

function getDVWhatNew() {
	$DVWhatNew = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from davinci_master.DV_whatNew limit 1");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$DVWhatNew = $row['content'];			
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $DVWhatNew;
}

function getDVBroadcast() {
	$DVBroadcast = '';
	$boardcastTime = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Broadcast limit 1");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$DVBroadcast = $row['content'];			
			$boardcastTime = $row['boardcastTime'];	
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	$result['DVBroadcast'] = trim($DVBroadcast);
	$result['boardcastTime'] = $boardcastTime;
	return $result;
}

function getLoginContent() {
	$loginContent1 = '';
	$loginContent2 = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Config where configName='login' limit 1");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['configData'],true);
			$loginContent1 = $docData['loginContent1'];
			$loginContent2 = $docData['loginContent2'];
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	$result['loginContent1'] = trim($loginContent1);
	$result['loginContent2'] = trim($loginContent2);
	return $result;
}

function getchargeList($TEST, $billAccountCredit) {
	$BillingList = array();
	$chargeTimezone = 'America/Los_Angeles';
	$format = 'm/d/Y';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_UserBilling where accountID=? and stripeResult='Success' and creditAmount<>'Credit'");
		$Stmt->execute(array($billAccountCredit));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			
			$stripeResponse = $row['stripeResponse'];
			if ($TEST == 't') {
				echo "stripeResponse<br>";
				var_dump($stripeResponse);
				echo "<br><br><br>";
			}
			$len = strlen('Stripe\Charge JSON: ');
			$stripeResponse = substr($stripeResponse, $len, strlen($stripeResponse) - $len);

			if ($TEST == 't') {
				echo "stripeResponse<br>";
				var_dump($stripeResponse);
				echo "<br><br><br>";
			}
			$docData = json_decode($stripeResponse,true);		
			if ($TEST == 't') {
				echo "docData<br>";
				var_dump($docData);
				echo "<br><br><br>";
			}
			$token = $docData['id'];
			$chargeDatetime = $row['chargeDatetime'];
			$billDesc = $row['billDesc'];
			
			$chargeDate = getReportDetailDateFormat($chargeDatetime, $chargeTimezone, $format);
			$billAmount = $row['billAmount'];
			$group = array(					
				'token'=>$token,
				'chargeDate'=>$chargeDate,
				'chargeDatetime'=>$chargeDatetime,
				'billAmount'=>$billAmount,
				'billDesc'=>$billDesc,
			);					
			$BillingList[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $BillingList;
}

function getDVBilling() {
	$BillingList = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_UserInfo");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);			
			$token = $docData['stripeToken'];
			//$accountName = $docData['defCompanyName'];
			$accountName = $row['studioAccountName'];
			$accountID = $row['acctID'];
			$group = array(					
				'token'=>$token,
				'accountName'=>$accountName,
				'accountID'=>$accountID,
			);					
			$BillingList[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $BillingList;
}

function substr_startswith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function getStudioUserID($TEST, $ticket, $userEmail) {
	if ($TEST == 't') {
		echo "getStudioUserID $ticket, $userEmail<br>";
	}
	$UserListRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $ticket        
		)
		
	);
	$UserListResponse = callService("userservice/GetUserList", $UserListRequest);
	if ($TEST == 't') {
		echo "getStudioUserID UserListResponse<br>";
		var_dump($UserListResponse);
		echo "<br>";
	}
	$ErrorCode = $UserListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$status = 'Success';
		$errorMessage = '';
		$studioError = '';
		$ShortUserLists = $UserListResponse->{"UserList"};			
		foreach ($ShortUserLists as $ShortUserList){
			$userID = $ShortUserList->{'ID'};
			$Email = $ShortUserList->{'Email'};
			$Mobile = $ShortUserList->{'Mobile'};
			$FirstName = $ShortUserList->{'FirstName'};
			$LastName = $ShortUserList->{'LastName'};
			
			if ($FirstName == '') {
				$name = splitEmail($Email);
				$FirstName = $name[0];
				$LastName = 'Unknown';
			}
			
			if ( strtolower($Email) == strtolower($userEmail) ) {
				break;
			}			
		}
	} else {
		$status = "Fail";
		$errorMessage = "getUserID ErrorMessage : ".$UserListResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$UserListResponse->{"Result"}->{"ExceptionMessage"};
		$userID = "-1";
		$studioError = $UserListResponse->{"Result"}->{"ErrorMessage"};
	}
	
	
	$result['status'] = $status;
	$result['userID'] = $userID;
	$result['Mobile'] = $Mobile;
	$result['FirstName'] = $FirstName;
	$result['LastName'] = $LastName;
	$result['errorMessage'] = $errorMessage;
	$result['studioError'] = $studioError;
	return $result;
}


function getElementPermissions($ACCOUNTID, $ProgramID) {
   if($db == null){
		$db = GetPDOMySQL();
	}
   $sqltxt = "select * from DV_ElementPermissions where acctID = ? and programID = ?";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($ACCOUNTID, $ProgramID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}
	return $row2;
}

function SetElementPermissions($TEST, $userTicket, $ProgramID, $ACCOUNTID){
	//$EMAIL = $_SESSION['EMAIL'];
	$EMAIL = '';
	$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$found = false;
	$errorMessage = '';

	$studioParam = GetStudioAPIParam();
	$POWEREMAIL = $studioParam['email'];
	$POWERPWD = $studioParam['pwd'];
	
	
	$elements = getElementPermissions($ACCOUNTID, $ProgramID);

	while ($row = $Stmt->fetch()) {
		$docData = json_decode($row['data'],true);
		$users = $docData['users'];	
		//$accountName = $docData['defCompanyName'];	
		$accountName = $row['studioAccountName'];
		//$FieldNames = getxmediaAPICouchDB($accountName);
		$FieldNames = getxmediaAPIAcctID($ACCOUNTID);
		$PARTNERGUID = $FieldNames[0];
		$PARTNERPASSWORD = $FieldNames[1]; 
		sleep(2);
		$powerTicket = getTicketCurl($ACCOUNTID, $POWEREMAIL, $POWERPWD, $PARTNERGUID, $PARTNERPASSWORD); 

		foreach( $users as $users ) {		
			$userEmail = $users['email'];	
			$userEmailLower = strtolower($userEmail);
			$mustCall = true;
			
			foreach ($elements as $element){
			   $userName = $element['userName'];
			   $userNameLower = strtolower($userName);
			   if ($userEmailLower == $userNameLower) {
			      $mustCall = false;
			      break;
			   }
			}
			
			if ($TEST == 't2') {
			   echo "userEmail : $userEmail, userName : $userName , mustCall : $mustCall<br>";
			}
			
			if ($mustCall == true) {
   			error_log("SetElementPermissions:".date('m.d.Y h:i:s')." userEmail : $userEmail, EMAIL : $EMAIL \n", 3, "/efs/log/SetElementPermissions.log");
   			if ($TEST == 't') {
   				echo "SetElementPermissions $userEmail, $EMAIL<br>";
   			}
   			if ($userEmail != $EMAIL) {
   				$StudioUser = getStudioUserID($TEST, $userTicket, $userEmail);
   				error_log("SetElementPermissions:".date('m.d.Y h:i:s')." StudioUser : ".json_encode($StudioUser)." \n", 3, "/efs/log/SetElementPermissions.log");
   				$userID = $StudioUser['userID'];
   				if ($userID != "-1") {
   					if ($TEST == 't') {
   						echo "SetElementPermissions userID $userID<br>";
   					}
   					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." userID : $userID \n", 3, "/efs/log/SetElementPermissions.log");
   					$ElementPermissionsRequest   = array
   					(						
   						"Credentials" => array
   						(
   							"Ticket" => $powerTicket        
   						),
   						"ElementPermissions" => [11000, 11001, 11002, 11003],
   						"ProgramID" => $ProgramID,
   						"UserID" => $userID
   
   					);
   					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." ElementPermissionsRequest : ".json_encode($ElementPermissionsRequest)." \n", 3, "/efs/log/SetElementPermissions.log");
   					if ($TEST == 't') {
   						echo "ElementPermissionsRequest<br>";
   						var_dump($ElementPermissionsRequest);
   						echo "<br>";
   					}
   					$ElementPermissionsResponse = callService("programservice/SetElementPermissions", $ElementPermissionsRequest);
   					$ErrorCode = $ElementPermissionsResponse->{"Result"}->{"ErrorCode"};
   					if ($TEST == 't') {
   						echo "ElementPermissionsResponse<br>";
   						var_dump($ElementPermissionsResponse);
   						echo "<br>";
   					}
   					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." ElementPermissionsResponse : ".json_encode($ElementPermissionsResponse)." \n", 3, "/efs/log/SetElementPermissions.log");
   					if ($ErrorCode == "") {
   					   
   					   $sql = 'INSERT INTO DV_ElementPermissions (id, createDateTime, acctID, programID, userName) VALUES (NULL, NOW(), ?, ?, ?)';
               		
               		 try {
               			$Stmt1 = MySQL_Prepare($db,$sql);
               			$Stmt1->execute(array($ACCOUNTID, $ProgramID, $userEmail));
               		 } catch(Exception $e){
               			$success = false;	
               			$ErrorMessage = $e->getMessage();
               	     }	
   
   					} else {
   						$errorMessage .= "ElementPermissionsResponse ERROR : <br> ErrorMessage -> ".$ElementPermissionsResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
   						"ExceptionMessage : ".$ElementPermissionsResponse->{"Result"}->{"ExceptionMessage"};		
   					}
   					if ($TEST == 't') {
   						echo "SetElementPermissions errorMessage : $errorMessage<br>";
   					}
   				} else {
   					if ($TEST == 't') {
   						echo "SetElementPermissions getStudioUserID : $userID<br>";
   					}
   				}
   				
   			}
			}
		}
	}


	$result['status'] = true;
	$result['errorMessage'] = $errorMessage;
	$result['ElementPermissionsRequest'] = $ElementPermissionsRequest;
	$result['ElementPermissionsResponse'] = $ElementPermissionsResponse;
	return $result;
}

function SetElementPermissionsOld($TEST, $userTicket, $ProgramID, $ACCOUNTID){
	//$EMAIL = $_SESSION['EMAIL'];
	$EMAIL = '';
	$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$found = false;
	$errorMessage = '';

	$studioParam = GetStudioAPIParam();
	$POWEREMAIL = $studioParam['email'];
	$POWERPWD = $studioParam['pwd'];
	     

	while ($row = $Stmt->fetch()) {
		$docData = json_decode($row['data'],true);
		$users = $docData['users'];	
		//$accountName = $docData['defCompanyName'];	
		$accountName = $row['studioAccountName'];
		//$FieldNames = getxmediaAPICouchDB($accountName);
		$FieldNames = getxmediaAPIAcctID($ACCOUNTID);
		$PARTNERGUID = $FieldNames[0];
		$PARTNERPASSWORD = $FieldNames[1]; 
		$powerTicket = getTicketCurl($ACCOUNTID, $POWEREMAIL, $POWERPWD, $PARTNERGUID, $PARTNERPASSWORD); 

		foreach( $users as $users ) {						
			$userEmail = $users['email'];		
			error_log("SetElementPermissions:".date('m.d.Y h:i:s')." userEmail : $userEmail, EMAIL : $EMAIL \n", 3, "/efs/log/SetElementPermissions.log");
			if ($TEST == 't') {
				echo "SetElementPermissions $userEmail, $EMAIL<br>";
			}
			if ($userEmail != $EMAIL) {
				$StudioUser = getStudioUserID($TEST, $userTicket, $userEmail);
				error_log("SetElementPermissions:".date('m.d.Y h:i:s')." StudioUser : ".json_encode($StudioUser)." \n", 3, "/efs/log/SetElementPermissions.log");
				$userID = $StudioUser['userID'];
				if ($userID != "-1") {
					if ($TEST == 't') {
						echo "SetElementPermissions userID $userID<br>";
					}
					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." userID : $userID \n", 3, "/efs/log/SetElementPermissions.log");
					$ElementPermissionsRequest   = array
					(						
						"Credentials" => array
						(
							"Ticket" => $powerTicket        
						),
						"ElementPermissions" => [11000, 11001, 11002, 11003],
						"ProgramID" => $ProgramID,
						"UserID" => $userID

					);
					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." ElementPermissionsRequest : ".json_encode($ElementPermissionsRequest)." \n", 3, "/efs/log/SetElementPermissions.log");
					if ($TEST == 't') {
						echo "ElementPermissionsRequest<br>";
						var_dump($ElementPermissionsRequest);
						echo "<br>";
					}
					$ElementPermissionsResponse = callService("programservice/SetElementPermissions", $ElementPermissionsRequest);
					$ErrorCode = $ElementPermissionsResponse->{"Result"}->{"ErrorCode"};
					if ($TEST == 't') {
						echo "ElementPermissionsResponse<br>";
						var_dump($ElementPermissionsResponse);
						echo "<br>";
					}
					error_log("SetElementPermissions:".date('m.d.Y h:i:s')." ElementPermissionsResponse : ".json_encode($ElementPermissionsResponse)." \n", 3, "/efs/log/SetElementPermissions.log");
					if ($ErrorCode == "") {

					} else {
						$errorMessage .= "ElementPermissionsResponse ERROR : <br> ErrorMessage -> ".$ElementPermissionsResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
						"ExceptionMessage : ".$ElementPermissionsResponse->{"Result"}->{"ExceptionMessage"};		
					}
					if ($TEST == 't') {
						echo "SetElementPermissions errorMessage : $errorMessage<br>";
					}
				} else {
					if ($TEST == 't') {
						echo "SetElementPermissions getStudioUserID : $userID<br>";
					}
				}
				
			}
		}
	}


	$result['status'] = true;
	$result['errorMessage'] = $errorMessage;
	$result['ElementPermissionsRequest'] = $ElementPermissionsRequest;
	$result['ElementPermissionsResponse'] = $ElementPermissionsResponse;
	return $result;
}


function getConversionReport($TEST, $accountid, $programid, $DVPROGRAMID) {
	$affected_rows2 = 0;
	if ($programid) {
	} else {
		$programid = '';
	}
	if ($TEST == 't') {
		echo "<br>getConversionReport<br>";
		echo "<br>accountid : $accountid<br>";
		echo "<br>DVPROGRAMID : $DVPROGRAMID<br>";
		echo "<br>programid : $programid<br>";
	}

	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$sql = "select * from DV_Document_PromoteBlog where acctID=? and ID=? limit 1";
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($accountid,$DVPROGRAMID));
		if ($TEST == 't') {
			echo "<br>Stmt1 sql : $sql,accountid : $accountid,DVPROGRAMID : $DVPROGRAMID<br>";
		}

		$affected_rows1 = $Stmt1->rowCount();

		if ($affected_rows1 != 0) {
			while ($row = $Stmt1->fetch()) {
				$docData = json_decode($row['data'],true);
				$MATCHINGRULE = $docData['CONVERSION-URL-MATCHING-RULE'];
				$CONVERSIONURL = $docData['CONVERSION-URL'];
				$userURL = $docData['userURL'];
				if ($programid == '') {
					$sql = "select * from DV_CampaignList where acctID=? and campaignID=? limit 1";
					$Stmt3 = MySQL_Prepare($db,$sql);
					$Stmt3->execute(array($accountid,$DVPROGRAMID));
					if ($TEST == 't') {
						echo "<br>Stmt3 sql : $sql,accountid : $accountid,DVPROGRAMID : $DVPROGRAMID<br>";
					}
					while ($row3 = $Stmt3->fetch()) {
						$programid = $row3['publishProgramID'];
					}							
				}
				
				$pagesql = '';
				if ($MATCHINGRULE) {
					if ($MATCHINGRULE == 'Do not track conversions') {
						$MATCHINGRULE = '';
					}
				} else {
					$MATCHINGRULE = '';
				}
				if ($TEST == 't') {					
					echo "<br>1 MATCHINGRULE : $MATCHINGRULE<br>";
				}
				
				if ($MATCHINGRULE == 'Contains') {
					$MATCHINGRULE = ' like ';	
					$CONVERSIONURL1 = "%$CONVERSIONURL%";
					$CONVERSIONURL2 = "%$CONVERSIONURL%";
				} else if ($MATCHINGRULE == 'StartsWith') {
					$MATCHINGRULE = ' like ';
					$CONVERSIONURL1 = "$CONVERSIONURL%";
					$CONVERSIONURL2 = "/$CONVERSIONURL%";
				} else if ($MATCHINGRULE == 'Exact') {
					$MATCHINGRULE = ' = ';	
					$CONVERSIONURL1 = $CONVERSIONURL;
					$CONVERSIONURL2 = '/'.$CONVERSIONURL;
				} else {
					if ($userURL == 'N') {
						$MATCHINGRULE = ' like ';	
						$CONVERSIONURL1 = "%download.html";
						$CONVERSIONURL2 = "%download.html";
					}
				}
				if ($TEST == 't') {					
					echo "<br>2 MATCHINGRULE : $MATCHINGRULE<br>";
					echo "<br>CONVERSIONURL : $CONVERSIONURL<br>";
					echo "<br>CONVERSIONURL1 : $CONVERSIONURL1<br>";
					echo "<br>CONVERSIONURL2 : $CONVERSIONURL2<br>";
					echo "<br>userURL : $userURL<br>";
				}

				if ($MATCHINGRULE != '') {
					if ($TEST == 't') {	
						echo "MATCHINGRULE not blank<br>";
					}
					$pagesql = " and (visitPage $MATCHINGRULE ? or visitPage $MATCHINGRULE ?)";				
					$sql = "select DISTINCT purl from DV_Conversion where accountid=? and programid = ? ";
					$sql2 = $sql.$pagesql;
					if ($TEST == 't') {	
						echo "sql2 : $sql2<br>";
					}
					$Stmt2 = MySQL_Prepare($db,$sql2);
					$Stmt2->execute(array($accountid,$programid,$CONVERSIONURL1,$CONVERSIONURL2));
					if ($TEST == 't') {
						echo "<br>Stmt2 sql2 : $sql2,accountid : $accountid,programid : $programid,CONVERSIONURL1 : $CONVERSIONURL1,CONVERSIONURL2 : $CONVERSIONURL2<br>";
					}
					
					$affected_rows2 = $Stmt2->rowCount();
					$purlRow = 0;
					$CriteriaRow = '';
					$JoinOperator = "|";
					while ($row2 = $Stmt2->fetch()) {
						$purl = $row2['purl'];
						$purlRow++;			
						$field = 'purl';
						$op = 'Equal';
						$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
					}
					$Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
					if ($TEST == 't') {
						echo "<br>sql : $sql<br>";
						echo "<br>pagesql : $pagesql<br>";
						echo "<br>affected_rows2 : $affected_rows2<br>";
					}
				} else {
					if ($TEST == 't') {	
						echo "MATCHINGRULE is blank<br>";
					}
				}
			}
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows2 = 0;	
	}

	$result['affected_rows2'] = $affected_rows2;
	$result['LISTDEFINITION'] = $Filter;
	return $result;
	//return $affected_rows2;
}

function getConversionEmailReport($TEST, $accountid, $programid, $DVPROGRAMID, $EmailName, $fd, $td) {
	$affected_rows2 = 0;
	if ($programid) {
	} else {
		$programid = '';
	}
	if ($TEST == 't') {
		echo "<br>getConversionReport<br>";
		echo "<br>accountid : $accountid<br>";
		echo "<br>DVPROGRAMID : $DVPROGRAMID<br>";
		echo "<br>programid : $programid<br>";
	}
	$pagesql = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		if ($programid == '') {
			$sql = "select * from DV_CampaignList where acctID=? and campaignID=? limit 1";
			$Stmt3 = MySQL_Prepare($db,$sql);
			$Stmt3->execute(array($accountid,$DVPROGRAMID));
			if ($TEST == 't') {
				echo "<br>Stmt3 sql : $sql,accountid : $accountid,DVPROGRAMID : $DVPROGRAMID<br>";
			}
			while ($row3 = $Stmt3->fetch()) {
				$programid = $row3['publishProgramID'];
			}							
		}
		$sql = "select DISTINCT purl from DV_Conversion_Leads_Email where accountid=? and programid = ? and emailName = ? and insertTime >= STR_TO_DATE('$fd', '%m/%d/%Y') AND insertTime <= STR_TO_DATE('$td', '%m/%d/%Y')";
		$sql2 = $sql.$pagesql;
		if ($TEST == 't') {	
			echo "sql2 : $sql2<br>";
		}
		$Stmt2 = MySQL_Prepare($db,$sql2);
		$Stmt2->execute(array($accountid,$programid,$EmailName));
		if ($TEST == 't') {
			echo "<br>Stmt2 sql2 : $sql2,accountid : $accountid,programid : $programid,EmailName : $EmailName<br>";
		}
		
		$affected_rows2 = $Stmt2->rowCount();
		$purlRow = 0;
		$CriteriaRow = '';
		$JoinOperator = "|";
		$field = 'purl';
		$op = 'Equal';
			
		while ($row2 = $Stmt2->fetch()) {
			$purl = $row2['purl'];
			$purlRow++;			
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		if ($purlRow == 0) {
			$purlRow = 1;
			$purl = '';
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		$Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
		if ($TEST == 't') {
			echo "<br>sql : $sql<br>";
			echo "<br>pagesql : $pagesql<br>";
			echo "<br>affected_rows2 : $affected_rows2<br>";
		}
				
			
	
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows2 = 0;	
	}
	
	$result['sql2'] = $sql2;
	$result['accountid'] = $accountid;
	$result['programid'] = $programid;
	$result['EmailName'] = $EmailName;
	$result['affected_rows2'] = $affected_rows2;
	$result['LISTDEFINITION'] = $Filter;
	
	return $result;
	//return $affected_rows2;
}

function getBA_ReachReport($TEST, $EMAIL, $PWD, $accountid, $programid, $DVPROGRAMID, $EmailName, $fd, $td) {
	$affected_rows2 = 0;
	if ($programid) {
	} else {
		$programid = '';
	}
	
	$pagesql = '';
	try {  
		
		
		if($db == null){
			$db = GetPDOMySQL();
		}
		if ($programid == '') {
			$sql = "select * from DV_CampaignList where acctID=? and campaignID=? limit 1";
			$Stmt3 = MySQL_Prepare($db,$sql);
			$Stmt3->execute(array($accountid,$DVPROGRAMID));
			if ($TEST == 't') {
				echo "<br>getBA_ReachReport Stmt3 sql : $sql,accountid : $accountid,DVPROGRAMID : $DVPROGRAMID<br>";
			}
			while ($row3 = $Stmt3->fetch()) {
				$programid = $row3['publishProgramID'];
			}							
		}
		if ($TEST == 't') {
			echo "<br>getBA_ReachReport<br>";
			echo "<br>accountid : $accountid<br>";
			echo "<br>DVPROGRAMID : $DVPROGRAMID<br>";
			echo "<br>programid : $programid<br>";
		}
		
		$BA_Reach = getBA_Reach($TEST, $EMAIL, $PWD, $accountid, $programid, $fd, $td);
		
		$purlList = $BA_Reach['purlList'];
		$affected_rows2 = $BA_Reach['purlCount'];
		$purlRow = 0;
		$CriteriaRow = '';
		$JoinOperator = "|";
		$field = 'purl';
		$op = 'Equal';
		
		foreach ($purlList as $purl) {
			$purlRow++;			
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		if ($purlRow == 0) {
			$purlRow = 1;
			$purl = '';
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		$Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
		if ($TEST == 't') {
			echo "<br>sql : $sql<br>";
			echo "<br>pagesql : $pagesql<br>";
			echo "<br>affected_rows2 : $affected_rows2<br>";
		}
				
			
	
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows2 = 0;	
	}
	
	$result['sql2'] = $sql2;
	$result['accountid'] = $accountid;
	$result['programid'] = $programid;
	$result['EmailName'] = $EmailName;
	$result['affected_rows2'] = $affected_rows2;
	$result['LISTDEFINITION'] = $Filter;
	$result['BA_Reach'] = $BA_Reach;
	$result['purlList'] = $purlList;
	$result['purlCount'] = $affected_rows2;
	
	return $result;
	//return $affected_rows2;
}

function getMarketReport($TEST, $accountid, $contentID) {
	$affected_rows2 = 0;
	if ($programid) {
	} else {
		$programid = '';
	}
	
	$pagesql = '';
	try {  
		$sql2 = "select distinct purl from DV_Conversion where contentID = $contentID and visitPage like '%Download.html%'";
		$conversionPurl = MySQL_Select($sql2);
		
		$purlList = array_column($conversionPurl, 'purl');
		$affected_rows2 = count($conversionPurl);
		$purlRow = 0;
		$CriteriaRow = '';
		$JoinOperator = "|";
		$field = 'purl';
		$op = 'Equal';
		
		foreach ($purlList as $purl) {
			$purlRow++;			
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		if ($purlRow == 0) {
			$purlRow = 1;
			$purl = '';
			$CriteriaRow .= "<Criteria Row=\"$purlRow\" Field=\"$field\" Operator=\"$op\" Value=\"$purl\" />";
		}
		$Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows2 = 0;	
	}
	
	$result['sql2'] = $sql2;
	$result['accountid'] = $accountid;
	$result['programid'] = '';
	$result['EmailName'] = '';
	$result['affected_rows2'] = $affected_rows2;
	$result['LISTDEFINITION'] = $Filter;
	$result['purlList'] = $purlList;
	$result['purlCount'] = $affected_rows2;
	
	return $result;
	//return $affected_rows2;
}

function saveEditSubAccount($TEST, $ACCOUNTID, $defCompanyName, $defCompanyAddress, $defCompanyPhone, $defCompanyWebsite, $defCompanyLogo, $defCompanyIcon, $defButtonColor, $defTimezone)
{
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$found = false;
	$errorMessage = '';
	
	
	while ($row = $Stmt->fetch()) {
		$docData = json_decode($row['data'],true);		

		$docData['defCompanyName'] = $defCompanyName;			
		$docData['defCompanyAddress'] = $defCompanyAddress;			
		$docData['defCompanyPhone'] = $defCompanyPhone;			
		$docData['defCompanyWebsite'] = $defCompanyWebsite;			
		$docData['defCompanyLogo'] = $defCompanyLogo;			
		$docData['defCompanyIcon'] = $defCompanyIcon;			
		$docData['defButtonColor'] = $defButtonColor;			
		$docData['defTimezone'] = $defTimezone;			

		

		$userInfo = json_encode($docData);
		try {  
			if($db == null){
				$db = GetPDOMySQL();
			}
			$table = 'DV_UserInfo';
			$insertStmt = MySQL_Prepare($db,"INSERT INTO $table(acctID, data) ".
				"VALUES(?, ?) ".
				"on duplicate key update ".
				"data=?"
			);
			$insertStmt->execute(array($ACCOUNTID, $userInfo, $userInfo));
			$affected_rows = $insertStmt->rowCount();
			$success = true;
		}  
		catch(exception $e) {
			$errorMessage = $e->getMessage();
			$affected_rows = -1;
			$success = false;
		}
		
		$s3Result = userinfoToS3("userinfo2s3",$ACCOUNTID,"yyyy");    
    	
	}
	$result['success'] = $success;
	$result['errorMessage'] = $errorMessage;
	$result['affected_rows'] = $affected_rows;
	$result['s3Result'] = $s3Result;
	return $result;
}

function getIPData($TEST, $login, $password, $ipAddress)
{
	$url = "https://geoip.maxmind.com/geoip/v2.1/insights/$ipAddress?pretty";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, "$login:$password");
	$result = curl_exec($ch);
	curl_close($ch);  
	
	if ($TEST == 't') {
		echo "result : $result<br>\n";
	}

	return $result;
} 


function getReportDate($usersTimezone) {
	// timezoneMoment
	// Date for a specific date/time:
	$date = new DateTime();
	$date->add(DateInterval::createFromDateString('yesterday'));


	// Convert timezone
	$tz = new DateTimeZone($usersTimezone);
	$date->setTimeZone($tz);

	// Output date after 
	$result['reportDate'] = $date->format('M j, Y');
	$result['calcDate'] = $date->format('Y-m-d');
	$result['calcDateTime'] = $date->format('Y-m-d H:i:s');
	$result['calcTime'] = $date->format('H:i:s');
	$result['calcHour'] = $date->format('H');
	return $result;
}

function getReportDateToday($usersTimezone) {
	// timezoneMoment
	// Date for a specific date/time:
	$date = new DateTime();
	

	// Convert timezone
	$tz = new DateTimeZone($usersTimezone);
	$date->setTimeZone($tz);

	// Output date after 
	$result['reportDate'] = $date->format('M j, Y');
	$result['calcDate'] = $date->format('Y-m-d');
	$result['calcDateTime'] = $date->format('Y-m-d H:i:s');
	$result['calcTime'] = $date->format('H:i:s');
	$result['calcHour'] = $date->format('H');
	return $result;
}

function getConversionSQLDateRange($startDate, $endDate, $usersTimezone, $fieldname) {
	
	$fd = $startDate;
	$td = $endDate;
	
	$fd = date("Y-m-d", strtotime($startDate));
	$td = date("Y-m-d", strtotime($endDate));
	
	$origin = strtotime($fd);
	$target = strtotime($td);
	
	$timeDiff = abs($target - $origin);

	$countDate = $timeDiff/86400;  // 86400 seconds in one day
	$countDate = intval($countDate);
	$countDate++;
	
	
	$startDate = "$startDate 00:00:00";
	$endDate = "$endDate 23:59:59";
	$startDate = convert_to_server_date($startDate, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$endDate = convert_to_server_date($endDate, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sqlDate = " $fieldname >= '$startDate' AND $fieldname <= '$endDate' ";

	$date = new DateTime();
	$todayDate = $date->format('Y-m-d');
	$todayDate1 = "$todayDate 00:00:00";
	$todayDate1 = convert_to_server_date($todayDate1, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$todayDate2 = "$todayDate 23:59:59";
	$todayDate2 = convert_to_server_date($todayDate2, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sqlToday = " $fieldname >= '$todayDate1' AND $fieldname <= '$todayDate2' ";

	$new_date = date('Y-m-d H:i:s', strtotime($startDate.' - 90 days'));
	$start90Date = convert_to_server_date($new_date, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sql90Days = " $fieldname >= '$start90Date' AND $fieldname <= '$endDate' ";
	
		
	$CountDate1 = $td;
	$CountDate2 = $fd;

	$c1 = convert_to_server_date("$CountDate1 23:59:59", $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$c2 = convert_to_server_date("$CountDate2 00:00:00", $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$CountDateTime2 = "$CountDate1 23:59:59";
	$CountDateTime1 = "$CountDate2 00:00:00";
	$sqlCountDate = " $fieldname >= '$c2' AND $fieldname <= '$c1' ";

	
	
	$result['startDate'] = $startDate;
	$result['endDate'] = $endDate;
	$result['todayDate'] = $todayDate;
	$result['todayDate1'] = $todayDate1;
	$result['todayDate2'] = $todayDate2;
	$result['CountDate1'] = $CountDate1;
	$result['CountDate2'] = $CountDate2;
	$result['CountDateTime2'] = $CountDateTime2;
	$result['CountDateTime1'] = $CountDateTime1;
	$result['sqlCountDate'] = $sqlCountDate;
	$result['sqlToday'] = $sqlToday;
	$result['sqlDate'] = $sqlDate;
	$result['start90Date'] = $start90Date;
	$result['sql90Days'] = $sql90Days;
	$result['countDate'] = $countDate;
	$result['origin'] = $origin;
	$result['target'] = $target;
	return $result;
}

function getConversionSQLDate($calcDate, $usersTimezone, $fieldname, $countDate) {

	$startDate = "$calcDate 00:00:00";
	$endDate = "$calcDate 23:59:59";
	$startDate = convert_to_server_date($startDate, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$endDate = convert_to_server_date($endDate, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sqlDate = " $fieldname >= '$startDate' AND $fieldname <= '$endDate' ";

	$date = new DateTime();
	$todayDate = $date->format('Y-m-d');
	$todayDate1 = "$todayDate 00:00:00";
	$todayDate1 = convert_to_server_date($todayDate1, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$todayDate2 = "$todayDate 23:59:59";
	$todayDate2 = convert_to_server_date($todayDate2, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sqlToday = " $fieldname >= '$todayDate1' AND $fieldname <= '$todayDate2' ";

	$new_date = date('Y-m-d H:i:s', strtotime($startDate.' - 90 days'));
	$start90Date = convert_to_server_date($new_date, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$sql90Days = " $fieldname >= '$start90Date' AND $fieldname <= '$endDate' ";

	if ($countDate != -1) {
		$date = new DateTime();
		$CountDate1 = date('Y-m-d');

		$datetime = new DateTime();		
		$datetime->modify("-".$countDate." day");
		$CountDate2 = $datetime->format('Y-m-d');

		$c1 = convert_to_server_date("$CountDate1 23:59:59", $usersTimezone, 'UTC', 'Y-m-d H:i:s');
		$c2 = convert_to_server_date("$CountDate2 00:00:00", $usersTimezone, 'UTC', 'Y-m-d H:i:s');
		$CountDateTime2 = "$CountDate1 23:59:59";
		$CountDateTime1 = "$CountDate2 00:00:00";
		$sqlCountDate = " $fieldname >= '$c2' AND $fieldname <= '$c1' ";

	} else {
		$CountDate1 = '';
		$CountDate2 = '';
		$CountDateTime1 = '';
		$CountDateTime2 = '';
	}
	
	
	$result['startDate'] = $startDate;
	$result['endDate'] = $endDate;
	$result['todayDate'] = $todayDate;
	$result['todayDate1'] = $todayDate1;
	$result['todayDate2'] = $todayDate2;
	$result['CountDate1'] = $CountDate1;
	$result['CountDate2'] = $CountDate2;
	$result['CountDateTime2'] = $CountDateTime2;
	$result['CountDateTime1'] = $CountDateTime1;
	$result['sqlCountDate'] = $sqlCountDate;
	$result['sqlToday'] = $sqlToday;
	$result['sqlDate'] = $sqlDate;
	$result['start90Date'] = $start90Date;
	$result['sql90Days'] = $sql90Days;
	return $result;
}

function getReportDetailDate($insertTime, $usersTimezone) {		
	$format = 'Y-m-d H:i:s';
	return getReportDetailDateFormat($insertTime, $usersTimezone, $format);
}

function getReportDetailDateFormat($insertTime, $usersTimezone, $format) {		
	$dt = new DateTime($insertTime);
	$dt->setTimezone(new DateTimeZone($usersTimezone));
	$date1 = $dt->format($format);
	return $date1;
}


function getServerHost($path) {
	$schema = isset($_SERVER["HTTPS"]) ? "https:" : "http:";
	$detailURL    = "$schema//" . $_SERVER['HTTP_HOST'] . "/$path";	
	return $detailURL;
}

function getConversionReportID($id) {	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Conversion_Report where id=? limit 1");
		$Stmt->execute(array($id));
		$affected_rows = $Stmt->rowCount();
		$acctID = '';
		$reportType = '';
		$usersTimezone = '';
		$serverDate = '';
		$calcDate = '';
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);				
			$acctID = $docData['acctID'];
			$reportType = $docData['reportType'];
			$usersTimezone = $docData['usersTimezone'];
			$serverDate = $docData['serverDate'];
			$calcDate = $docData['calcDate'];	
			$group = array(					
				'acctID'=>$acctID,
				'reportType'=>$reportType,
				'usersTimezone'=>$usersTimezone,
				'serverDate'=>$serverDate,
				'calcDate'=>$calcDate,
			);								
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $group;
}


function getUserTimeZoneNull($acctID) {
	$userInfo = getDVUserinfo($acctID);
	$usersTimezone = $userInfo['defTimezone'];
	if ($usersTimezone) { } else { $usersTimezone = ''; }
	if ($usersTimezone == '') {
		
	} else {
		$DVTimezones = getDVTimezone();
		foreach( $DVTimezones as $DVTimezone ) {	
			if ($usersTimezone == $DVTimezone['timezoneStudio']) {
				$usersTimezone = $DVTimezone['timezoneMoment'];
				break;
			}
		}
	}

	return $usersTimezone;
}

function getUserTimeZone($acctID) {
	$userInfo = getDVUserinfo($acctID);
	$usersTimezone = $userInfo['defTimezone'];
	if ($usersTimezone == '') {
		$usersTimezone = 'Greenwich Standard Time';
	}
	$DVTimezones = getDVTimezone();
	foreach( $DVTimezones as $DVTimezone ) {	
		if ($usersTimezone == $DVTimezone['timezoneStudio']) {
			$usersTimezone = $DVTimezone['timezoneMoment'];
			break;
		}
	}

	return $usersTimezone;
}

function getDVStateTimezone() {
	$DVState = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_StateTimezone");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$StateType = $row['StateType'];
			$State = $row['State'];
			$StateName = $row['StateName'];
			$Timezone = $row['Timezone'];
			$group = array(					
				'StateType'=>$StateType,
				'State'=>$State,
				'StateName'=>$StateName,
				'Timezone'=>$Timezone,
			);					
			$DVState[] = $group;
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $DVState;
}

function findDVStateTimezone($DVState, $state) {
	$Timezone = '';
	$state = strtolower($state);
	foreach( $DVState as $DVS ) {	
		$s1 = strtolower($DVS['State']);
		$s2 = strtolower($DVS['StateName']);
		if (($state == $s1) || ($state == $s2)) {
			$Timezone = $DVS['Timezone'];
			break;
		}
	}

	return $Timezone;
}



function getDVUserinfo($acctID) {
	if($db == null){
        $db = GetPDOMySQL();
    }
	$Stmt = MySQL_Prepare($db,"select * from DV_UserInfo where acctID=?");	
	$Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$defTimezone = '';
	$userEmail = '';
	while ($row = $Stmt->fetch()) {
		$docData = json_decode($row['data'],true);			
		$defTimezone = $docData['defTimezone'];	
		$defFromName = $docData['defFromName'];	
		$defFromAdd = $docData['defFromAdd'];	
		$defCompanyName = $docData['defCompanyName'];
		$defCompanyWebsite = $docData['defCompanyWebsite'];
		$stripeToken = $docData['stripeToken'];	
		$studioAccountName = $row['studioAccountName'];	
		$paidAccount = $row['paidAccount'];	
		$studioParentID = $row['studioParentID'];
		$defCompanyLogo = $docData['defCompanyLogo'];
		$defCompanyAddress = $docData['defCompanyAddress'];
		$defCompanyPhone = $docData['defCompanyPhone'];
		$defButtonColor = $docData['defButtonColor'];
		$defCompanyPrivacyPolicy = $docData['defCompanyPrivacyPolicy'];
		if ($defCompanyPrivacyPolicy) { } else { $defCompanyPrivacyPolicy = $defCompanyWebsite; } 
		if ($defButtonColor) { } else { $defButtonColor = "B36AF8"; } 
		
		
		$recurringDate = $row['recurringDate'];	
		$recurringAmount = $row['recurringAmount'];	
		$recurringPeriod = $row['recurringPeriod'];	
		$recurringProduct = $row['recurringProduct'];	
		$lastBillingDate = $row['lastBillingDate'];	
		$paidAccount = $row['paidAccount'];	
		$userStatus = $row['userStatus'];	
		$StripeCustomer = $row['StripeCustomer'];
		$expireDate = $row['expireDate'];
		$delinquentDate = $row['delinquentDate'];	
		$accountCloseOverride = $row['accountCloseOverride'];	
		$removeBrandTrigger = $row['removeBrandTrigger'];
		$IP_Enrichment_Enabled = $row['IP_Enrichment_Enabled'];
		$Premium_Enrichment_Flag = $row['Premium_Enrichment_Flag'];
		$showContent = $row['ShowContent'];
		$DV_Uiaccess = $row['DV_Uiaccess'];
		$smsLongCode = $row['smsLongCode'];
		$enableWalletPass = $row['enableWalletPass'];
		
		if ($removeBrandTrigger) { } else { $removeBrandTrigger = ''; }
		if ($IP_Enrichment_Enabled) { } else { $IP_Enrichment_Enabled = ''; }
		if ($Premium_Enrichment_Flag) { } else { $Premium_Enrichment_Flag = ''; }
		if ($DV_Uiaccess) { } else { $DV_Uiaccess = ''; }
		$emailLimit = $row['emailLimit'];	
		$emailOverage = $row['emailOverage'];	
		$contactLimit = $row['contactLimit'];	
		$contactOverage = $row['contactOverage'];	
		$smsLimit = $row['smsLimit'];	
		$smsOverage = $row['smsOverage'];	
		$enrichmentLimit = $row['enrichmentLimit'];	
		$enrichmentOverage = $row['enrichmentOverage'];	
		$contentSearchLimit = $row['contentSearchLimit'];	
		$contentSearchOverage = $row['contentSearchOverage'];	
		$contentLaunchLimit = $row['contentLaunchLimit'];	
		$contentLaunchOverage = $row['contentLaunchOverage'];	
		$UiAccess = $row['UiAccess'];

		if (is_numeric($emailLimit)) { } else { $emailLimit = -1; }
		if (is_numeric($emailOverage)) { } else { $emailOverage = -1; }
		if (is_numeric($contactLimit)) { } else { $contactLimit = -1; }
		if (is_numeric($contactOverage)) { } else { $contactOverage = -1; }
		if (is_numeric($smsLimit)) { } else { $smsLimit = -1; }
		if (is_numeric($smsOverage)) { } else { $smsOverage = -1; }
		if (is_numeric($enrichmentLimit)) { } else { $enrichmentLimit = -1; }
		if (is_numeric($enrichmentOverage)) { } else { $enrichmentOverage = -1; }
		if (is_numeric($contentSearchLimit)) { } else { $contentSearchLimit = -1; }
		if (is_numeric($contentSearchOverage)) { } else { $contentSearchOverage = -1; }
		if (is_numeric($contentLaunchLimit)) { } else { $contentLaunchLimit = -1; }
		if (is_numeric($contentLaunchOverage)) { } else { $contentLaunchOverage = -1; }
		if (is_numeric($UiAccess)) { } else { $UiAccess = 1; }
		
		$dvAccountName = $docData['dvAccountName'];	
		if ($dvAccountName != '') {
			$defCompanyName = $dvAccountName;
		}
		$users = $docData['users'];

		foreach( $users as $users ) {	
			if ($users['email'] != '') {
				$userEmail .= $users['email'].',';		
			}
		}
	}
	
	$lastBillingDateUS = setDateFormat($lastBillingDate,"m/d/Y");
	$delinquentDateUS = setDateFormat($delinquentDate,"m/d/Y");
	$recurringDateUS = setDateFormat($recurringDate,"m/d/Y");
	$expireDateUS = setDateFormat($expireDate,"m/d/Y");
	$accountCloseOverrideUS = setDateFormat($accountCloseOverride,"m/d/Y");
	
	
	$userEmail = rtrim($userEmail,',');
	$result['accountID'] = $acctID;
	$result['defTimezone'] = $defTimezone;
	$result['userEmail'] = $userEmail;
	$result['defFromAdd'] = $defFromAdd;
	$result['defFromName'] = $defFromName;
	$result['defCompanyName'] = $defCompanyName;
	$result['defCompanyWebsite'] = $defCompanyWebsite;
	$result['defCompanyAddress'] = $defCompanyAddress;
	$result['defCompanyPhone'] = $defCompanyPhone;
	$result['defButtonColor'] = $defButtonColor;
	$result['defCompanyPrivacyPolicy'] = $defCompanyPrivacyPolicy;
	$result['studioAccountName'] = $studioAccountName;
	$result['defCompanyLogo'] = $defCompanyLogo;
	$result['paidAccount'] = $paidAccount;
	$result['studioParentID'] = $studioParentID;
	$result['recurringDate'] = $recurringDate;
	$result['recurringAmount'] = $recurringAmount;
	$result['recurringPeriod'] = $recurringPeriod;
	$result['recurringProduct'] = $recurringProduct;
	$result['lastBillingDate'] = $lastBillingDate;
	$result['paidAccount'] = $paidAccount;
	$result['userStatus'] = $userStatus;
	$result['StripeCustomer'] = $StripeCustomer;
	$result['stripeToken'] = $stripeToken;
	$result['expireDate'] = $expireDate;
	$result['delinquentDate'] = $delinquentDate;
	$result['lastBillingDateUS'] = $lastBillingDateUS;
	$result['delinquentDateUS'] = $delinquentDateUS;
	$result['recurringDateUS'] = $recurringDateUS;
	$result['expireDateUS'] = $expireDateUS;
	$result['accountCloseOverride'] = $accountCloseOverride;
	$result['accountCloseOverrideUS'] = $accountCloseOverrideUS;
	$result['removeBrandTrigger'] = $removeBrandTrigger;
	$result['IP_Enrichment_Enabled'] = $IP_Enrichment_Enabled;
	$result['Premium_Enrichment_Flag'] = $Premium_Enrichment_Flag;
	$result['ShowContent'] = $showContent;
	
	$result['emailLimit'] = $emailLimit;	
	$result['emailOverage'] = $emailOverage;	
	$result['contactLimit'] = $contactLimit;	
	$result['contactOverage'] = $contactOverage;	
	$result['smsLimit'] = $smsLimit;	
	$result['smsOverage'] = $smsOverage;	
	$result['enrichmentLimit'] = $enrichmentLimit;	
	$result['enrichmentOverage'] = $enrichmentOverage;	
	$result['contentSearchLimit'] = $contentSearchLimit;	
	$result['contentSearchOverage'] = $contentSearchOverage;	
	$result['contentLaunchLimit'] = $contentLaunchLimit;	
	$result['contentLaunchOverage'] = $contentLaunchOverage;
	$result['UiAccess'] = $UiAccess;
	$result['DV_Uiaccess'] = $DV_Uiaccess;
	$result['smsLongCode'] = $smsLongCode;
	$result['enableWalletPass'] = $enableWalletPass;
	$result['docData'] = $docData;
	
	return $result;
}

function getDVUserinfoSubscriptions($acctID,$Subscriptions) {
	$Stmt = MySQL_Prepare($db,"select * from DV_UserInfo where acctID=?");	
	$Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$defTimezone = '';
	$userEmail = '';
	while ($row = $Stmt->fetch()) {
		$docData = json_decode($row['data'],true);			
		$defTimezone = $docData['defTimezone'];	
		$defFromAdd = $docData['defFromAdd'];	
		$defCompanyName = $docData['defCompanyName'];	
		$dvAccountName = $docData['dvAccountName'];	
		if ($dvAccountName != '') {
			$defCompanyName = $dvAccountName;
		}
		$users = $docData['users'];

		foreach( $users as $user ) {	
			if ($user['email'] != '') {
				if ($user[$Subscriptions] == 'N') {
				} else {
					if (stripos($user['email'], "@my-dv.com") !== FALSE) {
					} else {
						$userEmail .= $user['email'].',';		
					}
				}
			}
		}
	}
	$userEmail = rtrim($userEmail,',');
	$result['defTimezone'] = $defTimezone;
	$result['userEmail'] = $userEmail;
	$result['defFromAdd'] = $defFromAdd;
	$result['defCompanyName'] = $defCompanyName;
	
	return $result;
}

function countAllContactStudio($ACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD, $ticket) {
	$TEST = '';
	//$logger = new FileLogger("countAllContactStudio.log"); 
	
	//echo "ticket : $ticket <br>";
	try { 
		$sqlTxt = "SELECT * FROM DV_AudienceList where acctID = $ACCOUNTID"; 
		$docs = MySQL_Select($sqlTxt);
		$selectSuccess = true; 
	}catch(exception $e) {
		$errorMessage .= " ex: ".$e; 
		logDebug("ERROR: SELECT[$sqlTxt]");	    			
		logDebug($errorMessage);	    			
		$affected_rows = -1;
		$selectSuccess = false;
	}

	//echo "doc<br>";
	//var_dump($docs);
	//echo "<br>";
	if ($selectSuccess == true) {
		//$ticket = getTicket($ACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
		$errorMessage = '';
		foreach($docs as $doc){
				sleep(2); //2262
			$LISTDEFINITION = $doc["LISTDEFINITION"];
			$id = $doc["id"];
			$count = GetContactCount($TEST, $ticket, $LISTDEFINITION, $ACCOUNTID);
			$sql = "UPDATE DV_AudienceList SET LISTCOUNT = '$count'  WHERE id = $id and acctID = $ACCOUNTID" ; 
			try {  
				if($db == null){
					$db = GetPDOMySQL();
				}
				$removeStmt = MySQL_Prepare($db,$sql);
				$removeStmt->execute();
				$affected_rows = $removeStmt->rowCount();
				$updsuccess = true; 
				//$logger->log("sql : $sql");
				
			}catch(exception $e) {
				$errorMessage .= " ex: ".$e; 
				logDebug("ERROR: UPDATESQL[$sql]");	    			
				logDebug($errorMessage);	    			
				$affected_rows = -1;
				$updsuccess = false; 	
				$allUpdSuccess = false; 			
			}	
		}
		$message = $errorMessage;
	}
	return array('success'=>true, 'affected_rows' => $affected_rows, 'selectSuccess' => $selectSuccess, 'updsuccess' => $updsuccess, 'allUpdSuccess' => $allUpdSuccess, 'message' => $message);
}

function sendToNeverbounce($TEST, $importPath, $importFile) {

	$neverBounceKey = GetCurrentConfig()->neverBounceKey;
	$neverBounceMode = GetCurrentConfig()->neverBounceMode;
	$neverBounceFilename = "SampleNeverBounceAPI.csv";

	if ($neverBounceMode == 's3') {
		$srcFile = $importPath.$importFile;
		$value = file_get_contents($srcFile);
		$inputFile = s3_put_contents($importFile ,$value);		
	} else {
		$davinciDomain = getServerHost('');
		$inputFile = $davinciDomain.'admin/import/importFile/'.$importFile;
	}

	
	$createRequest = array
	(
		"key" => $neverBounceKey,
		"input_location" => "remote_url",
		"filename" => $importFile, 
		"auto_parse" => 1, 
		"auto_start" => 1, 
		"run_sample" => 1,
		"input" => $inputFile,

	);

	if ($TEST == 't') {
		echo "1 Starting an Analysis [createRequest] : <br>";
		var_dump ($createRequest);
		echo "<br>";
		echo "<br>";
	}


	$request_string = json_encode($createRequest); 
	$service = curl_init("https://api.neverbounce.com/v4/jobs/create/");   
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   

	$response_string = curl_exec($service);
	$createResponse = json_decode($response_string);
	if ($TEST == 't') {
		echo "1 Starting an Analysis [createResponse] : <br>";
		var_dump ($createResponse);
		echo "<br>";
		echo "<br>";
	}

	$status =  $createResponse->status;
	$job_id =  $createResponse->job_id;
	$execution_time =  $createResponse->execution_time;

	$analysisURL = "https://api.neverbounce.com/v4/jobs/status?key=$neverBounceKey&job_id=$job_id";
	if ($TEST == 't') {
		echo "2 Checking the status of an Analysis [analysisRequest] :<br>$analysisURL<br>";
	}
	
	// 'complete'

	//open connection
	$service = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($service, CURLOPT_URL, $analysisURL);
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);

	$response_string = curl_exec($service);
	$analysisResponse = json_decode($response_string);
	
	if ($TEST == 't') {
		echo "2 Checking the status of an Analysis [analysisResponse] :<br>";
		var_dump ($analysisResponse);
		echo "<br>";
		echo "<br>";
	}
	
		
	$verifyRequest = array
	(
		"key" => $neverBounceKey,
		"job_id" => $job_id,
	);
	
	if ($TEST == 't') {
		echo "3 Running Verification after an Analysis [verifyRequest] : <br>";
		var_dump ($verifyRequest);
		echo "<br>";
	}

	$request_string = json_encode($verifyRequest); 
	$service = curl_init("https://api.neverbounce.com/v4/jobs/start/");   
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string))                                                                       
	);                                                                                                                   
	$response_string = curl_exec($service);
	$verifyResponse = json_decode($response_string);
	if ($TEST == 't') {
		echo "3 Running Verification after an Analysis [verifyResponse] :<br>";
		var_dump ($verifyResponse);
		echo "<br>";
		echo "<br>";
	}

	$downloadURL = "https://api.neverbounce.com/v4/jobs/download?key=$neverBounceKey&job_id=$job_id";
	if ($TEST == 't') {
		echo "4 download [downloadRequest] :<br>$downloadURL<br>";
	}
	
	//open connection
	$service = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($service, CURLOPT_URL, $downloadURL);
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);

	$response_string = curl_exec($service);
	$downloadResponse = json_decode($response_string);
	
	if ($TEST == 't') {
		echo "4 download [downloadResponse] :<br>";
		var_dump ($downloadResponse);
		echo "<br>";
		echo "<br>";
	}
	
	

	$result['status'] = $status;
	$result['job_id'] = $job_id;
	$result['inputFile'] = $inputFile;	
	$result['neverBounceMode'] = $neverBounceMode;		
	$result['createRequest'] = $createRequest;
	$result['createResponse'] = $createResponse;
	$result['analysisURL'] = $analysisURL;
	$result['analysisRequest'] = $analysisRequest;
	$result['analysisResponse'] = $analysisResponse;
	$result['verifyRequest'] = $verifyRequest;
	$result['verifyResponse'] = $verifyResponse;
	$result['downloadURL'] = $downloadURL;
	$result['downloadRequest'] = $downloadRequest;
	$result['downloadResponse'] = $downloadResponse;
	return $result;

}

function updateImportFromStudio($acctID){
	$loggerSub = new FileLogger("getAuduenceData.log");  
	$loggerSub->log("-----CALL updateImportFromStudio() No option");  
	updateImportFromStudioInner($acctID,""); 
}

function updateImportFromStudioInner($acctID,$option){
	$CurrentConfig = davinciSetConfig();
	$davinciDomain = $CurrentConfig->davinciDomain;
	$loggerSub = new FileLogger("getAuduenceData.log"); 
	$loggerSub->log("-----CALL updateImportFromStudioInner() option = [$option]");  

   $ACCOUNTID = $_SESSION['ACCOUNTID'];
   if ($ACCOUNTID) { 
   	$studioParam = GetStudioAPIParam();
   	$loggerSub->log("[global.php] 5144");
   } else {
   	$ParentID = getStudioParentID($acctID); 
   	$studioParam = GetStudioAPIParamWithAcctID($ParentID);
   	$loggerSub->log("[global.php] 5148");
   }
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	
	$param['TEST'] = '';
	$param['ACCOUNTID'] = $acctID;
	$param['EMAIL'] = $EMAIL;
	$param['PWD'] = $PWD;
	$param['PARTNERGUID'] = $PARTNERGUID;
	$param['PARTNERPASSWORD'] = $PARTNERPASSWORD;
	$param['SearchString'] = '';

	if($option != "smartList"){	
		$studioList = getStudioList($param);
		$allLists = $studioList['allList'];	
		$count = sizeof($allLists);
		$loggerSub->log("count $count");
	}

	$AddDomain_status = 'Success';
	$errorMessage = '';		
	$Stmt2 = MySQL_Prepare($db,"SELECT * FROM DV_AudienceList WHERE acctID=? and studioStatus<=1 order by id");
	$Stmt2->execute(array($acctID));

	while ($row2 = $Stmt2->fetch()) {		
		$id = $row2['id'];
		$importName = $row2['LISTNAME'];
		$contactID = $row2['contactID'];
		$importUser = $row2['importUser'];		
		//$loggerSub->log("$id , $importName , $contactID , $importUser");		
		
		if(!empty($allLists)){	
			foreach( $allLists as $allList ) {	
				$Name = $allList['Name'];
				$count = $allList['count'];
				$Status = $allList['Status'];										
				$StatusName = $allList['StatusName'];					
				$StatusLower = strtolower($StatusName);

				$str1 = "Name : $Name, count : $count, Status : $Status, StatusName : $StatusName";
				//$loggerSub->log($str1);				
				if ($importName == $Name) {						
					$Stmt3 = MySQL_Prepare($db,"update DV_AudienceList set studioStatus=?,LISTCOUNT=? where id=?");
					$Stmt3->execute(array($Status, $count, $id));	
					$str1 = "Name : $Name, count : $count, Status : $Status, StatusName : $StatusName";
					$loggerSub->log($str1);	
					$errorMessage = '';
					if ($StatusLower == 'failed') {
						$errorMessage = "There has been an error in importing your list [$importName] to Studio.";
					} else if ($StatusLower == 'qualityrejected') {
						$errorMessage = "There has been an error in importing your list [$importName] to Studio because more than 50% of the records were erroneous.";
					} else if ($StatusLower == 'canceled') {
						$errorMessage = "This import [$importName] was canceled by the user.";
					} else if ($StatusLower == 'completed') {
						$errorMessage = "Your import [$importName] is complete.";
					}

					if ($errorMessage != '') {	
						$noticeType = 'Upload CSV';
						$NotificationTitle = '';
						$Notification = $errorMessage;
						$moreData['importName'] = $importName;	
						$moreData['contactID'] = $contactID;	
						$NotificationLink = "$davinciDomain"."admin/showContact.php?cid=$contactID";
						$moreData['actionHandler'] = 'ON';
						$moreData['NotificationLink'] = $NotificationLink;
						
						$loggerSub->log("-----CALL saveNotification1 $importUser, $acctID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink");  
						
						$NotificationResult = saveNotification1('', $importUser, $acctID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink, $moreData);
					}				
					break;
				}					
			}//for alllist
		}
	}			
	return $ImportResponse; 
}

function updateImportFromStudioInnerVue($acctID, $allLists){	
	$CurrentConfig = davinciSetConfig();
	$davinciDomain = $CurrentConfig->davinciDomain;

	$errorMessage = '';		
	$Stmt2 = MySQL_Prepare($db,"SELECT * FROM DV_AudienceList WHERE acctID=? and studioStatus<=1 order by id");
	$Stmt2->execute(array($acctID));

	while ($row2 = $Stmt2->fetch()) {		
		$id = $row2['id'];
		$importName = $row2['LISTNAME'];
		$contactID = $row2['contactID'];
		$importUser = $row2['importUser'];		
		
		if(!empty($allLists)){	
			foreach( $allLists as $allList ) {	
				$Name = $allList['Name'];
				$count = $allList['count'];
				$Status = $allList['Status'];										
				$StatusName = $allList['StatusName'];					
				$StatusLower = strtolower($StatusName);

				$str1 = "Name : $Name, count : $count, Status : $Status, StatusName : $StatusName";
				if ($importName == $Name) {						
					$Stmt3 = MySQL_Prepare($db,"update DV_AudienceList set studioStatus=?,LISTCOUNT=? where id=?");
					$Stmt3->execute(array($Status, $count, $id));	
					$str1 = "Name : $Name, count : $count, Status : $Status, StatusName : $StatusName";
					$logger->log($str1);	
					$errorMessage = '';
					if ($StatusLower == 'failed') {
						$errorMessage = "There has been an error in importing your list [$importName] to Studio.";
					} else if ($StatusLower == 'qualityrejected') {
						$errorMessage = "There has been an error in importing your list [$importName] to Studio because more than 50% of the records were erroneous.";
					} else if ($StatusLower == 'canceled') {
						$errorMessage = "This import [$importName] was canceled by the user.";
					} else if ($StatusLower == 'completed') {
						$errorMessage = "Your import [$importName] is complete.";
					}

					if ($errorMessage != '') {	
						$noticeType = 'Upload CSV';
						$NotificationTitle = '';
						$Notification = $errorMessage;
						$moreData['importName'] = $importName;	
						$moreData['contactID'] = $contactID;	
						$NotificationLink = "$davinciDomain"."admin/showContact.php?cid=$contactID";
						$moreData['actionHandler'] = 'ON';
						$moreData['NotificationLink'] = $NotificationLink;
						
						$logger->log("-----CALL saveNotification1 $importUser, $acctID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink");  						
						$NotificationResult = saveNotification1('', $importUser, $acctID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink, $moreData);
					}				
					break;
				}					
			}//for alllist
		}
	}			
}

function cancelAccount($billAccount, $accountName, $CancelAccount, $billAmount, $CancelMode, $CancelMessage) {
	
	$studioParam = GetStudioAPIParam();
	$POWERACCOUNTID = $studioParam['accountID'];
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	
	$param['POWERACCOUNTID'] = $POWERACCOUNTID;
	$param['EMAIL'] = $EMAIL;
	$param['PWD'] = $PWD;
	$param['PARTNERGUID'] = $PARTNERGUID;
	$param['PARTNERPASSWORD'] = $PARTNERPASSWORD;
	$result = cancelAccountCredential($billAccount, $accountName, $CancelAccount, $billAmount, $CancelMode, $CancelMessage, $param);
	return $result;
}

function cancelAccountCredential($billAccount, $accountName, $CancelAccount, $billAmount, $CancelMode, $CancelMessage, $param) {
	
	
	$logger2 = new FileLogger("cancelAccountCredential.log"); 
	
	$POWERACCOUNTID = $param['POWERACCOUNTID'];
	$EMAIL = $param['EMAIL'];
	$PWD = $param['PWD'];
	$PARTNERGUID = $param['PARTNERGUID'];
	$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
	
	$logger2->log("param ". json_encode($param));
	sleep(2);
	$Across = getAcrossAuthenticateCurl($POWERACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	$logger2->log("Across ". json_encode($Across));
	
	$powerTicket = $Across['userTicket'];
	$Credentials = $Across['Credentials'];
		
	$UpdateAccountRequest   = array
	(		
		"Credentials" => $Credentials,
		"AccountID" => $billAccount,
		"Name" => $accountName,
		"Status" => 2
	);
	
	$logger2->log("UpdateAccountRequest ". json_encode($UpdateAccountRequest));
	
	$UpdateAccountResponse = callServiceCredential("accountservice/UpdateAccount", $UpdateAccountRequest);
	
	$logger2->log("UpdateAccountResponse ". json_encode($UpdateAccountResponse));
	
	$ErrorCode = $UpdateAccountResponse->{"Result"}->{"ErrorCode"};
	
	$logger2->log("ErrorCode : $ErrorCode ");
	
	if ($TEST == 't') {
		echo "<br>UpdateAccountResponse<br>";
		var_dump($UpdateAccountResponse);
		echo "<br>";
	}
	if ($ErrorCode == "") {
		$errorMessage = '';
		$message = '';
		$status = true;
	} else {
		$status = false;
		$message = "UpdateAccountResponse ERROR : <br> ErrorMessage -> ".$UpdateAccountResponse->{"Result"}->{"ErrorMessage"};
		$errorMessage = "UpdateAccountResponse ERROR : <br> ErrorMessage -> ".$UpdateAccountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$UpdateAccountResponse->{"Result"}->{"ExceptionMessage"};		
	}
	$logger2->log("errorMessage : $errorMessage ");

	try {  		
		$BillStatus = 'Cancelled';
		
		$ACCTID = $_SESSION['ACCOUNTID'];
		
		if ($billAccount > 0) {
			if ($ErrorCode == "") {
				$Stmt = MySQL_Prepare($db,"UPDATE DV_signup SET signupStatusMsg4ShortCode=?  WHERE studioAccountID = ?");
				$Stmt->execute(array($BillStatus, $billAccount));

				$Stmt = MySQL_Prepare($db,"UPDATE DV_UserInfo SET userStatus=?  WHERE acctID = ?");
				$Stmt->execute(array($BillStatus, $billAccount));
			
				$cache = new MyPhpFileCache();
    			$cache->expire("GetUserInfoFromDB-v1-db$ACCTID");
    		
    			$logger2->log("remove cache GetUserInfoFromDB-v1-db$ACCTID");
			}
		}
		
		
		
		$EMAIL = $_SESSION['EMAIL'];
		$ACCOUNNAME = $_SESSION['ACCOUNNAME'];
		
		$activityDetail['userName'] = $EMAIL;
		$activityDetail['accountID'] = $billAccount;
		$activityDetail['accountName'] = $accountName;
		$activityDetail['contentID'] = $ACCTID;
		$activityDetail['contentName'] = $ACCOUNNAME;
		if(startsWith($CancelMessage,"Cancel sub-Accounts")){
			$activityDetail['eventName'] = 'Cancel Subaccount';
		} else {
			$activityDetail['eventName'] = 'Cancel Account';
		}
		
		$eventData['parentID'] = $ACCTID;
		$eventData['parentName'] = $ACCOUNNAME;
		$eventData['UpdateAccountErrorMessage'] = $errorMessage;
		$eventData['UpdateAccountErrorCode'] = $ErrorCode;
		
		$activityDetail['eventData'] = $eventData;
		$saveResult = SaveSiteActivity($activityDetail);
		
	}catch(exception $e) {
		$errorMessage .= " ex: ".$e; 
		logDebug("ERROR: UPDATESQL[$sql]");	    			
		logDebug($errorMessage);	    			
		$affected_rows = -1;
		$updsuccess = false; 	
		$allUpdSuccess = false; 			
	}
	
	

	$result['status'] = $status;	
	$result['message'] = $message;
	$result['errorMessage'] = $errorMessage;	
	$result['UpdateAccountRequest'] = $UpdateAccountRequest;	
	$result['UpdateAccountResponse'] = $UpdateAccountResponse;	
	return $result;
}

function sendCancelEmail($CancelMode, $billAccount, $accountName, $billDesc, $CancelMessage, $stripeResult, $stripeResponse) {
	$subject = "";
	$content = "";
	if ($CancelMode == '1') {
		$subject = "Credit issued to customer";
	} else if ($CancelMode == '2') {
		$subject = "Account cancelled";
	} else if ($CancelMode == '3') {
		$subject = "Account canlcelled and credit issued";
	} 
	error_log("sendCancelEmail subject : $subject, CancelMode : $CancelMode", 3, "/efs/log/global.log");	

	if ($subject != "") {
		$subject = "$subject : $accountName [$billAccount]";
		$EMAIL = "billing@mindfireinc.com";
		$cc = "DaVinci@mindfireinc.com";
		$insertTime = date('m/d/Y h:i:s');
		$usersTimezone = 'America/Los_Angeles';
		$insertTime = getReportDetailDate($insertTime, $usersTimezone);
		$CancelMessage = rtrim($CancelMessage,'.');
		$content = $CancelMessage.' at '.$insertTime."<br>
		Description : $billDesc<br>";	
		if (($CancelMode == '1') || ($CancelMode == '3')) {
			$content .= "Stripe Result : $stripeResult<br>
			Stripe Response : $stripeResponse<br>";
		}
		sendsocketlabsEmailAddCC($EMAIL, $subject, $content, "", $cc);
		error_log("sendCancelEmail sendsocketlabsEmailAddCC($EMAIL, $subject, $content, '', $cc)", 3, "/efs/log/global.log");	
	}
	return true;
}

function getRoleIDCredentials($Credentials, $userEmail = null) {
	$RoleListRequest   = array
	(
		
		"Credentials" => $Credentials
		
	);
	$RoleListResponse = callServiceCredential("userservice/GetRoleList", $RoleListRequest);
	$ErrorCode = $RoleListResponse->{"Result"}->{"ErrorCode"};
	$RoleID = "-1";
	if ($ErrorCode == "") {
		$status = 'Success';
		$studioError = '';
		$errorMessage = '';
		
		$RoleLists = $RoleListResponse->{"RoleList"};
		foreach ($RoleLists as $RoleList){
			$RoleID = $RoleList->{'ID'};
			$Name = $RoleList->{'Name'};
			$Name = strtoupper($Name);
			if ( $Name == 'ADMIN' ) {
				break;
			}			
		}
	} else {
		$status = "Fail";
		$errorMessage = "GetRoleList ErrorMessage : ".$RoleListResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$RoleListResponse->{"Result"}->{"ExceptionMessage"};
		$RoleID = "-1";
		$studioError = $RoleListResponse->{"Result"}->{"ErrorMessage"};
	}
	$result['status'] = $status;
	$result['RoleID'] = $RoleID;
	$result['errorMessage'] = $errorMessage;
	$result['studioError'] = $studioError;
	$result['RoleListResponse'] = $RoleListResponse;

	return $result;
}

function getRoleID($ticket, $userEmail = null) {
	$RoleListRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $ticket        
		)
		
	);
	$RoleListResponse = callService("userservice/GetRoleList", $RoleListRequest);
	$ErrorCode = $RoleListResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$status = 'Success';
		$studioError = '';
		$errorMessage = '';
		
		$RoleLists = $RoleListResponse->{"RoleList"};
		foreach ($RoleLists as $RoleList){
			$RoleID = $RoleList->{'ID'};
			$Name = $RoleList->{'Name'};
			$Name = strtoupper($Name);
			if ( $Name == 'ADMIN' ) {
				break;
			}			
		}
	} else {
		$status = "Fail";
		$errorMessage = "GetRoleList ErrorMessage : ".$RoleListResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$RoleListResponse->{"Result"}->{"ExceptionMessage"};
		$RoleID = "-1";
		$studioError = $RoleListResponse->{"Result"}->{"ErrorMessage"};
	}
	$result['status'] = $status;
	$result['RoleID'] = $RoleID;
	$result['errorMessage'] = $errorMessage;
	$result['studioError'] = $studioError;
	$result['RoleListResponse'] = $RoleListResponse;

	return $result;
}

function getPaidStatus($acctID) {
	$userInfo = getDVUserinfo($acctID);
	$paidAccount = $userInfo['paidAccount'];
	return $paidAccount;
}

function getStudioParentID($acctID) {
	$userInfo = getDVUserinfo($acctID);
	$studioParentID = $userInfo['studioParentID'];
	if ($studioParentID) {
	} else {
		$studioParentID = '-1';
	}
	return $studioParentID;
}

function getUsebouncerCredit($TEST) {
	$usebouncerKey = GetCurrentConfig()->usebouncerKey;

	$creditURL = "https://api.usebouncer.com/v1/customer/user/current/credit/";
	$service = curl_init();

	curl_setopt_array($service, array(
	  CURLOPT_URL => 'https://api.usebouncer.com/v1/customer/user/current/credit',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'GET',
	  CURLOPT_HTTPHEADER => array(
	    'x-api-key: '.$usebouncerKey
	  ),
	));

	$response_string = curl_exec($service);
	$creditResponse = json_decode($response_string);
	
	if ($TEST == 't') {
		echo "Check available credits [creditResponse] :<br>";
		echo "usebouncerKey : $usebouncerKey<br>";
		var_dump ($creditResponse);
		echo "<br>";
		echo "<br>";
	}	
	$credits = $creditResponse->credits;
	$result['credits'] = $credits;

	return $result; 


}

function getUsebouncerVerify ($TEST, $email) {
	$usebouncerKey = GetCurrentConfig()->usebouncerKey;
	$email = urlencode($email);
	$creditURL = "https://api.usebouncer.com/v1/email/verify?email=$email";
	$service = curl_init();
	
	//set the url, number of POST vars, POST data
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($service, CURLOPT_URL, $creditURL);
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'x-api-key: '.$usebouncerKey
	));                                                                                                                   

	$response_string = curl_exec($service);
	$statusResponse = json_decode($response_string);
	
	return($statusResponse);
}

function getUsebouncerVerifyLog ($TEST, $email, $acctID, $purl) {
	$usebouncerKey = GetCurrentConfig()->usebouncerKey;
	$emailLog = $email;
	$email = urlencode($email);
	$creditURL = "https://api.usebouncer.com/v1/email/verify?email=$email";
	$service = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($service, CURLOPT_URL, $creditURL);
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'x-api-key: '.$usebouncerKey
	));                                                                                                                   

	$response_string = curl_exec($service);
	$statusResponse = json_decode($response_string);
	
	$status = $statusResponse->{"status"}; 
	if ($TEST == 't') {
		$status = '503';
	}
	if ( (is_numeric($status)) || (str_contains($emailLog, 'test503')) ){
		if($db == null){
			$db = GetPDOMySQL();
		}
		$sql = 'INSERT INTO DV_BouncerError (Id, createDateTime, acctID, email, purl) VALUES (NULL, NOW(), ?, ?, ?) ';
		try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($acctID, $emailLog, $purl));
		} catch(Exception $e){
			$success = false;	
			$ErrorMessage = $e->getMessage();
		}
	}

	return($statusResponse);
}

function getMojoDNS ($TEST, $email) {
	$mojoDNSKey = GetCurrentConfig()->mojoDNSKey;
	$domain = substr(strrchr($email, "@"), 1);
	$domain = urlencode($domain);
	$creditURL = "https://api.mojodns.com/api/dns/$domain/TXT";
	$service = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($service, CURLOPT_URL, $creditURL);
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'authorization: '.$mojoDNSKey
	));                                                                                                                   

	$response_string = curl_exec($service);
	$httpcode = curl_getinfo($service, CURLINFO_HTTP_CODE);
	$statusResponse = json_decode($response_string);
	$result['statusResponse'] = $statusResponse;
	$result['httpcode'] = $httpcode;

	return($result);
}

function getMojoDNS2($TEST, $email) {

	$domainList = array();
	$domainTXT = array();
	
	$logger = new FileLogger("getMojoDNS2.log");
	
	$emailLower = strtolower($email);
	$logger->log("email : $email");
	$domainLower = substr($emailLower, strpos($emailLower, '@') + 1);	
	$domainList[] = $domainLower;
	$domainTXT[] = $domainLower;
	$logger->log("domainLower : $domainLower");
	$cnt = 0;
	$stopCnt = 0;
	$success = false;
	$txtCnt = sizeof($domainTXT);
	do {
		$domain = $domainList[$cnt];
		$cnt++;
		$logger->log("stopCnt : $stopCnt, cnt : $cnt, txtCnt : $txtCnt");
		$results = dns_get_record($domain, DNS_TXT);
		$logger->log("dnsRecords : ".json_encode($results));
		
		foreach ($results as $result) {
			$txt = $result['txt'];
			$txt = strtolower($txt);
			$logger->log("txt : $txt");
			if (strpos($piece, 'v=spf1') !== false) {
				$domainTXT[] = $txt;
				$logger->log("domainTXT : $domainTXT");
			}
			
			$pieces = explode(" ", $txt);
			if ($pieces) {
				foreach( $pieces as $piece ) {
					if (strpos($domainLower, 'email-od.com') !== false) {
					    $success = true;
					    $cnt = $stopCnt+1;
					    $logger->log("email-od.com : $domainLower");
					} else if (strpos($piece, 'include:') !== false) {
						$domainLower = strtolower($piece);
						$domainLower = str_replace('include:', '', $domainLower);
						$domainList[] = $domainLower;
						$logger->log("include : $domainLower");
						
					} else if (strpos($piece, 'redirect=') !== false) {
						$domainLower = strtolower($piece);
						$domainLower = str_replace('redirect=', '', $domainLower);
						$domainList[] = $domainLower;
						$logger->log("redirect : $domainLower");
					}
				}
			}
		}
		if ($success) {
			
		} else {
			$stopCnt = sizeof($domainList);
			$txtCnt = sizeof($domainTXT);
		
			if ($txtCnt > 3) {
				$cnt = $stopCnt+1;
			}
		}
	} while ($cnt < $stopCnt); 
	
	if ($success) {
		$logger->log("true");	
	} else {
		$logger->log("false");	
	}
	$logger->log("end");
	return $success;
}

function appendGoodList($TEST, $logger, $BouncerCache, $createRequest, $importPath, $fileName, $moreDetail) {
	$logTime = date("M d Y H:i:s");
	$logger->log(" 2 appendGoodList start : $logTime" );	
	
	$emailCol = $moreDetail['emailCol'];
	
	//$logger->log(" BouncerCache  : ".json_encode($BouncerCache) );	
	//$logger->log(" createRequest  : ".json_encode($createRequest) );
	
	$datas = array();
	
	foreach( $createRequest as $row1 ) {	
		$index1 = $row1[1];
		$indexFound = false;
		
		//$logger->log(" 2 appendGoodList index1 : $index1" );
		
		foreach( $BouncerCache as $row2 ) {
			$emai2 = $row2[0];
			$index2 = $row2[1];
			$result = $row2[2];
			$EmailProvider = $row2[3];
			if ($index1 == $index2) {
				$indexFound = true;
				break;
			}
		}
		
		if ($indexFound == true) {
			$bounceStatus = mapBouncerValue('status', $result);
			$group1 = array(					
				"index"=>$index1,
				"bounceStatus"=>$bounceStatus,		
				"EmailProvider"=>$EmailProvider,
			);
			//$logger->log(" 2 appendGoodList index1 : $index1 , $bounceStatus , $EmailProvider" );
		} else {
			$group1 = array(					
				"index"=>$index1,
				"bounceStatus"=>'',		
				"EmailProvider"=>'',
			);		
			//$logger->log(" 2 appendGoodList index1 : $index1 , '' , ''" );
		}
		$datas[] = $group1;			
	}
	
	
	
	$importFile = getLogPathEx($importPath,$fileName);
	$backupFile = getLogPathEx($importPath,$fileName.'.bak');
	
	$columnName = ["bounceStatus","EmailProvider"];
	$defaultValue = ["",""];
	$ret = AppendColumnDefaultBlankToCSV($backupFile,$importFile,$columnName,$datas,$defaultValue,$emailCol);
	
	$logger->log(" 4 After AppendColumnToCSV [ret] : ".json_encode($ret) );		
		
	$checkStatus = 'completed';	
	$result = array();
	$result['UsebouncerStatus'] = true;
	$result['requestId'] = $fileName;
	$result['checkStatus'] = $checkStatus;
	$result['importFile'] = $importFile;
	$result['datas'] = $datas;
	$result['AppendColumn'] = $ret;
	

		
	

	return $result;

}


function sendToUsebouncer($TEST, $logger, $createRequest, $importPath, $fileName, $moreDetail) {
	

	$usebouncerKey = GetCurrentConfig()->usebouncerKey;

	$request_string = json_encode($createRequest); 
	$service = curl_init("https://api.usebouncer.com/v1/email/verify/batch/");   
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'x-api-key: '.$usebouncerKey,
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string)
	));                                                                                                                 

	$response_string = curl_exec($service);
	$http_code = curl_getinfo($service, CURLINFO_HTTP_CODE);
	$mustStop = false;
		//$http_code = 505;
	if ($http_code >= 500) {
		// retry
		while ($http_code >= 500) {
			sleep(60);
			$createResponse = json_decode($response_string);
			$logger->log(" 1 retry batch request [createResponse] : ".json_encode($createResponse) );	
			$response_string = curl_exec($service);
			$http_code = curl_getinfo($service, CURLINFO_HTTP_CODE);
		}
	}


		//$http_code = 403;
	$content = '';
	if (($http_code >= 400) && ($http_code < 500)) {
		$mustStop = true;
		$UseCredits = getUsebouncerCredit($TEST);
		$credits = $UseCredits['credits'];
		$logger->log(" 1 sendToUsebouncer [UseCredits] : ".json_encode($UseCredits) );	

		// Send Email
		$EMAIL = 'DaVinci@mindfireinc.com';
		$cc = 'davincimonitoring@gmail.com';
		
		$ACCOUNTID = $moreDetail['ACCOUNTID'];
		$ACCOUNNAME = $moreDetail['ACCOUNNAME'];
		$importName = $moreDetail['importName'];
		$emailCol = $moreDetail['emailCol'];

		$subject = "Error from Bouncer while checking Email deliverability [".$http_code."]";
		 
		$content = "We received error $http_code while verifying listname $importName from account $ACCOUNNAME"."[".$ACCOUNTID."] while checking on email deliverability<br>
		importFile : $importPath$fileName<br>
		credits : $credits";
		sendsocketlabsEmailAddCC($EMAIL, $subject, $content, "", $cc);

	}

	if ($mustStop) {
		$result['UsebouncerStatus'] = false;
		$result['UsebouncerMessage'] = $content;
	} else {
		$createResponse = json_decode($response_string);
		$logger->log(" 1 Create batch request [createResponse] : ".json_encode($createResponse) );	

		$createStatus =  $createResponse->status;
		$requestId =  $createResponse->requestId;
		$created =  $createResponse->created;
		$completed =  $createResponse->completed;

		if (($requestId) &&  ($requestId != '') ) {

			$checkStatus = '';
			$logTime = date("M d Y H:i:s");
			$logger->log(" 2 Start Check status : $logTime" );	
			while ($checkStatus != 'completed') {
				sleep(30);
				$creditURL = "https://api.usebouncer.com/v1/email/verify/batch/$requestId/status/";
				$service = curl_init();

				//set the url, number of POST vars, POST data
				curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($service, CURLOPT_URL, $creditURL);
				curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
				curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
					'x-api-key: '.$usebouncerKey
				));                                                                                                                   

				$response_string = curl_exec($service);
				$statusResponse = json_decode($response_string);

				$logger->log(" 2 Check status [statusResponse] : ".json_encode($statusResponse) );	
							
				$checkStatus = $statusResponse->status;

			} 
			$logTime = date("M d Y H:i:s");
			$logger->log(" 2 Finish Check status : $logTime" );	

			if ($checkStatus == 'completed') {

				$creditURL = "https://api.usebouncer.com/v1/email/verify/batch/$requestId/";
				$service = curl_init();

				//set the url, number of POST vars, POST data
				curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($service, CURLOPT_URL, $creditURL);
				curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
				curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
					'x-api-key: '.$usebouncerKey
				));                                                                                                                   

				$response_string = curl_exec($service);
				$resultResponse = json_decode($response_string);
				
				$logger->log(" 3 Get results  [resultResponse] : ".json_encode($resultResponse) );						
				
				
				// save $resultResponse to /efs
				
				$bouncerFileName = getLogPathEx("bouncer",$requestId.'.csv');
				file_put_contents($bouncerFileName,json_encode($resultResponse));
				
				$datas = array();
				foreach( $resultResponse as $row1 ) {	
					$index = $row1->name;
					$bounceStatus = $row1->status;			
					$EmailProvider = $row1->provider;	
					$group1 = array(					
						"index"=>$index,
						"bounceStatus"=>$bounceStatus,		
						"EmailProvider"=>$EmailProvider,
					);					
					$datas[] = $group1;			
				}
				$logger->log(" 4 Before AppendColumnToCSV [datas] : ".json_encode($datas) );
				
				$createRequest2 = $moreDetail['createRequest2'];
				$resultCacheAll = $moreDetail['resultCacheAll'];
				
				$logger->log(" 4 Before AppendColumnToCSV [createRequest2] : ".json_encode($createRequest2) );
				$logger->log(" 4 Before AppendColumnToCSV [resultCacheAll] : ".json_encode($resultCacheAll) );
				
				foreach( $resultCacheAll as $row1 ) {
					
					// add data from bouncerCacheResult
					
					$email = $row1[0];
					$index1 = $row1[1];
					$result = $row1[2];
					$EmailProvider = $row1[3];
					
					$indexFound = false;
					foreach( $datas as $row2 ) {
						$index2 = $row2['index'];
						if ($index1 == $index2) {
							$indexFound = true;
							break;
						}
					}
					
					if ($indexFound == false) {
						$bounceStatus = mapBouncerValue('status', $result);
						
						$group1 = array(					
							"index"=>$index1,
							"bounceStatus"=>$bounceStatus,		
							"EmailProvider"=>$EmailProvider,
						);					
						$datas[] = $group1;	
					}
				}
				
				$logger->log(" 4 data from bouncerCacheResult [datas] : ".json_encode($datas) );
				
				//$importFile = $importPath.$fileName;
				//$backupFile = $importPath.$fileName.'.bak';
				$importFile = getLogPathEx($importPath,$fileName);
				$backupFile = getLogPathEx($importPath,$fileName.'.bak');
				//$ret = AppendColumnDefaultToCSV($backupFile,$importFile,"bounceStatus",$datas,"undeliverable");
				//$ret = AppendColumnDefaultBlankToCSV($backupFile,$importFile,"bounceStatus",$datas,"undeliverable",$emailCol);
				
				$columnName = ["bounceStatus","EmailProvider"];
				$defaultValue = ["undeliverable","unknown"];
				$ret = AppendColumnDefaultBlankToCSV($backupFile,$importFile,$columnName,$datas,$defaultValue,$emailCol);
				
				$logger->log(" 4 After AppendColumnToCSV [ret] : ".json_encode($ret) );		
				
				
			}
			$result = array();
			$result['UsebouncerStatus'] = true;
			$result['createResponse'] = $createResponse;
			$result['createStatus'] = $createStatus;
			$result['requestId'] = $requestId;
			$result['created'] = $created;
			$result['completed'] = $completed;
			$result['statusResponse'] = $statusResponse;
			$result['checkStatus'] = $checkStatus;
			$result['resultResponse'] = $resultResponse;
			$result['importFile'] = $importFile;
			$result['datas'] = $datas;
			$result['AppendColumn'] = $ret;
		} else {
			$result['UsebouncerStatus'] = false;
		}
	}

	return $result;

}

function doUsebouncerSample($TEST, $logger, $createRequest, $importPath, $fileName, $moreDetail) {
	

	$usebouncerKey = GetCurrentConfig()->usebouncerKey;

	$request_string = json_encode($createRequest); 
	
	$logger->log(" 1 doUsebouncerSample request [createRequest] : ".json_encode($createRequest) );	
	
	$service = curl_init("https://api.usebouncer.com/v1/email/verify/batch/sample/");   
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'x-api-key: '.$usebouncerKey,
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string)
	));                                                                                                                 

	$response_string = curl_exec($service);
	$http_code = curl_getinfo($service, CURLINFO_HTTP_CODE);
	$mustStop = false;
		//$http_code = 505;
	if ($http_code >= 500) {
		// retry
		while ($http_code >= 500) {
			sleep(60);
			$createResponse = json_decode($response_string);
			$logger->log(" 1 retry batch request [createResponse] : ".json_encode($createResponse) );	
			$response_string = curl_exec($service);
			$http_code = curl_getinfo($service, CURLINFO_HTTP_CODE);
		}
	}
	
	$ACCOUNTID = $moreDetail['ACCOUNTID'];
	$ACCOUNNAME = $moreDetail['ACCOUNNAME'];
	$importName = $moreDetail['importName'];
	$emailCol = $moreDetail['emailCol'];


		//$http_code = 403;
	$content = '';
	if (($http_code >= 400) && ($http_code < 500)) {
		$mustStop = true;
		$UseCredits = getUsebouncerCredit($TEST);
		$credits = $UseCredits['credits'];
		$logger->log(" 1 doUsebouncerSample [UseCredits] : ".json_encode($UseCredits) );	

		// Send Email
		$EMAIL = 'DaVinci@mindfireinc.com';
		$cc = 'davincimonitoring@gmail.com';
		
		

		$subject = "Error from Bouncer Sample while checking Email deliverability [".$http_code."]";
		 
		$content = "We received error $http_code while verifying listname $importName from account $ACCOUNNAME"."[".$ACCOUNTID."] while checking on email deliverability<br>
		importFile : $importPath$fileName<br>
		credits : $credits";
		sendsocketlabsEmailAddCC($EMAIL, $subject, $content, "", $cc);

	}

	if ($mustStop) {
		$result['UsebouncerStatus'] = false;
		$result['UsebouncerMessage'] = $content;
	} else {
		$createResponse = json_decode($response_string);
		$logger->log(" 1 Create batch request [doUsebouncerSample] : ".json_encode($createResponse) );	

		$bounceEstimate =  $createResponse->bounceEstimate;
		$requestId =  $createResponse->id;
		$finished =  $createResponse->finished;
		$logger->log("requestId : $requestId,  bounceEstimate : $bounceEstimate , finished : $finished" );	
		/*
		while ($http_code >= 500) {
			sleep(60);
		}
		*/
		if ($finished == true) {
			$logger->log("finished is true" );	
		} else {
			$logger->log("finished is false" );	
			$loopCount = 0;
			while ($finished == false) {
				sleep(30);
				$creditURL = "https://api.usebouncer.com/v1/email/verify/batch/sample/$requestId";
				$service = curl_init();

				//set the url, number of POST vars, POST data
				curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($service, CURLOPT_URL, $creditURL);
				curl_setopt($service, CURLOPT_CUSTOMREQUEST, 'GET');
				curl_setopt($service, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
					'x-api-key: '.$usebouncerKey
				));                                                                                                                   

				$response_string = curl_exec($service);
				$createResponse = json_decode($response_string);

				$bounceEstimate =  $createResponse->bounceEstimate;
				$requestId =  $createResponse->id;
				$finished =  $createResponse->finished;
				$logger->log("loopCount : $loopCount , requestId : $requestId ,  bounceEstimate : $bounceEstimate , finished : $finished" );	
				$loopCount++;
				if ($loopCount > 10) {
					$finished = true;	
				}
			} 
		}
		
		$result['UsebouncerStatus'] = true;
		$result['bounceEstimate'] = $bounceEstimate;
		$result['requestId'] = $requestId;
		$result['finished'] = $finished;
		
	}

	return $result;

}

function doUsebouncer($TEST, $emailCol, $importPath, $fileName, $moreDetail) {
	$logger = new FileLogger("Usebouncer.log");  // file name 
	//$importFile = $importPath.$fileName;
	//$backupFile = $importPath.$fileName.'.bak';
	$importFile = getLogPathEx($importPath,$fileName);
	$backupFile = getLogPathEx($importPath,$fileName.'.bak');
	$getEmailStatus = '';
	$copyStatus = '';
	$result['UsebouncerStatus'] = true;
	$result['stopImport'] = false;
	$result['goodList'] = false;
	$logger->log(" 1 doUsebouncer [moreDetail] : ".json_encode($moreDetail) );
	
	$EMAIL = $moreDetail['EMAIL'];
	$ACCOUNTID = $moreDetail['ACCOUNTID'];
	$ACCOUNNAME = $moreDetail['ACCOUNNAME'];
	$importName = $moreDetail['importName'];
	
	
	
	if (!copy($importFile, $backupFile)) {
		$copyStatus = "doUsebouncer Cannot copy $importFile to $backupFile";
		$logger->log(" copyStatus=$copyStatus" );
		$result['UsebouncerStatus'] = false;
		$result['copyStatus'] = $copyStatus;
		
	} else {
		
		$noticeType = $moreDetail['noticeType'];
		$NotificationTitle = $moreDetail['NotificationTitle'];
		$Notification1 = $moreDetail['Notification1'];
		$Notification2 = $moreDetail['Notification2'];
		$NotificationLink = $moreDetail['NotificationLink'];
		$logger->log("saveNotification1 $EMAIL, $ACCOUNTID, $noticeType, $NotificationTitle, $Notification1, '', $NotificationLink");
		$NotificationResult = saveNotification1('', $EMAIL, $ACCOUNTID, $noticeType, $NotificationTitle, $Notification1, '', $NotificationLink, $moreDetail);

		$copyStatus = "doUsebouncer Can copy $importFile to $backupFile";
		$emailData = GetColumnFromCSV($importFile,$emailCol);
		$emailSize = sizeof($emailData);
		
		$logger->log(" 1 GetColumnFromCSV [emailData] : ".json_encode($emailData) );
		$logger->log(" 1 GetColumnFromCSV [emailSize] : $emailSize");
		$createRequest = array();
		$createRequest2 = array();
		
		$sendEmail = false;

		if ( $emailSize > 0) {
			foreach( $emailData as $row1 ) {	
				$email = $row1['email'];
				$index = $row1['index'];
				$group1 = array(					
					"email"=>$email,
					"name"=>$index			
				);	
				$group2 = array(					
					$email,$index			
				);	
				$createRequest[] = $group1;
				$createRequest2[] = $group2;
			}
			
			$BouncerConfig = getBouncerConfig();
			$bounceEstimateMin = $BouncerConfig['bounceEstimateMin'];
			$bounceEstimateMax = $BouncerConfig['bounceEstimateMax'];
			$usebouncerSample = $BouncerConfig['usebouncerSample'];
			
			$resultCache = getBouncerCache($createRequest2);
			$resultCacheAll = $resultCache['all'];
			$resultCacheBouncer = $resultCache['bouncer'];
			
			$allSize = sizeof($resultCacheAll);
			$bouncerSize = sizeof($resultCacheBouncer);
			
			$logger->log(" 1 doUsebouncer [resultCacheAll] : ".json_encode($resultCacheAll) );
			$logger->log(" 1 doUsebouncer [resultCacheBouncer] : ".json_encode($resultCacheBouncer) );
			
			$logger->log(" 1 allSize : $allSize , bouncerSize : $bouncerSize , usebouncerSample : $usebouncerSample" );
			
			$moreDetail['emailCol'] = $emailCol;
			
			if ($bouncerSize == 0) {
				$bounceEstimateret = 0;
			} else {
				if ($bouncerSize >= $usebouncerSample) {
					$result = doUsebouncerSample($TEST, $logger, $resultCacheBouncer, $importPath, $fileName, $moreDetail);
					$logger->log(" 1 doUsebouncer [doUsebouncerSample] : ".json_encode($result) );
					$bounceEstimateret = intval($result['bounceEstimate']);
				} else {
					$bounceEstimateret = 6;
				}
			}
			
			$bounceCount = round(($bounceEstimateret * $bouncerSize) / 100);
			
			$bounceEstimate = round(($bounceCount /  $allSize) * 100);
			
			
			$logger->log(" 1 bounceEstimateret : $bounceEstimateret , bounceEstimate : $bounceEstimate" );
			
			$result['bounceCount'] = $bounceCount;
			$result['$bounceEstimateret'] = $bounceEstimateret;
			$result['bounceEstimate'] = $bounceEstimate;
			$result['bounceEstimateMin'] = $bounceEstimateMin;
			$result['bounceEstimateMax'] = $bounceEstimateMax;
			$result['usebouncerSample'] = $usebouncerSample;
			
			$pos = strpos($importName, 'poorlist');
			if ($pos !== false) {
				$bounceEstimate = 30;
			}
			$pos = strpos($importName, 'nicelist');
			if ($pos !== false) {
				$bounceEstimate = 6;
			}
			$pos = strpos($importName, 'goodlist');
			if ($pos !== false) {
				$bounceEstimate = 1;
			}
			
			
			
			
			
			$logger->log(" 1 importName : $importName , bounceEstimateMin : $bounceEstimateMin , bounceEstimateMax : $bounceEstimateMax , bounceEstimate : $bounceEstimate" );
			
			if ($bounceEstimate > $bounceEstimateMax) {
				$bouncerSample = 'poorlist';
				$getEmailStatus = "Any file with bounceEstimate [$bounceEstimate] > bounceEstimateMax [$bounceEstimateMax] records will stop importing [Very poor list].";
				$result['UsebouncerStatus'] = false;
				$result['getEmailStatus'] = $getEmailStatus;
				$result['stopImport'] = true;
				$logger->log(" 1 stop importing [result] : $getEmailStatus" );
				$sendEmail = true;
				
				$noticeType = $moreDetail['noticeType'];
				$NotificationTitle = $moreDetail['NotificationTitle'];
				$Notification1 = "Your file [$importName] has over 10% of bad email addresses. Sending out emails using this file will increase your SPAM score and will adversely impact your email deliverability. Please check your email for more information.";
				$Notification2 = $moreDetail['Notification2'];
				$NotificationLink = $moreDetail['NotificationLink'];
				$NotificationResult = saveNotification1('', $EMAIL, $ACCOUNTID, $noticeType, $NotificationTitle, $Notification1, '', $NotificationLink, $moreDetail);
				
				
				$cc = 'davincimonitoring@gmail.com';
				
		
				$subject = "Email List [$importName] in Account $ACCOUNNAME has more than 10% estimated bounce rate";
				
				$content = "Hi there:<br><br>
Your file has over 10% of bad email addresses. Typically speaking, when we see that your data contains > 10% records that have invalid/bad emails, it is indicative of data that has not been recently opted-in. Sending out emails using this file will increase your SPAM score and will adversely impact your email deliverability. We recommend you review your data and be sure you are only targeting opted-in records as per our AUP.<br><br>
If you feel that you are receiving this in error we are happy to review your opt-in policy for the data in question. The good news is, we are happy to have a discussion with you on your options here. Please contact us at support@mindfireinc.com to schedule an appointment.<br><br>
Alternatively, you can remove the email column and upload this list. You should be able to use this list for Direct Mail or Social media Ads, provided you have the correct mailing address for each contact.<br><br>
Thank you.<br><br>
Team MindFire.<br>";

				sendsocketlabsEmailAddCC($EMAIL, $subject, $content, "", $cc);
				
			} else {
				
				$bouncerSample = '';
				
				$result['createRequest'] = $createRequest;
				$moreDetail['createRequest'] = $createRequest;
				$moreDetail['createRequest2'] = $createRequest2;
				$moreDetail['resultCacheAll'] = $resultCacheAll;
				$moreDetail['resultCacheBouncer'] = $resultCacheBouncer;
				$moreDetail['emailCol'] = $emailCol;
				
				if ($bouncerSize == 0) {
					$result = appendGoodList($TEST, $logger, $resultCacheAll, $createRequest2, $importPath, $fileName, $moreDetail);
				} else {
					$result = sendToUsebouncer($TEST, $logger, $resultCacheBouncer, $importPath, $fileName, $moreDetail);
				}
				
				$result['copyStatus'] = $copyStatus;
				$result['bounceEstimate'] = $bounceEstimate;
				$result['bounceEstimateMin'] = $bounceEstimateMin;
				$result['bounceEstimateMax'] = $bounceEstimateMax;
				
				$logger->log(" 1 sendToUsebouncer [result] : ".json_encode($result) );
			}
			
			$sql = 'update DV_importFile set bouncerSample=?,emailCol=? where AccountID=? and importName=?';
			logDebug("ACCOUNTID : $ACCOUNTID, importName : $importName, bouncerSample : $bouncerSample");
             try {
             	if($db == null){
					$db = GetPDOMySQL();
				}
                $Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($bouncerSample, $emailCol, $ACCOUNTID, $importName));
            } catch(Exception $e){
                $ErrorMessage = $e->getMessage();
                $logger->log(" 1 DV_importFile [ErrorMessage] : $ErrorMessage" );
            }
			
		} else {
			$getEmailStatus = "Cannot find any valid emails.";
			$result['UsebouncerStatus'] = false;
			$result['getEmailStatus'] = $getEmailStatus;
			$logger->log(" 1 sendToUsebouncer [result] : $getEmailStatus" );
			$sendEmail = true;
			
			
			// 0610
			
		}
		
		if ($sendEmail) {
			// Send Email
			$EMAIL = 'DaVinci@mindfireinc.com';
			$cc = 'davincimonitoring@gmail.com';
			
			
	
			$subject = "Skip Bouncer while checking Email deliverability";
			 
			$content = "We skip bouncer while verifying listname $importName from account $ACCOUNNAME"."[".$ACCOUNTID."] while checking on email deliverability<br>
			importFile : $importPath$fileName<br>
			credits : $credits<br>
			reason : ".$result['getEmailStatus']."<br>";
			sendsocketlabsEmailAddCC($EMAIL, $subject, $content, "", $cc);
		}
		
		$logger->log(" result: ".json_encode($result) );

		
	}
	return $result;
}

function getBouncerCache($createRequest2) {
	
	// $mode = 'all' , 'bouncer' : only result = -1
	$logger = new FileLogger("getBouncerCache.log");
	$BouncerCache = getBouncerConfig();
	$bouncerCache = $BouncerCache['bouncerCache'];
	$request = array(
		"command" => "cacheResult",
		"provider" => true,
		"emails" => $createRequest2,
		"days" => 90
	);
	
		
	$request_string = json_encode($request); 
	
	$logger->log("request = ". json_encode($request) );	
	
	
	
	$service = curl_init($bouncerCache);   
	curl_setopt($service, CURLOPT_SSL_VERIFYPEER, false);    //Add to avoid EB old CA
	curl_setopt($service, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($service, CURLOPT_POSTFIELDS, $request_string);                                                                  
	curl_setopt($service, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($service, CURLOPT_HTTPHEADER, array(                                                                          
		'x-api-key: '.$usebouncerKey,
		'Content-Type: application/json',                                                                                
		'Content-Length: ' . strlen($request_string)
	));                                                                                                                 
	
	$response_string = curl_exec($service);
	$http_code = curl_getinfo($service, CURLINFO_HTTP_CODE);
	$mustStop = false;
	
	
	$logger->log("response_string = $response_string");	
	
	$createRequest = array();
	
	$CacheResponse = json_decode($response_string);
	
	$CacheresultSuccess = $CacheResponse->success;
	
	if ($CacheresultSuccess == true) {
		$logger->log("CacheresultSuccess = true" );	
		
		$Cacheresult = $CacheResponse->result;
		$logger->log("Cacheresult = ". json_encode($Cacheresult) );	
			
		foreach( $Cacheresult as $row1 ) {
				
			$email = $row1[0];
			$index = $row1[1];
			$result = $row1[2];
			$provider = $row1[3];
				
			$logger->log("row1 = ". json_encode($row1) );	
				
			$logger->log("$email , $index, $result, $provider");
				
			if ($result == -1) {
				$group1 = array(					
					"email"=>$email,
					"name"=>$index			
				);
				$createRequest[] = $group1;
			}
		}
		
	} else {
		$logger->log("CacheresultSuccess = false" );	
		$Cacheresult = array();
		
		foreach( $createRequest2 as $row1 ) {
			
			$email = $row1[0];
			$index = $row1[1];
			$result = -1;
			$provider = '';
				
			$logger->log("null row1 = ". json_encode($row1) );	
				
			$logger->log("$email , $index, $result, $provider");
			
			
			$group1 = array(					
				"email"=>$email,
				"name"=>$index			
			);
			$createRequest[] = $group1;
			
			$group2 = array($email,$index,$result,$provider);
			$Cacheresult[] = $group2;
			
		}
	}
		
		
	
	$result = array();
	$result['all'] = $Cacheresult;
	$result['bouncer'] = $createRequest;
	$logger->log("result = ". json_encode($result) );	
	return $result;
}

function saveOffer($TEST, $ACCOUNTID, $EMAIL, $ACCOUNNAME, $OfferID, $OfferDate, $ExpirationDate, $OfferDescription, $OfferAmount, $PayPeriodsNum, $PayPeriod, $AffiliateCode) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$table = 'DV_renewal_account';
		$sql = "INSERT INTO $table(ID,InsertDate,AccountID,userName,OfferID,StartDate,ExpirationDate,OfferDescription,OfferAmount,PayPeriodsNum,PayPeriod,AffiliateCode) ".
			"VALUES(NULL,NOW(),?,?,?,CURDATE(),CURDATE(),?,?,?,?,?) ";
		$insertStmt = MySQL_Prepare($db,$sql);
		$insertStmt->execute(array($ACCOUNTID, $EMAIL, $OfferID, $OfferDescription, $OfferAmount, $PayPeriodsNum, $PayPeriod, $AffiliateCode));
		$affected_rows = $insertStmt->rowCount();
		$insertID = $db->lastInsertId();
		$errorMessage = '';
		$success = true;
		

		$OfferInfo['RenewalID'] = $insertID;			
		$OfferInfo['OfferID'] = $OfferID;			

		$OfferData = json_encode($OfferInfo);

		$Stmt = MySQL_Prepare($db,"UPDATE DV_UserInfo SET OfferData=?  WHERE acctID = ?");
		$Stmt->execute(array($OfferData, $ACCOUNTID));

		$DVRenewalOfferSelect = getRenewalOfferSelect($ACCOUNTID);

		$subject = "$ACCOUNNAME [".$ACCOUNTID."] has renewed their MindFire subscription";
		$BillingEmail = "billing@mindfireinc.com";
		$cc = "DaVinci@mindfireinc.com";
		$insertTime = date('m/d/Y h:i:s');
		$usersTimezone = 'America/Los_Angeles';
		$insertTime = getReportDetailDate($insertTime, $usersTimezone);
		$CancelMessage = rtrim($CancelMessage,'.');
		$content = 
			"$subject at the rate of $OfferAmount per $PayPeriod for $PayPeriodsNum $PayPeriod(s) on $insertTime.<br>
			Record ID : $insertID<br>
			User Name : $EMAIL<br>
			<br>
			MindFire Team";	
		sendsocketlabsEmailAddCC($BillingEmail, $subject, $content, "", $cc);
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
	}
	return array('success'=>$success, 'affected_rows'=>$affected_rows, 'DVRenewalOfferSelect'=>$DVRenewalOfferSelect, 'errorMessage'=>$errorMessage );
}

function getRenewalOfferSelect($acctID) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_renewal_offers a WHERE a.AccountID=? AND a.OfferDate<=NOW() AND a.ExpirationDate>NOW() AND EXISTS (SELECT b.OfferID FROM DV_renewal_account b WHERE b.AccountID=a.AccountID AND b.OfferID=a.OfferID)");		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();
		$DVRenewalOfferSelect = $affected_rows;
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$DVRenewalOfferSelect = -1;	
	}
	return $DVRenewalOfferSelect;

}


function stripFirstLine($text){        
  return substr( $text, strpos($text, "\r\n")+1 );
}



function updatePaidAccountStatus($paidAccount, $acctID) {
	try{
		if ($acctID > 0) {
			if($db == null){
				$db = GetPDOMySQL();
			}	
			//$paidAccount = 'Y';
			$stmt = $db->prepare("UPDATE DV_UserInfo set paidAccount=? where acctID=?");
			$stmt->execute(array($paidAccount, $acctID));	
		}
	}catch(PDOException $ex){
		$errorMessage = "ex: ".$ex; 
	}
	return true;
}

function updateDV_Uiaccess($DV_Uiaccess, $acctID) {
	try{
		if ($acctID > 0) {
			if($db == null){
				$db = GetPDOMySQL();
			}	
			//$paidAccount = 'Y';
			$stmt = $db->prepare("UPDATE DV_UserInfo set DV_Uiaccess=? where acctID=?");
			$stmt->execute(array($DV_Uiaccess, $acctID));	
		}
	}catch(PDOException $ex){
		$errorMessage = "ex: ".$ex; 
	}
	return true;
}

function checkDV_MD5($acctID, $md5hash) {
	$LISTNAME = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_AudienceList where acctID=? and md5checksum=? order by CREATE_DATE desc limit 1");		
		$Stmt->execute(array($acctID, $md5hash));
		$affected_rows = $Stmt->rowCount();	
		while ($row = $Stmt->fetch()) {
			$LISTNAME = $row['LISTNAME'];			
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	$result['LISTNAME'] = trim($LISTNAME);
	return $result;
}

function getDVProductInfo($productCode) {
	if ($productCode == '') {
		$productCode = 'default';
	}
	$page1Content = '';
	$page2Content = '';
	$emailLimit = -1;
	$emailOverage = -1;
	$contactLimit = -1;
	$contactOverage = -1;
	$smsLimit = -1;
	$smsOverage = -1;
	$enrichmentLimit = -1;
	$enrichmentOverage = -1;
	$contentSearchLimit = -1;
	$contentSearchOverage = -1;
	$contentLaunchLimit = -1;
	$contentLaunchOverage = -1;
	$UiAccess = 1;
	$amount = 0;
	$payperiod = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Product where productCode=? limit 1");		
		$Stmt->execute(array($productCode));
		$affected_rows = $Stmt->rowCount();
		if ($affected_rows > 0) {

		} else {
			$productCode = 'default';
			$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Product WHERE productCode=? limit 1");
			$Stmt->execute(array($productCode));
		}
		while ($row = $Stmt->fetch()) {
			$page1Content = $row['page1Content'];			
			$page2Content = $row['page2Content'];
			
			$amount = $row['amount'];
			$payperiod = $row['payperiod'];
			$emailLimit = $row['emailLimit'];
			$emailOverage = $row['emailOverage'];
			$contactLimit = $row['contactLimit'];
			$contactOverage = $row['contactOverage'];
			$smsLimit = $row['smsLimit'];
			$smsOverage = $row['smsOverage'];
			$enrichmentLimit = $row['enrichmentLimit'];
			$enrichmentOverage = $row['enrichmentOverage'];
			$contentSearchLimit = $row['contentSearchLimit'];
			$contentSearchOverage = $row['contentSearchOverage'];
			$contentLaunchLimit = $row['contentLaunchLimit'];
			$contentLaunchOverage = $row['contentLaunchOverage'];
			$ProductIncluded = getProductIncluded($productCode);
			$UiAccess = $ProductIncluded['UiAccess'];
			if (is_numeric($emailLimit)) { } else { $emailLimit = -1; }
			if (is_numeric($emailOverage)) { } else { $emailOverage = -1; }
			if (is_numeric($contactLimit)) { } else { $contactLimit = -1; }
			if (is_numeric($contactOverage)) { } else { $contactOverage = -1; }
			if (is_numeric($smsLimit)) { } else { $smsLimit = -1; }
			if (is_numeric($smsOverage)) { } else { $smsOverage = -1; }
			if (is_numeric($enrichmentLimit)) { } else { $enrichmentLimit = -1; }
			if (is_numeric($enrichmentOverage)) { } else { $enrichmentOverage = -1; }
			if (is_numeric($contentSearchLimit)) { } else { $contentSearchLimit = -1; }
			if (is_numeric($contentSearchOverage)) { } else { $contentSearchOverage = -1; }
			if (is_numeric($contentLaunchLimit)) { } else { $contentLaunchLimit = -1; }
			if (is_numeric($contentLaunchOverage)) { } else { $contentLaunchOverage = -1; }
			if (is_numeric($UiAccess)) { } else { $UiAccess = 1; }
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	$result['page1Content'] = trim($page1Content);
	$result['page2Content'] = trim($page2Content);
	$result['amount'] = $amount;
	$result['payperiod'] = $payperiod;
	$result['emailLimit'] = $emailLimit;
	$result['emailOverage'] = $emailOverage;
	$result['contactLimit'] = $contactLimit;
	$result['contactOverage'] = $contactOverage;
	$result['smsLimit'] = $smsLimit;
	$result['smsOverage'] = $smsOverage;	
	$result['enrichmentLimit'] = $enrichmentLimit;
	$result['enrichmentOverage'] = $enrichmentOverage;
	$result['contentSearchLimit'] = $contentSearchLimit;
	$result['contentSearchOverage'] = $contentSearchOverage;
	$result['contentLaunchLimit'] = $contentLaunchLimit;
	$result['contentLaunchOverage'] = $contentLaunchOverage;
	$result['UiAccess'] = $UiAccess;
	return $result;
}

function getAffiliateInfo($AffiliateID) {
	$page1Content = '';
	$page2Content = '';
	$webinarpageContent = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Affiliate where AffiliateID=? limit 1");		
		$Stmt->execute(array($AffiliateID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$page1Content = $row['page1Content'];			
			$page2Content = $row['page2Content'];			
			$webinarpageContent = $row['webinarpageContent'];			
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	$result['page1Content'] = trim($page1Content);
	$result['page2Content'] = trim($page2Content);
	$result['webinarpageContent'] = trim($webinarpageContent);
	return $result;
}

function getPromoInfo($AffiliateID, $productCode) {
	$page1Content = '';
	$page2Content = '';
	$webinarpageContent = '';
	$emailLimit = -1;
	$emailOverage = -1;
	$contactLimit = -1;
	$contactOverage = -1;
	$smsLimit = -1;
	$smsOverage = -1;
	$enrichmentLimit = -1;
	$enrichmentOverage = -1;
	$contentSearchLimit = -1;
	$contentSearchOverage = -1;
	$contentLaunchLimit = -1;
	$contentLaunchOverage = -1;
	$UiAccess = 1;
	if ($AffiliateID) {
	} else {
		$AffiliateID = '';
	}
	if ($productCode) {
	} else {
		$productCode = '';
	}
	if ($productCode == '') {
		$productCode = 'default';
	}
	if ($AffiliateID != '') {
		$Affiliate = getAffiliateInfo($AffiliateID);
		$page1Affiliate = $Affiliate['page1Content'];
		$page2Affiliate = $Affiliate['page2Content'];
		$webinarpageAffiliate = $Affiliate['webinarpageContent'];
		$source = 'Affiliate';
	} else {
		$page1Affiliate = '';
		$page2Affiliate = '';
		$webinarpageAffiliate = '';
	}
	
	if ($productCode != '') {
		$Product = getDVProductInfo($productCode);
		$page1Product = $Product['page1Content'];
		$page1Product = $Product['page1Content'];
		$amount = $Product['amount'];
		$payperiod = $Product['payperiod'];
		$webinarpageProduct = $Product['page1Content'];
		$emailLimit = $Product['emailLimit'];
		$emailOverage = $Product['emailOverage'];
		$contactLimit = $Product['contactLimit'];
		$contactOverage = $Product['contactOverage'];
		$smsLimit = $Product['smsLimit'];
		$smsOverage = $Product['smsOverage'];
		$enrichmentLimit = $Product['enrichmentLimit'];
		$enrichmentOverage = $Product['enrichmentOverage'];
		$contentSearchLimit = $Product['contentSearchLimit'];
		$contentSearchOverage = $Product['contentSearchOverage'];
		$contentLaunchLimit = $Product['contentLaunchLimit'];
		$contentLaunchOverage = $Product['contentLaunchOverage'];
		$UiAccess = $Product['UiAccess'];
		$source = 'product';
	} else {
		$page1Product = '';
		$page2Product = '';
		$webinarpageProduct = '';
	}
	
	if ($page1Affiliate != '') {
		$page1Content = $page1Affiliate;
	} else if ($page1Product != '') {
		$page1Content = $page1Product;
	}
	if ($page2Affiliate != '') {
		$page2Content = $page2Affiliate;
	} else if ($page2Product != '') {
		$page2Content = $page2Product;
	}
	if ($webinarpageAffiliate != '') {
		$webinarpageContent = $webinarpageAffiliate;
	} else if ($webinarpageProduct != '') {
		$webinarpageContent = $webinarpageProduct;
	}
	$result['source'] = trim($source);
	$result['page1Content'] = trim($page1Content);
	$result['page2Content'] = trim($page2Content);
	$result['webinarpageContent'] = trim($webinarpageContent);
	$result['emailLimit'] = $emailLimit;
	$result['emailOverage'] = $emailOverage;
	$result['contactLimit'] = $contactLimit;
	$result['contactOverage'] = $contactOverage;
	$result['smsLimit'] = $smsLimit;
	$result['smsOverage'] = $smsOverage;
	$result['enrichmentLimit'] = $enrichmentLimit;
	$result['enrichmentOverage'] = $enrichmentOverage;
	$result['contentSearchLimit'] = $contentSearchLimit;
	$result['contentSearchOverage'] = $contentSearchOverage;
	$result['contentLaunchLimit'] = $contentLaunchLimit;
	$result['contentLaunchOverage'] = $contentLaunchOverage;
	$result['UiAccess'] = $UiAccess;
	return $result;
}

function getAffiliate($AffiliateID) {
	$AffiliateCookie = '';
	if ($AffiliateID) {

	} else {
		$AffiliateID = '';
	}
	if ($AffiliateID == '') {
		$AffiliateCookie = $_COOKIE["DavinciAffiliate"];
		if ($AffiliateCookie) {
			$response = json_decode($AffiliateCookie);
			$AffiliateID = $response->ID;
		} else {
			$AffiliateID = '';
		}
	}
	$result['AffiliateID'] = trim($AffiliateID);
	$result['AffiliateCookie'] = $AffiliateCookie;
	return $result;
}

function getDVProductTier($display) {
	$ProductTier = array();
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		if ($display == 'superadmin') {
			$Stmt = MySQL_Prepare($db,"select * from DV_Product where display!='N' order by productName");
			$Stmt->execute();
		} else if ($display == 'all') {
			$Stmt = MySQL_Prepare($db,"select * from DV_Product order by productName");
			$Stmt->execute();
		} else if ($display == 'W') {
			$Stmt = MySQL_Prepare($db,"select * from DV_Product where display=? order by productCode");
			$Stmt->execute(array($display));
		} else {
			$Stmt = MySQL_Prepare($db,"select * from DV_Product where display=? order by amount");
			$Stmt->execute(array($display));
		}
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$productCode = $row['productCode'];
			$productName = $row['productName'];
			$payperiod = $row['payperiod'];
			$amount = $row['amount'];
			$displayAmount = $amount;
			$description = $row['description'];
			$productDescription = $row['productDescription'];
			$page1Content = $row['page1Content'];
			$page2Content = $row['page2Content'];
			$payperiodShow = getPayperiodShow($payperiod);
			$emailLimit = $row['emailLimit'];
 			$emailOverage = $row['emailOverage'];
 			$contactLimit = $row['contactLimit'];
 			$contactOverage = $row['contactOverage'];
 			$smsLimit = $row['smsLimit'];
 			$smsOverage = $row['smsOverage'];
			$enrichmentLimit = $row['enrichmentLimit'];
			$enrichmentOverage = $row['enrichmentOverage'];
			$contentSearchLimit = $row['contentSearchLimit'];
			$contentSearchOverage = $row['contentSearchOverage'];
			$contentLaunchLimit = $row['contentLaunchLimit'];
			$contentLaunchOverage = $row['contentLaunchOverage'];
			$ProductIncluded = getProductIncluded($productCode);
			$UiAccess = $ProductIncluded['UiAccess'];
			$freeItem = $row['freeItem'];
			$productData = $row['productData'];
			$preloadType = $row['preloadType'];
 			if (is_numeric($emailLimit)) { } else { $emailLimit = -1; }
			if (is_numeric($emailOverage)) { } else { $emailOverage = -1; }
			if (is_numeric($contactLimit)) { } else { $contactLimit = -1; }
			if (is_numeric($contactOverage)) { } else { $contactOverage = -1; }
			if (is_numeric($smsLimit)) { } else { $smsLimit = -1; }
			if (is_numeric($smsOverage)) { } else { $smsOverage = -1; }
			if (is_numeric($enrichmentLimit)) { } else { $enrichmentLimit = -1; }
			if (is_numeric($enrichmentOverage)) { } else { $enrichmentOverage = -1; }
			if (is_numeric($contentSearchLimit)) { } else { $contentSearchLimit = -1; }
			if (is_numeric($contentSearchOverage)) { } else { $contentSearchOverage = -1; }
			if (is_numeric($contentLaunchLimit)) { } else { $contentLaunchLimit = -1; }
			if (is_numeric($contentLaunchOverage)) { } else { $contentLaunchOverage = -1; }
			if (is_numeric($UiAccess)) { } else { $UiAccess = 1; }
			if (is_numeric($freeItem)) { } else { $freeItem = -1; }
			//$priceData = ' $'.$amount.'/'.$payperiodShow;
			if ($display == 'all') {
				$amountData = $amount;
			} else {
				$amountData = str_replace('.0000','',$amount);
			}
			$priceData = ' $'.$amountData.'/'.$payperiodShow;
			$productNameUpper = strtoupper($productName);
			
			$docData = json_decode($productData,true);	
			if ($docData) {
				
				$p1 = $docData['productName'];
				if ($p1) {
					$productName = $p1;
				} else {
					$productName = $row['productName'];	
				}
				$p2 = $docData['productImage'];
				if ($p2) {
					$productImage = $p2;
				} else {
					$productImage = 'https://s3.amazonaws.com/mindfiredavinci/img/LGM_Product-Image-min.jpg';
				}
				$p3 = $docData['itemCount'];
				if ($p3) {
					$itemCount = $p3;
				} else {
					$itemCount = '7';
				}
				$p4 = $docData['guaranteeText'];
				if ($p4) {
					$guaranteeText = $p4;
				} else {
					$guaranteeText = "<div ng-show='!SignUpStatusMsg4' class='alert alert-success' role='alert'><i class='fa fa-bell-o' aria-hidden='true'></i> <strong>30 Day Guarantee</strong>: Sign up today, if you don't like it for ANY REASON, let us know and we'll give your money back.</div>";
				}
				$p5 = $docData['productDescription'];
				if ($p5) {
					$productDescription = $p5;
				} else {
					$productDescription = "Includes all $itemCount items shown on the right.";
				}
				$p6 = $docData['displayAmount'];
				if ($p6) {
					$displayAmount = $p6;
				} else {
					$displayAmount = $amount;
				}
				$p7 = $docData['buttonText'];
				if ($p7) {
					$buttonText = $p7;
				} else {
					$buttonText = "YES!  Get The $productName Now!";
				}
				
			} else {
				$productImage = '';
				$itemCount = '';
				$guaranteeText = '';
				$buttonText = '';
			}
			$productNameUpper = strtoupper($productName);
			$group = array(					
				'productData'=>"$payperiod|$amount|$productCode|$emailLimit|$emailOverage|$contactLimit|$contactOverage|$smsLimit|$smsOverage|$enrichmentLimit|$enrichmentOverage|$contentSearchLimit|$contentSearchOverage|$contentLaunchLimit|$contentLaunchOverage|$UiAccess",
				'priceData'=>$priceData,
				'productCode'=>$productCode,
				'productName'=>$productName,
				'productNameUpper'=>$productNameUpper,
				'productImage'=>$productImage,
				'displayAmount'=>$displayAmount,
				'itemCount'=>$itemCount,
				'guaranteeText'=>$guaranteeText,
				'buttonText'=>$buttonText,
				'description'=>$description,
				'payperiod'=>$payperiod,
				'page1Content'=>$page1Content,
				'page2Content'=>$page2Content,
				'productDescription'=>$productDescription,
				'amount'=>$amount,
				'emailLimit'=>$emailLimit,
				'emailOverage'=>$emailOverage,
				'contactLimit'=>$contactLimit,
				'contactOverage'=>$contactOverage,
				'smsLimit'=>$smsLimit,
				'smsOverage'=>$smsOverage,
				'enrichmentLimit'=>$enrichmentLimit,
				'enrichmentOverage'=>$enrichmentOverage,
				'contentSearchLimit'=>$contentSearchLimit,
				'contentSearchOverage'=>$contentSearchOverage,
				'contentLaunchLimit'=>$contentLaunchLimit,
				'contentLaunchOverage'=>$contentLaunchOverage,
				'UiAccess'=>$UiAccess,
				'freeItem'=>$freeItem,
				'preloadType'=>$preloadType,
			);					
			$ProductTier[] = $group;
		}

		$signupDatetime = date('Y-m-d H:i:s');
		$Stmt1 = MySQL_Prepare($db,"SELECT * FROM DV_ProductRate WHERE startTime<=? and endTime>=?");
		$Stmt1->execute(array($signupDatetime, $signupDatetime));
		$affected_rows = $Stmt1->rowCount();		
		while ($row1 = $Stmt1->fetch()) {
			$amount = $row1['amount'];
			$productCode = $row1['productCode'];
			$payperiod = $row1['payperiod'];
			$emailLimit = $row1['emailLimit'];
 			$emailOverage = $row1['emailOverage'];
 			$contactLimit = $row1['contactLimit'];
 			$contactOverage = $row1['contactOverage'];
 			$smsLimit = $row1['smsLimit'];
 			$smsOverage = $row1['smsOverage'];
			$enrichmentLimit = $row1['enrichmentLimit'];
			$enrichmentOverage = $row1['enrichmentOverage'];
			$contentSearchLimit = $row1['contentSearchLimit'];
			$contentSearchOverage = $row1['contentSearchOverage'];
			$contentLaunchLimit = $row1['contentLaunchLimit'];
			$contentLaunchOverage = $row1['contentLaunchOverage'];
			$ProductIncluded = getProductIncluded($productCode);
			$UiAccess = $ProductIncluded['UiAccess'];
 			if (is_numeric($emailLimit)) { } else { $emailLimit = -1; }
			if (is_numeric($emailOverage)) { } else { $emailOverage = -1; }
			if (is_numeric($contactLimit)) { } else { $contactLimit = -1; }
			if (is_numeric($contactOverage)) { } else { $contactOverage = -1; }
			if (is_numeric($smsLimit)) { } else { $smsLimit = -1; }
			if (is_numeric($smsOverage)) { } else { $smsOverage = -1; }
			if (is_numeric($enrichmentLimit)) { } else { $enrichmentLimit = -1; }
			if (is_numeric($enrichmentOverage)) { } else { $enrichmentOverage = -1; }
			if (is_numeric($contentSearchLimit)) { } else { $contentSearchLimit = -1; }
			if (is_numeric($contentSearchOverage)) { } else { $contentSearchOverage = -1; }
			if (is_numeric($contentLaunchLimit)) { } else { $contentLaunchLimit = -1; }
			if (is_numeric($contentLaunchOverage)) { } else { $contentLaunchOverage = -1; }
			if (is_numeric($UiAccess)) { } else { $UiAccess = 1; }
			for ($i = 0; $i <= sizeof($ProductTier)-1; $i++) {
				$node = $ProductTier[$i];
				if ( strtolower($productCode) == strtolower( $node['productCode'] ) ) {
					$payperiodShow = getPayperiodShow($payperiod);
					//$priceData = ' $'.$amount.'/'.$payperiodShow;
					$amountData = str_replace('.0000','',$amount);
					$priceData = ' $'.$amountData.'/'.$payperiodShow;
					$node['productData'] = "$payperiod|$amount|$productCode|$emailLimit|$emailOverage|$contactLimit|$contactOverage|$smsLimit|$smsOverage|$enrichmentLimit|$enrichmentOverage|$contentSearchLimit|$contentSearchOverage|$contentLaunchLimit|$contentLaunchOverage|$UiAccess";
					$node['amount'] = $amount;
					$node['payperiod'] = $payperiod;
					$node['priceData'] = $priceData;
					$node['emailLimit'] = $emailLimit;
					$node['emailOverage'] = $emailOverage;
					$node['contactLimit'] = $contactLimit;
					$node['contactOverage'] = $contactOverage;
					$node['smsLimit'] = $smsLimit;
					$node['smsOverage'] = $smsOverage;
					$node['enrichmentLimit'] = $enrichmentLimit;
					$node['enrichmentOverage'] = $enrichmentOverage;
					$node['contentSearchLimit'] = $contentSearchLimit;
					$node['contentSearchOverage'] = $contentSearchOverage;
					$node['contentLaunchLimit'] = $contentLaunchLimit;
					$node['contentLaunchOverage'] = $contentLaunchOverage;
					$node['UiAccess'] = $UiAccess;
					$ProductTier[$i] = $node;
				}
			}
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $ProductTier;
}


function getProductData($productTier) {	
	if ($productTier) {
    } else {
        $productTier = '';
    }
	$payperiod = '';
	$amount = '';
	$productCode = '';
	$productData = '';
	$payperiodShow = '';
	$emailLimit = -1;
	$emailOverage = -1;
	$contactLimit = -1;
	$contactOverage = -1;
	$smsLimit = -1;
	$smsOverage = -1;
	$enrichmentLimit = -1;
	$enrichmentOverage = -1;
	$contentSearchLimit = -1;
	$contentSearchOverage = -1;
	$contentLaunchLimit = -1;
	$contentLaunchOverage = -1;
	$UiAccess = 1;
	if ($productTier != '') {
		$pieces = explode("|", $productTier);
		if ($pieces) {
			if ($pieces[0] != '') {
				$payperiod = $pieces[0];
				$payperiodShow = getPayperiodShow($payperiod);
			}
			if ($pieces[1] != '') {
				$amount = $pieces[1];
			}
			if ($pieces[2] != '') {
				$productCode = $pieces[2];
			}
			if ($pieces[3] != '') {
				$emailLimit = $pieces[3];
			}
			if ($pieces[4] != '') {
				$emailOverage = $pieces[4];
			}
			if ($pieces[5] != '') {
				$contactLimit = $pieces[5];
			}
			if ($pieces[6] != '') {
				$contactOverage = $pieces[6];
			}
			if ($pieces[7] != '') {
				$smsLimit = $pieces[7];
			}
			if ($pieces[8] != '') {
				$smsOverage = $pieces[8];
			}
			if ($pieces[9] != '') {
				$enrichmentLimit = $pieces[9];
			}
			if ($pieces[10] != '') {
				$enrichmentOverage = $pieces[10];
			}
			if ($pieces[11] != '') {
				$contentSearchLimit = $pieces[11];
			}
			if ($pieces[12] != '') {
				$contentSearchOverage = $pieces[12];
			}
			if ($pieces[13] != '') {
				$contentLaunchLimit = $pieces[13];
			}
			if ($pieces[14] != '') {
				$contentLaunchOverage = $pieces[14];
			}
			$ProductIncluded = getProductIncluded($productCode);
			$UiAccess = $ProductIncluded['UiAccess'];
		}
		$productData = "<br>Da Vinci Plan : $productCode ".'[$'."$amount / $payperiodShow]<br>";
	}
	if ($amount == '') {
		$amount = '0.00';
	}
	$result['productData'] = $productData;
	$result['productCode'] = $productCode;
	$result['amount'] = $amount;
	$result['payperiodShow'] = $payperiodShow;
	$result['payperiod'] = $payperiod;
	$result['emailLimit'] = $emailLimit;
	$result['emailOverage'] = $emailOverage;
	$result['contactLimit'] = $contactLimit;
	$result['contactOverage'] = $contactOverage;
	$result['smsLimit'] = $smsLimit;
	$result['smsOverage'] = $smsOverage;
	$result['enrichmentLimit'] = $enrichmentLimit;
	$result['enrichmentOverage'] = $enrichmentOverage;
	$result['contentSearchLimit'] = $contentSearchLimit;
	$result['contentSearchOverage'] = $contentSearchOverage;
	$result['contentLaunchLimit'] = $contentLaunchLimit;
	$result['contentLaunchOverage'] = $contentLaunchOverage;
	$result['UiAccess'] = $UiAccess;
	return $result;
}

function getPayperiodShow($payperiod) {
	$detail = getPayperiodDetail($payperiod);
	$payperiodShow = $detail['payperiodShow'];
	return $payperiodShow;
}

function getPayperiodDetail($payperiod) {
	$payperiodLower = strtolower($payperiod);
	if (($payperiodLower == 'annual') || ($payperiodLower == 'yearly')) {
		$payperiodShow = 'yr';
		$payperiodDay = '365';
	} else if ($payperiodLower == 'monthly') {
		$payperiodShow = 'mo';
		//$payperiodDay = '30';
		$payperiodDay = '365'; // always add 365 days
	} else if ($payperiodLower == 'once') {
		$payperiodShow = '';
		$payperiodDay = '0';
	} else {
		$payperiodShow = 'mo';				
		$payperiodDay = '0';
	}
	$result['payperiodShow'] = $payperiodShow;
	$result['payperiodDay'] = $payperiodDay;
	return $result;
}

function createAffiliate($AffiliateDetail) {
	
	$AffiliateName = $AffiliateDetail['AffiliateName'];
	$AffiliateAddr = $AffiliateDetail['AffiliateAddr'];
	$AffiliateCity = $AffiliateDetail['AffiliateCity'];
	$AffiliateState = $AffiliateDetail['AffiliateState'];
	$AffiliateZip = $AffiliateDetail['AffiliateZip'];
	$AffiliateCountry = $AffiliateDetail['AffiliateCountry'];
	$AffiliatePhone = $AffiliateDetail['AffiliatePhone'];
	$AffiliateFax = $AffiliateDetail['AffiliateFax'];
	$AffiliateEmail = $AffiliateDetail['AffiliateEmail'];
	$page1Content = $AffiliateDetail['page1Content'];
	$page2Content = $AffiliateDetail['page2Content'];
	$webinarpageContent = $AffiliateDetail['webinarpageContent'];

	$AffiliateID = md5(strtolower($AffiliateName));

	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$sql = 'INSERT INTO DV_Affiliate (AffiliateID, AffiliateName, AffiliateAddr, AffiliateCity, AffiliateState, AffiliateZip, AffiliateCountry, AffiliatePhone, AffiliateFax, AffiliateEmail, page1Content, page2Content, webinarpageContent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE AffiliateID=?, AffiliateName=?, AffiliateAddr=?, AffiliateCity=?, AffiliateState=?, AffiliateZip=?, AffiliateCountry=?, AffiliatePhone=?, AffiliateFax=?, AffiliateEmail=?, page1Content=?, page2Content=?, webinarpageContent=?';
		$insertStmt = MySQL_Prepare($db,$sql);
		$insertStmt->execute(array($AffiliateID, $AffiliateName, $AffiliateAddr, $AffiliateCity, $AffiliateState, $AffiliateZip, $AffiliateCountry, $AffiliatePhone, $AffiliateFax, $AffiliateEmail, $page1Content, $page2Content, $webinarpageContent, $AffiliateID, $AffiliateName, $AffiliateAddr, $AffiliateCity, $AffiliateState, $AffiliateZip, $AffiliateCountry, $AffiliatePhone, $AffiliateFax, $AffiliateEmail, $page1Content, $page2Content, $webinarpageContent));
		$affected_rows = $insertStmt->rowCount();
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}

	$result['AffiliateID'] = $AffiliateID;
	$result['AffiliateDetail'] = $AffiliateDetail;
	return $result;
}



function createMyDVEmail($companyname) {
	$companyname = str_ireplace("http://", '', $companyname);
	$companyname = str_ireplace("https://", '', $companyname);
	$companyname = str_ireplace("www.", '', $companyname);
	$companyname = preg_replace("/[^A-Za-z0-9]/", '-', $companyname);
	$companyname = str_ireplace("----", '-', $companyname);
	$companyname = str_ireplace("---", '-', $companyname);
	$companyname = str_ireplace("--", '-', $companyname);
	return $companyname;
}


function unsubscribeFromDV($acctID, $OutboundContact_ID, $purl) {
	$TEST = '';
	$studioParam = GetStudioAPIParam();
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGaccessD = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	$LISTDEFINITION = '<Filter CriteriaJoinOperator="1"><Criteria Row="1" Field="purl" Operator="equal" Value="'.$purl.'" /></Filter>';
	
	$authRequest = array
	(
		"SelectedAccountID" => $acctID,
		"Email" => $EMAIL, 
		"Password" => $PWD, 
		"PartnerGuid" => $PARTNERGUID, 
		"PartnerPassword" => $PARTNERPASSWORD
	);

	$authResponse = callService("userservice/Authenticate", $authRequest);

	$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
	$powerTicket = '';
	$errorMessage = '';
	$AccountGuid = '';
	if ($ErrorCode == "") {	
		$powerTicket = $authResponse->{"Credentials"}->{"Ticket"};
		$AccountGuid = $authResponse->{"AccountGuid"};
	} else {
		$errorMessage = "Authenticate ERROR : ".$authResponse->{"Result"}->{"ErrorMessage"};
	}
	
	$FieldNames = array("PURL","Guid","id");
	$saveFile = '';
	$results = json_decode(GetContactWithFields($TEST, $powerTicket, $LISTDEFINITION, $acctID, $FieldNames, $saveFile) , true); 

	$Guid = '';
	$contactID = '';
	if( !empty($results['Contact'][0]) ){
		$docs = $results['Contact'][0] ; 
		$Guid = $docs[1];
		$contactID = $docs[2];
	}
	
	
	$RedirectUrl = 'http://studio.m.mdl.io/unsubscribe';
	
	$UnsubscribeRequest   = array
	(
		"AccountGUID" => $AccountGuid,
		"Account_ID" => $acctID,
		"ContactGUID" => $Guid,
		"OutboundContact_ID" => $OutboundContact_ID,
		"RedirectUrl" => $RedirectUrl,
	);
	$UnsubscribeResponse = callService("contactservice/Unsubscribe", $UnsubscribeRequest);
	$ErrorCode = $UnsubscribeResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$errorMessage = '';
	} else {
		$errorMessage = "ContactListCountResponse ERROR : <br> ErrorMessage -> ".$ContactListCountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$ContactListCountResponse->{"Result"}->{"ExceptionMessage"};		
	}
	$result['authRequest'] = $authRequest;
	$result['authResponse'] = $authResponse;
	$result['UnsubscribeRequest'] = $UnsubscribeRequest;
	$result['UnsubscribeResponse'] = $UnsubscribeResponse;
	$result['contactID'] = $contactID;
	
	
	return $result;

}

function MergeByStartWith(&$target,$src,$startWithValue) {
	foreach ($src as $key => $value) {
		if (strncmp($key, $startWithValue, strlen($startWithValue)) === 0) {
			$target->{$key} = $value;
		}
	}
}


function GetAccountList() {
	$studioParam = GetStudioAPIParam();
	$POWERACCOUNTID = $studioParam['accountID'];
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	$accountTicket = getTicketCurl($POWERACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	$AccountRequest   = array
	(
		"Credentials" => array
		(
			"Ticket" => $accountTicket        
		),		
		"SearchString" => '',
   	"Status" => 255,
   	"MaxRows" => 10000,
   	"SortExpression" => "",
   	"StartRowIndex" =>0,
	);
	$AccountResponse = callService("accountservice/GetAccountList", $AccountRequest);
	$ErrorCode = $AccountResponse->{"Result"}->{"ErrorCode"};
	$cnt = 0;
	if ($ErrorCode == "") {
		$errorMessage = '';
		
		$AccountList = $AccountResponse->{"AccountList"};
		$cnt = sizeof($AccountList);
		if ($cnt == 0) {
			$success = true;
		} else {
			$success = false;
		}
	} else {
		$errorMessage = "AccountResponse ERROR : <br> ErrorMessage -> ".$AccountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
		"ExceptionMessage : ".$AccountResponse->{"Result"}->{"ExceptionMessage"};		
		$success = false;
	}
	
	return $AccountList;
}


function GetAccountListStatus($AccountLists, $acctID) {
	$StatusName = '';
	foreach ($AccountLists as $x){
		$Name = $x->{'Name'};
		$StatusName = $x->{'StatusName'};
		$ACCOUNTID = $x->{'AccountID'};		
		if ($ACCOUNTID == $acctID) {
			break;
		}
	}
	return $StatusName;
}

function getUniqueConversionEmail($acctID, $progID, $emailName, $sqlDate) {
	$sql = "select DISTINCT PURL from DV_Conversion_Leads_Email where accountID=? and programID=? and emailName=? and purl != '' and $sqlDate";
	$Stmt4 = MySQL_Prepare($db,$sql);
	$Stmt4->execute(array($acctID, $progID, $emailName));
	$affected_rows4 = $Stmt4->rowCount();
	$UniqueConversion = $affected_rows4;
	return $UniqueConversion;
}

function createContentEncryption($contentID) {
	$t1 = microtime(true);
	$encryptID = md5($t1);
	$pathData['contentID'] = $contentID;
	$pathInfo = json_encode($pathData);
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$table = 'DV_ContentEncryption';
		$insertStmt = MySQL_Prepare($db,"INSERT INTO $table(ID, data, CreateTime) ".
			"VALUES(?, ?, NOW()) ".
			"on duplicate key update ".
			"data=?"
		);
		$insertStmt->execute(array($encryptID, $pathInfo, $pathInfo));
		$affected_rows = $insertStmt->rowCount();
		$success = true;
	}  
	catch(exception $e) {
		$logger->log("ERROR ENCRYPTION: " . $e);
		return [
			'success'=>false,
			'message'=>"CANNOT create content encrypt URL.",
		];
	}
	return [
		'success'=>true,
		'encryptID'=>$encryptID,
	];
}

function saveContentEncryption($pathData, $encryptID=null) {
	$t1 = microtime(true);
	if (empty($encryptID)) $encryptID = md5($t1);
	$pathInfo = json_encode($pathData);
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$table = 'DV_ContentEncryption';
		$insertStmt = MySQL_Prepare($db,"INSERT INTO $table(ID, data, CreateTime) ".
			"VALUES(?, ?, NOW()) ".
			"on duplicate key update ".
			"data=?"
		);
		$insertStmt->execute(array($encryptID, $pathInfo, $pathInfo));
		$affected_rows = $insertStmt->rowCount();
		$success = true;
	}  
	catch(exception $e) {
		$logger->log("ERROR ENCRYPTION: " . $e);
		return [
			'success'=>false,
			'message'=>"CANNOT create content encrypt URL.",
		];
	}
	return [
		'success'=>true,
		'encryptID'=>$encryptID,
	];
}

function getInfoFromIP($TEST, $moreInfo) {
	$logger = new FileLogger("getInfoFromIP.log"); 
	$LogTime = date("M d Y H:i:s");
	$logger->log("getInfoFromIP Start $LogTime $logfile");
	
	$config = GetCurrentConfig();
	$serverMode = $config->serverName;
	
	$email = $moreInfo['email'];
	$accountID = $moreInfo['accountID'];
	$accountName = $moreInfo['accountName'];
	$ipAddress = $moreInfo['ipAddress'];
	$portalLogin = $moreInfo['portalLogin'];
	if ($portalLogin) { } else { $portalLogin = ''; }
	$userInfo = getDVUserinfo($accountID);
	$company = $userInfo['defCompanyName'];
	
	$sendto = 'DaVinci@mindfireinc.com';
	$cc = "davincimonitoring@gmail.com"; 
	if ($portalLogin == '') {
		$emailSubject = "[dv-login-alert-".$serverMode."] $email from $company logged into $accountName";
	} else {
		$emailSubject = "[portal-login-alert-".$serverMode."] $email from $company logged into $accountName";
	}
	
	/*	
	$query = getIPAPI($ipAddress);
		
	$city = $query['city'];
	$state = $query['regionName'];
	$zip = $query['zip'];
	$country = $query['country'];
	$org = $query['org'];
	$isp = $query['isp'];
	$as = $query['as'];
	$emailContent = $query['emailContent'];
	*/
	
	$city = '';
	$state = '';
	$zip = '';
	$country = '';
	$org = '';
	$isp = '';
	$as = '';
	$emailContent = '';
	
	$eventData['ipAddress'] = $ipAddress;
	$eventData['company'] = $company;
	$eventData['city'] = $city;
	$eventData['state'] = $state;
	$eventData['zip'] = $zip;
	$eventData['country'] = $country;
	$eventData['org'] = $org;
	$eventData['isp'] = $isp;
	$eventData['as'] = $as;
	
	$activityDetail['userName'] = $email;
	$activityDetail['eventName'] = 'Login';
	$activityDetail['accountID'] = $accountID;
	$activityDetail['accountName'] = $accountName;
	$activityDetail['contentID'] = '';
	$activityDetail['contentName'] = '';
	$activityDetail['eventData'] = $eventData;
	
	$saveResult = SaveSiteActivity($activityDetail);
	
	// sendsocketlabsEmail($sendto, $emailSubject, $emailContent, '');
	
	sendsocketlabsEmailAddCC($sendto, $emailSubject, $emailContent, "", $cc);
	
	$LogTime = date("M d Y H:i:s");
	$logger->log("getInfoFromIP Stop $LogTime $logfile");	
	
	return true;
}

function getIPAPI($ipAddress) {
	$serverMode = GetCurrentConfig()->serverName;
	return getIPAPIServer($ipAddress,$serverMode);
}

function getIPAPIServer($ipAddress,$serverMode) {
	
	$city = '';
	$state = '';
	$zip = '';
	$country = '';
	$org = '';
	$isp = '';
	$as = '';
	
	/*
http://ip-api.com/docs/api:serialized_php

array (
  'status' => 'success',
  'country' => 'COUNTRY',
  'countryCode' => 'COUNTRY CODE',
  'region' => 'REGION CODE',
  'regionName' => 'REGION NAME',
  'city' => 'CITY',
  'zip' => ZIP CODE,
  'lat' => LATITUDE,
  'lon' => LONGITUDE,
  'timezone' => 'TIME ZONE',
  'isp' => 'ISP NAME',
  'org' => 'ORGANIZATION NAME',
  'as' => 'AS NUMBER / NAME',
  'query' => 'IP ADDRESS USED FOR QUERY',
)
*/
	$serverMode = strtolower($serverMode);
	if(startsWith($serverMode,"production")){
		$apikey = 'GnDX8fw0j9IfdfJ';
	} else if(startsWith($serverMode,"stage")){
		$apikey = 'gYImu0QbiPNaUc5';
	} else {
		$apikey = 'Dbar3cVVoGlF63j';
	}
	
	// https://pro.ip-api.com/php/8.8.8.8?key=GnDX8fw0j9IfdfJ
	$ipURL = 'http://pro.ip-api.com/php/'.$ipAddress.'?key='.$apikey;
	$query = @unserialize(file_get_contents($ipURL));
	if($query && $query['status'] == 'success') {
		
		$status = 'success';
		$city = $query['city'];
		$state = $query['regionName'];
		$zip = $query['zip'];
		$country = $query['country'];
		$org = $query['org'];
		$isp = $query['isp'];
		$as = $query['as'];
		if ($org) {

		} else {
			$org = '';
		}
		if ($as) {

		} else {
			$as = '';
		}
		if ($org == '') {
			$org = $as;
		}
		$errorMessage = '';
		$emailContent = "From IP $ipAddress in $city $state, $country at '$org' using ISP '$isp'";
	} else {
		$status = 'Fail';
		$errorMessage = $query['message'];
		$emailContent = "Unknown location. [$errorMessage]";
	}
	
	$result['status'] = $status;
	$result['city'] = $city;
	$result['state'] = $state;
	$result['zip'] = $zip;
	$result['country'] = $country;
	$result['org'] = $org;
	$result['isp'] = $isp;
	$result['emailContent'] = $emailContent;
	$result['errorMessage'] = $errorMessage;
	$result['ipURL'] = $ipURL;
	
	return $result;
}


function GetEventActivity($TEST, $ACCOUNTID, $ContinuationID, $PartnerTicket, $EventIds, $RequiredColumns, $fd, $td) {
	
	$fd = strtotime($fd);
   
   $fd2 = date('m/d/Y h:i', $fd);
   if ($TEST2 == 't') {
		echo "fd2 : $fd2 <br>";
   }
   
   $td = strtotime($td);
   $td2 = date('m/d/Y h:i', $td);
   if ($TEST2 == 't') {
		echo "td2 : $td2 <br>";
   }
   
   $FromDate = "/Date(".$fd."000)/";
   $ToDate = "/Date(".$td."000)/";
	
	
	$logger2 = new FileLogger("updateContactStatusCron.log");
	$logger2->log("GetEventActivity fd2 = $fd2, td2 = $td2 , ContinuationID : $ContinuationID");
	
	$ActivityRequest   = array
	(
		"Account_ID"=> $ACCOUNTID,
		"ContinuationID"=> $ContinuationID,
		"PartnerCredentials" => array
		(
			"Ticket" => $PartnerTicket        
		),
		"EventIds"=> $EventIds,
		"RequiredColumns"=> $RequiredColumns,
		"FromDate"=> $FromDate,
		"ToDate"=> $ToDate,
	);
	
	if ($TEST == 't') {
		echo "GetEventActivity ActivityRequest : <br>";
		var_dump($ActivityRequest);
		echo "<br>";
	}
	$logger2->log("GetEventActivity ActivityRequest = ". json_encode($ActivityRequest) );
	
	$ActivityResponse = callService("programservice/GetEventActivity", $ActivityRequest);
	$logger2->log("GetEventActivity ActivityResponse = ". json_encode($ActivityResponse) );
	
	return $ActivityResponse;
}


function clearStudioActivity($EventIds){
	for ($i = 0; $i < sizeof($EventIds); $i++) {
		$EventID = $EventIds[$i];
		$sql = 'DELETE FROM DV_StudioActivity where acctID=? and eventID=?';
		try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($ACCOUNTID, $EventID));
			$leadList[$PURL] = '30505';
		} catch(Exception $e){
			$ErrorMessage = $e->getMessage();
			if ($TEST2 == 't') {
				echo "ErrorMessage = ". $ErrorMessage;
				echo "<br>";
			}
		}
	}
}	

function splitName($userName) {
	return explode(' ',$userName, 2);
}

function splitEmail($userEmail) {
	return explode('@',$userEmail, 2);
}

function getUserID($ticket, $userEmail) {
	$logger2 = new FileLogger("checkLoginUser.log"); 
	$UserListRequest   = array
	(
		
		"Credentials" => array
		(
			"Ticket" => $ticket        
		)
		
	);
	$UserListResponse = callService("userservice/GetUserList", $UserListRequest);
	$ErrorCode = $UserListResponse->{"Result"}->{"ErrorCode"};
	$userID = "-1";
	$FirstName = '';
	$LastName = '';
	$Mobile = '';
	if ($ErrorCode == "") {
		$status = 'Success';
		$errorMessage = '';
		$studioError = '';
		$ShortUserLists = $UserListResponse->{"UserList"};			
		foreach ($ShortUserLists as $ShortUserList){
			$Email = $ShortUserList->{'Email'};
			if ( strtolower($Email) == strtolower($userEmail) ) {
				$userID = $ShortUserList->{'ID'};
				$Mobile = $ShortUserList->{'Mobile'};
				$FirstName = $ShortUserList->{'FirstName'};
				$LastName = $ShortUserList->{'LastName'};
				if ($FirstName == '') {
					$name = splitEmail($Email);
					$FirstName = $name[0];
					$LastName = 'Unknown';
				}
				break;
			}			
		}
	} else {
		$status = 'Fail';
		$errorMessage = "getUserID ErrorMessage : ".$UserListResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$UserListResponse->{"Result"}->{"ExceptionMessage"};
		$userID = "-1";
		$studioError = $UserListResponse->{"Result"}->{"ErrorMessage"};
	}
	$result['status'] = $status;
	$result['userID'] = $userID;
	$result['Mobile'] = $Mobile;
	$result['FirstName'] = $FirstName;
	$result['LastName'] = $LastName;
	$result['errorMessage'] = $errorMessage;
	$result['studioError'] = $studioError;
	return $result;
}


function genPassword($length = 10) {
    $upperCase    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowerCase    = 'abcdefghijklmnopqrstuvwxyz';
    $numbers      = '0123456789';
    $specialChars = '!@#$%^_-';

    $password  = $upperCase[rand(0, strlen($upperCase)-1)];
    $password .= $lowerCase[rand(0, strlen($lowerCase)-1)];
    $password .= $numbers[rand(0, strlen($numbers)-1)];
    $password .= $specialChars[rand(0, strlen($specialChars)-1)];
    $allChars = $upperCase . $lowerCase . $numbers . $specialChars;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[rand(0, strlen($allChars)-1)];
    }
    $password = str_shuffle($password);
    return $password;
}

function AddUser($ticket, $ACCOUNTID, $userName, $userEmail, $userMobile, $usermode, $addSeed) {
	$logger2 = new FileLogger("saveStudioUser.log"); 
	
	$ReturnResult = array();
	if ($usermode == 'setting') {
		$name = splitName($userName);
		$FirstName = $name[0];
		$LastName = $name[1];
		if ($LastName) {
		} else {
			$LastName = $FirstName;
		}
		
		$signUpLastName = $LastName;
	} else {
		$name = splitEmail($userEmail);
		$FirstName = $name[0];
		$LastName = 'Unknown';
		$signUpFirstName = $FirstName;
		$signUpLastName = '';
	}
	$signUpFirstName = $FirstName;
	$signUpEmail = $userEmail;
	
	$RoleIds = getRoleID($ticket);	
	$RoleID = $RoleIds['RoleID'];
	//$passwordData = "Password1#";
	$passwordData = genPassword(10) ;  //#2468
	
	$logger2->log("AddUser:: init PWD  = ". $passwordData );

	$Mobile = '';
	$request1 = array(
		"FirstName"=> $FirstName,  // from signup page
		"LastName"=> $LastName,    // from signup page
		"Mobile"=> $userMobile,
		"ContactScope"=> null,
		"Credentials"=> array(
			"Ticket" => $ticket,
		),
		"Email"=> $userEmail,     // from signup page
		"Password"=>$passwordData,                        
		"RoleIds"=> [$RoleID],
		"SharedPrograms"=> null
	);
	$logger2->log("RegisterAndActivateUser RoleIds  = ". json_encode($RoleIds) );
	$logger2->log("RegisterAndActivateUser request  = ". json_encode($request1) );
	$registerUserResponse = callService("userservice/RegisterAndActivateUser", $request1);
	$logger2->log("RegisterAndActivateUser response  = ". json_encode($registerUserResponse) );
	$ErrorCode = $registerUserResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$status = 'Success';
		$errorMessage = '';
		$studioError = '';
		$userID = $registerUserResponse->{'ID'};
		
		$dvAccountName = $_SESSION['ACCOUNNAME'];
		$TEST = '';
		$checkLoginResult = checkLogin($TEST, $userEmail, $passwordData);
	   	if ($checkLoginResult['success']) {
	   		$welcomeData = "Thanks for joining the MindFire family.  We can't wait for you to find out how easy it is to drive more leads and sales!";
	         $passwordData = "Password: $passwordData";
	         $accountData = "Account: $dvAccountName";
	   	} else {
	         $welcomeData = "You have been added to the account $dvAccountName";
	         $passwordData = "Please login to MindFire with your existing password. If you don't remember your existing password, click on forgot password link";
	         $accountData = "";
	   	}
		
		
		$userContent = getUserContent($ACCOUNTID, 'welcomeContent');
		$logger2->log("RegisterAndActivateUser userContent  = ". json_encode($userContent) );
		$userContentSubject = $userContent['subject'];
		$userContentContent = $userContent['content'];
		$sendFromName = $userContent['sendFromName'];
		$sendFromEmail = $userContent['sendFromEmail'];
		
		
		$sendto = $userEmail;
		if ($userContentSubject != '') {
			$subject = $userContentSubject;
		} else {
    		$subject = 'Welcome to the MindFire family';
		}
		if ($sendFromName == '') {
			$sendFromName = 'MindFire Product Team';
		}
		if ($sendFromEmail == '') {
			$sendFromEmail = 'mindfireproduct@mindfireinc.com';
		}
		$safeFirstName = htmlspecialchars($FirstName, ENT_QUOTES, 'UTF-8');
		if ($userContentContent != '') {
			$content = $userContentContent;
			$content = str_ireplace("{{FirstName}}", $safeFirstName, $content);
			$content = str_ireplace("{{userEmail}}", $userEmail, $content);
			$content = str_ireplace("{{passwordData}}", $passwordData, $content);
			$content = str_ireplace("{{accountData}}", $accountData, $content);

		} else {
    		$content = "Dear $safeFirstName,<br><br>$welcomeData<br><br>Here's your log in information:<br><br>URL: https://client.mdl.io <br>Username: $userEmail <br>$passwordData<br>$accountData<br><br>To your marketing success!<br><br>Once you have logged in, you can click on the user icon at the top left of the screen to change your password.<br><br>Team MindFire<br><br>Please contact us at support@mindfireinc.com if you have any questions or face problems.";
		}
		
		$emailData['EMAIL'] = $sendto;
		$emailData['subject'] = $subject;
		$emailData['content'] = $content;
		$emailData['emailMode'] = '';
		$emailData['saveLog'] = 'no';
		$emailData['fromEmail'] = $sendFromEmail;
		$emailData['fromName'] = $sendFromName;
		$emailResult = sendsocketlabsEmailAddLog($emailData);
      
	} else {
		$status = 'Fail';
		$errorMessage = "RegisterAndActivateUser ErrorMessage : ".$registerUserResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$registerUserResponse->{"Result"}->{"ExceptionMessage"};
		$result = getUserID($ticket, $userEmail);
		$userID = $result['userID'];
		$Mobile = $result['Mobile'];
		$FirstName1 = $result['FirstName'];
		$LastName1 = $result['LastName'];
		$studioError = $registerUserResponse->{"Result"}->{"ErrorMessage"};
	}
	$status1 = "RegisterAndActivateUser: $status";
	$errorMessage1 = $errorMessage;
	$studioError1 = $studioError;

	if ($userID != "-1") {
	    $request2 = array(
		   "Credentials"=>array(
				"Ticket" => $ticket,
	      ),
		   "SubAccountID"=>$ACCOUNTID,
			"UserID"=>$userID,
	    );
		$logger2->log("AddUserToSubAccount request  = ". json_encode($request2) );
		$addUserToSubAccountResponse = callService("userservice/AddUserToSubAccount", $request2);
		$logger2->log("AddUserToSubAccount response  = ". json_encode($addUserToSubAccountResponse) );
		$ErrorCode = $addUserToSubAccountResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			$status = 'Success';
			$errorMessage = '';
			$studioError = '';
			
			
		} else {
			$status = 'Fail';
			$errorMessage = "AddUserToSubAccount ErrorMessage : ".$addUserToSubAccountResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$addUserToSubAccountResponse->{"Result"}->{"ExceptionMessage"};
			//$userID = "-1";
			$studioError = $addUserToSubAccountResponse->{"Result"}->{"ErrorMessage"};
		}
		$status2 = "AddUserToSubAccount : $status";
		$errorMessage2 = $errorMessage;
		$studioError2 = $studioError;
		
		if ($userMobile == '') {
			$userMobile = $Mobile;
		}

		$request3 = array(
			"FirstName"=> $FirstName,  // from signup page
			"LastName"=> $LastName,    // from signup page
			"Mobile"=> $userMobile,
			"ContactScope"=> null,
			"Credentials"=> array(
				"Ticket" => $ticket,
			),                     
			"RoleIds"=> [$RoleID],
			"ID"=> $userID,
			"IsActive"=> true
		);
		$logger2->log("UpdateUser request  = ". json_encode($request3) );
		$registerUserResponse = callService("userservice/UpdateUser", $request3);
		$logger2->log("UpdateUser response  = ". json_encode($registerUserResponse) );
		$ErrorCode = $registerUserResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			$status = 'Success';
			$errorMessage = '';
			$studioError = '';			
		} else {
			$status = 'Fail';
			$errorMessage = "UpdateUser ErrorMessage : ".$registerUserResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$registerUserResponse->{"Result"}->{"ExceptionMessage"};
			$studioError = $registerUserResponse->{"Result"}->{"ErrorMessage"};
		}
		$status3 = "UpdateUser : $status";
		$errorMessage3 = $errorMessage;
		$studioError3 = $studioError;
			
	}
	
	$addSeed = 'N'; // #2366 Do not add seed.
	
	if ($addSeed != 'N') {
		$result = addSeedContact($ticket, $signUpFirstName, $signUpLastName, $signUpEmail);
		if ($result) {
			if ($result['success']) {
				$status = 'Success';
			} else {
				$status = 'Fail';
			}
			$errorMessage = $result['errorMessage'];
			$studioError = $result['errorMessage'];
		} else {
			$status = 'Fail';
		}
	} else {
		$status = 'Success';
	}
	$status4 = "addSeedContact : $status";
	$errorMessage4 = $errorMessage;
	$studioError4 = $studioError;
	
	$ReturnResult['FirstName'] = $FirstName;
	$ReturnResult['LastName'] = $LastName;
	$ReturnResult['userEmail'] = $userEmail;
	$ReturnResult['status'] = $status;
	$ReturnResult['userID'] = $userID;
	$ReturnResult['RoleIds'] = $RoleIds;
	$ReturnResult['RoleId'] = $RoleId;
	$ReturnResult['errorMessage'] = $errorMessage;
	$ReturnResult['studioError'] = $studioError;
	$ReturnResult['signUpFirstName'] = $signUpFirstName;
	$ReturnResult['signUpLastName'] = $signUpLastName;
	$ReturnResult['status1'] = $status1;
	$ReturnResult['errorMessage1'] = $errorMessage1;
	$ReturnResult['studioError1'] = $studioError1;
	$ReturnResult['status2'] = $status2;
	$ReturnResult['errorMessage2'] = $errorMessage2;
	$ReturnResult['studioError2'] = $studioError2;
	$ReturnResult['status3'] = $status3;
	$ReturnResult['errorMessage3'] = $errorMessage3;
	$ReturnResult['studioError3'] = $studioError3;
	$ReturnResult['status4'] = $status4;
	$ReturnResult['errorMessage4'] = $errorMessage4;
	$ReturnResult['studioError4'] = $studioError4;
	$ReturnResult['request1'] = $request1;
	$ReturnResult['request2'] = $request2;
	$ReturnResult['request3'] = $request3;

	return $ReturnResult;
}

function AddUserCredentials($Credentials, $ticket, $ACCOUNTID, $userName, $userEmail, $userMobile, $usermode) {
	$logger2 = new FileLogger("setupDVProd.log"); 
	
	$ReturnResult = array();
	if ($usermode == 'setting') {
		$name = splitName($userName);
		$FirstName = $name[0];
		$LastName = $name[1];
		if ($LastName) {
		} else {
			$LastName = $FirstName;
		}
		
		$signUpLastName = $LastName;
	} else {
		$name = splitEmail($userEmail);
		$FirstName = $name[0];
		$LastName = 'Unknown';
		$signUpFirstName = $FirstName;
		$signUpLastName = '';
	}
	$signUpFirstName = $FirstName;
	$signUpEmail = $userEmail;
	
	$RoleIds = getRoleIDCredentials($Credentials);	
	$RoleID = $RoleIds['RoleID'];
	$passwordData = "Password1#";
	$Mobile = '';
	$request1 = array(
		"FirstName"=> $FirstName,  // from signup page
		"LastName"=> $LastName,    // from signup page
		"Mobile"=> $userMobile,
		"ContactScope"=> null,
		"Credentials"=> $Credentials,
		"Email"=> $userEmail,     // from signup page
		//"Password"=>"password",
		"Password"=>$passwordData,                        
		"RoleIds"=> [$RoleID],
		"SharedPrograms"=> null
	);
	$logger2->log("RegisterAndActivateUser RoleIds  = ". json_encode($RoleIds) );
   $logger2->log("RegisterAndActivateUser request  = ". json_encode($request1) );
   $registerUserResponse = callServiceCredential("userservice/RegisterAndActivateUser", $request1);
   $logger2->log("RegisterAndActivateUser response  = ". json_encode($registerUserResponse) );
	$ErrorCode = $registerUserResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$status = 'Success';
		$errorMessage = '';
		$studioError = '';
		$userID = $registerUserResponse->{'ID'};
		
		$dvAccountName = $_SESSION['ACCOUNNAME'];
		$TEST = '';
		$checkLoginResult = checkLogin($TEST, $userEmail, $passwordData);
   	if ($checkLoginResult['success']) {
   		$welcomeData = "Thanks for joining the MindFire family.  We can't wait for you to find out how easy it is to drive more leads and sales!";
         $passwordData = "Password: $passwordData";
         $accountData = "Account: $dvAccountName";
   	} else {
         $welcomeData = "You have been added to the account $dvAccountName";
         $passwordData = "Please login to MindFire with your existing password. If you don't remember your existing password, click on forgot password link";
         $accountData = "";
   	}
		
		$sendto = $userEmail;
      $subject = 'Welcome to the MindFire family';
      $content = "Dear $FirstName,<br><br>$welcomeData<br><br>Here's your log in information:<br><br>URL: https://client.mdl.io <br>Username: $userEmail <br>$passwordData<br>$accountData<br><br>Once you have logged in, you can click on the user icon at the top left of the screen to change your password.<br><br>To your marketing success!<br><br>Team MindFire<br><br>Please contact us at support@mindfireinc.com if you have any questions or face problems.";
      
		$emailRet = sendsocketlabsEmailAddCC($sendto, $subject, $content, '', '');	
		
		
	} else {
		$status = 'Fail';
		$errorMessage = "RegisterAndActivateUser ErrorMessage : ".$registerUserResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$registerUserResponse->{"Result"}->{"ExceptionMessage"};
		$result = getUserID($ticket, $userEmail);
		$userID = $result['userID'];
		$Mobile = $result['Mobile'];
		$FirstName1 = $result['FirstName'];
		$LastName1 = $result['LastName'];
		$studioError = $registerUserResponse->{"Result"}->{"ErrorMessage"};
	}
	$status1 = "RegisterAndActivateUser: $status";
	$errorMessage1 = $errorMessage;
	$studioError1 = $studioError;

	if ($userID != "-1") {
	    $request2 = array(
		   "Credentials"=>$Credentials,
		   "SubAccountID"=>$ACCOUNTID,
			"UserID"=>$userID,
	    );
		$logger2->log("AddUserToSubAccount request  = ". json_encode($request2) );
		$addUserToSubAccountResponse = callServiceCredential("userservice/AddUserToSubAccount", $request2);
		$logger2->log("AddUserToSubAccount response  = ". json_encode($addUserToSubAccountResponse) );
		$ErrorCode = $addUserToSubAccountResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			$status = 'Success';
			$errorMessage = '';
			$studioError = '';
			
			
		} else {
			$status = 'Fail';
			$errorMessage = "AddUserToSubAccount ErrorMessage : ".$addUserToSubAccountResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$addUserToSubAccountResponse->{"Result"}->{"ExceptionMessage"};
			//$userID = "-1";
			$studioError = $addUserToSubAccountResponse->{"Result"}->{"ErrorMessage"};
		}
		$status2 = "AddUserToSubAccount : $status";
		$errorMessage2 = $errorMessage;
		$studioError2 = $studioError;
		
		if ($userMobile == '') {
			$userMobile = $Mobile;
		}

		$request3 = array(
			"FirstName"=> $FirstName,  // from signup page
			"LastName"=> $LastName,    // from signup page
			"Mobile"=> $userMobile,
			"ContactScope"=> null,
			"Credentials"=> $Credentials,                     
			"RoleIds"=> [$RoleID],
			"ID"=> $userID,
			"IsActive"=> true
		);
		$logger2->log("UpdateUser request  = ". json_encode($request3) );
		$registerUserResponse = callServiceCredential("userservice/UpdateUser", $request3);
		$logger2->log("UpdateUser response  = ". json_encode($registerUserResponse) );
		$ErrorCode = $registerUserResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			$status = 'Success';
			$errorMessage = '';
			$studioError = '';			
		} else {
			$status = 'Fail';
			$errorMessage = "UpdateUser ErrorMessage : ".$registerUserResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$registerUserResponse->{"Result"}->{"ExceptionMessage"};
			$studioError = $registerUserResponse->{"Result"}->{"ErrorMessage"};
		}
		$status3 = "UpdateUser : $status";
		$errorMessage3 = $errorMessage;
		$studioError3 = $studioError;
			
	}

	$result = addSeedContact($ticket, $signUpFirstName, $signUpLastName, $signUpEmail);
	if ($result) {
		if ($result['success']) {
			$status = 'Success';
		} else {
			$status = 'Fail';
		}
		$errorMessage = $result['errorMessage'];
		$studioError = $result['errorMessage'];
	} else {
		$status = 'Fail';
	}
	$status4 = "addSeedContact : $status";
	$errorMessage4 = $errorMessage;
	$studioError4 = $studioError;
	
	$ReturnResult['FirstName'] = $FirstName;
	$ReturnResult['LastName'] = $LastName;
	$ReturnResult['userEmail'] = $userEmail;
	$ReturnResult['status'] = $status;
	$ReturnResult['userID'] = $userID;
	$ReturnResult['RoleIds'] = $RoleIds;
	$ReturnResult['RoleId'] = $RoleId;
	$ReturnResult['errorMessage'] = $errorMessage;
	$ReturnResult['studioError'] = $studioError;
	$ReturnResult['signUpFirstName'] = $signUpFirstName;
	$ReturnResult['signUpLastName'] = $signUpLastName;
	$ReturnResult['status1'] = $status1;
	$ReturnResult['errorMessage1'] = $errorMessage1;
	$ReturnResult['studioError1'] = $studioError1;
	$ReturnResult['status2'] = $status2;
	$ReturnResult['errorMessage2'] = $errorMessage2;
	$ReturnResult['studioError2'] = $studioError2;
	$ReturnResult['status3'] = $status3;
	$ReturnResult['errorMessage3'] = $errorMessage3;
	$ReturnResult['studioError3'] = $studioError3;
	$ReturnResult['status4'] = $status4;
	$ReturnResult['errorMessage4'] = $errorMessage4;
	$ReturnResult['studioError4'] = $studioError4;
	$ReturnResult['request1'] = $request1;
	$ReturnResult['request2'] = $request2;
	$ReturnResult['request3'] = $request3;

	return $ReturnResult;
}


function UpdateUserIsActive($ticket, $ACCOUNTID, $userEmail, $userName, $userMobile, $IsActive) {
	$result = getUserID($ticket, $userEmail);
	$userID = $result['userID'];
	
	$ReturnResult = array();
	$RoleIds = getRoleID($ticket);	
	$RoleID = $RoleIds['RoleID'];
	
	$name = splitName($userName);
	$FirstName = $name[0];
	$LastName = $name[1];
	if ($LastName) {
	} else {
		$LastName = $FirstName;
	}

	$UpdateRequest   = array
	(
		"Credentials" => array
		(
			"Ticket" => $ticket        
		),
		"ID"=> $userID,
		"FirstName"=> $FirstName,
		"LastName"=> $LastName,
		"Mobile"=> $userMobile,
		"ContactScope"=> null,
		"IsActive"=> $IsActive,
		"RoleIds"=> [$RoleID]
	);
	$UpdateResponse = callService("userservice/UpdateUser", $UpdateRequest);
	$ErrorCode = $UpdateResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$status = 'Success';
		$errorMessage = '';
		$studioError = '';
	} else {
		$status = 'Fail';
		$errorMessage = "UpdateUser ErrorMessage : ".$UpdateResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$UpdateResponse->{"Result"}->{"ExceptionMessage"};
		$studioError = $UpdateResponse->{"Result"}->{"ErrorMessage"};
	}
	$ReturnResult['DeleteRequest'] = $UpdateRequest;
	$ReturnResult['DeleteResponse'] = $UpdateResponse;
	$ReturnResult['UpdateUserActiveStatus'] = $status;
	$ReturnResult['UpdateUserActiveErrorMessage'] = $errorMessage;
	$ReturnResult['UpdateUserActiveStudioError'] = $studioError;
	return $ReturnResult;
}

function UpdateUser($ticket, $userName, $userEmail, $userMobile, $usermode, $userOldEmail, $ACCOUNTID, $addSeed){
	$ReturnResult = array();
	if ($userOldEmail != $userEmail) {
		$AddResult = AddUser($ticket, $ACCOUNTID, $userName, $userEmail, $userMobile, $usermode, $addSeed);
		$ReturnResult['AddResult'] = $AddResult;
		if ($AddResult['status'] == 'Fail') {
			$UpdateResponse = UpdateUserIsActive($ticket, $ACCOUNTID, $userEmail, $userName, $userMobile, true);
		}
		$DeleteResult = UpdateUserIsActive($ticket, $ACCOUNTID, $userOldEmail, $userName, $userMobile, false);
		$ReturnResult['DeleteResult'] = $DeleteResult;
	} else {
			
		$UpdateResponse = UpdateUserIsActive($ticket, $ACCOUNTID, $userEmail, $userName, $userMobile, true);
			
		$status = $UpdateResponse['UpdateUserActiveStatus'];
		if ($status == 'Success') {
			$errorMessage = '';
			$studioError = '';
		} else {
			$errorMessage = $UpdateResponse['UpdateUserActiveErrorMessage'];
			$studioError = $UpdateResponse['UpdateUserActiveStudioError'];
		}
		$ReturnResult['FirstName'] = $FirstName;
		$ReturnResult['LastName'] = $LastName;
		$ReturnResult['userEmail'] = $userEmail;
		$ReturnResult['UpdateRequest'] = $UpdateRequest;
		$ReturnResult['UpdateResponse'] = $UpdateResponse;
		$ReturnResult['status'] = $status;
		$ReturnResult['userID'] = $userID;
		$ReturnResult['errorMessage'] = $errorMessage;
		$ReturnResult['studioError'] = $studioError;
	}
	
	return $ReturnResult;
}

function GetStudioAccountNames($email,$pwd)
{
    LogDebug("GetStudioAccountName [$email][$pwd][$SelectedAccountID]");
    $STUDIOLOGIN = $_SESSION['STUDIOLOGIN'];
    if ($STUDIOLOGIN == 'Y') {
    	$accountID = $_SESSION['ACCOUNTID'];
      $accountName = $_SESSION['ACCOUNNAME'];
    	$mpArray[] = array($accountID, $accountName);
    	return array('success'=>true, 'mps'=>$mpArray);
    } else {
    	$studioParam = GetStudioAPIParam();
   	$PARTNERGUID = $studioParam['guid'];
   	$PARTNERPASSWORD = $studioParam['password'];
		$authRequest = array
		(
			"SelectedAccountID" => null,
			"Email" => $email, 
			"Password" => $pwd, 
			"PartnerGuid" => $PARTNERGUID, 
			"PartnerPassword" => $PARTNERPASSWORD
		);
	
		$authResponse = callService("userservice/Authenticate", $authRequest);
		
		if ($_SESSION['STUDIOLOGIN'] == "Y") {
			$logger = new FileLogger("portalLogin.log");
			$logger->log("2 GetStudioAccountNames authRequest = ". json_encode($authRequest) );
			$logger->log("2 GetStudioAccountNames authResponse = ". json_encode($authResponse) );
		}
		
		
		$mpArray = array();
		$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
		if ($ErrorCode == "") {
			
			$AvailableAccountList = $authResponse->{"AvailableAccountList"};
			$AccountNumber = Sizeof($AvailableAccountList);
			$isFound = true;
	
			//$AccountNames = getxmediaAPIaccount();
	        
	        $AccountNames = getxmediaAPIaccountCouchDB();
	        $AccountIDs = getxmediaAPIaccountIDCouchDB();
			
			$loadmore = "Yes";
				
			foreach ($AvailableAccountList as $x){
				foreach ($AccountIDs as $y){
					$y = strtolower($y);
					$z = strtolower($x->{'Key'});
					if ($y == $z) {
						$mpArray[] = array($x->{'Key'}, $x->{'Value'});
					}
				}
				
			}
			sort($mpArray);	   
	        return array('success'=>true, 'mps'=>$mpArray);
	    }else{
	        return array('success'=>false, 'ErrorCode'=>$ErrorCode, 'authResponse'=>$authResponse);
	    }
    }

}

function saveConversionData($saveData) {
	
	$logger2 = new FileLogger("saveConversion2.log"); 
	
	$logger2->log("global saveData = ". json_encode($saveData) );
	
	
	$PURL = $saveData['PURL'];
	$TEST = $saveData['TEST'];
	$accountID = $saveData['accountID'];
	$encodeCID = $saveData['encodeCID'];
	$contactID = $saveData['contactID'];
	$ipAddress = $saveData['ipAddress'];
	$programID = $saveData['programid'];
	$elementName = $saveData['elementname'];
	$visitPage = $saveData['visitpage'];
	$visitTime = $saveData['visittime'];
	$urlParam = $saveData['urlParam'];
	$marketMode = $saveData['marketMode'];
	$scheduleID = $saveData['scheduleID'];
	$outboundID = $saveData['outboundID'];
	$trackSource = $saveData['trackSource'];
	
	
	$userInfo = getDVUserinfo($accountID);
	$userStatus = $userInfo['userStatus'];
	$userStatus = strtolower($userStatus);

	$logger2->log("global userStatus = $userStatus");

	if ($userStatus == 'cancelled') {
		
		$result['success'] = true;
		$result['ErrorMessage'] = "userStatus = $userStatus";;
		$result['affected_rows'] = 0;
		return $result;
	} else {
		
	}
	
	
	if (!empty($encodeCID)) {
		$data = MySQL_GetDocByKey("DV_ContentEncryption",["ID"=>$encodeCID],"data",true);
		$contentID = $data['contentID'];
	}
	
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$firstName = '';
		$lastName = '';
		$email = '';
		$city = '';
		$state = '';
		$zip = '';
		$company = '';
		$country = '';
		
		if ($PURL != '') {
			$studioParam = GetStudioAPIParam();
			$EMAIL = $studioParam['email'];
			$PWD = $studioParam['pwd'];
			$PARTNERGUID = $studioParam['guid'];
			$PARTNERPASSWORD = $studioParam['password'];
			$LISTDEFINITION = "<Filter CriteriaJoinOperator=\"&amp;\"><Criteria Row=\"1\" Field=\"PURL\" Operator=\"Equal\" Value=\"$PURL\" /></Filter>";

			$ticket = getTicket($accountID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);

			$result = GetContact($TEST, $ticket, $LISTDEFINITION, $accountID);	
			$logger2->log("global GetContact = ". json_encode($result) );
			if ($TEST == 't') {
				echo "GetContact<br>";
				var_dump($result);
				echo "<br>";
			}
			$output = json_decode($result);
			$Contact = $output->Contact;
			if ($Contact) {
				//$FieldNames = array("FirstName","LastName","Email","PURL","Phone","Address1","City","State","Zip","Company","Mobile","Country");
				$firstName = $Contact[0][0];
				$lastName = $Contact[0][1];
				$email = $Contact[0][2];
				$city = $Contact[0][6];
				$state = $Contact[0][7];
				$zip = $Contact[0][8];
				$company = $Contact[0][9];
				$country = $Contact[0][11];
			}
			if ($TEST == 't') {
				echo "firstName : $firstName, lastName : $lastName, email : $email, company : $company, city : $city, state : $state, zip :$zip, country : $country<br>";
			}
			$logger2->log("firstName : $firstName, lastName : $lastName, email : $email, company : $company, city : $city, state : $state, zip :$zip, country : $country");
		}
				 // $email = 'tt994613+etest505@gmail.com';
		//case empty account set account ID = 0
		$accountID = (empty($accountID)) ? 0 : $accountID;
		$contentID = (empty($contentID)) ? 0 : $contentID;
		$scheduleID = (empty($scheduleID)) ? 0 : $scheduleID;
		$outboundID = (empty($outboundID)) ? 0 : $outboundID;

		$insertStmt = MySQL_Prepare($db,"INSERT INTO DV_Conversion (id, insertTime, contactID, PURL, ipAddress, accountID, programID, elementName, visitPage, visitTime, urlParam, firstName, lastName, email, company, city, state, zip, country, contentID, marketMode, scheduleID, outboundID, trackSource) VALUES (NULL, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$insertStmt->execute(array($contactID, $PURL, $ipAddress, $accountID, $programID, $elementName, $visitPage, $visitTime, $urlParam, $firstName, $lastName, $email, $company, $city, $state, $zip, $country, $contentID, $marketMode, $scheduleID, $outboundID, $trackSource));
		$affected_rows = $insertStmt->rowCount();
		
		if ($PURL != '') {
					
			$PageUpper = strtoupper($visitPage);
			if (strpos($PageUpper, 'DOWNLOAD.HTML') !== false) {
				$index = 0;
				$group2 = array(					
					$email,$index			
				);	
				$createRequest2[] = $group2;
				
				
				$resultCache = getBouncerCache($createRequest2);
				$logger2->log(" createRequest2 = ".json_encode( $createRequest2 )); 
				$logger2->log(" resultCache = ".json_encode( $resultCache )); 
				
				$resultCacheAll = $resultCache['all'];
				
				foreach( $resultCacheAll as $row1 ) {
					
					$email = $row1[0];
					$index1 = $row1[1];
					$status = $row1[2];
					$emailProvider = $row1[3];
					
					$indexFound = false;
					foreach( $createRequest2 as $row2 ) {
						$index2 = $row2['index'];
						if ($index1 == $index2) {
							if ($status <> '-1') {
								$indexFound = true;
							}
							break;
						}
					}
					
				}
				
				if ($indexFound == true) {
					$logger2->log("Found in DV_BouncerResultDetail, status : $status, emailProvider : $emailProvider");
					$status = mapBouncerValue('status', $status);
					
					$contactDataAPI = array(
						'FirstName'=>$firstName,
						'LastName'=>$lastName,
						'bounceStatus'=>$status,
						'EmailProvider'=>$emailProvider,
						'PURL'=>$PURL,
					);
					$logger2->log("contactDataAPI: ".json_encode($contactDataAPI) );
					$updateContactResult = updateContact($ticket, $contactDataAPI);
					$logger2->log("updateContactResult: ".json_encode($updateContactResult) );
					
				} else {
					$logger2->log("Not Found in DV_BouncerResultDetail");
					$result = getUsebouncerVerifyLog($TEST, $email, $accountID, $PURL);	
					$logger2->log(" result = ".json_encode( $result )); 
				
					if($result){		
						$status = $result->{"status"}; 
						$emailProvider = $result->{"provider"};
						$reason = $result->{"reason"};
						$acceptAll = $result->{"domain"}->{"acceptAll"};
						$disposable = $result->{"domain"}->{"disposable"};
						$free = $result->{"domain"}->{"free"};
						
						$contactDataAPI = array(
							'FirstName'=>$firstName,
							'LastName'=>$lastName,
							'bounceStatus'=>$status,
							'EmailProvider'=>$emailProvider,
							'PURL'=>$PURL,
						);
						$logger2->log("contactDataAPI: ".json_encode($contactDataAPI) );
						$updateContactResult = updateContact($ticket, $contactDataAPI);
						$logger2->log("updateContactResult: ".json_encode($updateContactResult) );
						
						$recordCount = 0;
						$sql = "INSERT INTO davinci_master.DV_BouncerResult (id, createDate, bouncerID, recordCount) VALUES (NULL, CURDATE(), CURDATE(), ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
						try {
							$Stmt1 = MySQL_Prepare($db,$sql);
				            $Stmt1->execute(array($recordCount));
				            
				            $resultID = $db->lastInsertId();
				            $logger2->log("DV_BouncerResult $resultID : $resultID");
				            $success = true;
						} catch(Exception $e){
							$success = false;	
							$ErrorMessage = $e->getMessage();
							$logger2->log("DV_BouncerResult ErrorMessage : $ErrorMessage");
						}
						
						if ($success) {
							$email = strtolower($email);
							$status = strtolower($status);
							$provider = strtolower($emailProvider);
							$reason = strtolower($reason);
							$acceptAll = strtolower($acceptAll);
							$disposable = strtolower($disposable);
							$free = strtolower($free);
							
							if ( $acceptAll == 'yes' ) { $acceptAll = 1; } else { $acceptAll = 0; }
							if ( $disposable == 'yes' ) { $disposable = 1; } else { $disposable = 0; }
							if ( $free == 'yes' ) { $free = 1; } else { $free = 0; }
							
							$status = mapBouncerCode('status', $status);
							$reason = mapBouncerCode('reason', $reason);
							
							$logger2->log("resultID : $resultID, email : $email, status : $status, provider : $provider , reason : $reason , acceptAll : $acceptAll, disposable : $disposable, free : $free");
							
							$sql = "INSERT INTO davinci_master.DV_BouncerResultDetail (id, resultID, email, status, provider, reason, acceptAll, disposable, free) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=?, provider=?, reason=?, acceptAll=?, disposable=?, free=?";
							try {
								$Stmt1 = MySQL_Prepare($db,$sql);
					            $Stmt1->execute(array($resultID, $email, $status, $provider, $reason, $acceptAll, $disposable, $free, $status, $provider, $reason, $acceptAll, $disposable, $free));
					            $logger2->log("DV_BouncerResultDetail $resultID : $resultID");
							} catch(Exception $e){
								$success = false;	
								$ErrorMessage = $e->getMessage();
								$logger2->log("DV_BouncerResultDetail ErrorMessage : $ErrorMessage");
							}
						}
					}
				}
			}
		}
		
		
		$success = true;
		$ErrorMessage = '';
	}  
	catch(exception $e) {
		
		$ErrorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
	}
	if ($TEST == 't') {
		echo "ErrorMessage : $ErrorMessage, affected_rows : $affected_rows, success : $success<br>";
	}
	$logger2->log("ErrorMessage : $ErrorMessage, affected_rows : $affected_rows, success : $success");
	$result = array();
	$result['success'] = $success;
	$result['ErrorMessage'] = $ErrorMessage;
	$result['affected_rows'] = $affected_rows;
	return $result;
}


function zipImportFile($importPath, $zipName, $csvName) {
	$logger = new FileLogger("doImportFile2.log"); 
	
	$zip = new ZipArchive();
	
	$zipFile = getLogPathEx($importPath,$zipName);
	
	$logger->log(" zipImportFile importPath : $importPath, zipName : $zipName, zipFile : $zipFile");
	
	if(file_exists($zipFile)) {
		unlink ($zipFile); 
	}
	
	if ($zip->open($zipFile, ZIPARCHIVE::CREATE) != TRUE) {
		$ErrorMessage = "Could not open archive";
		$success = false;
	} else {
		
		// $pieces = explode(",", $csvName);
		// if ($pieces) {
		// 	foreach ($pieces as $piece) {
		// 		$csvFile = getLogPathEx($importPath,$piece);
		// 		$logger->log(" zipImportFile csvFile : $csvFile, piece : $piece");
		// 		$zip->addFile($csvFile,$piece);
		// 	}
		// }
		//#1926
		$csvFile = getLogPathEx($importPath,$csvName);
		$logger->log(" zipImportFile csvFile : $csvFile, csvName : $csvName");
		$zip->addFile($csvFile,$csvName);
		
		$success = true;
		$ErrorMessage = "";
	}
	// close and save archive
	$zip->close(); 
	
	$result['success'] = $success;
	$result['ErrorMessage'] = $ErrorMessage;
	$result['zipFile'] = $zipFile;
	
	return $result;
}

function getBA_Reach($TEST, $EMAIL, $PWD, $ACCOUNTID, $PROGRAMID, $fd, $td) {
	
	$STUDIOLOGIN = $_SESSION['STUDIOLOGIN'];
   
	if ($STUDIOLOGIN == 'Y') {
		$PartnerTicket = $_SESSION['PARTNERTICKET'];
	} else {
		$FieldNames = getxmediaAPIAcctID($ACCOUNTID);
		$PARTNERGUID = $FieldNames[0];
		$PARTNERPASSWORD = $FieldNames[1]; 
	
		$AuthenticateCurl = getAuthenticateCurl($ACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);	
		$PartnerTicket = $AuthenticateCurl['PartnerTicket'];
	}
	
	$logger2 = new FileLogger("getContactEx.log"); 
	
	
	$EventIds = [20103,20508]; // 20103 : Opened , 20508 : Direct Mail Succeeded
	$RequiredColumns = ["Date", "Event_ID", "Program_ID", "PURL"];
	
	$ContinuationID = 0;
	$delimiter = '|';
	
	$purlList = array();
	do {
		$ActivityResponse = GetEventActivity($TEST, $ACCOUNTID, $ContinuationID, $PartnerTicket, $EventIds, $RequiredColumns, $fd, $td);
		if ($TEST == 't') {
			echo "ActivityResponse<br>";
			var_dump($ActivityResponse);
			echo "<br>";
		}
		
		$logger2->log("2 ActivityResponse = ". json_encode($ActivityResponse) );	
		
		$ContinuationID = $ActivityResponse->{"ContinuationID"};
		$EventActivities = $ActivityResponse->{"EventActivities"};
		
		$EventData = explode("\r\n",$EventActivities);
		
		$logger2->log("2 EventData = ". json_encode($EventData) );
		
		$row1 = 0;
		$sqlCount = 0;
		foreach( $EventData as $rowData ) {	
			if ($row1 > 0) {
				$colData = explode(",",$rowData);
				
				// $logger2->log("2 colData = ". json_encode($colData) );
				
				$Date = $colData[0];
				$EventID = $colData[1];
				$ProgramID = $colData[2];
				$PURL = $colData[3];
				$EventID = '20103';
				
				if ($PURL) {
							
				} else {
					$PURL = '';
				}
				if ($ProgramID == $PROGRAMID) {
					$sqlData = $EventID.$delimiter.$ProgramID.$delimiter.$PURL;
					if ($TEST == 't') {
						echo "7626 sqlData : $sqlData<br>";
					}
					
					$logger2->log("7626 sqlData : $sqlData" );	
				
					if( (startsWith($PURL,"Deleted_")) || ($PURL == '') ){
					} else {
						if (array_key_exists($PURL, $purlList)) {
							$mustAdd = false;
						} else {
							$purlList[$PURL] = $PURL;
						}
					}
				}
			}
			$row1++;
		}
	} while($ContinuationID <> 0);
	
	
	$result['purlList'] = $purlList;
	$result['purlCount'] = sizeof($purlList);
	return $result;
}


function updateStripeCustomer($StripeCustomer, $accountID) {
	
	try{
	   if ($accountID > 0) {
	   	if($db==null){
				$db = GetPDOMySQL();
			}		
			$stmt = $db->prepare("UPDATE DV_UserInfo set StripeCustomer=? where acctID=?");
			$stmt->execute(array($StripeCustomer, $accountID));	
	   }
		$success = true;
		$errorMessage = '';
	}catch(PDOException $ex){
		$success = false;
		$errorMessage = "ex: ".$ex; 
	}
	$result['success'] = $success;
	$result['errorMessage'] = $errorMessage;
	return $result;
}


function updateStripeSource($stripeToken, $accountID) {	
	try{
	   if($db==null){
			$db = GetPDOMySQL();
		}		
		$stmt = $db->prepare("Select * from DV_UserInfo where acctID=? limit 1");
		$stmt->execute(array($accountID));	
		
		
		while ($row = $stmt->fetch()) {
			$docData = json_decode($row['data'],true);
			$docData['stripeToken'] =  $stripeToken;
			$saveData = json_encode($docData);
			if ($accountID > 0) {
				$stmt = $db->prepare("UPDATE DV_UserInfo set data=?,StripeCustomer='' where acctID=?");
				$stmt->execute(array($saveData, $accountID));	
			}
		}
		
		
		$success = true;
		$errorMessage = '';
	}catch(PDOException $ex){
		$success = false;
		$errorMessage = "ex: ".$ex; 
	}
	$result['success'] = $success;
	$result['errorMessage'] = $errorMessage;
	return $result;
}

function getStrpieReportID($id) {	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Conversion_Report where id=? limit 1");
		$Stmt->execute(array($id));
		$affected_rows = $Stmt->rowCount();
		$acctID = '';
		$logID = '';
		$table = '';
		$mode = '';
		
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);				
			$acctID = $docData['acctID'];
			$logID = $docData['logID'];
			$table = $docData['table'];
			$mode = $docData['mode'];
			
			$group = array(					
				'acctID'=>$acctID,
				'logID'=>$logID,
				'table'=>$table,
				'mode'=>$mode
			);								
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $group;
}


function createStripeReport($reportData) {
	$t1 = microtime(true);
	$ID = md5($t1);	
	
	$reportInfo = json_encode($reportData);
	
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$table = 'DV_Conversion_Report';
		$insertStmt = MySQL_Prepare($db,"INSERT INTO $table(ID, data, CreateTime) ".
			"VALUES(?, ?, NOW()) ".
			"on duplicate key update ".
			"data=?"
		);
		$insertStmt->execute(array($ID, $reportInfo, $reportInfo));
		$affected_rows = $insertStmt->rowCount();
		$success = true;
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
	}
	
	$CurrentConfig = davinciSetConfig();
	$davinciDomain = $CurrentConfig->davinciDomain;
	$detailURL    = "$davinciDomain"."admin/change_ccInfo.php";
	$stripeReport = "$detailURL?p=$ID";
	
	$result['stripeReport'] = $stripeReport;
	return $result;
}

function createDownloadCSV($reportData) {
	$t1 = microtime(true);
	$ID = md5($t1);	
	
	$reportInfo = json_encode($reportData);
	
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$table = 'DV_Conversion_Report';
		$insertStmt = MySQL_Prepare($db,"INSERT INTO $table(ID, data, CreateTime) ".
			"VALUES(?, ?, NOW()) ".
			"on duplicate key update ".
			"data=?"
		);
		$insertStmt->execute(array($ID, $reportInfo, $reportInfo));
		$affected_rows = $insertStmt->rowCount();
		$success = true;
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
	}
	
	$CurrentConfig = davinciSetConfig();
	$davinciDomain = $CurrentConfig->davinciDomain;
	$detailURL    = "$davinciDomain"."admin/DownloadLogin.html";
	$stripeReport = "$detailURL?p=$ID";
	
	$result['stripeReport'] = $stripeReport;
	return $result;
}

function getDownloadCSV($id) {	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Conversion_Report where id=? limit 1");
		$Stmt->execute(array($id));
		$affected_rows = $Stmt->rowCount();
		$acctID = '';
		$logID = '';
		$table = '';
		$mode = '';
		
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);				
			$acctID = $docData['acctID'];
			$mode = $docData['mode'];
			$S3FileURL = $docData['S3FileURL'];
			
			
			$group = array(					
				'acctID'=>$acctID,
				'S3FileURL'=>$S3FileURL,
				'mode'=>$mode
			);								
		}
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $group;
}

function getDiffDate($date1, $date2) {
	if ($date1 == '') {
		
	}
	$datetime1 = new DateTime(substr($date1,0,10));
	$datetime2 = new DateTime($date2);
	$interval = $datetime1->diff($datetime2);
	
	$ret = $interval->format('%R%a');
	$retnum = intval($ret);
	return $retnum;
}
function getPromoMoreInfo($productCode) {
	$productData = '';
	$productName = '';
	$productImage = '';
	$itemCount = '';
	$guaranteeText = '';
	$productDescription = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		
		
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Product where productCode=? limit 1");		
		$Stmt->execute(array($productCode));
		$affected_rows = $Stmt->rowCount();
		if ($affected_rows > 0) {

		} else {
			$productCode = 'default';
			$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Product WHERE productCode=? limit 1");
			$Stmt->execute(array($productCode));
		}
		while ($row = $Stmt->fetch()) {
			$productData = $row['productData'];		
			$docData = json_decode($productData,true);	
			
			$p1 = $docData['productName'];
			if ($p1) {
				$productName = $p1;
			} else {
				$productName = $row['productName'];	
			}
			$p2 = $docData['productImage'];
			if ($p2) {
				$productImage = $p2;
			} else {
				$productImage = 'https://s3.amazonaws.com/mindfiredavinci/img/LGM_Product-Image-min.jpg';
			}
			$p3 = $docData['itemCount'];
			if ($p3) {
				$itemCount = $p3;
			} else {
				$itemCount = '7';
			}
			$p4 = $docData['guaranteeText'];
			if ($p4) {
				$guaranteeText = $p4;
			} else {
				$guaranteeText = "<div ng-show='!SignUpStatusMsg4' class='alert alert-success' role='alert'><i class='fa fa-bell-o' aria-hidden='true'></i> <strong>30 Day Guarantee</strong>: Sign up today, if you don't like it for ANY REASON, let us know and we'll give your money back.</div>";
			}
			$p5 = $docData['productDescription'];
			if ($p5) {
				$productDescription = $p5;
			} else {
				$productDescription = "Includes all $itemCount items shown on the right.";
			}
			$p6 = $docData['displayAmount'];
			if ($p6) {
				$displayAmount = $p6;
			} else {
				$displayAmount = $amount;
			}
			$p7 = $docData['buttonText'];
			if ($p7) {
				$buttonText = $p7;
			} else {
				$buttonText = "YES!  Get The $productName Now!";
			}
			$ProductIncluded = getProductIncluded($productCode);
			$UiAccess = $ProductIncluded['UiAccess'];
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	$docData = json_decode($productData,true);
	$result['productData'] = $docData;
	$result['productName'] = $productName;
	$result['productNameUpper'] = strtoupper($productName);
	$result['productImage'] = $productImage;
	$result['itemCount'] = $itemCount;
	$result['guaranteeText'] = $guaranteeText;
	$result['UiAccess'] = $UiAccess;
	
	return $result;
}


function setBillingExpireDate($billData) {
	
	$logger2 = new FileLogger("setBillingExpireDate.log"); 
	$logger2->log("2 billData = ". json_encode($billData) );
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}

		$acctID = $billData['acctID'];
		$recurringDate = $billData['recurringDate'];
		$expireDate = $billData['expireDate'];
		$modifyDay = $billData['modifyDay'];
		$caller = $billData['caller'];
		$billPeriod = $billData['billPeriod'];
		$billProduct = $billData['billProduct'];
		$quantity = $billData['quantity'];
		$unitPrice = $billData['unitPrice'];
		$renew = $billData['renew'];
		
		$modifyDayCalc = $modifyDay*$quantity;
		
		$sql = '';
		if ($renew == 'Y') {
			if ($expireDate) {
				$mode = 'has expireDate'; //expireDate = expireDate + modifyDay
				
				$sql = "UPDATE DV_UserInfo set lastBillingDate=NOW(),expireDate=DATE_ADD(expireDate, INTERVAL $modifyDayCalc DAY) where acctID=?";
			} else if ($recurringDate) {
				$mode = 'has recurringDate'; // expireDate = recurringDate + modifyDay
				$sql = "UPDATE DV_UserInfo set lastBillingDate=NOW(),expireDate=DATE_ADD(recurringDate, INTERVAL $modifyDayCalc DAY) where acctID=?";
			} else {
				$mode = 'no recurringDate and expireDate'; // recurringDate = now and expireDate = now + modifyDay
				$sql = "UPDATE DV_UserInfo set lastBillingDate=NOW(),recurringDate=NOW(),expireDate=DATE_ADD(NOW(), INTERVAL $modifyDayCalc DAY) where acctID=?";
			}
		} else {
			$mode = 'renew != Y';
		}
		
		$table = 'DV_UserExpire';
		$sql1 = "INSERT INTO $table(ID, createDate, acctID, recurringDate, expireDate, modifyDay, caller, billPeriod, billProduct, mode, quantity) ".
			"VALUES(NULL,NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?) ";
		$insertStmt = MySQL_Prepare($db,$sql1);
		$insertStmt->execute(array($acctID, $recurringDate, $expireDate, $modifyDay, $caller, $billPeriod, $billProduct, $mode, $quantity));
		$affected_rows = $insertStmt->rowCount();
		$insertID = $db->lastInsertId();
		
		
		if ($sql != '') {
			if ($acctID > 0) {
				$stmt2 = $db->prepare($sql);
				$stmt2->execute(array($acctID));
			}
			
	 	}
	 	
	 	
	 	
	 	$success = true;
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
		$insertID = -1;
		$success = false;
	}
	$result['success'] = $success;
	$result['acctID'] = $acctID;
	$result['mode'] = $mode;
	$result['recurringDate'] = $recurringDate;
	$result['expireDate'] = $expireDate;
	$result['insertID'] = $insertID;
	$result['sql'] = $sql;
	$result['sql1'] = $sql1;
	$logger2->log("2 setBillingExpireDateResult = ". json_encode($result) );
	return $result;
}

function saveDelinquency($billData) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}

		$billAccount = $billData['billAccount'];
		$recurringAmount = $billData['recurringAmount'];
		$recurringDate = $billData['recurringDate'];
		$recurringPeriod = $billData['recurringPeriod'];
		$recurringProduct = $billData['recurringProduct'];
		$lastBillingDate = $billData['lastBillingDate'];
		$expireDate = $billData['expireDate'];
		$delinquentDate = $billData['delinquentDate'];
		$accountCloseOverride = $billData['accountCloseOverride'];
		$userStatus = $billData['userStatus'];
		$smsLongCode = $billData['smsLongCode'];
		$enableWalletPass = $billData['enableWalletPass'];
		$AddthispassKey = $billData['AddthispassKey'];
		
		$sql2 = "";
		if ($billAccount == '') {
			$billAccount = '0';
		}
		
		if ($userStatus) {
		} else {
			$userStatus = '';
		}
		
		if ($smsLongCode) {
		} else {
			$smsLongCode = '';
		}
		
		if (($recurringDate) && ($recurringDate != '')) {
			$recurringDate = "$recurringDate 00:00:00";
			$recurringDate = convert_to_server_date($recurringDate, 'UTC', 'UTC', 'Y-m-d H:i:s');
			$sql2 .= " , recurringDate='$recurringDate' ";
		} else {
			$sql2 .= " , recurringDate=NULL ";
		}
		
		if (($lastBillingDate) && ($lastBillingDate != '')) {
			$lastBillingDate = "$lastBillingDate 00:00:00";
			$lastBillingDate = convert_to_server_date($lastBillingDate, 'UTC', 'UTC', 'Y-m-d H:i:s');
			$sql2 .= " , lastBillingDate='$lastBillingDate' ";
		} else {
			$sql2 .= " , lastBillingDate=NULL ";
		}
		
		if (($expireDate) && ($expireDate != '')) {
			$expireDate = "$expireDate 00:00:00";
			$expireDate = convert_to_server_date($expireDate, 'UTC', 'UTC', 'Y-m-d H:i:s');
			$sql2 .= " , expireDate='$expireDate' ";
		} else {
			$sql2 .= " , expireDate=NULL ";
		}
		
		if (($delinquentDate) && ($delinquentDate != '')) {
			$delinquentDate = "$delinquentDate 00:00:00";
			$delinquentDate = convert_to_server_date($delinquentDate, 'UTC', 'UTC', 'Y-m-d H:i:s');
			$sql2 .= " , delinquentDate='$delinquentDate' ";
		} else {
			$sql2 .= " , delinquentDate=NULL ";
		}
		
		if (($accountCloseOverride) && ($accountCloseOverride != '')) {
			$accountCloseOverride = "$accountCloseOverride 00:00:00";
			$accountCloseOverride = convert_to_server_date($accountCloseOverride, 'UTC', 'UTC', 'Y-m-d H:i:s');
			$sql2 .= " , accountCloseOverride='$accountCloseOverride' ";
		} else {
			$sql2 .= " , accountCloseOverride=NULL ";
		}
		
		if (($AddthispassKey) && ($AddthispassKey != '')) {
			$sql2 .= " , AddthispassKey='$AddthispassKey' ";
		}
		
		$sql = '';
		$table = 'DV_UserInfo';
		$sql1 = "UPDATE $table set recurringAmount=? , recurringPeriod=? , recurringProduct=? , userStatus=? , smsLongCode=? , enableWalletPass=?";
		$sql1 .= $sql2;
		$sql1 .= " where acctID=?";
		if ($billAccount > 0) {
			$insertStmt = MySQL_Prepare($db,$sql1);
			$insertStmt->execute(array($recurringAmount, $recurringPeriod, $recurringProduct, $userStatus, $smsLongCode, $enableWalletPass, $billAccount));
			$affected_rows = $insertStmt->rowCount();
		}
		
	 	$success = true;
	 	$errorMessage = '';
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;
		$success = false;
	}
	$result['success'] = $success;
	$result['sql1'] = $sql1;
	$result['errorMessage'] = $errorMessage;
	$result['billData'] = $billData;
	return $result;
}

function reinstateAccount($billData) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}

		$billAccount = $billData['billAccount'];
		$accountName = $billData['accountName'];
		
		
		$sql2 = "";
		$userStatus = '';
		$logger = new FileLogger("reinstateAccount.log");
		$logger->log("2 billAccount $billAccount , accountName : $accountName");
		
		
	 	
	 	$studioParam = GetStudioAPIParam();
		$POWERACCOUNTID = $studioParam['accountID'];
		$EMAIL = $studioParam['email'];
		$PWD = $studioParam['pwd'];
		$PARTNERGUID = $studioParam['guid'];
		$PARTNERPASSWORD = $studioParam['password'];
	
		$Across = getAcrossAuthenticateCurl($POWERACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
		$powerTicket = $Across['userTicket'];
	 	$UpdateAccountRequest   = array
		(		
			"Credentials" => array
			(
				"Ticket" => $powerTicket        
			),
			"AccountID" => $billAccount,
			"Name" => $accountName,
			"Status" => 1,
			"ServicePackage_ID" => 2
		);
		$UpdateAccountResponse = callService("accountservice/UpdateAccount", $UpdateAccountRequest);
		
		$logger->log("2 reinstateAccount UpdateAccountRequest = ". json_encode($UpdateAccountRequest) );
		$logger->log("2 reinstateAccount UpdateAccountResponse = ". json_encode($UpdateAccountResponse) );
		$ErrorCode = $UpdateAccountResponse->{"Result"}->{"ErrorCode"};
		
		if ($ErrorCode == "") {
			$errorMessage = '';
			$status = true;
			
			$sql = '';
			$table = 'DV_UserInfo';
			$sql1 = "UPDATE $table set userStatus=?";
			$sql1 .= $sql2;
			$sql1 .= " where acctID=?";
			if ($billAccount > 0) {
				$insertStmt = MySQL_Prepare($db,$sql1);
				$insertStmt->execute(array($userStatus, $billAccount));
				$affected_rows = $insertStmt->rowCount();
			}
			
		 	$success = true;
		 	$errorMessage = '';
			
		} else {
			$status = false;
			//$errorMessage = "UpdateAccountResponse ERROR : <br> ErrorMessage -> ".$UpdateAccountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
			//"ExceptionMessage : ".$UpdateAccountResponse->{"Result"}->{"ExceptionMessage"};
			$errorMessage = "Cannot reopen Studio account, please contact L2";
		}
	}  
	catch(exception $e) {
		$errorMessage = "Cannot reopen Studio account, please contact L2";
		$affected_rows = -1;
		$success = false;
	}
	$result['success'] = $success;
	$result['sql1'] = $sql1;
	$result['errorMessage'] = $errorMessage;
	$result['errorCode'] = $ErrorCode;
	$result['billData'] = $billData;
	return $result;
}

function updateAccount($billData) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}

		$billAccount = $billData['billAccount'];
		$accountName = $billData['accountName'];
		$UiAccess = $billData['UiAccess'];
		if ($UiAccess) { } else { $UiAccess = 1; };
		
		$sql2 = "";
		$userStatus = '';
		$logger = new FileLogger("updateAccount.log");
		$logger->log("2 billAccount $billAccount , accountName : $accountName, UiAccess : $UiAccess");
		
		
	 	
	 	$studioParam = GetStudioAPIParam();
		$POWERACCOUNTID = $studioParam['accountID'];
		$EMAIL = $studioParam['email'];
		$PWD = $studioParam['pwd'];
		$PARTNERGUID = $studioParam['guid'];
		$PARTNERPASSWORD = $studioParam['password'];
	
		$Across = getAcrossAuthenticateCurl($POWERACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
		
		$logger->log("2 POWERACCOUNTID $POWERACCOUNTID , EMAIL : $EMAIL, PWD : $PWD, PARTNERGUID : $PARTNERGUID, PARTNERPASSWORD : $PARTNERPASSWORD");
		
		$powerTicket = $Across['userTicket'];
		$Credentials = $Across['Credentials'];
		$studioRequest = GetCurrentConfig()->studioRequest;
		if ($studioRequest == 'live') {
		 	$UpdateAccountRequest   = array
			(		
				"Credentials" => $Credentials,
				"AccountID" => $billAccount,
				"Name" => $accountName,
				"Status" => 1,
				"ServicePackage_ID" => 2,
				"UiAccess" => $UiAccess,
			);
		} else {
			$UpdateAccountRequest   = array
			(		
				"Credentials" => $Credentials,
				"AccountID" => $billAccount,
				"Name" => $accountName,
				"Status" => 1,
				"ServicePackage_ID" => 2,
				"UiAccess" => $UiAccess,
			);
		}
		$UpdateAccountResponse = callServiceCredential("accountservice/UpdateAccount", $UpdateAccountRequest);
		//$logger->log("2 UpdateAccountRequest = ". json_encode($UpdateAccountRequest) );
		//$logger->log("2 UpdateAccountResponse = ". json_encode($UpdateAccountResponse) );
		
		$ErrorCode = $UpdateAccountResponse->{"Result"}->{"ErrorCode"};
		
		if ($ErrorCode == "") {
			$errorMessage = '';
			$status = true;
			
		 	$success = true;
		 	$errorMessage = '';
			
		} else {
			$status = false;
			//$errorMessage = "UpdateAccountResponse ERROR : <br> ErrorMessage -> ".$UpdateAccountResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
			//"ExceptionMessage : ".$UpdateAccountResponse->{"Result"}->{"ExceptionMessage"};
			$errorMessage = "Cannot update Studio account, please contact L2";
		}
	}  
	catch(exception $e) {
		$errorMessage = "Cannot update Studio account, please contact L2";
		$affected_rows = -1;
		$success = false;
	}
	$result['success'] = $success;
	$result['sql1'] = $sql1;
	$result['errorMessage'] = $errorMessage;
	$result['errorCode'] = $ErrorCode;
	$result['billData'] = $billData;
	return $result;
}

function setDateFormat($date, $format) {
	if ($date) {
		$date= date_format(date_create($date),"m/d/Y") ; 	
	} else {
		$date = '';
	}
	return $date;
}

function canLoginStatus($accountID) {
   $DVUser = getDVUserinfo($accountID);
               
   $objDateTime = new DateTime('NOW');
   $runDate = $objDateTime->format('Y-m-d');
   $expireDate = $DVUser['expireDate'];
   $delinquentDate = $DVUser['delinquentDate'];
   $accountCloseOverride = $DVUser['accountCloseOverride'];
   $recurringAmount = $DVUser['recurringAmount'];
   
   $success = true;
   $showExpire = false;
   $mustLogout = false;
   $expiredMessage = '';
   $expireDateD30 = '';
   $expireDateA15 = '';
   $delinquentDateA15 = '';
   $accountCloseOverrideD30 = '';
   $mode = '';
   
   $cannotLoginMessage = "Your account is past due and has been terminated. Please email billing@mindfireinc.com to reinstate.";
   
   if ($expireDate) {
   	$expireDate = substr($expireDate,0,10);
   	$expireDateD30 = date('Y-m-d', strtotime($expireDate.' - 30 days'));
   	$expireDateA15 = date('Y-m-d', strtotime($expireDate.' + 15 days'));
   } else {
   	$expireDate = '';
   }
   
   if ($delinquentDate) {
   	$delinquentDate = substr($delinquentDate,0,10);
   	$delinquentDateA15 = date('Y-m-d', strtotime($delinquentDate.' + 15 days'));
   } else {
   	$delinquentDate = '';
   }
   
   if ($accountCloseOverride) {
   	$accountCloseOverride = substr($accountCloseOverride,0,10);
   	$accountCloseOverrideD30 = date('Y-m-d', strtotime($accountCloseOverride.' - 30 days'));
   } else {
   	$accountCloseOverride = '';
   }
   
   if ($expireDate == '') {
   	$mode = "0 expireDate = ''";
   }else{
   	
   		if (($delinquentDate == '') && ($accountCloseOverride == '')) {
	   		if ($runDate > $expireDateA15) {
	   			$mode = "A-1 today [$runDate] > expiryDate+15 [$expireDateA15]";
		   		$success = false ;
		   		$mustLogout = true;
		   		$expiredMessage = $cannotLoginMessage;
	   		} else if (($runDate > $expireDate) && ($runDate <= $expireDateA15)) {
	   			$mode = "A-2 today [$runDate] > expiryDate [$expireDate] and today [$runDate] <= expiryDate+15 [$expireDateA15]";
		   		$messageDate = date('m/d/Y', strtotime($expireDate.' + 15 days'));
		         $expiredMessage = "Your account is past due and you may lose access after $messageDate. Please contact billing@mindfireinc.com to bring your account up to date. Thank you.";
		         $showExpire = true;
	   		} else if (($runDate > $expireDateD30) && ($runDate < $expireDate)) {
	   			if ($recurringAmount == '0') {
	   				$mode = "A-3.1 today [$runDate] > expiryDate - 30 [$expireDateD30] and today [$runDate] < expiryDate [$expireDate] and recurringAmount = $recurringAmount";
		   			$messageDate = date('m/d/Y', strtotime($expireDate));
		   			$expiredMessage = "Your free trial will expire on $messageDate.<br>You will be automatically upgraded to a paid account on that day. You can <a href='https://bit.ly/upgradedavinci'>CLICK HERE</a> to upgrade now.";
		   			$showExpire = true;
	   			} else {
	   				$mode = "A-3.2 today [$runDate] > expiryDate - 30 [$expireDateD30] and today [$runDate] < expiryDate [$expireDate] and recurringAmount = $recurringAmount";
		   			$messageDate = date('m/d/Y', strtotime($expireDate));
		   			$expiredMessage = "Your account renewal is due on $messageDate. Please contact billing@mindfireinc.com to renew.";
		   			$showExpire = true;
	   			}
	   		} else {
	   			$mode = "A-4 Can Login";
	   		}
	   } else {
		   	if (($delinquentDate != '') && ($accountCloseOverride == '')) {
		   		if ($runDate > $expireDateA15) {
		   			$mode = "B-1 today [$runDate] > expiryDate+15 [$expireDateA15]";
		   			$success = false;
		   			$mustLogout = true;
		   			$expiredMessage = $cannotLoginMessage;
		   		} else if ($runDate > $delinquentDateA15) {
		   			$mode = "B-2 today [$runDate] > delinquentDate+15 [$delinquentDateA15]";
		   			$success = false;
		   			$mustLogout = true;
		   			$expiredMessage = $cannotLoginMessage;
		   		} else if (($runDate > $expireDate) && ($runDate <= $expireDateA15)) {
	   				$mode = "B-3 today [$runDate] > expiryDate [$expireDate] and today [$runDate] <= expiryDate+15 [$expireDateA15]";
		   			$messageDate = date('m/d/Y', strtotime($expireDate.' + 15 days'));
		         	$expiredMessage = "Your account is past due and you may lose access after $messageDate. Please contact billing@mindfireinc.com to bring your account up to date. Thank you.";
		         	$showExpire = true;
		   		} else if (($runDate > $delinquentDate) && ($runDate <= $delinquentDateA15)) {
		   			$mode = "B-4 today [$runDate] > delinquentDate [$delinquentDate] and today [$runDate] <= delinquentDate+15 [$delinquentDateA15]";
		   			$messageDate = date('m/d/Y', strtotime($delinquentDate.' + 15 days'));
		         	$expiredMessage = "Your account is past due and you may lose access after $messageDate. Please contact billing@mindfireinc.com to bring your account up to date. Thank you.";
		         	$showExpire = true;
		   		} else {
		   			$mode = "B-5 Can Login";
		   		}
		   	} else if ($accountCloseOverride != '') {
		   		if ($runDate > $accountCloseOverride) {
		   			$mode = "C-1 today [$runDate] > accountCloseOverride [$accountCloseOverride]";
		   			$mustLogout = true;
		   			$success = false;
		   			$expiredMessage = $cannotLoginMessage;
		   		} else if ($runDate > $accountCloseOverrideD30) {
		   			$mode = "C-2 today [$runDate] > accountCloseOverride - 30 [$accountCloseOverrideD30]";
		   			$messageDate = date('m/d/Y', strtotime($accountCloseOverride));
		         	//$expiredMessage = "Your account renewal is due on $messageDate. Please contact billing@mindfireinc.com to renew.";
		         	$expiredMessage = "Your account is past due and will be terminated after $messageDate. Please contact billing@mindfireinc.com to renew.";
		         	$showExpire = true;
		   		} else {
		   			$mode = "C-3 Can Login";
		   		}
		   	} else {
		   		$mode = "D";
		   	}
	   }
   }
   
   $result['success'] = $success;
   $result['mode'] = $mode;
   $result['message'] = $expiredMessage;
   $result['expiredMessage'] = $expiredMessage;
   $result['showExpire'] = $showExpire;
   $result['mustLogout'] = $mustLogout;
   
   $result['runDate'] = $runDate;
   $result['expireDate'] = $expireDate;
   $result['delinquentDate'] = $delinquentDate;
   $result['accountCloseOverride'] = $accountCloseOverride;
   $result['expireDateD30'] = $expireDateD30;
   $result['expireDateA15'] = $expireDateA15;
   $result['delinquentDateA15'] = $delinquentDateA15;
   $result['accountCloseOverrideD30'] = $accountCloseOverrideD30;
   $result['mustLogout'] = $mustLogout;
   return $result;
} 



function getCampaignMeasures($param) {
    
   $acctID = $param['acctID'];
   $programID = $param['programID'];
   $campaignType = $param['campaignType'];
   $totalEmail = $param['totalEmail'];
   $dvProgramID = $param['dvProgramID'];
   $campaignName = $param['campaignName'];
   $EMAIL = $param['EMAIL'];
   $PWD = $param['PWD'];
   $fd = $param['fd'];
   $td = $param['td'];
   $TEST = $param['test'];
    
   $sqltxt = "select * from DV_CampaignMeasures where acctID = ? and campaignID = ? limit 1";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($acctID,$dvProgramID));
	$affected_rows = $Stmt->rowCount();
	if ($affected_rows == 0) {
		
		if($db == null){
			$db = GetPDOMySQL();
		}
		$Reach = 0;
		$Click = 0;
		$ClickedRate = 0;
		$sql = 'INSERT INTO DV_CampaignMeasures (id,createDate,lastUpdateDate,acctID,campaignID,campaignName,studioID,campaignType,Reach,Click,ClickedRate) VALUES (NULL,NOW(),NOW(),?,?,?,?,?,?,?,?)';
		
		try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($acctID,$dvProgramID,$campaignName,$programID,$campaignType,$Reach,$Click,$ClickedRate));
		} catch(Exception $e){
			$success = false;	
			$ErrorMessage = $e->getMessage();
		}
		
		
		$token = getToken($acctID, $EMAIL, $PWD);
		$moreParam = []; 
		$moreParam['campaignType'] = $campaignType; 
		
		if ($campaignType == 'PromoteEbook') {
         $result = GetCampaignReportPage($TEST, $acctID, $programID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $dvProgramID);
         
		} else if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' ) {
			$result = GetCampaignReportEmailSummary($TEST, $acctID, $programID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $dvProgramID,$moreParam);
			
		} else if ($campaignType == 'EventReminder') {
			$result = GetCampaignReportEmailSummary($TEST, $acctID, $programID, $campaignName, $fd, $td, $token, $EMAIL, $PWD, $dvProgramID,$moreParam);	
			
		} else if ($campaignType == 'BuildAwareness') {
         $result = GetCampaignReportPageBA($TEST, $acctID, $programID, $campaignName, $fd, $td, $token, $totalEmail, $EMAIL, $PWD, $dvProgramID, null);
   	}
      return $result;
	}
	while ($row2 = $Stmt->fetch()) {
		
		$ReachShow = $row2['Reach'];
		$ClickShow = $row2['Click'];
		
		if ($ReachShow) { } else { $ReachShow = 0; }
		
		if ($ClickShow) { } else { $ClickShow = 0; }
		
	   if ($campaignType == 'PromoteEbook') {
	       
	       $success = true;
	       $Reach = $row2['CLReach'];
	       $Submits = $row2['CLSubmits'];
	       $conversion = $row2['CLConversion'];
	       $download = 0;
	       $welcome = 0;
	       $reportURL = '';
	       
	       if ($Reach) { } else { $Reach = 0; }
	       if ($Submits) { } else { $Submits = 0; }
	       if ($conversion) { } else { $conversion = 0; }
	       
	       if ($Reach == 0) { $Reach = $row2['Reach']; }
	       if ($Submits == 0) { $Submits = $row2['Click']; }
	       if ($conversion == 0) { $conversion = $row2['ClickedRate']; }
	       
	        $result = json_encode( array(
	    		'success'=>$success,
	    		'ReachShow' => $ReachShow, 
        		'ClickShow' => $ClickShow, 
	    		'rows' => $rows, 
	    		'Reach' => $Reach, 
	    		'conversion' => $conversion, 
	    		'download' => $download, 
	    		'welcome' => $welcome, 
	    		'Submits' => $Submits, 
	    		'reportURL' => $reportURL
    	    ));
    	    return $result;
    	    
	   } else if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT') {  //#2176
		   	$Email = '';
		   	$Sent = $row2['DTSent'];
		   	$Delivered = $row2['DTDelivered'];
		   	$Opened = $row2['DTOpened'];
		   	$Clicked = $row2['DTClicked'];
		   	$FailedToProcess = $row2['DTFailedToProcess'];
		   	$HardBounced = $row2['DTHardBounced'];
		   	$SoftBounced = $row2['DTSoftBounced'];
		   	$Suppressed = $row2['DTSuppressed'];
		   	$MarkedAsSpam = $row2['DTMarkedAsSpam'];
		   	$Unsubscribed = $row2['DTUnsubscribed'];
		   	$conversion = $row2['DTConversion'];
		   	
		   	if ($Sent) { } else { $Sent = 0; }
		   	if ($Delivered) { } else { $Delivered = 0; }
		   	if ($Opened) { } else { $Opened = 0; }
		   	if ($Clicked) { } else { $Clicked = 0; }
		   	if ($FailedToProcess) { } else { $FailedToProcess = 0; }
		   	if ($HardBounced) { } else { $HardBounced = 0; }
		   	if ($SoftBounced) { } else { $SoftBounced = 0; }
		   	if ($Suppressed) { } else { $Suppressed = 0; }
		   	if ($MarkedAsSpam) { } else { $MarkedAsSpam = 0; }
		   	if ($Unsubscribed) { } else { $Unsubscribed = 0; }
		   	if ($conversion) { } else { $conversion = 0; }
		   	
		   	$group = array(		
		   		'acctID'=>$acctID,
		   		'programID'=>$programID,
		   		'campaignType'=>$campaignType,
		   		'ReachShow' => $ReachShow, 
        		'ClickShow' => $ClickShow, 
				'Email'=>$Email,
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'Unsubscribed'=>$Unsubscribed,
				'conversion'=>$conversion
			);					
			$rows[] = $group;
		   	
		   	$success = true;
		   	$reportURL = '';
		   	$token = '';
		   	$result = json_encode( array(
		   		'success'=>$success, 
		   		'rows' => $rows, 
		   		'reportURL' => $reportURL, 
		   		'token' => $token
		   	));
		   	return $result;
		   	
		} else if ($campaignType == 'EventReminder') { //#1185
		   	$Email = '';
		   	$Sent = $row2['DTSent'];
		   	$Delivered = $row2['DTDelivered'];
		   	$Opened = $row2['DTOpened'];
		   	$Clicked = $row2['DTClicked'];
		   	$FailedToProcess = $row2['DTFailedToProcess'];
		   	$HardBounced = $row2['DTHardBounced'];
		   	$SoftBounced = $row2['DTSoftBounced'];
		   	$Suppressed = $row2['DTSuppressed'];
		   	$MarkedAsSpam = $row2['DTMarkedAsSpam'];
		   	$Unsubscribed = $row2['DTUnsubscribed'];
		   	$conversion = $row2['DTConversion'];
		   	
		   	if ($Sent) { } else { $Sent = 0; }
		   	if ($Delivered) { } else { $Delivered = 0; }
		   	if ($Opened) { } else { $Opened = 0; }
		   	if ($Clicked) { } else { $Clicked = 0; }
		   	if ($FailedToProcess) { } else { $FailedToProcess = 0; }
		   	if ($HardBounced) { } else { $HardBounced = 0; }
		   	if ($SoftBounced) { } else { $SoftBounced = 0; }
		   	if ($Suppressed) { } else { $Suppressed = 0; }
		   	if ($MarkedAsSpam) { } else { $MarkedAsSpam = 0; }
		   	if ($Unsubscribed) { } else { $Unsubscribed = 0; }
		   	if ($conversion) { } else { $conversion = 0; }
		   	
		   	$group = array(		
		   		'acctID'=>$acctID,
		   		'programID'=>$programID,
		   		'campaignType'=>$campaignType,
				'Email'=>$Email,
				'ReachShow' => $ReachShow, 
        		'ClickShow' => $ClickShow, 
				'Sent'=>$Sent,
				'Delivered'=>$Delivered,
				'Opened'=>$Opened,
				'Clicked'=>$Clicked,
				'FailedToProcess'=>$FailedToProcess,
				'HardBounced'=>$HardBounced,
				'SoftBounced'=>$SoftBounced,
				'Suppressed'=>$Suppressed,
				'MarkedAsSpam'=>$MarkedAsSpam,
				'Unsubscribed'=>$Unsubscribed,
				'conversion'=>$conversion
			);					
			$rows[] = $group;
		   	
		   	$success = true;
		   	$reportURL = '';
		   	$token = '';
		   	$result = json_encode( array(
		   		'success'=>$success, 
		   		'rows' => $rows, 
		   		'reportURL' => $reportURL, 
		   		'token' => $token
		   	));
		   	return $result;	 	
	       
	   } else if ($campaignType == 'BuildAwareness') {
	       
	        $download = '0';
            $welcome = '0';
        	$Reach = $row2['BAReach'];
        	$conversion = '0';
        	$reportURL = '';
        	$Sent = '0';
        	$Exit = $row2['BAExit'];
        	$Complete = $row2['BAComplete'];
        	$Unsubscribed = $row2['BAUnsubscribed'];
        	$Delivered = $row2['BADelivered'];
        	
        	if ($Reach) { } else { $Reach = 0; }
        	if ($Exit) { } else { $Exit = 0; }
        	if ($Complete) { } else { $Complete = 0; }
        	if ($Unsubscribed) { } else { $Unsubscribed = 0; }
        	if ($Delivered) { } else { $Delivered = 0; }
        	
        	
        	if ($Reach == 0) {
        		$DeliveredRate = 0;
        	} else {
        		$DeliveredRate = 100 * ($Delivered / $Reach);		
        	}
        	
        	if ($Delivered == 0) {
        		$CompleteRate = 0;
        		$ExitRate = 0;
        		$UnsubscribedRate = 0;
        	} else {
        		$CompleteRate = 100 * ($Complete / $Delivered);
        		$ExitRate = 100 * ($Exit / $Delivered);		
        		$UnsubscribedRate = 100 * ($Unsubscribed / $Delivered);		
        	}
	       
	       $success = true;
	        $result = json_encode( array(
        		'success'=>$success, 
        		'rows' => $rows,
        		'ReachShow' => $ReachShow, 
        		'ClickShow' => $ClickShow, 
        		'Sent' => $Sent, 
        		'Delivered' => $Delivered, 
        		'Complete' => $Complete, 
        		'Exit' => $Exit, 
        		'Reach' => $Reach, 
        		'conversion' => $conversion, 
        		'download' => $download, 
        		'welcome' => $welcome, 
        		'Submits' => $Submits, 
        		'reportURL' => $reportURL,
        		'UnsubscribedRate' => $UnsubscribedRate,
        		'DeliveredRate' => $DeliveredRate,
        		'ExitRate' => $ExitRate,
        		'CompleteRate' => $CompleteRate,
        		'Unsubscribed' => $Unsubscribed,
        		'str' => $str,
        		'totalEmail' => $totalEmail,
        		
        	));
        	return $result;
	   }
		
	}
}


function getCampaignMeasuresList($ACCOUNTID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from DV_CampaignMeasures where acctID = ?";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}

	return $row2;
}


function saveCampaignMeasures($param) {
	$success = true;
	$ErrorMessage = '';
	
	$acctID = $param['acctID'];
	$campaignID = $param['campaignID'];
	$campaignName = $param['campaignName'];
	$studioID = $param['studioID'];
	$campaignType = $param['campaignType'];
	$Reach = $param['Reach'];
	$Click = $param['Click'];
	$ClickedRate = $param['ClickedRate'];
	
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$sql = 'INSERT INTO DV_CampaignMeasures (id,createDate,lastUpdateDate,acctID,campaignID,campaignName,studioID,campaignType,Reach,Click,ClickedRate) VALUES (NULL,NOW(),NOW(),?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE lastUpdateDate=NOW(),campaignName=?,studioID=?,Reach=?,Click=?,ClickedRate=?';
	
	try {
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($acctID,$campaignID,$campaignName,$studioID,$campaignType,$Reach,$Click,$ClickedRate,$campaignName,$studioID,$Reach,$Click,$ClickedRate));
	} catch(Exception $e){
		$success = false;	
		$ErrorMessage = $e->getMessage();
	}		
	
	return array(
		'success'=>$success, 
		'ErrorMessage'=>$ErrorMessage,
	);
}

function updateCampaignMeasuresData($TEST, $mode, $quantity, $ACCOUNTID, $fd, $td, $token, $EMAIL, $PWD) {
	$moreParam = array();
	return updateCampaignMeasuresMoreData($TEST, $mode, $quantity, $ACCOUNTID, $fd, $td, $token, $EMAIL, $PWD, $moreParam);
}

function updateCampaignMeasuresMoreData($TEST, $mode, $quantity, $ACCOUNTID, $fd, $td, $token, $EMAIL, $PWD, $moreParam) {
	
	$startReport = date('m.d.Y h:i:s');
	
	$CampaignMeasures = getCampaignMeasuresList($ACCOUNTID);
	$CampaignMeasures15Min = getCampaignMeasuresList15Min($ACCOUNTID);
	//$campaigns = MySQL_GetAllByKey("DV_CampaignList",["acctID"=>$ACCOUNTID]);
	$campaignSource = '';
	$campaigns = MySQL_GetAllByKey('DV_CampaignList',["acctID"=> $ACCOUNTID, "campaignSource"=> $campaignSource]);
	
	$manually = $moreParam['manually'];
	
	if ( $manually == 'y' ) {
		$logger2 = new FileLogger("getCampaignMeasuresDateRange.log");
		$campaignData = $CampaignMeasures;
	} else {
		$logger2 = new FileLogger("getReportCampaignStat.log");
		$campaignData = getCampaignMeasuresUpdate($ACCOUNTID);
	}
	
	if ($mode == 'refresh') {
		$sizeCheck = 0;
	} else {
		$sizeCheck = sizeof($CampaignMeasures);
	}
	
	$str1 = "STARTED -----------------------------------------";
	$logger2->log($str1);
	$str1 = "mode : $mode , sizeof CampaignMeasures : $sizeCheck , quantity : $quantity";
	$logger2->log($str1);
	
	
	if ( $sizeCheck > 0 ) {
		$DVSTAT = 'from CampaignMeasures';
		
		foreach( $campaigns as $campaign ) {	
			$campaignID = $campaign['campaignID'];
			$campaignName = $campaign['campaignName'];
			$createDate = $campaign['createDate'];
			$lastEditDate = $campaign['lastEditDate'];
			$status = $campaign['status'];
			$campaignType = $campaign['campaignType'];
			$campaignList = $campaign['campaignList'];
			
			$str1 = "8256 Working on CampaignMeasures $campaignName";
			$logger2->log($str1);
			
			$Reach = "0";
			$Click = "0";
			$ClickedRate = "0";
			$Open = "0";
			$Complete = "0";
			$ProgramID = "";
			
			$canFind = false;
			
			foreach( $CampaignMeasures as $CampaignMeasure ) {
				$Reach = "0";
				$Click = "0";
				$ClickedRate = "0";
				if ($campaignID == $CampaignMeasure['campaignID']) {			
					$Reach = $CampaignMeasure['Reach'];
					$Click = $CampaignMeasure['Click'];
					$ClickedRate = $CampaignMeasure['ClickedRate'];
					$ProgramID = $CampaignMeasure['studioID'];
					$canFind = true;
					
					if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder' ) {
						
						$DTSent = $CampaignMeasure['DTSent'];
						$DTClicked = $CampaignMeasure['DTClicked'];
						
						if ($DTSent) { } else { $DTSent = 0; }
	      			if ($DTClicked) { } else { $DTClicked = 0; }
						
						if ($DTSent != 0) { $Reach = $DTSent; }
						if ($DTClicked != 0) { $Click = $DTClicked; }
						
						
	      			
					} else if ($campaignType == 'PromoteEbook') {
						
						$CLReach = $CampaignMeasure['CLReach'];
						$CLSubmits = $CampaignMeasure['CLSubmits'];
						
						if ($CLReach) { } else { $CLReach = 0; }
	      			if ($CLSubmits) { } else { $CLSubmits = 0; }
						
						if ($CLReach != 0) { $Reach = $CLReach; }
						if ($CLSubmits != 0) { $Click = $CLSubmits; }
						
						
					} else if ($campaignType == 'BuildAwareness') {
						
						$BAReach = $CampaignMeasure['BAReach'];
						$BAComplete = $CampaignMeasure['BAComplete'];
						
						if ($BAReach) { } else { $BAReach = 0; }
	      			if ($BAComplete) { } else { $BAComplete = 0; }
						
						if ($BAReach != 0) { $Reach = $BAReach; }
						if ($BAComplete != 0) { $Click = $BAComplete; }
						
	      			
					}
					
					if ($Reach) { } else { $Reach = 0; }
	      		if ($Click) { } else { $Click = 0; }
					
					if ($Reach == '0') {
						$ClickedRate = "0";
					} else {
						$ClickedRate = 100 * ($Click / $Reach);						
					}
					
					break;
				}
				
					
					
					
				
			}
			
			$arr1 = array(					
				'campaignID'=>$campaignID,
				'accountID'=>$ACCOUNTID,
				'campaignName'=>$campaignName,
				'createDate'=>$createDate,
				'lastEditDate'=>$lastEditDate,
				'status'=>$status,
				'campaignType'=>$campaignType,
				'Reach'=>$Reach,
				'Click'=>$Click,
				'ClickedRate'=>$ClickedRate,
				'publishProgramID'=>$ProgramID,
				'ACCOUNTID'=>$ACCOUNTID,
				'campaignList'=>$campaignList,
				
			);	
			
			$logger2->log("arr1 = ". json_encode($arr1) );
			
			$rows[] = $arr1;
		}
		
	} else {
		$DVSTAT = 'from studio';
		
		$cnt = 0;
		
		
		$ord = array();
		//$campaigns = $campaignlist['campaigns'];
		
		// Dave added this to solve the slow Ali query #301.  Here we sort $campaigns array based on lastEditDate (most recent at top)
		foreach ($campaigns as $key => $value){
				$ord[] = strtotime($value['lastEditDate']);
		}
		
		array_multisort($ord, SORT_DESC, $campaigns);
		
		if ($TEST == 't') {
			echo "Test<br>";
		}
		
		if ( $manually == 'y' ) {
			$sessionData = array();
		} else {
			$sessionData = $_SESSION['DVSTAT'];
		}
		
		$logger2->log("sessionData = ". json_encode($sessionData) );
			
		if ( ($mode == 'refresh') || ($mode == 'cron') ) {
			unset($_SESSION['DVSTAT']);
		}
		
		if (($TEST == 't') || ($TEST == 't2')){
			unset($_SESSION['DVSTAT']);
		}
		
		//unset($_SESSION['DVSTAT']); // Do not use session
		if ($_SESSION["DVSTAT"]) {
			if ($TEST == 't') {
				echo "_SESSION<br>";
			}
			$rows = $_SESSION["DVSTAT"];
			$lastDatePullStudioReport = MySQL_GetLastDatePullStudioReport($ACCOUNTID);
			$result = json_encode( array(
				'success'	=> $success,
				'startReport' => $startReport,
				'DVSTAT' => 'from session DVSTAT',
				'finishReport' => $finishReport,
				'lastDatePullStudioReport' => $lastDatePullStudioReport,
				'rows'		=> $rows
			));
			return $result;
		} else {
			if ($TEST == 't') {
				echo "get report<br>";
			}
			preventTimeout();
			//$AllEmailsStat = GetReportAllEmail($TEST, $ACCOUNTID, $fd, $td, $token, "0", $EMAIL, $PWD);
			//$AllEmailsStat = GetReportAllEmailMode($TEST, $ACCOUNTID, $fd, $td, $token, "0", 'preventTimeout', $EMAIL, $PWD);
			preventTimeout();
			$AllPagesStat = GetReportAllPage($TEST, $ACCOUNTID, $fd, $td, $token, "0", $EMAIL, $PWD);
			
		}
		
		
		$BALists = getBATotalEmail($ACCOUNTID);
		$ProgramInfos = getDV_ProgramInfo($ACCOUNTID);
		
		$result = array();
	
		//$token = getToken($ACCOUNTID, $EMAIL, $PWD);
		
		foreach( $campaigns as $campaign ) {	
			
			$mustUpdate = false;
			$mustUpdate1 = 'false';
			$campaignID = $campaign['campaignID'];
			$accountID = $campaign['accountID'];
			$campaignName = $campaign['campaignName'];
			$createDate = $campaign['createDate'];
			$lastEditDate = $campaign['lastEditDate'];
			$status = $campaign['status'];
			$campaignType = $campaign['campaignType'];
			
			foreach( $campaignData as $CampaignMeasure ) {
				if ($campaignID == $CampaignMeasure['campaignID']) {			
					$mustUpdate = true;
					$mustUpdate1 = 'true';
					break;
				}
			}
			
			
			preventTimeout();
			
			$str1 = "8384 Working on Campaign : $campaignName , mustUpdate1 : $mustUpdate1, status : $status";
			$logger2->log($str1);
			
			
			if ($mustUpdate == true) {
				
				$ProgramID = "";
				foreach( $ProgramInfos as $ProgramInfo ) {
					if ($campaignID == $ProgramInfo['progID']) {			
						$ProgramID = $ProgramInfo['publishProgramID'];
						if ($TEST == 't1') {
							echo "campaignID : $campaignID<br>";			
							echo "ProgramID : $ProgramID<br>";
						}
						break;
					}
				}
				
				$Reach = "0";
				$Click = "0";
				$Open = "0";
				$Complete = "0";
				$ClickedRate = "0";
				
				if ($ProgramID != "") {
					$str1 = "8396 campaignType $campaignType";
					$logger2->log($str1);
					if ( ($campaignType == 'PromoteEbook') || ($campaignType == 'EventReminder') || ($campaignType == 'PromoteBlog') || ($campaignType == 'PromoteDT') ){
						if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT') {
							$AllEmailsStat = GetReportAllEmailMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, 'preventTimeout', $EMAIL, $PWD);
							$tempArrays = $AllEmailsStat;
						} else if ($campaignType == 'EventReminder') { //#1185
						$AllEmailsStat = GetReportAllEmailMode($TEST, $ACCOUNTID, $fd, $td, $token, $ProgramID, 'preventTimeout', $EMAIL, $PWD);
							$tempArrays = $AllEmailsStat;
						} else if ($campaignType == 'PromoteEbook') {
							$tempArrays = $AllPagesStat;
						}
						$canFind = false;
						foreach ($tempArrays as $tempArray){
							$tempProgram = $tempArray['Program'];		
							$tempProgramID = $tempArray['ProgramID'];		
							//if ($tempProgram == $campaignName) {
							if ($TEST == 't1') {
								echo "tempProgramID : $tempProgramID<br>";			
								echo "tempProgram : $tempProgram<br>";
							}
							
							if ($tempProgramID == $ProgramID) {
								$Reach = $tempArray['Sent'];
								if ($campaignType == 'PromoteBlog' || $campaignType == 'PromoteDT' || $campaignType == 'EventReminder') {  //#1185 #2176
									$Click = $tempArray['Clicked'];
								} else {
									$Click = $tempArray['Submits'];
								}
								$Open = $tempArray['Opened'];
								if ($Reach) { } else {$Reach = 0; } 
								if ($Click) { } else {$Click = 0; }
								if ($Open) { } else {$Open = 0; }
								
								if ($Reach == '0') {
									$ClickedRate = "0";
								} else {
									$ClickedRate = 100 * ($Click / $Reach);						
								}
								$canFind = true;
								$str1 = "10244 canFind = true";
								$logger2->log($str1);
								break;
							}
						}
						if ($canFind == true) {
							$str1 = "8429 ACCOUNTID $ACCOUNTID , campaignID $campaignID , Reach $Reach , Click $Click , Open $Open , ClickedRate $ClickedRate";
							$logger2->log($str1);
							$param['acctID'] = $ACCOUNTID;
							$param['campaignID'] = $campaignID;
							$param['campaignName'] = $campaignName;
							$param['studioID'] = $ProgramID;
							$param['campaignType'] = $campaignType;
							$param['Reach'] = $Reach;
							$param['Click'] = $Click;
							$param['ClickedRate'] = $ClickedRate;
							saveCampaignMeasures($param);
						} else {
							$str1 = "10263 canFind = false";
							$logger2->log($str1);
						}
					} else if ($campaignType == 'BuildAwareness') {
						
						$ReachSave = 0;
						$ClickSave = 0;
						$ClickedRateSave = 0;
						foreach( $CampaignMeasures as $CampaignMeasure ) {
							if ($campaignID == $CampaignMeasure['campaignID']) {			
								$ReachSave = $CampaignMeasure['Reach'];
								$ClickSave = $CampaignMeasure['Click'];
								$ClickedRateSave = $CampaignMeasure['ClickedRate'];
								
								break;
							}
						}
						
						$mustCallBA = false;
						foreach( $CampaignMeasures15Min as $CampaignMeasure ) {
							if ($campaignID == $CampaignMeasure['campaignID']) {			
								$mustCallBA = true;
								
								break;
							}
						}
						
						if ($mustCallBA == true) {
							$logger2->log("mustCallBA = true");
							$totalEmail = '';
					   	foreach( $BALists as $BAList ) {
					   		$ID = $BAList['ID'];
					   		if ($ID == $campaignID) {
					   			$totalEmail = $BAList['totalEmail'];
					   			break;
					   		}
					   	}
							
							$EMAIL = '';
							$PWD = '';
							
							$tempArrays = GetCampaignReportPageBA($TEST, $ACCOUNTID, $ProgramID, $campaignName, $fd, $td, $token, $totalEmail, $EMAIL, $PWD, $campaignID, $AllEmailsStat);
							
							$tempArray = json_decode($tempArrays,true);
							$Reach = $tempArray['Reach'];
							$Click = $tempArray['Complete'];
							$Complete = $tempArray['Complete'];
						} else {
							$logger2->log("mustCallBA = false");
							$Reach = $ReachSave;
							$Click = $ClickSave;
							$Complete = $ClickSave;
						}
						
						if ($TEST == 't2') {
							echo "Reach : $Reach<br>";
							echo "Click : $Click<br>";
							echo "Complete : $Complete<br>";
						}
						if ($Reach == '0') {
							$ClickedRate = "0";
						} else {
							$ClickedRate = 100 * ($Complete / $Reach);						
						}
					}
				}
			} else {
				$sessionFind = false;
				foreach ($sessionData as $sessionD){
					if ($campaignID == $sessionD['campaignID']) {			
						$campaignID = $sessionD['campaignID'];
						$accountID = $sessionD['accountID'];
						$campaignName = $sessionD['campaignName'];
						$createDate = $sessionD['createDate'];
						$lastEditDate = $sessionD['lastEditDate'];
						$status = $sessionD['status'];
						$campaignType = $sessionD['campaignType'];
						$Reach = $sessionD['Reach'];
						$Click = $sessionD['Click'];
						$ClickedRate = $sessionD['ClickedRate'];
						$ProgramID = $sessionD['publishProgramID'];
						$ACCOUNTID = $sessionD['ACCOUNTID'];
						$campaignList = $sessionD['campaignList'];
						$sessionFind = true;
						break;
					}
				}
				if ($sessionFind == false) {
					$Reach = 0;
					$Click = 0;
					$ClickedRate = 0;
				}
			}
			$arr1 = array(					
				'campaignID'=>$campaignID,
				'accountID'=>$ACCOUNTID,
				'campaignName'=>$campaignName,
				'createDate'=>$createDate,
				'lastEditDate'=>$lastEditDate,
				'status'=>$status,
				'campaignType'=>$campaignType,
				'Reach'=>$Reach,
				'Click'=>$Click,
				'ClickedRate'=>$ClickedRate,
				'publishProgramID'=>$ProgramID,
				'ACCOUNTID'=>$ACCOUNTID,
			);	
			$rows[] = $arr1;
			
		}
	}
	
	if ( $manually == 'y' ) {
	} else {
		session_reopen();
		$_SESSION["DVSTAT"] = $rows;
		session_write_close();
	}
	
	$success = true;
	if (empty($rows)) $rows = array();
	$str1 = "FINISHED -----------------------------------------";
	$logger2->log($str1);
	$finishReport = date('m.d.Y h:i:s');
	$lastDatePullStudioReport = MySQL_GetLastDatePullStudioReport($ACCOUNTID);
	$result = json_encode(array(
		'success'	=> $success,
		'startReport' => $startReport,
		'finishReport' => $finishReport,
		'DVSTAT' => $DVSTAT,
		'lastDatePullStudioReport' => $lastDatePullStudioReport,
		'rows'		=> $rows,
		'sessionData'		=> $sessionData
	));
	return $result;
}

function getBATotalEmail($ACCOUNTID) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Document_BuildAwareness where acctID=?");
		$Stmt->execute(array($ACCOUNTID));
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['data'],true);	
			$ID = $row['ID'];
			$totalEmail = $docData['totalEmail'];
		
			$arr1 = array(					
				'ID'=>$ID,
				'totalEmail'=>$totalEmail
			);	
			$rows[] = $arr1;
		}
	}  
	catch(exception $e) {
		$rows = array();
	}
	return $rows;
}

function session_reopen() {
    ini_set('session.use_only_cookies', false);
    ini_set('session.use_cookies', false);
    //ini_set('session.use_trans_sid', false); //May be necessary in some situations
    ini_set('session.cache_limiter', null);
    session_start(); //Reopen the (previously closed) session for writing.
}

function getStudioList($param) {
	//$logger = new FileLogger("test003.log"); 
	$TEST = $param['TEST'];
	$ACCOUNTID = $param['ACCOUNTID'];
	$EMAIL = $param['EMAIL'];
	$PWD = $param['PWD'];
	$PARTNERGUID = $param['PARTNERGUID'];
	$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
	$SearchString = $param['SearchString'];
	
	
	$logger = new FileLogger("updateAccount.log");
	$logger->log("getStudioList ACCOUNTID : $ACCOUNTID , EMAIL : $EMAIL , PWD : $PWD , PARTNERGUID : $PARTNERGUID , PARTNERPASSWORD : $PARTNERPASSWORD");
	
	if ($_SESSION['STUDIOLOGIN'] == 'Y') {
		$powerTicket = $_SESSION['STUDIOTICKET'];
	} else {
		if ($_SESSION['STUDIOTICKET']) {
			$powerTicket = $_SESSION['STUDIOTICKET'];
			$logger->log("getStudioList SESSION powerTicket : $powerTicket");
		} else {
			$powerTicket = getTicketCurl($ACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
			$logger->log("getStudioList getTicketCurl powerTicket : $powerTicket");
		}
		if ($TEST == 't') {
			echo "<br>$powerTicket, $ACCOUNTID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD, $SearchString<br>";
		}
	}
	$resultCount = 0;
	$StartRowIndex = 0;
	$loopCnt = 0;
	$allListName = []; 
	$allList = [];
	
	do {
		$ImportRequest   = array
		(
			"MaxRows"=>"1000",
			"SearchString"=>$SearchString,
			"SortExpression"=>"SubmitDate",
			"Status"=>"255",
			"StartRowIndex"=>$StartRowIndex,
			"Credentials"=> array
			(
				"Ticket" => $powerTicket    // get ticket from user account
			)
		);
		$ImportResponse = callService("contactservice/GetImportList", $ImportRequest);	
		$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
		
		if ($ErrorCode == "") {	
			$ImportLists = $ImportResponse->{"ImportList"};	
			foreach( $ImportLists as $ImportList ) {		
			
				$Name = $ImportList->{'Name'};
				$count = $ImportList->{'SuccessfulRecords'};
				$reject = $ImportList->{'RejectedRecords'};
				$Status = $ImportList->{'Status'};										
				$StatusName = $ImportList->{'StatusName'};					
				$StatusLower = strtolower($StatusName);
				
				$ID = $ImportList->{'ID'};
				$ExportColumns = $ImportList->{'ExportColumns'};
				$FtpPassword = $ImportList->{'FtpPassword'};
				$FtpUrl = $ImportList->{'FtpUrl'};
				$FtpUsername = $ImportList->{'FtpUsername'};
				$FileName = $ImportList->{'FileName'};
				$Filterable = $ImportList->{'Filterable'};
				$Mapping = $ImportList->{'Mapping'};
				$Mode = $ImportList->{'Mode'};
				$NotificationEmail = $ImportList->{'NotificationEmail'};
				$CsvRecords = $ImportList->{'CsvRecords'};
				$LastUpdate = $ImportList->{'LastUpdate'};
				$ProcessEndDate = $ImportList->{'ProcessEndDate'};
				$ProcessStartDate = $ImportList->{'ProcessStartDate'};
				$ProcessedRecords = $ImportList->{'ProcessedRecords'};
				$RejectedRecords = $ImportList->{'RejectedRecords'};
				$SubmitDate = $ImportList->{'SubmitDate'};
				$SuccessfulRecords = $ImportList->{'SuccessfulRecords'};
				$ModeName = $ImportList->{'ModeName'};
				
				$listItem['Name'] = $Name;
				$listItem['count'] = $count;
				$listItem['reject'] = $reject;
				$listItem['Status'] = $Status;
				$listItem['StatusName'] = $StatusName;
				$listItem['StatusLower'] = $StatusLower;
				
				$listItem['ID'] = $ID;
				$listItem['ExportColumns'] = $ExportColumns;
				$listItem['FtpPassword'] = $FtpPassword;
				$listItem['FtpUrl'] = $FtpUrl;
				$listItem['FtpUsername'] = $FtpUsername;
				$listItem['FileName'] = $FileName;
				$listItem['Filterable'] = $Filterable;
				$listItem['Mapping'] = $Mapping;
				$listItem['Mode'] = $Mode;
				$listItem['NotificationEmail'] = $NotificationEmail;
				$listItem['CsvRecords'] = $CsvRecords;
				$listItem['LastUpdate'] = $LastUpdate;
				$listItem['ProcessEndDate'] = $ProcessEndDate;
				$listItem['ProcessStartDate'] = $ProcessStartDate;
				$listItem['ProcessedRecords'] = $ProcessedRecords;
				$listItem['RejectedRecords'] = $RejectedRecords;
				$listItem['SubmitDate'] = $SubmitDate;
				$listItem['SuccessfulRecords'] = $SuccessfulRecords;
				$listItem['ModeName'] = $ModeName;
				
				$allListName[] = $Name; 
				$allList[] = $listItem;
			}
			
			if ($ImportLists) {
				$resultCount = sizeof($ImportLists);
			} else {
				$resultCount = 0;
			}
			//$logger->log("426 resultCount : $resultCount");
		
			if ($resultCount < 1000) {
				$resultCount = 0;
			} else {
				$loopCnt++;
				$StartRowIndex = (1000 * $loopCnt);
			}
			//$logger->log("434 StartRowIndex : $StartRowIndex");
			$errorMessage = '';
		} else {
			$resultCount = 0;
			$errorMessage = "GetImportList ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
		} 
		
	} while($resultCount != 0);
	
	
	
	
	$result['allListName'] = $allListName;
	$result['allList'] = $allList;
	$result['errorCode'] = $ErrorCode;
	$result['errorMessage'] = $errorMessage;
	return $result;
}

function getStudioListCredentials($ACCOUNTID,$credentials,$SearchString) {	  	
	$logger = new FileLogger("getStudioListCredentials.log");
	$logger->log("START === $ACCOUNTID === credentials : " .  json_encode($credentials) );	
	$logger->log("SearchString : $SearchString");	

	$resultCount = 0;
	$StartRowIndex = 0;
	$loopCnt = 0;
	$allListName = []; 
	$allList = [];
	
	do {
		$ImportRequest   = array(
			"MaxRows"=>"1000",
			"SearchString"=>$SearchString,
			"SortExpression"=>"SubmitDate",
			"Status"=>"255",
			"StartRowIndex"=>$StartRowIndex,
			"Credentials" => $credentials,
		);
		$ImportResponse = callServiceCredential("contactservice/GetImportList", $ImportRequest);	
		$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
		$logger->log("ErrorCode : $ErrorCode");	

		if ($ErrorCode == "") {	
			$ImportLists = $ImportResponse->{"ImportList"};	

			foreach( $ImportLists as $ImportList ) {					
				$Name = $ImportList->{'Name'};
				$count = $ImportList->{'SuccessfulRecords'};
				$reject = $ImportList->{'RejectedRecords'};
				$Status = $ImportList->{'Status'};										
				$StatusName = $ImportList->{'StatusName'};					
				$StatusLower = strtolower($StatusName);
				
				$ID = $ImportList->{'ID'};
				$ExportColumns = $ImportList->{'ExportColumns'};
				$FtpPassword = $ImportList->{'FtpPassword'};
				$FtpUrl = $ImportList->{'FtpUrl'};
				$FtpUsername = $ImportList->{'FtpUsername'};
				$FileName = $ImportList->{'FileName'};
				$Filterable = $ImportList->{'Filterable'};
				$Mapping = $ImportList->{'Mapping'};
				$Mode = $ImportList->{'Mode'};
				$NotificationEmail = $ImportList->{'NotificationEmail'};
				$CsvRecords = $ImportList->{'CsvRecords'};
				$LastUpdate = $ImportList->{'LastUpdate'};
				$ProcessEndDate = $ImportList->{'ProcessEndDate'};
				$ProcessStartDate = $ImportList->{'ProcessStartDate'};
				$ProcessedRecords = $ImportList->{'ProcessedRecords'};
				$RejectedRecords = $ImportList->{'RejectedRecords'};
				$SubmitDate = $ImportList->{'SubmitDate'};
				$SuccessfulRecords = $ImportList->{'SuccessfulRecords'};
				$ModeName = $ImportList->{'ModeName'};
				
				$listItem['Name'] = $Name;
				$listItem['count'] = $count;
				$listItem['reject'] = $reject;
				$listItem['Status'] = $Status;
				$listItem['StatusName'] = $StatusName;
				$listItem['StatusLower'] = $StatusLower;
				
				$listItem['ID'] = $ID;
				$listItem['ExportColumns'] = $ExportColumns;
				$listItem['FtpPassword'] = $FtpPassword;
				$listItem['FtpUrl'] = $FtpUrl;
				$listItem['FtpUsername'] = $FtpUsername;
				$listItem['FileName'] = $FileName;
				$listItem['Filterable'] = $Filterable;
				$listItem['Mapping'] = $Mapping;
				$listItem['Mode'] = $Mode;
				$listItem['NotificationEmail'] = $NotificationEmail;
				$listItem['CsvRecords'] = $CsvRecords;
				$listItem['LastUpdate'] = $LastUpdate;
				$listItem['ProcessEndDate'] = $ProcessEndDate;
				$listItem['ProcessStartDate'] = $ProcessStartDate;
				$listItem['ProcessedRecords'] = $ProcessedRecords;
				$listItem['RejectedRecords'] = $RejectedRecords;
				$listItem['SubmitDate'] = $SubmitDate;
				$listItem['SuccessfulRecords'] = $SuccessfulRecords;
				$listItem['ModeName'] = $ModeName;

				$logger->log("ImportList :  listItem = " . json_encode($listItem));	
				
				$allListName[] = $Name; 
				$allList[] = $listItem;
			}
			
			if ($ImportLists) {
				$resultCount = sizeof($ImportLists);
			} else {
				$resultCount = 0;
			}		
		
			if ($resultCount < 1000) {
				$resultCount = 0;
			} else {
				$loopCnt++;
				$StartRowIndex = (1000 * $loopCnt);
			}
			$errorMessage = '';
		} else {
			$resultCount = 0;
			$errorMessage = "GetImportList ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
			$logger->log("errorMessage : $errorMessage");	
		} 		
	}while($resultCount != 0);
	
	$result['allListName'] = $allListName;
	$result['allList'] = $allList;
	$result['errorCode'] = $ErrorCode;
	$result['errorMessage'] = $errorMessage;
	return $result;
}

function preventTimeout() {
	//prevent browser timeout
	echo " ";
	flush();
}


function getCampaignMeasuresList15Min($ACCOUNTID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$sqltxt = "select * from DV_CampaignMeasures where acctID = ? and lastUpdateDate <= NOW() - INTERVAL 15 MINUTE";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}

	return $row2;
}

function getCampaignMeasuresUpdate($ACCOUNTID) {
	
	$row2 = array();
	try {
		if($db == null){
			$db = GetPDOMySQL();
		}
		
		$sql = "UPDATE DV_CampaignMeasures a INNER JOIN DV_CampaignList c ON a.acctID=c.acctID AND a.campaignID=c.campaignID SET a.studioID = c.publishProgramID WHERE a.studioID IS NULL AND c.publishProgramID IS NOT NULL AND a.acctID=?";
		
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($ACCOUNTID));
		
		$sqltxt = "SELECT * FROM DV_CampaignMeasures c WHERE c.lastUpdateDate < (SELECT max(d.timestamp) FROM DV_StudioActivity d WHERE d.acctID=c.acctID AND d.programID=c.studioID) AND acctid=?";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($ACCOUNTID));
		$affected_rows = $Stmt->rowCount();
		
		while ($row = $Stmt->fetch()) {
			$row2[] = $row;
		}
	} catch(Exception $e){
		$ErrorMessage = $e->getMessage();
		$success = false;
	}

	return $row2;
}

function getCampaignMeasuresDateRange($ACCOUNTID, $fd, $td) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$logger = new FileLogger("getCampaignMeasuresDateRange.log");
	
	$datetime = new DateTime($td);		
	$CountDate1 = $datetime->format('Y-m-d');
	$datetime = new DateTime($fd);		
	$CountDate2 = $datetime->format('Y-m-d');
	
	$usersTimezone = getUserTimeZone($ACCOUNTID);
	
	$CountDate2 = "$CountDate2 00:00:00";
	$CountDate1 = "$CountDate1 23:59:59";
	$CountDate2 = convert_to_server_date($CountDate2, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	$CountDate1 = convert_to_server_date($CountDate1, $usersTimezone, 'UTC', 'Y-m-d H:i:s');
	
	$logger->log("fd : $fd, td : $td");
	$logger->log("CountDate2 : $CountDate2, CountDate1 : $CountDate1");
	
	$sqltxt = "SELECT * FROM DV_CampaignMeasures WHERE acctID=? and lastUpdateDate >= ? and lastUpdateDate <= ?  ORDER BY id";
	
	$logger->log("sqltxt : $sqltxt");
	$logger->log("ACCOUNTID : $ACCOUNTID, CountDate2 : $CountDate2 , CountDate1 : $CountDate1");
	
	$Stmt = MySQL_Prepare($db,$sqltxt);
	
	$Stmt->execute(array($ACCOUNTID, $CountDate2, $CountDate1));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}

	return $row2;
}

function getSignupInfo($acctID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$sqltxt = "select * from DV_signup where studioAccountID = ? and amount=0";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}

	return $row2;
}


function mobileFormat($data) {
	$result = $data;
	$data = '+1'.$data;
	if(  preg_match( '/^\+\d(\d{3})(\d{3})(\d{4})$/', $data,  $matches ) ) {
   	$result = $matches[1] . '-' .$matches[2] . '-' . $matches[3];
	}
	return $result;
}

function updateSubAccountInfo($parentID, $studioAccountID) {
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$stripeToken = '';
		$sqltxt = "select * from DV_UserInfo where acctID = ? limit 1";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($parentID));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		while ($row = $Stmt->fetch()) {
			$paidAccount = $row['paidAccount'];
			$recurringDate = $row['recurringDate'];
			$recurringAmount = $row['recurringAmount'];
			$recurringPeriod = $row['recurringPeriod'];
			$recurringProduct = $row['recurringProduct'];
			$lastBillingDate = $row['lastBillingDate'];
			$delinquentDate = $row['delinquentDate'];
			$expireDate = $row['expireDate'];
			$accountCloseOverride = $row['accountCloseOverride'];
			$emailLimit = $row['emailLimit'];
			$emailOverage = $row['emailOverage'];
			$contactLimit = $row['contactLimit'];
			$contactOverage = $row['contactOverage'];
			$smsLimit = $row['smsLimit'];
			$smsOverage = $row['smsOverage'];
			$enrichmentLimit = $row['enrichmentLimit'];
			$enrichmentOverage = $row['enrichmentOverage'];
			$contentSearchLimit = $row['contentSearchLimit'];
			$contentSearchOverage = $row['contentSearchOverage'];
			$contentLaunchLimit = $row['contentLaunchLimit'];
			$contentLaunchOverage = $row['contentLaunchOverage'];
			$UiAccess = $row['UiAccess'];
			$StripeCustomer = $row['StripeCustomer'];
			
			
			$docData = json_decode($row['data'],true);			
			$stripeToken = $docData['stripeToken'];
		}
		
		$sqltxt = "select * from DV_UserInfo where acctID = ? limit 1";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($studioAccountID));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		while ($row = $Stmt->fetch()) {
			$docData1 = json_decode($row['data'],true);	
			$docData1['stripeToken'] = $stripeToken;
		}
		
		$data = json_encode($docData1);
		
		
		if ($studioAccountID > 0) {
			$sqltxt = "update DV_UserInfo set paidAccount=?,recurringDate=?,recurringAmount=?,recurringPeriod=?,recurringProduct=?,lastBillingDate=?,delinquentDate=?,expireDate=?,accountCloseOverride=?,emailLimit=?,emailOverage=?,contactLimit=?,contactOverage=?,smsLimit=?,smsOverage=?,enrichmentLimit=?,enrichmentOverage=?,contentSearchLimit=?,contentSearchOverage=?,contentLaunchLimit=?,contentLaunchOverage=?,StripeCustomer=?,data=? where acctID = ?";
			$Stmt = MySQL_Prepare($db,$sqltxt);		
			$Stmt->execute(array($paidAccount,$recurringDate,$recurringAmount,$recurringPeriod,$recurringProduct,$lastBillingDate,$delinquentDate,$expireDate,$accountCloseOverride,$emailLimit,$emailOverage,$contactLimit,$contactOverage,$smsLimit,$smsOverage,$enrichmentLimit,$enrichmentOverage,$contentSearchLimit,$contentSearchOverage,$contentLaunchLimit,$contentLaunchOverage,$StripeCustomer,$data,$studioAccountID));
		}
		$success = true;
		$ErrorMessage = '';
	} catch(Exception $e){
		$success = false;
		$ErrorMessage = $e->getMessage();
	}	
	
	return array(
        'success'=>$success,
        'ErrorMessage'=>$ErrorMessage,
    );
}


function getServerFoodOrder($reportMode, $usersTimezone) {
	$logger = new FileLogger("optiChannelUtils.log");
	$startDate = date("Y-m-d H:i:s");
	$calcDate = convert_to_server_date($startDate, 'UTC' , $usersTimezone, 'Y-m-d H:i:s');
	
	$calcDate = substr($calcDate, 0, 10);
	
	$fieldname = 'createDate';
	$countDate = getOrderCountDate($reportMode);
	
	$logger->log("getServerFoodOrder reportMode : $reportMode , startDate : $startDate , calcDate : $calcDate , usersTimezone : $usersTimezone , countDate : $countDate");
	
	$td = $calcDate;
	$datetime = new DateTime();		
	if ($countDate != -1) {
		$datetime->modify("-".$countDate." day");
	} else {
		$datetime->modify("-1 day");
	}
	$CountDate2 = $datetime->format('Y-m-d H:i:s');
	$calcDate = convert_to_server_date($CountDate2, 'UTC' , $usersTimezone, 'Y-m-d H:i:s');
	$calcDate = substr($calcDate, 0, 10);
	$fd = $calcDate;
	
	$logger->log("getServerFoodOrder reportMode : $reportMode , fd : $fd , td : $td ");
	if ($reportMode == 'Today') {
		$fd = $td;
	} else if ($reportMode == 'Yesterday')  {
		$td = $fd;
	} else {
		
	}
	
	$logger->log("fd : $fd , td : $td ");
	return array(
        'fd'=>$fd,
        'td'=>$td,
    );
}

function getOrderCountDate($mode){
	$CountDate = 30;
	if (($mode == '') || ($mode == 'Today') ) {
		$CountDate = 0;
	} else if ($mode == 'Lifetime') {
		$CountDate = 10000;
	} else {
		if ($mode == 'Yesterday') {
			$CountDate = -1;
		} else if ($mode == '7Days') {
			$CountDate = 7;
		} else if ($mode == '14Days') {
			$CountDate = 14;
		} else if ($mode == '30Days') {
			$CountDate = 30;
		} else if ($mode == '90Days') {
			$CountDate = 90;
		}
	}
	return $CountDate;
}


function reconFields($TEST, $scr, $dest) {
	$logger3 = new FileLogger("login1.log"); 
	$logger2 = new FileLogger("reconFields1.log"); 
	$logger2->log("START");
	$logger3->log("reconFields");
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$accountName = '';
	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? order by acctID desc");
	$Stmt->execute(array($dest));
	
	while ($row = $Stmt->fetch()) {
	    $accountName = $row['studioAccountName'];
	}
	
	
	$str1 = "scr = $scr, dest = $dest , accountName = $accountName";
	if ($TEST == 't') {
		echo "$str1<br>";
	}
	$logger2->log($str1);
	
	//copy DV_Document_Default_FormLibrary 
	$defFormDocRET = reconDocDefFormLib($scr,$dest); 
	$logger2->log("ADD DV_Document_Default_FormLibrary = ". $defFormDocRET["success"]." , errormessage = ". $defFormDocRET['ErrorMessage'] );
	
	// copy DV_FormFieldType
	$scrList = MySQL_GetAllByKey("DV_FormFieldType",["accountID"=>$scr]);
	$destList = MySQL_GetAllByKey("DV_FormFieldType",["accountID"=>$dest]);
	
	$logger2->log("scrList = ". json_encode($scrList) );
	$logger2->log("destList = ". json_encode($destList) );
	
	if ($TEST == 't') {
		//echo "scrList<br>";
		//var_dump($scrList);
		//echo "<br>";
	
		//echo "destList<br>";
		//var_dump($destList);
		//echo "<br>";
	}
	
	$copyList = array();
	
	foreach ($scrList as $scrItem){
		$scrID = strtolower($scrItem['fieldID']);
		$canFind = false;
		foreach ($destList as $destItem){
			$destID = strtolower($destItem['fieldID']);
			if ($scrID == $destID) {
				$canFind = true;
				break;
			}
		}
		if ($canFind == true) {
		} else {
			$copyList[] = $scrItem;
		}
	}
	
	$logger2->log("DV : copyList = ". json_encode($copyList) );
	
	if ($TEST == 't') {
		echo "DV : copyList<br>";
		var_dump($copyList);
		echo "<br>";
	}
	
	$errorMessage = "";
	
	foreach ($copyList as $copyItem){
		
		$id = $copyItem['ID'];
		
		$sql = "INSERT INTO DV_FormFieldType (ID, accountID, fieldID, fieldType, fieldOption) SELECT NULL,$dest,fieldID,fieldType,fieldOption  FROM DV_FormFieldType WHERE  ID = $id ";
		try {
			$Stmt1 = MySQL_Prepare($db,$sql);
			
			$str1 = "<br>sql = $sql";
			if ($TEST == 't') {
				echo "$str1<br>";
			}
			$logger2->log($str1);	
			
			$message = "DV_FormFieldType : Start copy $id to $dest";
			
			$str1 = "message = $message";
			if ($TEST == 't') {
				echo "$str1<br>";
			}
			$logger2->log($str1);
			
			
			$Stmt1->execute();
			$insertID = $db->lastInsertId();
			
			$str1 = "insertID = $insertID";
			if ($TEST == 't') {
				echo "$str1<br>";
			}
			$logger2->log($str1);
			
			try {
				
				$sql = "INSERT INTO DV_OptionOrder (ID,formFieldID,options,val, orders) SELECT NULL,$insertID,options,val, orders  FROM DV_OptionOrder WHERE  formFieldID = $id ";
				$Stmt2 = MySQL_Prepare($db,$sql);
			
				$str1 = "<br>sql = $sql";
				if ($TEST == 't') {
					echo "$str1<br>";
				}
				$logger2->log($str1);
				
				$message = "DV_OptionOrder : Start copy $id to $dest";
			
				$str1 = "message = $message";
				if ($TEST == 't') {
					echo "$str1<br>";
				}
				$logger2->log($str1);
				
				$Stmt2->execute();
			
			} catch(Exception $e){
				
				$success = false;	
				$errorMessage = "DV_OptionOrder : Cannot copy $id to $dest";
			
				$str1 = "errorMessage = $errorMessage";
				if ($TEST == 't') {
					echo "$str1<br>";
				}
				$logger2->log($str1);
				
			}
		} catch(Exception $e){
			$success = false;	
			$errorMessage = "DV_FormFieldType : Cannot copy $id to $dest";
			
			$str1 = "errorMessage = $errorMessage";
			if ($TEST == 't') {	
				echo "$str1<br>";
			}
			$logger2->log($str1);
		}
	}
	
	$studioParam = GetStudioAPIParam();
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	
	
	$fieldType = 0;
		sleep(2); //2262
	$scrTicket = getTicketCurl($scr, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	$scrList = GetContactFieldListAPI($TEST, $scrTicket , $fieldType);
	
	$logger3->log("scrList = ". json_encode($scrList) );
	
	$str1 = "scrTicket = $scrTicket";
	if ($TEST == 't') {
		echo "$str1<br>";
	}
	$logger2->log($str1);
	
	$logger2->log("scrList = ". json_encode($scrList) );
	if ($TEST == 't') {
		//echo "scrList<br>";
		//var_dump($scrList);
		//echo "<br>";
	}
	
	$studioParam = GetStudioAPIParam();
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
		sleep(2); //2262
	$destTicket = getTicketCurl($dest, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	$destList = GetContactFieldListAPI($TEST, $destTicket, $fieldType);
	
	$logger3->log("destList = ". json_encode($destList) );
	
	$str1 = "destTicket = $destTicket";
	if ($TEST == 't') {
		echo "$str1<br>";
	}
	$logger2->log($str1);
	
	$logger2->log("destList = ". json_encode($destList) );
	if ($TEST == 't') {
		//echo "destList<br>";
		//var_dump($destList);
		//echo "<br>";
	}
	
	$copyList = array();
	
	foreach ($scrList as $scrItem){
		
		$scrName = strtolower($scrItem['Name']);
		$canFind = false;
		foreach ($destList as $destItem){
			$destName = strtolower($destItem['Name']);
			if ($scrName == $destName) {
				$canFind = true;
				break;
			}
		}
		if ($canFind == true) {
		} else {
			$copyList[] = $scrItem;
		}
	}
	
	$logger2->log("Studio : copyList = ". json_encode($copyList) );
	if ($TEST == 't') {
		echo "Studio : copyList<br>";
		var_dump($copyList);
		echo "<br>";
	}
	
	foreach ($copyList as $copyItem){
		
		$fieldName = $copyItem['Name'];
		$fieldType = $copyItem['DataType_ID'];
		
		addCustomField($destTicket, $dest, $accountName, $fieldName,	$fieldType);
			sleep(2); //2262
		$str1 = "addCustomField : $destTicket, $dest, $accountName, $fieldName,	$fieldType";
		$logger2->log($str1);
		if ($TEST == 't') {
			echo "$str1<br>";
		}
		
	}
	
	$logger2->log("END");
	if ($TEST == 't') {
		echo "END<br>";
	}
	
	$success = true;
	$ErrorMessage = '';
	return array(
        'success'=>$success,
        'ErrorMessage'=>$ErrorMessage,
    );
	
}

function reconDocDefFormLib($src,$dest){
	// global $logger; 
	$logger = new FileLogger("reconFields2.log");
	$srcData = MySQL_GetDoc("DV_Document_Default_FormLibrary",$src,"contactForm");                
	$destData = MySQL_GetDoc("DV_Document_Default_FormLibrary",$dest,"contactForm");                
	
	$logger->log("src = $src , dest = $dest"); 
	
	if($srcData){
		if($destData){
			$srcItem = $srcData->{'items'};
			$destItem = $destData->{'items'};
			$logger->log("iiiiii 000 = ". json_encode($srcItem[0])); 
			$logger->log("dddd 000 = ". json_encode($destItem[0])); 
			$srcFieldLists = $srcItem[0]->{'fieldLists'}; 
			$destFieldLists = $destItem[0]->{'fieldLists'}; 
			$logger->log("srcFieldLists = ". json_encode($srcFieldLists)); 
			$logger->log("destFieldLists = ". json_encode($destFieldLists));
			
			$resultFieldLists = $destFieldLists;
			//Diffe SRC AND DEST fieldlist 
			foreach( $srcFieldLists as $srclist ) {
				$notfound = true;  
				$srcName = $srclist->{'fieldID'}; //**** stdclass
				foreach( $destFieldLists as $destlist ) {		
					$destName = $destlist->{'fieldID'};
					if($srcName == $destName){
						$notfound = false; 
						break;
					}
				}
				if($notfound){
					$resultFieldLists[] = $srclist; 
					$logger->log("***** ADD NEW   = ".json_encode($srclist)); 
				}
			}
			
			$logger->log("RRRR resultFieldLists = ". json_encode($resultFieldLists)); 
			if ($destItem) {
				$destItem[0]->{'fieldLists'} = $resultFieldLists; 
				$destData->{'items'} = $destItem; 
				$ret = MySQL_SaveDoc("DV_Document_Default_FormLibrary",$dest,"contactForm",$destData);
				$logger->log("====END[reconDocDefFormLib]=====Save NEW DOC DV_Document_Default_FormLibrary($dest) = ".$ret["success"] ); 
			} else {
				$logger->log("DEST DV_Document_Default_FormLibrary is NULL"); 
				$ret = MySQL_SaveDoc("DV_Document_Default_FormLibrary",$dest,"contactForm",$srcData);
				$logger->log("====END[reconDocDefFormLib]=====(COPY SRC to TARGET) DV_Document_Default_FormLibrary($dest) = ".$ret["success"] ); 	
			}
		}else{
			$logger->log("DEST DV_Document_Default_FormLibrary is NULL"); 
			$ret = MySQL_SaveDoc("DV_Document_Default_FormLibrary",$dest,"contactForm",$srcData);
			$logger->log("====END[reconDocDefFormLib]=====(COPY SRC to TARGET) DV_Document_Default_FormLibrary($dest) = ".$ret["success"] ); 
		}
	}else{
			$logger->log("====END[reconDocDefFormLib]=====SRC DV_Document_Default_FormLibrary is NULL"); 
			$ErrorMessage = "No data of src (DV_Document_Default_FormLibrary)-$src"; 
	}
	return array(
        'success'=>$ret["success"],
        'ErrorMessage'=>$ErrorMessage,
    );
}

function checkDV_FormName($acctID, $fID, $fName) {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$fID1 = strtolower($fID);
		$fName1 = strtolower($fName);
		
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Document_FormLibrary where acctID=?");		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();	
		$duplicate = false;
		while ($row = $Stmt->fetch()) {
			$targetFormID = $row['ID']; 
			$fID2 = strtolower($targetFormID);
			$docData = json_decode($row['data'],true);			
			$fName2 = strtolower($docData['formName']);
			if ($fName1 == $fName2) {
				if ($fID1 != $fID2) {
					$duplicate = true;
					break;
				}
			}
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	$result['duplicate'] = $duplicate;
	$result['targetFormID'] = $targetFormID;
	return $result;
}

function allprogram_allmspagesl_summary($TEST, $token, $PROGRAMID, $ACCOUNTID, $fd, $td, $moreInfo) {
	$reportURL = "https://studio.dashboard.mdl.io/api/Report//GetReportData?rn=allprogram_allmspagesl_summary&si=$PROGRAMID&ai=$ACCOUNTID&st=0&fd=$fd&td=$td&seed=false&datasourceType=redshift";

	if ($TEST == 't') {
		echo "reportURL : $reportURL<br>";
	}
	
	$rows = runReportMoreInfo($TEST, $reportURL, $token, $moreInfo);
	if ($TEST == 't') {
		echo "1 rows<br>";
		var_dump($rows);
		echo "<br>";
	}
	$jsonRow = json_decode($rows);
		//var_dump($jsonRow);
		//echo "<br>";
	$rows = array();
	
	$download = '0';
	$welcome = '0';
	$Reach = '0';
	$conversion = '0';
	$reportURL = '';
	$Submits = 0;
	$Exit = '0';
	
	// Direct Mail Succeeded event id is 20508
	foreach( $jsonRow as $row1 ) {	
		//var_dump($row1);
		//echo "<br>";
		$Page = $row1->{'Page'};
		$Visitors = $row1->{'Visitors'};
		$Submits = $row1->{'Submits'};
		$Clicks = $row1->{'Clicks'};
		$Responses = $row1->{'Responses'};		
		$PageUpper = strtoupper($Page);
		if ($PageUpper == 'WELCOME.HTML') {
			$orderid = '1';
			$welcome = $Visitors;
			$submit = $Submits;
		} else if ($PageUpper == 'DOWNLOAD.HTML') {
			$orderid = '2';
			$download = $Clicks;
		} else if ($PageUpper == 'REMOVE.HTML') {
			$orderid = '3';
			$Exit = $Visitors;
		} else {
			$orderid = '4';
		}
		

		//Reach should be visitors and % conversion should be Clicks on download.html divided by visits to Welcome.html

		$group = array(					
			'orderid'=>$orderid,
			'Page'=>$Page,
			'welcome'=>$welcome,
			'Visitors'=>$Visitors,
			'Submits'=>$Submits,
			'Clicks'=>$Clicks,
			'Exit'=>$Exit,
			'Complete'=>$Complete,
			'Responses'=>$Responses
		);					
		$rows[] = $group;
		
	}
	if ($TEST == 't') {
		echo "2 rows<br>";
		var_dump($rows);
		echo "<br>";
	}
	$result ['rows'] = $rows;
	$result ['welcome'] = $welcome;
	$result ['download'] = $download;
	$result ['submit'] = $submit;
	$result ['Exit'] = $Exit;
	return $result;
}


function getStudioAccountList() {
	$studioParam = GetStudioAPIParam();
	$POWERACCOUNTID = $studioParam['accountID'];
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	
	$studioParam = GetStudioAPIParam();
   $PARTNERGUID = $studioParam['guid'];
   $PARTNERPASSWORD = $studioParam['password'];
   	
	$SelectedAccountID = null;
	$authRequest = array
	(
		"SelectedAccountID" => $SelectedAccountID,
		"Email" => $EMAIL, 
		"Password" => $PWD, 
		"PartnerGuid" => $PARTNERGUID, 
		"PartnerPassword" => $PARTNERPASSWORD
	);
	
	$authResponse = callService("userservice/Authenticate", $authRequest);
	$accountList = array();
	//$userTicket = $authResponse->{"Credentials"}->{"Ticket"};
	//echo "userTicket=$userTicket<br>";
	$ErrorCode = $authResponse->{"Result"}->{"ErrorCode"};
	
	if ($_SESSION['STUDIOLOGIN'] == "Y") {
		$logger = new FileLogger("portalLogin.log");
		$logger->log("2 getStudioAccountList authRequest = ". json_encode($authRequest) );
		$logger->log("2 getStudioAccountList authResponse = ". json_encode($authResponse) );
	}
	
	if ($ErrorCode == "") {
		
		$AvailableAccountList = $authResponse->{"AvailableAccountList"};
		$AccountNumber = Sizeof($AvailableAccountList);
		$isFound = true;
	
		//$AccountNames = getxmediaAPIaccount();
		
		$AccountNames = getxmediaAPIaccountCouchDB();
		$AccountIDs = getxmediaAPIaccountIDCouchDB();
		
		$loadmore = "Yes";
			
		foreach ($AvailableAccountList as $x){
			foreach ($AccountIDs as $y){
				$y = strtolower($y);
				$z = strtolower($x->{'Key'});
				if ($y == $z) {
					$code = $x->{'Key'};
					$AccountName = $x->{'Value'};
					$group = array(					
						'code'=>$code,
						'AccountName'=>$AccountName
					);
					$accountList[] = $group;
				}
			}
			
		}	
	} else {
		//$errorMessage = $authResponse->{"Result"}->{"ErrorMessage"};
		$errorMessage = "That username or password is invalid.";
	}
	return $accountList;

}

function checkPortalToken($token, $aid, $mode) {
    $logger = new FileLogger("loginPortalToken.log");    
    $logger->log("loginToken token : $token , aid : $aid , mode : $mode");
    
    if ($mode == 'parent') {
    	$studioParam = GetStudioAPIParam();
        $PartnerGuid = $studioParam['guid'];
        $PartnerPassword = $studioParam['password'];
    } else {
    	$FieldNames = getxmediaAPIAcctID($aid);
    
		$PartnerGuid = $FieldNames[0];
		$PartnerPassword = $FieldNames[1]; 
    }
	
	$logger->log("loginToken PartnerGuid : $PartnerGuid , PartnerPassword : $PartnerPassword");
    
    
    $authRequest = array
	(
		"Email" => "",
    	"IdentityToken" => $token,
    	"IsEncrypted" => false,
    	"PartnerGuid" => $PartnerGuid,
    	"PartnerPassword" => $PartnerPassword,
    	"SelectedAccountID" => $aid,
    	"Password" => "",
    	"UserToken" => ""
	);
    
    
   $logger->log("checkPortalToken authRequest : ".json_encode($authRequest));
    
	$authResponse = callService("userservice/Authenticate", $authRequest);
	
	$logger->log("checkPortalToken authResponse : ".json_encode($authResponse));
	
	if ($ErrorCode == "") {	
		
		$Credentials = $authResponse->{"Credentials"};
		$userTicket = $Credentials->{"Ticket"};
		
		saveDVPartnerCredentials($userTicket, $Credentials);
	}
	
	
    
   return $authResponse;
    
}



function GetCampaignListPortalNoTicket($acctID) {
	$studioParam = GetStudioAPIParam();
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	$PARTNERGUID = $studioParam['guid'];
	$PARTNERPASSWORD = $studioParam['password'];
	
	$powerTicket = getTicketCurl($acctID, $EMAIL, $PWD, $PARTNERGUID, $PARTNERPASSWORD);
	return GetCampaignListPortal($acctID, $powerTicket);
}


function GetCampaignListPortal($acctID, $powerTicket)
{
   if($db == null){
        $db = GetPDOMySQL();
   }
    
    
   $logger = new FileLogger("getStudioCampaign.log"); 
	$logger->log("start GetCampaignListPortal  ---------");	
    
	$record = array();
	
	$getProgramList = array(
	   	"SearchString" => "",
	   	"State" => 255,
	   	"MaxRows" => 10000,
	   	"SortExpression" => "",
	   	"StartRowIndex" =>0,
	 		"Credentials"			=>  array
	      (
	          "Ticket" => $powerTicket        
	      ),
	);
	 		
	$getProgramListResponse = callService("programservice/GetProgramList", $getProgramList);   
	 
	 
	$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_CampaignList WHERE acctID=?");
	$dvResults = $Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$rows = $Stmt->fetchAll();
	
	$dvResult[] = array();
	while ($row = $Stmt->fetch()) {
		$dvResult[] = $row;
	} 
	
	
	$programs = $getProgramListResponse->ProgramList;
	foreach($programs as $program){
	    
	    
	    $programID = $program->ProgramID;
	    $campaignName = $program->ProgramName;
	    
	    
	    $campaignID = $programID;
	    
	    
	    $found = false;
	    $found1 = 'No';
	    
	    foreach ($rows as $row) {
	        $publishProgramID = $row['publishProgramID'];
	        
            if(strtolower($programID) == strtolower($publishProgramID)){
                $found = true;
                $found1 = 'Yes';
                $campaignID = $row['campaignID'];
                $campaignName = $row['campaignName'];
                $campaignSource = $row['campaignSource'];
                break;
            }
	    }
	    
	    $mustSave = '';
	    
	    if ($found == true) {
	      	if ($campaignSource == 'Studio') {
		    		$mustSave = 'Y';
	      	}
        } else {
            $mustSave = 'Y';
        }
		
		if ((startsWith($campaignName,"WebTracking2017")) || (startsWith($campaignName,"SBC-"))){
		    
		} else {
		    $record[] = $item;
		    
		    
		    if ($mustSave == 'Y') {
		    	$PublishDate = $program->PublishDate;
	    		$createDate = studioToDVTimeStamp($PublishDate);
	    		$LastCheckin = $program->LastCheckin;
	    		$lastEditDate = studioToDVTimeStamp($LastCheckin);
	    		
	    		$status = 'Live';
	    		$campaignType = 'PromoteBlog';
	    		$publishDate = $createDate;
	    		$publishProgramID = $campaignID;
	    		$campaignStatus = $program->StateName;
	    		$campaingLink = $campaignID;
	    		$userURL = 'N';
	    		$isDripCampaign = 'N';    
	    		$campaignSource = 'Studio';
	    		
	    		$sql = 'INSERT INTO DV_CampaignList (acctID, campaignID, campaignName, createDate, lastEditDate, status, campaignType, publishDate, publishProgramID, campaignStatus, campaingLink, userURL, isDripCampaign, campaignSource) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE acctID=?, campaignID=?, campaignName=?, createDate=?, lastEditDate=?, status=?, campaignType=?, publishDate=?, publishProgramID=?, campaignStatus=?, campaingLink=?, userURL=?, isDripCampaign=?, campaignSource=?';
	    		
	    		try {
	    			
	    			$logger->log("GetCampaignListPortal sql : $sql");
	    			$logger->log("$acctID, $campaignID, $campaignName, $createDate, $lastEditDate, $status, $campaignType, $publishDate, $publishProgramID, $campaignStatus, $campaingLink, $userURL, $isDripCampaign, $campaignSource");	
	    			
					$Stmt1 = MySQL_Prepare($db,$sql);
					$Stmt1->execute(array($acctID, $campaignID, $campaignName, $createDate, $lastEditDate, $status, $campaignType, $publishDate, $publishProgramID, $campaignStatus, $campaingLink, $userURL, $isDripCampaign, $campaignSource, $acctID, $campaignID, $campaignName, $createDate, $lastEditDate, $status, $campaignType, $publishDate, $publishProgramID, $campaignStatus, $campaingLink, $userURL, $isDripCampaign, $campaignSource));
				} catch(Exception $e){
					$success = false;	
					$ErrorMessage = $e->getMessage();
			   }		
	    		
		    }
		}
    }
	$logger->log("stop GetCampaignListPortal  ---------");	
    return $record;
}	


function getBrandLink($acctID) {
	$brandLink = '<a href="http://www.mindfireinc.com" target="_blank">Powered by MindFire</a>';
	$removeBrandTrigger = '';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$removeBrandTrigger = $row['removeBrandTrigger'];
		}
		if ($removeBrandTrigger == 'Y') {
			$brandLink = '';
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	return $brandLink;
}

function getReminderLogin($acctID, $userID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$message = '';
	$reminderID = '';
	try { 
		$sqltxt = "select * from DV_Notification1 where NotificationAcct = ? and NotificationUser = ? and NotificationActionDate <= NOW() and reminderStatus <> 'DISMISS' and NotificationOriginModule='REMINDER' order by NotificationActionDate";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($acctID, $userID));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		
		while ($row = $Stmt->fetch()) {
			
			$Id = $row['Id'];
			$NotificationBody = $row['NotificationBody'];
			//You have a reminder to contact coa9201  test9201 at 04/21/2021 11:35:00 AM.
			$NotificationBody = str_ireplace('You have a reminder to contact ', '', $NotificationBody);
			$message = $message.$NotificationBody.'<br>';
			$reminderID = $reminderID.','.$Id;
			$group = array(					
				'Id'=>$Id,
				'NotificationBody'=>$NotificationBody
			);					
			$row2[] = $group;
			
		}
	}
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	
	if ($reminderID <> '') {
		$reminderID = substr($reminderID, 1);
	}
	
	if ($message) { } else { $message = ''; }
	
	
	$result['reminderMessage'] = $message;
	$result['reminderID'] = $reminderID;
	$result['row2'] = $row2;
	return $result;
}

function createBillingReport($TEST, $moreInfo) {
	
	$fd = $moreInfo['fd'];
	$td = $moreInfo['td'];
	$billEmail = $moreInfo['billEmail'];
	
	$cronName = 'billingReport.php';
	$logger2 = new FileLogger("billingReport.log"); 
	$startDate = date("M d Y H:i:s");
	$str1 = "\n<br>\n<br>\n<br>$startDate => $cronName , fd : $fd , td : $td , billEmail : $billEmail started.\n<br>";
	$logger2->log($str1);	
	//echo $str1;
	
	
	if ($TEST2) {
		
	} else {
		$TEST2 = '';
	}
	if ($initmode) {
		
	} else {
		$initmode = '';
	}
	if ($fd) {
		
	} else {
		$fd = '';
	}
	if ($td) {
		
	} else {
		$td = '';
	}
	if ($ACCOUNTID) {
		
	} else {
		$ACCOUNTID = '';
	}
	
	$db = GetPDOMySQL();
	
	$sql = "SELECT * FROM DV_UserInfo WHERE ( (studioAccountName NOT LIKE 'MFINC Test%') AND (studioAccountName NOT LIKE 'MFINC PROD%') AND (studioAccountName NOT LIKE 'coa test%') ) ";
		
	if ($TEST2 == 't') {
		$sql = $sql." AND ( acctID in (28870, 30215, 18723) )";
	} else if ($ACCOUNTID == '') {
		$sql = $sql." AND ( paidAccount='Y' )";
	} else {
		$sql = $sql." AND ( acctID='$ACCOUNTID' )";
	}
	
	$sql = $sql." order by acctID desc";
	
	$Stmt = MySQL_Prepare($db,$sql);
	
	$Stmt->execute();
	$userCount = $Stmt->rowCount();
	
	$accountList = getDVBilling();
	
	
	$date = new DateTime();
	$date->add(DateInterval::createFromDateString('yesterday'));
	if ($fd == '') {
		$fd = $date->format('m/d/Y');
	}
	if ($td == '') {
		$date = new DateTime();
		$td = $date->format('m/d/Y');
	}
	if ($initmode == 'Y') {
		$fd = "01/01/2018";
	}
	
	$str1 = "ACCOUNTID : $ACCOUNTID,fd : $fd,td : $td\n<br>";
	$logger2->log($str1);	
	
	$delimiter = '|';
	$oldDelimiter = "_";
	
	$data = '';
	
	$studioParam = GetStudioAPIParam();
	$EMAIL = $studioParam['email'];
	$PWD = $studioParam['pwd'];
	
	$ProgramID = 0;
	$mode = 'Account_Stat';
	
	$serverMode = GetCurrentConfig()->serverName;
	
	while ($row = $Stmt->fetch()) {  
		$PROGRAMID = 0;
		$INBOUNDID = 0;
		$ACCOUNTID = $row['acctID'];
		$accountName = $row['studioAccountName'];
		$userStatus = $row['userStatus'];
		
		$usersTimezone = getUserTimeZone($ACCOUNTID);
		
		$ParentID = getParentID('', $ACCOUNTID);
		
		$logger2->log($userCount);	
		$userCount = $userCount-1;
		
		$fieldname1 = 'insertTime';
		$fieldname2 = 'createdDate';
		$ConversionSQLDate = getConversionSQLDateRange($fd, $td, $usersTimezone, $fieldname1);
		$countDate = $ConversionSQLDate['countDate'];
		$origin = $ConversionSQLDate['origin'];
		$target = $ConversionSQLDate['target'];
		$CountDate1 = $ConversionSQLDate['CountDate1'];
		$CountDate2 = $ConversionSQLDate['CountDate2'];
		
		if ($ParentID == '') {
			$ParentID = $ACCOUNTID;
			$ParentName = $accountName;
		} else {
			$parentInfo = getDVUserinfo($ParentID);
			$ParentName = $parentInfo['studioAccountName'];
		}
		
		$Emails = 0;
		$Contacts = 0;
		$Visits = 0;
		$SMS = 0;
		$Enrichment = 0;
		
		$Emails = getSumTargetCount($ACCOUNTID, $CountDate2, $CountDate1, $logger2);
		if ($Emails == '') { $Emails = 0; }
		
		$sqlCountDate = $ConversionSQLDate['sqlCountDate'];
		
		$sqlCountDate2 = $sqlCountDate;
		$sqlCountDate2 = str_ireplace($fieldname1, $fieldname2, $sqlCountDate2);
		$sqlCountDate2 = str_ireplace($fieldname1, $fieldname2, $sqlCountDate2);
		
		$Visits = getVisitCount($ACCOUNTID, $sqlCountDate, $logger2);
		$tmp = "$ACCOUNTID, $sqlCountDate, $sqlCountDate2<br>";
		
		if ($TEST2 == 't') {	
			echo $tmp;
		}
		$logger2->log($tmp);
		
		if ($Visits == '') { $Visits = 0; }
		
		$Everyones = getEveryone($ACCOUNTID, $logger2);
		foreach( $Everyones as $Everyone ) {		
			$Contacts = $Everyone['LISTCOUNT'];
		}
		if ( ($Contacts == '') || ($Contacts == '-1') || ($Contacts == 'null') ) { $Contacts = 0; }
		
		
		$token = getTokenCurl($ACCOUNTID, $EMAIL, $PWD);
		$tmp = "ACCOUNTID : $ACCOUNTID , token : $token";
		$logger2->log($tmp);
		
		$smsTest = '';
		$AllsmsStat = GetReportAllsmsMode($smsTest, $ACCOUNTID, $fd, $td, $token, $ProgramID, $mode, $EMAIL, $PWD);
		$SMS = $AllsmsStat['SentAll'];
		if ( ($SMS == '') || ($SMS == '-1') || ($SMS == 'null') ) { $SMS = 0; }
		
		$tmp = "SMS : $SMS";
		$logger2->log($tmp);
		
		
		$Enrichs = getEnrichContact($ACCOUNTID, $sqlCountDate2, $logger2);
		foreach( $Enrichs as $Enrich ) {		
			$Enrichment = $Enrich['count'];
		}
		if ($Enrichment == '') { $Enrichment = 0; }
		
		if ( ($Contacts > 0) || ($Visits > 0) || ($Emails > 0) || ($SMS > 0) || ($Enrichment > 0) ) {
			$data .= "\"$ParentID\",\"$ParentName\",\"$ACCOUNTID\",\"$accountName\",\"$userStatus\",\"$Contacts\",\"$Visits\",\"$Emails\",\"$SMS\",\"$Enrichment\"\n";						
		}
		
		
	}
	
	$data = "\"Parent ID\",\"Parent Name\",\"Account ID\",\"Account Name\",\"Status\",\"Active Contacts\",\"Page Visits\",\"Emails Sent\",\"SMS Sent\",\"Data Enrichment\"\n".$data;
	
	$reportType = 'billingReport';
	
	
	$TDdate = new DateTime('now');
	$reportName = 'billingReport_'.$TDdate->format('mdY-His').'.csv'; 
	$reportFile = '/dvAdminReport/'.$reportName;
	
	// UPLOAD FILE TO S3
	$S3File = s3_put_contents($reportFile ,$data);	
	
	
	$emailSubject = "Billing Report from $fd to $td [$serverMode]";
	$t = $TDdate->format('F-d-Y ');	// date("F-d-Y-h-i-a",time());
	$emailContent = "$emailSubject. Please click on the link below to download the CSV file.\n <a href=\"$S3File\">Click</a> ";
	$logger2->log("Finish prepare data \n");
	
	//$logger2->log("[$reportEmail]\n[$emailSubject]\n[$emailContent]\n");
	$sentResult = sendsocketlabsEmail($billEmail, $emailSubject, $emailContent, '');
	
	$stopDate = date("M d Y H:i:s");
	
	$logger2->log("$startDate - $stopDate send info to socketlab $S3File\n");
	
	$success = true;
	
	return array(
		'success'=>$success, 
		'S3File'=>$S3File
	);
}

function getReportCsv($TEST, $moreInfo) {
	$logger2 = new FileLogger("getReportCSV.log");
	$logger2->log("\n START \n");
	
	$fd = $moreInfo['fd'];
	$td = $moreInfo['td'];
	$reportEmail = $moreInfo['reportEmail'];
	
	$logger2->log("1. from _GET fd=[$fd] td=[$td] reportEmail=[$reportEmail] \n");

	if ($td =='') {
		$td = date("m/d/Y",time()); 
		$fd = date("m/01/Y",time()); 
	}


	$TEST = '';
	$logger2->log("1. fd=[$fd] td=[$td] reportEmail=[$reportEmail] \n");


	//$records = MySQL_Select("SELECT * FROM DV_UserInfo WHERE paidAccount='Y' order by acctID limit 10 ");
	$records = MySQL_Select("SELECT * FROM DV_UserInfo WHERE acctID>0 and paidAccount='Y' order by acctID desc");


	if($records==[]){
		$logger2->log("DV_UserInfo Empty");
	}else{
		// array to keep report data
		$res;
		// Expected output Acct ID, Acct Name, Total Contacts, Email Sent, Email Delivered, Email Opened, Email Clicked, Page Visited, Page Submitted
		$acctID = "";
		$acctName = "";
		$totalContacts = "";
		$emailSent = "";
		$emailDelivered = "";
		$emailOpened = "";
		$emailClicked = "";
		$pageVisited = "";
		$pageSubmitted = "";

		$newLine = "\n";

		$output .=arrayToCsv(['Acct ID', 'Acct Name', 'Total Contacts', 'Email Sent', 'Email Delivered', 'Email Opened', 'Email Clicked', 'Page Visited', 'Page Submitted']).$newLine;

		$studioParam = GetStudioAPIParam();
		$POWERACCOUNTID = $studioParam['accountID'];
		$POWEREMAIL = $studioParam['email'];
		$POWERPWD = $studioParam['pwd'];
		$PARTNERGUID = $studioParam['guid'];
		$PARTNERPASSWORD = $studioParam['password'];
		$LISTDEFINITION = '<Filter CriteriaJoinOperator="1"><Criteria Row="1" Field="IsSeed" Operator="NotEqual" Value="True" /></Filter>';
		
		//$logger2->log("2. POWERACCOUNTID=[$POWERACCOUNTID] POWEREMAIL=[$POWEREMAIL] POWERPWD=[$POWERPWD] PARTNERGUID=[$PARTNERGUID]\n");
		$i = 1;
		$mode = 'Account_Stat';
		foreach($records as $record){
			$count = 0;
			$acctID = $record['acctID'];
			$acctName = $record['studioAccountName'];
			$logger2->log("\nFOR record no $i\n 4. acctID=[$acctID] acctName=[$acctName]\n");
			//$logger2->log("  acctID=[$acctID] POWEREMAIL=[$POWEREMAIL] POWERPWD=[$POWERPWD] PARTNERGUID=[$PARTNERGUID]\n");

			//send 0 for program ID to collect all 
			$programID = "0";

			$FieldNames = getxmediaAPIAcctID($ACCOUNTID);
			$PARTNERGUID = $FieldNames[0];
			$PARTNERPASSWORD = $FieldNames[1]; 
			$AuthenticateCurl = getAuthenticateCurl($ACCOUNTID, $POWEREMAIL, $POWERPWD, $PARTNERGUID, $PARTNERPASSWORD);	
			$PartnerTicket = $AuthenticateCurl['PartnerTicket'];
			$ticket = $AuthenticateCurl['userTicket'];
			
			$token = getToken($acctID, $POWEREMAIL, $POWERPWD);
			
			$logger2->log("4.1  acctID=[$acctID] ticket=[$ticket] token=[$token]\n");
			
			if ($ticket != '') {
				$count = GetContactCount($TEST, $ticket, $LISTDEFINITION, $acctID);
			} else {
				$count = 0;
			}
			$totalContacts = $count;
			$logger2->log("4.2  totalContacts=[$totalContacts] count=[$count]\n");

			
			$AllEmailsStat = null;

			
			//$logger2->log("==>  ticket=[$ticket] token=[$token]\n");

			//$token = getToken($acctID, $POWEREMAIL, $POWERPWD);
			

			$AllEmailsStat = GetReportAllEmailMode($TEST, $acctID, $fd, $td, $token, $programID, $mode, $POWEREMAIL, $POWERPWD);
			$logger2->log("4.3  AllEmailsStat=[".json_encode($AllEmailsStat)."] TEST=[$TEST]  acctID=[$acctID] fd=[$fd] td=[$td] token=[$token]\n");

			$emailSent = 0;
			$emailDelivered = 0;
			$emailOpened = 0;
			$emailClicked = 0;
			if($AllEmailsStat==[]){
				$logger2->log("4.3 GetReportAllEmail Empty \n");
			}else{
				$logger2->log("4.3 GetReportAllEmail NOT Empty \n");
				foreach($AllEmailsStat as $emailsStat){
					//$logger2->log("-->  emailsStat=[".json_encode($emailsStat)."]\n");
					$sent	= $emailsStat['Sent'];
					$opened	= $emailsStat['Opened'];
					$clicked	= $emailsStat['Clicked'];
					$delivered = $emailsStat['Delivered'];
					$logger2->log("  sent=[$sent]  opened=[$opened] clicked=[$clicked] delivered=[$delivered] \n");

					$emailSent		= $emailSent+$sent;//$emailsStat['Delivered'];
					$emailDelivered = $emailDelivered+$delivered;
					$emailOpened	= $emailOpened+$opened;
					$emailClicked	= $emailClicked+$clicked;
					$logger2->log("emailSent=[$emailSent] emailOpened=[$emailOpened] emailClicked=[$emailClicked] \n");
				}
				
				
			}
			$logger2->log("4.2  totalContacts=[$totalContacts] count=[$count]\n");
			$logger2->log("4.3  emailSent=[$emailSent]  emailDelivered=[$emailDelivered] emailOpened=[$emailOpened] emailClicked=[$emailClicked] \n");

			$AllPagesStat = null;
			//$logger2->log("GET page stat for TEST=[$TEST]  acctID=[$acctID] fd=[$fd] td=[$td] token=[$token]\n");

			//$AllPagesStat = GetReportAllPage($TEST, $acctID, $fd, $td, $token, "0");


			$AllPagesStat = GetReportAllPageMode($TEST, $acctID, $fd, $td, $token, "0", $mode, $POWEREMAIL, $POWERPWD);
			//$logger2->log("4.4  AllPagesStat=[$AllPagesStat]  TEST=[$TEST]  acctID=[$acctID] fd=[$fd] td=[$td] token=[$token]\n");
			$pageVisited = 0;
			$pageSubmitted = 0;
			
			if($AllPagesStat==[]){
				//$logger2->log("4.4 AllPagesStat Empty \n");
			}else{
				//$logger2->log("4.4 AllPagesStat NOT Empty \n)";
				foreach($AllPagesStat as $pagesStat){
					$visited	= $pagesStat['Visitors'];
					$submitted	= $pagesStat['Submits'];
					
					$pageVisited		= $pageVisited+$visited;
					$pageSubmitted		= $pageSubmitted+$submitted;

					//$logger2->log(" visited=[$visited]  submitted=[$submitted]  pageVisited=[$pageVisited] pageSubmitted=[$pageSubmitted] \n");
				}
				
				
				
			}
			$logger2->log("4.4  pageVisited=[$pageVisited] pageSubmitted=[$pageSubmitted] \n");
			$logger2->log("BEFORE  $acctID, $acctName, $totalContacts, $emailSent, $emailOpened, $emailClicked, $pageVisited, $pageSubmitted \n");
			
			//$res[] = ["$acctID", "$acctName", "$totalContacts", "$emailSent", "$emailOpened", "$emailClicked", "$pageVisited", "$pageSubmitted"];
			$output .=arrayToCsv(["$acctID", "$acctName", "$totalContacts", "$emailSent", "$emailDelivered", "$emailOpened", "$emailClicked", "$pageVisited", "$pageSubmitted"]).$newLine;

			//$logger2->log("AFTER array_push\nres=[".$output."]\n");

			$i++;
			
			

		}

		//$logger2->log("BEFORE create file on S3\n");
		
		$t = date("mdY-His",time()); 
		$tmpName = 'AccountStat_'.$t.'.csv'; 
		//$filename = '/dvAdminReport/'.$tmpName;
		$filename = '/dvAdminReport/'.$tmpName;

		//file_put_contents($filename,$output); //need parameter file name and content of file
		// UPLOAD FILE TO S3
		$reportS3File = s3_put_contents($filename ,$output);	
		//echo "After s3_put_contents to S3 file=[$reportS3File] \n";
		//$reportEmail = "nuntiya@mindfiremail.info";
		
		$emailSubject = "MindFire Usage Statistics from $fd to $td ";
		$emailContent = "Please click on the link below to download the usage statistics of MindFire for the above mentioned period.\n <a href=\"$reportS3File\">$reportS3File</a> ";

		$logger2->log("[$reportEmail]\n[$emailSubject]\n[$emailContent]\n");
		$sentResult = sendsocketlabsEmail($reportEmail, $emailSubject, $emailContent, '');
		$logger2->log("[".json_encode($sentResult)."]\n");
		$logger2->log("END ". time(). "\n");

		return $sentResult;

	}
}

function mapBouncerCode($mode, $value){
	
	if ($mode == 'status') {
		if ($value == 'deliverable') {
			$status = 0;
		} else if ($value == 'risky') {
			$status = 1;
		} else if ($value == 'undeliverable') {
			$status = 2;
		} else {
			$status = 3;
		}
		return $status;
	}
	
	if ($mode == 'reason') {
		if ($value == 'accepted_email') {
			$reason = 0;
		} else if ($value == 'dns_error') {
			$reason = 1;
		} else if ($value == 'invalid_domain') {
			$reason = 2;
		} else if ($value == 'low_deliverability') {
			$reason = 3;
		} else if ($value == 'low_quality') {
			$reason = 4;
		} else if ($value == 'rejected_email') {
			$reason = 5;
		} else if ($value == 'unavailable_smtp') {
			$reason = 6;
		} else {
			$reason = 7;
		}
		return $reason;
	}
}

function mapBouncerValue($mode, $code){
	
	if ($mode == 'status') {
		if ($code == 0) {
			$status = 'deliverable';
		} else if ($code == 1) {
			$status = 'risky';
		} else if ($code == 2) {
			$status = 'undeliverable';
		} else {
			$status = 'unknown';
		}
		return $status;
	}
	
	if ($mode == 'reason') {
		if ($code == 0) {
			$reason = 'accepted_email';
		} else if ($code == 1) {
			$reason = 'dns_error';
		} else if ($code == 2) {
			$reason = 'invalid_domain';
		} else if ($code == 3) {
			$reason = 'low_deliverability';
		} else if ($code == 4) {
			$reason = 'low_quality';
		} else if ($code == 5) {
			$reason = 'rejected_email';
		} else if ($code == 6) {
			$reason = 'unavailable_smtp';
		} else {
			$reason = 'unknown';
		}
		return $reason;
	}
}

function getUiAccess() {
	
	$logger2 = new FileLogger("getUiAccess.log");
	
	
	$UiAccess = intval($_SESSION['UIACCESS']);
	$acctID = $_SESSION['ACCOUNTID'];
	$logger2->log("acctID : $acctID ");
	$logger2->log("UiAccess : $UiAccess ");
	
	if ($UiAccess > 2) {
		$UiAccess = POWERUSERUIACCESS;
	}
	
	// for test 
	/*
	$accountID = $_SESSION['ACCOUNTID'];
	if ($accountID == 31618) {
		$UiAccess = 0;
	} else if ($accountID == 31626) {
		$UiAccess = 1;
	} else if ($accountID == 11) {
		$UiAccess = 0;
	} else if ($accountID == 31823) {
		$UiAccess = 2;
	}
	*/
	return $UiAccess;
}

function getOGcurl($URL) {
	$logger = new FileLogger("shareDVLinkedIn.log"); 
	$serverMode = GetCurrentConfig()->serverName;
	if ( ($serverMode == 'LocalCoa') || ($serverMode == 'LocalCoaStudio') || ($serverMode == 'LocalJiewC9') || ($serverMode == 'LocalFungC9') ) {
	    //$detailURL = "https://davincistage.mindfireinc.com/admin/saveBilling.php";
	    $detailURL = "http://localhost:8080/admin/getOGTags.php";
	} else if ($serverMode == 'LocalJiew' || $serverMode == 'LocalFung') {
	    $detailURL = "http://127.0.0.1/admin/getOGTags.php";
	} else {
	    $detailURL = getServerHost("admin/getOGTags.php");
	}
	$detailURL = $detailURL."?url=".$URL;
	$logger->log("detailURL = ".  json_encode($detailURL) );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $detailURL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTREDIR, 3);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $logger->log("7724 info = ".  json_encode($info) );
    if($response === false){
        $errorMessage = json_encode(curl_error($ch));
        $logger->log("curl_error = ". $errorMessage  );
        $doc = false;
    }else{
        $errorMessage = '';
        $logger->log("response = ".  json_encode($response) );
        $doc = json_decode($response);
    }
    return $response;
}   

function isCL30TypeFromID($dbName,$acctID,$DTid){
	$logger = new FileLogger("linkedInAPI.log"); 
	try { 
		$sqltxt = "select * from $dbName.DV_Document_PromoteBlog where acctID = ? and ID = ?  limit 1";
		$logger->log("isCL30TypeFromID:: SQL = $sqltxt  >> "  . " $acctID / $DTid");    
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($acctID, $DTid));
		$affected_rows = $Stmt->rowCount();
		if($affected_rows == 0){
			return true; 
		}		
	}catch(exception $e) {
		$errorMessage = $e->getMessage();		
	}
	return false;
}

function getLinkedInInfo($param) {
	$logger2 = new FileLogger("linkedInAPI.log"); 	
	$key1 = $param['key1'];
	$key2 = $param['key2'];
	$url = $param['url'];
	$acctID = $param['acctID'];
	$campaignID = $param['campaignID'];
	$dbName = $param['dbName'];
	$emailID = $param['emailID'];
	$errorMessage = '';
	
	// https://davincistage.mindfireinc.com/admin/fbShare.php?u=28870_ce523038184d46bb20eabc069ee35292&r=0.9479566969390603&url=https%3A%2F%2F28870.my-dv.com%2F907a3452a2953d6e8016b154217ec897%2F?l=FBPOST
	
	//https://28870.my-dv.com/907a3452a2953d6e8016b154217ec897/?l=FBPOST
	
	$parts = parse_url($url);
	parse_str($parts['query'], $query);
	$value1 = $query[$key1];
	$postURL = $query[$key2];
	
	
	$DTid = str_replace("$acctID"."_", '', $value1);
	if ($campaignID) $DTid = $campaignID;
	echo "DTid : $DTid<br>";
	echo "DTid : $DTid<br>";

	
	try { 
		$sqltxt = "select * from $dbName.DV_Document_PromoteBlog where acctID = ? and ID = ?  limit 1";
		if(isCL30TypeFromID($dbName,$acctID,$DTid)){   //#2298
			$sqltxt = "select * from $dbName.DV_Document_CL30 where acctID = ? and ID = ?  limit 1";
		}
	    $logger2->log("getLinkedInInfo:: SQL = $sqltxt  >> $acctID / $DTid");
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($acctID, $DTid));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		
		while ($row = $Stmt->fetch()) {
			
			$docData = json_decode($row['data'],true);
			echo 'docData: ' . json_encode($docData['LinkedInPost']).'<br>';			
			$i = str_replace("email", '', $emailID);
			
			if (empty($docData['LinkedInPost'][$i]['POST-IMAGE'])) {
				$resolvedUrl = '';
			} else {
				$html = $docData['LinkedInPost'][$i]['POST-IMAGE'];				

				$gotSrc = strpos($html, "src=") ; 
				if($gotSrc !== false){
					echo "html";
					var_dump($html);
					echo "<br>";
					$doc = new DOMDocument();
					$doc->loadHTML($html);
					$xpath = new DOMXPath($doc);
					$resolvedUrl = $xpath->evaluate("string(//img/@src)"); 

				}else{
					$resolvedUrl = $html; 
				}
			}
		}
		$logger2->log("getLinkedInInfo:: resolvedUrl = $resolvedUrl");
	}
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}	
	
	$result = array();
	$result['value1'] = $value1;
	$result['postURL'] = $postURL;
	$result['DTid'] = $DTid;
	$result['resolvedUrl'] = $resolvedUrl;
	$result['errorMessage'] = $errorMessage;
	return $result;
	
}

function getPortalInfo() {
	$PORTALTOKEN = $_SESSION['PORTALTOKEN'];
	$ACCOUNTID = $_SESSION['ACCOUNTID'];
	
	$CurrentConfig = davinciSetConfig();
	$davinciDomain = $CurrentConfig->davinciDomain;
	if ( ($serverMode == 'LocalCoa') || ($serverMode == 'LocalCoaStudio') ) {
		$dvurl = 'https://a45596625bdf4e74beb52d3a1dd967f4.vfs.cloud9.us-west-1.amazonaws.com/'; 
	} else {
		$dvurl = $davinciDomain;
	}
	
	$dvurl = $dvurl."admin/portalLogin.php";
	
	$campaignURL = "https://studioweb.mdl.io/mycampaigns?token=".$PORTALTOKEN."&aid=".$ACCOUNTID."&dvURL=".$dvurl;
	
	$result = array();
	$result['campaignURL'] = $campaignURL;
	
	return $result;
}

function getProductIncluded($productCode) {
	try { 
		$sqltxt = "select * from DV_Product where productCode = ? limit 1";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($productCode));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		$UiAccess = 1;
		
		while ($row = $Stmt->fetch()) {
			
			$productData = json_decode($row['productData'],true);
			$productIncluded = $productData['productIncluded'];
			
			if ($productIncluded) {
				
				if (in_array('WFD', array_map("strtoupper", $productIncluded))) {
					$UiAccess = 2;
				}				
			}			
		}
	}
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	$result = array();
	$result['UiAccess'] = $UiAccess;
	return $result;
}


function awsAllSSLCron($param) {
	$errorMessage = '';
	$success = true;
	try {
		
		$scriptPath = realpath('awsAllSSLCron.php');

		if ($scriptPath) {
		    $output = shell_exec("php $scriptPath");
		    $errorMessage = $output;
		} else {
			$success = false;
		    $errorMessage = "Script not found.";
		}
		
		
	}  
	catch(exception $e) {
		$success = false;
		$errorMessage = $e->getMessage();
	}
	
	$result = array(
        "success"=>$success,
        "message"=>$errorMessage,
    );  
    return $result;
}
		

function setupDV($param) {
	$errorMessage = '';
	$logger2 = new FileLogger("setupDV.log"); 
	try {  
		if($db==null){
            $db = GetPDOMySQL();
        }
		
		$setupMode = $param['setupMode'];
		$acctID = $param['acctID'];
		$EMAIL  = $param['EMAIL'];
		$PASSWORD = $param['PASSWORD'];
		$ParentAccountID = $param['ParentAccountID'];
		$ParentUiAccess = $param['ParentUiAccess'];
		$dbName = $param['dbName'];
		$PARTNERGUID = $param['PARTNERGUID'];
		$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
		
		if ($setupMode == 'new') {
			$Authenticate = getAcrossAuthenticateCurl($acctID, $EMAIL, $PASSWORD, $PARTNERGUID, $PARTNERPASSWORD);
			$logger2->log("setupMode : $setupMode");
			$logger2->log("Authenticate = ". json_encode($Authenticate) );
			$PartnerCredentials = $Authenticate['Credentials'];
			$accountTicket = $Authenticate['userTicket'];
			$UiAccess = $Authenticate['UiAccess'];
			$authResponse = $Authenticate['authResponse'];
			$logger2->log("authResponse = ". json_encode($authResponse) );
			$signUpFirstName = $authResponse->FirstName;
			$signUpLastName = $authResponse->LastName;
			$acctName = $authResponse->{"AvailableAccountList"}[0]->{"Value"};
			if (($UiAccess == 1) || ($UiAccess == 2)) {
				$logMode = 'subaccount';
			}
			$param['signUpFirstName'] = $signUpFirstName;
			$param['signUpLastName'] = $signUpLastName;
			$param['acctName'] = $acctName;
			$param['logMode'] = $logMode;
			$logger2->log("signUpFirstName = $signUpFirstName , signUpLastName : $signUpLastName , acctName : $acctName , UiAccess : $UiAccess");
		}
		
		$logger2->log("param = ". json_encode($param) );
		
		$createStudioAPI = createStudioAPI($param);
		$logger2->log("createStudioAPI = ". json_encode($createStudioAPI) );
		$initStudioTable = initStudioTable($param);
		$logger2->log("initStudioTable = ". json_encode($initStudioTable) );
		
		
		
		
		
		$signUpAccount = $param['acctName'];
		$signUpFirstName = $param['signUpFirstName'];
		$signUpLastName = $param['signUpLastName'];
		$Credentials = $param['Credentials'];
		$logMode = $param['logMode'];
		$studioDomain = $param['studioDomain'];
		
		
		if ($setupMode) { } else { $setupMode = ''; }
		
		$setupCredentials = $param['setupCredentials'];
		
		
		try{
			
            $stmt = $db->prepare("UPDATE DV_SetupUser set status='working' where acctID=?");
            $stmt->execute(array($acctID));
            $affected_rows = $stmt->rowCount();

        }catch(PDOException $ex){	
            $MySQLmessage = $ex->getMessage();	
            $logger2->log(" SignUpSaveLog 0002 DV_SetupUser : $MySQLmessage");
        }
		
		randomUserType($acctID);
		
		if ($UiAccess == 0) {
			$paidAccount = '';
			updatePaidAccountStatus($paidAccount, $acctID);
		}
		
		updateDV_Uiaccess($UiAccess, $acctID);
		
		$insertAssetDirectoryDB = insertAssetDirectoryDB($acctID, $dbName);
		$logger2->log("insertAssetDirectoryDB = ". json_encode($insertAssetDirectoryDB) );
		
		$studioParam = GetStudioAPIParam();
		$POWERACCOUNTID = $studioParam['accountID'];
		$subaccountData = '';
		
		$logger2->log("ParentAccountID : $ParentAccountID ,  POWERACCOUNTID : $POWERACCOUNTID");
		
		if (($ParentAccountID <> '') && ($ParentAccountID <> $POWERACCOUNTID)) {
			try{
	             
	            
	            $stmt = $db->prepare("INSERT INTO DV_Account(acctID,parentID,data) VALUES(?, ?, ?) on duplicate key update parentID=?,data=?");
	            $stmt->execute(array($acctID,$ParentAccountID,$subaccountData,$ParentAccountID,$subaccountData));
	            $affected_rows = $stmt->rowCount();
	
	        }catch(PDOException $ex){	
	            $MySQLmessage = $ex->getMessage();	
	            $logger2->log(" SignUpSaveLog 0002 MySQLmessage : $MySQLmessage");
	        }
			
		} else {
			try{
	            if($db==null){
	                $db = GetPDOMySQL();
	            } 
	            $subaccountData = '';
	            $stmt = $db->prepare("DELETE FROM DV_Account where acctID=?");
	            $stmt->execute(array($acctID));
	            $affected_rows = $stmt->rowCount();
	
	        }catch(PDOException $ex){	
	            $MySQLmessage = $ex->getMessage();	
	            $logger2->log(" SignUpSaveLog 0002 MySQLmessage : $MySQLmessage");
	        }
		}
		
		
		
		
		if ($logMode == 'subaccount') {
			
			if ($setupMode == 'new') {
				
			} else {
				if ($_SESSION['STUDIOLOGIN'] == "Y") {
					$PartnerCredentials = $_SESSION['LOGINCREDENTIALS'];	
				} else {
					$PartnerCredentials = $param['PartnerCredentials'];
				}
			}
			if ($setupCredentials) {
				
				$logger2->log("setupCredentials = ". json_encode($setupCredentials) );
				$accountTicket = $setupCredentials['Ticket'];
				$logger2->log("accountTicket = $accountTicket");
				$PartnerCredentials = $setupCredentials;
			}
			if ($PartnerCredentials) {
				//$accountTicket = $PartnerCredentials->Ticket;
				//$logger2->log("accountTicket = $accountTicket");
			} else {
				$Authenticate = getAcrossAuthenticateCurl($acctID, $EMAIL, $PASSWORD, $PARTNERGUID, $PARTNERPASSWORD);
				$logger2->log("Authenticate = ". json_encode($Authenticate) );
				$PartnerCredentials = $Authenticate['Credentials'];
				$accountTicket = $Authenticate['userTicket'];
			}
			
			
			if ($accountTicket) { } else { $accountTicket = ''; }
			
			$logger2->log("PartnerCredentials = ".json_encode($PartnerCredentials));
			
			$SeedContact = addSeedContactCredentials($PartnerCredentials, $signUpFirstName, $signUpLastName, $EMAIL);
			$logger2->log("addSeedContact = ". json_encode($SeedContact) );
			if ($SeedContact) {
				if ($SeedContact['success']) {
					$AddSeedContact_status = "Success";
				} else {
					$AddSeedContact_status = "Fail";
				}
			} else {
				$AddSeedContact_status = "Fail";
			}
	
			$domain = "$acctID.$studioDomain";
			$domainResult = createDomain($logger2, $PartnerCredentials,$domain);
			$AddDomain_status = $domainResult['status'];
			$errorMessage = $domainResult['errorMessage'];
			$domainID = $domainResult['roleID'];
			$ErrorMessageStudio = $domainResult['ErrorMessageStudio'];
			
			$roleResult = createRole($logger2, $PartnerCredentials);
            $AddRole_status = $roleResult['status'];
            $errorMessage = $roleResult['errorMessage'];
            $roleID = $roleResult['roleID'];
            $ErrorMessageStudio = $roleResult['ErrorMessageStudio'];
			
			// Add Goal #2233
			$goalResult = createGoal($logger2, $PartnerCredentials); 
			
			if ($accountTicket == '') {
				$logger2->log("Do not call createWebTracking accountTicket is null, acctID : $acctID");
			} else {
				$addSeed = '';
				$AddResult = AddUser($accountTicket, $acctID, 'Davinci Davinci', 'davinci@mindfireinc.com', '', 'setting', $addSeed);
				$logger2->log("AddUser davinci@mindfireinc.com ". json_encode($AddResult));
				
				
				$id = getWebTrackingIdentifier('', $acctID);
				if ($id == '') {
					$logger2->log("About to call createWebTracking acctID : $acctID, accountTicket : $accountTicket");
					createWebTrackingSQS($accountTicket,$acctID);
					$logger2->log("Finished calling createWebTracking");	
				} else {
					$logger2->log("Do not call createWebTracking identifier : $id, acctID : $acctID,  ");
				}
			}
			
			updateUserInfoPortal($dbName, $acctID);
			
			$studioParam = GetStudioAPIParam();
			$POWERACCOUNTID = $studioParam['accountID'];
			reconFields('', $POWERACCOUNTID, $acctID);
		}
		
		try{
            $stmt = $db->prepare("UPDATE DV_SetupUser set status='completed' where acctID=?");
            $stmt->execute(array($acctID));
            $affected_rows = $stmt->rowCount();

        }catch(PDOException $ex){	
            $MySQLmessage = $ex->getMessage();	
            $logger2->log(" SignUpSaveLog 0002 DV_SetupUser : $MySQLmessage");
        }
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	
	
	if (($AddSeedContact_status == "Success") && ($AddDomain_status == "Success") && ($AddRole_status == "Success")) {
		
	} else {
		if ($acctID <> 32474) {
			sendSetupFailEmail($acctID);
		}
	}
	
	
	
	$defFromAdd = "mindfireproduct@mindfireinc.com ";
		$cc = "davincimonitoring@gmail.com";
	$subject = 'Set up of account '.$signUpAccount.' ['.$acctID.'] is complete';
	$emailMode = '';
	$content = 'Hello there:<br><br>
Your MindFire account '.$signUpAccount.' ['.$acctID.'] is now ready for use. Please <a href="https://mindfiresupport.freshdesk.com" target="_blank">click HERE</a> to access our HELP articles and learn about our product suite.<br><br>
Thank you.<br><br>
The MindFire Team';
	
	$data['EMAIL'] = $EMAIL;
	$data['subject'] = $subject;
	$data['content'] = $content;
	$data['emailMode'] = $emailMode;
	$data['cc'] = $cc;
	$data['Attachment'] = '';
	$data['fromEmail'] = $defFromAdd;
	$data['fromName'] =	'';
	$data['saveLog'] =	'yes';

	$data['acctID'] = $acctID;
	$data['alertDate'] = '';
	$data['noticeType'] = 'setupDV';							
	$data['NotificationAccount'] = $ParentAccountID;

	$data['NotificationTitle'] = '';
	$data['Notification'] = "Your account [$signUpAccount] setup is complete. Please log in again."; 						
	$NotificationLink = "";
	$data['NotificationLink'] = $NotificationLink;
	
	$emailRet = sendsocketlabsEmailAddLog($data);
	
	if ($setupMode == 'new') {
		$endDate = date("M d Y H:i:s");
		$str1 = "$endDate => $cronName ended";
		$logger2->log($str1);
		
		
		$str1 = "==========";
		$logger2->log($str1);
	}
	
	
	$result = array();
	$result['createStudioAPI'] = $createStudioAPI;
	$result['initStudioTable'] = $initStudioTable;
	$result['insertAssetDirectoryResult'] = $insertAssetDirectoryResult;
	//$result['AddUserResult'] = $AddUserResult;
	$result['errorMessage'] = $errorMessage;
	return $result;
}

function createStudioAPI($param) {
	$message = '';
	$success = true;
	
	
	$logger2 = new FileLogger("setupDV.log"); 
	
	
	$dbName = $param['dbName'];
	$acctID = $param['acctID'];
	$acctName = $param['acctName'];
	$PARTNERGUID = $param['PARTNERGUID'];
	$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
	
	try {  
		$table = "$dbName.DV_studioAPI";
		$sql = "INSERT INTO $table(acctID, accountname, PartnerGuid, PartnerPwd) ".
			"VALUES(?, ?, ?, ?) ".
			"on duplicate key update ".
			"accountname=?, PartnerGuid=?, PartnerPwd=?";
		$insertStmt = MySQL_Prepare($db,$sql);
		
		$PARTNERGUID = secured_encrypt($PARTNERGUID);
		$PARTNERPASSWORD = secured_encrypt($PARTNERPASSWORD);
		
		$insertStmt->execute(array($acctID, $acctName, $PARTNERGUID, $PARTNERPASSWORD, $acctName, $PARTNERGUID, $PARTNERPASSWORD));
		$logger2->log("createStudioAPI sql = $sql");
		$affected_rows = $insertStmt->rowCount();
	}  
	catch(exception $e) {
		$message = "ex: ".$e; 
		$logger2->log("error sql = $message");
		$affected_rows = -1;
		$success = false;
	}
	
	$result = array(
        "success"=>$success,
        "message"=>$message,
    );  
    return $result;
}


function initStudioTable($param) {
	
	$logger = new FileLogger("setupDV.log"); 
	$message = '';
	$success = true;
	
	$dbName = $param['dbName'];
	$acctID = $param['acctID'];
	$acctName = $param['acctName'];
	$PARTNERGUID = $param['PARTNERGUID'];
	$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
	
	$signUpEmail = $param['EMAIL'];
	$signUpFirstName = $param['signUpFirstName'];
	$signUpLastName = $param['signUpLastName'];
	$Credentials = $param['Credentials'];
	$logMode = $param['logMode'];
	$ParentAccountID = $param['ParentAccountID'];
	
	$tables = GetSignupTables();
    foreach($tables as $table){
        $tableName = $table["name"];
        $tableName = "$dbName.$tableName";
        $acctField = $table["fieldAccountID"];
		$cnt = 0; 
		$whereis = ["$acctField"=>-1]; 
		
		if($tableName == "$dbName.DV_Document_FormLibrary" ){
			//$whereis = ["$acctField"=>-1,"ID"=>"e0f67e9c2f82fdce8831f5297913ee45"]; //contact default
			$sqltext = "SELECT * FROM DV_Document_FormLibrary WHERE acctID = -1"; // and (`ID` = 'e0f67e9c2f82fdce8831f5297913ee45' or `ID` = 'f3f3dbc6395e5e0716d110fac581968e' or `ID` = '17887fff8c6b42b7e6c025c4cec9f695')
			$datas = MySQL_Select($sqltext);	
		} else if($tableName == "$dbName.DV_AudienceList"){
			//$sqltext = "SELECT * FROM ".$tableName. " WHERE acctID = -1 order by id"; 
			$sqltext = "SELECT * FROM ".$tableName. " WHERE acctID = -1 order by contactID desc "; 
			$datas = MySQL_Select($sqltext);	

		} else if($tableName == "$dbName.DV_PageTemplateList" || $tableName == "$dbName.DV_Document_PageTemplate"){  //#2459 ATR2
			$datas = []; 			
			$savePageRet = AddDefaultPageTemplate($acctID); 
			$logger->log("AddDefaultPageTemplate = ". json_encode($savePageRet) );

		}else{
			$datas = MySQL_GetAllByKey($tableName,$whereis);	
		}
		//$data = MySQL_GetByKey($tableName,["$acctField"=>-1]);			
		$logger->log("tableName = $tableName , datas = ". json_encode($datas) );	

		if($datas!=[]){
            foreach($datas as $data){
				$cnt++; 				
				if(($tableName == "$dbName.DV_UserInfo" && $cnt < 2) || $tableName == "$dbName.DV_AudienceList" || $tableName == "$dbName.DV_Document_FormLibrary")  {
					$mustSkip = false;
					if ($tableName == "$dbName.DV_UserInfo") {
						$sqltext1 = "SELECT * FROM $dbName.DV_UserInfo WHERE acctID = $acctID"; 
						$datas1 = MySQL_Select($sqltext1);
						if($datas1!=[]){
							
							$logger->log("skip DV_UserInfo");
							$mustSkip = true;
						}						
					}
					
					if ($mustSkip == false) {
					// only DV_AudienceList can insert all acctID = -1
						if(!empty($table['data'])){						
							//Decode field first
							$fieldData = json_decode($data[$table['fieldData']],true);
							$mergeData = ${$table['data']};					
							MergeArrayWithArray($fieldData,$mergeData,true);
							//Set data back after merge
							$data[$table['fieldData']] = json_encode($fieldData);
						}
						
						$data[$acctField] = $acctID;   					// Change acctID
						if(!empty($table['removeField'])){   					// Remove Field
							
							if( is_array($table['removeField']) ){
								foreach ($table['removeField'] as $piece) {
									unset($data[$piece]);
								}
							}else{
								//logDebug("removeField NOT array"); 									
								unset($data[$table['removeField']]);
							}
						}	
						$logger->log("data = ". json_encode($data) );
						$saveRet = MySQL_SaveByKey($tableName,$data);
						$logger->log("saveRet = ". json_encode($saveRet) );
					}
				}				
			}
		} // datas
    }
    
    $tableName = "$dbName.DV_UserInfo";
    
    $defTimezone = '';
    
    if ($ParentAccountID != '') {
    	try { 
			$sqltxt = "select * from $tableName where acctID = ? limit 1";
			$Stmt = MySQL_Prepare($db,$sqltxt);		
			$Stmt->execute(array($ParentAccountID));
			$affected_rows = $Stmt->rowCount();
			
			while ($row = $Stmt->fetch()) {
				$docData = json_decode($row['data'],true);
				$defTimezone = $docData['defTimezone'];
			}
    	}
    	catch(exception $e) {
			$errorMessage = $e->getMessage();
			$affected_rows = -1;	
		}
    }
    
    $logger->log("ParentAccountID = $ParentAccountID , defTimezone = $defTimezone");
    
    try { 
		$sqltxt = "select * from $tableName where acctID = ? limit 1";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();
		$row2 = array();
		
		
		while ($row = $Stmt->fetch()) {
			
			$docData = json_decode($row['data'],true);
			$docData['defCompanyName'] = $acctName;
			$docData['defTimezone'] = $defTimezone;
			
			if($logMode == 'subaccount'){
				$docData['defFromAdd'] = $signUpEmail;	
				$defFromName = "$signUpFirstName $signUpLastName";
				$docData['defFromName'] = $defFromName;
				$emailName = createMyDVEmail($acctName);
				
				$users = array(
		            array(
		               "name"=>$defFromName,
		               "email"=>"$signUpEmail",
		               "OfficialName"=>$defFromName,
		               "Cell"=>"",
		               "Permissions"=>"",
					   "spfFlag"=>"",
		            ),
					array(
		               "name"=>$defFromName,
		               "email"=>$emailName."@my-dv.com",
		               "fwdEmail"=>"$signUpEmail",
		               "OfficialName"=>$defFromName,
		               "Cell"=>"",
		               "Permissions"=>"",
					   "spfFlag"=>"1",
		            ),
		        );
				$docData['users'] = $users;
				
			}
			$saveData = json_encode($docData);
			
			$logger->log("saveData = $saveData");
			
			$sql = "UPDATE $tableName set studioAccountName=?,data=? where acctID=?";
			try {
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($acctName, $saveData, $acctID));
				$saveSuccess = true;
				$saveErrorMessage = '';
			} catch(Exception $e){
				$saveSuccess = false;	
				$saveErrorMessage = $e->getMessage();
			}
			
			updateSubAccountInfo($ParentAccountID, $acctID);
		}
	}
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
    $result = array(
        "success"=>$success,
        "message"=>$message,
    );  
    return $result;
}

function removeAudienceList($insID) {
	$sql = "DELETE FROM DV_AudienceList where id=?";
	try {
		$db = GetPDOMySQL();
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($insID));
		$saveSuccess = true;
		$saveErrorMessage = '';
	} catch(Exception $e){
		$saveSuccess = false;	
		$saveErrorMessage = $e->getMessage();
	}
	$result = array(
        "success"=>$saveSuccess,
        "message"=>$saveErrorMessage,
    );  
    return $result;
}

function getMustSetup($acctID){
	$mustSetup = false;
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_SetupUser where acctID=? and status='' limit 1");		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();	
		while ($row = $Stmt->fetch()) {
			$param = json_decode($row['data'],true);			
			$mustSetup = true;
		}
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	return $mustSetup;
}

function getBouncerConfig() {
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}
		$canFind = false;
		$result = array();
		
		$config = GetCurrentConfig();
		$bounceEstimateMin = intval($config->bounceEstimateMin);
		$bounceEstimateMax = intval($config->bounceEstimateMax);
		$usebouncerLimit = intval($config->usebouncerLimit);
		$usebouncerSample = intval($config->usebouncerSample);
		$bouncerCache = $config->bouncerCache;
		
		$result['bounceEstimateMin'] = $bounceEstimateMin;
		$result['bounceEstimateMax'] = $bounceEstimateMax;
		$result['usebouncerSample'] = $usebouncerSample;
		$result['usebouncerLimit'] = $usebouncerLimit;
		$result['bouncerCache'] = $bouncerCache;
		
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_Config where configName='bouncer' limit 1");		
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();	
		while ($row = $Stmt->fetch()) {
			$param = json_decode($row['configData'],true);			
			$bounceEstimateMin = intval($param['bounceEstimateMin']);
			$bounceEstimateMax = intval($param['bounceEstimateMax']);
			$usebouncerSample = intval($param['usebouncerSample']);
			$usebouncerLimit = intval($param['usebouncerLimit']);
			$bouncerCache = $param['bouncerCache'];
			$canFind = true;
		}
		$result['bounceEstimateMin'] = $bounceEstimateMin;
		$result['bounceEstimateMax'] = $bounceEstimateMax;
		$result['usebouncerSample'] = $usebouncerSample;
		$result['usebouncerLimit'] = $usebouncerLimit;
		$result['bouncerCache'] = $bouncerCache;
	}  
	catch(exception $e) {
		$affected_rows = -1;	
	}
	return $result;
}

function setGateKepper() {
	$config = MySQL_GetDocByKey("davinci_master.DV_Document",["Id"=>"3","Name"=>"GateKeeper"],"Content",true);
    $gates = $config;
    foreach ($gates as $g) { // Create the array of gates for this user in format gate_name = TRUE or gate_name = FALSE.
                
        $_SESSION['GATES'][$g['Code_Nick']] = ((stripos($g['Who_Can_See'], $email ) !== FALSE) || (stripos($g['Who_Can_See'], $accountID ) !== FALSE)||($g['Status'] == "RELEASED")) ? "true" : "false";
    }	
}            


function mapNotification($module, $body, $title) {
	
	$module = strtoupper($module);
	$body = strtoupper($body);
	$title = strtoupper($title);
	
	$NotificationImage = '';
	
	if ($body == '') {
		$content = $title;
	} else {
		$content = $body;
	}
	$severity = "Information";
	
	if ($module == 'FACEBOOK POST') {
		
	} else if ($module == 'CAMPAIGN') {
		
	} else if ($module == 'FIND COMPANY CONTACTS') {
		
	} else if ($module == 'PUBLISHMAML') {
		$severity = "Error";
	} else if ($module == 'CREATEACCOUNT') {
		$severity = "Error";
	} else if ($module == 'UPLOAD CSV') {
		if (strpos($content, 'ERROR') !== false) {
			$severity = "Error";
		} else if (strpos($content, 'BAD') !== false) {
			$severity = "Warning";
		}
	} else if ($module == ' DRIP') {
		$severity = "Warning";
	} else if ($module == 'ACTIVE DRIP') {
		
	} else if ($module == 'REMOVE DRIP') {
		
	} else if ($module == 'UNSUBSCRIBE USER') {
		if (strpos($content, 'COULD NOT') !== false) {
			$severity = "Warning";
		}
	} else if ($module == 'MYMARKETS') {	
		if (strpos($content, 'ERROR') !== false) {
			$severity = "Error";
		}
	} else if ($module == 'SYNC USERS') {	
		if (strpos($content, 'ERROR') !== false) {
			$severity = "Error";
		}
	} else if ($module == 'UPLOAD HASHTAG') {	
		if (strpos($content, 'HAS BEEN UPLOADED') !== false) {
			
		} else if (strpos($content, 'IS READY') !== false) {
			
		} else {
			$severity = "Warning";
		}
	} else if ($module == 'UPLOAD COMPANY') {	
		if (strpos($content, 'ERROR') !== false) {
			$severity = "Error";
		} else if (strpos($content, 'DUPLICATES') !== false) {
			$severity = "Warning";
			
		}
	} else if ($module == 'UPLOAD CONTACT') {	
		if (strpos($content, 'DUPLICATES') !== false) {
			$severity = "Warning";
		}
	} else if ($module == 'UPLOAD COUPONS') {	
		if (strpos($content, 'DUPLICATES') !== false) {
			$severity = "Warning";
		}
	} else if ($module == 'LINKEDIN POST') {	
		if (strpos($content, 'ERROR') !== false) {
			$severity = "Error";
		}
	} else if ($module == 'DRIP DIRECT MAIL') {	
		$severity = "Warning";
	} else if ($module == 'REMINDER') {	
		$NotificationImage = "https://s3.amazonaws.com/mindfiredavinci/reminder.png";
	} else if ($module == 'DAILY DIGEST') {	
		$NotificationImage = "https://s3.amazonaws.com/mindfiredavinci/leads.png";
	} else if ($module == 'PUBLISHMAML') {	
		$severity = "Error";
	} else if ($module == 'EMAIL SCHEDULING') {	
		$severity = "Warning";
	} else if ($module == 'FORM') {	
		
	} else if ($module == 'SETUPDV') {	
		
	}
	
	if ($NotificationImage == '') {
		$severityLower = strtolower($severity);
	    if ($severityLower == 'warning') {
	       $NotificationImage = 'https://mfdavinci.s3.us-west-1.amazonaws.com/image/warning-icon.jpg';
	    } else if ($severityLower == 'error') {
	        $NotificationImage = 'https://s3.amazonaws.com/mindfiredavinci/alarm.png';
	    } else {
	       $NotificationImage = "https://s3.amazonaws.com/mindfiredavinci/info.png";			 
	    }
	}
	
	$result = array();
	$result['severity'] = $severity;
	$result['NotificationImage'] = $NotificationImage;
	return $result;
}

function getsubSystemList() {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "SELECT DISTINCT(NotificationOriginModule) FROM DV_Notification1 ORDER BY NotificationOriginModule";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array());
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$NotificationOriginModule = $row['NotificationOriginModule'];
		$group1 = array(					
			"NotificationOriginModule"=>$NotificationOriginModule
		);
		$row2[] = $group1;
	}

	return $row2;	
}

function getBouncerAccount($acctID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$Stmt = MySQL_Prepare($db,"select * from DV_importFile where AccountID=? and bouncerSample='poorlist' order by importName");	
	$Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$id = $row['id'];
		$importName = $row['importName'];			
		$filename = $row['filename'];			
		$exportFile = $row['exportFile'];
		
		$group1 = array(					
			"id"=>$id,
			"importName"=>$importName,
			"filename"=>$filename,
			"exportFile"=>$exportFile
		);
		$row2[] = $group1;
		
	}
	
	$result['importList'] = $row2;
	
	
	return $result;
}

function getBouncerInfo($param, $logfile) {
	$accountID = $param['accountID'];
	$id = $param['id'];
	$emailTo = $param['email'];
	$accounttName = $param['accounttName'];
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	$Stmt = MySQL_Prepare($db,"select * from DV_importFile where id=? limit 1");	
	$Stmt->execute(array($id));
	$affected_rows = $Stmt->rowCount();
	$exportFile = '';
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$id = $row['id'];
		$importName = $row['importName'];			
		$filename = $row['filename'];			
		$exportFile = $row['exportFile'];
		$emailCol = $row['emailCol'];
	}
	
	$logger = new FileLogger("getBouncerInfo.log");
	$startDate = date("M d Y H:i:s");
	$logger->log(" 1 getBouncerInfo [start] : $startDate" );
	
	$logger->log(" 1 DV_importFile [exportFile] : $exportFile" );
	
	if ($exportFile == '') {		
		$logger->log(" 1 sendToUsebouncer Start" );		
		$importPath = '/efs/import/importFile/';
		$sourceFile = $importPath.$filename;
		$moreDetail['ACCOUNTID'] = $accountID;
		$moreDetail['ACCOUNNAME'] = $accounttName;
		$moreDetail['importName'] = $importName;
		
		$logger->log(" 1 sendToUsebouncer [sourceFile] : $sourceFile");
		$logger->log(" 1 sendToUsebouncer [emailCol] : $emailCol");
		
		$rows = File2CSV($sourceFile);
	    $numRow = count($rows);
	    $emailData = [];
	    for($i=1;$i<$numRow;$i++){
	        $email = $rows[$i][$emailCol];	        
	        $emailData[] = [
	            'index'=>$i,
	            'email'=>$email,
	        ];
	    }		
	    $emailSize = sizeof($emailData);
    	
    	if ( $emailSize > 0) {
    		foreach( $emailData as $row1 ) {	
				$email = $row1['email'];
				$index = $row1['index'];
				$group1 = array(					
					"email"=>$email,
					"name"=>$index			
				);	
				$group2 = array(					
					$email,$index			
				);	
				$createRequest[] = $group1;
				$createRequest2[] = $group2;
			}			
			$moreDetail['createRequest'] = $createRequest;
			$moreDetail['createRequest2'] = $createRequest2;			
			
			$logger->log(" 1 sendToUsebouncer [createRequest] : ".json_encode($createRequest) );
			$sendToResult = sendToUsebouncer($TEST, $logger, $createRequest, $importPath, $filename, $moreDetail);
			$logger->log(" 1 sendToUsebouncer [sendToResult] : ".json_encode($sendToResult) );			
    	}		
		$data = file_get_contents($sourceFile);
	
		$TDdate = new DateTime('now');
		$reportName = 'bouncer_'.$TDdate->format('mdY-His').'.csv'; 
		$reportFile = '/dvBouncer/'.$reportName;
	
		$S3File = s3_put_contents($reportFile ,$data);			
		$sql = 'update DV_importFile set exportFile=? where id=?';	
	     try {
	     	if($db == null){
				$db = GetPDOMySQL();
			}
	        $Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($S3File, $id));
	    } catch(Exception $e){
	        $ErrorMessage = $e->getMessage();
	        $logger->log(" 1 DV_importFile [ErrorMessage] : $ErrorMessage" );
	    }	    
	    $logger->log(" 1 sendToUsebouncer Finish" );	    
	} else {		
		$S3File = $exportFile;		
		$logger->log(" 1 sendToUsebouncer No" );
	}	
	$serverMode = GetCurrentConfig()->serverName;
	$cc = "DaVinci@mindfireinc.com";	
	$emailSubject = "Bouncer Result for $importName [$accounttName] [$serverMode]";
	$emailContent = "Please click on the link below to download the CSV file.\n <a href=\"$S3File\">Click</a><br>
	Account ID: $accountID<br>
	Account Name: $accounttName<br>
	List Name : $importName<br>
	File Name : $filename";
	$sentResult = sendsocketlabsEmailAddCC($emailTo, $emailSubject, $emailContent, "", $cc);	
	$result['success'] = true;
	return $result;
}

function diffRenderField($renderFieldNames,$addFields,$logger=null){
		if($logger==null){
			$logger = new FileLogger("qrcodeImgEachCron.log"); 
		}				
		$diffRender= array_udiff($addFields,$renderFieldNames,"strcasecmp");		
		$logger->log("diffRenderField :: diffRender YYYYYY =". json_encode($diffRender));			

		if(!empty($diffRender)){
			foreach($diffRender as $ufield){
				if($ufield !=""){
					array_push($renderFieldNames,$ufield);
					$logger->log("diffRenderField :: ADD Field =". $ufield);
				}
			}
		}
		return $renderFieldNames; 
}

function addNoChooseField($renderFieldNames,$qrStr,$logger=null){
		if($logger==null){
			$logger = new FileLogger("qrcodeImgEachCron.log"); 
		}
		//$pattern = "/##(.*?)##/";
		$pattern = "/##([^#]+)##/"; 
		$useFields = []; 	
		if(preg_match_all($pattern, $qrStr, $matches)){
			$useFields = $matches[1] ; 	
			$diffRender=array_udiff($useFields,$renderFieldNames,"strcasecmp");

			$logger->log("YYYYYY addNoChooseField diffRender YYYYYY =". json_encode($diffRender));			

			if(!empty($diffRender)){
				foreach($diffRender as $ufield){
					if($ufield !=""){
						array_push($renderFieldNames,$ufield);
						$logger->log("YYYYYY addNoChooseField ADD Field YYYYYY =". $ufield);
					}
				}
			}
		}
		return $renderFieldNames; 
}


function getPQRoption($logger, $PARAM, $PARAMDEF) {
	$blueprint = $PARAM["blueprint"];
	$qrclID = $PARAM["qrclID"];
	$domainURL = $PARAM["domainURL"];
	$purlMode = $PARAM["purlMode"];
	$LISTDEFINITION = '';
	$sendTo = '';
	$filter = $PARAMDEF['filter'];
	$emailTo = $PARAMDEF['emailTo'];
	$FieldNames = $PARAMDEF['FieldNames'];
	$addFields = $PARAMDEF["addFields"];
	
	$contentType = $PARAMDEF['contentType'];
	$content = $PARAMDEF['content'];
	$url = $PARAMDEF['url'];
	$urlParam = $PARAMDEF['urlParam'];
	$qrStr = $PARAMDEF['qrStr'];
	$personalized = $PARAMDEF['personalized'];
	$vpdSystem = $PARAMDEF['vpdSystem'];

	$logger->log("getPQRoption IN PARAMDEF >> ".json_encode($PARAMDEF));
	$logger->log("getPQRoption blueprint : $blueprint , qrclID : $qrclID , domainURL : $domainURL");
	$logger->log("getPQRoption IN addFields  ".json_encode($addFields));
	
	$renderFieldNames = array("purl","FirstName","LastName","Email","Phone","Address1","State","Zip","Company","Mobile");	
	
	if (($blueprint == 'PQR') || ($blueprint == 'CL2') || ($blueprint == 'CL21') || ($blueprint == 'AT2') || ($blueprint == 'AT21') || ($blueprint == 'CL30')) {

		$sql2 = "SELECT * FROM DV_QRCode_PQR where ID = '$qrclID'"; 
		$logger->log("SQL:getPQRoption :: $sql2");		  
	
		$res2 = MySQL_Select($sql2);
		$rec2 = $res2[0];
		$logger->log("rec2 = ". json_encode($rec2));
		
		$personalized = 'Yes';
		if($rec2){
			$data2 = $rec2["data"] ;
			$doc2 = json_decode($data2);
			$reportData = json_decode($doc2->reportData);
			//$sendTo = $reportData->sessEMAIL; //#2322
			$sendTo = $doc2->email;    
			$LISTDEFINITION = $doc2->LISTDEFINITION;
			$logger->log("getPQRoption LISTDEFINITION PQR =  : ".$LISTDEFINITION );
			
		}
		$contentType = 'url';
		$content = $domainURL."/##purl##";
		$url = $domainURL;
		
		$logger->log("getPQRoption IN content : ".$content );
		$logger->log("sendTo : ".$sendTo );
		
		// 1 = suffix 
		if ($purlMode == '1') {
			$content = $domainURL.'/##purl##';
			$qrStr = $content.'/QR.html';
			$logger->log("getPQRoption OUT MODE 111 content : ".$content );
			$logger->log("getPQRoption OUT MODE 111 qrStr : ".$qrStr );
			
		} else {
		    
		  $logger->log("getPQRoption IN MODE 2 content : ".$content );
			$content = $domainURL;
			$content = str_ireplace("https://","https://##purl##.",$content);
			$content = str_ireplace("http://","http://##purl##.",$content);
			$qrStr = $content.'/QR.html';
			
			$logger->log("getPQRoption OUT MODE 222 content : ".$content );
			$logger->log("getPQRoption OUT MODE 222 qrStr : ".$qrStr );
		}
		if ($urlParam == '')  {
		
		} else {
			$qrStr = $qrStr.'?'.$urlParam;
		}		
		$renderFieldNames = array("purl","FirstName","LastName","Email","Phone","Address1","State","Zip","Company","Mobile","Gender","promoCode");
	}
	
	$logger->log("getPQRoption OUT  content : ".$content );
	$logger->log("getPQRoption OUT  qrStr : ".$qrStr );
	$logger->log("getPQRoption OUT  sendTo : ".$sendTo );
	

	if(empty($addFields)) { 
		$addFields = array("purl");
		$logger->log("Set def addFields : ".json_encode($addFields));
	}	
	
	if(!empty($FieldNames))  $renderFieldNames = $FieldNames;
	
 	$logger->log("getPQRoption OUT addFields : ".json_encode($addFields));
 	$logger->log("getPQRoption OUT filter : ".$filter);
 	$logger->log("getPQRoption OUT LISTDEFINITION : ".$LISTDEFINITION);
	
	if ($LISTDEFINITION == '') {  
		$LISTDEFINITION = $filter; 				
	}
    
	if ($sendTo == '') {
		$sendTo = $emailTo; 				
	}
	$result['LISTDEFINITION'] = $LISTDEFINITION;
	$result['sendTo'] = $sendTo;
	$result['contentType'] = $contentType;
	$result['content'] = $content;
	$result['url'] = $url;
	$result['qrStr'] = $qrStr;
	$result['addFields'] = $addFields;
	$result['personalized'] = $personalized;
	$result['FieldNames'] = $FieldNames;
	$result['renderFieldNames'] = $renderFieldNames;
	return $result;
}

function sendQRListContacts($PARAM){
    $logger = new FileLogger("sendQRListContacts.log"); 		
	$success = false;
	$QRID = $PARAM['QRID']; 
	$ACCOUNTID = $PARAM['ACCOUNTID']; 
	$EMAIL = $PARAM['EMAIL']; 
	$PWD = $PARAM['PWD']; 
	$PARTNERGUID = $PARAM['PARTNERGUID']; 
	$PARTNERPASSWORD = $PARAM['PARTNERPASSWORD']; 	
	$ACCOUNTNAME = $PARAM["ACCOUNTNAME"]; 
	$Credentials = $PARAM["LOGINCREDENTIALS"]; 
	$QRResultID = $PARAM["QRResultID"]; 
	$QRReportSuccess = $PARAM["QRReportSuccess"];
	$campaignListname = $PARAM["campaignListname"];
	
	$sql = "SELECT * FROM DV_QRCode where acctID = $ACCOUNTID and ID = '$QRID'"; 
	$logger->log("  sendQRListContacts - SQL: $sql");		  	
	$res = MySQL_Select($sql);
	logDebug("sendQRListContacts () res = ". json_encode($res));		

	$rec = $res[0]; 
	if($rec){
		$QRNAME = $rec["name"] ;
		$data = $rec["data"] ;
		$doc = json_decode($data);
		logDebug(" ---- doc = ". json_encode($doc));			
		if($doc!=[]){
			$vpdSystem = $doc->vpdSystem ; 			
			$docName = $doc->qrName ; 
			$personalized = $doc->personalized; 
			$FieldNames = $doc->FieldNames ; 
			$addFields = $doc->addFields ; 
			$vcardList = $doc->vcardList ; 	
			$listName = $doc->listName ;
			
			$PARAMDEF['filter'] = $doc->filter;
			$PARAMDEF['emailTo'] = $doc->email;
			$PARAMDEF['contentType'] = $doc->contentType;
			$PARAMDEF['content'] = $doc->content;
			$PARAMDEF['url'] = $doc->url;
			$PARAMDEF['qrStr'] = $doc->qrStr;
			$PARAMDEF['urlParam'] = $doc->urlParam;
			$PARAMDEF['addFields'] = $doc->addFields;
			$PARAMDEF['personalized'] = $doc->personalized;
			$PARAMDEF['vpdSystem'] = $vpdSystem;
			$PARAMDEF['FieldNames'] = $FieldNames;
			$PQRoption = getPQRoption($logger, $PARAM, $PARAMDEF);
			
			$logger->log("sendQRListContacts :: RES PQRoption = ". json_encode($PQRoption));				
			
			$LISTDEFINITION = $PQRoption['LISTDEFINITION'];
			$email = $PQRoption['sendTo'];
			if ($email == '') {
				$email = $EMAIL;
				$logger->log("email = '' so set email =". $EMAIL);
			}
			$contentType = $PQRoption['contentType'];
			$contentText = $PQRoption['content'];
			$domain = $PQRoption['url'];
			$qrStr = $PQRoption['qrStr'];
			$addFields = $PQRoption['addFields'];
			$personalized = $PQRoption['personalized'];
			$FieldNames = $PQRoption['FieldNames'];
			$renderFieldNames = $PQRoption['renderFieldNames'];

			$logger->log("LISTDEFINITION=". $LISTDEFINITION);				
			$logger->log("FieldNames=". json_encode($FieldNames));				
			$logger->log("renderFieldNames=". json_encode($renderFieldNames));				
			$logger->log("qq001 addFields=". json_encode($addFields));
			logDebug("Credentials =". json_encode($Credentials) );
			
			if($contentType == "vcard" && !empty($vcardList)){
					$renderFieldNames = $vcardList; 			
			}
			if (!in_array("purl", $renderFieldNames) && !in_array("PURL", $renderFieldNames) ){
				logDebug("UNSHIFT purl");
				array_unshift($renderFieldNames,"purl");  //push first 
			}
	
			$logger->log("  sendQRListContacts :: AAAAA addFields =". json_encode($addFields));				
			$renderFieldNames = diffRenderField($renderFieldNames,$addFields,$logger);  
			$logger->log("  sendQRListContacts :: RENDER Field = ". json_encode($renderFieldNames));

			$noticeType = 'QR Code Email' ; 
			$noticeMessage = "Your QR codes [qrcodeID:$QRID] were successfully generated and sent to your email."; 

			if($vpdSystem){      // gen excel with qrstring				
				$moreData['qrEmailType'] = "QR String";	

				$addInfo = []; 
				$addInfo['contentType'] = $contentType; 
				$addInfo['personalized'] = $personalized; 				
				$addInfo['url'] = $domain; 
				$addInfo['contentText'] = $contentText; 
				
				$logger->log("sendQRListContacts :: AddInfo = " .json_encode($addInfo) ); 
				
				$logger->log("------------- CALL [genExcelQRString] FROM [sendQRListContacts] ----------"); 
				$genResult = genExcelQRString($ACCOUNTID,$QRID,$Credentials,$LISTDEFINITION,$qrStr,$FieldNames,$renderFieldNames,1,$addInfo,$logger); 
				$logger->log("genExcelQRString (option1) = ". json_encode($genResult));				

				if($genResult["success"]){			
					$logger->log("  QRStringExcel SUCCESS >> SERVER >> fileDestination=". $genResult["fileDestination"]."-- accDir=". $genResult["tmpName"] . " -- totalExcelCnt=" . $genResult["totalCount"]);			

					if($genResult["fileDestination"]){
						$s3Result = uploadQRTOS3($genResult["fileDestination"],$genResult["tmpName"]); 
						$logger->log("  uploadQRTOS3 () - s3Result = ".json_encode($s3Result)); 								

						if($s3Result["success"]){
							deleteQrcodeTmpFile($s3Result['fileDestination']); 

							$PARAM["excelS3url"] = $s3Result["S3url"];	
							$moreData['s3ExcelURL'] = $s3Result["S3url"];	
							$logger->log("  SEND EMAIL to $email link >> ". $s3Result["S3url"]);	
							
							$logger->log("  #2345 docName : $docName");
							
							if ($campaignListname == '') {
							
								$toListnames = getListName($ACCOUNTID, $listName);
								$toListname = $toListnames['allList'];
								if ($toListname == '') {
									$toListname = $campaignListname;
								}
							} else {
								$toListname = $campaignListname;
							}
							 								
							$logger->log("  #2345 toListname : $toListname");
							
							$userInfo = getDVUserinfo($ACCOUNTID);
							$acctname = $userInfo['studioAccountName'];

							$subject = "Your QR Code Export for account [$acctname].";
							$content = "Hi there:<br><br>" ; 
							//$content .= "Your QR code exports are ready.<br><br>";
							$content .= "Your QR code exports are ready for QR Code [$docName] using the following lists:<br>";
							$content .= "$toListname<br><br>";

							$content .= "Please click <a href='".$s3Result["S3url"]."'>here</a> to download the data file.<br><br>"; 
							$content .= "Please note that these files will remain available for up to 30 days.<br>"; 	
							$content .= "Please email us at support@mindfireinc.com if you have any questions.<br>"; 
							$content .= "MindFire Product Team<br>"; 
							
							$email = $EMAIL;
							
							$emailRet = sendsocketlabsEmail($email, $subject, $content, '');
							$PARAM['toemail'] = $email; 
							$logger->log("  MAIL TO = ( $email ) , subject = $subject ");
							$logger->log("  Send Mail Result = " . json_encode($emailRet));
							
							$eventData['emailSent'] = $emailRet['success'];
							$eventData['emailMsg'] = $emailRet['message'];
							$moreData['emailSent'] = $emailRet['success'];
							$moreData['emailMsg'] = $emailRet['message'];
							$success = true; 		


							$PARAM["totalExcelCount"] = $genResult["totalCount"]; 

							$logger->log("******* UPDATE QR STRING success[$QRReportSuccess]***** QRResultID = " . $QRResultID);
							$PARAM["zipDir"] = ""; 
							if($QRReportSuccess){
								$updRet = updateQRReport($QRResultID,$PARAM); 
								$eventData['QRReportSuccess'] = $updRet['success'];
								$eventData['QRReportSent'] = $updRet['affected_rows'];
								$moreData['QRReportSuccess'] = $updRet['success'];
								$moreData['QRReportSent'] = $updRet['affected_rows'];
							}
						}
					}else{
						$logger->log(' EMPTY - genResult["fileDestination"]' ); 
					}
				}else{
					$success = false; 
					$errorMsg = "ERROR::QRStringExcel >>SERVER>> fileDestination=".$genResult["fileDestination"]."/ tmpName=".$genResult["tmpName"]; 
					$logger->log($errorMsg);
					$moreData['QRStringExcelError'] = $errorMsg; 

					$noticeMessage = "We had an error in sending your email [qrcodeID : $QRID]. Please see your email for the error message. Please email us at support@mindfireinc.ocm if you have questions.";
				}
				
				$moreData['success'] = $success;			
				logDebug(" moreData = [". json_encode($moreData)."]" ); 		
				$NotificationResult = saveNotification1('', $email, $ACCOUNTID, $noticeType, "" , $noticeMessage, '', "" , $moreData);
				$NotificationID = $NotificationResult['NotificationID'];
				$eventData['NotificationID'] = $NotificationID; 

			}else{  
					$logger->log("------------ Move to run in qrcodeImgCron.log -------------");

			}
			$eventData['success'] = $success;
			$eventData['errorMsg'] = $errorMsg;
		}else{			
			logDebug("+++ERROR :: No qr.data");	
			$errorMsg = "ERROR :: No qr.data DV_QRCode.ID=[$QRID]"; 
			$success = false; 
			$eventData['success'] = $success;
			$eventData['errorMsg'] = $errorMsg;
		}
	}
	$activityDetail['userName'] = $email;
	$activityDetail['eventName'] = $noticeType;
	$activityDetail['accountID'] = $ACCOUNTID;	
	$activityDetail['accountName'] = $ACCOUNTNAME; 
	$activityDetail['contentID'] = $QRID;
	$activityDetail['contentName'] = $QRNAME;
	$activityDetail['eventData'] = $eventData;
	$saveResult = SaveSiteActivity($activityDetail);
	$logger->log("============= END == SaveSiteActivity[QR Code Email]- $QRID =========="); 
	return array(
		"success"=>$success,
		"message"=>$errorMsg,
		"NotificationID"=>$NotificationID,
		"logDebug"=>logDebug(),
	);
}
function deleteQrcodeTmpFile($path){	
	$logger = new FileLogger("sendQRListContacts.log"); 	
	if (substr($path,0,19) != '/efs/import/qrcode/'){
		$logger->log("ERROR::deleteQrcodeTmpFile ($path) ----- CANNOT DELETE File !!!!! "); 
		return; 
	}
	$logger->log("Do::deleteQrcodeTmpFile () - $path"); 
	unlink($path);
}
function deleteQrcodeDirectory($dir){
	$logger = new FileLogger("sendQRListContacts.log"); 
	logDebug("  ===START deleteQrcodeDirectory () > $dir"); 
	logDebug("  substr dir = ".  substr($dir, 0, 19)); 
	if (substr($dir,0,19) != '/efs/import/qrcode/'){
		$logger->log("  ERROR::deleteQrcodeDirectory ($dir) ----- CANNOT DELETE Directory !!!!! "); 
		return; 
	}
	
	$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it,RecursiveIteratorIterator::CHILD_FIRST);
/*
	if( empty((array)$files) ){
		$logger->log("  ERROR::deleteQrcodeDirectory ()  RecursiveIteratorIterator-file is empty "); 
	}
*/ 
	logDebug("deleteQrcodeDirectory files = ". json_encode($files)); 
	foreach($files as $file) {
		//logDebug("deleteQrcodeDirectory file = ". $file); 
		if ($file->isDir()){
			rmdir($file->getRealPath());
		} else {
			unlink($file->getRealPath());
		}
	}
	rmdir($dir);
	logDebug("  ===END deleteQrcodeDirectory () > $dir"); 
}
function updateQRReport($ID, $PARAM,$data=[],$logger=null){	
	if($logger==null){
		$logger = new FileLogger("sendQRListContacts.log"); 
	}	
	$toemail = $PARAM["toemail"]; 
	$excelS3url = $PARAM["excelS3url"]; 
	$zipDir = $PARAM["zipDir"]; 	
	$totalExcel = $PARAM["totalExcelCount"]; 
	
	$blueprint = $PARAM["blueprint"]; 
	$qrclID = $PARAM["qrclID"]; 
	
	try {
		$db = GetPDOMySQL();
		if ($blueprint == '') {
			$sql = 'UPDATE DV_QRCode_PQR set contactCount=? where ID=? ';
			if($qrclID){				
				try {
					$Stmt1 = MySQL_Prepare($db,$sql);					
					$logger->log("updateQRReport:: PQR SQL: $sql");
					$logger->log("updateQRReport:: contactcount = $totalExcel, ID = $qrclID");
					$Stmt1->execute(array($totalExcel, $qrclID));		
				} catch(Exception $e){
					$success = false;	
					$ErrorMessage = $e->getMessage();
				}
			}else{
				$logger->log("updateQRReport:: - NO qrclID !!!!! CANNOT >> " . $sql);
			}			
		}		
		
		$sql = "UPDATE DV_QRCodeReport SET zipDir='".$zipDir."', email=QRCodeApproval, count='".$totalExcel."', excelS3url='".$excelS3url."', status='sent', sentDate=Now(), data=? where ID=?";  
		$logger->log("updateQRReport:: QRCode REPORT SQL = $sql");
		$logger->log("updateQRReport:: ID = $ID , data = ". json_encode($data));
		$db = GetPDOMySQL();
		$updStmt = MySQL_Prepare($db,$sql);
		$updStmt->execute(array(json_encode($data), $ID));
		$affected_rows = $updStmt->rowCount();
		if($affected_rows > 0){
				$success = true;	
		}

	} catch(Exception $e){
		$success = false;	
		$ErrorMessage = $e->getMessage();
	}
	return array('success'=>$success ,'affected_rows' => $affected_rows,'logDebug'=>logDebug()) ;
}

function addQRReport($acctID, $QRID, $listname, $qrType, $count,$email,$data,$logger=null){	
	if($logger==null){
		$logger = new FileLogger("sendQRListContacts.log"); 
	}	
	$success = false;	
	$logger->log("addQRReport:: data is array = " . is_array($data));
	try {
		$db = GetPDOMySQL();
		$sql = "INSERT INTO DV_QRCodeReport(acctID,qrID,listname,qrType,count,data,createDate,QRCodeApproval,email) VALUES (?, ?, ?, ?, ?, ?,Now(),?,?)";
		logDebug("addQRReport:: SQL = $sql");
		logDebug("addQRReport:: $acctID / QRID = $QRID / listname = $listname / qrType = $qrType / count = $count / email = $email / data.size = ".sizeof($data));
		$db = GetPDOMySQL();
		$insertStmt = MySQL_Prepare($db,$sql);
		$insertStmt->execute(array($acctID, $QRID, $listname, $qrType, $count, json_encode($data), $email, $email));
		$affected_rows = $insertStmt->rowCount();
		$numInsert = $db->lastInsertId(); 				
		$success = true;	

	} catch(Exception $e){
		$success = false;	
		$ErrorMessage = $e->getMessage();
		logDebug("addQRReport:: ERROR >> $ErrorMessage");
	}
	return array('success'=>$success ,'numInsert' => $numInsert,'logDebug'=>logDebug()) ;
}

function zipFolder($zipPath,$accDir,$zipDir){
	$logger = new FileLogger("sendQRListContacts.log"); 
	// Get real path for our folder	
	$rootPath = $zipPath;    //$rootPath = realpath($zipPath);
	$logger->log("  zipFolder ():: rootPath = " . $rootPath);
	$zipFilename = $accDir.'/'. $zipDir .'.zip'; 

	// Initialize archive object
	$zip = new ZipArchive();
	$zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
	
	$filesToDelete = array();    // Initialize empty "delete list"
	
	// Create recursive directory iterator  	/** @var SplFileInfo[] $files */
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath)) as $key=>$file)	{		
		if (!$file->isDir()){
			// Get real and relative path for current file
			$filePath = $file->getRealPath();
			$relativePath = substr($filePath, strlen($rootPath) + 1);
			
			// Add current file to archive
			$zip->addFile($filePath, $relativePath);

			//Add currentfile to "delete list"/delete it later cause ZipArchive create archive only after calling close function and ZipArchive lock files until archive created)
			//if ($file->getFilename() != 'important.txt'){
				//$filesToDelete[] = $filePath;
				//$logger->log(" filesToDelete = " . json_encode($filesToDelete) ) ;
			//}
		}
	}
	// Zip archive will be created only after closing object
	$zip->close();	

	/*
	// Delete all files from "delete list"
	foreach ($filesToDelete as $file){
		unlink($file);
		logDebug("unlink(". $file.")") ;
	}
	*/ 
	return array('success'=>true, 'errorMessage' => $errorMessage, 'zipFilename'=>$zipFilename);	
}

function getQRImgConfig() {	
	$maxSize = 500;
	$minSize = 10;
	$limit = 5; 
	$delay = 1; 
	$result = []; 
	$sql = "select * from DV_Config where configName='qrImg' limit 1"; 
	$Rows = MySQL_Select($sql);
	if(count($Rows)>0){		
		$docData = json_decode($Rows[0]['configData'],true);
		return $docData;
		//$maxSize = (int)$docData["maxSize"] ; 
	}	
	// case no value; 
	$result['maxSize'] = $maxSize;
	$result['minSize'] = $minSize;
	$result['delay'] = $delay;
	return $result;
}

function genQRImgDummyComplete($ACCOUNTID,$zipDir){
		$sql = 	"UPDATE DV_QRCodeDummy SET completeTime = Now() where zipDir='".$zipDir."' and acctID= '".$ACCOUNTID."'" ; 		
		logDebug("genQRImgDummyComplete sql = " . $sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute();	
		$affected_rows = $Stmt->rowCount();
		if($affected_rows > 0){
			//$logger->log("DV_QRCodeDummy set completeTime - zipDir[$zipDir] ");
			return true; 
		}
		return false; 
}

function genQRImgDummyS3Link($ACCOUNTID,$zipDir,$idx,$s3link){
		$sql = 	"UPDATE DV_QRCodeDummy SET s3ZipLink = '".$s3link."' where zipDir='".$zipDir."' and acctID= '".$ACCOUNTID."' and idx=$idx" ; 		
		logDebug("genQRImgDummyS3Link sql = " . $sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute();	
		$affected_rows = $Stmt->rowCount();
		if($affected_rows > 0){
			//$logger->log("DV_QRCodeDummy set s3ZipLink - $zipDir[$s3link] ");
			return true; 
		}
		return false; 
}

function insertQRImgDummy($ACCOUNTID,$QRID,$zipDir,$i,$total,$maindata,$paramdata){ 	
	$logger = new FileLogger("qrcodeImgCron.log"); 
	//$logger->log("CALL insertQRImgDummy  ------------------------------------- " . $maindata); 
	try {  		
		$db = GetPDOMySQL();
		$sql = 	"INSERT INTO DV_QRCodeDummy (zipDir, idx, total, acctID, qrID, maindata,param) VALUES ('".$zipDir."'," . $i ."," .$total."," . $ACCOUNTID.",'" . $QRID."', ? , ?)" ; 			
		$logger->log("[insertQRImgDummy] sql = " . $sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute(array($maindata,$paramdata));	
		$affected_rows = $Stmt->rowCount();			
		$logger->log("[insertQRImgDummy] - INSERT DV_QRCodeDummy res=$affected_rows | zipDir=$zipDir - [$i]/[$total]- $ACCOUNTID / $QRID");
		return true; 

	}catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		$logger->log("ERROR : [insertQRImgDummy] ($ACCOUNTID/$QRID/$zipDir/$i/$total) sql=$sql:  ERROR=" . $e);
	}
	return false; 
}

function updateTriggerQRDummy($ACCOUNTID,$zipDir,$i,$cnt){ 	
	$logger = new FileLogger("qrcodeImgEachCron.log"); 
	try {  		
		$db = GetPDOMySQL();	
		$sql = 	"UPDATE DV_QRCodeDummy SET triggerTime = Now(), triggerCount = $cnt where zipDir='".$zipDir."' and idx=$i and acctID= '".$ACCOUNTID."'" ; 
		$logger->log("updateTriggerQRDummy SQL:".$sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute();	
		$affected_rows = $Stmt->rowCount();		
		//$logger->log("updateTriggerQRDummy RESULT:".$affected_rows);

	}catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		$logger->log("ERROR : updateTriggerQRDummy ( $ACCOUNTID / $zipDir / $i) sql=$sql:  ERROR=" . $e);
	}
	return false; 
}

function genQRImgDummy($ACCOUNTID,$QRID,$zipDir,$i,$total=0,$docdata=[]){
	$logger = new FileLogger("qrcodeImgCron.log"); 
	$data = json_encode($docdata); 
	//logDebug("genQRImgDummy - $ACCOUNTID / $QRID / $zipDir / $i / $total / $data ");
	try {  
		$db = GetPDOMySQL();	
		$sql = 	"UPDATE DV_QRCodeDummy SET eachDoneTime = Now(), data = ? where zipDir='".$zipDir."' and idx=$i and acctID= '".$ACCOUNTID."'" ; 		
		$logger->log("SSSSSSSSSS $zipDir --1/4  genQRImgDummy SQL:".$sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute(array($data));	
		$affected_rows = $Stmt->rowCount();		
		//$logger->log("genQRImgDummy RESULT:".$affected_rows);
		
		return true; 		
	}catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		$logger->log("SSSSSSSSSS $zipDir --1/4 **** ERROR : genQRImgDummy ($ACCOUNTID/$QRID/$zipDir/$i/$total) sql=$sql:  ERROR=" . $e);
	}
	return false; 
}
			

/*
function setRoundEachDummy($zipDir,$idx,$ACCOUNTID){
	$logger = new FileLogger("qrcodeImgEachCron.log"); 	
	logDebug("setRoundEachDummy - $zipDir / $idx / $ACCOUNTID ");
	try {  
		$db = GetPDOMySQL();	
		$sql = "UPDATE DV_QRCodeDummy SET recovery = (recovery+1) where zipDir='".$zipDir."' and idx=$idx and acctID= '".$ACCOUNTID."'" ; 		
		$logger->log("setRoundEachDummy SQL:" . $sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute();	
		$affected_rows = $Stmt->rowCount();
		//$logger->log("setRoundEachDummy RESULT:".$affected_rows);		
		return true; 		
	}catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		$logger->log("ERROR : setRoundEachDummy ($ACCOUNTID/$QRID/$zipDir/$idx) SQL:$sql > ERROR=" . $e);
	}
	return false; 
}
*/ 
function setEachCronDummy($zipDir,$idx,$ACCOUNTID){
	$logger = new FileLogger("qrcodeImgEachCron.log"); 	
	logDebug("setEachCronDummy - $zipDir / $idx / $ACCOUNTID ");
	try {  
		$db = GetPDOMySQL();	
		$sql = "UPDATE DV_QRCodeDummy SET eachDoneTime = '0000-00-00 00:00:00', createDate=Now() where zipDir='".$zipDir."' and idx=$idx and acctID= '".$ACCOUNTID."'" ; 		
		$logger->log("setEachCronDummy SQL:" . $sql ); 
		$Stmt = MySQL_Prepare($db,$sql);
		$Stmt->execute();	
		$affected_rows = $Stmt->rowCount();
		//$logger->log("setEachCronDummy RESULT:".$affected_rows);
		
		return true; 		
	}catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		$logger->log("ERROR : setEachCronDummy ($ACCOUNTID/$QRID/$zipDir/$idx) SQL:$sql > ERROR=" . $e);
	}
	return false; 
}

function doQRExcelToS3($PARAM,$path,$accDir,$zipDir){  
		$logger = new FileLogger("qrcodeImgCron.log"); 			
		$success = false; 
		$QRID = $PARAM['QRID']; 
		$ACCOUNTID = $PARAM['ACCOUNTID']; 
		$Credentials = $PARAM["LOGINCREDENTIALS"]; 
		
		$blueprint = $PARAM["blueprint"];
		$qrclID = $PARAM["qrclID"];
		
		$logger->log("doQRExcelToS3 start PARAM : ".json_encode($PARAM));
		$logger->log("blueprint : $blueprint , qrclID : $qrclID"); 

		//$sql = "SELECT * FROM DV_QRCode where acctID = $ACCOUNTID and ID = '$QRID'"; 
		$sql = "SELECT * FROM DV_QRCodeDummy where acctID = $ACCOUNTID and zipDir = '$zipDir'"; 
		$res = MySQL_Select($sql);
		
		$logger->log("SQL doQRExcelToS3 = : $sql");		  		
		$logger->log("res doQRExcelToS3 = " . json_encode($res));	
		

    		$rec = $res[0]; 
    		$logger->log("each rec doQRExcelToS3 = " . json_encode($rec));	
    		if($rec){
    		    
				$eachTimeData = $rec["data"] ;   // for each data of each time generate. #2322
	    		$logger->log("eachTimeData = " . json_encode($eachTimeData));	
    			$eachdoc = json_decode($eachTimeData,true);
				$logger->log("  PARAMDEF eachdoc = ". json_encode($eachdoc) );		
    			if($eachdoc!=[]){
					$FieldNames = $eachdoc['FieldNames']; 
					$PARAMDEF['FieldNames'] = $FieldNames;
					$PARAMDEF['filter'] = $eachdoc['LISTDEFINITION']; 
					$PARAMDEF['emailTo'] = $eachdoc['EMAIL']; 
    			}
    			
    			$data = $rec["maindata"] ;
    			$doc = json_decode($data);
    			if($doc!=[]){
    				$vpdSystem = $doc->vpdSystem ; 	
    				$docName = $doc->qrName ; 
    				$vcardList = $doc->vcardList ; 
    				$personalized = $doc->personalized; 
    				$imgformat = $doc->imgFormat; 	
    				$addFields = $doc->addFields ;
    				
    				$PARAMDEF['contentType'] = $doc->contentType;
    				$PARAMDEF['content'] = $doc->content;
    				$PARAMDEF['url'] = $doc->url;
    				$PARAMDEF['qrStr'] = $doc->qrStr;
    				$PARAMDEF['urlParam'] = $doc->urlParam;
    				$PARAMDEF['addFields'] = $doc->addFields;
    				$PARAMDEF['personalized'] = $doc->personalized;
    				$PARAMDEF['vpdSystem'] = $vpdSystem;
    				
    				if(empty($PARAMDEF['FieldNames'])){
    				    $PARAMDEF['FieldNames'] = $doc->FieldNames ; 
    				}
    				if(empty($PARAMDEF['filter'])){
    				    $PARAMDEF['filter'] = $doc->filter ; 
    				}
    				if(empty($PARAMDEF['emailTo'])){
    				    $PARAMDEF['emailTo'] = $doc->email ; 
    				}
    				
    // 				$FieldNames = $doc->FieldNames ; 
				//  	$PARAMDEF['FieldNames'] = $FieldNames;
				//  	$PARAMDEF['filter'] = $doc->filter ; 
				//  	$PARAMDEF['emailTo'] = $doc->email ; 
				
    				
    				$PQRoption = getPQRoption($logger, $PARAM, $PARAMDEF);
    				
    				$logger->log("  doQRExcelToS3 :: PQRoption = ". json_encode($PQRoption));				
    				
    				$LISTDEFINITION = $PQRoption['LISTDEFINITION'];
    				$email = $PQRoption['sendTo'];
    				$contentType = $PQRoption['contentType'];
    				$contentText = $PQRoption['content']; //#2271
    				$domain = $PQRoption['url']; //#2271
    				$qrStr = $PQRoption['qrStr']; //#2271
    				$addFields = $PQRoption['addFields']; //#2271
    				$personalized = $PQRoption['personalized']; //#2271
    				$FieldNames = $PQRoption['FieldNames']; //#2271
    				$renderFieldNames = $PQRoption['renderFieldNames']; //#2271
    				
    				$logger->log("  qqq002 :: FieldNames =". json_encode($FieldNames));
    				$logger->log("  qqq002 :: addFields =". json_encode($addFields));
    				if($contentType == "vcard" && !empty($vcardList)){
						$renderFieldNames = $vcardList;		
						$logger->log("  [doQRExcelToS3]:: VCARD >>> FieldNames VS vcardList  =". json_encode($vcardList));	
				        $renderFieldNames = diffRenderField($renderFieldNames,$FieldNames,$logger);  //#2322 diff vcard with fieldnames >> add to renderFieldname
    				}
    				if (!in_array("purl", $renderFieldNames) && !in_array("PURL", $renderFieldNames) ){
    					array_unshift($renderFieldNames,"purl");  //push first 
    				}
    				
    				$logger->log("  doQRExcelToS3 :: AAAAA addFields =". json_encode($addFields));				
    				$renderFieldNames = diffRenderField($renderFieldNames,$addFields,$logger);  
    
    				$logger->log("  doQRExcelToS3 :: RENDER Field = ". json_encode($renderFieldNames));
    				$addInfo = []; 
    				$addInfo['imgformat'] = $imgformat; 
    				$addInfo['contentType'] = $contentType; 
    				$addInfo['personalized'] = $personalized; 
    				$addInfo['url'] = $domain; 
    				$addInfo['contentText'] = $contentText; 
    				
    				$logger->log("doQRExcelToS3 :: AddInfo = " .json_encode($addInfo) ); 
    				
    				$logger->log("------------- CALL [genExcelQRString] FROM [doQRExcelToS3] qrcodeImgCron.log----------"); 
    				$logger->log("LISTDEFINITION : $LISTDEFINITION"); 
    				$genExcelResult = genExcelQRString($ACCOUNTID,$QRID,$Credentials,$LISTDEFINITION,$qrStr,$FieldNames,$renderFieldNames,2,$addInfo,$logger);  
    				$logger->log("  genExcelQRString RESULT = ". json_encode($genExcelResult));
    				
    				
    				if($genExcelResult["fileDestination"]){					
    					
    					if (($blueprint == 'PQR') || ($blueprint == 'CL2') || ($blueprint == 'CL21') || ($blueprint == 'AT2') || ($blueprint == 'AT21') || ($blueprint == 'CL30')) {
    						$sql = 'UPDATE DV_QRCode_PQR set contactCount=? where ID=? ';
    						try {
    							$Stmt1 = MySQL_Prepare($db,$sql);
    							
    							$contactCount = $genExcelResult['totalCount'];
    						
    							$logger->log("UPDATE DV_QRCode_PQR : $sql");
    							$logger->log($contactCount,$qrclID);
    							$Stmt1->execute(array($contactCount, $qrclID));		
    						} catch(Exception $e){
    							$success = false;	
    							$ErrorMessage = $e->getMessage();
    						}
    					}
    					
    					$s3ExcelResult = uploadQRTOS3($genExcelResult["fileDestination"],$genExcelResult["tmpName"]); 
    					$logger->log("  s3ExcelResult = ".json_encode($s3ExcelResult)); 																						
    					deleteQrcodeTmpFile($s3ExcelResult['fileDestination']); 
    					$logger->log("  Delete Temp -- EXCEL = ". $s3ExcelResult['fileDestination']); 		
    					$success  = true; 
    				}				
    			}
    		}	    
		return array('success'=>$success, 'genExcelResult'=>$genExcelResult, 's3ExcelResult'=>$s3ExcelResult, 'toemail'=>$email, "LISTDEFINITION"=>$LISTDEFINITION);
}

function getQRCodeApproval($QRResultID){
    $loggerCron = new FileLogger("qrcodeImgCron.log"); 		
    $success = false;	
    try {
		$db = GetPDOMySQL();
		$sql = "SELECT * From DV_QRCodeReport where ID='".$QRResultID."'"; 
		$loggerCron->log("SQL:: getQRCodeApproval () = $sql");
		$db = GetPDOMySQL();
		$Rows = MySQL_Select($sql,$db);	
		
		$loggerCron->log("RES:: getQRCodeApproval () = ". json_encode($Rows));
		$listname = '';
		$qrName = '';
		if(!empty($Rows)){	
		    foreach($Rows as $Row){
		    	$acctID = $Row["acctID"];
		    	$qrID = $Row["qrID"];
				$QRCodeApproval = $Row["QRCodeApproval"];
				$listname = $Row["listname"];
				
				$sql = "SELECT * FROM DV_QRCode where acctID = $acctID and ID = '$qrID'"; 
				//$logger->log("  sendQRListContacts - SQL: $sql");		  
				
				$res = MySQL_Select($sql);
				$rec = $res[0]; 
				if($rec){
					$qrName = $rec["name"] ;
				}
				
				
				$success = true;	
			}
		}
	} catch(Exception $e){
		$ErrorMessage = $e->getMessage();
		$loggerCron->log("ERROR:: getQRCodeApproval () = ". $ErrorMessage);
		
	}
	return array(
		'success'=>$success ,
		'QRCodeApproval' => $QRCodeApproval,
		'listname' => $listname,
		'qrName' => $qrName,
		'ErrorMessage' => $ErrorMessage, 
		'logDebug'=>logDebug()
	) ;
}

function doQRExcelS3AndSendEmail($PARAM, $path, $accDir, $zipDir){   // doQRExcelToS3 > send email	
		$loggerCron = new FileLogger("qrcodeImgCron.log"); 		
		$QRID = $PARAM["QRID"] ; 
		$QRResultID = $PARAM["QRResultID"]; 
		$QRReportSuccess = $PARAM["QRReportSuccess"]; 	
		$ACCOUNTID = $PARAM["ACCOUNTID"]; 
		$ACCOUNTNAME = $PARAM["ACCOUNTNAME"]; 
		$toemail = $PARAM["EMAIL"];
		$EMAIL = $PARAM["EMAIL"];
		
		$loggerCron->log("+++++++X doQRExcelS3AndSendEmail == START  == PARAM >> " .json_encode($PARAM)); 
		$success = false; 		
		$noticeType = 'QR Code Email' ; 			
		$noticeMessage = "Your QR codes [qrcodeID:$QRID] were successfully generated and sent to your email."; 

		$moreData['qrEmailType'] = "QR Image";	  			
		$eventData['accDir'] = $accDir ; 
		$eventData['zipDir'] = $zipDir ;	 
		
		
		$zipEmailContent = ""; 
		$sql = "SELECT * FROM DV_QRCodeDummy t1 WHERE t1.zipDir = '".$zipDir."' and t1.acctID = '".$ACCOUNTID."' order BY t1.idx asc"; 
		$loggerCron->log("doQRExcelS3AndSendEmail :: SQL  $sql");   
		
		$db = GetPDOMySQL();
		$Rows = MySQL_Select($sql,$db);		
		$s3link = []; 
		
		$loggerCron->log("doQRExcelS3AndSendEmail :: Result Rows = " . json_encode($Rows));   
		
		if(count($Rows)>1){		
			$zipEmailContent = "Your QR code images have been split into multiple zip files for ease of download.<br>" ;
			$zipEmailContent .= "Here are the links to those zip files:<br>"; 
			foreach($Rows as $Row){
				$idx = (int)$Row["idx"];
				$S3url = $Row["s3ZipLink"];
				if($S3url){
					$zipEmailContent .= "Click <a href='".$S3url."'>Link ".$idx."</a> to download a zip file containing QR code images.<br>"; 
				}else{
					$zipEmailContent .= "No <a href='".$S3url."'>Link ".$idx."</a> to download a zip file containing QR code images.<br>"; 
				}
				$s3link[$idx] = $S3url; 				
			}

		}else if(count($Rows)==1){
				$zipEmailContent = "Your QR code images have been combined into a zip file for ease of download.<br>" ;
				$zipEmailContent .= "Here is the link to that zip files<br>"; 
				
				foreach($Rows as $Row){
					$idx = $Row["idx"];
					$S3url = $Row["s3ZipLink"];
					$s3link[1] = $S3url ; 
				}
				if($S3url){
					$zipEmailContent .= "Click <a href='".$S3url."'>Link</a> to download a zip file containing QR code images.<br>"; 				
				}else{
					$zipEmailContent .= "No <a href='".$S3url."'>Link</a> to download a zip file containing QR code images.<br>"; 				
				}
		}
	
		//2 doExcelToS3
		$loggerCron->log("LOGDATETIME start - doQRExcelToS3 [$zipDir] at " .date('m.d.Y h:i:s'));   
		$doExcelResult = doQRExcelToS3($PARAM,$path,$accDir,$zipDir); 			
		$loggerCron->log("LOGDATETIME end - doQRExcelToS3 [$zipDir] at " .date('m.d.Y h:i:s'));   
		
		$loggerCron->log("SSSSSSSSSS $zipDir --2/4  doQRExcelToS3 Result = ". json_encode($doExcelResult));
		$genExcelResult = $doExcelResult["genExcelResult"]; 				
		
		$QRNAME = $doExcelResult["QRNAME"]; 	
		$moreData['genExcelSuccess'] = $genExcelResult["success"];
		$eventData['genExcelSuccess'] = $genExcelResult["success"];			
		$eventData['excelFileDes'] = $genExcelResult["fileDestination"];
		$eventData['excelName'] = $genExcelResult["tmpName"];
		
		$s3ExcelResult = $doExcelResult["s3ExcelResult"]; 	

		$PARAM["excelS3url"] = $s3ExcelResult["S3url"];
 		$eventData['s3ExcelURL'] = $s3ExcelResult["S3url"];
		$eventData['S3ZipURL'] = json_encode($s3link);
		$moreData['s3ExcelURL'] = $s3ExcelResult["S3url"];
		$moreData['S3ZipURL'] = json_encode($s3link);
		
		
		$userInfo = getDVUserinfo($ACCOUNTID);
		$acctname = $userInfo['studioAccountName'];
		
		$eRet = getQRCodeApproval($QRResultID); 
		if($eRet['success'] = true){
		     $toemail = $eRet['QRCodeApproval'];
		     $qrName = $eRet['qrName'];
		     $toListname = $eRet['listname'];
		     $loggerCron->log("*** email get QRCodeApproval QQQQ toemail = " . $toemail." , qrName = ".$qrName." , toListname = ".$toListname);
		}
		
		//3. Sent mail				
		$notificationLink = $s3ExcelResult["S3url"]; 
		$subject = "Your QR Code Export for account [$acctname].";
		$content = "Hi there:<br><br>" ; 		
		$content .= "Your QR code exports are ready for QR Code [$qrName] using the following lists:<br>";
		$content .= "$toListname<br><br>"; 

		$content .= "Please click <a href='".$s3ExcelResult["S3url"]."'>here</a> to download the data file.<br>"; 
		$content .= $zipEmailContent . "<br><br>"; 
		$content .= "Please note that these files will remain available for up to 30 days.<br>"; 
		$content .= "Please email us at support@mindfireinc.com if you have any questions.<br>"; 
		$content .= "MindFire Product Team<br>"; 
		
		$toemail = $EMAIL;
		
		if ($toemail == '') {
			$sql = "SELECT * From DV_QRCodeReport where ID='".$QRResultID."'";
			//$logger->log("  sendQRListContacts - SQL: $sql");		  
			
			$res = MySQL_Select($sql);
			$rec = $res[0]; 
			if($rec){
				$toemail = $rec["email"] ;
			}
		}
		

		$loggerCron->log("doQRExcelS3AndSendEmail :: toemail = " . $toemail);   
		
		$cc = "davincimonitoring@gmail.com"; 
		$emailRet = sendsocketlabsEmailAddCC($toemail, $subject, $content, '', $cc);
		$PARAM["toemail"] = $toemail; 
		$loggerCron->log("  SEND MAIL TO = **** $toemail **** , subject = $subject | Excel S3link = ". $s3ExcelResult["S3url"] );
		$loggerCron->log("  Send Mail Result = " . json_encode($emailRet));

		$eventData['emailSent'] = $emailRet['success'];
		$eventData['emailMsg'] = $emailRet['message'];									

		$moreData['emailSent'] = $emailRet['success'];
		$moreData['emailMsg'] = $emailRet['message'];		

		
		$PARAM["zipDir"] = $zipDir; 
		$reData = []; 
		$reData["zipS3"] = $s3link; 
		$reData["LISTDEFINITION"] = $doExcelResult["LISTDEFINITION"] ; 	

		$PARAM["totalExcelCount"] = $genExcelResult["totalCount"]; 

		$loggerCron->log("----- updateQRReport [$QRID] success=$QRReportSuccess | QRResultID = " . $QRResultID );
		if($QRReportSuccess){			
			$updRet = updateQRReport($QRResultID,$PARAM,$reData); 
			$eventData['QRReportSuccess'] = $updRet['success'];
			$eventData['QRReportSent'] = $updRet['affected_rows'];

			$moreData['QRReportSuccess'] = $updRet['success'];
			$moreData['QRReportSent'] = $updRet['affected_rows'];
			$moreData['success'] = true;			
		}
		$success = true; 		
		$loggerCron->log("SSSSSSSSSS $zipDir --3/4 Do Dummy Complete  -- $ACCOUNTID " ); 					
		$completeRes = genQRImgDummyComplete($ACCOUNTID,$zipDir); 
		$eventData['qrDummyComplete'] = $completeRes; 

		logDebug("CALL saveNotification1 = $toemail \  $ACCOUNTID \  $noticeType \ $noticeMessage \  $notificationLink"); 		
		$NotificationResult = saveNotification1('', $toemail, $ACCOUNTID, $noticeType, "" , $noticeMessage, '', $notificationLink, $moreData);
		$loggerCron->log("NotificationResult = [". json_encode($NotificationResult)."]" ); 					

		$NotificationID = $NotificationResult['NotificationID'];
		$eventData['NotificationID'] = $NotificationID; 
		$eventData['success'] = $success;
		$eventData['errorMsg'] = $errorMsg;

		$activityDetail['userName'] = $toemail;
		$activityDetail['eventName'] = $noticeType ;
		$activityDetail['accountID'] = $ACCOUNTID;	
		$activityDetail['accountName'] = $ACCOUNTNAME; 
		$activityDetail['contentID'] = $QRID;	
		$activityDetail['contentName'] = $QRNAME;
		$activityDetail['eventData'] = $eventData;
		$saveResult = SaveSiteActivity($activityDetail);
		$loggerCron->log("+++++++ doQRExcelS3AndSendEmail == END == SaveSiteActivity[QR Code Email]- $QRID =========="); 

		return array("success"=>$success,"message"=>$errorMsg,"NotificationID"=>$NotificationID,"logDebug"=>logDebug());		
}

function callCSVProcessorCurl($id) {
		$logger = new FileLogger("qrcodeProcessor.log");    
    $url = "https://8mdmei6e83.execute-api.us-west-1.amazonaws.com/test/api/csvprocessor";
    $url .= "?cmd=chainExecute&id=" . urlencode($id);
		$logger->log("callCSVProcessorCurl:: id = $id , url " . $url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,   
        CURLOPT_HEADER => true,           
        CURLOPT_HTTPHEADER => [
            "origin: http://localhost",
            "Authorization: bearer XYZ"
        ]
    ]);
    $response = curl_exec($ch);
		//$logger->log("callCSVProcessorCurl:: result = " . print_r($response, true));
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
				$logger->log("callCSVProcessorCurl:: error = " . $error);
				return array(
					"success" => false,					
					"error" => $error
				);	
        //throw new Exception("cURL Error: " . $error);
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

		$result = array("success" => true,"status" => $statusCode,"response" => $response);
		$logger->log("callCSVProcessorCurl:: RESULT = " . json_encode($result));
		return $result ; 
}

function DoQRCodeProcExcel($QRoption){   //Use instead of doQRExcelToS3 ()  //FFFFF
   $logger = new FileLogger("qrcodeProcessor.log"); 			
   $logger->log("DoQRCodeProcExcel:: - START PQRoption PARAM =". json_encode($QRoption));      		
   $success = false;       
   try{
      $LISTDEFINITION = $QRoption['LISTDEFINITION'];
      $email = $QRoption['sendTo'];
      $contentType = $QRoption['contentType'];
      $contentText = $QRoption['content']; 
      $domain = $QRoption['url']; 
      $qrStr = $QRoption['qrStr'];  //#2271
      $addFields = $QRoption['addFields']; 
      $personalized = $QRoption['personalized']; 
      $FieldNames = $QRoption['FieldNames'];
      $renderFieldNames = $QRoption['renderFieldNames'];                 
      $vcardList = $QRoption["vcardList"];
      $imgformat = $QRoption["imgFormat"];

      $logger->log("DoQRCodeProcExcel:: FieldNames =". json_encode($FieldNames));

      if($contentType == "vcard" && !empty($vcardList)){
         $renderFieldNames = $vcardList;		
         $logger->log("DoQRCodeProcExcel:: VCARD >> vcardList = ". json_encode($vcardList));	
         $renderFieldNames = diffRenderField($renderFieldNames,$FieldNames,$logger);  //#2322 diff vcard with fieldnames >> add to renderFieldname
      }
      if (!in_array("purl", $renderFieldNames) && !in_array("PURL", $renderFieldNames) ){
         array_unshift($renderFieldNames,"purl");  //push first 
      }
      
      $logger->log("DoQRCodeProcExcel:: addFields =". json_encode($addFields));				
      $renderFieldNames = diffRenderField($renderFieldNames,$addFields,$logger);  

      $logger->log("DoQRCodeProcExcel:: RENDER Field = ". json_encode($renderFieldNames));
      $addInfo = []; 
      $addInfo['imgformat'] = $imgformat; 
      $addInfo['contentType'] = $contentType; 
      $addInfo['personalized'] = $personalized; 
      $addInfo['url'] = $domain; 
      $addInfo['contentText'] = $contentText; 
      
      $logger->log("DoQRCodeProcExcel:: AddInfo = " .json_encode($addInfo) ); 

      $QRID = $QRoption['QRID']; 
      $ACCOUNTID = $QRoption['ACCOUNTID']; 
      $Credentials = $QRoption["LOGINCREDENTIALS"]; 					      

      $genExcelResult = genExcelQRString($ACCOUNTID,$QRID,$Credentials,$LISTDEFINITION,$qrStr,$FieldNames,$renderFieldNames,2,$addInfo,$logger);  
      $logger->log("DoQRCodeProcExcel:: CSVPROC genExcelQRString RESULT = ". json_encode($genExcelResult));
      $contactCount = $genExcelResult['totalCount']; 
      

      if($genExcelResult["fileDestination"]){

				$blueprint = $QRoption["blueprint"];			
				$qrclID = $QRoption["qrclID"] ?? "";
				if (!empty($qrclID)) {
					if (($blueprint == 'PQR') || ($blueprint == 'CL2') || ($blueprint == 'CL21') || ($blueprint == 'AT2') || ($blueprint == 'AT21') || ($blueprint == 'CL30')) {

					    $sql = 'UPDATE DV_QRCode_PQR set contactCount=? where ID=? ';
					    try {
					       $Stmt1 = MySQL_Prepare($db,$sql);                       
					       $logger->log("DoQRCodeProcExcel:: qrclID = $qrclID - UPDATE DV_QRCode_PQR : $sql");
					       $logger->log($contactCount,$qrclID);
					       $Stmt1->execute(array($contactCount, $qrclID));		
					    } catch(Exception $ee){					       
								 $logger->log("DoQRCodeProcExcel::ERROR UPDATE DV_QRCode_PQR >> " . $ee->getMessage());
					    }
					}
				}
				
				$zip = new ZipArchive();					
				$csvName = $genExcelResult["tmpName"];   	//	$tmpName = $QRID.'_'.$t.'.csv';						
				$csvFile = $genExcelResult["fileDestination"];  // $fileDestination = /efs/import/qrcode/".$ACCOUNTID .'/'.$tmpName;								
				$pieces = explode(".", $csvFile);
				if(count($pieces)<2){
					$ErrorMsg = "Invalid tmpName file";
					$logger->log("DoZip:: ERROR csvFile = $csvFile , pieces = ". json_encode($pieces));
					$zipFile = $csvFile . ".zip";					
				}else{
					$zipFile = $pieces[0] . ".zip";					
				}										
				if(file_exists($zipFile)) {
					$logger->log("DoZip:: Delete Exist zipfile > ". $zipFile);
					unlink ($zipFile); 
				}				
				if ($zip->open($zipFile, ZIPARCHIVE::CREATE) != TRUE) {					
					$logger->log("DoZip:: ERROR Could not open archive > ". $zipFile);

				} else {															
					$zip->addFile($csvFile,$csvName);															
				}				
				$zip->close(); 
				
				$allQRUploadDes = ["zip" => $zipFile, "csv" => $genExcelResult["fileDestination"]];
				$allQRS3Result = uploadAll_QR_S3($allQRUploadDes);
				$logger->log("UPLOAD ALL QR to S3 = ". json_encode($allQRS3Result)); 
				if($allQRS3Result['result']){					
					if(isset($allQRS3Result['result']['zip']) && $allQRS3Result['result']['zip']!=""){
						$s3ZipUrl = $allQRS3Result['result']['zip'];					
						unlink($zipFile);
					}
					if(isset($allQRS3Result['result']['csv']) && $allQRS3Result['result']['csv']!=""){
						$s3CsvUrl = $allQRS3Result['result']['csv'];					
						unlink($csvFile);
					}					
				}
				$QRoption["excelS3url"] = $s3CsvUrl ?? "";
				$QRoption["zipS3url"] = $s3ZipUrl ?? "";
				$QRoption["totalCount"] = $contactCount; 
				$logger->log("DoQRCodeProcExcel:: QRoption = ". json_encode($QRoption)); 	
				$success = AddCSVProcessorDB($QRoption); 
				$logger->log("DoQRCodeProcExcel:: AddCSVProcessorDB = " . ($success ? "true" : "false") ); 	       				
      }				
   } catch (Exception $e) {
			$ErrorMsg = $e->getMessage(); 
      $logger->log("DoQRCodeProcExcel::ERROR >> " . $ErrorMsg);			
   }
	 return array('success'=>$success, "ErrorMsg"=>$ErrorMsg, 'toemail'=>$email, 's3ExcelResult'=>$s3ExcelResult, 'genExcelResult'=>$genExcelResult);   
}

function uploadAll_QR_S3($fileDestinations){	//zip and csv
	$logger = new FileLogger("qrcodeProcessor.log"); 	
	$result = []; 
	foreach($fileDestinations as $key => $fileDestination){  // $fileDestinations = ["zip" => "/var/log/aaa.zip", "csv" => "/var/log/aaa.csv"];
		$value = file_get_contents($fileDestination);
    if(empty($value)){
        $logger->log("uploadAll_QR_S3 () ERROR:: *****EMPTY FILE***** " . $fileDestination); 			
				$result[$key] = "";
    } else {	
        $tmpName   = basename($fileDestination);			
        $s3fileDes = '/qrcode/'.$tmpName;
        $S3url     = s3_put_contents($s3fileDes, $value);				
        $logger->log("uploadAll_QR_S3 () PUT-S3 S3url = " . $S3url); 
        $result[$key] = $S3url;
    }
	}	
	return array('success'=>true, 'result'=>$result );
}


function GenCSVProcessorData($QRoption){     //FFFFF 
  //$qrstring = "BEGIN:VCARD\nVERSION:2.1\nN:##lastname##;##firstname##;\nFN:##firstname## ##lastname##,#website##\nEMAIL;PREF;INTERNET:##email##\nEND:VCARD";
	$logger = new FileLogger("qrcodeProcessor.log");    
  $myOption = $QRoption['myOption'] ?? [];   
	$defaultOption = [
    "margin" => "0",
    "color" => [
        "dark" => "#000000FF",
        "light" => "#F1F1F1FF",
        "lefttop" => "#000000FF",
        "righttop" => "#000000FF",
        "leftbottom" => "#000000FF",
        "svgLogo" => "#000000FF",
        "frame" => "#000000FF",
        "frameText" => "#FFFFFFFF"
    ],
    "cellMode" => "square",
    "width" => 100,
    "frame" => "",
    "frameText" => "Hello World",
    "svgLogo" => "",
    "logo" => ""
	];
	$finalOption = !empty($myOption) ? $myOption : $defaultOption;  
   
  $qrstring = $QRoption["qrStr"]; 
  $qrstring = preg_replace('/##(.*?)##/', '{$1}', $qrstring);     	//replace ##field## to {$field} for eval

	$imageType = $QRoption['imageFormat'] ?? 'jpg';
	$imgSize = $QRoption['imgSize'] ?? 100;
	$logoData = $finalOption['logo'] ?? "";	 
  $logoName = $QRoption['filename'] ?? '' ;   
  $chkRoundEdge = $QRoption['roundEdge'] ?? false ;   
  $chkGrayScale = $QRoption['grayscale'] ?? false ;    
  $qrDetail = [
      "name" => "qrstr",
      "value" => $qrstring,
      "ignoreEmpty" => true,
      "err" => "",
      "defaultOption" => [
        "margin" => "0",
        "color" => [
               "dark" => $finalOption["color"]["dark"],
               "light" => $finalOption["color"]["light"],
               "lefttop" => $finalOption["color"]["lefttop"],
               "righttop" => $finalOption["color"]["righttop"],
               "leftbottom" => $finalOption["color"]["leftbottom"],
               "svgLogo" => "#000000FF",
               "frame" => "#000000FF",
               "frameText" => "#FFFFFFFF"
        ],
        "cellMode" => $finalOption["cellMode"],
        "width" => $imgSize,
        "frame" => "",
        "frameText" => "Hello World",
        "svgLogo" => "",
        "marker" => [
               "lefttop" => "version2.1",
               "righttop" => "version2.2",
               "leftbottom" => "version2.4"
        ],
        "logo" => $logoData,         
			],
			"chkGenImage" => true,
      "chkRoundEdge" => $chkRoundEdge,
      "chkGrayScale" => $chkGrayScale,
      "logoLocalFileName" => $logoName,
      "imageFormat" => $imageType,
      "imageColumn" => "QR Code Image File",
      "chkCreateNewColumn" => true,
      "chkEvalMode" => false,
      "columnName" => ""
  ];
	$logger->log("GenCSVProcessorData:: qrDetail = ". json_encode($qrDetail)); 	
  $result = [
						["process" => "QRCode","detail" => json_encode($qrDetail, JSON_UNESCAPED_UNICODE),"enable" => true],
      			["process" => "SendEmail","detail" => json_encode(["email" => $QRoption["AdminEmail"]] , JSON_UNESCAPED_UNICODE),"enable" => true]
					];
  return $result ; 
}

function AddCSVProcessorDB($QRoption){    //insert Nextgen, insert QrcodeProc  //FFFFF
   $logger = new FileLogger("qrcodeProcessor.log");    
   try {
      $aID = $QRoption['ACCOUNTID'];       
			$excelS3url = $QRoption["excelS3url"]; 			
			$zipS3url = $QRoption["zipS3url"];
			$totalCount = $QRoption["totalCount"]; 			
      //Gen data          
      $jres = GenCSVProcessorData($QRoption); 
			$processData = json_encode($jres, JSON_UNESCAPED_UNICODE);			
			$logger->log("AddCSVProcessorDB:: GenCSVProcessorData processData = " . $processData);			
			$commerceStage = GetCurrentConfig()->commerceStage; 
			$domain = "https://mf1-desktopcsvprocessor-stg.s3.us-west-1.amazonaws.com";
			if($commerceStage == "prod"){				
				$domain = "https://mf1-desktopcsvprocessor.s3.us-west-1.amazonaws.com";
			}

			$pdata = ["source" => basename($excelS3url),
								"domain" => $domain,
								"user" => $QRoption["AdminEmail"],
								"delimiter" => ",",
								"fileExtension" => ".csv", 
								"exportAsCSV" => 0,  
								"tabDelimiter" => 0,																
								"qrcodeTotal" => $totalCount,								
								"qrStart" => 1756806292157,								
								"qrTime" => 524992,
								"correctedHeader" => null]; 

			$propdata = json_encode($pdata, JSON_UNESCAPED_UNICODE);      
      //Add CSVProc
      $procName = "DVQRCODE_".$QRoption['QRResultID']; 			//reportID
      $sql = "INSERT INTO nextgen.CSVProcStatus (`accountID`, `name`, `url`, `data`, `prop`, `status`, `result`) VALUES (?,?,?,?,?,?,?)"; 
      $logger->log("AddCSVProcessorDB:: SQL = " . $sql);
			$logger->log("AddCSVProcessorDB:: insert 	$aID / $procName / $zipS3url");	
      
			$db = GetPDOMySQL();
			$insertStmt = MySQL_Prepare($db,$sql);
			$insertStmt->execute(array($aID,$procName,$zipS3url,$processData,$propdata,"W","{}"));   //FFXXXX check cron insert duplicate ****** cron = key before add at FronT 				
			$affected_rows = $insertStmt->rowCount();				
			$insertID = $db->lastInsertId();        
			$logger->log("AddCSVProcessorDB::inserted successfully >> process ID = $insertID");

			// Run chainExecute 
			if($insertID){
				$res = callCSVProcessorCurl($insertID);         
				$logger->log("DONE callCSVProcessorCurl:: res = " . json_encode($res));
				$reportID = $QRoption["QRResultID"] ?? ""; 

				//ADD DV_QRCodeProcessor
				$sql2 = "INSERT INTO DV_QRCodeProcessor (`acctID`,`csvprocID`,`reportID`,`sent`,`data`) VALUES (?,?,?,?,?)"; 			
				$logger->log("AddQRCodeProcessorDB:: SQL = " . $sql2);			
				$insertStmt2 = MySQL_Prepare($db,$sql2);      //FFXXXX check cron insert duplicate >> cron = key before add at FronT 
				$insertStmt2->execute(array($aID,$insertID,$reportID,"wait",json_encode($QRoption)));
				$affected_rows2 = $insertStmt2->rowCount();				
				
				$logger->log("AddQRCodeProcessorDB:: INSERT DV_QRCodeProcessor affected_rows2 = " . $affected_rows2 );  
				$logger->log("reportID = ". $reportID . ", insertID = $insertID " );  
				return true; 
			}  				
   } catch (Exception $ex) {      
      $logger->log("AddCSVProcessorDB::ERROR >> " . $ex->getMessage());			
   }
	 return false ; 
}

function DoQRProcessorCron(){   //instead of doQrcodeImgCron + doQRExcelS3AndSendEmail
	$logger = new FileLogger("qrcodeProcessorCron.log");    	// insert DV_QRCodeProcessor  //  sent = waiting  // csvprocID // data //qrreportID
	try{				
		$success = false;		
		$sql = "SELECT * FROM DV_QRCodeProcessor where sent='wait' order by ID asc limit 10";   //`acctID`,`csvprocID`,`reportID`,`sent`,`data`
		$logger->log("START Cron == DoQRProcessorCron:: SQL = " . $sql);			
		$db = GetPDOMySQL();
		$Rows = MySQL_Select($sql,$db);		
		$logger->log("Cron Rows = " . count($Rows));	 

		//check row exist
		if(count($Rows)==0){
			$success = true; 
			$ErrorMessage = "NO Queue" ; 	
			$logger->log(" == END == Cron == :: NO Queue ");

		}else{			
			foreach($Rows as $procRow){	
				$procID = $procRow['ID'];
				$csvprocID = $procRow['csvprocID']; 	
				$procqrdata = json_decode($procRow['data'],true);

				$logger->log(" == START == procID[$procID] / csvprocID[$csvprocID] ");	 

				$sql2 = "SELECT * FROM nextgen.CSVProcStatus where ID=$csvprocID and status='E'"; 
				$logger->log("CSVProcStatus SQL::" . $sql2);
				$csvProcRow = MySQL_Select($sql2,$db);			

				$logger->log("CSVProcStatus Result::" . json_encode($csvProcRow));			

				if(!empty($csvProcRow)){
					$url = $csvProcRow[0]['url'];  //csv link
					$qrcodeResult = $csvProcRow[0]['result']; //qrcode link	
					$zipS3 = [];

					$logger->log("xxxxxxxxxxxxx qrcodeResult ::" . json_encode($qrcodeResult));		

					if($qrcodeResult){
						$qrcodeResult = json_decode($qrcodeResult,true);				
						$s3link = $qrcodeResult['qrcode'] ?? []; 
						
						if(count($s3link)>1){
							$zipEmailContent = "Your QR code images have been split into multiple zip files for ease of download.<br>" ;
							$zipEmailContent .= "Here are the links to those zip files:<br>"; 
							$idx = 1; 
							foreach($s3link as $S3url){
								if($S3url){
									$zipEmailContent .= "Click <a href='".$S3url."'>Link ".$idx."</a> to download a zip file containing QR code images.<br>"; 
								}else{
									$zipEmailContent .= "No <a href='".$S3url."'>Link ".$idx."</a> to download a zip file containing QR code images.<br>"; 
								}
								$s3link[$idx] = $S3url; 	
								$zipS3[$idx] = $S3url;
								$idx++; 
							}
						}else if(count($s3link)==1){
							$zipEmailContent = "Your QR code images have been combined into a zip file for ease of download.<br>" ;
							$zipEmailContent .= "Here is the link to that zip files<br>"; 
							
							$S3url = $s3link[0];
							$s3link[1] = $S3url ; 
							if($S3url){
								$zipEmailContent .= "Click <a href='".$S3url."'>Link</a> to download a zip file containing QR code images.<br>"; 				
							}else{
								$zipEmailContent .= "No <a href='".$S3url."'>Link</a> to download a zip file containing QR code images.<br>"; 				
							}
							$zipS3[1] = $S3url;
						}
					}		

					$QRName = $procqrdata['qrName'] ?? '';
					$excelS3url = $procqrdata['excelS3url'] ?? '';
					$sessemail = $procqrdata['sessEMAIL'] ?? '';

					$ACCOUNTID = $procRow['acctID'];
					$userInfo = getDVUserinfo($ACCOUNTID);
					$acctname = $userInfo['studioAccountName'];

					$toemail = $sessemail;			
					$logger->log("xxxxxxxxxxxxx toemail ::" . $toemail);	
					
					if($toemail == ''){

						$logger->log("xxxxxxxxxxxxx procRow['reportID'] ::" . $procRow['reportID']);	

						$reportID = $procRow['reportID'];
						$eRet = getQRCodeApproval($reportID); 
						if($eRet['success'] = true){
							$toemail = $eRet['email'];				
						}
					}			
					$zipDir = "QRProcessorID_".$procID; 	
					$moreData['qrEmailType'] = "QR Image";	  			
					//$eventData['accDir'] = $accDir ; 
					$eventData['zipDir'] = $zipDir ;
					if($excelS3url){
						$moreData['genExcelSuccess'] = true;	
						$moreData['s3ExcelURL'] = $excelS3url; 
						$moreData['s3ZipURL'] = $zipS3url; 

						$eventData['genExcelSuccess'] = true;							
						$eventData['s3ExcelURL'] = $excelS3url; 
						$eventData['s3ZipURL'] = $zipS3url; 
					}			
					$eventData['S3ZipURL'] = json_encode($zipS3);			
					$moreData['S3ZipURL'] = json_encode($zipS3);
					
					$subject = "Your QR Code Export for account [$acctname].";
					$content = "Hi there:<br><br>" ;			
					$content .= "Your QR code exports are ready for QR Code [$QRName].<br><br>";
					$content .= "Please click <a href='".$excelS3url."'>here</a> to download the data file.<br>"; 
					$content .= $zipEmailContent . "<br><br>"; 
					$content .= "Please note that these files will remain available for	up to 30 days.<br>"; 
					$content .= "Please email us at support@mindfireinc.com if you have any questions.<br>"; 
					$content .= "MindFire Product Team<br>"; 			

					$cc = "davincimonitoring@gmail.com"; 
					$emailRet = sendsocketlabsEmailAddCC($toemail, $subject, $content, '', $cc);			
					$logger->log("  SEND MAIL TO = **** $toemail **** , subject = $subject | Excel S3link = ". $excelS3url );
					$logger->log("  Send Mail Result = " . json_encode($emailRet));					
					
					$moreData['emailSent'] = $emailRet['success'];
					$moreData['emailMsg'] = $emailRet['message'];						
					$eventData['emailSent'] = $emailRet['success'];
					$eventData['emailMsg'] = $emailRet['message'];	

					$listDEFINITION = $procqrdata['LISTDEFINITION'] ?? '';
					$reData = []; 
					$reData["zipS3"] = $zipS3; 
					$reData["LISTDEFINITION"] = $listDEFINITION ;

					$QRResultID = $procRow['reportID'];
					$reportPARAM["toemail"] = $toemail; 
					$reportPARAM["excelS3url"] = $excelS3url; 
					$reportPARAM["totalExcelCount"] = $procqrdata['totalCount'] ?? 0; 
					$reportPARAM["zipDir"] = $zipDir;
					$reportPARAM["blueprint"] = $procqrdata['blueprint'] ?? ''; 
					$reportPARAM["qrclID"] = $procqrdata['qrclID'] ?? ''; 

					$updRet = updateQRReport($QRResultID,$reportPARAM,$reData,$logger); 
					$moreData['QRReportSuccess'] = $updRet['success'];
					$moreData['QRReportSent'] = $updRet['affected_rows'];
					$moreData['success'] = true;					
					$eventData['QRReportSuccess'] = $updRet['success'];
					$eventData['QRReportSent'] = $updRet['affected_rows'];		
					
					try{
						$sql3 = "UPDATE DV_QRCodeProcessor set sent='sent' where ID=?";				
						$updateStmt = MySQL_Prepare($db,$sql3);
						$updateStmt->execute(array($procID)); 
						$affected_rows3 = $updateStmt->rowCount();				
						$logger->log("Update DV_QRCodeProcessor $sql3   >> result = " . $affected_rows3 );
						$eventData['qrProcessorComplete'] = true; 
					}catch(Exception $e){
						$ErrorMessage = $e->getMessage();
						$logger->log("Update DV_QRCodeProcessor::ERROR >> " . $ErrorMessage);			
						$eventData['qrProcessorComplete'] = false; 
					}	

					$QRID = $procqrdata['QRID'] ?? '';
					$noticeType = 'QR Code Email' ; 
					$noticeMessage = "Your QR codes [qrcodeID:$QRID] were successfully generated and sent to your email."; 
					$notificationLink = $excelS3url; 
					
					$NotificationResult = saveNotification1('', $toemail, $ACCOUNTID, $noticeType, "" , $noticeMessage, '', $notificationLink, $moreData);
					$logger->log("Save NotificationResult = [". json_encode($NotificationResult)."]" ); 					

					$NotificationID = $NotificationResult['NotificationID'];
					$eventData['NotificationID'] = $NotificationID; 
					$eventData['success'] = $NotificationResult['notificationSuccess'];    	
					$eventData['errorMsg'] = $NotificationResult['notificationerrorMessageDB'];

					$activityDetail['userName'] = $toemail;
					$activityDetail['eventName'] = $noticeType ;
					$activityDetail['accountID'] = $ACCOUNTID;	
					$activityDetail['accountName'] = $ACCOUNTNAME; 
					$activityDetail['contentID'] = $QRID;	
					$activityDetail['contentName'] = $QRNAME;
					$activityDetail['eventData'] = $eventData;
					$saveResult = SaveSiteActivity($activityDetail);
					$logger->log(" == END == procID[$procID] DoQRProcessorCron == SaveSiteActivity[QR Code Email] =========="); 				

				} //end if(!empty($csvProcRow))					
			}//foreach(procRow)	
			$success = true;			
			$ErrorMessage = "Processed " . count($Rows) . " queue(s)" ;
		}
		
	} catch (Exception $ex) {
			$success = false;			
      $ErrorMessage = $ex->getMessage();
      $logger->log("AddQRCodeProcessorDB::ERROR = " . $ErrorMessage);      			
  }
	return array('success'=>$success, 'error' => $ErrorMessage); 
}

function addQRCodeProcessor($PARAM){  //Use instead of genQRImageMain (insertDummy)  //FFFFF
	$logger = new FileLogger("qrcodeProcessor.log"); 
	$success = false; 	
	$QRID = $PARAM['QRID']; 
	$ACCOUNTID = $PARAM['ACCOUNTID']; 
  $blueprint = $PARAM["blueprint"]; 		

	$logger->log("START ------------- addQRCodeProcessor IN PARAM = " . json_encode($PARAM));
	$db = GetPDOMySQL();
	$sql = "SELECT * FROM DV_QRCode where acctID = $ACCOUNTID and ID = '$QRID'"; 
	$res = MySQL_Select($sql);
	$rec = $res[0]; 	
	if($rec){
		$mdata = $rec["data"] ;			

		$qrcode_data = json_decode($mdata, true);
		$PARAMDEF['filter'] = $qrcode_data['filter'] ?? "";
		$PARAMDEF['emailTo'] = $qrcode_data['email'] ?? "";      

		if (in_array($blueprint, ['PQR', 'CL2', 'CL21', 'AT2', 'AT21', 'CL30'])) {                 
         $PARAMDEF['contentType'] = $qrcode_data['contentType'] ?? "";
         $PARAMDEF['content'] = $qrcode_data['content'] ?? "";
         $PARAMDEF['url'] = $qrcode_data['url'] ?? "";
         $PARAMDEF['qrStr'] = $qrcode_data['qrStr'] ?? "";
         $PARAMDEF['urlParam'] = $qrcode_data['urlParam'] ?? "";
         $PARAMDEF['addFields'] = $qrcode_data['addFields'] ?? "";
         $PARAMDEF['personalized'] = $qrcode_data['personalized'] ?? "";
         $PARAMDEF['vpdSystem'] = $qrcode_data['vpdSystem'] ?? "";
         $PARAMDEF['FieldNames'] = $qrcode_data['FieldNames'] ?? "";

		} else {                 
			if(empty($LISTDEFINITION)){
				$LISTDEFINITION = $qrcode_data['filter'] ?? "";
			}
		}
		$logger->log("addQRCodeProcessor:: qrcode_data = ". json_encode($qrcode_data));

		$PARAM['qrName'] = $qrcode_data['qrName'] ?? "";		
		$PARAM['vcardList'] = $qrcode_data['vcardList'] ?? "";
		
		$PQRoption = getPQRoption($logger, $PARAM, $PARAMDEF);  
		$logger->log("addQRCodeProcessor:: PQRoption = ". json_encode($PQRoption));		
		
		if($blueprint == ""){
			$QRoption = $qrcode_data + $PQRoption; 
		}else{
			$QRoption = $PQRoption + $qrcode_data; 			
		}				
		//ADD PARAM to QRoption
		$QRoption["QRID"] = $QRID; 		
		$QRoption["sessEMAIL"] = $PARAM['EMAIL']; 
		$QRoption["ACCOUNTID"] = $ACCOUNTID; 
		$QRoption["blueprint"] = $blueprint;  
		$QRoption["LOGINCREDENTIALS"] = $PARAM["LOGINCREDENTIALS"]; 
		$QRoption["QRReportSuccess"] = $PARAM['QRReportSuccess']; 		
		$QRoption["QRResultID"] = $PARAM['QRResultID']; 		 // QRCodeReport.ID
		$QRoption["AdminEmail"] = "aumpai@mindfiremail.info"; // davinci@mindfireinc.com   //FFXXXX

		$doResult = DoQRCodeProcExcel($QRoption);   // DO Excel > insert CSVProc  
		$logger->log("doResult = " . json_encode($doResult)); 		
		if(isset($doResult["success"]) && $doResult["success"]){  
			$success = true; 
			$logger->log("Done -------------- DoQRCodeProcExcel waiting CRON (reportID = ".$PARAM['QRResultID'].") success = $success"); 			
		}else{
			if(isset($doResult["ErrorMsg"])){  
				$errorMsg = $doResult["ErrorMsg"]; 		
			}
		}
	}
	return array(
		"success"=>$success,
		"message"=>$errorMsg,	
		"logDebug"=>logDebug()
	);	
}

function genQRImageMain($PARAM){
	$logger = new FileLogger("qrcodeImgCron.log"); 
	$success = false; 
	$qrImgSuccess = false; 

	$QRID = $PARAM['QRID']; 
	$ACCOUNTID = $PARAM['ACCOUNTID']; 
	$Credentials = $PARAM["LOGINCREDENTIALS"]; 
	$LISTDEFINITION = $PARAM["LISTDEFINITION"]; 	
	$blueprint = $PARAM["blueprint"]; 	

	$qrconfig = getQRImgConfig();
	$maxRows = (int)$qrconfig['maxSize'];
	$minRows = (int)$qrconfig['minSize'];
	$PARAM["maxRows"] = $maxRows; 
	$PARAM["minRows"] = $minRows; 
	
	$logger->log("genQRImageMain IN PARAM = " . json_encode($PARAM));
	$db = GetPDOMySQL();
	$sql = "SELECT * FROM DV_QRCode where acctID = $ACCOUNTID and ID = '$QRID'"; 
	$res = MySQL_Select($sql);
	$rec = $res[0]; 	
	if($rec){
		$maindata = $rec["data"] ;		
		$doc = json_decode($maindata);   
		if (($blueprint == 'PQR') || ($blueprint == 'CL2') || ($blueprint == 'CL21') || ($blueprint == 'AT2') || ($blueprint == 'AT21') || ($blueprint == 'CL30')) {
			$PARAMDEF['filter'] = $doc->filter;
			$PARAMDEF['emailTo'] = $doc->email;
			$PQRoption = getPQRoption($logger, $PARAM, $PARAMDEF);
			$logger->log("genQRImageMain :: RES PQRoption = ". json_encode($PQRoption));				
			
			$LISTDEFINITION = $PQRoption['LISTDEFINITION'];
			$email = $PQRoption['sendTo'];
		} else {
			if(empty($LISTDEFINITION)){
				$LISTDEFINITION = $doc->filter;
			}
		}

		$logger->log(" maxRows[$maxRows] / $ACCOUNTID /  $LISTDEFINITION  / Credentials = " . json_encode($Credentials));
		$count = GetContactCountCredentials($TEST, $Credentials, $LISTDEFINITION, $ACCOUNTID);   
		$PARAM["totalcnt"] = $count; 

		$pages = ceil($count / $maxRows );
		$logger->log("Count[$count] / maxRow[$maxRows] = totalPages[" .$pages."]" );      
		$t = date("mdY-His",time());					
		$paramdata = json_encode($PARAM); 	

		//Do loop Gen QRImg 
		for($i=1; $i<=$pages; $i++){
			$zipDir = $QRID.'_'.$t; 				
			$logger->log("VVV totalcnt = $count ----- minRows : $minRows" ); 
			if($count <= $minRows){  // less than minSize
				if($i==1){
					insertQRImgDummy($ACCOUNTID,$QRID,$zipDir,$i,$pages,$maindata,$paramdata); 						
					$config = GetCurrentConfig();
					$qrcodeWithOutSQS = $config->option->qrcodeWithOutSQS ;   //#2322 Just add
					if($qrcodeWithOutSQS == true){
							$logger->log("CallLLLLLLLLLLL genQRImageSub ----- qrcodeWithOutSQS : $qrcodeWithOutSQS   / zipDir = $zipDir "  ); 
							$subRes = genQRImageSub($PARAM,$i,$maxRows,$zipDir); 	
					}else{
							$logger->log("SQSSQSSQSSQSSQS Call genQRImageSubSQS ----- qrcodeWithOutSQS : $qrcodeWithOutSQS   / zipDir = $zipDir " ); 
						$subRes = genQRImageSubSQS($PARAM,$i,$maxRows,$zipDir); 	
					}
				}else{
					$logger->log("minRows[$minRows] BBB maxRow[$maxRows] " );      
				}
			}else{
				insertQRImgDummy($ACCOUNTID,$QRID,$zipDir,$i,$pages,$maindata,$paramdata); 	
			}			
		} 	
		$success = true; 
	}
	return array(
		"success"=>$success,
		"message"=>$errorMsg,		
		"logDebug"=>logDebug(),
	);		
}


function genQRImageSub($PARAM,$i,$maxRows,$zipDir){
	$logger = new FileLogger("qrcodeImgEachCron.log"); 
	$logger->log("  +++++++ START [genQRImageSub]:: Page i[" .$i . "] "   );	

	$success = false; 
	$startRunning = false;
	
	$logger->log("genQRImageSub IN PARAM = " . json_encode($PARAM));

	$QRID = $PARAM['QRID']; 
	$ACCOUNTID = $PARAM['ACCOUNTID']; 
	$ACCOUNTNAME = $PARAM['ACCOUNTNAME']; 		
	$QRReportSuccess = $PARAM['QRReportSuccess']; 		
	$QRResultID = $PARAM['QRResultID']; 		
	$Credentials = $PARAM["LOGINCREDENTIALS"]; 			
	$minRows = (int)$PARAM["minRows"]; 	
	$totalcnt = (int)$PARAM["totalcnt"]; 		
	$isRecovery = $PARAM['isRecovery']; 
	$blueprint = $PARAM['blueprint']; 
	$domainURL = $PARAM['domainURL']; 
	if(empty($isRecovery)){
		$isRecovery = "No"; 
	}
	
	$sql1 = "SELECT * FROM DV_QRCodeDummy where zipDir = '".$zipDir."'  AND  acctID = $ACCOUNTID  AND  idx = $i ";
	$sql1 .= "  AND  eachDoneTime is null AND doingTime is null AND completeTime is null" ; 

	$logger->log("  [genQRImageSub]:: - CHK B4 Call Sub SQL: $sql1");		  				
	$res1 = MySQL_Select($sql1);		
	if($res1 != []){		
		$rec1 = $res1[0]; 
		$round = $rec1["recovery"] ; 
		setEachCronDummy($zipDir,$i,$ACCOUNTID);
		if($round < 2){
			$startRunning = true; 			
		}
	}else{
		$errorMessage = "NoResult to startRunning [genQRImageSub]:: $sql1 " ; 
		$logger->log("  [genQRImageSub]::$errorMessage");		
	}

	$logger->log("  [genQRImageSub]:: startRunning =$startRunning -- isRecovery=$isRecovery -- round=$round");		
	if($startRunning){

		$sql = "SELECT * FROM DV_QRCode where acctID = $ACCOUNTID and ID = '$QRID'"; 
		$logger->log("  [genQRImageSub]:: - SQL: $sql");		  		
		$res = MySQL_Select($sql);
		logDebug("  [genQRImageSub]:: res = ". json_encode($res));		

		$rec = $res[0]; 
		if($rec){
			$QRNAME = $rec["name"] ;
			$data = $rec["data"] ;
			$doc = json_decode($data);
			logDebug("  ---- doc = ". json_encode($doc));			
			if($doc!=[]){
				$vpdSystem = $doc->vpdSystem ; 			
				$docName = $doc->qrName ; 
				if ($blueprint == 'PQR') {
					
				} else {
					$qrStr = $doc->qrStr; 	
				}
				
				$vcardList = $doc->vcardList ; 
				$personalized = $doc->personalized; 
				$FieldNames = $doc->FieldNames ;        					
				$addFields = $doc->addFields ; 
				
				$PARAMDEF['filter'] = $doc->filter;
				$PARAMDEF['emailTo'] = $doc->email;
				$PARAMDEF['contentType'] = $doc->contentType;
				$PARAMDEF['url'] = $doc->url;
				$PARAMDEF['qrStr'] = $doc->qrStr;
				$PARAMDEF['urlParam'] = $doc->urlParam;
				$PARAMDEF['addFields'] = $doc->addFields;
				$PARAMDEF['personalized'] = $doc->personalized;
				$PARAMDEF['vpdSystem'] = $vpdSystem;
				$PARAMDEF['FieldNames'] = $FieldNames;
				$PQRoption = getPQRoption($logger, $PARAM, $PARAMDEF);
				
				$logger->log("genQRImageSub :: RES PQRoption = ". json_encode($PQRoption));				
				
				$LISTDEFINITION = $PQRoption['LISTDEFINITION'];
				$email = $PQRoption['sendTo'];
				$contentType = $PQRoption['contentType'];
				$domain = $PQRoption['url'];
				$qrStr = $PQRoption['qrStr'];
				$addFields = $PQRoption['addFields'];
				$personalized = $PQRoption['personalized'];
				$FieldNames = $PQRoption['FieldNames'];
				$renderFieldNames = $PQRoption['renderFieldNames'];
				
				$logger->log("  qqq003 [genQRImageSub]:: FieldNames =". json_encode($FieldNames));			
				$logger->log("  qqq003 [genQRImageSub]:: addFields =". json_encode($addFields));			
				$logger->log("  [genQRImageSub]:: vcardList =". json_encode($vcardList));
				
				//$logger->log("  FieldNames=". json_encode($FieldNames));								
				//$renderFieldNames = array("purl","FirstName","LastName","Email","Phone","Address1","State","Zip","Company","Mobile");	
				//if(!empty($FieldNames))  $renderFieldNames = $FieldNames; 				

				if($contentType == "vcard" && !empty($vcardList)){
					$renderFieldNames = $vcardList; 			
					$logger->log("  [genQRImageSub]:: USE VCARD FIELD=". json_encode($renderFieldNames));	
					$logger->log("  [genQRImageSub]:: VCARD >>> diffRenderField VS FieldNames =". json_encode($FieldNames));	
					$renderFieldNames = diffRenderField($renderFieldNames,$FieldNames,$logger);  //#2322 diff vcard with fieldnames >> add to renderFieldname
						
				}
				if (!in_array("purl", $renderFieldNames) && !in_array("PURL", $renderFieldNames) ){
					array_unshift($renderFieldNames,"purl");  //push first 
				}
				
				$logger->log("  [genQRImageSub]:: addFields =". json_encode($addFields));				
				$renderFieldNames = diffRenderField($renderFieldNames,$addFields,$logger);  
				//$renderFieldNames = addNoChooseField($renderFieldNames,$qrStr,$logger);

				$myOption = $doc->myOption; 
				$imgFormat = $doc->imgFormat; 

				$logger->log("  [genQRImageSub]:: $ACCOUNTID | maxRow[$maxRows] | $LISTDEFINITION |  credential = ". json_encode($Credentials) . " | renderFieldNames = "  . json_encode($renderFieldNames));		
				$filterEncoded = filterEncoded($LISTDEFINITION, $logger);
				$ContactListRequest   = array(  
					"Credentials" => $Credentials,
					"Account_ID" => $ACCOUNTID,
					"FieldNames" => $renderFieldNames,
					"Filter" => $filterEncoded,
					"MaxRows" => $maxRows,
					"StartRowIndex" => ($i-1) ,     //actually this is page index rowindex = StartRowIndex*MaxRows
				);				

				$logger->log("********* STUDIO callServiceCredential contactservice/GetContactsByPage *********");		
				$ContactListResponse = callServiceCredential("contactservice/GetContactsByPage", $ContactListRequest);
				$Contact = $ContactListResponse->{"ContactsCsv"};
				$Contact = str_replace("<","&lt;",$Contact);
				$contactResults = array_map("str_getcsv", explode("\n", $Contact));  //$Contactarray
				array_splice($contactResults, 0, 1);      // Remove Header
				array_splice($contactResults, -1, 1);     // Remove last empty record

				logDebug("  [genQRImageSub]:::: myOption = ". json_encode($myOption)); 	
				logDebug("  [genQRImageSub]:::: imgFormat = ". $imgFormat); 						

				$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
				$logger->log("  [genQRImageSub]:: ".$zipDir.'_'.$i." ContactListResponse ErrorCode = ". $ErrorCode); 	
				if ($ErrorCode == "") {
					$accDir = "/efs/import/qrcode/".$ACCOUNTID; 
					genQRAcctDir($accDir); 	
					$zipDirSub = $zipDir.'_'.$i; 
					$path = $accDir . "/" . $zipDirSub ;   // (Split each maxRow in sub Dir_$i
					//$path = $accDir . "/" . $zipDir ; 	//   /efs/import/qrcode/$ACCOUNTID/QRID_time   (all in 1 Dir)					
					genQRAcctDir($path); 	

					$k=0; 			
					foreach ($contactResults as $Contact) {
						$startCreate = false; 
						$purlname = renderEachPURL($contactResults,$renderFieldNames,$k,$logger); 		
						$qrStrRender = renderEachContact($contactResults,$renderFieldNames,$qrStr,$k,$contentType); 

						if(empty($qrStrRender)){
							$logger->log("  [genQRImageSub]:: file_put_contents = **Blank** : $zipDirSub / PURL=$purlname / qrStrRender=".$qrStrRender); 		
						}else{
							$tmpName = $purlname. '.' .$imgFormat;		
							$rawName = $purlname. '_' .$imgFormat;										
							$fileRawDestination = $path.'/'.$rawName;		
							
							$fileDestination = $path.'/'.$tmpName;	

							if($isRecovery == "Yes"){
								// check exist file
								if (file_exists($fileDestination)) {
									$startCreate = false;  // file recovery is exist not do
									$logger->log("  [genQRImageSub]::RECOVERY FILE fileDestination exist = ".$fileDestination); 
								}else{
									$startCreate = true;  
									$logger->log("  [genQRImageSub]::RECOVERY FILE fileDestination = ".$fileDestination); 
								}
							}else{
								$startCreate = true;
							}				

							if($startCreate){					
								$qrJson = array(
										'command'=>'qrcode', 
										'text' => $qrStrRender, 
										'option' => $myOption, 
										'imageFormat' => $imgFormat, 
										'outputFormat' => 'dataUrl'
								);							
								
								$apiEndPoint = GetCurrentConfig()->qrcodeEndPoint;
								$ret = curlPostJson($apiEndPoint,$qrJson); 	
								//$logger->log("  curlPostJson ret = ". json_encode($ret)); 					
								$imgData = $ret->data; 
								$img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imgData));
								$logger->log("  [genQRImageSub]:: STATE CREATE - file_put_contents $zipDirSub / $k  > fileDestination img = ".$fileDestination); 
								file_put_contents($fileRawDestination, $img);	
								rename($fileRawDestination, $fileDestination);																
							}
						}
						if(fmod($k, 10) == 0){
							updateTriggerQRDummy($ACCOUNTID,$zipDir,$i,$k); 	
						}
						$k++; 
					}						
					updateTriggerQRDummy($ACCOUNTID,$zipDir,$i,$k); 	


					$zipRes = zipFolder($path,$accDir,$zipDirSub);
					$logger->log(" zipRes = " . json_encode($zipRes)); 

					if($zipRes["zipFilename"]){		
						try{
							$logger->log("  [genQRImageSub]::----------- START CALL [uploadQRToS3] path = $path"); 
							$s3zipName = $zipDirSub.'.zip'; 
							$s3ZipResult = uploadQRTOS3($zipRes["zipFilename"],$s3zipName); 
							$logger->log("  s3ZipResult = ".json_encode($s3ZipResult)); 								
							$s3zipDestination = $s3ZipResult['fileDestination']; 
							$logger->log(" [genQRImageSub]::------------ END CALL [uploadQRToS3] Temp zip file > ". $s3zipDestination); 		
						}catch(PDOException $zipE){
							//$success = false; 
							$zErr = $zipE->getMessage();
							$logger->log(" [genQRImageSub]:: zipRes ERROR::". $zErr ); 		
						}
					}

					//$success = true; 
					$docData = []; 					
					$docData["mSize"] = $maxRows;  					
					$docData["QRResultID"] = $QRResultID;  				
					$docData["S3url"] = $s3ZipResult["S3url"]; 
					$docData["QRNAME"] = $QRNAME;  
					$docData["path"] = $path;  
					$docData["accDir"] = $accDir;  					
					$docData["QRReportSuccess"] = $QRReportSuccess;  														
					$docData["ACCOUNTNAME"] = $ACCOUNTNAME;  
					$docData["EMAIL"] = $email;  					
					$docData["LISTDEFINITION"] = $LISTDEFINITION; 					
					$docData["LOGINCREDENTIALS"] = $Credentials; 					
					$docData["FieldNames"] = $renderFieldNames; 
					$docData["zipName"] = $s3zipName; 	

					$logger->log("  [genQRImageSub]:: ----- Do [genQRImgDummy] ($zipDir / $i ) -----///////");	
					genQRImgDummy($ACCOUNTID,$QRID,$zipDir,$i,0,$docData); 									
					genQRImgDummyS3Link($ACCOUNTID,$zipDir,$i,$s3ZipResult["S3url"]) ; 		

					$logger->log("  [genQRImageSub]:: Delete --- Zip file --- ". $s3zipDestination); 		
					//deleteQrcodeDirectory($path); 						
					deleteQrcodeTmpFile($s3zipDestination); 

					
					$logger->log("  [genQRImageSub]:: minRows = $minRows   ---    totalcnt = $totalcnt" ); 
					if(is_int($totalcnt) && is_int($minRows) && $totalcnt <= $minRows){							
							$logger->log("LESS THAN $minRows AAAAAAAAAAAA AUTOCALL doQrcodeImgCron zipDir = $zipDir AAAAAAAAAAAA ");		  				
							$res = doQrcodeImgCron($PARAM,$zipDir,$accDir); 
							$success = $res["success"] ; 
							$errorMessage = $res["error"] ; 
							if($success){
								$dirpath = $accDir."/" . $zipDir."_".$i ; 
								$logger->log("[genQRImageSub]:: CALL[deleteQrcodeDirectory] Dir - $dirpath" ); 		
								deleteQrcodeDirectory($dirpath); 						
								//deleteQrcodeTmpFile($s3zipDestination); 
							}
					}
				}else{					
					$errorMessage = "ERROR:: genQRImageSub $QRID - GetContactsByPage($i) - $ErrorCode" ; 
					$logger->log("  [genQRImageSub]:: $errorMessage");		  		
					$success = false; 
				}      					
			}
		}else{		
			$errorMessage = "NoResult :: $sql " ; 
			$logger->log("  [genQRImageSub]:: $errorMessage");		
			$success = false; 
		}//end if rec  
	}	
	return array('success'=>$success, 'errorMessage' => $errorMessage, 'path'=>$path, 'accDir'=>$accDir, 'zipDir'=>$zipDir, "toemail"=>$email ,  "QRNAME"=>$QRNAME, "logDebug"=>logDebug());
}

function doQrcodeImgCron($PARAM,$zipDir,$accDir){
		$logger = new FileLogger("qrcodeImgCron.log"); 
		$updSql = "UPDATE DV_QRCodeDummy SET doingTime = Now() WHERE zipDir = '".$zipDir."' ";
		$logger->log("+++++++X doQrcodeImgCron == START  == PARAM >> " .json_encode($PARAM));
		$logger->log("[doQrcodeImgCron]:: - sql UPDATE = " . $updSql);
		$db = GetPDOMySQL();
		try{
			$numInsert = $db->exec($updSql);
			if($numInsert > 0){
				$dirpath = $accDir."/" . $zipDir ; 
				$logger->log("[doQrcodeImgCron]:: ========== START doQRExcelS3AndSendEmail  $zipDir ---");
				$logger->log("[doQrcodeImgCron]::dirpath = $dirpath | $accDir | $zipDir ");			
				doQRExcelS3AndSendEmail($PARAM, $dirpath, $accDir, $zipDir) ; 
				$logger->log("SSSSSSSSSS $zipDir --4/4 [doQrcodeImgCron]::========== END doQRExcelS3AndSendEmail  ******* ");
				$success = true; 
			}else{			
				$success = false; 
				$error = "SSSSSSSSSS $zipDir --4/4 ERROR:: DV_QRCodeDummy SET doingTime Fail!! zipDir=$zipDir"; 
			}
		}catch(PDOException $e){
			$success = false; 
			$error = $e->getMessage();
		}
		return array('success'=>$success, 'error' => $error); 
}


function renderEachPURL($contactResults,$renderFieldNames,$index,$logger){
	if( !empty($contactResults[$index])){  //each row 
		if(count($contactResults[$index]) == count($renderFieldNames)){
			$i = 0; 
			foreach($renderFieldNames as $field){
				if(strtolower($field)=="purl"){  
					return $contactResults[$index][$i]; 	
				}				
				$i++; 
			}
		}		
	}else{
		$logger->log("[renderEachPURL] EMPTY contactResults" ); 		
	}
	return ""; 
}
function renderEachContact($contactResults,$renderFieldNames,$qrStr,$index,$contentType=null){
	if( !empty($contactResults[$index])){  //each row 
		if(count($contactResults[$index]) == count($renderFieldNames)){
			$i = 0; 
			foreach($renderFieldNames as $field){
				$qrStr = str_ireplace("##".$field."##",$contactResults[$index][$i],$qrStr); 
				$i++; 
			}
		}		
	}
	if($contentType == "google"){
		$qrStr = preg_replace("/[\s]/", "+", $qrStr); 
	}
	return $qrStr; 
}

function GetContactsByPageCredentials($Credentials, $filter, $ACCOUNTID, $renderFieldNames,$moredata) {
	$logger = new FileLogger("GetContactsByPageCredentials.log"); 		
	$logger->log("ACCOUNTID = $ACCOUNTID , filter=$filter , renderFieldNames=". json_encode($renderFieldNames)  );	
	
	$ContactRES = "";
	$Contact = "";
	$rows = array();
	$Contactarray = array();
	$columns = array();
	$errorMessage = '';
	
	$count = GetContactCountCredentials($TEST, $Credentials, $filter, $ACCOUNTID); 
    $contentTotal = $count; 
    $maxRows = 5000 ;
	//$maxRows = 5 ;
    $pages = ceil($count / $maxRows );
	$logger->log("Count[$count] / maxRows[$maxRows] = totalPages[" .$pages."]" );


	$addCompleteURL = $moredata['addCompleteURL']; 
	$aHeader = $moredata['aHeader']; 
	$option = $moredata['option']; 
	$qrStr = $moredata['qrStr']; 
	$addInfo = $moredata['addInfo']; 

	$logger->log("option[$option] / aHeader[$aHeader] / qrStr=". $qrStr );
	$logger->log("addInfo = " . json_encode($addInfo) );

	$ErrorMsg = ""; 
	$totalCount = 0; 
	$groupID = 1;

	$addHeader="QR Code String" ; 							
	if($option==2){
		$addHeader="QR Code Image File" ; 							
	}		
	$aHeader = $aHeader.",".$addHeader ; 
	if($addCompleteURL){
		$addCURL = "Complete URL"; 
		$aHeader = $aHeader.",".$addCURL ; 
	}
	$logger->log("[GetContactsByPageCredentials]:: -- aHeader = ". $aHeader); 	
	
	for($i = 0;$i < $pages;$i++){
		$filterEncoded = filterEncoded($filter, $logger);
		$ContactListRequest   = array	(
    		"Credentials" => $Credentials,
            "Account_ID" => $ACCOUNTID,
    		"FieldNames" => $renderFieldNames,
    		"Filter" => $filterEncoded,
            "MaxRows" => $maxRows,
            "StartRowIndex" => $i ,   // actually this is page index rowindex = StartRowIndex*MaxRows
    	);

    	$logger->log("Person i[".$i."]-total count[$count] , page=$pages"); 
     	
    	$ContactListResponse = callServiceCredential("contactservice/GetContactsByPage", $ContactListRequest);
    	$ErrorCode = $ContactListResponse->{"Result"}->{"ErrorCode"};
        $logger->log("ErrorCode Call Credential = ".$ErrorCode); 
        if ($ErrorCode == "") {
        	try{	
				$ContactData = $ContactListResponse->{"ContactsCsv"};
        		$ContactData = str_replace("<","&lt;",$ContactData);
        		if ($groupID == 1) {
        			$ContactData = stripFirstLine($ContactData);
        		}
        		$ContactData = trim($ContactData);    	        
        		$CArrays = array_map("str_getcsv", explode("\n", $ContactData));
        		if ($groupID > 1) {
					array_splice($CArrays, 0, 1);      // Remove Header
				}
				$logger->log("[GetContactsByPageCredentials - $i]  ::  CArrays count = ". count($CArrays) );                 
				//array_splice($CArrays, -1, 1);     // Remove last empty record                
				
                $Contactarray = array_merge($Contactarray, $CArrays); 
				$totalCount = count($Contactarray); 				
				$logger->log("[GetContactsByPageCredentials 222 - $i]  :: totalCount = ". $totalCount );	

				$j=0; 
				$cstr = ""; 
				foreach ($CArrays as $CArr){	
					
					$purlRender = renderEachPURL($CArrays,$renderFieldNames,$j,$logger); 
					if($option==1){  				
						$qrStrRender = renderEachContact($CArrays,$renderFieldNames,$qrStr,$j,$addInfo['contentType']);    //replace ##	xxx##
					}else{
						$strRender = renderEachContact($CArrays,$renderFieldNames,$qrStr,$j,$addInfo['contentType']);    //replace ##	xxx##
						$qrStrRender = $purlRender; 
						if($addInfo){
							if(empty($strRender)){
								$qrStrRender = ""; 
							}else{
								$qrStrRender = $qrStrRender.'.'. $addInfo['imgformat'];
							}
						}							
					}
					$completeURL = ""; 
					if($addCompleteURL){
						$domain = $addInfo['url'];
						//$completeURL = ","."\"".$domain.'/'.$purlRender."\"";
						
						$contentText = $addInfo['contentText'];  //#2271
						$logger->log("[addCompleteURL-$i]  :: contentText = ". $contentText );	
						
						$contentText = renderEachContact($CArrays,$renderFieldNames,$contentText,$j,$addInfo['contentType']);    //#2322
						$completeURL = ","."\"". str_ireplace("##purl##",$purlRender,$contentText) ."\"";  
					}
					$eachRec = json_encode($CArr) ; 
					logDebug("page $i / $j - " . $eachRec ); 
					$eachRec = substr($eachRec, 1, -1);

					$cstr .= $eachRec .",". "\"".$qrStrRender."\"" . $completeURL ."\r\n" ; 
					$j++;
				}				
				if ($groupID == 1) {
					$Contact = $aHeader ."\r\n" . $cstr ; 
				}else{
					$Contact = "\r\n" . $cstr ; 
				}				
				//logDebug("LAST  Contact ===  " . $Contact); 
			
				$Contact = trim($Contact);
				$ContactRES = $ContactRES."\r\n".$Contact; 

    	        $groupID++;
        	}catch(PDOException $er){
    		    $logger->log("ERROR catch fn[GetContactsByPageCredentials]" .$er->getMessage() );   
				$ErrorMsg = $ErrorMsg . " Error[$i]=".$er->getMessage()."<br> "; 
        	}
        }else{
    		$errorMessage = "ContactListResponse ERROR : <br> ErrorMessage -> ".$ContactListResponse->{"Result"}->{"ErrorMessage"}.'<br>'.
    		"ExceptionMessage : ".$ContactListResponse->{"Result"}->{"ExceptionMessage"};
			$ErrorMsg = $ErrorMsg . "==== ErrorMain :: ".$errorMessage ."===== <br> "; 
    		break; 
        }
		
	}
	
	$logger->log("========== GetContactsByPageCredentials = Done============== ContactRES = " . json_encode($ContactRES));
	$ContactRES = trim($ContactRES);
	
	$result['Contact'] = $ContactRES;
	$result['Contactarray'] = $Contactarray;
	$result['totalCount'] = $totalCount;
	$result['ErrorMsg'] = $ErrorMsg;

	//$Contacts = string2ByteArray($Contact);
	//$result['Contacts'] = $Contacts;
	
	return $result;
}

function genExcelQRString($ACCOUNTID,$QRID,$Credentials,$LISTDEFINITION,$qrStr,$FieldNames,$renderFieldNames,$option,$addInfo=null,$logger){
	/*gen excel contact list with 2 option  //debug with "Create QR strings only for VPD systems" first.
		1. qrcode string + render## >> sendQRListContacts.php
		2. qrcode img name 
	*/ 	
	$success = false; 	
	$errorMessage = '';		
	$logger->log("[genExcelQRString 222]:: IN LISTDEFINITION = ". $LISTDEFINITION . " | renderFieldNames " . json_encode($renderFieldNames));	
	$logger->log("[genExcelQRString 222]:: IN FieldNames " . json_encode($FieldNames));	
	$logger->log("[genExcelQRString]:: Credentials " . json_encode($Credentials));	
	if($option == 1){
		$logger->log("[genExcelQRString]:: option 1 = Gen QR STRING --------------- See sendQRListContacts.log >> GetContactsByPageCredentials.log  " );	
	}else{
		$logger->log("[genExcelQRString]:: option 2 = Gen QR IMAGE --------------- " );	
	}
	$filterEncoded = filterEncoded($LISTDEFINITION, $logger);
	$ContactListRequest   = array	(		
		"Credentials" => $Credentials,	
		"FieldNames" => $renderFieldNames,
		"Filter" => $filterEncoded,
		"OutputType" => 1,
	);

	$aHeader = implode(",",$renderFieldNames); 
	$addCompleteURL = false; 

	$logger->log("[genExcelQRString] addInfo = ". json_encode($addInfo)  );	
	if($addInfo){
		$domain = $addInfo['url'];
		if($addInfo['contentType'] == "url" && $addInfo['personalized'] == "Yes"){
			$addCompleteURL = true; 
		}
	}

	$moredata['addCompleteURL'] = $addCompleteURL; 
	$moredata['aHeader'] = $aHeader; 
	$moredata['option'] = $option; 
	$moredata['qrStr'] = $qrStr; 
	$moredata['addInfo'] = $addInfo; 
	
	//Gen CSV file
	$ContactsByPage = GetContactsByPageCredentials($Credentials, $LISTDEFINITION, $ACCOUNTID, $renderFieldNames,$moredata); 
	$Contact = $ContactsByPage['Contact'];
	$totalCount = $ContactsByPage['totalCount'];
	$ErrorMsg = $ContactsByPage['$ErrorMsg'];
	$ErrorCode = '';

	$logger->log("[genExcelQRString] empty Contact  = " . empty($Contact) ); 
	if( !empty($Contact) ){
			$logger->log("[genExcelQRString] CREATE FILE !!!!!"); 
			$t = date("mdY-His",time());			
			$tmpName = $QRID.'_'.$t.'.csv';		
			$path = "/efs/import/qrcode/".$ACCOUNTID; 
			genQRAcctDir($path); 
			
			$fileDestination = $path.'/'.$tmpName;
			$logger->log("[genExcelQRString]:: Excel fileDestination = ". $fileDestination); 	

			$Contact = trim($Contact);
			file_put_contents( $fileDestination, $Contact);					
			$success = true;	
	}else {
			$errorMessage = "API/GetContactsByPageCredentials ERROR:: <br> ErrorMessage = ". $ErrorMsg ;
			$success = false;
	}	
	return array('success'=>$success, 'errorMessage' => $errorMessage, 'fileDestination' => $fileDestination,'tmpName'=>$tmpName,"totalCount"=>$totalCount );
}


function uploadQRTOS3($fileDestination,$tmpName){	
	$logger = new FileLogger("qrcodeImgEachCron.log"); 
	$value = file_get_contents($fileDestination);
	if(empty($value)){
		$logger->log("uploadQRTOS3 () ERROR:: *****EMPTY FILE***** " . $value); 
		$success = false; 
	}else{	
		$success = true; 
		$s3fileDes = '/qrcode/'.$tmpName;
		$S3url = s3_put_contents($s3fileDes ,$value);				
		$logger->log("uploadQRTOS3 () PUT-S3 S3url = " . $S3url); 
	}
	return array('success'=>true, 's3fileDes'=>$s3fileDes, 'fileDestination'=>$fileDestination, 'S3url'=>$S3url, 'tmpName'=>$tmpName );
}

function genQRAcctDir($path){
		$logger = new FileLogger("sendQRListContacts.log");  		
		if (is_dir($path)) {			
			//logDebug("Already HAS directory:: ".$path); 
		}else{
			mkdir($path, 0777);
			chmod($path, 0777); 
			$logger->log("  DO mkdir-chmod path = ".$path); 
		}
}

function updateUserInfoPortal($dbName, $acctID) {
	try{
		
		if($db == null){
			$db = GetPDOMySQL();
		}	    
	    
	    $logger2 = new FileLogger("updateUserInfoPortal.log"); 
	    
		$emailLimit = -1;
		$emailOverage = 0;
		$contactLimit = -1;
		$contactOverage = 0;
		$smsLimit = 0;
		$smsOverage = 0;
		$enrichmentLimit = 0;
		$enrichmentOverage = 0.45;
		$contentSearchLimit = -1;
		$contentSearchOverage = 0;
		$contentLaunchLimit = -1;
		$contentLaunchOverage = 0;
		
		if ($acctID > 0) {
			$accountID = -1;
			$stmt = $db->prepare("Select * from $dbName.DV_UserInfo where acctID=? limit 1");
			$stmt->execute(array($accountID));	
			
			
			while ($row = $stmt->fetch()) {
				$emailLimit = $row['emailLimit'];
				$emailOverage = $row['emailOverage'];
				$contactLimit = $row['contactLimit'];
				$contactOverage = $row['contactOverage'];
				$smsLimit = $row['smsLimit'];
				$smsOverage = $row['smsOverage'];
				$enrichmentLimit = $row['enrichmentLimit'];
				$enrichmentOverage = $row['enrichmentOverage'];
				$contentSearchLimit = $row['contentSearchLimit'];
				$contentSearchOverage = $row['contentSearchOverage'];
				$contentLaunchLimit = $row['contentLaunchLimit'];
				$contentLaunchOverage = $row['contentLaunchOverage'];
			}
			
			$sql = "UPDATE $dbName.DV_UserInfo set paidAccount='Y', emailLimit=?, emailOverage=?, contactLimit=?, contactOverage=?, smsLimit=?, smsOverage=?, enrichmentLimit=?,enrichmentOverage=?,contentSearchLimit=?,contentSearchOverage=?,contentLaunchLimit=?,contentLaunchOverage=? where acctID=?";
			//echo "$sql<br>";
			
			$logger2->log("sql : $sql"); 
			
			$logger2->log("emailLimit : $emailLimit, emailOverage : $emailOverage, contactLimit : $contactLimit, contactOverage : $contactOverage, smsLimit : $smsLimit, smsOverage : $smsOverage, enrichmentLimit : $enrichmentLimit, enrichmentOverage : $enrichmentOverage, contentSearchLimit : $contentSearchLimit, contentSearchOverage : $contentSearchOverage, contentLaunchLimit : $contentLaunchLimit, contentLaunchOverage : $contentLaunchOverage, acctID : $acctID"); 
			
			try {
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($emailLimit, $emailOverage, $contactLimit, $contactOverage, $smsLimit, $smsOverage, $enrichmentLimit, $enrichmentOverage, $contentSearchLimit, $contentSearchOverage, $contentLaunchLimit, $contentLaunchOverage, $acctID));
			} catch(Exception $e){
				$recurringErrorMessage = $e->getMessage();
				$logger2->log("recurringErrorMessage : $recurringErrorMessage"); 
			}
		}
	
	}catch(PDOException $ex){	
	    $MySQLmessage = $ex->getMessage();	
	    $logger2->log("MySQLmessage : $MySQLmessage"); 
	}
	
	$logger2->log("Done"); 
	return true;
}

function sendSetupFailEmail($aid){
	$subject = "Set up of Asset Manager and other components failed for account no [$aid]";
	$sendTo = "davinci@mindfifreinc.com";
	$cc = "support@mindfireinc.com,davincimonitoring@gmail.com";
	$content = 
		"The database components for accessing the asset manager, contact management and market intel sections have not been set up for acct ID $aid.<br>Please take appropriate action and let the customer know.<br><br>Thank you.";	
	sendsocketlabsEmailAddCC($sendTo, $subject, $content, "", $cc);
	return true;
}

function userinfoToS3($cmd,$acctID,$progID) {
	// #2190
	
	$logger = new FileLogger("userinfo2s3.log");
    $logger->log("cmd : $cmd , acctID : $acctID , progID : $progID");
	
	$dbName = getDatabaseName($acctID,"");
	
	$userInfoRet = GetUserInfoFromDB($dbName);
    $userInfo = $userInfoRet['doc'];
    $fields = array();
    foreach(get_object_vars($userInfo) as $key => $value) {
        if(startsWith($key,"def")){
            //$fields[] = $key;
            //https://s3-us-west-1.amazonaws.com/bkktest/tmp/{{accountID}}-defCompanyPhone.html
            
            $s3FileName = $acctID."-".$key.".html";
            //$fields[] = $s3FileName;
            try{
                $s3Url = s3_put_contents($s3FileName,$value,array("$acctID"=>$acctID,"$progID"=>$progID));
            }catch(exception $e){
                 $logger->log("ERROR s3FileName : $s3FileName");
            	 $logger->log("value : $value");
            }            
            $fields[] = $s3Url;
            
            if (strtolower($key) == 'deftimezone') {
            	
            	
            	$valueMoment = '';
            	$DVTimezones = getDVTimezone();
            	foreach( $DVTimezones as $DVTimezone ) {	
					if ( strtolower($value) == strtolower($DVTimezone['timezoneStudio'])) {
						$valueMoment = $DVTimezone['timezoneMoment'];
						break;
					}
				}
            	
            	$logger->log("timezone : $value , valueMoment : $valueMoment");
            	
            	if ($valueMoment != '') {
		        	$s3FileName = $acctID."-defTimezoneMoment.html";
		            //$fields[] = $s3FileName;
		            try{
		                $s3Url = s3_put_contents($s3FileName,$valueMoment,array("$acctID"=>$acctID,"$progID"=>$progID));
		            }catch(exception $e){
		                 $logger->log("ERROR s3FileName : $s3FileName");
		            	 $logger->log("value : $valueMoment");
		            }
		            $fields[] = $s3Url;
            	}
            }
        }
    }
    
    
    $logger->log("fields : ".json_encode($fields));
    
    return json_encode( 
        array(
            'success'=>true,
            'cmd'=>$cmd,
            'fields'=>$fields
        ));
}



function syncUser($param) {
	$logger2 = new FileLogger("syncUser.log"); 
	$errorMessage = '';
	$studioError = '';
	$test = 'T';
	try {  
		if($db==null){
            $db = GetPDOMySQL();
        }
		
		$setupMode = $param['setupMode'];
		$acctID = $param['acctID'];
		$acctName = $param['acctName'];
		$EMAIL  = $param['EMAIL'];
		$PASSWORD = $param['PASSWORD'];
		$ParentAccountID = $param['ParentAccountID'];
		$ParentUiAccess = $param['ParentUiAccess'];
		$dbName = $param['dbName'];
		$PARTNERGUID = $param['PARTNERGUID'];
		$PARTNERPASSWORD = $param['PARTNERPASSWORD'];
		$userLogin = $param['userLogin'];
		
		$deleteItem = array();
		
			//$PARTNERGUID = '';
		
		$Authenticate = getAcrossAuthenticateCurl($acctID, $EMAIL, $PASSWORD, $PARTNERGUID, $PARTNERPASSWORD);
		$Credentials = $Authenticate['Credentials'];
		$ticket = $Authenticate['userTicket'];
		
		
		$roleResult = createRole($logger2, $Credentials);
        $roleID = $roleResult['roleID'];
		
		$logger2->log("  roleResult = ".json_encode($roleResult)); 								
		
		$UserListRequest   = array
		(
			
			"Credentials" => $Credentials
			
		);
		$UserListResponse = callServiceCredential("userservice/GetUserList", $UserListRequest);
		$logger2->log("  acctID : $acctID , acctName : $acctName"); 								
		$logger2->log("  UserListResponse = ".json_encode($UserListResponse)); 								
		
		$ErrorCode = $UserListResponse->{"Result"}->{"ErrorCode"};
		$userID = "-1";
		$FirstName = '';
		$LastName = '';
		$Mobile = '';
		$userLists = array();
		if ($ErrorCode == "") {
			$status = 'Success';
			$errorMessage = '';
			$studioError = '';
			$ShortUserLists = $UserListResponse->{"UserList"};			
			foreach ($ShortUserLists as $ShortUserList){
				$Email = strtolower($ShortUserList->{'Email'});
				$userID = $ShortUserList->{'ID'};
				$Mobile = $ShortUserList->{'Mobile'};
				$FirstName = $ShortUserList->{'FirstName'};
				$LastName = $ShortUserList->{'LastName'};
				$IsActive = $ShortUserList->{'IsActive'};
				$FirstName = trim($FirstName);
				$LastName = trim($LastName);
				if ($FirstName == '') {
					$name = splitEmail($userEmail);
					$FirstName = $name[0];
					$LastName = 'Unknown';
				}
				
				$user['Email'] = $Email;
				$user['userID'] = $userID;
				$user['Mobile'] = $Mobile;
				$user['FirstName'] = $FirstName;
				$user['LastName'] = $LastName;
				$user['IsActive'] = $IsActive;
				if ($Email != 'davinci@mindfireinc.com') {
					$userLists[] = $user;
				}
							
			}
			$status = 'success';
			
			$logger2->log("  userLists = ".json_encode($userLists)); 								
			
			$EMAIL = '';
			$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
			$Stmt->execute(array($acctID));
			$affected_rows = $Stmt->rowCount();
			$found = false;
			$errorMessage = '';
		
			
			while ($row = $Stmt->fetch()) {
				$docData = json_decode($row['data'],true);
				$users = $docData['users'];	
				
				$logger2->log("  users = ".json_encode($users));
			}
			
			
			for ($j = 0; $j < sizeof($userLists); $j++) {
			   $element = $userLists[$j];
			   $userName = $element['Email'];
			   $Mobile = $element['Mobile'];
		   	   $FirstName = $element['FirstName'];
		   	   $LastName = $element['LastName'];
		   	   $userID = $element['userID'];
		   	   $IsActive = $element['IsActive'];
		   	   $spfFlag = '';
		   	   $Permissions = '';
		   	   $image = 'https://s3-us-west-1.amazonaws.com/mfdavinci/image/person.jpg';
			   $userNameLower = strtolower($userName);
			   $user = array();
			   $t1 = microtime(true);
			   $ID = md5($t1);
			   $ID = 'IMG-'.$ID;
			   
			   $mustAdd = true;
			   for ($i = 0; $i < sizeof($users); $i++) {
					$user = $users[$i];
					$userEmail = $user['email'];
					$userEmailLower = strtolower($userEmail);
					if ($userEmailLower == $userNameLower) {
						$OfficialName = $user['OfficialName'];
						$Permissions = $user['Permissions'];
						$spfFlag = $user['spfFlag'];
						$image = $user['image'];
						$ID = $user['id'];
			    		$mustAdd = false;
			    		$itemIndex = $i;
			    		if ($IsActive == false) {
			    			$deleteItem[] = $itemIndex;
			    		}
			    		break;
					}
			   }
			   
			   $uName = "$FirstName $LastName";
			   
			   
			   if ($mustAdd == false) {
			   	if ($IsActive == true) {
			   		// $users[$i] = $user;
			   		$logger2->log("  exist  user = ".json_encode($user)); 
			   	} else {
			   		
			   		// unset($users[$itemIndex]);
			   		$logger2->log("  delete  user = ".json_encode($user)); 
			   	}
			   		
			   		
			   } else {
			   		if ($IsActive == true) {
			   			
			   			$user["name"] = $uName;
					    $user["email"] = $userName;
					    $user["Cell"] = $Mobile;
					    $user["userID"] = $userID;
					    $user["OfficialName"] = $uName;
					    $user["Permissions"] = $Permissions;
					    $user["spfFlag"] = $spfFlag;
					    $user["image"] = $image;
					    $user["id"] = $ID;
				   		
						$users[] = $user;
						$logger2->log("  add  user = ".json_encode($user)); 
				   		
				   		
				   		$SeedContact = addSeedContactCredentials($Credentials, $FirstName, $LastName, $userName);
						$logger2->log("addSeedContact = ". json_encode($SeedContact) );
						if ($SeedContact) {
							if ($SeedContact['success']) {
								$AddSeedContact_status = "Success";
							} else {
								$AddSeedContact_status = "Fail";
							}
						} else {
							$AddSeedContact_status = "Fail";
						}
			   		}
					
			   }
			   $UpdateResponse = UpdateUserIsActive($ticket, $acctID, $userName, $uName, $Mobile, $IsActive);
			}
			
			
			$logger2->log("deleteItem 2 = ". json_encode($deleteItem) );
			
			for ($i = sizeof($deleteItem); $i > 0; $i--) {
				$logger2->log("deleteItem $i = ".  $deleteItem[$i]);
			}
			
			$userSave = $users;
			$logger2->log("userSave 2 = ". json_encode($userSave) );
			
			$docData['users'] = $userSave;

			$data = json_encode($docData);
			
			$logger2->log("data 2 = ". json_encode($docData) );
			
			
			
			
			
			
			$sql = "UPDATE DV_UserInfo SET data = ? WHERE acctID = ?" ; 
			try {  
				$updateStmt = MySQL_Prepare($db,$sql);
				$updateStmt->execute(array($data, $acctID));
				$affected_rows = $updateStmt->rowCount();
				$updsuccess = true; 
			}catch(exception $e) {
				$errorMessageUpdate .= " ex: ".$e; 
			}
			
			$logger2->log("errorMessageUpdate = $errorMessageUpdate");	
			
			
		} else {
			$status = 'Fail';
			$errorMessage = "syncUser ErrorMessage : ".$UserListResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$UserListResponse->{"Result"}->{"ExceptionMessage"};
			$userID = "-1";
			$studioError = $UserListResponse->{"Result"}->{"ErrorMessage"};
		}
		
	} catch(exception $e) {
		$status = 'Fail';
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	
	if ($status == 'Fail') {
		$NotificationTitle = 'There was an error in pulling the user information from the WFD. We have informed our support team to look into it.';
		
		$subject = "Error in sync-ing users for account $acctName [$acctID]";
		$sendTo = "davinci@mindfifreinc.com";
		$cc = "support@mindfireinc.com,davincimonitoring@gmail.com";
		$content = 
		"There was an error in pulling the user information from the WFD.<br><br>Error message : $errorMessage";	
		sendsocketlabsEmailAddCC($sendTo, $subject, $content, "", $cc);
		
	} else {
		$NotificationTitle = 'Your user settings is ready.';
	}
	
	$noticeType = 'Sync Users';
	$Notification = $NotificationTitle;
	$NotificationLink = "";
	$moreData['NotificationLink'] = $NotificationLink;
	
	$NotificationResult = saveNotification1('', $userLogin, $acctID, $noticeType, $NotificationTitle, $Notification, '', $NotificationLink, $moreData);
	
	$result['status'] = $status;
	$result['errorMessage'] = $errorMessage;
	$result['studioError'] = $studioError;
	$result['userLists'] = $userLists;
	$result['users'] = $users;
	$result['EMAIL'] = $EMAIL;
	$result['PASSWORD'] = $PASSWORD;
	$result['PARTNERGUID'] = $PARTNERGUID;
	$result['PARTNERPASSWORD'] = $PARTNERPASSWORD;
	$logger2->log("===========================================");	
	
	return $result;
}


function createGoal($logger3, $Credentials) {
	
	// Add Goal #2233
			
	
	$result = array();		
			
	for ($g = 1; $g <= 8; $g++) {
		
		if ($g == 1) {
			$ID = 1;
			$Name = "ClickedEmail";
			$Description = "Contact Clicked Link in Email";
			$Category_ID = 2;
		} else if ($g == 2) {
			$ID = 2;
			$Name = "Converted";
			$Description = "Contact Reached Conversion Point";
			$Category_ID = 2;
		} else if ($g == 3) {
			$ID = 3;
			$Name = "OpenedEmail";
			$Description = "Contact Opened Email";
			$Category_ID = 1;
		} else if ($g == 4) {
			$ID = 4;
			$Name = "SubmittedWelcome";
			$Description = "Contact Submitted Welcome Page";
			$Category_ID = 2;
		} else if ($g == 5) {
			$ID = 5;
			$Name = "SubmittedSurvey";
			$Description = "Contact Submitted Survey Page";
			$Category_ID = 2;
		} else if ($g == 6) {
			$ID = 6;
			$Name = "Level-0";
			$Description = "Visited Welcome Page";
			$Category_ID = 1;
		} else if ($g == 7) {
			$ID = 7;
			$Name = "Level-1";
			$Description = "Visited Survey Page";
			$Category_ID = 2;
		} else if ($g == 8) {
			$ID = 8;
			$Name = "Level-2";
			$Description = "Visited Thank You page";
			$Category_ID = 2;
		}	
		
		
		$ImportRequest   = array
		(
			"Category_ID"=> $Category_ID,
			"Name"=> $Name,
			"Description"=> $Description,
			"Credentials" => $Credentials,
		);
		$AddGoalRequest = $ImportRequest;
		$logger3->log("About to call function callService for configurationservice/AddGoal");
		$logger3->log("ImportRequest = ". json_encode($ImportRequest));
		$stop = false;
		$loopCount = 0;
		while($stop == false){
			$ImportResponse = callServiceCredential("configurationservice/AddGoal", $ImportRequest);
			$AddRoleResponse = $ImportResponse;
			$logger3->log("Finished calling function callService for configurationservice/AddGoal");
			$logger3->log("$loopCount ImportResponse = ". json_encode($ImportResponse));
			$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
			$loopCount++;
			if ($ErrorCode == "") {
				$stop = true;
			} else if ($ErrorCode == "GoalNameNotUnique;") {
				$stop = true;
			} else if ($ErrorCode == "AccessDenied;") {	
				$stop = true;
			} else {
				if ($loopCount == 1) {
					sleep(5);
				} else if ($loopCount == 2) {
					sleep(10);
				} else if ($loopCount == 3) {
					sleep(20);
				} else if ($loopCount == 4) {
					sleep(40);
				} else if ($loopCount == 5) {
					$stop = true;
				}			
			}
		}						
		if ($ErrorCode == "") {
			$status = "Success";
			$errorMessage = '';
		} else if ($ErrorCode == "GoalNameNotUnique;") {
			$status = "Success";
			$errorMessage = '';
		} else {
			$status = "Fail";
			$errorMessage = "AddGoal ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
			$ErrorMessageStudio = $ImportResponse->{"Result"}->{"ErrorMessage"};
		}
		
		
		$group['Name'] = $Name;
		$group['Description'] = $Description;
		$group['Category_ID'] = $Category_ID;
		$group['status'] = $status;
		$group['ErrorCode'] = $ErrorCode;
		$group['errorMessage'] = $errorMessage;
		$group['studioError'] = $ErrorMessageStudio;
		
		$result[] = $group;
		sleep(2);
		
	}
	
	$logger3->log("================================");
	
	
	
	return $result;
	
}

function createRole($logger3,$PartnerCredentials) {
	$ImportRequest   = array
	(
		"Description"=> "",
		"Name"=> "ADMIN",
		"Credentials" => $PartnerCredentials,
		"PermissionIds"=> [1001,1002,1011,1012,2001,2002,3001,3002,4000,5000,5001,6000]
	);
	$AddRoleRequest = $ImportRequest;
	$logger3->log("About to call function callService for userservice/AddRole");
	$logger3->log("ImportRequest = ". json_encode($ImportRequest));
	$stop = false;
	$loopCount = 0;
	while($stop == false){
		$ImportResponse = callServiceCredential("userservice/AddRole", $ImportRequest);
		$AddRoleResponse = $ImportResponse;
		$logger3->log("Finished calling function callService for userservice/AddRole");
		$logger3->log("$loopCount ImportResponse = ". json_encode($ImportResponse));
		$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
		$loopCount++;
		if ($ErrorCode == "") {
			$stop = true;
		} else {
			if ($ErrorCode == 'RoleNameNotUnique;') {
				$stop = true;	
			} else {
				if ($loopCount == 1) {
					sleep(5);
				} else if ($loopCount == 2) {
					sleep(10);
				} else if ($loopCount == 3) {
					sleep(20);
				} else if ($loopCount == 4) {
					sleep(40);
				} else if ($loopCount == 5) {
					$stop = true;
				}
			}
		}
	}						
	if ($ErrorCode == "") {
		$AddRole_status = "Success";
		$errorMessage = '';
		$roleID = $ImportResponse->{"ID"};
		$ErrorMessageStudio = '';
	} else if ($ErrorCode == "RoleNameNotUnique;") {
		
		
		
		$RoleIds = getRoleIDCredentials($PartnerCredentials);	
		
		$roleID = $RoleIds['RoleID'];
		
		$logger3->log("getRoleIDCredentials RoleID = $roleID");
		
		if ($roleID == -1) {
		
		} else {
			
			
			$UpdateRoleRequest   = array
			(
				"Description"=> "",
				"Name"=> "ADMIN",
				"Credentials" => $PartnerCredentials,
				"PermissionIds"=> [1001,1002,1011,1012,2001,2002,3001,3002,4000,5000,5001,6000],
				"ID" => $roleID        
			);
			$logger3->log(" Name : $Name, StatusName : $StatusName, ACCOUNTID : $ACCOUNTID, roleID : $roleID");
			$logger3->log(" UpdateRoleRequest = ". json_encode($UpdateRoleRequest));
			
			$UpdateRoleResponse = callServiceCredential("userservice/UpdateRole", $UpdateRoleRequest);

			$logger3->log("[updateAccountRole.php] UpdateRoleResponse = ". json_encode($UpdateRoleResponse));

			$ErrorCode = $UpdateRoleResponse->{"Result"}->{"ErrorCode"};
			if ($ErrorCode == "") {
				$AddRole_status = "Success";
				$UpdateRole_status = "Success";
				$errorMessage = '';
			} else {
				$AddRole_status = 'Fail';
				$UpdateRole_status = "Fail";
				$errorMessage = "UpdateRole ErrorMessage : ".$UpdateRoleResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$UpdateRoleResponse->{"Result"}->{"ExceptionMessage"};
			}
		}
	} else {
		$AddRole_status = "Fail";
		$errorMessage = "AddRole ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
		$ErrorMessageStudio = $ImportResponse->{"Result"}->{"ErrorMessage"};
		$roleID = "-1";
	}
	
	$roleResult['status'] = $AddRole_status;
	$roleResult['UpdateRole_status'] = $UpdateRole_status;
	$roleResult['errorMessage'] = $errorMessage;
	$roleResult['roleID'] = $roleID;
    $roleResult['ErrorMessageStudio'] = $ErrorMessageStudio;
    
    return $roleResult;
}

function createDomain($logger2,$PartnerCredentials,$domain) {

	$ImportRequest   = array
	(
		"Description"=>"",
		"IsHidden"=>true,
		"OnlyWithSSL"=>true,
		"Credentials"=> $PartnerCredentials,
		"Name"=>$domain
	);
	$logger2->log("About to call function callService for configurationservice/AddDomain");
	$logger2->log("AddDomain = ". json_encode($ImportRequest));
	$stop = false;
	$loopCount = 0;
	while($stop == false){
		$ImportResponse = callServiceCredential("configurationservice/AddDomain", $ImportRequest);
		$AddDomainResponse = $ImportResponse;
		$logger2->log("Finished calling function callService for configurationservice/AddDomain");
		$logger2->log("$loopCount AddDomain = ". json_encode($ImportResponse));
		$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
		$logger2->log("ErrorCode : $ErrorCode");
		$loopCount++;
		if ($ErrorCode == "") {
			$stop = true;
		} else {
			if ($ErrorCode == 'DomainNameNotUnique;') {
				$stop = true;
			} else {
				if ($loopCount == 1) {
					sleep(5);
				} else if ($loopCount == 2) {
					sleep(10);
				} else if ($loopCount == 3) {
					sleep(20);
				} else if ($loopCount == 4) {
					sleep(40);
				} else if ($loopCount == 5) {
					$stop = true;
				}	
			}
		}
	}
	$ErrorCode = $ImportResponse->{"Result"}->{"ErrorCode"};
	if ($ErrorCode == "") {
		$AddDomain_status = "Success";
		$errorMessage = '';
		$domainID = $ImportResponse->{"ID"};
		$ErrorMessageStudio = '';
	} else if ($ErrorCode == "DomainNameNotUnique;") {
		$AddDomain_status = "Success";
		$errorMessage = '';
	} else {
		$AddDomain_status = "Fail";
		$errorMessage = "AddDomain ErrorMessage : ".$ImportResponse->{"Result"}->{"ErrorMessage"}."ExceptionMessage : ".$ImportResponse->{"Result"}->{"ExceptionMessage"};
		$domainID = "-1";
		$ErrorMessageStudio = $ImportResponse->{"Result"}->{"ErrorMessage"};
	}
	$roleResult['status'] = $AddDomain_status;
	$roleResult['errorMessage'] = $errorMessage;
	$roleResult['domainID'] = $domainID;
    $roleResult['ErrorMessageStudio'] = $ErrorMessageStudio;
    
    return $roleResult;
}



function LoadQRitem($ACCOUNTID) {
	if($db == null){
		$db = GetPDOMySQL();
	}
	$Stmt = MySQL_Prepare($db,"select * from DV_QRCode where acctID=? order by name");	
	$Stmt->execute(array($ACCOUNTID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$id = $row['ID'];
		$qrName = $row['name'];	
		$createDate = $row['createDate'];	
		$lastModified = $row['lastModified'];
		$group1 = array(					
			"id"=>$id,
			"qrName"=>$qrName,
			"createDate"=>$createDate,
			"lastModified"=>$lastModified,
		);
		$row2[] = $group1;
		
	}
	
	$group1 = array(					
		"id"=>'',
		"qrName"=>'Choose QR Code',
		"createDate"=>'2050-02-22 07:16:31',
		"lastModified"=>'2050-02-22 07:16:31',
	);
	$row2[] = $group1;
	
	$result['qrList'] = $row2;
	
	
	return $result;
}

function getMasterAWSconfig() {
	
	
	$AWSKEY = "FUEa1XP3bc5T5BRQcgvyF+YT7XbFxG0p+Wrmbg6vjN8=";
	$AWSSECRET = "sbgioSiaftJuU2Cxjk6dsEba7wvALnRF9AYZHKMQfBsQ/+QCHYwgxTazCBds5HJm";
	$region = "us-east-1";
	$CachePolicyId = "4135ea2d-6df8-44a3-9df3-4b5a84be39ad";
	$OriginRequestPolicyId = "216adef6-5c7f-47e4-b989-5492eafa07d3";
	$hostedZoneId = "Z01883393IETQGKA8OKSZ";
	$hostedZoneDomain = "secure-opt.com";
	$hostedZoneDomainMF = "mf.secure-opti.com";
	$maxCount = 25;
	$arnID = "700863480031";
	$originDomain = 'msp.mdl.io';
	$primaryDomain = 'msp.mdl.io';
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Config where configName='SSLCONFIG' limit 1");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['configData'],true);
			$AWSKEY = $docData['AWSKEY'];
			$AWSSECRET = $docData['AWSSECRET'];
			$region = $docData['region'];
			$CachePolicyId = $docData['CachePolicyId'];
			$OriginRequestPolicyId = $docData['OriginRequestPolicyId'];
			$hostedZoneId = $docData['hostedZoneId'];
			$hostedZoneDomain = $docData['hostedZoneDomain'];
			$hostedZoneDomainMF = $docData['hostedZoneDomainMF'];
			$maxCount = intval($docData['maxCount']);
			$arnID = $docData['arnID'];
			$originDomain = $docData['originDomain'];
			$primaryDomain = $docData['primaryDomain'];
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	
	$AWSKEY = secured_decrypt($AWSKEY);
	$result['AWSKEY'] = $AWSKEY;
	$AWSSECRET = secured_decrypt($AWSSECRET);
	$result['AWSSECRET'] = $AWSSECRET;
	$result['region'] = $region;
	$result['CachePolicyId'] = $CachePolicyId;
	$result['OriginRequestPolicyId'] = $OriginRequestPolicyId;
	$result['hostedZoneId'] = $hostedZoneId;
	$result['hostedZoneDomain'] = $hostedZoneDomain;
	$result['hostedZoneDomainMF'] = $hostedZoneDomainMF;
	$result['maxCount'] = $maxCount;
	$result['arnID'] = $arnID;
	$result['originDomain'] = $originDomain;
	$result['primaryDomain'] = $primaryDomain;
	
	return $result;
}

function getAWSconfig() {
	
	$region = 'us-east-1';
	$AWSKEY = 'ubs//ed5DtaF21TNqObo1AVmXZnYHzQTZvkCKjfiYJU=';
	$AWSSECRET = '0sd5kULHfYmQBNPYWQeYs4FNJl1b4Shm2i5WRbLdljXC40Y1HHWH6Wl23qr/P0Mw';
	
	
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$Stmt = MySQL_Prepare($db,"select * from DV_Config where configName='AWSCONFIG' limit 1");
		$Stmt->execute();
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
			$docData = json_decode($row['configData'],true);
			$AWSKEY = $docData['AWSKEY'];
			$AWSSECRET = $docData['AWSSECRET'];
			$region = $docData['region'];
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$affected_rows = -1;	
	}
	
	$AWSKEY = secured_decrypt($AWSKEY);
	$result['AWSKEY'] = $AWSKEY;
	$AWSSECRET = secured_decrypt($AWSSECRET);
	$result['AWSSECRET'] = $AWSSECRET;
	$result['region'] = $region;
	
	return $result;
}

function stopTaskCanceledAccount($acctID) {
	//$logger = new FileLogger("stopTaskCanceledAccount.log");
	//$logger->log("acctID : $acctID");
	if($db == null){
		$db = GetPDOMySQL();
	}
	try {
		$Stmt = MySQL_Prepare($db,"UPDATE DV_TargetMarket SET nextAutoRefreshSchedule = null WHERE acctID = $acctID AND nextAutoRefreshSchedule IS NOT NULL");
		$Stmt->execute();

		$Stmt = MySQL_Prepare($db,"UPDATE DV_MI_AutomatedExport SET `status` = 'stop' WHERE acctID = $acctID AND `status` = 'running'");
		$Stmt->execute();
		
		$apiData = MySQL_GetByKey('DV_IP_Enrichment_Settings', ["acctID"=> $acctID, "status"=> 'enabled'],null,$db);
		//$logger->log("apiData : " . json_encode($apiData) );
		if ($apiData != []) {
			$result = setInsiderdata360onlineAPIActivation($apiData['domain'], 'disabled');
			$Stmt = MySQL_Prepare($db,"UPDATE DV_IP_Enrichment_Settings SET `status` = 'disabled' WHERE acctID = $acctID");
			$Stmt->execute();
		}
		
		$saveSuccess = true;
		$saveErrorMessage = '';
	} catch(Exception $e){
		$saveSuccess = false;	
		$saveErrorMessage = $e->getMessage();
	}
	$result = array(
        "success"=>$saveSuccess,
        "message"=>$saveErrorMessage,
    );  
    return $result;
}


function getDocTrigger($acctID, $campaignID, $blueprint) {
	
	$logger = new FileLogger("getDocTrigger.log");
	
	try{
		if($db == null){
			$db = GetPDOMySQL();
		}
	
		$sqltxt = "SELECT * from DV_Document_Trigger where acctID=? and ID = ? and blueprint=? limit 1";
		$logger->log("sqltxt: $sqltxt");
		$stmt = $db->prepare($sqltxt);
		$stmt->execute(array($acctID, $campaignID, $blueprint));	
		$data['DOWNLOADLEADTRIGGER'] = '';
		$data['VISITLEADTRIGGER'] = '';
		$data['WELCOMETRIGGER'] = '';
		$data['OPENTRIGGER'] = '';
		$data['CLICKTRIGGER'] = '';
		$data['REOPENTRIGGER'] = '';
		$data['RECLICKTRIGGER'] = '';
		while ($row = $stmt->fetch()) {
			$data['DOWNLOADLEADTRIGGER'] = $row['DOWNLOADLEADTRIGGER'];
			$data['VISITLEADTRIGGER'] = $row['VISITLEADTRIGGER'];
			$data['WELCOMETRIGGER'] = $row['WELCOMETRIGGER'];
			$data['OPENTRIGGER'] = $row['OPENTRIGGER'];
			$data['CLICKTRIGGER'] = $row['CLICKTRIGGER'];
			$data['REOPENTRIGGER'] = $row['REOPENTRIGGER'];
			$data['RECLICKTRIGGER'] = $row['RECLICKTRIGGER'];
		}
	}catch(PDOException $ex){	
	    $MySQLmessage = $ex->getMessage();	
	    $logger->log("MySQLmessage error : $MySQLmessage"); 
	}
	
	return $data;
	
}

function removeSkipField($fieldList) {
	
	
	for ($i = 0; $i < sizeOf($fieldList); $i++) {
	    $strTemp = trim($fieldList[$i]["Name"]);
	    $strTemp = strtoupper($strTemp);
	    if (($strTemp == 'WELCOMESUBMITPAGE') || ($strTemp == 'PAGESUBMITDATE') ) {
           	unset($fieldList[$i]);
	    }
        
    }
        
	return $fieldList;
}


function getBlueprintName($logger, $campaignType) {
	
	$blueprint = $campaignType;
	
	try{
		if($db == null){
			$db = GetPDOMySQL();
		}
	
		$sqltxt = "SELECT * from DV_BluePrints where name=? limit 1";
		$logger->log("sqltxt: $sqltxt");
		$stmt = $db->prepare($sqltxt);
		$stmt->execute(array($campaignType));	
		while ($row = $stmt->fetch()) {
			$blueprint = $row['blueprintName'];
			if ($blueprint == '') {
				$blueprint = $campaignType;
			}
		}
	}catch(PDOException $ex){	
	    $MySQLmessage = $ex->getMessage();	
	    $logger->log("MySQLmessage error : $MySQLmessage"); 
	}
	
	return $blueprint;
	
}

function updateCampaignBlueprint($logger, $userTicket, $ProgramID, $campaignType) {
	$result = array();
	$result['campaignType1'] = $campaignType;
	$logger->log("campaignType = $campaignType");
	if (($campaignType == 'CL21') || ($campaignType == 'AT2') || ($campaignType == 'AT21') || ($campaignType == 'CL30')) {
		$campaignType = getBlueprintName($logger, $campaignType);
		$BlueprintName = array();
		$BlueprintName['BlueprintName'] = $campaignType;
		$BlueprintName['ProgramID'] = $ProgramID;
		$BlueprintNames[] = $BlueprintName;
			
		$BlueprintRequest   = array
		(
			
			"Credentials" => array
			(
				"Ticket" => $userTicket        
			),
			"BlueprintNames" => $BlueprintNames
		);
		$BlueprintResponse = callService("programservice/SetBlueprintNames", $BlueprintRequest);
		$logger->log("BlueprintRequest = ". json_encode($BlueprintRequest) );
		$logger->log("BlueprintResponse = ". json_encode($BlueprintResponse) );
		$result['campaignType2'] = $campaignType;
		$result['BlueprintRequest'] = $BlueprintRequest;
		$result['BlueprintResponse'] = $BlueprintResponse;
	}
	return $result;
}

function getListName($acctID, $listNames) {
	$allList = '';
	$logger = new FileLogger("sendQRListContacts.log");
	try{
		if($db == null){
			$db = GetPDOMySQL();
		}
		foreach( $listNames as $listID ) {
			
			$logger->log("getListName listID = $listID");
			
			$sqltxt = "SELECT * from DV_AudienceList where acctID=? and contactID=? limit 1";
			$logger->log("sqltxt: $sqltxt");
			$logger->log("acctID : $acctID, listID : $listID");
			$stmt = $db->prepare($sqltxt);
			$stmt->execute(array($acctID, $listID));	
			while ($row = $stmt->fetch()) {
				$LISTNAME = $row['LISTNAME'];
				$logger->log("LISTNAME: $LISTNAME");
				$allList = $allList.',"'.$LISTNAME.'"';
			}
		}
	}catch(PDOException $ex){	
	    $MySQLmessage = $ex->getMessage();	
	    $logger->log("getListName error : $MySQLmessage"); 
	}
	if ($allList != '') {
		$allList = trim($allList,",");
		$allList = '['.$allList.']';
	}
	$logger->log("getListName allList = $allList");
	$result['allList'] = $allList;
	return $result;
}

function updateCampaignBlueprintCredentials($logger, $credentials, $ProgramID, $campaignType) {
	$result = array();
	$result['campaignType1'] = $campaignType;
	$logger->log("updateCampaignBlueprintCredentials ():: campaignType = $campaignType");
	if ($campaignType == 'CL30') {
		$campaignType = getBlueprintName($logger, $campaignType);
		$BlueprintName = array();
		$BlueprintName['BlueprintName'] = $campaignType;
		$BlueprintName['ProgramID'] = $ProgramID;
		$BlueprintNames[] = $BlueprintName;			

		$BlueprintRequest   = array(			
			"Credentials" => $credentials,
			"BlueprintNames" => $BlueprintNames
		);
		$BlueprintResponse = callServiceCredential("programservice/SetBlueprintNames", $BlueprintRequest);

		$logger->log("updateCampaignBlueprintCredentials ():: BlueprintRequest = ". json_encode($BlueprintRequest) );
		$logger->log("updateCampaignBlueprintCredentials ():: BlueprintResponse = ". json_encode($BlueprintResponse) );
		$result['campaignType2'] = $campaignType;
		$result['BlueprintRequest'] = $BlueprintRequest;
		$result['BlueprintResponse'] = $BlueprintResponse;
	}
		return $result;
}

function getBlueprintTable($acctID,$progID) {
	
	$logger = new FileLogger("SendTestSTF.log");
	
	
	$tableName = 'DV_Document_PromoteEbook';
	try {  
		if($db == null){
			$db = GetPDOMySQL();
		}	
		$tablename = 'DV_CampaignList';
		$sql2 = "SELECT * FROM $tablename WHERE acctID=? AND campaignID=? limit 1";
		$Stmt2 = MySQL_Prepare($db,$sql2);
		$Stmt2->execute(array($acctID, $progID));
		$logger->log("sql2 : $sql2");
		$logger->log("acctID : $acctID");
		$logger->log("progID : $progID");
		$affected_rows = $Stmt2->rowCount();
		while ($row2 = $Stmt2->fetch()) {
			$campaignType = $row2['campaignType'];
			$logger->log("campaignType : $campaignType");
			if ($campaignType == 'PromoteEbook') {
				$tablename = 'DV_Document_PromoteEbook';
			} else if ($campaignType == 'PromoteBlog') {
				$tablename = 'DV_Document_PromoteBlog';
			} else if ($campaignType == 'EventReminder') { //#1185
				$tablename = 'DV_Document_EventReminder';
			} else if ($campaignType == 'PQR') { 
				$tablename = 'DV_Document_PQR';
			} else if ($campaignType == 'CL2') { 
				$tablename = 'DV_Document_CL2';
			} else if ($campaignType == 'CL21') { 
				$tablename = 'DV_Document_CL21';
			} else if ($campaignType == 'AT2') { 
				$tablename = 'DV_Document_AT2';				
			} else if ($campaignType == 'AT21') { 
				$tablename = 'DV_Document_AT21';
			} else if ($campaignType == 'CL30') { 
				$tablename = 'DV_Document_CL30';
			} else if ($campaignType == 'PromoteDT') { 
				$tablename = 'DV_Document_PromoteDT';
			} else if ($campaignType == 'BuildAwareness') {	
				$tablename = 'DV_Document_BuildAwareness';
			} else {
				$tablename = 'DV_Document_PromoteEbook';
			}
		}
		$logger->log("$tablename : $tablename");
	}  
	catch(exception $e) {
		$errorMessage = "ex: ".$e; 
		
	}
	return $tablename;
	
}

function getUserContent($acctID, $contentType) {
   $logger2 = new FileLogger("getUserContent.log");
    try{
		if($db == null){
			$db = GetPDOMySQL(); 
		}
		$subject = '';
		$content = '';
		$sendFromName = '';
		$content = '';
		
		$Stmt = MySQL_Prepare($db,"SELECT * FROM DV_UserInfo WHERE acctID=? limit 1");
		$Stmt->execute(array($acctID));
		$affected_rows = $Stmt->rowCount();
		while ($row = $Stmt->fetch()) {
		    
		    
		    $data = $row['data'];
		    
		    $campaignData = json_decode($data);
		    
		    $logger2->log("getUserContent campaignData  = ". json_encode($campaignData) );
		    
		    $subject = $campaignData->WELCOMEUSERSUBJECT;
		    $sendFromName = $campaignData->WELCOMEUSERSENDFROM;
		    $sendFromEmail = $campaignData->WELCOMEUSERSENDEMAIL;
		    
		    if ($subject) { } else { $subject = ''; };
		    if ($sendFromName) { } else { $sendFromName = ''; };
		    if ($sendFromEmail) { } else { $sendFromEmail = ''; };
		    
		    $triggerID = 'WELCOMEUSER';
		    
		    $s3Filename = "file/".$acctID."-".$triggerID.".html";
    		$s3Name = $acctID."-".$triggerID.".html";
    		$s3Location = "https://mfdavinci.s3.us-west-1.amazonaws.com/".$s3Filename;
    		$result1 = file_get_contents($s3Location);
			if ($result1 == false) {
				$content = $campaignData->WELCOMEUSERCONTENT;	
			} else {
				$content = $result1;
			}
			if ($content) { } else { $content = ''; };
			
			$logger2->log("getUserContent subject  = $subject, content  = $content, sendFromName  = $sendFromName, sendFromEmail  = $sendFromEmail, s3Location  = $s3Location");
		}
		return array(
            "success"=>true,
            "subject"=>$subject,
			"content"=>$content,
			"sendFromName"=>$sendFromName,
			"sendFromEmail"=>$sendFromEmail,
        );

    }catch(PDOException $ex){
        return array(
            "success"=>false,
            "message"=>$ex->getMessage(),
			"subject"=>$subject,
			"content"=>$content,
			"sendFromName"=>$sendFromName,
			"sendFromEmail"=>$sendFromEmail,
        );
    }
}


// Google Document Start *************************************************************************************************


$GAPIS = 'https://www.googleapis.com/';
$DOCAPIS = 'https://docs.googleapis.com/';
$SLIDEAPIS = 'https://slides.googleapis.com/';

// *************************************************************************************************

function generateGoogleAsset($access_token, $acctID, $googleAsset, $userinfo, $userdata, $folder) {
	
	$logger = new FileLogger("generateGoogleAssetCB.log"); 
	
    
    $defFromAdd = $userdata['userEmail'];
    $userEmail = $defFromAdd;
    $userdata['acctID'] = $acctID;
    
    
    $logger->log("access_token : $access_token");
    $logger->log("acctID : $acctID");
    $logger->log("folder : $folder");
    
    $asssetResult = array();
    
    if($db == null){
		$db = GetPDOMySQL();
	}
	try {  
		
		foreach ($googleAsset as $googleItem) {
		
		
			$fileId = $googleItem['assettID'];
			$docName = $googleItem['docName'];
			$assetType = $googleItem['assetType'];
			
			$logger->log("fileId : $fileId , docName : $docName , assetType : $assetType");
			
			$docLink = '';
			$new_name = '';
			
			if ($assetType == 'html') {
			    // html start
    
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $docName = substr($docName, 0, strrpos($docName, '.'));
            
                
                $mime_type = $response['mimeType'];
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1.'.html';
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                
                $googleContent = downloadGoogleFile($logger, $access_token, $fileId);
                // echo "downloadGoogleFile<br>";
                // var_dump($googleContent);
                // echo "<br>";
                
                $renderContent = renderFileUser($logger, $googleContent, $userinfo, $userdata);
                // echo "renderFile<br>";
                // var_dump($renderContent);
                // echo "<br>";
                
                $fname = '/efs/sessions/'.$t1;
                
                file_put_contents($fname, $renderContent);
                
                $result = uploadGoogleFile($logger, $access_token, $fname, $mime_type, $new_name, $folder);
                // echo "uploadGoogleFile<br>";
                // var_dump($result);
                // echo "<br>";
                $response = json_decode($result, true);
                $docID = $response['id'];
                $docLink = "https://drive.google.com/file/d/$docID/view?usp=drive_link";
                
                if (file_exists($fname)) {
                    unlink($fname);
                }
                
                // html end
			    
			} else if ($assetType == 'slide') {
			    // slide start
                
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $mime_type = $response['mimeType'];
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1;
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                $result = copyGoogleFile($logger, $access_token, $fileId, $new_name, $folder);
                // echo "copyGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                $docID = $response['id'];
                
                
                $result = updateGoogleSlideUser($logger, $userinfo, $access_token, $docID, $userdata);
                // echo "updateGoogleSlide<br>";
                // echo $result;
                // echo "<br>";
                
                $docLink1 = "https://docs.google.com/presentation/d/$docID/edit";
				$docLink = "https://docs.google.com/presentation/d/$docID/export/pdf";
                
                // echo "Your slide is $new_name<br>";
                // echo "Your link is $docLink<br>";
                // echo "<a href='$docLink' target='_blank'>Open</a><br>";
                // echo "<br>";
                
                // slide end
			    
			} else if ($assetType == 'doc') {
			    // doc start
            	
                $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $mime_type = 'application/vnd.google-apps.document';
                
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $mime_type = $response['mimeType'];
                
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1;
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                $result = copyGoogleFile($logger, $access_token, $fileId, $new_name, $folder);
                // echo "copyGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                $docID = $response['id'];
                //echo "<br>docID : $docID<br>";
                
                $result = updateGoogleDocUser($logger, $userinfo, $access_token, $docID, $userdata);
                // echo "updateGoogleDoc<br>";
                // echo $result;
                // echo "<br>";
                
                $docLink1 = "https://docs.google.com/document/d/$docID/edit";
                $docLink = "https://docs.google.com/document/d/$docID/export?format=pdf";
                
                // echo "Your doc is $new_name<br>";
                // echo "Your link is $docLink<br>";
                // echo "<a href='$docLink' target='_blank'>Open</a><br>";
                // echo "<br>";
                // doc end
			}
			
			if ($new_name != '') {
			    $assetItem['new_name'] = $new_name;
                $assetItem['docLink'] = $docLink;
                $assetItem['docLink1'] = $docLink1;
                $assetItem['assetType'] = $assetType;
                $asssetResult[] = $assetItem;
			}
			
		}
		
		
		$contentEmailCustomer = "";
		
		$contentEmailSubjectCustomerFileName = "templates/triggers/foodOrderSubjectCustomer.html";
		$contentEmailSubjectCustomer = file_get_contents($contentEmailSubjectCustomerFileName);
		$contentEmailSubjectCustomer = str_ireplace("##firstname##",$firstName,$contentEmailSubjectCustomer);
		
		foreach($asssetResult as $assetItem) {
	        
	        $new_name = $assetItem['new_name'];
	        $docLink = $assetItem['docLink'];
	        $docLink1 = $assetItem['docLink1'];
	        $assetType = $assetItem['assetType'];
	            
	        // $contentEmailCustomer = $contentEmailCustomer."Your $assetType is $new_name<br>";
	        $contentEmailCustomer = $contentEmailCustomer."Your PDF Link is $docLink<br>";
	        $contentEmailCustomer = $contentEmailCustomer."Your Google Drive Link is $docLink1<br>";
	        $contentEmailCustomer = $contentEmailCustomer."<br>";
	    }
	    
	    $Email = $userdata['userEmail'];
		
		
		$emailData['EMAIL'] = $Email;
		$emailData['subject'] = $contentEmailSubjectCustomer;
		$emailData['content'] = $contentEmailCustomer;
		$emailData['emailMode'] = '';
		$emailData['saveLog'] = 'no';
		$emailData['fromEmail'] = $refEmail;
		$emailData['fromName'] = $refFName;
		$emailResult = sendsocketlabsEmailAddLog($emailData);
		
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
		$logger->log("errorMessage : $errorMessage");
	}
	
	
    
    return $asssetResult;
}



// FUNCTION

// function using PHP & cURL to send requests. Returns array
function sendGoogleRequest($logger, $url, $header, $data, $method="GET") {
    
    $logger->log("sendGoogleRequest");
    $logger->log("url:$url");
    $logger->log("header:");
    $logger->log(json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $logger->log("data:");
    $logger->log(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($response, true);
    ////$logger->log("response:$response");
    return $output;
}

function generateGoogleToken($logger) {
    
    
    /*
	------------------------------------------------------------------------------------------------
	Google Drive REST API v3 using a Service Account
	------------------------------------------------------------------------------------------------

    Service Account Details issued by Google Cloud Platform IAM
                    https://console.developers.google.com/iam-admin/serviceaccounts

    Service Account Authorization by G-Suite Administrator
                    https://admin.google.com

    Google Drive API v3:
                    https://developers.google.com/drive/api/v3/reference

    Google OAuth 2.0 Playground:
                    https://developers.google.com/oauthplayground/

    Google Scopes
                    https://developers.google.com/identity/protocols/googlescopes
    */
    
    
    // init
    $api = array();
    $access_token = '';
    
    // *************************************************************************************************
    // EDIT THE FOLLOWING 
    // REMINDER: Do not store key in publicly accessible web-folder but where this script can access it.
    
    // Location of private key on your server (JSON downloadable from Google)
    $api['keys']['private']['file']         = 'DV_drive.json';  
    
    // Location to store access token (needs be writeable)
    $api['jwt']['token']['file']            = '/efs/import/qrcode/access_token.dat';  
    
    // Email of the user to impersonate (leave blank until authorized)
    // IMPORTANT: An admin of the GSuite domain has to authorize this client id with the GDrive scope.
    //  1. Browse to https://admin.google.com
    //  2. Go to Security > Show More > Advanced Settings > Manage API Client Access
    //  3. Enter the Client ID in the field "Client Name"
    //  4. Enter the scope URL in the field "One or More API Scopes" (eg. https://www.googleapis.com/auth/drive)
    //  5. Click "Authorize"
    $api['gdrive']['impersonator']          = "";  
    
    // Google Defaults (only change if the API needs upgrading)
    $api['gapis']['oauth']['grant_type']    = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    $api['gapis']['oauth']['token']         = 'https://www.googleapis.com/oauth2/v4/token';
    $api['gapis']['drive']['scope']         = 'https://www.googleapis.com/auth/drive';
    $api['gapis']['drive']['files']         = 'https://www.googleapis.com/drive/v3/files';
    

    // *************************************************************************************************
    // GENERATE OAUTH ACCESS TOKEN
    // only generate another if stored token will expire soon
    
    // check minutes remaining
    $api['jwt']['token']['minutes']=0;
    if(file_exists($api['jwt']['token']['file'])){
        $expiry_time = filemtime($api['jwt']['token']['file']) + 3600;
        $diff = $expiry_time - time();    
        $api['jwt']['token']['minutes'] = floor($diff/60);            
    }
    
    // if at least 5 minutes, then use stored token
    if( $api['jwt']['token']['minutes'] > 5){
        
        $access_token = base64_decode(file_get_contents($api['jwt']['token']['file']));
        
    }else{
        
        // Get JSON file (generated by Google) contents
        $api['keys']['private']['contents']    = json_decode( file_get_contents( $api['keys']['private']['file'] ), true);
    
        // Build token header.  Specify algorithm
        $api['jwt']['header']['alg']           = 'RS256';
        $api['jwt']['header']['typ']           = 'JWT';
    
        // Build token payload for a JSON string
        $api['jwt']['claim_set']['iss']        = $api['keys']['private']['contents']['client_email'];
        if($api['gdrive']['impersonator']!=""){
            $api['jwt']['claim_set']['sub']    = $api['gdrive']['impersonator'];  // only if service account has been authorized
        }
        $api['jwt']['claim_set']['scope']      = $api['gapis']['drive']['scope'];
        $api['jwt']['claim_set']['aud']        = $api['gapis']['oauth']['token'];
        $api['jwt']['claim_set']['exp']        = strtotime('+1 hour');
        $api['jwt']['claim_set']['iat']        = strtotime('now');
    
        // Generate assertion/signature of JWT
        $api['jwt']['assertion']               = rtrim(strtr(base64_encode(json_encode($api['jwt']['header'])), '+/', '-_'), '=');
        $api['jwt']['assertion']              .= ".".rtrim(strtr(base64_encode(json_encode($api['jwt']['claim_set'])), '+/', '-_'), '=');
        
        $result1 = openssl_sign(
            $api['jwt']['assertion'], 
            $signature, 
            openssl_pkey_get_private($api['keys']['private']['contents']['private_key']), 
            'sha256');
        
        
            
        if ($result1 === true){
            $api['jwt']['assertion']          .= "." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        }
    
        // prepare token request
        $url                                   = $api['gapis']['oauth']['token'];
        $header[]                              = 'Content-Type: application/x-www-form-urlencoded';
        $data['grant_type']                    = $api['gapis']['oauth']['grant_type'];
        $data['assertion']                     = $api['jwt']['assertion'];
    
        // send request
        $api['jwt']['token']['response']       = sendGoogleRequest($logger, $url, $header, $data, "POST");
        
        
        // check if response exists
        if(isset($api['jwt']['token']['response']['access_token'])){
    
            // store in var
            $access_token = $api['jwt']['token']['response']['access_token'];
    
            // store in file
            file_put_contents($api['jwt']['token']['file'], base64_encode($access_token));
        
            // reset minutes counter
            $api['jwt']['token']['minutes'] = 59;
        
        }
    }
    
    // no longer needed by this script
    unset($api['keys']);
    unset($api['jwt']);
    unset($api['gapis']['oauth']);
    
    if ($access_token) {     } else { $access_token = ''; }
    
    $result = array();
    $result['access_token'] = $access_token;
    
    return $result;
}



function curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header) {
    global $GAPIS;

    
    $logger->log("curlGoogle");
    $logger->log("googleURL:$googleURL");
    

    $ch1 = curl_init();

    // don't do ssl checks
    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);

    // do other curl stuff
    curl_setopt($ch1, CURLOPT_URL, $googleURL);
    if ($method == 'GET') {
        curl_setopt($ch1, CURLOPT_POST, 0);
    } else {
        curl_setopt($ch1, CURLOPT_POST, 1);
    }
    if ($my_beautiful_body == '') {
        
    } else {
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $my_beautiful_body);
    }
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);

    // set authorization header
    curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
        'Content-Type: multipart/related; boundary=joes_awesome_divider',
        'Content-Length: ' . strlen($my_beautiful_body), 
        'Authorization: Bearer ' . $access_token
        ) 
    );

    // execute cURL request
    $response=curl_exec($ch1);
    if($response === false){
        $output = 'ERROR: '.curl_error($ch1);
    } else{
        $output = $response;
    }

    // close first request handler
    curl_close($ch1);
    
    return $output;
}

function uploadGoogleFile($logger, $access_token, $file, $mime_type, $name, $folder) {
    
    global $GAPIS;
    
    $googleURL = $GAPIS . 'upload/drive/v3/files?uploadType=multipart';
    $method = 'POST';
    $my_beautiful_body = '
--joes_awesome_divider
Content-Type: application/json; charset=UTF-8

{
    "name": "'.$name.'",
    "parents": ["'.$folder.'"]
}

--joes_awesome_divider
Content-Type: '.$mime_type.'

'.file_get_contents($file).'

--joes_awesome_divider--
    ';
    
    
    $logger->log("uploadGoogleFile");
    $logger->log("googleURL:$googleURL");
    $logger->log("my_beautiful_body:$my_beautiful_body");
    $logger->log("method:$method");
    
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    $logger->log($result);
    return $result;
}

function downloadGoogleFile($logger, $access_token, $file) {
    
    global $GAPIS;
    
    // https://www.googleapis.com/drive/v3/files/1Tm5YPyw_8T5lv27qIo3P1R5n4YEevFiy?alt=media

    
    $googleURL = $GAPIS . "drive/v3/files/$file?alt=media";
    $method = 'GET';
    $my_beautiful_body = '';

    
    $logger->log("downloadGoogleFile");
    $logger->log("googleURL:$googleURL");
    $logger->log("my_beautiful_body:$my_beautiful_body");
    $logger->log("method:$method");
    
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    $logger->log($result);
    return $result;
}


function copyGoogleFile($logger, $access_token, $fileId, $new_name, $folder) {
    
    global $GAPIS;
    
    //POST https://www.googleapis.com/drive/v3/files/{fileId}/copy
    
    $googleURL = $GAPIS . "drive/v3/files/".$fileId."/copy";
    $method = 'POST';
    
    $my_beautiful_body = '
{
    "name": "'.$new_name.'",
    "parents": ["'.$folder.'"]
}';
    
    
    $logger->log("copyGoogleFile");
    $logger->log("googleURL:$googleURL");
    $header = array();
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    $logger->log($result);
    return $result;
}

function updateGoogleDoc($logger, $userinfo, $access_token, $fileId) {
    
  global $DOCAPIS;
  
  $googleURL = $DOCAPIS . "v1/documents/".$fileId.":batchUpdate";
  $method = 'POST';
  
  $renderList = createRenderDocList($logger, $access_token, $fileId, $userinfo);
  $my_beautiful_body = $renderList['my_beautiful_body'];
    
  $logger->log("updateGoogleDoc");
  $logger->log("googleURL:$googleURL");
  $header = array();
  $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
  $logger->log($result);
  return $result;
}

function updateGoogleDocUser($logger, $userinfo, $access_token, $fileId, $userdata) {
    
  global $DOCAPIS;
  
  $googleURL = $DOCAPIS . "v1/documents/".$fileId.":batchUpdate";
  $method = 'POST';
  
  $renderList = createRenderDocListUser($logger, $access_token, $fileId, $userinfo, $userdata);
  $my_beautiful_body = $renderList['my_beautiful_body'];
    
  $logger->log("updateGoogleDoc");
  $logger->log("googleURL:$googleURL");
  $header = array();
  $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
  $logger->log($result);
  return $result;
}

function updateGoogleSlide($logger, $userinfo, $access_token, $fileId) {
    
  global $SLIDEAPIS;

  $googleURL = $SLIDEAPIS . "v1/presentations/".$fileId.":batchUpdate";
  $method = 'POST';
  
  $renderList = createRenderSlideList($logger, $access_token, $fileId, $userinfo);
  $my_beautiful_body = $renderList['my_beautiful_body'];
    
  $logger->log("updateGoogleSlide");
  $logger->log("googleURL:$googleURL");
  $header = array();
  $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
  $logger->log($result);
  return $result;
}

function updateGoogleSlideUser($logger, $userinfo, $access_token, $fileId, $userdata) {
    
  global $SLIDEAPIS;

  $googleURL = $SLIDEAPIS . "v1/presentations/".$fileId.":batchUpdate";
  $method = 'POST';
  
  $renderList = createRenderSlideListUser($logger, $access_token, $fileId, $userinfo, $userdata);
  $my_beautiful_body = $renderList['my_beautiful_body'];
    
  $logger->log("updateGoogleSlide");
  $logger->log("googleURL:$googleURL");
  $header = array();
  $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
  $logger->log($result);
  return $result;
}

function getGoogleFile($logger, $access_token, $fileId) {
    
    global $GAPIS;
    //GET https://www.googleapis.com/drive/v3/files/{fileId}
    
    $googleURL = $GAPIS . "drive/v3/files/".$fileId;
    $method = 'GET';
    $my_beautiful_body = '';
    
    
    $logger->log("getGoogleFile");
    $logger->log("googleURL:$googleURL");
    
    $header = array();
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    $logger->log($result);
    return $result;
}

function getGoogleDocInline($logger, $access_token, $fileId) {
    
    global $DOCAPIS;
    //GET https://docs.googleapis.com/v1/documents/1WVAx7enqGaMPNv3WPhjhriKJ13a_0wLnCBtkXiVh9os?suggestionsViewMode=DEFAULT_FOR_CURRENT_ACCESS

    
    $googleURL = $DOCAPIS . "v1/documents/".$fileId."?suggestionsViewMode=DEFAULT_FOR_CURRENT_ACCESS";
    $method = 'GET';
    $my_beautiful_body = '';
    
    
    //$logger->log("getGoogleFileInline");
    //$logger->log("googleURL:$googleURL");
    
    $header = array();
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    //$logger->log($result);
    return $result;
}

function getGoogleSlideInline($logger, $access_token, $fileId) {
    
    global $SLIDEAPIS;
    //GET   'https://slides.googleapis.com/v1/presentations/1LG_4E8vDB5RJbNZVwOHd8z8lluC5YS02w6BH80EgSs0?key=[YOUR_API_KEY]' \
    //GET https://slides.googleapis.com/v1/presentations/{presentationId}


    
    $googleURL = $SLIDEAPIS . "v1/presentations/".$fileId;
    $method = 'GET';
    $my_beautiful_body = '';
    
    
    //$logger->log("getGoogleFileInline");
    //$logger->log("googleURL:$googleURL");
    
    $header = array();
    $result = curlGoogle($logger, $access_token, $googleURL, $method, $my_beautiful_body, $header);
    // $logger->log($result);
    return $result;
}

function getXSlideInlineObject($logger, $access_token, $fileId) {
    $googleFileResult = getGoogleSlideInline($logger, $access_token, $fileId);
    $googleFile = json_decode($googleFileResult, true);
    
    $inlineDetail = array();
    
    $slides = $googleFile['slides'];
    
    $logger->log("getSlideInlineObject slides : ".json_encode($slides));
    
    foreach($slides as $slide) {
        
        $pageElements = $slide['pageElements'];
        
        foreach($pageElements as $pageElement) {
          $objectId = $pageElement["objectId"];
          $title = $pageElement["title"];
          
          if ($title) {
    		$title = trim(strtolower($title));
            $inlineItem['objectId'] = $objectId;
            $inlineItem['title'] = $title;
            $inlineDetail[] = $inlineItem;
          }
        }
        
        
    }
    
    $logger->log("getSlideInlineObject inlineDetail : ".json_encode($inlineDetail));
    
    return $inlineDetail;
}

function getDocInlineObject($logger, $access_token, $fileId) {
    $googleFileResult = getGoogleDocInline($logger, $access_token, $fileId);
    $googleFile = json_decode($googleFileResult, true);
    
    $inlineDetail = array();
    $outputDetail = array();
    
    $inlineObjects = $googleFile['inlineObjects'];
    foreach($inlineObjects as $inlineObject) {
        
        $objectId = $inlineObject['objectId'];
        //echo "objectId : $objectId<br>";
        
        $inlineObjectProperties = $inlineObject['inlineObjectProperties'];
        foreach($inlineObjectProperties as $inlineObjectPropertie) {
            $title = $inlineObjectPropertie["title"];
            // echo "title : $title<br>";
        }
        
        $inlineItem['objectId'] = $objectId;
        $inlineItem['title'] = $title;
        $inlineDetail[] = $inlineItem;
    }
    
    $inlineStartDetail = array();
    $contentObjects = $googleFile['body']['content'];
    foreach($contentObjects as $contentObject) {
      $paragraph = $contentObject['paragraph'];  
      if ($paragraph) {
        $elements = $paragraph['elements'];
        foreach($elements as $element) {
          $startIndex = $element['startIndex'];
          $endIndex = $element['endIndex'];
          $inlineObjectElement = $element['inlineObjectElement'];
          $inlineObjectId = $inlineObjectElement['inlineObjectId'];
          if ($inlineObjectId != '') {
            // echo "startIndex : $startIndex , inlineObjectId : $inlineObjectId<br>";
            $inlineStartItem['inlineObjectId'] = $inlineObjectId;
            $inlineStartItem['startIndex'] = $startIndex;
            $inlineStartItem['endIndex'] = $endIndex;
            $inlineStartDetail[] = $inlineStartItem;
          }
        }
      }
    }
    
    foreach ($inlineDetail as $item1) {
      $objectId = $item1['objectId'];
      $title = $item1['title'];
      $objectIdLower = strtolower($objectId);
      foreach ($inlineStartDetail as $item2) {
        $inlineObjectId = strtolower($item2['inlineObjectId']);
        if ($objectIdLower == $inlineObjectId) {
          $startIndex = $item2['startIndex'];
          $endIndex = $item2['endIndex'];
          $outputItem['objectId'] = $objectId;
          $outputItem['title'] = $title;
          $outputItem['startIndex'] = $startIndex;
          $outputItem['endIndex'] = $endIndex;
          $outputDetail[] = $outputItem;
          break;
        }
      }
    }
    
    // echo "<br>";
    // var_dump ($outputDetail);
    //  echo "<br>";
    
    return $outputDetail;
}


function renderFile($logger, $googleContent, $userinfo) {
    
    $prepareResult = prepareRenderList($logger, $userinfo);
    $replaceDetail = $prepareResult['replaceDetail'];
    
    foreach ($replaceDetail as $replaceItem) {
      $text = $replaceItem['text'];
      $replaceText = $replaceItem['replaceText'];
      
      // echo "text : $text , replaceText : $replaceText<br>";
      
      $googleContent = str_ireplace($text, $replaceText, $googleContent);
      
    }
  
  // echo "<br>googleContent : $googleContent<br>";
  
  return $googleContent;
}

function renderFileUser($logger, $googleContent, $userinfo, $userdata) {
    
    $prepareResult = prepareRenderListUser($logger, $userinfo, $userdata);
    $replaceDetail = $prepareResult['replaceDetail'];
    
    foreach ($replaceDetail as $replaceItem) {
      $text = $replaceItem['text'];
      $replaceText = $replaceItem['replaceText'];
      
      // echo "text : $text , replaceText : $replaceText<br>";
      
      $googleContent = str_ireplace($text, $replaceText, $googleContent);
      
    }
    
    $acctID = $userdata['acctID'];
    
    
	$variablesList = getStorefrontVariables($acctID);
	foreach ($variablesList as $variables) {
	  	$variableName = $variables['variableName'];
	  	$variableType = $variables['variableType'];
	  	$variableValue = $variables['variableValue'];
	  	$variableTemplate = $variables['variableTemplate'];
	  	
	  	if ($variableType == 'Image') {
			$text = '{{'.$variableName.'}}';
			$googleContent = str_ireplace($text, $variableValue, $googleContent);
		}
	  	
	}
  
  // echo "<br>googleContent : $googleContent<br>";
  
  return $googleContent;
}

function prepareRenderListUser($logger, $userinfo, $userdata) {
  
  global $email;
  $logger1 = new FileLogger("testGoogle.log"); 
  
  $acctID = $userdata['acctID'];
  $variablesList = getStorefrontVariables($acctID);
  $logger1->log("acctID : $acctID");
  $logger1->log("variablesList : ".json_encode($variablesList));
  
  $docData = $userinfo['docData'];
  $users = $docData['users'];
  
  $defCompanyLogo = $userinfo['defCompanyLogo'];
  
  $defCompanyName = $userinfo['defCompanyName'];
  $defCompanyAddress = $userinfo['defCompanyAddress'];
  $defCompanyWebsite = $userinfo['defCompanyWebsite'];
  $defCompanyPhone = $userinfo['defCompanyPhone'];
  $defCompanyPrivacyPolicy = $userinfo['defCompanyPrivacyPolicy'];
  $defButtonColor = $userinfo['defButtonColor'];
  $defFromName = $userinfo['defFromName'];
  $defFromAdd = $userinfo['defFromAdd'];
  
  
  $userImage = $userdata['userImage'];
  if ($userImage == '') {
    $userImage = 'https://s3-us-west-1.amazonaws.com/mfdavinci/image/person.jpg';  
  }
  $userPhone = $userdata['userPhone'];
  $userTitle = $userdata['userTitle'];
  $userEmail = $userdata['userEmail'];
  $userName = $userdata['userName'];
    
  $logger1->log("userdata : ".json_encode($userdata));
	
  $result = array();
  if ($userName == '') {
      return $result;
  }
  $replaceDetail = array();
  
  
  $colorList = array();
  $fontColorList = array();
  $colors = convertColor($logger, $defButtonColor);
  $blueCode = $colors['blueCode'];
  $greenCode = $colors['greenCode'];
  $redCode = $colors['redCode'];
  $colorListItem['blueCode'] = $blueCode;
  $colorListItem['greenCode'] = $greenCode;
  $colorListItem['redCode'] = $redCode;
  $colorListItem['colorName'] = 'company_color';
  $colorList[] = $colorListItem;
  
  $templateListItem['colorName'] = 'company_color';
  $templateListItem['blueCode'] = $blueCode;
  $templateListItem['greenCode'] = $greenCode;
  $templateListItem['redCode'] = $redCode;
  $colors = convertColor($logger, '#F2673A');
  $blueCode = $colors['blueCode'];
  $greenCode = $colors['greenCode'];
  $redCode = $colors['redCode'];
  $templateListItem['blueTemplate'] = $blueCode;
  $templateListItem['greenTemplate'] = $greenCode;
  $templateListItem['redTemplate'] = $redCode;
  $fontColorList[] = $templateListItem;
  
  foreach ($variablesList as $variables) {
  	$variableName = $variables['variableName'];
  	$variableType = $variables['variableType'];
  	$variableValue = $variables['variableValue'];
  	$variableTemplate = $variables['variableTemplate'];
  	
  	if ($variableType == 'Text') {
  		$replaceItem['text'] = '{{'.$variableName.'}}';
		$replaceItem['replaceText'] = $variableValue;
		$replaceDetail[] = $replaceItem;
  	} else if ($variableType == 'Color') {
  		$colors = convertColor($logger, $variableValue);
  		$blueCode = $colors['blueCode'];
  		$greenCode = $colors['greenCode'];
  		$redCode = $colors['redCode'];
  		$colorListItem['blueCode'] = $blueCode;
		$colorListItem['greenCode'] = $greenCode;
		$colorListItem['redCode'] = $redCode;
		$colorListItem['colorName'] = strtolower($variableName);
		$colorList[] = $colorListItem;	
  	} else if ($variableType == 'FontColor') {
  		$colors = convertColor($logger, $variableValue);
  		$blueCode = $colors['blueCode'];
  		$greenCode = $colors['greenCode'];
  		$redCode = $colors['redCode'];
  		$templateListItem['colorName'] = strtolower($variableName);
  		$templateListItem['blueCode'] = $blueCode;
		$templateListItem['greenCode'] = $greenCode;
		$templateListItem['redCode'] = $redCode;
		$colors = convertColor($logger, $variableTemplate);
		$blueCode = $colors['blueCode'];
  		$greenCode = $colors['greenCode'];
  		$redCode = $colors['redCode'];
		$templateListItem['blueTemplate'] = $blueCode;
		$templateListItem['greenTemplate'] = $greenCode;
		$templateListItem['redTemplate'] = $redCode;
		
		$fontColorList[] = $templateListItem;
  	}
  }
  
  $replaceItem['text'] = '{{company_name}}';
  $replaceItem['replaceText'] = $defCompanyName;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_address}}';
  $replaceItem['replaceText'] = $defCompanyAddress;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_url}}';
  $replaceItem['replaceText'] = $defCompanyWebsite;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_phone}}';
  $replaceItem['replaceText'] = $defCompanyPhone;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_color}}';
  $replaceItem['replaceText'] = $defButtonColor;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_privacy_policy}}';
  $replaceItem['replaceText'] = $defCompanyPrivacyPolicy;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_email}}';
  $replaceItem['replaceText'] = $userEmail;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_phone}}';
  $replaceItem['replaceText'] = $userPhone;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_title}}';
  $replaceItem['replaceText'] = $userTitle;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_logo}}';
  $replaceItem['replaceText'] = $defCompanyLogo;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_image}}';
  $replaceItem['replaceText'] = $userImage;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_name}}';
  $replaceItem['replaceText'] = $userName;
  $replaceDetail[] = $replaceItem;
  
  $result['replaceDetail'] = $replaceDetail;
  $result['userImage'] = $userImage;
  $result['defCompanyLogo'] = $defCompanyLogo;
  $result['colorList'] = $colorList;
  $result['fontColorList'] = $fontColorList;
  
  $logger1->log("result : ".json_encode($result));
  
  return $result;
  
}

function prepareRenderList($logger, $userinfo) {
  
  global $email;
  
  $docData = $userinfo['docData'];
  $users = $docData['users'];
  
  $defCompanyLogo = $userinfo['defCompanyLogo'];
  
  $defCompanyName = $userinfo['defCompanyName'];
  $defCompanyAddress = $userinfo['defCompanyAddress'];
  $defCompanyWebsite = $userinfo['defCompanyWebsite'];
  $defCompanyPhone = $userinfo['defCompanyPhone'];
  $defCompanyPrivacyPolicy = $userinfo['defCompanyPrivacyPolicy'];
  $defButtonColor = $userinfo['defButtonColor'];
  $defFromName = $userinfo['defFromName'];
  $defFromAdd = $userinfo['defFromAdd'];
  
  $userImage = 'https://s3-us-west-1.amazonaws.com/mfdavinci/image/person.jpg';
  $userPhone = '';
  $userTitle = '';
  $userEmail = '';
  $userName = '';
    
	foreach( $users as $user ) {	
	    $emailLower = strtolower($user['email']);
		if ($emailLower ==  strtolower($email)) {
		    if ($user['Title']) { $userTitle = $user['Title']; }
		    if ($user['Cell']) { $userPhone = $user['Cell']; }
		    if ($user['image']) { $userImage = $user['image']; }
		    if ($user['name']) { $userName = $user['name']; }
		    if ($user['email']) { $userEmail = $user['email']; }
			break;
		}
	}
  $result = array();
  if ($userName == '') {
      return $result;
  }
  $replaceDetail = array();
  $replaceItem['text'] = '{{company_name}}';
  $replaceItem['replaceText'] = $defCompanyName;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_address}}';
  $replaceItem['replaceText'] = $defCompanyAddress;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_url}}';
  $replaceItem['replaceText'] = $defCompanyWebsite;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_phone}}';
  $replaceItem['replaceText'] = $defCompanyPhone;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_privacy_policy}}';
  $replaceItem['replaceText'] = $defCompanyPrivacyPolicy;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_color}}';
  $replaceItem['replaceText'] = $defButtonColor;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_email}}';
  $replaceItem['replaceText'] = $userEmail;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_phone}}';
  $replaceItem['replaceText'] = $userPhone;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_title}}';
  $replaceItem['replaceText'] = $userTitle;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{company_logo}}';
  $replaceItem['replaceText'] = $defCompanyLogo;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_image}}';
  $replaceItem['replaceText'] = $userImage;
  $replaceDetail[] = $replaceItem;
  
  $replaceItem['text'] = '{{user_name}}';
  $replaceItem['replaceText'] = $userName;
  $replaceDetail[] = $replaceItem;
  
  $result['replaceDetail'] = $replaceDetail;
  $result['userImage'] = $userImage;
  $result['defCompanyLogo'] = $defCompanyLogo;
  return $result;
  
}

function createRenderList($logger, $access_token, $fileId, $userinfo, $inlineDetails, $docType) {
}


function divideFloat($a, $b, $precision=3) {
    $a*=pow(10, $precision);
    $result=(int)($a / $b);
    if (strlen($result)==$precision) return '0.' . $result;
    else return preg_replace('/(\d{' . $precision . '})$/', '.\1', $result);
}


function convertColor($logger, $hex) {
    // Remove the hash (#) if it's present
    $hex = ltrim($hex, '#');

    // Initialize RGB values
    $r = $g = $b = 0;

    // Check if the hex code is valid
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
    } elseif (strlen($hex) === 3) {
        // Handle shorthand hex code
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2)) / 255;
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2)) / 255;
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2)) / 255;
    } else {
        // Invalid hex code
        return null;
    }
    
    // Format RGB values to 7 decimal places
    return [
        'redCode' => number_format($r, 7, '.', ''),
        'greenCode' => number_format($g, 7, '.', ''),
        'blueCode' => number_format($b, 7, '.', '')
    ];
}

function convertColor1($logger, $defButtonColor) {
	
	
	if (substr($defButtonColor, 0, 1) === '#') {
        $defButtonColor = substr($defButtonColor, 1);
    }
    
    $logger->log("defButtonColor - $defButtonColor");
	
	$redCodeHex = hexdec($defButtonColor[0].$defButtonColor[1]);
	$greenCodeHex = hexdec($defButtonColor[2].$defButtonColor[3]);
	$blueCodeHex = hexdec($defButtonColor[4].$defButtonColor[5]);
	// echo "redCode : $redCodeHex , greenCode : $greenCodeHex , blueCode : $blueCodeHex<br><br>";
	
	$redCode = divideFloat($redCodeHex , 255 , 7);
	$greenCode = divideFloat($greenCodeHex, 255, 7);
	$blueCode = divideFloat($blueCodeHex, 255, 7);
	
	// $logger->log("redCode : $redCode , greenCode : $greenCode , blueCode : $blueCode");
	// echo "redCode : $redCode , greenCode : $greenCode , blueCode : $blueCode<br><br>";
	
	
	$result['blueCode'] = $blueCode;
	$result['greenCode'] = $greenCode;
	$result['redCode'] = $redCode;
	
	$logger->log(" result - ".json_encode($result));
	
	// echo "result<br>";
    // var_dump($result);
    // echo "<br><br>";
	
	
	return $result;
}


function createRenderListUser($logger, $access_token, $fileId, $userinfo, $inlineDetails, $docType, $userdata, $extraData) {
  $prepareResult = prepareRenderListUser($logger, $userinfo, $userdata);
  $replaceDetail = $prepareResult['replaceDetail'];
  $userImage = $prepareResult['userImage'];
  $defCompanyLogo = $prepareResult['defCompanyLogo'];
  $colorList = $prepareResult['colorList'];
  $fontColorList = $prepareResult['fontColorList'];
  
  $defButtonColor = $prepareResult['defButtonColor'];
  
  
  $acctID = $userdata['acctID'];
  $variablesList = getStorefrontVariables($acctID);
  
  // echo "userImage : $userImage<br>";
  
  
  // echo "inlineDetails<br>";
  // var_dump($inlineDetails);
  // echo "<br>";
	
	$docName = 'url';
	if ($docType == 'doc') {
	  $docName = 'uri';
	}
	
	$inlineCode = '';
	
	
	
	
	//$logger->log("createRenderListUser redCode : $redCode , greenCode : $greenCode , blueCode : $blueCode");
	
	
	
	
  foreach ($inlineDetails as $inlineDetail) {
      $imageObjectId = $inlineDetail['objectId'];
      $title = $inlineDetail['title'];
      
      $logger->log("inlineDetail imageObjectId : $imageObjectId , title : $title");
      
      $imageFile = '';
      $isColor = false;
      foreach ($colorList as $colorListItem) {
      	$colorName = $colorListItem['colorName'];
      	
      	$colorName = '{{'.$colorName.'}}';
		if (strtolower($colorName) == strtolower($title)) {
      		$blueCode = $colorListItem['blueCode'];
			$greenCode = $colorListItem['greenCode'];
			$redCode = $colorListItem['redCode'];
			$colorName = $colorListItem['colorName'];
			$isColor = true;
			break;
      	}
      }
      
      if ($isColor == true) {
      	
      	$shapeType = $inlineDetail['shapeType'];
      	
	      	if ( ($shapeType == 'RECTANGLE') || ($shapeType == 'TEXT_BOX') || ($shapeType == 'RIGHT_TRIANGLE') || ($shapeType == 'TRIANGLE') || ($shapeType == 'ROUND_RECTANGLE') || ($shapeType == 'ROUND_2_SAME_RECTANGLE') || ($shapeType == 'ELLIPSE')  || ($shapeType == 'CUSTOM') || ($shapeType == '') ){
	      		$inlineCode = $inlineCode.
		      		',{
			            "updateShapeProperties": {
			            "objectId": "'.$imageObjectId.'",
			            "shapeProperties": {
			                 "shapeBackgroundFill": {
			                	"propertyState": "RENDERED",
			            		"solidFill": {
			                	"color": {
			                      "rgbColor": {
			                         "blue": '.$blueCode.',
			            			 "green": '.$greenCode.',
			            			 "red": '.$redCode.'
			            		  }
			            		},
			            		"alpha": 1
			            	  }
			            	}
		        		},
		        		"fields": "shapeBackgroundFill.solidFill.color,outline"
		    		}
		    	}';
	    	} else if ($shapeType == 'LINE') {
	    		$inlineCode = $inlineCode.
		      		',{
			            "updateLineProperties": {
			            "objectId": "'.$imageObjectId.'",
			            "lineProperties": {
			                 "lineFill": {
			            		"solidFill": {
			                	"color": {
			                      "rgbColor": {
			                         "blue": '.$blueCode.',
			            			 "green": '.$greenCode.',
			            			 "red": '.$redCode.'
			            		  }
			            		},
			            		"alpha": 1
			            	  }
			            	}
		        		},
		        		"fields": "lineFill.solidFill.color"
		    		}
		    	}';
	    	}
      	
      	
      } else {
      
    		
    	  $imageFile = '';	
	      if ($title == '{{user_image}}') {
	          $imageFile = $userImage;
	      } else if ($title == '{{company_logo}}') {
	          $imageFile = $defCompanyLogo;
	      } else {
	          foreach ($variablesList as $variables) {
			  	$variableName = $variables['variableName'];
			  	$variableType = $variables['variableType'];
			  	$variableValue = $variables['variableValue'];
			  	$variableTemplate = $variables['variableTemplate'];
			  	
			  	if ($variableType == 'Image') {
			  		$text = '{{'.$variableName.'}}';
			  		if (strtolower($text) == strtolower($title)) {
			  			$imageFile = $variableValue;
			  			break;
			  		}
			  	}
			  }
	      }
	    	// echo "title : $title, imageFile : $imageFile<br>";
	      if ($imageFile != '') {
	        if ($docType == 'doc') {
	          $startIndex = $inlineDetail['startIndex'];
	          $endIndex = $inlineDetail['endIndex'];
	          $inlineCode = $inlineCode.
	              ',{
	                  "deleteContentRange": {
	                    "range" : {
	                      "startIndex" : "'.$startIndex.'",
	                      "endIndex" : "'.$endIndex.'"
	                    }
	                  }              
	                },
	                {
	                  "insertInlineImage": {
	                    "location": { "index" : '.$startIndex.'},
	                    "objectSize": { "width" : { "magnitude" : 100 , "unit" : "PT"} },
	                    "'.$docName.'": "'.$imageFile.'"
	                  }
	                }';
	        } else {
	          $inlineCode = $inlineCode.
	          ',
	          {
	              "replaceImage": {
	                  "imageObjectId": "'.$imageObjectId.'",
	                  "'.$docName.'": "'.$imageFile.'",
	                  "imageReplaceMethod": "IMAGE_REPLACE_METHOD_UNSPECIFIED"
	              }
	          }';
	        }
	      }
      }	     
  }
  
  
  
  
  
  
  
  $inlineTexts = $extraData['inlineText'];
  if ($inlineTexts) {
	foreach ($inlineTexts as $inlineText) {
		
		
		$objectId = $inlineText['objectId'];
		$startIndex = $inlineText['startIndex'];
		$endIndex = $inlineText['endIndex'];
		$blueCode = $inlineText['blueCode'];
		$greenCode = $inlineText['greenCode'];
		$redCode = $inlineText['redCode'];
		
		$inlineCode = $inlineCode.
	      		',{
		            "updateTextStyle": {
			        "fields": "foregroundColor",
			        "objectId": "'.$objectId.'",
			        "style": {
			          "foregroundColor": {
			            "opaqueColor": {
			              "rgbColor": {
			                "blue": '.$blueCode.',
		            		"green": '.$greenCode.',
		            		"red": '.$redCode.'
			              }
			            }
			          }
			        },
			        "textRange": {
			          "type": "FIXED_RANGE",
			          "startIndex": '.$startIndex.',
			          "endIndex": '.$endIndex.'
			        }
	    		}
	    	}';
		
	}
  }
  	// echo "inlineCode : $inlineCode<br>";
  
  
  $replaceCode = '';
  foreach ($replaceDetail as $replaceItem) {
      $text = $replaceItem['text'];
      $replaceText = $replaceItem['replaceText'];
      $replaceCode = $replaceCode.
      ',
      {
        "replaceAllText": {
          "containsText": {
            "text": "'.$text.'",
            "matchCase": false
          },
          "replaceText": "'.$replaceText.'"
        }
      }';
      
  }
  $replaceCode = trim($replaceCode,",");
  $inlineCode = trim($inlineCode,",");
  
  $logger->log("replaceCode:$replaceCode");
  $logger->log("inlineCode:$inlineCode");
  
  $my_beautiful_body = '';
  
  
    if ($inlineCode != '') {
        $my_beautiful_body = $my_beautiful_body.$inlineCode;
    }
    $logger->log("my_beautiful_body:$my_beautiful_body");
    if ($replaceCode != '') {
    	if ($my_beautiful_body != '') {
    		$my_beautiful_body = $my_beautiful_body.','.$replaceCode;
    	} else {
        	$my_beautiful_body = $my_beautiful_body.$replaceCode;
    	}
    }
    $my_beautiful_body = '{"requests": ['.$my_beautiful_body.']}';

$logger->log("my_beautiful_body:$my_beautiful_body");

  $result['my_beautiful_body'] = $my_beautiful_body;
  $result['inlineDetails'] = $inlineDetails;
  $result['replaceDetail'] = $replaceDetail;
  $result['replaceCode'] = $replaceCode;
  $result['inlineCode'] = $inlineCode;
  $result['colorList'] = $colorList;
  $result['fontColorList'] = $fontColorList;
  
  return $result;
}

function createRenderDocList($logger, $access_token, $fileId, $userinfo) {
  $prepareResult = prepareRenderList($logger, $userinfo);
  $replaceDetail = $prepareResult['replaceDetail'];
  $userImage = $prepareResult['userImage'];
  $defCompanyLogo = $prepareResult['defCompanyLogo'];
	
	
  $inlineDetails = getDocInlineObject($logger, $access_token, $fileId);
  $renderList = createRenderList($logger, $access_token, $fileId, $userinfo, $inlineDetails, 'doc');
  
  $inlineCode = $renderList['inlineCode'];
  $replaceCode = $renderList['replaceCode'];
  $my_beautiful_body = $renderList['my_beautiful_body'];
  

  $result['my_beautiful_body'] = $my_beautiful_body;
  $result['inlineDetails'] = $inlineDetails;
  $result['replaceDetail'] = $replaceDetail;
  $result['replaceCode'] = $replaceCode;
  $result['inlineCode'] = $inlineCode;
  
  return $result;
}

function createRenderDocListUser($logger, $access_token, $fileId, $userinfo, $userdata) {
  $prepareResult = prepareRenderList($logger, $userinfo);
  $replaceDetail = $prepareResult['replaceDetail'];
  $userImage = $prepareResult['userImage'];
  $defCompanyLogo = $prepareResult['defCompanyLogo'];
	
	
	$inlineDetails = getDocInlineObject($logger, $access_token, $fileId);
	$extraData['inlineText'] = null;
	$renderList = createRenderListUser($logger, $access_token, $fileId, $userinfo, $inlineDetails, 'doc', $userdata, $extraData);
  
  $inlineCode = $renderList['inlineCode'];
  $replaceCode = $renderList['replaceCode'];
  $my_beautiful_body = $renderList['my_beautiful_body'];
  

  $result['my_beautiful_body'] = $my_beautiful_body;
  $result['inlineDetails'] = $inlineDetails;
  $result['replaceDetail'] = $replaceDetail;
  $result['replaceCode'] = $replaceCode;
  $result['inlineCode'] = $inlineCode;
  
  return $result;
}


function createRenderSlideList($logger, $access_token, $fileId, $userinfo) {
  
  
  $prepareResult = prepareRenderList($logger, $userinfo);
  $replaceDetail = $prepareResult['replaceDetail'];
  $userImage = $prepareResult['userImage'];
  $defCompanyLogo = $prepareResult['defCompanyLogo'];
  
  $inlineDetails = getXSlideInlineObject($logger, $access_token, $fileId);
  $renderList = createRenderList($logger, $access_token, $fileId, $userinfo, $inlineDetails, 'slide');
  
  $inlineCode = $renderList['inlineCode'];
  $replaceCode = $renderList['replaceCode'];
  $my_beautiful_body = $renderList['my_beautiful_body'];
  
	
  $result['my_beautiful_body'] = $my_beautiful_body;
  $result['inlineDetails'] = $inlineDetails;
  $result['replaceDetail'] = $replaceDetail;
  $result['replaceCode'] = $replaceCode;
  $result['inlineCode'] = $inlineCode;
  
  return $result;
}


function getSlideInlineObject($logger, $access_token, $fileId, $acctID, $colorList, $fontColorList) {
    $googleFileResult = getGoogleSlideInline($logger, $access_token, $fileId);
    $googleFile = json_decode($googleFileResult, true);
    
    $inlineDetail = array();
    $inlineText = array();
    $inlineLine = array();
    
    $precision = 6;
    
    $slides = $googleFile['slides'];
    
    $logger->log("getSlideInlineObject slides : ".json_encode($slides));
    
    $variablesList = getStorefrontVariables($acctID);
    
    foreach($slides as $slide) {
        
        $pageElements = $slide['pageElements'];
        
        foreach($pageElements as $pageElement) {
          $objectId = $pageElement["objectId"];
          $title = $pageElement["description"];
          
          $elementGroups = $pageElement["elementGroup"];
          
          if ($elementGroups) {
          	$children = $elementGroups['children'];
          	foreach($children as $child) {
          		$objectId = $child["objectId"];
          		$shapeType = strtoupper($child['shape']['shapeType']);
          		$title = $child["description"];
          		if ($shapeType == 'RECTANGLE') {
          			
          			$inlineItem['objectId'] = $objectId;
	            	$inlineItem['title'] = $title;
	            	$inlineItem['shapeType'] = $shapeType;
	            	$inlineDetail[] = $inlineItem;
          		} else if ( ($shapeType == 'TEXT_BOX') || ($shapeType == 'RIGHT_TRIANGLE') || ($shapeType == 'TRIANGLE') || ($shapeType == 'ROUND_RECTANGLE') || ($shapeType == 'ROUND_2_SAME_RECTANGLE') || ($shapeType == 'ELLIPSE')  || ($shapeType == 'CUSTOM') || ($shapeType == '') ){
          		
	            	
	            	$isColor = false;
	            	foreach ($colorList as $colorListItem) {
					  	$colorName = $colorListItem['colorName'];
					  	
					  	$colorName = '{{'.$colorName.'}}';
						if (strtolower($colorName) == strtolower($title)) {
					  		$blueCode = $colorListItem['blueCode'];
							$greenCode = $colorListItem['greenCode'];
							$redCode = $colorListItem['redCode'];
							$colorName= $colorListItem['colorName'];
							$isColor = true;
							break;
					  	}
					}
				  
				    if ($isColor == true) {
                		$inlineItem['objectId'] = $objectId;
        				$inlineItem['title'] = $title;
        				$inlineItem['shapeType'] = $shapeType;
        				$inlineDetail[] = $inlineItem;
				    } 
	            	
          		}
          		
          	}
          	
          } else {
          	
          
          
	          if ($title) {
	    		$title = trim(strtolower($title));
	    		$shapeType = strtoupper($pageElement['shape']['shapeType']);
	    		if ($shapeType) {
	    		} else {
	    			$lineType = $pageElement['line'];
	            	if ($lineType) { $shapeType = 'LINE'; } else {  $shapeType = ''; }
	    		}
	    		
	            $inlineItem['objectId'] = $objectId;
	            $inlineItem['title'] = $title;
	            $inlineItem['shapeType'] = $shapeType;
	            $inlineDetail[] = $inlineItem;
	            
	            
	          } else {
	            
	            
	            
	            $shapeType = strtoupper($pageElement['shape']['shapeType']);
	            $lineType = $pageElement['line'];
	            if ($lineType) { $shapeType = 'LINE'; } else { }
	            
	            if ( ($shapeType == 'TEXT_BOX') || ($shapeType == 'RIGHT_TRIANGLE') || ($shapeType == 'TRIANGLE') || ($shapeType == 'ROUND_RECTANGLE') || ($shapeType == 'ROUND_2_SAME_RECTANGLE') || ($shapeType == 'ELLIPSE')  || ($shapeType == 'CUSTOM') || ($shapeType == '') ){
	            	$title = $pageElement['title'];
	                $textElements = $pageElement['shape']['text']['textElements'];
	                $startIndex = 0;
	                $endIndex = 0;
	                $redCode = 0;
	                $greenCode = 0;
	                $blueCode = 0;
	                foreach($textElements as $textElement) {
	                    $startIndex = $textElement['startIndex'];
	                    if ($startIndex) { } else { $startIndex = 0; }
	                    $endIndex = $textElement['endIndex'];
	                    $redCode = $textElement['textRun']['style']['foregroundColor']['opaqueColor']['rgbColor']['red'];
	                    $greenCode = $textElement['textRun']['style']['foregroundColor']['opaqueColor']['rgbColor']['green'];
	                    $blueCode = $textElement['textRun']['style']['foregroundColor']['opaqueColor']['rgbColor']['blue'];
	                    
	                    $isColor = false;
		            	foreach ($fontColorList as $colorListItem) {
						  	$colorName = $colorListItem['colorName'];
						  	$blueTemplate = $colorListItem['blueTemplate'];
						    $greenTemplate = $colorListItem['greenTemplate'];
						    $redTemplate = $colorListItem['redTemplate'];
						    
							if ((compareDecimals($redCode, $redTemplate, $precision)) && (compareDecimals($greenCode, $greenTemplate, $precision)) && (compareDecimals($blueCode, $blueTemplate, $precision))) {
						  		$blueTemplate = $colorListItem['blueCode'];
						    	$greenTemplate = $colorListItem['greenCode'];
						    	$redTemplate = $colorListItem['redCode'];
								$isColor = true;
								break;
						  	}
						}
					  
					    if ($isColor == true) {
	                		$inlineTextItem = array();
	                    //if (($redCode == 0.9490196) && ($greenCode == 0.40392157) && ($blueCode == 0.22745098)) {
	                        $inlineTextItem['objectId'] = $objectId;
	                        $inlineTextItem['endIndex'] = $endIndex;
	                        $inlineTextItem['startIndex'] = $startIndex;
	                        $inlineTextItem['redCode'] = $redTemplate;
	                        $inlineTextItem['greenCode'] = $greenTemplate;
	                        $inlineTextItem['blueCode'] = $blueTemplate;
	                        $inlineTextItem['shapeType'] = $shapeType;
	                        $inlineTextItem['colorType'] = 'foregroundColor';
	                        $inlineTextItem['colorName'] = $colorName;
	                        $inlineText[] = $inlineTextItem;            
	                    } 
	                }
	            }
	              
	          }
          }
        }
        
        
    }
    
    $masters = $googleFile['masters'];
    
    // $logger->log("getSlideInlineObject masters : ".json_encode($masters));
    
    foreach($masters as $master) {
    	$pageElements = $master['pageElements'];
    	
    	// $logger->log("getSlideInlineObject pageElements : ".json_encode($pageElements));
    	
        foreach($pageElements as $pageElement) {
        	
        	
          $logger->log("getSlideInlineObject pageElement : ".json_encode($pageElement));
          
          $objectId = $pageElement["objectId"];
          $title = $pageElement["title"];
          
          if ($objectId) { } else { $objectId=''; }
          if ($title) { } else { $title=''; }
          
          // $logger->log("getSlideInlineObject objectId : $objectId, title : $title");
          
          if ($title != '') {
          		$inlineItem['objectId'] = $objectId;
	            $inlineItem['title'] = $title;
				$inlineItem['shapeType'] = '';
				$inlineDetail[] = $inlineItem;
          }
        }
    }
    
    $logger->log("getSlideInlineObject inlineDetail : ".json_encode($inlineDetail));
    
    $result['inlineDetail'] = $inlineDetail;
    $result['inlineText'] = $inlineText;
    $result['inlineLine'] = $inlineLine;
    return $result;
}

function createRenderSlideListUser($logger, $access_token, $fileId, $userinfo, $userdata) {
  
  
  $prepareResult = prepareRenderListUser($logger, $userinfo, $userdata);
  $replaceDetail = $prepareResult['replaceDetail'];
  $userImage = $prepareResult['userImage'];
  $defCompanyLogo = $prepareResult['defCompanyLogo'];
  $colorList = $prepareResult['colorList'];
  $fontColorList = $prepareResult['fontColorList'];
  
  $acctID = $userdata['acctID'];
  
  $inlineDetail = getSlideInlineObject($logger, $access_token, $fileId, $acctID, $colorList, $fontColorList);
  $inlineDetails = $inlineDetail['inlineDetail'];
  $inlineText = $inlineDetail['inlineText'];
  
  $extraData['inlineText'] = $inlineText;
  
  $renderList = createRenderListUser($logger, $access_token, $fileId, $userinfo, $inlineDetails, 'slide', $userdata, $extraData);
  
  $inlineCode = $renderList['inlineCode'];
  $replaceCode = $renderList['replaceCode'];
  $my_beautiful_body = $renderList['my_beautiful_body'];
  
	
  $result['my_beautiful_body'] = $my_beautiful_body;
  $result['inlineDetails'] = $inlineDetails;
  $result['replaceDetail'] = $replaceDetail;
  $result['replaceCode'] = $replaceCode;
  $result['inlineCode'] = $inlineCode;
  $result['fontColorList'] = $fontColorList;
  
  return $result;
}

function copyGoogleAsset($logger, $access_token, $acctID, $productCode, $userinfo, $userEmail, $folder) {
    
        $defFromAdd = $userinfo['defFromAdd'];
    
    if ($userEmail) { } else { $userEmail = $defFromAdd; }
    $asssetResult = array();
    
    if($db == null){
		$db = GetPDOMySQL();
	}
	try {  
		$jsonRow = null;
		$sqltxt = "select * from DV_Google_Asset where productCode=?";
		$Stmt = MySQL_Prepare($db,$sqltxt);		
		$Stmt->execute(array($productCode));
		$affected_rows = $Stmt->rowCount();
		
		while ($row2 = $Stmt->fetch()) {		
			$fileId = $row2['assettID'];
			$assetType = $row2['assetType'];
			
			$docLink = '';
			$new_name = '';
			
			if ($assetType == 'html') {
			    // html start
    
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $docName = $response['name'];
                $docName = substr($docName, 0, strrpos($docName, '.'));
            
                
                $mime_type = $response['mimeType'];
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1.'.html';
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                
                $googleContent = downloadGoogleFile($logger, $access_token, $fileId);
                // echo "downloadGoogleFile<br>";
                // var_dump($googleContent);
                // echo "<br>";
                
                $renderContent = renderFile($logger, $googleContent, $userinfo);
                // echo "renderFile<br>";
                // var_dump($renderContent);
                // echo "<br>";
                
                $fname = '/efs/sessions/'.$t1;
                
                file_put_contents($fname, $renderContent);
                
                $result = uploadGoogleFile($logger, $access_token, $fname, $mime_type, $new_name, $folder);
                // echo "uploadGoogleFile<br>";
                // var_dump($result);
                // echo "<br>";
                $response = json_decode($result, true);
                $docID = $response['id'];
                $docLink = "https://drive.google.com/file/d/$docID/view?usp=drive_link";
                
                if (file_exists($fname)) {
                    unlink($fname);
                }
                
                // html end
			    
			} else if ($assetType == 'slide') {
			    // slide start
                
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $docName = $response['name'];
                $mime_type = $response['mimeType'];
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1;
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                $result = copyGoogleFile($logger, $access_token, $fileId, $new_name, $folder);
                // echo "copyGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                $docID = $response['id'];
                
                
                $result = updateGoogleSlide($logger, $userinfo, $access_token, $docID);
                // echo "updateGoogleSlide<br>";
                // echo $result;
                // echo "<br>";
                
                $docLink1 = "https://docs.google.com/presentation/d/$docID/edit";
                $docLink = "https://docs.google.com/presentation/d/$docID/export/pdf";
                
                // echo "Your slide is $new_name<br>";
                // echo "Your link is $docLink<br>";
                // echo "<a href='$docLink' target='_blank'>Open</a><br>";
                // echo "<br>";
                
                // slide end
			    
			} else if ($assetType == 'doc') {
			    // doc start
            	
                $mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $mime_type = 'application/vnd.google-apps.document';
                
                $result = getGoogleFile($logger, $access_token, $fileId);
                // echo "getGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                
                $mime_type = $response['mimeType'];
                $docName = $response['name'];
                //MyDoc001_<<acctID>>_<<userEmail>>_<<epochtimestamp>>
                $t1 = microtime(true);
                $new_name = $docName.'_'.$acctID.'_'.$userEmail.'_'.$t1;
                // echo "mime_type : $mime_type<br>";
                // echo "new_name : $new_name<br>";
                $logger->log("mime_type : $mime_type");
                $logger->log("new_name : $new_name");
                
                $result = copyGoogleFile($logger, $access_token, $fileId, $new_name, $folder);
                // echo "copyGoogleFile<br>";
                // echo $result;
                // echo "<br>";
                
                $response = json_decode($result, true);
                $docID = $response['id'];
                //echo "<br>docID : $docID<br>";
                
                $result = updateGoogleDoc($logger, $userinfo, $access_token, $docID);
                // echo "updateGoogleDoc<br>";
                // echo $result;
                // echo "<br>";
                
                $docLink1 = "https://docs.google.com/document/d/$docID/edit";
                $docLink = "https://docs.google.com/document/d/$docID/export?format=pdf";
                
                // echo "Your doc is $new_name<br>";
                // echo "Your link is $docLink<br>";
                // echo "<a href='$docLink' target='_blank'>Open</a><br>";
                // echo "<br>";
                // doc end
			}
			
			if ($new_name != '') {
			    $assetItem['new_name'] = $new_name;
                $assetItem['docLink'] = $docLink;
                $assetItem['docLink1'] = $docLink1;
                $assetItem['assetType'] = $assetType;
                $asssetResult[] = $assetItem;
			}
			
		}
	}  
	catch(exception $e) {
		$errorMessage = $e->getMessage();
	}
    
    return $asssetResult;
}

// Google Document End *************************************************************************************************


function addSettingUserCredentials($Credentials, $user) {
	$ContactStatus = 'User';
	return addSettingUserCredentialsItem($Credentials, $user, $ContactStatus);
}

function addSettingUserCredentialsItem($Credentials, $user, $ContactStatus) {
	
	$logger2 = new FileLogger("addSettingUserCredentialsItem.log");
	
	$email = $user->email;
	$userName = $user->name;
	$mobile = $user->Cell;
	$title = $user->Title;
	$image = $user->image;
	
	$name = splitName($userName);
	$firstname = $name[0];
	$lastname = $name[1];
	if ($lastname) {
	} else {
		$lastname = $firstname;
	}
	
	$contactDataAPI = array(
		array("Key" => "firstname", "Value" => $firstname),
		array("Key" => "lastname", "Value" => $lastname),
		array("Key" => "email", "Value" => $email),
		array("Key" => "mobile", "Value" => $mobile),
		array("Key" => "title", "Value" => $title),
		array("Key" => "photoURL", "Value" => $image),
		array("Key" => "ContactStatus", "Value" => $ContactStatus)							
	);
	$addContact  = array // The new Contact array; I grabbed this from Dustin's email
			(
				"Credentials" => $Credentials, 
				"KeyValueList" => $contactDataAPI
			);
	
	$logger2->log(" addContact - ".json_encode($addContact));

	$newContact = callServiceCredential("contactservice/CreateContact", $addContact);
	
	$logger2->log(" newContact - ".json_encode($newContact));

	$ErrorCode = $newContact->{"Result"}->{"ErrorCode"};
	$errorMessage = "";
	$purl = "";

	if ($ErrorCode == "") {
		$purl=$newContact->{'Purl'}; // The Contact's newly issued PURL	
		$message = "$firstname $lastname was added.  Let's do another!";
		$errorMessage = '';
		$success = true;
		
	} else {
		
		
		$peopleDataAPI = array(
			'firstname'=>$firstname,
			'lastname'=>$lastname,
			'email'=>$email,
			'mobile'=>$mobile,
			'title'=>$title,
			'photoURL'=>$image,
			'ContactStatus'=>$ContactStatus
		);
		
		$logger2->log(" peopleDataAPI - ".json_encode($peopleDataAPI));
		
		$updateContactResult = updateContactCredentials($Credentials, $peopleDataAPI);
		
		$logger2->log(" updateContactResult - ".json_encode($updateContactResult));
		
		$success = $updateContactResult['success'];
		$errorMessage = $updateContactResult['errorMessage'];
		$message = $updateContactResult['message'];
	}
	return array('success'=>$success, 'message' => $message, 'errorMessage' => $errorMessage, 'purl' => $purl, 'ContactStatus' => $ContactStatus, 'addContact'=> $addContact, 'newContact'=> $newContact);
}


function getStorefrontVariables($acctID) {
		if($db == null){
		$db = GetPDOMySQL();
	}
	$sqltxt = "select * from DV_Storefront_Variables where acctID = ?";
	$Stmt = MySQL_Prepare($db,$sqltxt);		
	$Stmt->execute(array($acctID));
	$affected_rows = $Stmt->rowCount();
	$row2 = array();
	while ($row = $Stmt->fetch()) {
		$row2[] = $row;
	}
	return $row2;
}

function compareDecimals($num1, $num2, $precision) {
    
    $rounded_num1 = round($num1, $precision);
    $rounded_num2 = round($num2, $precision);

    if ($rounded_num1 > $rounded_num2) {
        return false;
    } elseif ($rounded_num1 < $rounded_num2) {
        return false;
    } else {
        return true;
    }
}

function getSender($ACCOUNTID) {
    if($db == null){
        $db = GetPDOMySQL();
    }
    
    $table = 'DV_UserInfo';
    $stmt = $db->query("SELECT * FROM $table where acctID='$ACCOUNTID'");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //$logger->log("get DV_UserInfo done");
    $mydvEmail = '';
    $mydvName = '';
    $mydvFwdEmail = '';
    if (count($records)>0) {
		$doc = json_decode($records[0]['data'],false);
		$paidAcct = $records[0]['paidAccount'];
		$studioAccountName = $records[0]['studioAccountName'];
		$studioParentID = $records[0]['studioParentID'];
		$OfferData = json_decode($records[0]['OfferData'],false);
		$RenewalOfferID = $OfferData->{'OfferID'};
		$RenewalID = $OfferData->{'RenewalID'};
		
		$userInfo = json_decode($records[0]['data'],true);
		$users = $userInfo['users'];
        if(!empty($users)){
            foreach($users as $user){
                if (stripos($user['email'], "@my-dv.com") !== FALSE) {
                    $mydvEmail = $user['email'];
                    $mydvName = $user['name'];
                    $mydvFwdEmail = $user['fwdEmail'];
                    break;
                }
            }
            //logDebug($userInfo);
        }
        
        $senders = $userInfo['senders'];
        if ($senders) {
            
        } else {
            $senders = $users;
        }
        
		$default = false;
    }else{
        $doc = json_decode("{}",false);
		$default = true;
    }
    return $senders;
}

function genSenderFilter($senders) {
    
    $CriteriaRow = '';
    $CriteriaRow1 = '';
    if (sizeof($senders) == 0) {
        $Filter = "<Filter CriteriaJoinOperator=\"&amp;\"><Criteria Row=\"1\" Field=\"isseed\" Operator=\"Equal\" Value=\"True\" /></Filter>";    
    } else {
        
        $LISTDEFINITIONROW = '';
        $row = 0;
        $JoinOperator = "|";
        foreach( $senders as $sender ) {	
			$data = $sender['email'];
			$data = strtolower($data);
			if (stripos($data, "@my-dv.com") !== FALSE) {
			} else {
				$field = 'email';
				$op		= 'Equal';
				$row++;
				$data = htmlspecialchars($data);  //ISSUE 1356 
				$CriteriaRow1 .= "Criteria Row=\"$row\" Field=\"$field\" Operator=\"$op\" Value=\"$data\" <br>";
				$CriteriaRow .= "<Criteria Row=\"$row\" Field=\"$field\" Operator=\"$op\" Value=\"$data\" />";
			}
			
		}
	    $Filter = "<Filter CriteriaJoinOperator=\"$JoinOperator\">$CriteriaRow</Filter>";
		$CriteriaRow1 = "Filter CriteriaJoinOperator=\"$JoinOperator\" $CriteriaRow1 Filter<br>";
		
    }
    return $Filter;
}

function removeSubdomainAndProtocol($url) {
    // Use a regular expression to match and remove the protocol and subdomain
    return preg_replace('/^(https?:\/\/)?([a-z0-9]+[.])*([a-z0-9-]+[.][a-z]+)(.*)$/i', '$3$4', $url);
}



function replaceDomain($url) {
    // Remove protocol (http, https, etc.) in a case-insensitive manner
    $url = preg_replace("~^https?://~i", "", $url);

    // Replace www. with *. in a case-insensitive manner
    $url = preg_replace("~^www\.~i", "*.", $url);

    return $url;

}

function hasSubdomain($domain) {
    // Remove common prefixes like '*.' and 'www.' (case-insensitive)
    $domain = preg_replace('/^(\*\.)|^(www\.)/i', '', $domain);

    // Split the domain by dots
    $parts = explode('.', $domain);

    // If there are more than two parts, it means the domain has a subdomain
    if (count($parts) > 2) {
        return true;
    }

    // No subdomain detected
    return false;
}

function checkSslStatus($url) {
    // Create a stream context with SSL options
    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);

    // Try to get the content from the URL
    $getResult = @file_get_contents($url, false, $context);
    
    // Check if the result is false, indicating SSL verification failed
    if ($getResult === false) {
        $status = 'invalid';
        $statusMessage = "SSL is not valid for the URL: $url";
    } else {
        $status = 'valid';
        $statusMessage = "SSL is valid for the URL: $url";
    }
    $result['status'] = $status;
    $result['statusMessage'] = $statusMessage;
    return $result;
}

function getCertValidation($domainList, $company) {
    

   
	$logger = new FileLogger("getCertValidation.log");
		
	$ch = curl_init();
	$serverMode = GetCurrentConfig()->serverName;
	if ( ($serverMode == 'LocalCoa') || ($serverMode == 'LocalCoaStudio') || ($serverMode == 'LocalJiewC9') || ($serverMode == 'LocalFungC9') ) {
    	$url = "http://localhost:8080/admin/requestCertValidation.php";
    	// $url = getServerHost("admin/requestCertValidation.php");
	} else if ($serverMode == 'LocalJiew' || $serverMode == 'LocalFung') {
	    $url = "http://127.0.0.1/admin/requestCertValidation.php";
	} else if ($serverMode == 'Production') {
		$url = "http://localhost/admin/requestCertValidation.php";
	} else if ($serverMode == 'StagePHP8') {
		$url = "http://localhost/admin/requestCertValidation.php";
	} else {
    	$url = getServerHost("admin/requestCertValidation.php");
	}
	$url = $url."?mode=cron&domain=".$domainList."&company=$company";
	$logger->log("url = ".  $url );
    
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTREDIR, 3);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $logger->log("7724 info = ".  json_encode($info) );
    if($response === false){
        $errorMessage = json_encode(curl_error($ch));
        $logger->log("curl_error = ". $errorMessage  );
        $doc = false;
    }else{
        $errorMessage = '';
        $logger->log("response = ".  json_encode($response) );
        $doc = json_decode($response);
    }
    $result['doc'] = $doc;
    $result['errorMessage'] = $errorMessage;
    
    
    return $result;  
}

function createCloudFrontDistribution($logger, $cloudFrontClient, $originDomain, $cnameList, $certificateArn) {
    
    $AWSconfig = getMasterAWSconfig();

    $CachePolicyId = $AWSconfig['CachePolicyId'];
    $OriginRequestPolicyId = $AWSconfig['OriginRequestPolicyId'];
    
    // Generate a unique CallerReference using a timestamp or a unique string
    $callerReference = 'cloudfront-' . uniqid();

    // Define the distribution configuration
    $distributionConfig = [
        'CallerReference' => $callerReference, // Add unique CallerReference
        'OriginGroups' => [
            'Quantity' => 0 // No origin groups used, so set to 0
        ],
        'Origins' => [
            'Quantity' => 1,
            'Items' => [
                [
                    'Id' => $originDomain,
                    'DomainName' => $originDomain,
                    'OriginPath' => '',
                    'CustomOriginConfig' => [
                        'HTTPPort' => 80,
                        'HTTPSPort' => 443,
                        'OriginProtocolPolicy' => 'http-only', // For redirecting to HTTPS
                        'OriginSslProtocols' => [
                            'Quantity' => 1,
                            'Items' => ['TLSv1.2'],
                        ],
                    ],
                ],
            ],
        ],
        'DefaultCacheBehavior' => [
            'TargetOriginId' => $originDomain,
            'ViewerProtocolPolicy' => 'redirect-to-https', // Redirect from HTTP to HTTPS
            'AllowedMethods' => [
                'Quantity' => 7,
                'Items' => ['GET', 'HEAD', 'OPTIONS', 'PUT', 'POST', 'PATCH', 'DELETE']
            ],
            'CachedMethods' => [
                'Quantity' => 2,
                'Items' => ['GET', 'HEAD']
            ],
            'CachePolicyId' =>  $CachePolicyId, // 'Managed-CachingDisabled',
            'OriginRequestPolicyId' => $OriginRequestPolicyId //'Managed-AllViewer'
        ],
        'Comment' => 'My CloudFront Distribution',
        'Enabled' => true,
        'Aliases' => [
            'Quantity' => count($cnameList),
            'Items' => $cnameList,
        ],
        'ViewerCertificate' => [
            'ACMCertificateArn' => $certificateArn,
            'SSLSupportMethod' => 'sni-only',
            'MinimumProtocolVersion' => 'TLSv1.2_2021',
        ],
    ];

    try {
        // Create the CloudFront distribution
        
        echo "<br>CloudFront Distribution config.<br>";
        echo "<br>".json_encode($distributionConfig)."<br>";
        $logger->log('CloudFront Distribution config : ');
        $logger->log(json_encode($distributionConfig));
        $result = $cloudFrontClient->createDistribution(['DistributionConfig' => $distributionConfig]);
        
        $logger->log('CloudFront Distribution result : ');
        $logger->log(json_encode($result));
        
        // Note down the CloudFront domain name
        $cloudFrontDomain = $result['Distribution']['DomainName'];
        $cloudFrontID = $result['Distribution']['Id'];
        echo "<br>CloudFront Distribution created successfully.<br>";
        echo "<br>CloudFront Domain Name: $cloudFrontDomain<br>";
        echo "<br>CloudFront ID: $cloudFrontID<br>";
        echo "<br>CloudFront callerReference: $callerReference<br>";
        $logger->log("CloudFront Distribution created successfully.");
        $logger->log("CloudFront Domain Name: $cloudFrontDomain");
    } catch (AwsException $e) {
        // Output error message if fails
        echo "<br>CloudFront Distribution created error.<br>";
        echo $e->getMessage() . "<br>\n";
        $logger->log("CloudFront Distribution created error.");
        $logger->log($e->getMessage());
    }
    
    $result['callerReference'] = $callerReference;
    $result['cloudFrontID'] = $cloudFrontID;
    $result['cloudFrontDomain'] = $cloudFrontDomain;
    return $result;
}

function updateCloudFrontDistribution($logger, $cloudFrontClient, $originDomain, $cnameList, $certificateArn, $distributionId) {
	try {
	    
	
	    // Retrieve the current distribution configuration
        $result = $cloudFrontClient->getDistributionConfig([
            'Id' => $distributionId,
        ]);
    
        // Get the current configuration and ETag
        $distributionConfig = $result['DistributionConfig'];
        $etag = $result['ETag']; // Required for updates
    
        // Modify the aliases (CNAMEs)
        $distributionConfig['Aliases'] = [
            'Quantity' => count($cnameList),
            'Items' => $cnameList,
        ];
    
        // Modify the viewer certificate to use an ACM certificate
        $distributionConfig['ViewerCertificate'] = [
            'ACMCertificateArn' => $certificateArn,
            'SSLSupportMethod' => 'sni-only',
            'MinimumProtocolVersion' => 'TLSv1.2_2021',
        ];
        
        $request = [
            'Id' => $distributionId,
            'IfMatch' => $etag, // You must pass the ETag from the current configuration
            'DistributionConfig' => $distributionConfig,
        ];
        
        echo "<br>CloudFront Distribution request.<br>";
        echo "<br>".json_encode($distributionConfig)."<br>";
        
        $logger->log('CloudFront Distribution request : ');
        $logger->log(json_encode($request));
    
        // Update the distribution with the modified configuration
        $updateResult = $cloudFrontClient->updateDistribution($request);
	
	    // Output the updated details
	    echo "CloudFront distribution updated successfully.<br>";
	    
	    $logger->log('CloudFront Distribution updateResult : ');
        $logger->log(json_encode($updateResult));
	
	} catch (AwsException $e) {
	    // Output error message if the operation fails
	    echo "Error updating distribution: " . $e->getMessage() . "<br>";
	    $logger->log("CloudFront Distribution updated  error.");
        $logger->log($e->getMessage());
	}
	
	$result['distributionId'] = $distributionId;
    return $result;
}




function createCnameRecord($logger, $route53Client, $domainID, $domain, $cloudfrontDomain, $hostedZoneId, $recordValue) {

    // Prepare the CNAME record
    $recordName = $cloudfrontDomain;
    $value = $cloudfrontDomain; // Value: CloudFront domain name
    
    $recordType = 'CNAME'; // The type of DNS record
    

    try {
        
        $config = [
            'HostedZoneId' => $hostedZoneId,
            'ChangeBatch' => [
                'Changes' => [
                    [
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => [
                            'Name' => $cloudfrontDomain,
                            'Type' => 'CNAME',
                            'TTL' => 300,
                            'ResourceRecords' => [
                                ['Value' => $recordValue],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        
        
        echo "<br>createCnameRecord config : <br>";
        echo json_encode($config);
        echo "<br>";
        
        $logger->log('createCnameRecord config : ');
        $logger->log(json_encode($config));
        
        // Submit the change batch to Route 53
        $result = $route53Client->changeResourceRecordSets($config);
        
        echo "<br>createCnameRecord result : <br>";
        echo json_encode($result);
        echo "<br>";
        
        $logger->log('createCnameRecord result : ');
        $logger->log(json_encode($result));

        echo "<br>CNAME record created successfully: {$recordName} -> {$value}\n<br>";
        $logger->log("CNAME record created successfully: {$recordName} -> {$value}\n<br>");
    } catch (AwsException $e) {
        // Handle AWS errors
        echo "<br>Error creating CNAME record: " . $e->getMessage() . "\n<br>";
        $logger->log("Error creating CNAME record.");
        $logger->log($e->getMessage());
    }
    
    $result['recordName'] = $recordName;
    return $result;
}

function getAllCert($logger2, $acmClient) {
    
    // Parameters for listing certificates
    $params = [
        // Other optional parameters can be included here
    ];
    $identifierFound = 'false';
    
    // Array to store all certificates
    $allCertificates = [];
    $errorMessage = '';
    try {
        do {
            // Call listCertificates with current params
            $result = $acmClient->listCertificates($params);
            
            // Add current page of certificates to the array
            $allCertificates = array_merge($allCertificates, $result['CertificateSummaryList']);
            
            // Update params with NextToken to fetch next page
            $params['NextToken'] = $result['NextToken'] ?? null;
    
        } while ($params['NextToken']); // Continue until NextToken is null or empty
    
        // Output or further process allCertificates array
        // print_r($allCertificates);
    
    } catch (AwsException $e) {
        // Handle AWS SDK exceptions
        
        $errorMessage = $e->getMessage();
        $logger2->log("errorMessage = $errorMessage");
    }
    
    $logger2->log("allCertificates = ". json_encode($allCertificates));	
    return $allCertificates;
    
}

function getCert($logger2, $acmClient, $allCertificates, $identifier) {
    
    $Domains = array();
    $identifierFound = 'false';
    
    $logger2->log("identifier = ".$identifier);
    $logger2->log("sizeof = ".sizeof($allCertificates));
    
    for ($i = 0; $i <= sizeof($allCertificates)-1; $i++) {
        
        $Certificate = $allCertificates[$i];
        
        $CertificateArn = $Certificate['CertificateArn'];
        // $logger2->log("CertificateArn = $CertificateArn");
        
        $DomainName = $Certificate['DomainName'];
        // $logger2->log("DomainName = $DomainName");
        $CertificateArnLower = strtolower($CertificateArn);
        $identifierLower = strtolower($identifier);
        
        if (str_ends_with($CertificateArnLower, $identifierLower)) {
            $logger2->log("identifier match");
            $identifierFound = 'true';
            
            $result = $acmClient->describeCertificate([
                'CertificateArn' => $CertificateArn, // REQUIRED
            ]);
            
            $logger2->log("1 describeCertificate = ".$result);
            
            $status = $result['Certificate']['Status'];
            $DomainValidationOptions = $result['Certificate']['DomainValidationOptions'];
            
            
            foreach( $DomainValidationOptions as $DomainValidationOption ) {
                $item = array();
                
                        
                $DomainName = $DomainValidationOption['DomainName'];
                
                
                $logger2->log("DomainName 123 = $DomainName");
                
                $ValidationDomain = $DomainValidationOption['ValidationDomain'];
                $domain = trim($domain, '*.');
                $domain = removeSubdomainAndProtocol($ValidationDomain);
                $domain = trim($domain, '*.');
                
                // echo "domain : $domain<br>";
                
                if (hasSubdomain($domain)) {
                	$domain = removeSubdomainAndProtocol($domain);	
                }
                
                
                $ValidationStatus = $DomainValidationOption['ValidationStatus'];
                $ValidationMethod = $DomainValidationOption['ValidationMethod'];
                $ResourceRecord = $DomainValidationOption['ResourceRecord'];
                
                $ResourceRecord_name = $ResourceRecord['Name'];
                $awsName = $ResourceRecord_name;
                $ResourceRecord_type = $ResourceRecord['Type'];
                $ResourceRecord_value = $ResourceRecord['Value'];
                
                $item['DomainName'] = $DomainName;
                $item['ValidationDomain'] = $ValidationDomain;
                $item['ValidationStatus'] = $ValidationStatus;
                
                $ResourceRecord_name = str_replace($domain . '.', '', $ResourceRecord_name);
                $ResourceRecord_name = trim($ResourceRecord_name, '.');
                $ResourceRecord_value = trim($ResourceRecord_value, '.');
                
                $ResourceRecord['Name'] = $ResourceRecord_name;
                $ResourceRecord['awsName'] = $awsName;
                $ResourceRecord['Type'] = $ResourceRecord_type;
                $ResourceRecord['Value'] = $ResourceRecord_value;
                $item['ResourceRecord'] = $ResourceRecord;
                
                $item['ValidationMethod'] = $ValidationMethod;
                
                
                
                
                $Domains[] = $item;
                
            }
            
            
            
            break;
        }
        
    }
    
    $result['status'] = $status;
    $result['Domains'] = $Domains;
    $result['DomainName'] = $DomainName;
    $result['errorMessage'] = $errorMessage;
    $result['identifierFound'] = $identifierFound;
    $result['ValidationStatus'] = $ValidationStatus;
    $result['ResourceRecord_name'] = $ResourceRecord_name;
    $result['ResourceRecord_value'] = $ResourceRecord_value;
    return $result;
}

function listCachePolicies($cloudFrontClient) {
    
    try {
        // List all cache policies
        $result = $cloudFrontClient->listCachePolicies([]);
    
        // Display each cache policy's ID and name
        foreach ($result['CachePolicyList']['Items'] as $policy) {
            //echo "Cache Policy ID: " . $policy['CachePolicy']['Id'] . "<br>";
        }
    
    } catch (AwsException $e) {
        // Output error message if fails
        echo "listCachePolicies error <br>";
        echo $e->getMessage();
        echo "<br>";
    }
    
    return $result;
}

function checkCName($cloudFrontClient, $cnameToCheck) {
    
    $Id = '';
    $cnameFound = false;
    $message = '';
    $status = 'Error';
    try {
        $nextMarker = null; // For pagination
    
        do {
            // Prepare the parameters for the request
            $params = [];
            if ($nextMarker !== null) {
                $params['Marker'] = $nextMarker;
            }
    
            // List distributions
            $result = $cloudFrontClient->listDistributions($params);
    
            // Check if there are distributions in the current response
            if (isset($result['DistributionList']['Items'])) {
                foreach ($result['DistributionList']['Items'] as $distribution) {
                    // Get the aliases (CNAMEs) for this distribution
                    $aliases = $distribution['Aliases']['Items'] ?? [];
                    
                    // Check if the CNAME is in the aliases
                    if (in_array($cnameToCheck, $aliases)) {
                        $Id = $distribution['Id'];
                        $cnameFound = true;
                        $message = "CNAME '{$cnameToCheck}' is associated with CloudFront distribution ID: " . $Id;
                        // echo $message."<br>";
                        break;
                    }
                }
            }
    
            // Get the NextMarker for pagination
            $nextMarker = $result['DistributionList']['NextMarker'] ?? null;
    
        } while ($nextMarker); // Continue if NextMarker is not null (more distributions to fetch)
    
    } catch (AwsException $e) {
        
        // Output error message if fails
        //echo "listDistributions error <br>";
        //echo $e->getMessage();
        //echo "<br>";
        $status = 'Error';
        $message = "listDistributions error ".$e->getMessage();
    }
    
    $result = [];
    $result['status'] = $status;
    $result['cnameFound'] = $cnameFound;
    $result['Id'] = $Id;
    $result['message'] = $message;
    return $result;
}

function requestCertificate ($logger2, $client, $domainLower, $company) {

    try {
        
        $result = $client->requestCertificate([
            'DomainName' => $domainLower, // REQUIRED
            'Tags' => [
                [
                    'Key' => 'Name', // REQUIRED
                    'Value' => $company,
                ],
            ],
            'ValidationMethod' => 'DNS',
        ]);
        
        $logger2->log("result = $result");
        $CertificateArn = $result['CertificateArn'];
        $statusCode = $result['@metadata']['statusCode'];
        
        $CertificateArn = substr($CertificateArn, strpos($CertificateArn, '/')+1 );
    }  
    	catch(exception $e) {
    	    $statusCode = 'Error';
    	    $CertificateArn = '';
    		$errorMessage = $e->getMessage();
    		$logger2->log("errorMessage : $errorMessage");	
    	}
    
    	
    
    $result = array(
	    'statusCode'	=> $statusCode,
	    'CertificateArn'	=> $CertificateArn,
	    'domain' => $domainLower,
	    'company' => $company,
	    'errorMessage' => $errorMessage
    );
    return $result;
}


// Function to check if a certificate already exists in any group
function certificateExists($certificates, $AWS_Certificate_Id, $Domain_requested) {
    foreach ($certificates as $group) {
        foreach ($group as $certificate) {
            if ($certificate['AWS_Certificate_Id'] === $AWS_Certificate_Id &&
                $certificate['Domain_requested'] === $Domain_requested) {
                return true; // Certificate exists
            }
        }
    }
    return false; // Certificate does not exist
}

// Function to add a new certificate to the correct group (max 25 per group)
function addCertificate($logger2, &$certificates, $maxCount, $AWS_Certificate_Id, $Domain_requested, $Verification_Status) {
    // Check if the certificate already exists
    if (certificateExists($certificates, $AWS_Certificate_Id, $Domain_requested)) {
        // echo "Certificate already exists, not added.<br>";
        return;
    }
    
    // Get the last group in the array
    $lastGroup = &$certificates[count($certificates) - 1];
    
    // If the last group has 25 items, create a new group
    if (count($lastGroup) >= $maxCount) {
        $certificates[] = []; // Add a new group
        $lastGroup = &$certificates[count($certificates) - 1]; // Set the last group reference to the new group
    }
    
    // Add the new certificate to the last group
    $lastGroup[] = [
        'AWS_Certificate_Id' => $AWS_Certificate_Id,
        'Domain_requested' => $Domain_requested,
        'Verification_Status' => $Verification_Status
    ];
    
    // echo "Certificate added successfully.<br>";
}

// Function to save certificates to MySQL
function saveCertificatesToDB($logger2, $certificates) {
	
	if($db == null){
		$db = GetPDOMySQL();
	}
	
	$groupID = 0;
    foreach ($certificates as $group) {
    	$groupID++;
    	$itemID = 0;
        foreach ($group as $certificate) {
        	$itemID++;
            $AWS_Certificate_Id = $certificate['AWS_Certificate_Id'];
            $Domain_requested = $certificate['Domain_requested'];
            $Domain_status = 'PENDING_VALIDATION';
            
            // Insert into the database (use ON DUPLICATE KEY to avoid duplicates)
            $sql = "INSERT INTO AWS_Domain_List (ID, ItemID, AWS_Certificate_Id, Domain_requested, createDate, Domain_status)
                    VALUES (?, ?, ?, ?, NOW(),?)
                    ON DUPLICATE KEY UPDATE LastEditDate = NOW()";

			try {
				
				$Stmt1 = MySQL_Prepare($db,$sql);
				$Stmt1->execute(array($groupID,$itemID,$AWS_Certificate_Id,$Domain_requested,$Domain_status));
				$str1 = "Record inserted successfully: $AWS_Certificate_Id";
				// echo $str1."<br>";
				// $logger2->log($str1);
			} catch(Exception $e){
				$ErrorMessage = $e->getMessage();
				$str1 = "INSERT ErrorMessage : $ErrorMessage";
				// echo $str1."<br>";
				$logger2->log($str1);
			}        


            
        }
    }
    $result['groupID'] = $groupID;
    $result['itemID'] = $itemID;
    return $result;
}

function updateAWS($logger2, $acmClient, $allCertificates, $identifier) {
	$result1 = getCert($logger2, $acmClient, $allCertificates, $identifier);
		
	$status = $result1['status'];
	$Domains = $result1['Domains'];
	$identifierFound = $result1['identifierFound'];
	
	$logger2->log("identifierFound = $identifierFound");
	$logger2->log("status = $status");
	
	$AWSconfig = getMasterAWSconfig();
	$primaryDomain = strtolower($AWSconfig['primaryDomain']);
	
	if ($identifierFound == 'false') {
		
	} else {
		for ($i = 0; $i <= sizeof($Domains)-1; $i++) {
		    $Domain = $Domains[$i];
		    $DomainName = $Domain['DomainName'];
		    if (strtolower($DomainName) <> $primaryDomain) {
			    $ValidationStatus = $Domain['ValidationStatus'];
			    $ResourceRecord_name = $Domain['ResourceRecord']['Name'];
			    $ResourceRecord_awsname = $Domain['ResourceRecord']['awsName'];
			    $ResourceRecord_value = $Domain['ResourceRecord']['Value'];
			    $ValidationStatusAdd = 'PENDING_VALIDATION';
			    // echo "$identifier, $DomainName, $ValidationStatus, $ResourceRecord_name, $ResourceRecord_value<br>";
			    
			    $sql = 'UPDATE AWS_Certificate_Request set LastEditDate=NOW(),CnameHost=?,CnameAws=?,CnameValue=? where AWS_Certificate_Id=?';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value, $identifier));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_Certificate_Request FAIL : ".$ErrorMessage);
			    }
			    
			    $sql = 'INSERT INTO AWS_SSL_Verification_log (ID,AWS_Certificate_Id,Domain_requested,Verification_Status,CnameHost,CnameAws,CnameValue,CreateDate,LastEditDate) VALUES (NULL,?,?,?,?,?,?,NOW(),NULL) ON DUPLICATE KEY UPDATE Verification_Status=?, CnameHost=?, CnameAws=?, CnameValue=?, LastEditDate=NOW()';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($identifier, $DomainName, $ValidationStatusAdd, $ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value, $ValidationStatus, $ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_SSL_Verification_log FAIL : ".$ErrorMessage);
			    }
		    }		   
		}  
		
	}
	$awsResult['CnameHost'] = $ResourceRecord_name;
	$awsResult['CnameAws'] = $ResourceRecord_awsname;
	$awsResult['CnameValue'] = $ResourceRecord_value;
	return $awsResult;
}

function updateAWS2($logger2, $acmClient, $allCertificates, $identifier, $CnameHost, $CnameAws, $CnameValue) {
	$result1 = getCert($logger2, $acmClient, $allCertificates, $identifier);
		
	$status = $result1['status'];
	$Domains = $result1['Domains'];
	$identifierFound = $result1['identifierFound'];
	
	$logger2->log("identifierFound = $identifierFound");
	$logger2->log("status = $status");
	
	$AWSconfig = getMasterAWSconfig();
	$primaryDomain = strtolower($AWSconfig['primaryDomain']);
	
	
	$ResourceRecord_name = '';
	$ResourceRecord_awsname = '';
	$ResourceRecord_value = '';
	if ($identifierFound == 'false') {
		$ResourceRecord_name = $CnameHost; 
		$ResourceRecord_awsname = $CnameAws; 
		$ResourceRecord_value = $CnameValue; 
		
	} else {
		for ($i = 0; $i <= sizeof($Domains)-1; $i++) {
		    $Domain = $Domains[$i];
		    $DomainName = $Domain['DomainName'];
		    if (strtolower($DomainName) <> $primaryDomain) {
			    $ValidationStatus = $Domain['ValidationStatus'];
			    $ResourceRecord_name = $Domain['ResourceRecord']['Name'];
			    $ResourceRecord_awsname = $Domain['ResourceRecord']['awsName'];
			    $ResourceRecord_value = $Domain['ResourceRecord']['Value'];
			    $ValidationStatusAdd = 'PENDING_VALIDATION';
			    // echo "$identifier, $DomainName, $ValidationStatus, $ResourceRecord_name, $ResourceRecord_value<br>";
			    
			    $sql = 'UPDATE AWS_Certificate_Request set LastEditDate=NOW(),CnameHost=?,CnameAws=?,CnameValue=? where AWS_Certificate_Id=?';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value, $identifier));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_Certificate_Request FAIL : ".$ErrorMessage);
			    }
			    
			    $sql = 'INSERT INTO AWS_SSL_Verification_log (ID,AWS_Certificate_Id,Domain_requested,Verification_Status,CnameHost,CnameAws,CnameValue,CreateDate,LastEditDate) VALUES (NULL,?,?,?,?,?,?,NOW(),NULL) ON DUPLICATE KEY UPDATE Verification_Status=?, CnameHost=?, CnameAws=?, CnameValue=?, LastEditDate=NOW()';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($identifier, $DomainName, $ValidationStatusAdd, $ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value, $ValidationStatus, $ResourceRecord_name, $ResourceRecord_awsname, $ResourceRecord_value));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_SSL_Verification_log FAIL : ".$ErrorMessage);
			    }
		    }		   
		}  
		
	}
	
	$awsResult['CnameHost'] = $ResourceRecord_name;
	$awsResult['CnameAws'] = $ResourceRecord_awsname;
	$awsResult['CnameValue'] = $ResourceRecord_value;
	return $awsResult;
}

function updateAWSStatus($logger2, $acmClient, $allCertificates, $identifier) {
	$result1 = getCert($logger2, $acmClient, $allCertificates, $identifier);
		
	$status = $result1['status'];
	$Domains = $result1['Domains'];
	$identifierFound = $result1['identifierFound'];
	
	$logger2->log("identifierFound = $identifierFound");
	$logger2->log("status = $status");
	
	$AWSconfig = getMasterAWSconfig();
	$primaryDomain = strtolower($AWSconfig['primaryDomain']);
	
	if ($identifierFound == 'false') {
		
	} else {
		for ($i = 0; $i <= sizeof($Domains)-1; $i++) {
		    $Domain = $Domains[$i];
		    $DomainName = $Domain['DomainName'];
		    if (strtolower($DomainName) <> $primaryDomain) {
			    $ValidationStatus = $Domain['ValidationStatus'];
			    $ResourceRecord_name = $Domain['ResourceRecord']['Name'];
			    $ResourceRecord_awsname = $Domain['ResourceRecord']['awsName'];
			    $ResourceRecord_value = $Domain['ResourceRecord']['Value'];
			    $ValidationStatusAdd = 'PENDING_VALIDATION';
			    // echo "$identifier, $DomainName, $ValidationStatus, $ResourceRecord_name, $ResourceRecord_value<br>";
			    
			    $sql = 'UPDATE AWS_Certificate_Request set CertificateStatus=? where AWS_Certificate_Id=?';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($ValidationStatus, $identifier));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_Certificate_Request FAIL : ".$ErrorMessage);
			    }
			    
			    $sql = 'UPDATE AWS_SSL_Verification_log SET Verification_Status=? WHERE AWS_Certificate_Id=?';
			    try {
				    $Stmt1 = MySQL_Prepare($db,$sql);
				    $Stmt1->execute(array($ValidationStatus, $identifier));
			    } catch(Exception $e){
		    		$success = false;	
				    $ErrorMessage = $e->getMessage();
				    $logger2->log("AWS_SSL_Verification_log FAIL : ".$ErrorMessage);
			    }
		    }		   
		}  
		
	}
	$awsResult['CnameHost'] = $ResourceRecord_name;
	$awsResult['CnameAws'] = $ResourceRecord_awsname;
	$awsResult['CnameValue'] = $ResourceRecord_value;
	return $awsResult;
}

function updateCnameContent($logger2, $identifier, $domainType, $CnameHost, $CnameAws, $CnameValue, $Route53Host, $domain) {
	$CnameContent = '';
	return updateCnameContent1($logger2, $identifier, $domainType, $CnameHost, $CnameAws, $CnameValue, $Route53Host, $domain, $CnameContent);
}


// Function to find the status of a given DNShost
function findStatus($DNShost, $CnameContentJson) {
    // Check if the JSON string is empty
    if (empty($CnameContentJson)) {
        return "Pending";
    }
    
    // Decode JSON string into an array
    $CnameContent = json_decode($CnameContentJson, true);

    // Check if decoding failed (invalid JSON)
    if (!is_array($CnameContent)) {
        return "Pending";
    }

    // Loop through the array to find the matching DNShost
    foreach ($CnameContent as $record) {
        if (isset($record['DNShost']) && $record['DNShost'] === $DNShost) {
            return $record['status'] ?? "Pending"; // Return status or "Pending" if status is null
        }
    }

    return "Pending"; // Default return value if no match is found
}

function updateCnameContent1($logger2, $identifier, $domainType, $CnameHost, $CnameAws, $CnameValue, $Route53Host, $domain, $CnameContent) {
	$contentArray = array();
	$content = '';
	$contentEmail1 = '';
	$ResourceRecord_name = $CnameHost;
	$ResourceRecord_value = $CnameValue;
	
	
	$logger2->log("updateCnameContent");
	$logger2->log("ResourceRecord_name = $ResourceRecord_name");
	$logger2->log("ResourceRecord_value = $ResourceRecord_value");
	$logger2->log("Route53Host = $Route53Host");
	$logger2->log("CnameContent = $CnameContent");
	$logger2->log("domain = $domain");
	//$status = 'Pending';
	$status = findStatus($CnameAws, $CnameContent);
	$statusMessage = '';
	// $dnsRecords = dns_get_record($CnameAws, DNS_CNAME);
	$logger2->log("1 dns_get_record status = $status");
	
	if ($status != 'Verified') {
		$logger2->log("2 dns_get_record status = $status Call findCanonical");
		$dnsRecords = findCanonical($CnameAws);
		$host = $dnsRecords['host'];
		$target = $dnsRecords['target'];
		
		$logger2->log("dns_get_record CnameAws = $CnameAws");
		$logger2->log("dnsRecords : ".json_encode($dnsRecords));
		// var_dump($dnsRecords);
		
		if ($dnsRecords === false) {
		    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
		    $logger2->log("<br>Failed to retrieve DNS records for $CnameAws<br>");
		} else {
		    // foreach ($dnsRecords as $record) {
		        // echo "<br>CNAME Name: " . $record['host'] . "<br>";
		        // echo "<br>CNAME Value: " . $record['target'] . "<br>";
		        
		        $logger2->log("CNAME Name: " . $host . "");
		        $logger2->log("CNAME Value: " . $target . "");
		        if ($target == $ResourceRecord_value) {
		        	$status = 'Verified';
		        	$statusMessage = "SSL is verified for the URL: $CnameAws";
		        }
		        $logger2->log("CNAME status: " . $status . "");
		        $logger2->log("CNAME statusMessage: " . $statusMessage . "");
		    //}
		}
		
	} else {
		
		
		
		
		$statusMessage = "SSL is verified for the URL: $CnameAws";
		$logger2->log("2 dns_get_record status = $status Do not call findCanonical");
		
		$dnsRecords = findCanonical($CnameAws);
		$host = $dnsRecords['host'];
		$target = $dnsRecords['target'];
		if ($dnsRecords === false) {
		    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
		    $status = 'Unverified';
        	$statusMessage = "SSL is unverified for the URL: $CnameAws";
		    $logger2->log("<br>Failed to retrieve DNS records for $CnameAws<br>");
		} else {
		
			if ($target == $ResourceRecord_value) {
        		$status = 'Verified';
        		$statusMessage = "SSL is verified for the URL: $CnameAws";
        	} else {
        		$status = 'Unverified';
        		$statusMessage = "SSL is unverified for the URL: $CnameAws";
        	}
		}
		
		$logger2->log("2 dnsRecords : ".json_encode($dnsRecords));
		$logger2->log("CNAME status: " . $status . "");
		$logger2->log("CNAME statusMessage: " . $statusMessage . "");
	}
		
	
	//<p>Host: ##cnameHost##</p>
	//<p>Value: ##cnameValue##</p>
	
	$content = $content."Host: $ResourceRecord_name<br> Value: $ResourceRecord_value<br><br>";
	$contentEmail1 = $contentEmail1."<p><b>Host:</b> $ResourceRecord_name</p><p><b>Value:</b> $ResourceRecord_value</p>";
	$contentItem['host'] = $ResourceRecord_name;
	$contentItem['DNShost'] = $CnameAws;
	$contentItem['value'] = $ResourceRecord_value;
	$contentItem['status'] = $status;
	
	$logger2->log("1 contentItem : ".json_encode($contentItem));
	
	$contentArray[] = $contentItem;
	$ret = validateDomainType($domain);
	$domainType = $ret['type'];
	if ($domainType == 'wildcardDomain') {
	    $content = $content."Host: *<br> Value: $Route53Host<br><br>";
	    
	    //$status = 'Pending';
	    $status = findStatus($domain, $CnameContent);
		$statusMessage = '';
		// $dnsRecords = dns_get_record($CnameAws, DNS_CNAME);
		$logger2->log("3 dns_get_record status = $status");
		
		if ($status != 'Verified') {
		    // $dnsRecords = dns_get_record($domain, DNS_CNAME);
		    $logger2->log("4 dns_get_record status = $status Call findCanonical");
		    $dnsRecords = findCanonical($domain);
			$host = $dnsRecords['host'];
			$target = $dnsRecords['target'];
		    $logger2->log("dns_get_record domain = $domain");	
		    $logger2->log("dnsRecords : ".json_encode($dnsRecords));
				
			// var_dump($dnsRecords);
			
			if ($dnsRecords === false) {
			    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
			    
			    $logger2->log("<br>Failed to retrieve DNS records for $domain<br>");
			} else {
			    //foreach ($dnsRecords as $record) {
			        // echo "<br>CNAME Name: " . $record['host'] . "<br>";
			        // echo "<br>CNAME Value: " . $record['target'] . "<br>";
			        
			        $logger2->log("CNAME Name: " . $host . "");
			        $logger2->log("CNAME Value: " . $target . "");
			        if ($target == $Route53Host) {
			        	$status = 'Verified';
			        	$statusMessage = "SSL is verified for the URL: $domain";
			        }
			        
			    //}
			}
		} else {
			$statusMessage = "SSL is verified for the URL: $domain";
			$logger2->log("4 dns_get_record status = $status Do not call findCanonical");
		}
	    
	    
	    $contentEmail1 = $contentEmail1."<p><b>Host:</b> *</p><p><b>Value:</b> $Route53Host</p>";
	    $contentItem['host'] = '*';
	    $contentItem['DNShost'] = $domain;
	    $contentItem['value'] = $Route53Host;
	    $contentItem['status'] = $status;
	    $contentArray[] = $contentItem;
	    
	    //$status = 'Pending';
	    
		$statusMessage = '';
		// $dnsRecords = dns_get_record($CnameAws, DNS_CNAME);
		
	    $domain1 = preg_replace('/\*/', 'www', $domain, 1);
		$logger2->log("dns_get_record domain1 = $domain1");	
		$status = findStatus($domain1, $CnameContent);
		$logger2->log("5 dns_get_record status = $status");
		
	    //$dnsRecords = dns_get_record($domain1, DNS_CNAME);
	    if ($status != 'Verified') {
		    // $dnsRecords = dns_get_record($domain, DNS_CNAME);
		    $logger2->log("6 dns_get_record status = $status Call findCanonical");
		    $dnsRecords = findCanonical($domain1);
			$host = $dnsRecords['host'];
			$target = $dnsRecords['target'];
			$logger2->log("dnsRecords : ".json_encode($dnsRecords));
			// var_dump($dnsRecords);
			
			if ($dnsRecords === false) {
			    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
			    
			    $logger2->log("<br>Failed to retrieve DNS records for $domain1<br>");
			} else {
			    //foreach ($dnsRecords as $record) {
			        // echo "<br>CNAME Name: " . $record['host'] . "<br>";
			        // echo "<br>CNAME Value: " . $record['target'] . "<br>";
			        
			        $logger2->log("CNAME Name: " . $host . "");
			        $logger2->log("CNAME Value: " . $target . "");
			        if ($target == $Route53Host) {
			        	$status = 'Verified';
			        	$statusMessage = "SSL is verified for the URL: $domain1";
			        }
			    //}
			}
		} else {
			$statusMessage = "SSL is verified for the URL: $domain1";
			$logger2->log("6 dns_get_record status = $status Do not call findCanonical");
		}
		
	    
	    $content = $content."Host: www<br> Value: $Route53Host<br><br>";    
	    $contentEmail1 = $contentEmail1."<p><b>Host:</b> www</p><p><b>Value:</b> $Route53Host</p>";
	    $contentItem['host'] = 'www';
	    $contentItem['DNShost'] = $domain1;
	    $contentItem['value'] = $Route53Host;
	    $contentItem['status'] = $status;
	    $contentArray[] = $contentItem;
	} else if ($domainType == 'rootDomain') {
		$domain1 = '@.'.$domain;
		$logger2->log("dns_get_record domain1 = $domain1");	
		//$dnsRecords = dns_get_record($domain1, DNS_CNAME);
		$status = findStatus($domain1, $CnameContent);
		$logger2->log("7 dns_get_record status = $status");
		
	    //$dnsRecords = dns_get_record($domain1, DNS_CNAME);
	    if ($status != 'Verified') {
		    // $dnsRecords = dns_get_record($domain, DNS_CNAME);
		    $logger2->log("8 dns_get_record status = $status Call findCanonical");
			$dnsRecords = findCanonical($domain1);
			$host = $dnsRecords['host'];
			$target = $dnsRecords['target'];
			$logger2->log("dnsRecords : ".json_encode($dnsRecords));
				
			// var_dump($dnsRecords);
			$status = 'Pending';
			if ($dnsRecords === false) {
			    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
			    
			    $logger2->log("<br>Failed to retrieve DNS records for $domain1<br>");
			} else {
			    //foreach ($dnsRecords as $record) {
			        // echo "<br>CNAME Name: " . $record['host'] . "<br>";
			        // echo "<br>CNAME Value: " . $record['target'] . "<br>";
			        //$target = $record['target'];
			        $logger2->log("CNAME Name: " . $host . "");
			        $logger2->log("CNAME Value: " . $target . "");
			        if ($target == $Route53Host) {
			        	$status = 'Verified';
			        	$statusMessage = "SSL is verified for the URL: $domain1";
			        }
			        
			    //}
			}
	    } else {
			$statusMessage = "SSL is verified for the URL: $domain1";
			$logger2->log("8 dns_get_record status = $status Do not call findCanonical");
		}
		
	    $content = $content."Host: @<br> Value: $Route53Host<br><br>";
	    $contentEmail1 = $contentEmail1."<p><b>Host:</b> @</p><p><b>Value:</b> $Route53Host</p>";
	    $contentItem['host'] = '@';
	    $contentItem['DNShost'] = $domain1;
	    $contentItem['value'] = $Route53Host;
	    $contentItem['status'] = $status;
	    $contentArray[] = $contentItem;
	} else if (($domainType == 'subDomain') || ($domainType == 'wildcardSubDomain')) {
		
		//$dnsRecords = dns_get_record($domain, DNS_CNAME);
		$status = findStatus($domain, $CnameContent);
		$statusMessage = '';
		// $dnsRecords = dns_get_record($CnameAws, DNS_CNAME);
		$logger2->log("9 dns_get_record status = $status");
		
		if ($status != 'Verified') {
		    // $dnsRecords = dns_get_record($domain, DNS_CNAME);
		    $logger2->log("10 dns_get_record status = $status Call findCanonical");
			$dnsRecords = findCanonical($domain);
			$host = $dnsRecords['host'];
			$target = $dnsRecords['target'];
			$logger2->log("dns_get_record domain = $domain");
			$logger2->log("dnsRecords : ".json_encode($dnsRecords));
			// var_dump($dnsRecords);
			$status = 'Pending';
			if ($dnsRecords === false) {
			    // echo "<br>Failed to retrieve DNS records for $CnameAws<br>";
			    
			    $logger2->log("<br>Failed to retrieve DNS records for $domain<br>");
			} else {
			    //foreach ($dnsRecords as $record) {
			        // echo "<br>CNAME Name: " . $record['host'] . "<br>";
			        // echo "<br>CNAME Value: " . $record['target'] . "<br>";
			        //$target = $record['target'];
			        $logger2->log("CNAME Name: " . $host . "");
			        $logger2->log("CNAME Value: " . $target . "");
			        if ($target == $Route53Host) {
			        	$status = 'Verified';
			        	$statusMessage = "SSL is verified for the URL: $domain";
			        }
			        
			    //}
			}
		} else {
			$statusMessage = "SSL is verified for the URL: $domain";
			$logger2->log("10 dns_get_record status = $status Do not call findCanonical");
		}
		
	    $domainshow = removeBeforeDot($CnameHost);
        if ($domainType == 'wildcardSubDomain') {
        	$domainshow = '*.'.$domainshow;
        }
        $content = $content."Host: $domainshow<br> Value: $Route53Host<br><br>";
        $contentEmail1 = $contentEmail1."<p><b>Host:</b> $domainshow</p><p><b>Value:</b> $Route53Host</p>";
        $contentItem['host'] = $domainshow;
        $contentItem['DNShost'] = $domain;
	    $contentItem['value'] = $Route53Host;
	    $contentItem['status'] = $status;
	    $contentArray[] = $contentItem;
	}
	
	$saveData = json_encode($contentArray);
	
	$sql = 'UPDATE AWS_Certificate_Request set CnameContent=?,Route53Host=? where AWS_Certificate_Id=?';
	try {
		$Stmt1 = MySQL_Prepare($db,$sql);
		$Stmt1->execute(array($saveData,$Route53Host,$identifier));
	} catch(Exception $e){
		$ErrorMessage = $e->getMessage();
		$str1 = "INSERT ErrorMessage : $ErrorMessage";
		$logger2->log($str1);
	}
	$awsResult['content'] = $content;
	$awsResult['contentEmail1'] = $contentEmail1;
	$awsResult['contentArray'] = $contentArray;
	return $awsResult;
}


function validateDomainType($domain) {
    $patterns = [
        'root' => '/^[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/',                  // Root Domain (e.g., testdomain.com)
        'wildcardRoot' => '/^\*\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/',      // Wildcard Domain (e.g., *.testdomain.com)
        'subdomain' => '/^[a-zA-Z0-9-]+\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/', // Subdomain (e.g., subdomain.testdomain.com)
        'wildcardSubdomain' => '/^\*\.[a-zA-Z0-9-]+\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/' // Wildcard Subdomain (e.g., *.subdomain.testdomain.com)
    ];

    $type = null;

    // Validate and determine the type of the domain
    if (preg_match($patterns['root'], $domain)) {
        $type = 'rootDomain';
    } elseif (preg_match($patterns['wildcardRoot'], $domain)) {
        $type = 'wildcardDomain';
    } elseif (preg_match($patterns['subdomain'], $domain)) {
        $type = 'subDomain';
    } elseif (preg_match($patterns['wildcardSubdomain'], $domain)) {
        $type = 'wildcardSubDomain';
    }

    return [
        'valid' => $type !== null,  // true if a type is matched, otherwise false
        'type' => $type             // the type of domain (or null if invalid)
    ];
}

function removeBeforeDot($inputString) {
    // Use regex to replace everything up to and including the first dot
    return preg_replace('/^.*?\./', '', $inputString);
}

function findCanonical($CnameAws) {
    $result = findCanonicalType($CnameAws,'cname');
    
    return $result;
}

function findCanonicalType($CnameAws,$type) {
	
	$logger2 = new FileLogger("findCanonical.log"); 
    
    // Run the nslookup command
    $command = "nslookup -query=$type " . escapeshellarg($CnameAws). " 8.8.8.8";
    $logger2->log("command : $command");
    $output = shell_exec($command);
    $logger2->log("output : $output");
    $cname = '';
    $cnameResult = '';
    // Use regex to find the canonical name
    if (preg_match('/\s+canonical name\s*=\s*(\S+)/i', $output, $matches)) {
        $canFind = true;
        $cname = rtrim($matches[1], '.');
        $cnameResult = "The canonical name for $domain is: $cname";
    } else {
        $canFind = false;
        $cname = '';
        $cnameResult = "No CNAME record found for $domain.";
    }
    $result['canFind'] = $canFind;
    $result['cname'] = $cname;
    $result['cnameResult'] = $cnameResult;
    $result['host'] = $CnameAws;
    $result['target'] = $cname;
    return $result;
}

function filterEncoded($xml, $logger) {
    return preg_replace_callback(
        '/Value="(.*?)"(?=\s*\/?>)/s',
        function ($matches) {
            $value = $matches[1];
            $logger2 = new FileLogger("filterEncoded.log");
            $logger2->log("filterEncoded value 1 : ".$value);
            
            // Encode &, >, <
            $value = str_replace('&', '&amp;', $value);
            $value = str_replace('>', '&gt;', $value);
            $value = str_replace('<', '&lt;', $value);
            
            $logger2->log("filterEncoded value 2 : ".$value);

            // Wrap based on quote presence
            if (strpos($value, '"') !== false && strpos($value, "'") !== false) {
                // Contains both ' and "
                $value2 = "Value='`{$value}`'";
            } elseif (strpos($value, '"') !== false) {
                // Contains " only
                $value2 = "Value='{$value}'";
            } else {
                // Default double quotes
                $value2 = "Value=\"{$value}\"";
            }

            $logger2->log("filterEncoded value final : ".$value2);
            return $value2;
        },
        $xml
    );
}

function decryptData($data, $accessKey) {
    try {
        // Step 1: Decode the Base64 string
        $decodedString = base64_decode($data);
        if ($decodedString === false) {
            throw new Exception('Base64 decoding failed');
        }

        // Step 2: XOR decrypt using the same access key
        $decrypted = '';
        $keyLength = strlen($accessKey);
        for ($i = 0, $len = strlen($decodedString); $i < $len; $i++) {
            $decrypted .= chr(ord($decodedString[$i]) ^ ord($accessKey[$i % $keyLength]));
        }

        return $decrypted;
    } catch (Exception $e) {
        error_log('Decryption error: ' . $e->getMessage());
        throw new Exception('Failed to decrypt data');
    }
}


function getLS_config($account) {
	$defaultConfigFile = "conf/_davinci.json";
	$doc = json_decode(file_get_contents($defaultConfigFile),false);
	$serverName = 'Production';
	if(!empty($doc->serverConf->{$serverName})){
		// check if we have default
        $doc = $doc->serverConf->{$serverName};
		$doc = (object) array_merge((array) $default, (array) $doc);
        $config = $doc;
	}    
	
	
	$dbConfig = $config->dbConfig;
	$host = $dbConfig->host;
	$db = $dbConfig->dbName;
	$user = $dbConfig->user;
	$pass = $dbConfig->password;

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        logMessage("DB Connection failed: " . $conn->connect_error);
        die(json_encode(['error' => 'Database connection failed']));
    }

    $stmt = $conn->prepare("SELECT * FROM nextgen.LS_config WHERE acctID = ?");
    $stmt->bind_param("s", $account);
    $stmt->execute();
    $result = $stmt->get_result();
    $keys = $result->fetch_assoc();

    $stmt->close();
    $conn->close();

    if (!$keys) {
        logMessage("No keys found for mode: " . $mode);
        die(json_encode(['error' => 'No API keys found']));
    }

    return $keys;
}

function AddDefaultPageTemplate($acctID, $blueprint=""){
	/* acctID = -2 is default on signup
		acctID = -1 is default from spacial campaign (QuickStart/Webinar) 	*/ 
	try {	
		$logger = new FileLogger("createDefaultPageTemplate.log");  
		
		$db = GetPDOMySQL();
		$sqltxt = "SELECT *, ID as templateID FROM DV_Document_PageTemplate WHERE acctID = -2 " ;

		if (!empty($blueprint)) {
			$sqltxt = "SELECT p.*, d.data FROM DV_PageTemplateList p JOIN DV_Document_PageTemplate d ON p.templateID = d.ID 
			WHERE p.accountID = -2 AND p.blueprintUsed = '".$blueprint."' AND d.acctID = -2";
		}			
		$logger->log("========START AddDefaultPageTemplate :: SQL =  $sqltxt");

		$rows = MySQL_Select($sqltxt);        
		foreach ($rows as $row) {
			if (isset($row['data'])) {														
					$newdata = str_replace("37921", $acctID, $row['data']);  // copy data from this default account
			}
			$templateID = $row['templateID'];

			$sql = "INSERT INTO DV_Document_PageTemplate (acctID, ID, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE  data = VALUES(data) ";
			$Stmt1 = MySQL_Prepare($db,$sql);
			$Stmt1->execute(array($acctID,$templateID,$newdata));
			$logger->log("Insert SUCCESS - DV_Document_PageTemplate Done! AT $acctID / $templateID ");
		}

		$sql2 = "INSERT INTO DV_PageTemplateList (accountID, templateID, templateName, createDate, lastModified, status, blueprintUsed, subdir)
		SELECT ? , templateID, templateName, NOW(), NOW(), status, blueprintUsed, subdir FROM DV_PageTemplateList
		WHERE accountID = -2 on duplicate key update templateName=values(templateName), blueprintUsed=values(blueprintUsed), lastModified=now(), subdir=values(subdir)";	

		if (!empty($blueprint)) {
			$sql2 = "INSERT INTO DV_PageTemplateList (accountID, templateID, templateName, createDate, lastModified, status, blueprintUsed, subdir)
			SELECT ? , templateID, templateName, NOW(), NOW(), status, blueprintUsed, subdir FROM DV_PageTemplateList
			WHERE accountID = -1 AND blueprintUsed = '".$blueprint."' on duplicate key update templateName=values(templateName), blueprintUsed=values(blueprintUsed), lastModified=now(), subdir=values(subdir)";	
		}			
		$logger->log("SQL2 = $sql2");			

		$Stmt2 = MySQL_Prepare($db,$sql2);		
		$Stmt2->execute(array($acctID));

		$logger->log($blueprint . "Insert - DV_PageTemplateList SUCCESS...");		
		$success = true; 

	} catch(PDOException $e){
		$success = false; 
		$ErrorMessage = $e->getMessage();
		$logger->log($blueprint . " -- ErrorMessage : $ErrorMessage");			
	}

	return array(
		"success"=>$success,      
		"errorMessage"=>$ErrorMessage,
		"AccountID"=>$acctID,
		"blueprint"=>$blueprint,
	);		
}

