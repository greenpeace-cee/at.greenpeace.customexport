<?php
/*-------------------------------------------------------+
| SYSTOPIA - Custom Export for Greenpeace                |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: M. Wire (mjw@mjwconsult.co.uk)                 |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

class CRM_Customexport_WelcomepackageEmail extends CRM_Customexport_Base {

  private $campaignExternalIdentifier; // Holds the external identifier for the campaign (used in SQL queries) from setting welcomepackageemail_campaign_externalidentifier

  function __construct($params) {
    if (!$this->getExportSettings('welcomepackageemail_exports')) {
      throw new Exception('Could not load welcomepackagepostExports settings - did you define a default value?');
    };

    $this->campaignExternalIdentifier = CRM_Customexport_Utils::getSettings('welcomepackageemail_campaign_externalidentifier');

    $this->getLocalFilePath();

    // params override setting, if param not specified read the setting
    if (!isset($params['create_activity'])) {
      $params['create_activity'] = CRM_Customexport_Utils::getSettings('welcomepackageemail_create_export_activity');
    }
    if (!isset($params['export_activity_subject'])) {
      $params['export_activity_subject'] = CRM_Customexport_Utils::getSettings('welcomepackageemail_export_activity_subject');
    }

    parent::__construct($params);
  }

  function export() {
    return parent::export();
  }

  public function createMassActivity($activity_params = array()) {
    $activity_params = array(
      'status_id'        => 'Completed',
      'activity_type_id' => 'Action',
      'subject'          => $this->params['export_activity_subject']);

    try {
      if ($this->campaignExternalIdentifier) {
        $campaign = civicrm_api3('Campaign', 'getsingle', array(
          'external_identifier' => $this->campaignExternalIdentifier,
          'return'              => 'title,id'));
        $activity_params['campaign_id'] = $campaign['id'];
      }

      parent::createMassActivity($activity_params);
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Customexport - Problem creating activity: ' . $e->getMessage());
    }
  }

  /**
   * The keys we need in the csv export.  These MUST exist in the sql select (sqlFinalSelect)
   * @return array
   */
  function keys() {
    return array('Contact_Hash', 'Email', 'contact_id');
  }

  /**
   * The final select statement
   * @return array
   */
  function sqlFinalSelect() {
    // Start of sql statements
    return "
# ****************#
#   FINAL SELECT  #
# ****************#*/
SET @CiviCampaignID:= (SELECT id FROM civicrm_campaign
    WHERE external_identifier='{$this->campaignExternalIdentifier}');

SELECT Contact_Hash,Email,contact_id
FROM temp_welcome;
    ";
  }

  /**
   * The actual array of queries
   */
  function sql() {
    // Start of sql statements
    return "
# *******#
#   OUT  #
# *******#

#Create basis table of contacts with first reduction:
#OUT: deceased, deleted, do not email, no empty emailaddress
DROP TABLE IF EXISTS temp_welcome;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome AS
	(
  SELECT DISTINCT
        c.id            AS contact_id
      , c.hash          AS Contact_Hash
      , email.email 		AS Email
      , 0 					AS keep_contact
	FROM civicrm_contact c
		LEFT JOIN civicrm_email email ON email.contact_id=c.id  AND is_primary=1
	WHERE c.is_deceased=0 AND c.is_deleted=0  
		AND c.do_not_email=0 AND c.is_opt_out=0 AND email.email IS NOT NULL
    );

ALTER TABLE temp_welcome ADD PRIMARY KEY (contact_id);

#Table with all contacts, which should not receive any welcome mail
DROP TABLE IF EXISTS temp_welcome_delete;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete AS 
	(
	SELECT DISTINCT contact_id
    FROM (	
			#OUT: inaktiv, VIP, Firma, Schule 
			SELECT DISTINCT et.entity_id AS contact_id
			FROM civicrm_entity_tag et 		
			LEFT JOIN civicrm_tag ct ON et.tag_id=ct.id 
			WHERE et.entity_table = 'civicrm_contact' AND ct.name IN ('inaktiv','VIP') 
			
			UNION ALL
            
			#OUT: Erblasser
			SELECT DISTINCT group_c.contact_id
			FROM civicrm_group_contact group_c 
			LEFT JOIN civicrm_group g ON g.id=group_c.group_id
			WHERE g.title='Erblasser'
			
			UNION ALL
			
            #OUT: Major Donors
			SELECT cb.contact_id
			FROM civicrm_contribution AS cb 
			WHERE receive_date >= NOW() - INTERVAL 1 YEAR
			GROUP BY cb.contact_id
			HAVING SUM(cb.total_amount)>=1000
                        
            #OUT: Hard/Softbounces - not in Civi, yet! when implemented, then this has to be updated
            
		) AS delete_multiple_contacts
    );
    
ALTER TABLE temp_welcome_delete ADD PRIMARY KEY (contact_id);

#Delete all contacts from the welcome-list which were collected in the delete-table    
DELETE
FROM temp_welcome 
WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete)
;

# *******#
#   IN   #
# *******#

#Table with all contacts, that have to be kept and should receive a welcome mail
#IN: Membership Current/ Paused, Sepa RCUR / FRST, join date in last 6 months
DROP TABLE IF EXISTS temp_welcome_keep;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_keep AS 
	(
    SELECT DISTINCT contact_id
	FROM civicrm_membership AS m
	LEFT JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
	LEFT JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	LEFT JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=(SELECT id FROM civicrm_option_group WHERE name ='payment_instrument')
	WHERE ms.label IN ('Current','Paused') AND v.name IN ('RCUR','FRST')  AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY) AND join_date>='2017-07-12'
    );
    
ALTER TABLE temp_welcome_keep ADD PRIMARY KEY (contact_id);

#Mark the contacts, which should definetly be on the welcome list
UPDATE temp_welcome 
SET  keep_contact=1
WHERE contact_id IN (SELECT contact_id FROM temp_welcome_keep);

# *******#
#   OUT  #
# *******#

#Final delete of all contacts, which are not marked to be kept
DELETE
FROM temp_welcome 
WHERE keep_contact=0;

    "; // DO NOT REMOVE (end of SQL statements)
  }

}
