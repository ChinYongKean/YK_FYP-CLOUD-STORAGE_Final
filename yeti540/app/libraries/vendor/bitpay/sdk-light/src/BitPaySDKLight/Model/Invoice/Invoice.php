<?php

namespace BitPaySDKLight\Model\Invoice;


use BitPaySDKLight\Exceptions\BitPayException;
use BitPaySDKLight\Model\Currency;

/**
 *
 * @package Bitpay light
 */
class Invoice
{
    protected $_currency;

    protected $_guid  = "";
    protected $_token = "";

    protected $_price;
    protected $_posData           = "";
    protected $_notificationURL   = "";
    protected $_transactionSpeed  = "";
    protected $_fullNotifications = false;
    protected $_notificationEmail = "";
    protected $_redirectURL       = "";
    protected $_closeURL       = "";
    protected $_autoRedirect  = false;
    protected $_jsonPayProRequired;
    protected $_buyerSms;
    protected $_buyerEmail;
    protected $_smsCode;
    protected $_merchantName;
    protected $_forcedBuyerSelectedWallet;
    protected $_selectedTransactionCurrency;
    protected $_orderId           = "";
    protected $_itemDesc          = "";
    protected $_itemCode          = "";
    protected $_physical          = false;
    protected $_description;
    protected $_paymentCurrencies;
    protected $_paymentSubtotals;
    protected $_paymentTotals;
    protected $_paymentDisplayTotals;
    protected $_paymentDisplaySubTotals;
    protected $_paymentCodes;
    protected $_acceptanceWindow;
    protected $_buyer;
    protected $_refundAddresses;

    protected $_id;
    protected $_url;
    protected $_status;
    protected $_lowFeeDetected;
    protected $_invoiceTime;
    protected $_expirationTime;
    protected $_currentTime;
    protected $_transactions;
    protected $_exceptionStatus;
    protected $_targetConfirmations;
    protected $_refundAddressRequestPending;
    protected $_buyerProvidedEmail;
    protected $_buyerProvidedInfo;
    protected $_paymentString;
    protected $_verificationLink;
    protected $_supportedTransactionCurrencies;
    protected $_minerFees;
    protected $_nonPayProPaymentReceived;
    protected $_shopper;
    protected $_billId;
    protected $_refundInfo;
    protected $_extendedNotifications = false;
    protected $_isCancelled;
    protected $_fiatAmount;

    protected $_transactionCurrency;
    protected $_amount;
    protected $_isFee;
    protected $_underpaidAmount;
    protected $_overpaidAmount;
    protected $_amountPaid;
    protected $_displayAmountPaid;
    protected $_exchangeRates;
    protected $_bitpayIdRequired;

    /**
     * Constructor, create a minimal request Invoice object.
     *
     * @param $price    float The amount for which the invoice will be created.
     * @param $currency string three digit currency type used to compute the invoice bitcoin amount.
     */
    public function __construct(float $price = null, string $currency = null)
    {
        $this->_price = $price;
        $this->_currency = $currency;
        $this->_buyer = new Buyer();
        $this->_buyerProvidedInfo = new BuyerProvidedInfo();
        $this->_supportedTransactionCurrencies = new SupportedTransactionCurrencies();
        $this->_minerFees = new MinerFees();
        $this->_shopper = new Shopper();
        $this->_refundInfo = new RefundInfo();
    }

    // API fields
    //

    public function getCurrency()
    {
        return $this->_currency;
    }

    // Required fields
    //

    public function setCurrency(string $currency)
    {
        if (!Currency::isValid($currency)) {
            throw new BitPayException("currency code must be a type of Model.Currency");
        }

        $this->_currency = $currency;
    }

    public function getGuid()
    {
        return $this->_guid;
    }

    public function setGuid(string $guid)
    {
        $this->_guid = $guid;
    }

    public function getToken()
    {
        return $this->_token;
    }

    // Optional fields
    //

    public function setToken(string $token)
    {
        $this->_token = $token;
    }

    public function getPrice()
    {
        return $this->_price;
    }

    public function setPrice(float $price)
    {
        $this->_price = $price;
    }

    public function getPosData()
    {
        return $this->_posData;
    }

    public function setPosData(string $posData)
    {
        $this->_posData = $posData;
    }

    public function getNotificationURL()
    {
        return $this->_notificationURL;
    }

    public function setNotificationURL(string $notificationURL)
    {
        $this->_notificationURL = $notificationURL;
    }

    public function getTransactionSpeed()
    {
        return $this->_transactionSpeed;
    }

    public function setTransactionSpeed(string $transactionSpeed)
    {
        $this->_transactionSpeed = $transactionSpeed;
    }

    public function getFullNotifications()
    {
        return $this->_fullNotifications;
    }

    public function setFullNotifications(bool $fullNotifications)
    {
        $this->_fullNotifications = $fullNotifications;
    }

    public function getNotificationEmail()
    {
        return $this->_notificationEmail;
    }

    public function setNotificationEmail(string $notificationEmail)
    {
        $this->_notificationEmail = $notificationEmail;
    }

    public function getRedirectURL()
    {
        return $this->_redirectURL;
    }

    public function setRedirectURL(string $redirectURL)
    {
        $this->_redirectURL = $redirectURL;
    }

    public function getCloseURL()
    {
        return $this->_closeURL;
    }

    public function setCloseURL(string $closeURL)
    {
        $this->_closeURL = $closeURL;
    }

    public function getAutoRedirect()
    {
        return $this->_autoRedirect;
    }

    public function setAutoRedirect(bool $autoRedirect)
    {
        $this->_autoRedirect = $autoRedirect;
    }

    public function getJsonPayProRequired()
    {
        return $this->_jsonPayProRequired;
    }

    public function setJsonPayProRequired(bool $jsonPayProRequired)
    {
        $this->_jsonPayProRequired = $jsonPayProRequired;
    }

    public function getBitpayIdRequired()
    {
        return $this->_bitpayIdRequired;
    }

    public function setBitpayIdRequired(bool $bitpayIdRequired)
    {
        $this->_bitpayIdRequired = $bitpayIdRequired;
    }

    public function getMerchantName()
    {
        return $this->_merchantName;
    }

    public function setMerchantName(string $merchantName)
    {
        $this->_merchantName = $merchantName;
    }

    public function getForcedBuyerSelectedWallet()
    {
        return $this->_forcedBuyerSelectedWallet;
    }

    public function setForcedBuyerSelectedWallet(string $forcedBuyerSelectedWallet)
    {
        $this->_forcedBuyerSelectedWallet = $forcedBuyerSelectedWallet;
    }

    public function getSelectedTransactionCurrency()
    {
        return $this->_selectedTransactionCurrency;
    }

    public function setSelectedTransactionCurrency(string $selectedTransactionCurrency)
    {
        $this->_selectedTransactionCurrency = $selectedTransactionCurrency;
    }

    public function getOrderId()
    {
        return $this->_orderId;
    }

    public function setOrderId(string $orderId)
    {
        $this->_orderId = $orderId;
    }

    public function getItemDesc()
    {
        return $this->_itemDesc;
    }

    public function setItemDesc(string $itemDesc)
    {
        $this->_itemDesc = $itemDesc;
    }

    public function getItemCode()
    {
        return $this->_itemCode;
    }

    public function setItemCode(string $itemCode)
    {
        $this->_itemCode = $itemCode;
    }

    public function getPhysical()
    {
        return $this->_physical;
    }

    public function setPhysical(bool $physical)
    {
        $this->_physical = $physical;
    }

    public function getPaymentCurrencies()
    {
        return $this->_paymentCurrencies;
    }

    public function setPaymentCurrencies(array $paymentCurrencies)
    {
        $this->_paymentCurrencies = $paymentCurrencies;
    }

    public function getAcceptanceWindow()
    {
        return $this->_acceptanceWindow;
    }

    // Buyer data
    //

    public function setAcceptanceWindow(float $acceptanceWindow)
    {
        $this->_acceptanceWindow = $acceptanceWindow;
    }

    public function getBuyer()
    {
        return $this->_buyer;
    }

    public function setBuyer(Buyer $buyer)
    {
        $this->_buyer = $buyer;
    }

    public function getBuyerSms()
    {
        return $this->_buyerSms;
    }

    public function setBuyerSms(string $buyerSms)
    {
        $this->_buyerSms = $buyerSms;
    }

    public function getBuyerEmail()
    {
        return $this->_buyerEmail;
    }

    public function setBuyerEmail(string $buyerEmail)
    {
        $this->_buyerEmail = $buyerEmail;
    }

    public function getSmsCode()
    {
        return $this->_smsCode;
    }

    public function setSmsCode(string $smsCode)
    {
        $this->_smsCode = $smsCode;
    }

    // Response fields
    //

    public function getRefundAddresses()
    {
        return $this->_refundAddresses;
    }

    public function setRefundAddresses(array $refundAddresses)
    {
        $this->_refundAddresses = $refundAddresses;
    }

    public function getId()
    {
        return $this->_id;
    }
    
    public function setId($id)
    {
        $this->_id = $id;
    }
    
    public function getUrl()
    {
        return $this->_url;
    }
    
    public function setUrl($url)
    {
        $this->_url = $url;
    }
    
    public function getPaymentSubTotals()
    {
        return $this->_paymentSubtotals;
    }

    public function setPaymentSubTotals($paymentSubtotals)
    {
        $this->_paymentSubtotals = $paymentSubtotals;
    }

    public function getPaymentTotals()
    {
        return $this->_paymentTotals;
    }

    public function setPaymentTotals($paymentTotals)
    {
        $this->_paymentTotals = $paymentTotals;
    }

    public function getPaymentDisplayTotals()
    {
        return $this->_paymentDisplayTotals;
    }

    public function setPaymentDisplayTotals($paymentDisplayTotals)
    {
        $this->_paymentDisplayTotals = $paymentDisplayTotals;
    }

    public function getPaymentDisplaySubTotals()
    {
        return $this->_paymentDisplaySubTotals;
    }

    public function setPaymentDisplaySubTotals($paymentDisplaySubTotals)
    {
        $this->_paymentDisplaySubTotals = $paymentDisplaySubTotals;
    }

    public function getPaymentCodes()
    {
        return $this->_paymentCodes;
    }

    public function setPaymentCodes($paymentCodes)
    {
        $this->_paymentCodes = $paymentCodes;
    }

    public function getPaymentString()
    {
        return $this->_paymentString;
    }

    public function setPaymentString(string $paymentString)
    {
        $this->_paymentString = $paymentString;
    }

    public function getVerificationLink()
    {
        return $this->_verificationLink;
    }

    public function setVerificationLink(string $verificationLink)
    {
        $this->_verificationLink = $verificationLink;
    }

    public function getAmount()
    {
        return $this->_amount;
    }

    public function setAmount($amount)
    {
        $this->_amount = $amount;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function setDescription($description)
    {
        $this->_description = $description;
    }

    public function getIsFee()
    {
        return $this->_isFee;
    }

    public function setIsFee(bool $isFee)
    {
        $this->_isFee = $isFee;
    }

    public function getUnderpaidAmount()
    {
        return $this->_underpaidAmount;
    }

    public function setUnderpaidAmount($underpaidAmount)
    {
        $this->_underpaidAmount = $underpaidAmount;
    }

    public function getOverpaidAmount()
    {
        return $this->_overpaidAmount;
    }

    public function setOverpaidAmount($overpaidAmount)
    {
        $this->_overpaidAmount = $overpaidAmount;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function setStatus($status)
    {
        $this->_status = $status;
    }

    public function getLowFeeDetected()
    {
        return $this->_lowFeeDetected;
    }

    public function setLowFeeDetected($lowFeeDetected)
    {
        $this->_lowFeeDetected = $lowFeeDetected;
    }

    public function getInvoiceTime()
    {
        return $this->_invoiceTime;
    }

    public function setInvoiceTime($invoiceTime)
    {
        $this->_invoiceTime = $invoiceTime;
    }

    public function getExpirationTime()
    {
        return $this->_expirationTime;
    }

    public function setExpirationTime($expirationTime)
    {
        $this->_expirationTime = $expirationTime;
    }

    public function getCurrentTime()
    {
        return $this->_currentTime;
    }

    public function setCurrentTime($currentTime)
    {
        $this->_currentTime = $currentTime;
    }

    public function getTransactions()
    {
        return $this->_transactions;
    }

    public function setTransactions($transactions)
    {
        $this->_transactions = $transactions;
    }

    public function getExceptionStatus()
    {
        return $this->_exceptionStatus;
    }

    public function setExceptionStatus($exceptionStatus)
    {
        $this->_exceptionStatus = $exceptionStatus;
    }

    public function getTargetConfirmations()
    {
        return $this->_targetConfirmations;
    }

    public function setTargetConfirmations($targetConfirmations)
    {
        $this->_targetConfirmations = $targetConfirmations;
    }

    public function getRefundAddressRequestPending()
    {
        return $this->_refundAddressRequestPending;
    }

    public function setRefundAddressRequestPending($refundAddressRequestPending)
    {
        $this->_refundAddressRequestPending = $refundAddressRequestPending;
    }

    public function getBuyerProvidedEmail()
    {
        return $this->_buyerProvidedEmail;
    }

    public function setBuyerProvidedEmail($buyerProvidedEmail)
    {
        $this->_buyerProvidedEmail = $buyerProvidedEmail;
    }

    public function getBuyerProvidedInfo()
    {
        return $this->_buyerProvidedInfo;
    }

    public function setBuyerProvidedInfo(BuyerProvidedInfo $buyerProvidedInfo)
    {
        $this->_buyerProvidedInfo = $buyerProvidedInfo;
    }

    public function getSupportedTransactionCurrencies()
    {
        return $this->_supportedTransactionCurrencies;
    }

    public function setSupportedTransactionCurrencies(SupportedTransactionCurrencies $supportedTransactionCurrencies)
    {
        $this->_supportedTransactionCurrencies = $supportedTransactionCurrencies;
    }

    public function getMinerFees()
    {
        return $this->_minerFees;
    }

    public function setMinerFees(MinerFees $minerFees)
    {
        $this->_minerFees = $minerFees;
    }

    public function getNonPayProPaymentReceived()
    {
        return $this->_nonPayProPaymentReceived;
    }

    public function setNonPayProPaymentReceived(bool $nonPayProPaymentReceived)
    {
        $this->_nonPayProPaymentReceived = $nonPayProPaymentReceived;
    }

    public function getShopper()
    {
        return $this->_shopper;
    }

    public function setShopper(Shopper $shopper)
    {
        $this->_shopper = $shopper;
    }

    public function getBillId()
    {
        return $this->_billId;
    }

    public function setBillId($billId)
    {
        $this->_billId = $billId;
    }

    public function getRefundInfo()
    {
        return $this->_refundInfo;
    }

    public function setRefundInfo(RefundInfo $refundInfo)
    {
        $this->_refundInfo = $refundInfo;
    }

    public function getExtendedNotifications()
    {
        return $this->_extendedNotifications;
    }

    public function setExtendedNotifications(bool $extendedNotifications)
    {
        $this->_extendedNotifications = $extendedNotifications;
    }

    public function getTransactionCurrency()
    {
        return $this->_transactionCurrency;
    }

    public function setTransactionCurrency($transactionCurrency)
    {
        $this->_transactionCurrency = $transactionCurrency;
    }

    public function getAmountPaid()
    {
        return $this->_amountPaid;
    }

    public function setAmountPaid($amountPaid)
    {
        $this->_amountPaid = $amountPaid;
    }

    public function getDisplayAmountPaid()
    {
        return $this->_displayAmountPaid;
    }

    public function setDisplayAmountPaid(string $displayAmountPaid)
    {
        $this->_displayAmountPaid = $displayAmountPaid;
    }

    public function getExchangeRates()
    {
        return $this->_exchangeRates;
    }

    public function setExchangeRates($exchangeRates)
    {
        $this->_exchangeRates = $exchangeRates;
    }

    public function getIsCancelled()
    {
        return $this->_isCancelled;
    }

    public function setIsCancelled(bool $isCancelled)
    {
        $this->_isCancelled = $isCancelled;
    }

    public function getFiatAmount()
    {
        return $this->_fiatAmount;
    }

    public function setFiatAmount($fiatAmount)
    {
        $this->_fiatAmount = $fiatAmount;
    }

    public function toArray()
    {
        $elements = [
            'currency'                       => $this->getCurrency(),
            'guid'                           => $this->getGuid(),
            'token'                          => $this->getToken(),
            'price'                          => $this->getPrice(),
            'posData'                        => $this->getPosData(),
            'notificationURL'                => $this->getNotificationURL(),
            'closeURL'                       => $this->getCloseURL(),
            'transactionSpeed'               => $this->getTransactionSpeed(),
            'fullNotifications'              => $this->getFullNotifications(),
            'notificationEmail'              => $this->getNotificationEmail(),
            'redirectURL'                    => $this->getRedirectURL(),
            'orderId'                        => $this->getOrderId(),
            'itemDesc'                       => $this->getItemDesc(),
            'itemCode'                       => $this->getItemCode(),
            'physical'                       => $this->getPhysical(),
            'paymentCurrencies'              => $this->getPaymentCurrencies(),
            'acceptanceWindow'               => $this->getAcceptanceWindow(),
            'buyer'                          => $this->getBuyer()->toArray(),
            'refundAddresses'                => $this->getRefundAddresses(),
            'autoRedirect'                   => $this->getAutoRedirect(),
            'id'                             => $this->getId(),
            'url'                            => $this->getUrl(),
            'status'                         => $this->getStatus(),
            'lowFeeDetected'                 => $this->getLowFeeDetected(),
            'invoiceTime'                    => $this->getInvoiceTime(),
            'expirationTime'                 => $this->getExpirationTime(),
            'currentTime'                    => $this->getCurrentTime(),
            'transactions'                   => $this->getTransactions(),
            'exceptionStatus'                => $this->getExceptionStatus(),
            'targetConfirmations'            => $this->getTargetConfirmations(),
            'refundAddressRequestPending'    => $this->getRefundAddressRequestPending(),
            'buyerProvidedEmail'             => $this->getBuyerProvidedEmail(),
            'buyerProvidedInfo'              => $this->getBuyerProvidedInfo()->toArray(),
            'supportedTransactionCurrencies' => $this->getSupportedTransactionCurrencies()->toArray(),
            'minerFees'                      => $this->getMinerFees()->toArray(),
            'shopper'                        => $this->getShopper()->toArray(),
            'billId'                         => $this->getBillId(),
            'refundInfo'                     => $this->getRefundInfo()->toArray(),
            'extendedNotifications'          => $this->getExtendedNotifications(),
            'nonPayProPaymentReceived'       => $this->getNonPayProPaymentReceived(),
            'transactionCurrency'            => $this->getTransactionCurrency(),
            'amountPaid'                     => $this->getAmountPaid(),
            'exchangeRates'                  => $this->getExchangeRates(),
            'merchantName'                   => $this->getMerchantName(),
            'bitpayIdRequired'               => $this->getBitpayIdRequired(),
            'forcedBuyerSelectedWallet'      => $this->getForcedBuyerSelectedWallet(),
            'buyerSms'                       => $this->getBuyerSms(),
            'paymentString'                  => $this->getPaymentString(),
            'verificationLink'               => $this->getVerificationLink(),
            'amount'                         => $this->getAmount(),
            'description'                    => $this->getDescription(),
            'isFee'                          => $this->getIsFee(),
            'isCancelled'                    => $this->getIsCancelled(),
            'fiatAmount'                     => $this->getFiatAmount(),
            'buyerEmail'                     => $this->getBuyerEmail(),
            'smsCode'                        => $this->getSmsCode(),
            'paymentTotals'                  => $this->getPaymentTotals(),
            'paymentSubtotals'               => $this->getPaymentSubTotals(),
            'paymentDisplayTotals'           => $this->getPaymentDisplayTotals(),
            'paymentDisplaySubTotals'        => $this->getPaymentDisplaySubTotals(),
            'displayAmountPaid'              => $this->getDisplayAmountPaid(),
            'paymentCodes'                   => $this->getPaymentCodes(),
            'paymentString'                  => $this->getPaymentString(),
            'verificationLink'               => $this->getVerificationLink(),
            'bitpayIdRequired'               => $this->getBitpayIdRequired()
        ];

        foreach ($elements as $key => $value) {
            if (empty($value)) {
                unset($elements[$key]);
            }
        }

        return $elements;
    }
}
