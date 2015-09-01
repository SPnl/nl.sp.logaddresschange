<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Logaddresschange_Upgrader extends CRM_Logaddresschange_Upgrader_Base {


  public function install() {
    $this->createActivityType('log_address_change', 'Adres wijziging');
  }

  protected function createActivityType($name, $label) {
    try {
      $activity_type = civicrm_api3('OptionValue', 'getsingle', array('name' => $name, 'option_group_id' => 2));
    } catch (Exception $e) {
      //activity type does not exist
      $params['name'] = $name;
      $params['label'] = $label;
      $params['filter'] = 1;
      $params['weight'] = 1;
      $params['is_active'] = 1;
      civicrm_api3('ActivityType', 'create', $params);
    }
  }

}
