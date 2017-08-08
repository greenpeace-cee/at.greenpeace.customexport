<?php
/*--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
+--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
+--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +-------------------------------------------------------------------*/

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Customexport_Form_Settings extends CRM_Core_Form {
  private $_settingFilter = array('group' => 'Customexport');
  private $_submittedValues = array();

  private static $settingsPrefix = '';
  private static $settingsTitle = 'Custom Export';

  public static function getSettingsPrefix() {
    return self::$settingsPrefix;
  }

  function buildQuickForm() {
    parent::buildQuickForm();

    CRM_Utils_System::setTitle(ts(self::$settingsTitle . ' - Settings'));

    $settings = $this->getFormSettings();

    foreach ($settings['values'] as $name => $setting) {
      if (isset($setting['type'])) {
        Switch ($setting['type']) {
          case 'String':
            $this->addElement('text', self::getSettingName($name), ts($setting['description']), $setting['html_attributes'], array());
            break;
          case 'Boolean':
            $this->addElement('checkbox', self::getSettingName($name), ts($setting['description']), '', '');
            break;
          case 'Json':
            if ($setting['name'] == 'webshop_exports') { // webshop_exports is special case (supports multiple sftp uploads)
              $orderTypes = civicrm_api3('OptionValue', 'get', array(
                'return' => "label,value",
                'option_group_id' => "order_type",
              ));
              $this->buildUploadSettings($setting['name'] . '_default', $setting['description'] . ': default');
              foreach ($orderTypes['values'] as $orderType) {
                $this->buildUploadSettings($setting['name'] . '_' . $orderType['value'], $setting['description'] . ': ' . $orderType['label']);
              }
            }
            else {
              $this->buildUploadSettings($setting['name'] . '_default', $setting['description']);
            }
        }
      }
    }

    $this->addButtons(array(
      array (
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
      array (
        'type' => 'cancel',
        'name' => ts('Cancel'),
      )
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
  }

  function buildUploadSettings($key, $description) {
    // {"default":{"file":"welcomepackagepost","remote":"sftp:\/\/test:test@example.org\/default\/"}}
    $element[] = $this->addElement('text', $key . '_file', $description . ' Filename prefix: ', array('size' => 40), array());
    $element[] = $this->addElement('text', $key . '_remote', $description . ' Remote URI: ', array('size' => 80), array());
    return $element;
  }

  function postProcess() {
    $this->_submittedValues = $this->exportValues();
    $this->saveSettings();
    parent::postProcess();
    CRM_Core_Session::singleton()->setStatus('Configuration Updated', self::$settingsTitle, 'success');
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons". These
    // items don't have labels. We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Get the settings we are going to allow to be set on this form.
   *
   * @return array
   */
  function getFormSettings() {
    $settings = civicrm_api3('setting', 'getfields', array('filters' => $this->_settingFilter));
    return $settings;
  }

  /**
   * Get the settings we are going to allow to be set on this form.
   *
   * @return array
   */
  function saveSettings() {
    $settings = $this->getFormSettings();

    $appendedValues=array();
    foreach ($this->_submittedValues as $key => $value) {
      $appendedValues[self::getSettingsPrefix().$key] = $value;
    }
    $values = array_intersect_key($appendedValues, $settings['values']);
    foreach ($settings['values'] as $settingKey => $settingValues) {
      switch ($settingValues['type']) {
        case 'Json':
          $jsonData = array();
          foreach ($this->_submittedValues as $key => $value) {
            if (substr($key, 0, strlen($settingKey)) === $settingKey) {
              // This is part of $settingKey setting
              // eg. webshop_exports_default_file needs to be parsed as $jsonData['default']['file']
              $subKeys = explode('_', substr($key, strlen($settingKey)));
              if (!empty($value)) {
                $jsonData[$subKeys[1]][$subKeys[2]] = $value;
              }
            }
          }
          $values[$settingKey] = json_encode($jsonData);
          break;
      }
    }

    civicrm_api3('setting', 'create', $values);
  }

  /**
   * Set defaults for form.
   *
   * @see CRM_Core_Form::setDefaultValues()
   */
  function setDefaultValues() {
    $settings = $this->getFormSettings();
    $values = $settings['values'];
    $existing = civicrm_api3('setting', 'get', array('return' => array_keys($values)));
    $defaults = array();
    $domainID = CRM_Core_Config::domainID();
    foreach ($existing['values'][$domainID] as $name => $value) {
      if ($values[$name]['type'] == 'Json') {
        $json = json_decode($value, TRUE);
        foreach ($json as $sftpKey => $sftpGroup) {
          foreach ($sftpGroup as $key => $confValue) {
            $defaults[self::getSettingName($name) . '_' . $sftpKey . '_' . $key] = $confValue;
          }
        }
      }
      else {
        $defaults[self::getSettingName($name)] = $value;
      }
    }
    return $defaults;
  }

  /**
   * Get name of setting
   * @param: setting name
   * @prefix: Boolean
   */
  public static function getSettingName($name, $prefix = false) {
    $ret = str_replace(self::getSettingsPrefix(),'',$name);
    if ($prefix) {
      $ret = self::getSettingsPrefix().$ret;
    }
    return $ret;
  }
}
