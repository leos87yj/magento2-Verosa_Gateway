<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Verosa\Pay\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Magento\Sales\Model\Order\Payment\Transaction as PaymentTransaction;
use Magento\Payment\Model\InfoInterface;

/**
 * Pay In Store payment method model
 */
 class Payment extends \Magento\Payment\Model\Method\Cc
{
    const CAPTURE_ON_INVOICE        = 'invoice';
    const CAPTURE_ON_SHIPMENT       = 'shipment';
    const METHOD_CODE               = 'verosa_pay';
    const CODE                      = 'verosa_pay';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isGateway                   = true;
    /**
     * @var bool
     */
	protected $_canAuthorize				= true;
    /**
     * @var bool
     */
    protected $_canCapture                  = true;
    /**
     * @var bool
     */
    protected $_canCapturePartial           = true;
    /**
     * @var bool
     */
    protected $_canRefund                   = true;
    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial     = true;
    /**
     * @var bool
     */
	protected $_canVoid						= true;
    /**
     * @var bool
     */
	protected $_canUseInternal				= true;
    /**
     * @var bool
     */
	protected $_canUseCheckout				= true;
    /**
     * @var bool
     */
	protected $_canUseForMultishipping		= true;
    /**
     * @var bool
     */
	protected $_canSaveCc					= false;

	protected $_VerosaProductCode		= '12';
	protected $_VerosaVersion			= '16.4.1';
	protected $_VerosaVaultVersion		= '2.0';

    protected $_countryFactory;

    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_supportedCurrencyCodes = array('USD');

    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];


    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_countryFactory = $countryFactory;
        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

     /**
     * Payment authorize
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        $this->debugData(['Location' => 'authorize']);

        $order = $payment->getOrder();
		$orderid = $order->getIncrementId();
        $BillingAddress = $order->getBillingAddress();
        $ShippingAddress = $order->getShippingAddress();
		$payment->setAmountAuthorized($amount);

        try {
			$data=$this->_prepareData();
			if($this->getConfigData('payment_action') == \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE)
				$data['command']	=	"AUTH";
			else
				$data['command']	=	"SALE";

			$paymentdetails=array(
				'ponum'=> $payment->getPoNumber(),
				'currency' => strtolower($order->getBaseCurrencyCode())
			);

			$ccType = "0";
			switch($payment->getCcType()) {
				case 'MC':
					$ccType = "1";
					break;
				case 'VI':
					$ccType = "2";
					break;
				case 'AE':
					$ccType = "3";
					break;
				case 'DI':
					$ccType = "4";
					break;
				case 'JCB':
					$ccType = "7";
					break;
				case 'SO':
					$ccType = "0";
					break;
				case 'SM':
					$ccType = "0";
					break;

			}
			$creditcard=array(
				'nameoncard'	=>	$payment->getCcOwner(),
				'cardnumber'	=>	$payment->getCcNumber(),
				'cardexpmonth'	=>	str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT),
				'cardexpyear'	=>	substr($payment->getCcExpYear(),-2),
				'cctype' => $ccType,
			);

			if($this->getConfigData('useccv')==1) {
				$creditcard["cvmindicator"]	=	"provided";
				$creditcard["cvv2"]			=	$payment->getCcCid();
			}

			$shipping=array();
			$billing=array();
			$transactiondetails=array();

			$paymentdetails['orderid'] = $orderid;
			$paymentdetails['ip'] = $order->getRemoteIp();
			$paymentdetails['customeremail'] = $order->getCustomerEmail();
			$paymentdetails['tax'] = $order->getTaxAmount();
			$paymentdetails['shipping'] = $order->getShippingAmount();
			$paymentdetails['amount'] = $amount;
			$paymentdetails['currency'] = strtolower($order->getOrderCurrencyCode());
			$paymentdetails['description'] = 'Your purchase at ' . $order->getStore()->getGroup()->getName();

			if (empty($BillingAddress)) {
				$billing['firstname'] = '';
				$billing['lastname'] = '';
				$billing['name'] = '';
				$billing['company'] = '';
				$billing['address1'] = '';
				$billing['address2'] = '';
				$billing['city'] = '';
				$billing['state'] = '';
				$billing['state_cd'] = '';
				$billing['zip'] = '';
				$billing['country'] = '';
				$billing['country_id'] = '';
				$billing['phone'] = '';
				$billing['email'] = '';
				$billing['custid'] = 'guest';
			} else {
				$billing['firstname']	=	$BillingAddress->getFirstname();
				$billing['lastname']	=	$BillingAddress->getLastname();
				$billing['name'] =	$BillingAddress->getFirstname()." ".$BillingAddress->getLastname();
				$billing['company']	=	$BillingAddress->getCompany();
				$billing['city']	=	$BillingAddress->getCity();
				$billing['state'] =	$BillingAddress->getRegion();
				$billing['state_cd'] = $BillingAddress->getRegionCode();
				$billing['zip'] =	$BillingAddress->getPostcode();
				$billing['country'] =	$BillingAddress->getCountry();
				$billing['country_id'] =	$BillingAddress->getCountryId();
				$billing['email']	=	$order->getCustomerEmail();
				$billing['phone']	=	$BillingAddress->getTelephone();
				$billing['fax']		=	$BillingAddress->getFax();
				$billing['custid']		=	$BillingAddress->getCustomerId();
				if ($billing['custid'] == '') $billing['custid'] = 'guest';
				$billing['address1'] = '';
				$billing['address2'] = '';
				$street = $BillingAddress->getStreet();
				for ($j = 0; $j < count($street); $j++) {
					$st = 'address' . ($j+1);
					$billing[$st] = $street[$j];
				}
			}
			if (empty($shipping)) {
				$shipping['sfirst'] = '';
				$shipping['slast'] = '';
				$shipping['sname'] = '';
				$shipping['saddress1'] = '';
				$shipping['saddress2'] = '';
				$shipping['scity'] = '';
				$shipping['sstate'] = '';
				$shipping['sstate_cd'] = '';
				$shipping['szip'] = '';
				$shipping['scountry'] = '';
				$shipping['scountry_id'] = '';
				$shipping['sphone'] = '';
				$shipping['semail'] = '';
			} else {
				$shipping['scompany'] =	$ShippingAddress->getCompany();
				$shipping['sfirst'] =	$ShippingAddress->getFirstname();
				$shipping['slast'] =	$ShippingAddress->getLastname();
				$shipping['sname'] =	$ShippingAddress->getFirstname()." ".$ShippingAddress->getLastname();
				$shipping['scity'] =	$ShippingAddress->getCity();
				$shipping['sstate'] =	$ShippingAddress->getRegion();
				$shipping['sstate_cd'] =	$ShippingAddress->getRegionCode();
				$shipping['szip'] =	$ShippingAddress->getPostcode();
				$shipping['scountry'] =	$ShippingAddress->getCountry();
				$shipping['scountry_id'] =	$ShippingAddress->getCountryId();
				$shipping['sphone'] =	$ShippingAddress->getTelephone();
				$shipping['semail'] =	$ShippingAddress->getEmail();
				$street = $ShippingAddress->getStreet();
				$shipping['saddress1'] = '';
				$shipping['saddress2'] = '';
				for ($j = 0; $j < count($street); $j++) {
					$st = 'saddress' . ($j+1);
					$shipping[$st] = $street[$j];
				}
			}
			foreach ($order->getAllVisibleItems() as $item) {
				$transactiondetails[] = array(
						'sku' => $item->getSku(),
						'name' => $item->getName(),
						'description' => '',
						'cost' => $item->getPrice(),
						'taxable' =>  ($item->getTaxAmount() > 0)  ? 'Y' : 'N',
						'qty' => $item->getQtyToInvoice());
			}
			$requestData =array_merge($data, $creditcard, $billing, $shipping, $transactiondetails, $paymentdetails);
			$response = $this->_postRequest($requestData);
			if ($response['error_code'] == '0') {
				$payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_APPROVED);
				$payment->setTransactionId($response['refnum']);
				$payment->setCcTransId($response['refnum']);
   				$payment->setCcAvsStatus($response['avs_result']);
                $payment->setIsTransactionClosed(false);
				$textResult =	'<strong>Authorization Results</strong><br />'.
								'<strong>Approval Code:</strong> '.$response['approval'].'<br />'.
								'<strong>Auth Number:</strong> '.$response['authcode'].'<br />'.
								'<strong>AVS Result:</strong> '.$response['avs_result'].'<br />'.
								'<strong>CVV Result:</strong> '.$response['cvv2_result'];
				$order->addStatusHistoryComment($textResult, true);
				$order->save();
			} else {
				$textResult =	'<strong>Authorization Results</strong><br />'.
								'<strong>Error Code:</strong> '.$response['error_code'].'<br />'.
								'<strong>Error Message:</strong> '.$response['error_message'];
				$order->addStatusHistoryComment($textResult, true);
				$order->save();

				$this->debugData(['request' => $requestData, 'exception' => $response['error_message']]);
				$this->_logger->error(__('Payment Authorize error.'));
				throw new \Magento\Framework\Validator\Exception(__('Payment Authorize error: ' . $response['error_message']));

			}
		} catch (\Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment Authorize error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment Authorize error.'));
        }

        return $this;


	}

    /**
     * Payment capturing
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(InfoInterface $payment, $amount)
    {
        $this->debugData(['Location' => 'capture']);
        try {
			$transactionId = $payment->getParentTransactionId();
            $this->debugData(['Location' => 'capture', 'transactionId' => $transactionId, 'amount' => $amount]);
			if ($transactionId) {
				$data=$this->_prepareData();
				$data['command']	=	"CAPTURE";
				$capture = array(
					'transaction_id' => $transactionId,
					'amount' => $amount,
				);
				$requestData=array_merge($data, $capture);
				$response = $this->_postRequest($requestData);
				if ($response['error_code'] == '0') {
					$payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_APPROVED);
					$payment->setTransactionId($response['refnum']);
					$payment->setIsTransactionPending(false);
					$payment->setIsTransactionClosed(0);
				} else {
					$textResult =	'<strong>Authorization Results</strong><br />'.
									'<strong>Error Code:</strong> '.$response['error_code'].'<br />'.
									'<strong>Error Message:</strong> '.$response['error_message'];
					$order->addStatusHistoryComment($textResult, true);
					$order->save();

					$this->debugData(['request' => $requestData, 'exception' => $response['error_message']]);
					$this->_logger->error(__('Payment Capture error.'));
					throw new \Magento\Framework\Validator\Exception(__('Payment Capture error: ' . $response['error_message']));
				}
			} else {
				$this->authorize($payment, $amount);
			}
        } catch (\Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment Capture error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
        return $this;
    }


    /**
     * Payment refund
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $this->debugData(['Location' => 'refund']);
        try {
			$transactionId = $payment->getParentTransactionId();
            $this->debugData(['Location' => 'refund', 'transactionId' => $transactionId, 'amount' => $amount]);
			if ($transactionId) {
				$data=$this->_prepareData();
				$data['command']	=	"REFUND";
				$refund = array(
					'transaction_id' => $transactionId,
					'amount' => $amount,
				);
				$requestData=array_merge($data, $refund);
				$response = $this->_postRequest($requestData);
				if ($response['error_code'] == '0') {
					$payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_APPROVED)
							->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
							->setParentTransactionId($transactionId)
							->setIsTransactionClosed(1)
							->setShouldCloseParentTransaction(1);
				} else {
					$textResult =	'<strong>Refund Results</strong><br />'.
									'<strong>Error Code:</strong> '.$response['error_code'].'<br />'.
									'<strong>Error Message:</strong> '.$response['error_message'];
					$order->addStatusHistoryComment($textResult, true);
					$order->save();

					$this->debugData(['request' => $requestData, 'exception' => $response['error_message']]);
					$this->_logger->error(__('Payment Refund error.'));
					throw new \Magento\Framework\Validator\Exception(__('Payment Refund error: ' . $response['error_message']));
				}
			} else {
				$this->_logger->error(__('Payment Refund error. Could not getParentTransactionId'));
				throw new \Magento\Framework\Validator\Exception(__('Payment Refund error: Was not able to find parent transaction id'));
			}
        } catch (\Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment Refund error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment Refund error.'));
        }


        return $this;
    }
     /**
     * Payment void
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function void(InfoInterface $payment)
    {
        $this->debugData(['Location' => 'void']);
        try {
			$transactionId = $payment->getParentTransactionId();
            $this->debugData(['Location' => 'refund', 'transactionId' => $transactionId]);
			if ($transactionId) {
				$data=$this->_prepareData();
				$data['command']	=	"VOID";
				$void = array(
					'transaction_id' => $transactionId
				);
				$requestData=array_merge($data, $void);
				$response = $this->_postRequest($requestData);
				if ($response['error_code'] == '0') {
					$payment->setStatus(\Magento\Payment\Model\Method\AbstractMethod::STATUS_APPROVED)
							->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_VOID)
							->setParentTransactionId($transactionId)
							->setIsTransactionClosed(1)
							->setShouldCloseParentTransaction(1);
				} else {
					$textResult =	'<strong>Refund Results</strong><br />'.
									'<strong>Error Code:</strong> '.$response['error_code'].'<br />'.
									'<strong>Error Message:</strong> '.$response['error_message'];
					$order->addStatusHistoryComment($textResult, true);
					$order->save();

					$this->debugData(['request' => $requestData, 'exception' => $response['error_message']]);
					$this->_logger->error(__('Payment Void error.'));
					throw new \Magento\Framework\Validator\Exception(__('Payment Void error: ' . $response['error_message']));
				}
			} else {
				$this->_logger->error(__('Payment VOid error. Could not getParentTransactionId'));
				throw new \Magento\Framework\Validator\Exception(__('Payment Void error: Was not able to find parent transaction id'));
			}
        } catch (\Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment Void error.'));
            throw new \Magento\Framework\Validator\Exception(__('Payment Void error.'));
        }
        return $this;
    }



	protected function _prepareData() {
        $this->debugData(['Location' => '_prepareData']);
		$url = $this->getConfigData('test') ? 'https://devgate.cold-beat.com/vault2/request.php' : 'https://gate.verosa.com/vault2/request.php';
		$data=array(
			'companyid' =>  $this->getConfigData('company_id'),
			'userid'	=>  $this->getConfigData('user_id'),
			'authkey' =>	$this->getConfigData('auth_key'),
			'url' => $url,
			'use_proxy' => 	$this->getConfigData('use_proxy'),
			'proxy_url' => $this->getConfigData('proxy_url'),
			'bypassssl' => $this->getConfigData('bypassssl'),
		);
        $this->debugData(['Location' => '_prepareData', 'data' => $data]);

		return $data;
	}

	protected function _postRequest($data) {
        $this->debugData(['Location' => '_postRequest']);
        $this->debugData(['Location' => '_postRequest', 'data' => $data]);
		$xml='';

		switch($data['command']) {
			case "AUTH":
				$xml = $this->_buildAuthXML($data);
				break;
			case "VOID":
				$xml = $this->_buildVoidXML($data);
				break;
			case "REFUND":
				$xml = $this->_buildRefundXML($data);
				break;
			case "CAPTURE":
				$xml = $this->_buildCaptureXML($data);
				break;
			default:
				$xml = $this->_buildSaleXML($data);
		}
        $this->debugData(['Location' => '_postRequest', 'xml' => $xml]);

		$url = $data["url"];
		$ch = curl_init($url);

		## Method is Post
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: text/xml;charset=UTF-8"));
		curl_setopt($ch, CURLOPT_FRESH_CONNECT,TRUE);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		## Check for a proxy in the config
		if ( $this->getConfigData('use_proxy') ) {
			curl_setopt ($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
			curl_setopt ($ch, CURLOPT_PROXY, $data['proxy_url']);
		}

		## Check to bypass SSL
		if( $data['bypassssl'] ) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		}
		else {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}
		$rawResponse = curl_exec ($ch);
        $this->debugData(['Location' => '_postRequest', 'rawResponse' => $rawResponse]);
		## Check for cURL error
		if($rawResponse === false) {
			$errText = curl_error($ch) . ' (error '.curl_errno($ch).')';
			throw new \Magento\Framework\Validator\Exception(__('Error during payment transmission:  ' . $errText ));
		} else {

			$parts = explode('<?xml version="1.0" encoding="UTF-8" ?>', $rawResponse);
			$result = $parts[1];
			#convert xml response to hash
			$retarr = $this->_readResponse($result);
			## Custom filters based on the error code
			switch($retarr['error_code']) {
				case 0:
					return $retarr;
					break;
				default:
					throw new \Magento\Framework\Validator\Exception(__('An error occurred while processing: '.$retarr['error'].' (error code '.$retarr['error_code'].') '));
					break;
			}
		}
	}

	/**
     * converts the  response xml string
     * to a hash of name-value pairs
	 *
     * @param String $xml
     * @return Array $retarr
     */
	protected function _readResponse($xml) {
        $this->debugData(['Location' => '_readResponse']);
        $this->debugData(['Location' => '_readResponse', 'xml' => $xml]);
		$rt_array = array();

		$xml_object  = simplexml_load_string($xml);
        $this->debugData(['Location' => '_readResponse', 'xml_object' => $xml_object]);
		if ( $xml_object  === false)
		{
			$rt_array['error'] = "Gateway processing error";
			$rt_array['error_code'] = "20";
			return $rt_array;
		}

		$tmp=$this->object2array($xml_object);
		$rt_array['error'] = $tmp["Error"]["error_message"];
		$rt_array['error_code'] = $tmp["Error"]["error_code"];

		if ($rt_array['error_code']) {
			$rt_array['result'] = (empty($tmp["Transaction"]["transactionresult"])) ? $rt_array['error'] : $tmp["Transaction"]["transactionresult"];
			$rt_array['resultcode'] = (empty($tmp["Transaction"]["transactionresponsecode"])) ? $rt_array['error_code'] : $tmp["Transaction"]["transactionresponsecode"];
			$rt_array['error'] = (empty($tmp["Transaction"]["transactionbankerrormessage"])) ? $rt_array['error'] : $tmp["Transaction"]["transactionbankerrormessage"];
			$rt_array['error_code'] = (empty($tmp["Transaction"]["transactionbankerrorcode"])) ? $rt_array['error_code'] : $tmp["Transaction"]["transactionbankerrorcode"];
			$rt_array['authcode'] = '';
			$rt_array['approval'] = '';
			$rt_array['refnum'] = '';
			$rt_array['avs_result'] = '';
			$rt_array['avs_result_code'] = '';
			$rt_array['cvv2_result'] = '';
			$rt_array['cvv2_result_code'] = '';
		} else {
			$rt_array['result'] = (empty($tmp["Transaction"]["transactionresult"])) ? '' : $tmp["Transaction"]["transactionresult"];
			$rt_array['resultcode'] = (empty($tmp["Transaction"]["transactionresponsecode"])) ? '' : $tmp["Transaction"]["transactionresponsecode"];
			$rt_array['authcode'] = (empty($tmp["Transaction"]["transactionauthnumber"])) ? '' : $tmp["Transaction"]["transactionauthnumber"];
			$rt_array['approval'] = (empty($tmp["Transaction"]["transactionapproval"])) ? '' : $tmp["Transaction"]["transactionapproval"];
			$rt_array['refnum'] = (empty($tmp["Vault"]["vaultrecordid"])) ? '' : $tmp["Vault"]["vaultrecordid"];
			$rt_array['avs_result'] = (empty($tmp["Transaction"]["avs_result"])) ? '' : $tmp["Transaction"]["avs_result"];
			$rt_array['avs_result_code'] = (empty($tmp["Transaction"]["avs_result"])) ? '' : $tmp["Transaction"]["avs_result"];
			$rt_array['cvv2_result'] = (empty($tmp["Transaction"]["csc_result"])) ? '' : $tmp["Transaction"]["csc_result"];
			$rt_array['cvv2_result_code'] = (empty($tmp["Transaction"]["csc_result"])) ? '' : $tmp["Transaction"]["csc_result"];
		}
        $this->debugData(['Location' => '_readResponse', 'rt_array' => $rt_array]);
		return $rt_array;
	}

	protected function _buildAuthXml($pdata) {
		$xml  = "
<Request>
	<UserAuth>
		<companyid>".$pdata['companyid']."</companyid>
		<userid>".$pdata['userid']."</userid>
		<authkey>".$pdata['authkey']."</authkey>
		<vaultversion>".$this->_VerosaVaultVersion."</vaultversion>
		<osversion>".PHP_OS."</osversion>
		<productid>".$this->_VerosaProductCode."</productid>
        <version>".$this->_VerosaVersion."</version>
        <uname>".php_uname()."</uname>
        <phpversion>".phpversion()."</phpversion>
	</UserAuth>
	<Vault>
		<vaultaction>37</vaultaction>
	</Vault>
	<ClientInfo>
		<qbid>".$pdata['custid']."</qbid>
		<firstname>".$pdata['firstname']."</firstname>
		<lastname>".$pdata['lastname']."</lastname>
		<companyname>".$pdata['company']."</companyname>
		<address1>".$pdata['address1']."</address1>
		<address2>".$pdata['address2']."</address2>
		<city>".$pdata['city']."</city>
		<state>".$pdata['state_cd']."</state>
		<zip>".$pdata['zip']."</zip>
		<country>".$pdata['country_id']."</country>
		<phone>".$pdata['phone']."</phone>
		<email>".$pdata['email']."</email>
		<shipto>
			<firstname>".$pdata['sfirst']."</firstname>
			<lastname>".$pdata['slast']."</lastname>
			<companyname>".$pdata['company']."</companyname>
			<address1>".$pdata['saddress1']."</address1>
			<address2>".$pdata['saddress2']."</address2>
			<city>".$pdata['scity']."</city>
			<state>".$pdata['sstate_cd']."</state>
			<zip>".$pdata['szip']."</zip>
			<country>".$pdata['scountry_id']."</country>
			<phone>".$pdata['phone']."</phone>
			<email>".$pdata['email']."</email>
		</shipto>
	</ClientInfo>
	<CCPayment>
		<companyname>".$pdata['company']."</companyname>
		<nameoncard>".$pdata['nameoncard']."</nameoncard>
		<cardnumber>".$pdata['cardnumber']."</cardnumber>
		<cctype>".$pdata['cctype']."</cctype>
		<cvv2>".$pdata['cvv2']."</cvv2>
		<expire>".$pdata['cardexpmonth']."/".$pdata['cardexpyear']."</expire>
		<trackdata></trackdata>
		<voiceauthorization/>
		<testmode>0</testmode>
		<batchdefault>0</batchdefault>
		<default>0</default>
	</CCPayment>
	<PaymentInfo>
		<paymentammount>".$pdata['amount']."</paymentammount>
		<currency>".$pdata['currency']."</currency>
		<invoice>".$pdata['orderid']."</invoice>
		<salesreceipt>".$pdata['description']."</salesreceipt>
		<statementcharge></statementcharge>
		<seccode></seccode>
		<leveltwo>".$pdata['orderid']."</leveltwo>
		<checknumber></checknumber>
		<job></job>
		<batchid></batchid>
	</PaymentInfo>
	<Level2>
		<CustomerNumber>".$pdata['custid']."</CustomerNumber>
		<PoField>".$pdata['ponum']."</PoField>
		<TaxAmount>".$pdata['tax']."</TaxAmount>
	</Level2>
</Request>";
		$xml = trim($xml);
		return $xml;
	}

	protected function _buildVoidXML($pdata) {
		$xml  = "
<Request>
	<UserAuth>
		<companyid>".$pdata['companyid']."</companyid>
		<userid>".$pdata['userid']."</userid>
		<authkey>".$pdata['authkey']."</authkey>
		<vaultversion>".$this->_VerosaVaultVersion."</vaultversion>
		<osversion>".PHP_OS."</osversion>
		<productid>".$this->_VerosaProductCode."</productid>
        <version>".$this->_VerosaVersion."</version>
        <uname>".php_uname()."</uname>
        <phpversion>".phpversion()."</phpversion>
	</UserAuth>
	<Vault>
		<vaultaction>30</vaultaction>
	</Vault>
	<VoidTransaction>
		<vaulttransactionid>".$pdata['transaction_id']."</vaulttransactionid>
		<transactionid></transactionid>
	</VoidTransaction></Request>";
		$xml = trim($xml);
		return $xml;
	}

	protected function _buildRefundXML($pdata) {
		$xml  = "
<Request>
	<UserAuth>
		<companyid>".$pdata['companyid']."</companyid>
		<userid>".$pdata['userid']."</userid>
		<authkey>".$pdata['authkey']."</authkey>
		<vaultversion>".$this->_VerosaVaultVersion."</vaultversion>
		<osversion>".PHP_OS."</osversion>
		<productid>".$this->_VerosaProductCode."</productid>
        <version>".$this->_VerosaVersion."</version>
        <uname>".php_uname()."</uname>
        <phpversion>".phpversion()."</phpversion>
	</UserAuth>
	<Vault>
		<vaultaction>29</vaultaction>
	</Vault>
	<RefundTransaction>
		<vaulttransactionid>".$pdata['transaction_id']."</vaulttransactionid>
		<transactionid></transactionid>
		<refundamount>".$pdata['amount']."</refundamount>
	</RefundTransaction>
</Request>";
		$xml = trim($xml);
		return $xml;
	}

	protected function _buildCaptureXML($pdata) {
		$xml  = "
<Request>
	<UserAuth>
		<companyid>".$pdata['companyid']."</companyid>
		<userid>".$pdata['userid']."</userid>
		<authkey>".$pdata['authkey']."</authkey>
		<vaultversion>".$this->_VerosaVaultVersion."</vaultversion>
		<osversion>".PHP_OS."</osversion>
		<productid>".$this->_VerosaProductCode."</productid>
        <version>".$this->_VerosaVersion."</version>
        <uname>".php_uname()."</uname>
        <phpversion>".phpversion()."</phpversion>
	</UserAuth>
	<Vault>
		<vaultaction>39</vaultaction>
	</Vault>
	<SettleTransaction>
		<vaulttransactionid>".$pdata['transaction_id']."</vaulttransactionid>
		<transactionid></transactionid>
		<settleamount>".$pdata['amount']."</settleamount>
	</SettleTransaction>
</Request>";
		$xml = trim($xml);
		return $xml;
	}
	protected function _buildSaleXml($pdata) {
		$xml  = "
<Request>
	<UserAuth>
		<companyid>".$pdata['companyid']."</companyid>
		<userid>".$pdata['userid']."</userid>
		<authkey>".$pdata['authkey']."</authkey>
		<vaultversion>".$this->_VerosaVaultVersion."</vaultversion>
		<osversion>".PHP_OS."</osversion>
		<productid>".$this->_VerosaProductCode."</productid>
        <version>".$this->_VerosaVersion."</version>
        <uname>".php_uname()."</uname>
        <phpversion>".phpversion()."</phpversion>
	</UserAuth>
	<Vault>
		<vaultaction>8</vaultaction>
	</Vault>
	<ClientInfo>
		<qbid>".$pdata['custid']."</qbid>
		<firstname>".$pdata['firstname']."</firstname>
		<lastname>".$pdata['lastname']."</lastname>
		<companyname>".$pdata['company']."</companyname>
		<address1>".$pdata['address1']."</address1>
		<address2>".$pdata['address2']."</address2>
		<city>".$pdata['city']."</city>
		<state>".$pdata['state_cd']."</state>
		<zip>".$pdata['zip']."</zip>
		<country>".$pdata['country_id']."</country>
		<phone>".$pdata['phone']."</phone>
		<email>".$pdata['email']."</email>
		<shipto>
			<firstname>".$pdata['sfirst']."</firstname>
			<lastname>".$pdata['slast']."</lastname>
			<companyname>".$pdata['company']."</companyname>
			<address1>".$pdata['saddress1']."</address1>
			<address2>".$pdata['saddress2']."</address2>
			<city>".$pdata['scity']."</city>
			<state>".$pdata['sstate_cd']."</state>
			<zip>".$pdata['szip']."</zip>
			<country>".$pdata['scountry_id']."</country>
			<phone>".$pdata['phone']."</phone>
			<email>".$pdata['email']."</email>
		</shipto>
	</ClientInfo>
	<CCPayment>
		<companyname>".$pdata['company']."</companyname>
		<nameoncard>".$pdata['nameoncard']."</nameoncard>
		<cardnumber>".$pdata['cardnumber']."</cardnumber>
		<cctype>".$pdata['cctype']."</cctype>
		<cvv2>".$pdata['cvv2']."</cvv2>
		<expire>".$pdata['cardexpmonth']."/".$pdata['cardexpyear']."</expire>
		<trackdata></trackdata>
		<voiceauthorization/>
		<testmode>0</testmode>
		<batchdefault>0</batchdefault>
		<default>0</default>
	</CCPayment>
	<PaymentInfo>
		<paymentammount>".$pdata['amount']."</paymentammount>
		<currency>".$pdata['currency']."</currency>
		<invoice>".$pdata['orderid']."</invoice>
		<salesreceipt>".$pdata['description']."</salesreceipt>
		<statementcharge></statementcharge>
		<seccode></seccode>
		<leveltwo>".$pdata['orderid']."</leveltwo>
		<checknumber></checknumber>
		<job></job>
		<batchid></batchid>
	</PaymentInfo>
	<Level2>
		<CustomerNumber>".$pdata['custid']."</CustomerNumber>
		<PoField>".$pdata['ponum']."</PoField>
		<TaxAmount>".$pdata['tax']."</TaxAmount>
	</Level2>
</Request>";
		$xml = trim($xml);
		return $xml;
	}


	protected function object2array($object) { return @json_decode(@json_encode($object),1); }

    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote && (
            $quote->getBaseGrandTotal() < $this->_minAmount
            || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
        ) {
            return false;
        }

        if (!$this->getConfigData('auth_key')) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }
}
