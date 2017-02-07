<?php

class nc_payment_system_payeer extends nc_payment_system
{
	protected $automatic = TRUE;
	
	const ERROR_MERCHANT_URL_IS_NOT_VALID = NETCAT_MODULE_PAYMENT_PAYEER_ERROR_MERCHANT_URL_IS_NOT_VALID;
	const ERROR_MERCHANT_ID_IS_NOT_VALID = NETCAT_MODULE_PAYMENT_PAYEER_ERROR_MERCHANT_ID_IS_NOT_VALID;
	const ERROR_SECRET_KEY_IS_NOT_VALID = NETCAT_MODULE_PAYMENT_PAYEER_ERROR_SECRET_KEY_IS_NOT_VALID;
	const MSG_NOT_VALID_IP = NETCAT_MODULE_PAYMENT_PAYEER_MSG_NOT_VALID_IP;
	const MSG_VALID_IP = NETCAT_MODULE_PAYMENT_PAYEER_MSG_VALID_IP;
	const MSG_THIS_IP = NETCAT_MODULE_PAYMENT_PAYEER_MSG_THIS_IP;
	const MSG_HASHES_NOT_EQUAL = NETCAT_MODULE_PAYMENT_PAYEER_MSG_HASHES_NOT_EQUAL;
	const MSG_WRONG_AMOUNT = NETCAT_MODULE_PAYMENT_PAYEER_MSG_WRONG_AMOUNT;
	const MSG_WRONG_CURRENCY = NETCAT_MODULE_PAYMENT_PAYEER_MSG_WRONG_CURRENCY;
	const MSG_WRONG_ORDER_PAYEED = NETCAT_MODULE_PAYMENT_PAYEER_MSG_WRONG_ORDER_PAYEED;
	const MSG_STATUS_FAIL = NETCAT_MODULE_PAYMENT_PAYEER_MSG_STATUS_FAIL;
	const MSG_ERR_REASONS = NETCAT_MODULE_PAYMENT_PAYEER_MSG_ERR_REASONS;
	const MSG_SUBJECT = NETCAT_MODULE_PAYMENT_PAYEER_MSG_SUBJECT;
	
    protected $accepted_currencies = array('RUB', 'RUR', 'USD', 'EUR');

    protected $settings = array(
        'MERCHANT_URL' => null,
        'MERCHANT_ID' => null,
        'SECRET_KEY' => null,
		'LOG_FILE' => null,
		'IP_FILTER' => null,
		'ADMIN_EMAIL' => null
    );

    protected $request_parameters = array();

    protected $callback_response = array(
        'm_operation_id' => null,
        'm_operation_ps' => null,
	    'm_operation_date' => null,
	    'm_operation_pay_date' => null,
		'm_shop' => null,
	    'm_orderid' => null,
	    'm_amount' => null,
		'm_curr' => null,
		'm_desc' => null,
		'm_status' => null,
		'm_sign' => null
    );

    public function execute_payment_request(nc_payment_invoice $invoice) 
	{
		$m_url = $this->get_setting('MERCHANT_URL');
		$m_shop = $this->get_setting('MERCHANT_ID');
		$m_key = $this->get_setting('SECRET_KEY');
		$m_orderid = $invoice->get_id();
		$m_amount = number_format($invoice->get_amount("%0.2F"), 2, '.', '');
		$m_curr = $invoice->get_currency() == 'RUR' ? 'RUB' : $invoice->get_currency();
		$m_desc = base64_encode($invoice->get_description());
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

        ob_end_clean();
		
        $form = "
            <html>
              <body>
                    <form action='" . $m_url . "' method='get'>" .
                    $this->make_inputs(array(
                        'm_shop' => $m_shop,
                        'm_orderid' => $m_orderid,
                        'm_amount' => $m_amount,
	                    'm_curr' => $m_curr,
	                    'm_desc' => $m_desc,
                        'm_sign' => $sign
                    )) . "
                </form>
                <script>
                  document.forms[0].submit();
                </script>
              </body>
            </html>";
			
        echo $form;
        exit;
    }

    public function on_response(nc_payment_invoice $invoice = null) 
	{
    }

    public function validate_payment_request_parameters() 
	{
		$m_url = $this->get_setting('MERCHANT_URL');
		$m_id = $this->get_setting('MERCHANT_ID');
		$m_key = $this->get_setting('SECRET_KEY');
		
        if (empty($m_url)) 
		{
            $this->add_error(nc_payment_system_payeer::ERROR_MERCHANT_URL_IS_NOT_VALID);
        }
		
		if (empty($m_id)) 
		{
            $this->add_error(nc_payment_system_payeer::ERROR_MERCHANT_ID_IS_NOT_VALID);
        }
		
		if (empty($m_key))
		{
            $this->add_error(nc_payment_system_payeer::ERROR_SECRET_KEY_IS_NOT_VALID);
        }
    }

    public function validate_payment_callback_response(nc_payment_invoice $invoice = null) 
	{
		$m_operation_id = $this->get_response_value('m_operation_id');
		$m_sign = $this->get_response_value('m_sign');
		
		if (isset($m_operation_id) && isset($m_sign))
		{
			$err = false;
			$message = '';

			// запись логов
			
			$log_text = 
				"--------------------------------------------------------\n" .
				"operation id		" . $this->get_response_value('m_operation_id') . "\n" .
				"operation ps		" . $this->get_response_value('m_operation_ps') . "\n" .
				"operation date		" . $this->get_response_value('m_operation_date') . "\n" .
				"operation pay date	" . $this->get_response_value('m_operation_pay_date') . "\n" .
				"shop				" . $this->get_response_value('m_shop') . "\n" .
				"order id			" . $this->get_response_value('m_orderid') . "\n" .
				"amount				" . $this->get_response_value('m_amount') . "\n" .
				"currency			" . $this->get_response_value('m_curr') . "\n" .
				"description			" . base64_decode($this->get_response_value('m_desc')) . "\n" .
				"status				" . $this->get_response_value('m_status') . "\n" .
				"sign				" . $this->get_response_value('m_sign') . "\n\n";
			
			$log_file = $this->get_setting('LOG_FILE');
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip
			
			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				$this->get_response_value('m_operation_id'),
				$this->get_response_value('m_operation_ps'),
				$this->get_response_value('m_operation_date'),
				$this->get_response_value('m_operation_pay_date'),
				$this->get_response_value('m_shop'),
				$this->get_response_value('m_orderid'),
				$this->get_response_value('m_amount'),
				$this->get_response_value('m_curr'),
				$this->get_response_value('m_desc'),
				$this->get_response_value('m_status'),
				$this->get_setting('SECRET_KEY')
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', $this->get_setting('IP_FILTER'));
			
			if (!empty($sIP))
			{
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
				{
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip)
			{
				$message .= nc_payment_system_payeer::MSG_NOT_VALID_IP . "\n" . 
				nc_payment_system_payeer::MSG_VALID_IP . $sIP . "\n" . 
				nc_payment_system_payeer::MSG_THIS_IP . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if ($this->get_response_value('m_sign') != $sign_hash)
			{
				$message .= nc_payment_system_payeer::MSG_HASHES_NOT_EQUAL . "\n";
				$err = true;
			}
			
			if ($invoice->get('status') == 6)
			{
				$message .= nc_payment_system_payeer::MSG_WRONG_ORDER_PAYEED . "\n";
				$err = true;
			}
			
			if (!$err)
			{
				$order_curr = ($invoice->get_currency() == 'RUR') ? 'RUB' : $invoice->get_currency();
				$order_amount = number_format($invoice->get_amount(), 2, '.', '');
				
				// проверка суммы и валюты
				
				if ($this->get_response_value('m_amount') != $order_amount)
				{
					$message .= nc_payment_system_payeer::MSG_WRONG_AMOUNT . "\n";
					$err = true;
				}

				if ($this->get_response_value('m_curr') != $order_curr)
				{
					$message .= nc_payment_system_payeer::MSG_WRONG_CURRENCY . "\n";
					$err = true;
				}

				// проверка статуса
				
				if (!$err)
				{
					switch ($this->get_response_value('m_status'))
					{
						case 'success':
							$invoice->set('status', nc_payment_invoice::STATUS_SUCCESS);
							$invoice->save();
							$this->on_payment_success($invoice);
							break;
							
						default:
							$invoice->set('status', nc_payment_invoice::STATUS_CALLBACK_ERROR);
							$invoice->save();
							$message .= nc_payment_system_payeer::MSG_STATUS_FAIL . "\n";
							$err = true;
							break;
					}
				}
			}
			
			if ($err)
			{
				$to = $this->get_setting('ADMIN_EMAIL');

				if (!empty($to))
				{
					$message = nc_payment_system_payeer::MSG_ERR_REASONS . "\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, nc_payment_system_payeer::MSG_SUBJECT, $message, $headers);
				}
				
				echo ($this->get_response_value('m_orderid') . '|error');
			}
			else
			{
				echo ($this->get_response_value('m_orderid') . '|success');
			}
		}
    }

    public function load_invoice_on_callback() 
	{
        return $this->load_invoice($this->get_response_value('m_orderid'));
    }
}