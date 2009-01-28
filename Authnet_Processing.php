<?php
	class Authnet_Processing
	{
		const   LOGIN    			= 'YOUR-LOGIN-ID';					# Authorize.Net Login ID
		const   TRANSKEY 			= 'YOUR-TRANSACTION-KEY';			# Authorize.Net Transaction Key
		const	APIHOST	 			= 'apitest.authorize.net';			# Authorize.Net API Host
		const	APIPATH	 			= '/xml/v1/request.api';			# Authorize.Net API Path

		private $lineItems			= array();							# Line items for the transaction
		private $shippingDetails	= array('amount' 		=> '0.00'	# Shipping Information: Total Amount
											'name' 			=> ''		# Shipping Information: Shipping Name
											'description'	=> 'none');	# Shipping Information: Shipping Description	

		/**
		 * Method: createLineItem()
		 *
		 * Adds a Line Item to the Array
		 *
		 * @param $itemId 		Integer 	ID for the Item
		 * @param $name 		String 		Name of the item
		 * @param $description 	String 		Description of the item
		 * @param $quantity		Integer		Quantity of the item
		 * @param $unitPrice	Float 		Price per Unit of the item
		 * @param $taxable		Boolean 	Whether or not the item is taxable
		 *
		 * @return Boolean Whether or not the addition was successful
		 */
		public function createLineItem($itemId, $name, $description, $quantity, $unitPrice, $taxable = false)
		{
			$numLineItems = count($this->lineItems);
			
			$newLineItem = array('itemId'		=> (integer) $itemId,
								 'name'			=> $name,
								 'description'	=> $description,
								 'quantity'		=> (integer) $quantity,
								 'unitPrice'	=> (float) $unitPrice,
								 'totalPrice'	=> (float) ( $quantity * $unitPrice ),
								 'taxable'		=> ($taxable) ? 'true' : 'false');

			$newNumLineItems = array_push($this->lineItems, $newLineItem);
			
			return ( $numLineItems++ == $newNumLineItems ) ? true : false;
		}
		
		
		/**
		 * Method: setShippingDetails()
		 *
		 * Sets the shipping details
		 *
		 * @param $amount 		Float 		ID for the Item
		 * @param $name 		String 		Shipping plan
		 * @param $description 	String 		Description of the shipping method
		 */
		public function setShippingDetails($amount = NULL, $name = NULL, $description = NULL)
		{
			if ( !is_null($amount) ) 
				$this->shippingDetails['amount'] = (float) $amount;
			
			if ( !is_null($name) ) 
				$this->shippingDetails['name'] = (string) $name;
			
			if ( !is_null($description) ) 
				$this->shippingDetails['description'] = (string) $description;
		}
		
		/**
		 * Method: getLineItems()
		 *
		 * Returns all current line items
		 *
		 * @return Array / Boolean All current line items or False
		 */
		public function getLineItems()
		{
			return (count($this->lineItems) > 0) ? $this->lineItems : false;
		}

		/**
		 * Method: getLineItemsTotalAmount()
		 *
		 * Returns the total amount of all line items combined
		 *
		 * @return Float Total amount of all line items
		 */
		public function getLineItemsTotalAmount()
		{
			$totalAmount = 0;
			
			foreach ($this->lineItems as $lineItem)
				$totalAmount += $lineItem['totalPrice'];
				
			return $totalAmount;
		}

		/**
		 * Method: createTransaction()
		 *
		 * Creates the XML for the AuthNet Transaction
		 *
		 * @param $customerProfileId 			Integer 			Profile ID for the customer initiating the transaction
		 * @param $customerPaymentProfileId 	Integer 			Payment Profile ID for the customer initiating the transaction
		 * @param $invoiceNumber 				String 				Invoice Number
		 * @param $customerShippingAddressId	Integer				Shipping Address ID for the customer initiating the transaction
		 *
		 * @return 								Array / Boolean		Transaction Details or False.
		 */
		public function createTransaction($customerProfileId, $customerPaymentProfileId, $invoiceNumber, $customerShippingAddressId = 0)
		{	
			$content =
				"<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
				"<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
					$this->MerchantAuthenticationBlock().
					"<transaction>".
						"<profileTransAuthOnly>".
							"<amount>" . $this->getLineItemsTotalAmount() . "</amount>". // Should include tax, shipping, etc.
							"<shipping>".
								"<amount>" . $this->shippingDetails['amount'] . "</amount>".
								"<name>" . $this->shippingDetails['name']. "</name>".
								"<description>" . $this->shippingDetails['description'] ."</description>".
							"</shipping>";
							
							$lineItems = $this->lineItems;
							foreach ($lineItems as $lineItem)
							{
								$content .= 
											'<lineItems>
												<itemId>' . $lineItem['itemId'] . '</itemId>
												<name>' . $lineItem['name'] . '</name>
												<description>' . $lineItem['description'] . '</description>
												<quantity>' . $lineItem['quantity'] . '</quantity>
												<unitPrice>' . $lineItem['unitPrice'] . '</unitPrice>
												<taxable>' . $lineItem['taxable'] . '</taxable>
											 </lineItems>';
							}

				$content .=
							"<customerProfileId>$customerProfileId</customerProfileId>
							 <customerPaymentProfileId>$customerPaymentProfileId</customerPaymentProfileId>
							 <customerShippingAddressId>$customerShippingAddressId</customerShippingAddressId>
							 <order>
								 <invoiceNumber>$invoiceNumber</invoiceNumber>
							 </order>
						 </profileTransAuthOnly>
					 </transaction>
				 </createCustomerProfileTransactionRequest>";

			$response		= self::send_request_via_curl($content);
			$parsedresponse = self::parse_api_response($response);
			
			if (isset($parsedresponse->directResponse)) 
			{					
				$directResponseFields 	= explode(',', $parsedresponse->directResponse);
				$responseCode 			= $directResponseFields[0]; 						# 1 = Approved 2 = Declined 3 = Error
				$responseReasonCode 	= $directResponseFields[2]; 						# See http://www.authorize.net/support/AIM_guide.pdf
				$responseReasonText 	= $directResponseFields[3];
				$approvalCode 			= $directResponseFields[4]; 						# Authorization code
				$transId 				= $directResponseFields[6];
				
				if 		($responseCode == '1') $result = 'Approved';
				else if ($responseCode == '2') $result = 'Declined';
				else 						   $result = 'Error';
				
				$result = array('result' 		=> $result,
								'reason'		=> $responseReasonText,
								'approvalCode' 	=> $approvalCode,
								'transactionId' => $transId);
				return $result;
			}
			
			return false;
		}


		/**
		 * Method: send_request_via_curl()
		 *
		 * Sends the XML to Authorize.Net
		 *
		 * @param $content String	XML being sent to Authorize.Net
		 *
		 * @return String Response from Authorize.Net
		 */		
		private function send_request_via_curl($content)
		{
			$posturl = "https://" . self::APIHOST . self::APIPATH;
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $posturl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$response = curl_exec($ch);

			return $response;
		}
		
		/**
		 * Method: parse_api_response()
		 *
		 * Formated the XML response from Authorize.Net
		 *
		 * @param $content String	XML response from Authorize.Net
		 *
		 * @return Object Formatted response from Authorize.Net
		 */	
		private function parse_api_response($content)
		{
			# SimpleXML Fix
			$position 	= stripos($content, "<?xml");
			$content 	= substr($content, $position);
			
			$parsedresponse = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOWARNING);
			
			if ($parsedresponse->messages->resultCode != 'Ok') 
			{
				echo 'The operation failed with the following errors: <br />';
				foreach ($parsedresponse->messages->message as $msg) 
					echo '[' . htmlspecialchars($msg->code) . '] ' . htmlspecialchars($msg->text) . '<br />';

				echo '<br />';
			}
			return $parsedresponse;
		}
		
		/**
		 * Method: MerchantAuthenticationBlock()
		 *
		 * Generates the merchant authentication block of XML
		 *
		 * @return String Merchant authentication block
		 */	
		private function MerchantAuthenticationBlock() 
		{
			return
				'<merchantAuthentication>' .
					'<name>' . self::LOGIN . '</name>' .
					'<transactionKey>' . self::TRANSKEY . '</transactionKey>' .
				'</merchantAuthentication>';
		}	
	}