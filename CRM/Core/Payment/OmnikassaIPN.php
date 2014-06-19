<?php

class CRM_Core_Payment_OmnikassaIPN extends CRM_Core_Payment_BaseIPN{

  static $_paymentProcessor = NULL;

  /**
* Input parameters from payment processor. Store these so that
* the code does not need to keep retrieving from the http request
* @var array
*/
  protected $_inputParameters = array();

  /**
* store for the variables from the invoice string
* @var array
*/
  protected $_omnikassaData = array();



  protected $_exitMode = FALSE;
  /**
* Are we dealing with an event an 'anything else' (contribute)
* @var string component
*/
  protected $_component = 'contribute';
  /**
* constructor function
*/
  function __construct($inputData) {
    $this->setInputParameters($inputData);
    $this->_exitMode = !empty($inputData['exit_mode']);
    parent::__construct();
  }

  /**
* output required response for CMCIC process
* @param boolean $mac_ok
* @return string
*/
  function omnikassa_receipt($mac_ok) {
//    return "version=2\ncdr=" . ($mac_ok ? '0' : '1') . "\n";
  }

  /**
* output result and exit - for when url is being hit by cmcic
* @param unknown $mac_ok
*/
  function omnikassa_receipt_exit($mac_ok) {
    echo $this->omnikassa_receipt($mac_ok);
    if($this->_exitMode) {
      exit;
    }
  }

  /**
* check response from cmcic using private key
* @param string $key
* @param array $fields
* @param string $algorithm
* @return boolean
*/
  function omnikassa_validate_response() {
    $fields = $this->_inputParameters;
    if (!isset($fields['Data']) || empty($fields['Seal'])) {
      return FALSE;
    }
    $p = $this->_paymentProcessor;
    $validate = $p->data_validate($fields['Data'], $fields['Seal']);
  
    if (!$validate) return false;

    return true;
  }


  /**
* @param string $name of variable to return
* @param string $type data type
* - String
* - Integer
* @param string $location - deprecated
* @param boolean $abort abort if empty
* @return Ambigous <mixed, NULL, value, unknown, array, number>
*/
  function retrieve($name, $type, $abort = TRUE) {
    $value = CRM_Utils_Type::validate(
      CRM_Utils_Array::value($name, $this->_inputParameters),
      $type,
      FALSE
    );
    if ($abort && $value === NULL) {
      throw new CRM_Core_Exception("Could not find an entry for $name");
    }
    return $value;
  }
  function retrievedata($name, $type, $abort = TRUE) {
     if (count($this->_omnikassaData) == 0){
        $return = array();
        $data = $this->retrieve("Data", "String");
        $data = explode("|", $data);
        foreach ($data as $datapart) {
            list($key, $value) = explode("=", $datapart);
            if($key=='transactionReference')
                $value = substr($value, 0, strrpos($value,'t'));
            $return[$key] = $value;
        }

//        $this->log("info", $logtxt . ", " . ($return['responseCode']=='00' ? "SUCCESS" : "FAIL") . ", order " . $return['transactionReference'] . ", payment type " .$
        $this->_omnikassaData = $return;
     }
     $value = CRM_Utils_Type::validate(
       CRM_Utils_Array::value($name, $this->_omnikassaData),
       $type,
       FALSE
     );
     if ($abort && $value === NULL) {
       throw new CRM_Core_Exception("Could not find an entry for $name");
     }
     return $value;
  }






  /**
* This is the main function to call. It should be sufficient to instantiate the class
* (with the input parameters) & call this & all will be done
*
* @todo the references to POST throughout this class need to be removed
* @return void|boolean|Ambigous <void, boolean>
*/
  function main() {

    CRM_Core_Error::debug_log_message( "Omnikassa IPN main() function".print_r($_REQUEST,true) );
    $paymentProcessor = civicrm_api3('payment_processor', 'getsingle', array('id' => $this->retrieve('processor_id', 'Integer', TRUE)));
    //we say contribute here as a dummy param as we are using the api to complete & we don't need to know
    $this->_paymentProcessor = new CRM_Core_Payment_Omnikassa('contribute', $paymentProcessor);


    if(!$this->omnikassa_validate_response()) {
      $this->omnikassa_receipt_exit(FALSE);
      return;
    }

    CRM_Core_Error::debug_log_message( "Omnikassa IPN main() function: SEAL valid" );

    $resultCode = $this->retrieveData("responseCode", "String");
    $trxn_id = $this->retrieveData("transactionReference", "String");
    $contributionID = $this->retrieveData("orderId", "Integer");
    $success=false;

    if ($resultCode == "00")
    {
      CRM_Core_Error::debug_log_message( "Omnikassa IPN main() function: RESULT OK" );

      civicrm_api3('contribution', 'completetransaction', array(
        'id' => $contributionID,
        'trxn_id' => $trxn_id,
      ));
  //    $this->omnikassa_receipt_exit(TRUE);
      $success=true;

    }
    else
    {
      CRM_Core_Error::debug_log_message( "Omnikassa IPN main() function: RESULT FAILED:".$resultCode) ;

      $this->processFailedTransaction($contributionID);
 //     $this->omnikassa_receipt_exit(TRUE);

    }
   
    if (isset($_REQUEST["md"]) && isset($_REQUEST["qfKey"])){
      $module = $_REQUEST["md"];
      $qfKey = $_REQUEST["qfKey"];
      $invoiceId = $_REQUEST["inId"];
      switch ($module) {
        case 'contribute':
          if ($success){
            $url = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_ThankYou_display=1&qfKey={$qfKey}", FALSE, NULL, FALSE
            );
          }
          else {
            $error=$this->_paymentProcessor->getErrorDescription($resultCode);
            CRM_Core_Session::setStatus($error, ts('Error:'), 'error');
            $url = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_Confirm_display=true&qfKey={$qfKey}", FALSE, NULL, FALSE
            );

          }

          break;
        case 'event':
          if ($success){
 
            $url = CRM_Utils_System::url('civicrm/event/register', "_qf_ThankYou_display=1&qfKey={$qfKey}", FALSE, NULL, FALSE
            );
          }
          else
          {
            $error=$this->_paymentProcessor->getErrorDescription($resultCode);

            CRM_Core_Session::setStatus($error, ts('Error:'), 'error');
            $url = CRM_Utils_System::url('civicrm/event/register', "_qf_Confirm_display=true&qfKey={$qfKey}", FALSE, NULL, FALSE
          );


          } 
          break;

     }
        CRM_Utils_System::redirect($url);     


    }

    return TRUE;
  }

  /**
* Process failed transaction - would be nice to do this through api too but for now lets put in
* here - this is a copy & paste of the completetransaction api
* @param unknown $contributionID
*/
  function processFailedTransaction($contributionID) {

    $input = $ids = array();
    $contribution = new CRM_Contribute_BAO_Contribution();
    $contribution->id = $contributionID;
    $contribution->find(TRUE);
    if(!$contribution->id == $contributionID){
      throw new Exception('A valid contribution ID is required', 'invalid_data');
    }
    try {

      if(!$contribution->loadRelatedObjects($input, $ids, FALSE, TRUE)){
        throw new Exception('failed to load related objects');
        CRM_Core_Error::debug_log_message( "Omnikassa Failed to load related Objects") ;

      }
      $objects = $contribution->_relatedObjects;
      $objects['contribution'] = &$contribution;
      $input['component'] = $contribution->_component;
      $input['is_test'] = $contribution->is_test;
      $input['amount'] = $contribution->total_amount;
      // @todo required for base ipn but problematic as api layer handles this

      $transaction = new CRM_Core_Transaction();

      $ipn = new CRM_Core_Payment_BaseIPN();

      $ipn->failed($objects, $transaction, $input);

    }
    catch (Exception $e) {
        CRM_Core_Error::debug_log_message( "Omnikassa Exception:".$e.getMessage()) ;
    }
  }
}
