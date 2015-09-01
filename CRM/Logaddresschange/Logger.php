<?php

class CRM_Logaddresschange_Logger {

  private static $singleton;

  private $logActivityType;

  private $preAddress;

  private function __construct() {
    $this->logActivityType = civicrm_api3('OptionValue', 'getsingle', array('name' => 'log_address_change', 'option_group_id' => 2));
  }

  public static function singleton() {
    if (!isset(self::$singleton)) {
      self::$singleton = new CRM_Logaddresschange_Logger();
    }
    return self::$singleton;
  }

  public function pre($op, $objectName, $id, &$params) {
    if ($objectName != 'Address') {
      return;
    }

    $this->preAddress = false;
    $address = new CRM_Core_BAO_Address();
    $address->id = $id;
    if ($address->find(true)) {
      $this->preAddress = $address;
    }
  }

  public function post($op, $objectName, $id, &$objectRef) {
    if ($objectName != 'Address') {
      return;
    }

    $action = 'gewijzigd';
    switch ($op) {
      case 'create':
        $action = 'toegevoegd';
        break;
      case 'delete':
        $action = 'verwijderd';
        break;
    }

    $contact_id = ($this->preAddress ? $this->preAddress->contact_id : $objectRef->contact_id);

    $body = '';
    if ($this->preAddress) {
      $this->preAddress->addDisplay();
      $body .= "Oud adres\r\n" . $this->preAddress->display_text . "\r\n\r\n";
    }

    $address = new CRM_Core_BAO_Address();
    $address->id = $id;
    if ($address->find(TRUE)) {
      $address->addDisplay();
      $body .= "Nieuw adres\r\n" . $address->display_text . "\r\n\r\n";
    }

    $primair = FALSE;
    if (($this->preAddress && $this->preAddress->is_primary) || ($address && $address->is_primary)) {
      $primair = TRUE;
    }

    $oldlocationType = FALSE;
    if ($this->preAddress && $this->preAddress->location_type_id) {
      $oldlocationType = civicrm_api3('LocationType', 'getvalue', array(
        'return' => 'display_name',
        'id' => $this->preAddress->location_type_id
      ));
    }

    $newlocationType = FALSE;
    if ($address && $address->location_type_id) {
      $newlocationType = civicrm_api3('LocationType', 'getvalue', array(
        'return' => 'display_name',
        'id' => $address->location_type_id
      ));
    }

    $subject = '';
    if ($primair) {
      $subject .= 'Primair ';
    }
    if ($oldlocationType != $newlocationType && !empty($oldlocationType) && !empty($newlocationType)) {
      $subject .= $oldlocationType .' gewijzigd naar '.$newlocationType;
    } elseif (!empty($oldlocationType)) {
      $subject .= $oldlocationType .' '.$action;
    } elseif (!empty($newlocationType)) {
      $subject .= $newlocationType .' '.$action;
    }

    $activityParams = array(
      'source_contact_id' => $contact_id,
      'activity_type_id' => $this->logActivityType['value'],
      'subject' => $subject,
      'activity_date_time' => date('YmdHis'),
      'status_id' => CRM_Core_OptionGroup::getValue('activity_status',
        'Completed',
        'name'
      ),
      'details' => nl2br($body),
      'skipRecentView' => TRUE,
    );

    // create activity with target contacts
    $session = CRM_Core_Session::singleton();
    $id = $session->get('userID');
    if ($id) {
      $activityParams['source_contact_id'] = $id;
      $activityParams['target_contact_id'][] = $contact_id;
    }

    if (is_a(CRM_Activity_BAO_Activity::create($activityParams), 'CRM_Core_Error')) {
      CRM_Core_Error::fatal("Failed creating Activity");
      return FALSE;
    }

  }


}