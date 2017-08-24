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

class CRM_Customexport_WelcomepackagePost extends CRM_Customexport_Base {

  private $campaignExternalIdentifier; // Holds the external identifier for the campaign (used in SQL queries) from setting welcomepackagepost_campaign_externalidentifier

  function __construct($params) {
     if (!$this->getExportSettings('welcomepackagepost_exports')) {
      throw new Exception('Could not load welcomepackagepostExports settings - did you define a default value?');
    };
    $this->campaignExternalIdentifier = CRM_Customexport_Utils::getSettings('welcomepackagepost_campaign_externalidentifier');

    $this->getLocalFilePath();

    // params override setting, if param not specified read the setting
    if (!isset($params['create_activity'])) {
      $params['create_activity'] = CRM_Customexport_Utils::getSettings('welcomepackagepost_create_export_activity');
    }
    if (!isset($params['export_activity_subject'])) {
      $params['export_activity_subject'] = CRM_Customexport_Utils::getSettings('welcomepackagepost_export_activity_subject');
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
   * The keys we need in the csv export.  These MUST exist in the sql select
   * "contact_id" field must exist for export activities to be created
   * @return array
   */
  function keys() {
    return array("contact_id", "titel", "anrede", "vorname", "nachname", "co", "strasse", "plz", "ort", "postfach", "land", "kundennummer", "vertragstyp");
  }

  function sqlFinalSelect() {
    return "
# ****************#
#   FINAL SELECT  #
# ****************#

#Campaign Id for Kundennummer
SET @CiviCampaignID:= (SELECT id FROM civicrm_campaign
    WHERE external_identifier='AKTION-7767');
    
#Output for CSV File
#\"id\", \"titel\", \"anrede\", \"vorname\", \"nachname\", \"co\", \"strasse\", \"plz\", \"ort\", \"postfach\", \"land\", \"kundennummer\" 
SELECT 	w.contact_id 			AS contact_id
		,formal_title 			AS titel     
		, v.label 				AS anrede     
        , c.first_name 			AS vorname
        , (case contact_type
				when 'Individual' then c.last_name
				when 'Organization' then c.organization_name
				when 'Household' then c.household_name
			end)  				AS nachname
		, NULL 					AS co
        , address.street_address AS strasse
        , address.postal_code 	AS plz
		, address.city 			AS ort
        , NULL 					AS postfach
        , ctry.iso_code 		AS land
        , CONCAT(LPAD(@CiviCampaignID,5,'0'),'C',LPAD(w.contact_id, 9, '0')) 
								AS kundennummer
		, membership_type		AS vertragstyp

FROM temp_welcome w
	LEFT JOIN civicrm_contact c 			ON c.id=w.contact_id
	LEFT JOIN civicrm_address address 		ON address.contact_id=c.id AND address.is_primary=1
	LEFT JOIN civicrm_value_address_statistics address_stat ON address_stat.entity_id=address.id 
	LEFT JOIN civicrm_country ctry 			ON address.country_id=ctry.id
    LEFT JOIN civicrm_option_value v 		ON v.value=c.prefix_id AND v.option_group_id=(SELECT id FROM civicrm_option_group WHERE name ='individual_prefix')
    LEFT JOIN temp_membershiptypes mt		ON mt.contact_id=w.contact_id
    "; // DO NOT REMOVE (end of SQL statements)
  }

  /**
   * The actual array of queries
   */
  function sql() {
    // Start of sql statements
    return "
#Create basis table of contacts 
#IN:  new contracts:
DROP TABLE IF EXISTS temp_welcome;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome AS 
	(
	SELECT DISTINCT contact_id
        , 0 AS keep_contact
 	FROM civicrm_membership AS m
	LEFT JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
	LEFT JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	LEFT JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=(SELECT id FROM civicrm_option_group WHERE name ='payment_instrument')
	WHERE 	
			#Memberships Current oder Paused
			ms.label IN ('Current','Paused') 
            
            #SEPA RCUR oder FRST
        AND v.name IN ('RCUR','FRST')  
        
			#join_date innerhalbt der letzten 6 Monate und vorgestern
        AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY)        
        #AND contact_id=19837
        
      );      

ALTER TABLE temp_welcome ADD primary key (contact_id);   

#OUT: deceased, deleted, do not mail, not AT, empty address, Retourenzähler >=2
	DROP TABLE IF EXISTS temp_welcome_delete_contacts;
	CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete_contacts AS 
		(
 			SELECT DISTINCT w.contact_id
			FROM temp_welcome  					w
				INNER JOIN civicrm_contact			c on w.contact_id=c.id 
				LEFT JOIN civicrm_address address 		ON address.contact_id=c.id AND is_primary=1 #NUR PRIMARY
				LEFT JOIN civicrm_value_address_statistics address_stat ON address_stat.entity_id=address.id 
				LEFT JOIN civicrm_country ctry 			ON address.country_id=ctry.id

			WHERE c.is_deleted=1
					OR c.is_deceased=1 
					OR c.do_not_mail=1 
                    OR ctry.iso_code<>'AT' 
					OR address.street_address IS NULL  
                    OR address.postal_code IS NULL 
                    OR address.city IS NULL 
					OR address_stat.rts_counter >=2  	
                    OR c.contact_type='Organization'
                    OR (c.contact_type='Individual' AND first_name IS NULL)
                    Or (c.contact_type='Individual' AND last_name is null)
					Or (c.contact_type='Household' AND household_name is null)
        )
;	
    ALTER TABLE temp_welcome_delete_contacts ADD primary key (contact_id);	
    
	DELETE
	FROM temp_welcome 
	WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
;
   	DELETE FROM temp_welcome_delete_contacts; 


#OUT: inaktiv, VIP, Firma, Schule 
	INSERT INTO temp_welcome_delete_contacts
	SELECT DISTINCT  w.contact_id
	FROM temp_welcome  					w
		INNER JOIN civicrm_entity_tag et on w.contact_id=et.entity_id and et.entity_table ='civicrm_contact' 		
		INNER JOIN civicrm_tag ct ON et.tag_id=ct.id AND ct.name IN ('inaktiv','VIP','Firma','Schule') 
;
	DELETE
	FROM temp_welcome 
	WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
;
   	DELETE FROM temp_welcome_delete_contacts
; 
            
#OUT: erblasser
	INSERT INTO temp_welcome_delete_contacts
	SELECT DISTINCT w.contact_id
	FROM temp_welcome  	w
		INNER JOIN  civicrm_group_contact group_c ON group_c.contact_id=w.contact_id
		INNER JOIN civicrm_group g ON g.id=group_c.group_id AND g.title='Erblasser'
;
	DELETE
	FROM temp_welcome 
	WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
;
   	DELETE FROM temp_welcome_delete_contacts
;    

#OUT: MajorDonor
	INSERT INTO temp_welcome_delete_contacts
	SELECT cb.contact_id
	FROM temp_welcome w 
	INNER JOIN civicrm_contribution  	cb ON cb.contact_id=w.contact_id
	INNER JOIN civicrm_option_value 	v  ON cb.contribution_status_id=v.value AND option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='contribution_status') 
																				   AND v.label='Completed'
	WHERE receive_date >= NOW() - INTERVAL 1 YEAR
	GROUP BY cb.contact_id
	HAVING SUM(cb.total_amount)>=1000
;
	DELETE
	FROM temp_welcome 
	WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
;
   	DELETE FROM temp_welcome_delete_contacts
;

#OUT: Mailhistory in last 6 Months
	INSERT INTO temp_welcome_delete_contacts
	SELECT distinct ac.contact_id 
	FROM temp_welcome w 
	INNER JOIN civicrm_activity_contact  ac 		ON ac.contact_id=w.contact_id		AND record_type_id=3 
	INNER JOIN civicrm_activity  		activity 	ON ac.activity_id=activity.id		AND activity_date_time >= NOW()-INTERVAL 6 MONTH 
	INNER JOIN civicrm_campaign 		campaign	ON campaign.id=activity.campaign_id AND campaign.external_identifier='AKTION-7767' 
	INNER JOIN civicrm_option_value 	a_status 	ON a_status.value=activity.status_id 	 	AND a_status.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='activity_status') 
																						AND a_status.label='Completed'
	 
;
	DELETE
	FROM temp_welcome 
	WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
;
   	DELETE FROM temp_welcome_delete_contacts
;


#Add Information for membership types - Collect all membership types belonging to one contact
DROP TABLE IF EXISTS temp_membershiptypes;
CREATE TEMPORARY TABLE IF NOT EXISTS temp_membershiptypes 
	(contact_id 			INT(10) 	PRIMARY KEY
	, membership_type 		VARCHAR(10000)
    )
	SELECT w.contact_id 
		, GROUP_CONCAT(DISTINCT mt.name ORDER BY mt.name SEPARATOR ', ') AS membership_type
	FROM temp_welcome w
	INNER JOIN civicrm_membership m ON w.contact_id=m.contact_id AND join_date >= (NOW() - INTERVAL 6 MONTH) AND join_date < (NOW() - INTERVAL 2 DAY)
	INNER join civicrm_membership_type as mt on mt.id=m.membership_type_id
	INNER JOIN civicrm_membership_status AS ms ON m.status_id=ms.id aND ms.label IN ('Current','Paused')
	INNER JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
	INNER JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=(SELECT id FROM civicrm_option_group WHERE name ='payment_instrument')
																		AND v.name IN ('RCUR','FRST')
	group by contact_id
;
    "; // DO NOT REMOVE (end of SQL statements)
  }
}
