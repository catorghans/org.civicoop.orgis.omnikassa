<?php

class CRM_Core_Payment_Omnikassa extends CRM_Core_Payment{
  CONST CHARSET = 'iso-8859-1';

  protected $_mode = NULL;

  protected $_key = '';

  protected $_algorithm = 'md5';
  /**
* We only need one instance of this object. So we use the singleton
* pattern and cache the instance in this variable
*
* @var object
* @static
*/
  static private $_singleton = NULL;

  /**
* Constructor
*
* @param string $mode the mode of operation: live or test
*
* @return void
*/
  function __construct($mode, &$paymentProcessor) {

    $this->_mode = $mode;
    $this->_key = $paymentProcessor['password'];
//    $this->_algorithm = empty($paymentProcessor['subject']) ? 'md5' : $paymentProcessor['subject'];

    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('Omnikassa');
  }

  /**
* singleton function used to manage this object
*
* @param string $mode the mode of operation: live or test
*
* @return object
* @static
*
*/
  static function &singleton($mode = 'test', &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    $processorName = $paymentProcessor['name'];
    if (self::$_singleton[$processorName] === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_Omnikassa($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  function checkConfig() {
    $config = CRM_Core_Config::singleton();

    $error = array();

    if (empty($this->_paymentProcessor['user_name'])) {
      $error[] = ts('Merchant ID is not set in the Administer &raquo; System Settings &raquo; Payment Processors');
    }

    if (empty($this->_paymentProcessor['password'])) {
      $error[] = ts('Secret Key is not set in the Administer &raquo; System Settings &raquo; Payment Processors');
    }

    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  function setessCheckOut(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  function doDirectPayment(&$params) {
    CRM_Core_Error::fatal(ts('This function is not implemented'));
  }

  /**
* Main transaction function
*
* @param array $params name value pair of contribution data
*
* @return void
* @access public
*
*/
  function doTransferCheckout(&$params, $component) {

/*    if ($component == 'contribute') {
      $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() . "civicrm/payment/ipn?processor_name=Zaakpay&md=contribute&qfKey=" . $params['qfKey'] . '&inId=' . $par
ams['invoiceID'];
    }
    else if ($component == 'event') {
      $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() . "civicrm/payment/ipn?processor_name=Zaakpay&md=event&qfKey=" . $params['qfKey'] . '&pid=' . $params['p
articipantID'] . "&eid=" . $params['eventID'] . "&inId=" . $params['invoiceID'];
    } */

    $baseURL = 'civicrm/payment/ipn';

    $component = strtolower($component);


    if ($component == 'event') {
//      $baseURL = 'civicrm/event/register';
      $returnURL = CRM_Utils_System::url($baseURL,array(
        'md' => $component,
        'qfKey' => $params['qfKey'],
        'processor_id' => $params['payment_processor_id'],
        'mode' => $this->_mode,
        'participantId' => $orderID[4],
       ),
       TRUE, NULL, FALSE
      );
    }
    elseif ($component == 'contribute') {
//      $baseURL = 'civicrm/contribute/transact';
      $returnURL = CRM_Utils_System::url($baseURL,array(
        'md' => $component,
        'qfKey' => $params['qfKey'],
        'processor_id' => $params['payment_processor_id'],
        'mode' => $this->_mode,
       ),
       TRUE, NULL, FALSE
      );
    }

    $notificationUrl = CRM_Utils_System::url($baseURL, array(
       'processor_id' => $params['payment_processor_id'],
       'mode' => $this->_mode,
       ), TRUE, NULL, FALSE
    );
   
    $config = CRM_Core_Config::singleton();
    CRM_Core_Error::debug_log_message( "Omnikassa mode='".$this->_mode."' Params array ".print_r($params,true) );
    CRM_Core_Error::debug_log_message( "Omnikassa config array ".print_r($config,true) );

    list($currencynumber, $fraction_unit) = $this->getCurrency($params['currencyID']);    
 
    $action_url = $this->_paymentProcessor['url_site'];
    // Build our query string;
    $Omniparams = array();
    $Omniparams['orderId'] =  $params['contributionID'];
    $Omniparams['transactionReference'] =  $params['invoiceID'];
    $Omniparams['amount'] = $params['amount']*$fraction_unit;
    $Omniparams['merchantId']= $this->_paymentProcessor['user_name'];
    $Omniparams['keyVersion']=1;
    $Omniparams['customerLanguage']=$this->getLanguage();
    $Omniparams['currencyCode']=$currencynumber;
    $Omniparams['normalReturnUrl']=$returnURL;
    $Omniparams['automaticResponseUrl']=$notificationUrl;
    $Omniparams['expirationDate'] =  date('c', time() + 7200); // verloopt na 2 uur


    CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $OmniParams);

    $req=$this->omnikassa_createRequest($Omniparams);
   
    $OmnikassaParams['Data']=$req['data'];
    $OmnikassaParams['Seal']=$req['seal'];
    $OmnikassaParams['InterfaceVersion']='HP_1.0';

?>
<html>
<body>
<form method="POST" name="omnikassa" action="<?php echo $action_url ; ?>" id="parameters">
<?php
          foreach($OmnikassaParams as $key => $val){
            echo '<input name="'.$key.'" type="hidden" value="'.$val.'" />';
          }
      ?>
      <input type="submit">
</form>
<script type="text/javascript">
document.forms['omnikassa'].submit();
</script>
<?php
   
  //      echo "Redirecting... please wait";
  //      require_once 'CRM/Core/Session.php';
  //      CRM_Core_Session::storeSessionObjects( );
        exit;
  }




  /**
* calculate MAC key
* @param unknown $key
* @param unknown $params
* @param unknown $algorithm
* @return string
*/
  private function encodeMac($params) {
    $string = implode('*', $params) . '**********';
    return hash_hmac($this->getAlgorithm(), $string, $this->getKey());
  }

  /**
* format key - adapted from drupal commerce module
* @param unknown $key
* @return string
*/
  private function getUsableKey($key) {
    $hex_str_key = substr($key, 0, 38);
    $hex_final = "" . substr($key, 38, 2) . "00";

    $cca0 = ord($hex_final);

    if ($cca0 > 70 && $cca0 < 97) {
      $hex_str_key .= chr($cca0 - 23) . substr($hex_final, 1, 1);
    }
    else {
      if (substr($hex_final, 1, 1) == "M") {
        $hex_str_key .= substr($hex_final, 0, 1) . "0";
      }
      else {
        $hex_str_key .= substr($hex_final, 0, 2);
      }
    }
    return pack("H*", $hex_str_key);
  }


  /**
* Get language string -Size: 2 characters
* Possible values: CS CY DE EN ES FR NL SK
* Since this is a Dutch payment processor we will default to Dutch if no
* other match established
* @return string
*/
  function getLanguage() {
    $lang = explode('_', CRM_Core_Config::singleton()->lcMessages);
    $validLangs = array('cs', 'cy', 'de', 'en', 'es', 'fr', 'nl', 'sk');
    if(in_array($lang[0], $validLangs)) {
      return strtoupper($lang[0]);
    }
    return 'nl';
  }

/*
*  Get Currency numeric code and fractional unit per currence
* Since this is a Dutch payment processor we will default to EUR
*/

  function getCurrency($currency_code){
    $curr  = array();
    $curr["EUR"]=array("nr" => 978, "fraction" => 2);
    $curr["USD"]=array("nr" => 840, "fraction" => 2);
    $curr["CHF"]=array("nr" => 756, "fraction" => 2);
    $curr["GBP"]=array("nr" => 826, "fraction" => 2);
    $curr["CAD"]=array("nr" => 124, "fraction" => 2);
    $curr["JPY"]=array("nr" => 392, "fraction" => 0);
    $curr["AUD"]=array("nr" => 036, "fraction" => 2);
    $curr["NOK"]=array("nr" => 578, "fraction" => 2);
    $curr["SEK"]=array("nr" => 752, "fraction" => 2);
    $curr["DKK"]=array("nr" => 208, "fraction" => 2);
 
    $currency = isset($curr[$currency_code])?$curr[$currency_code]:$curr["EUR"];   

    return (array($currency["nr"], $currency["fraction"]==2?100:1));
  }

  /**
* getter for key
* @return string
*/
  function getKey() {
    return $this->getUsableKey($this->_key);
  }


  /**
* getter for algorithm
* @return string
*/
  function getAlgorithm() {
    return $this->_algorithm;
  }

  function handlePaymentNotification(){
/*    $logTableExists = FALSE;
    $checkTable = "SHOW TABLES LIKE 'civicrm_notification_log'";
    $dao = CRM_Core_DAO::executeQuery($checkTable);
    if(!$dao->N) {
      CRM_Core_DAO::executeQuery("CREATE TABLE IF NOT EXISTS `civicrm_notification_log` (
`id` INT(10) NOT NULL AUTO_INCREMENT,
`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`message_type` VARCHAR(255) NULL DEFAULT NULL,
`message_raw` LONGTEXT NULL,
PRIMARY KEY (`id`)
)");
    }

    $dao = CRM_Core_DAO::executeQuery("INSERT INTO civicrm_notification_log (message_raw, message_type) VALUES (%1, 'cmcic')",
      array(1 => array(json_encode($_REQUEST), 'String'))
    );*/
    CRM_Core_Error::debug_log_message( "Omnikassa Notification".print_r($_REQUEST,true) );

    $ipn = new CRM_Core_Payment_OmnikassaIPN(array_merge($_REQUEST, array('exit_mode' => TRUE)));
    $ipn->main();
    //if for any reason we come back here
//    CRM_Core_Error::debug_log_message( "It should not be possible to reach this line" );

 }

    /**
     * Genereert een array met de parameters die nodig zijn voor ieder verzoek
     * naar de Rabobank, inclusief een controlecode.
     * @param array $data Parameters
     * @return array Parameters
     */
   function omnikassa_createRequest($data = array()) {
        $datastring = '';
        foreach ($data as $key => $value)
            $datastring .= $key . '=' . $value . '|';
        $datastring = substr($datastring, 0, -1);

        $seal = hash('sha256', utf8_encode($datastring . $this->_paymentProcessor['password']));

        return array(
            "data" => $datastring,
            "seal" => $seal,
        );
    }
    /**
     * De server stuurt een soortgelijk verzoek terug, dat parsen we hier:
     * @param string $data Data
     * @param string $seal Seal
     * @param bool $callback Is callback? (or redirect?)
     * @return mixed Data-array bij succes, false bij ongeldige seal 
     */

    public function data_validate($data, $seal) {
        $sealcheck = hash('sha256', utf8_encode($data . $this->_paymentProcessor['password']));
        if ($seal != $sealcheck) {
//            $this->log("warning", $logtxt . ", seal check failed.");
              CRM_Core_Error::debug_log_message( "Omnikassa Data Seal Validation Failed");

            return false;
        }
        return true;   

    }

    function getErrorDescription($code) {
        switch ($code) {
            case 00:
                return '';
                break;

            case 17: // betaling geannuleerd door klant
                return "Je hebt de betaling geannuleerd. Probeer het opnieuw.";
                break;

            case 60: // transactie in behandeling
                return "Je transactie is in behandeling en de status is op dit moment niet bekend.";
                break;

            case 94: // dubbele transactie
                return "Deze transactie en betaling is reeds eerder verwerkt.";
                break;

            case 02: // limiet overschreden
            case 05: // autorisatie geweigerd
            case 14: // ongeldig kaartnummer / cvc
            case 34: // vermoeden van fraude
            case 63: // beveiligingsprobleem gedetecteerd
            case 75: // aantal pogingen overschreden
                break;
    
            case 17: // betaling geannuleerd door klant
                return "Je hebt de betaling geannuleerd. Probeer het opnieuw.";
                break;

            case 60: // transactie in behandeling
                return "Je transactie is in behandeling en de status is op dit moment niet bekend.";
                break;

            case 94: // dubbele transactie
                return "Deze transactie en betaling is reeds eerder verwerkt.";
                break;

            case 02: // limiet overschreden
            case 05: // autorisatie geweigerd
            case 14: // ongeldig kaartnummer / cvc
            case 34: // vermoeden van fraude
            case 63: // beveiligingsprobleem gedetecteerd
            case 75: // aantal pogingen overschreden
                return "De betaling die je hebt geprobeerd te verrichten is niet goedgekeurd door de bank. Controleer je gegevens en probeer het opnieuw.";
                break;

            case 03: // ongeldig contract webwinkel
            case 12: // ongeldige transactie
            case 24: // ongeldige status
            case 25: // transactie niet gevonden
            case 30: // ongeldig formaat
            case 40: // niet toegestaan in deze webwinkel
            case 90: // server acquirer onbereikbaar
            case 97: // request timeout
            case 99: // betalingspagina onbereikbaar
            default: // alle andere errors
                return "Er is een serverfout opgetreden bij het verwerken van de transactie, of de betalingsserver is niet beschikbaar. Probeer het later opnieuw.";
                break;
        }
    }

}
