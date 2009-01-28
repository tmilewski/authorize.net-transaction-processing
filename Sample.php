<?php
	require('Authnet_Processing.php');
	
	# Instantiate Object
	$invoice = new Authnet_Processing();
	
	# Create Line Items (Item_Id, Name, Description, Quantity, Unit_Price, Is_Taxable = false)
	$invoice->createLineItem('1', 'Plan Renewal', 'Pro Plan Renewal 1/1/09 to 2/1/09', 	 1, 19.99, false);
	$invoice->createLineItem('2', 'Plan Renewal', 'Basic Plan Renewal 1/1/09 to 2/1/09', 1, 9.99,  false);
	
	# Set Shipping Details (Amount = NULL, Name = NULL, Description = NULL)
	$invoice->setShippingDetails(0.00, 'No Shipping', '');

	# Process Transaction (Authnet_Profile_Id, Authnet_Payment_Profile_Id, Invoice_Id, Authnet_Shipping_Address_Id = 0)
	$transaction = $invoice->createTransaction(47857359, 16816816, 0000001);

	# Check Responses
	if 		($transaction['result'] == 'Error')			echo 'Transaction could not be processed.';
	else if ($transaction['result'] == 'Declined')		echo 'Your credit card has been declined.';
	else if (is_null($transaction['approvalCode']) || 
			 is_null($transaction['transactionId']))	echo 'Error connecting to payment processor.';
	else												echo 'Success!';
?>