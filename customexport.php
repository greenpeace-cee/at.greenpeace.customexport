<?php

require_once 'customexport.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function customexport_civicrm_config(&$config) {
  _customexport_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function customexport_civicrm_xmlMenu(&$files) {
  _customexport_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function customexport_civicrm_install() {
  // Create a "Webshop Order" activity type
  // See if we already have this type
  $activityName = customexport_activityName();
  $activity = civicrm_api3('OptionValue', 'get', array(
    'option_group_id' => "activity_type",
    'name' => $activityName,
  ));
  if (empty($activity['count'])) {
    $activityParams = array(
      'option_group_id' => "activity_type",
      'name' => $activityName,
      'description' => $activityName
    );
    $activityType = civicrm_api3('OptionValue', 'Create', $activityParams);
  }

  _customexport_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function customexport_civicrm_postInstall() {
  _customexport_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function customexport_civicrm_uninstall() {
  _customexport_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function customexport_civicrm_enable() {
  _customexport_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function customexport_civicrm_disable() {
  _customexport_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function customexport_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _customexport_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function customexport_civicrm_managed(&$entities) {
  _customexport_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function customexport_civicrm_caseTypes(&$caseTypes) {
  _customexport_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function customexport_civicrm_angularModules(&$angularModules) {
  _customexport_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function customexport_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _customexport_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function customexport_activityName() {
  return 'Webshop Order';
}
