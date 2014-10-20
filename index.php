<?

$NETCAT_FOLDER = join(strstr(__FILE__, "/") ? "/" : "\\", array_slice(preg_split("/[\/\\\]+/", __FILE__), 0, -4)).( strstr(__FILE__, "/") ? "/" : "\\" );
include_once ($NETCAT_FOLDER."vars.inc.php");
require ($INCLUDE_FOLDER."index.php");

global $nc_core, $MODULE_VARS;
$tableID = $MODULE_VARS['netshop']['ORDER_TABLE'];
		
//callback
if ($_GET['m_status'])
{
	switch ($_GET['m_status']) 
	{
		case 'success':
			$status = 3;
			break;
		case 'fail':
			$status = 2;
			break;
	}
	
	// проверка принадлежности ip списку доверенных ip
	$list_ip_str = str_replace(' ', '', $MODULE_VARS['payeer']['IPfilter']);

	if (!empty($list_ip_str)) 
	{
		$list_ip = explode(',', $list_ip_str);
		$this_ip = $_SERVER['REMOTE_ADDR'];
		$this_ip_field = explode('.', $this_ip);
		$list_ip_field = array();
		$i = 0;
		$valid_ip = FALSE;
		foreach ($list_ip as $ip)
		{
			$ip_field[$i] = explode('.', $ip);
			if ((($this_ip_field[0] ==  $ip_field[$i][0]) or ($ip_field[$i][0] == '*')) and
				(($this_ip_field[1] ==  $ip_field[$i][1]) or ($ip_field[$i][1] == '*')) and
				(($this_ip_field[2] ==  $ip_field[$i][2]) or ($ip_field[$i][2] == '*')) and
				(($this_ip_field[3] ==  $ip_field[$i][3]) or ($ip_field[$i][3] == '*')))
				{
					$valid_ip = TRUE;
					break;
				}
			$i++;
		}
	}
	else
	{
		$valid_ip = TRUE;
	}

	if (!$valid_ip)
	{
		$status = 2;
		
		$log_text = 
		"--------------------------------------------------------\n".
		"operation id		".$_GET["m_operation_id"]."\n".
		"operation ps		".$_GET["m_operation_ps"]."\n".
		"operation date		".$_GET["m_operation_date"]."\n".
		"operation pay date	".$_GET["m_operation_pay_date"]."\n".
		"shop				".$_GET["m_shop"]."\n".
		"order id			".$_GET["m_orderid"]."\n".
		"amount				".$_GET["m_amount"]."\n".
		"currency			".$_GET["m_curr"]."\n".
		"description		".base64_decode($_GET["m_desc"])."\n".
		"status				".$_GET["m_status"]."\n".
		"sign				".$_GET["m_sign"]."\n\n";
		
		$to = $MODULE_VARS['payeer']['Email'];
		$subject = "Error payment";
		$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
		$message.=" - the ip address of the server is not trusted\n";
		$message.="   trusted ip: ".$MODULE_VARS['payeer']['IPfilter']."\n";
		$message.="   ip of the current server: ".$_SERVER['REMOTE_ADDR']."\n";
		$message.="\n".$log_text;
		$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
		mail($to, $subject, $message, $headers);
	}
	
	$nc_core->db->query("UPDATE Message$tableID SET Status=$status WHERE Message_ID=" . $_GET['m_orderid']);
	header("Location: /");
}

//response
if ($_POST['m_status'])
{
	if (isset($_POST["m_operation_id"]) && isset($_POST["m_sign"]))
	{
		$m_key = $MODULE_VARS['payeer']['SecretKey'];
		$arHash = array($_POST['m_operation_id'],
				$_POST['m_operation_ps'],
				$_POST['m_operation_date'],
				$_POST['m_operation_pay_date'],
				$_POST['m_shop'],
				$_POST['m_orderid'],
				$_POST['m_amount'],
				$_POST['m_curr'],
				$_POST['m_desc'],
				$_POST['m_status'],
				$m_key);
		$sign_hash = strtoupper(hash('sha256', implode(":", $arHash)));
		
		if ($_POST["m_sign"] == $sign_hash && $_POST['m_status'] == "success")
		{
			echo $_POST['m_orderid']."|success";
		}
		else
		{
			$to = $MODULE_VARS['payeer']['Email'];
			$subject = "Error payment";
			$message = "Failed to make the payment through the system Payeer for the following reasons:\n\n";
			
			if ($_POST["m_sign"] != $sign_hash)
			{
				$message.=" - Do not match the digital signature\n";
			}
			
			if ($_POST['m_status'] != "success")
			{
				$message.=" - The payment status is not success\n";
			}

			$message.="\n".$log_text;
			$headers = "From: no-reply@".$_SERVER['HTTP_SERVER']."\r\nContent-type: text/plain; charset=utf-8 \r\n";
			mail($to, $subject, $message, $headers);

			echo $_POST['m_orderid']."|error";
		}
		
		$log_text = 
		"--------------------------------------------------------\n".
		"operation id		".$_POST["m_operation_id"]."\n".
		"operation ps		".$_POST["m_operation_ps"]."\n".
		"operation date		".$_POST["m_operation_date"]."\n".
		"operation pay date	".$_POST["m_operation_pay_date"]."\n".
		"shop				".$_POST["m_shop"]."\n".
		"order id			".$_POST["m_orderid"]."\n".
		"amount				".$_POST["m_amount"]."\n".
		"currency			".$_POST["m_curr"]."\n".
		"description		".base64_decode($_POST["m_desc"])."\n".
		"status				".$_POST["m_status"]."\n".
		"sign				".$_POST["m_sign"]."\n\n";
		
		if (!empty($MODULE_VARS['payeer']['LogFile']))
		{	
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/payeer.log', $log_text, FILE_APPEND);
		}
	}
}