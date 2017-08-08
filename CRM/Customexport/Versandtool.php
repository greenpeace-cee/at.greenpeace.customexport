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

class CRM_Customexport_Versandtool extends CRM_Customexport_Base {

  function __construct() {
    if (!$this->getExportSettings('versandtool_exports')) {
      throw new Exception('Could not load versandtoolExports settings - did you define a default value?');
    };

    $this->getLocalFilePath();

    // params override setting, if param not specified read the setting
    if (!isset($params['create_activity'])) {
      $params['create_activity'] = CRM_Customexport_Utils::getSettings('versandtool_create_export_activity');
    }
    if (!isset($params['export_activity_subject'])) {
      $params['export_activity_subject'] = CRM_Customexport_Utils::getSettings('versandtool_export_activity_subject');
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
      $this->createMassActivity($activity_params);
    } catch (Exception $e) {
      error_log("Problem creating activity: " . $e->getMessage());
    }
  }

  /**
   * The keys we need in the csv export.  These MUST exist in the sql select
   * @return array
   */
  function keys() {
    return array('Contact_Hash', 'Email', 'Salutation', 'Firstname', 'Lastname', 'Birthday', 'Title', 'ZIP', 'City', 'Country', 'Address', 'Contact_ID',
	    'Telephone', 'PersonID_IMB', 'Package_id', 'Segment_id', 'Community_NL', 'Donation_Info', 'Campaign_Topic', 'Petition');
  }

  function sqlFinalSelect() {
    // Start of sql statements
    return "
#FINAL EXPORT
SELECT *
FROM temp_versandtool versand;

    "; // DO NOT REMOVE (end of SQL statements)
  }

  /**
   * The actual array of queries
   */
  function sql() {
    // Start of sql statements
    return "
DROP TABLE IF EXISTS temp_versandtool;
CREATE TABLE IF NOT EXISTS temp_versandtool 
	(
	Contact_Hash 			VARCHAR(32)
	, Email					VARCHAR(254)
	, Salutation 			VARCHAR(255)
	, Firstname 			VARCHAR(64)
	, Lastname				VARCHAR(64)
	, Birthday				DATE
	, Title         		VARCHAR(64)
	, ZIP					VARCHAR(12)
	, City					VARCHAR(64)
	, Country				CHAR(2)
	, Address				VARCHAR(96)
	, Contact_ID 			INT(10) 	PRIMARY KEY
	, Telephone 			VARCHAR(32)
	, PersonID_IMB			VARCHAR(64)
	, Package_id			INT(10)
	, Segment_id 			INT(10)
	, Community_NL			VARCHAR(8)
	, Donation_Info			VARCHAR(8)
	, Campaign_Topic 		VARCHAR(10000)
	, Petition      		VARCHAR(10000)    
	) 
SELECT 	c.hash 				AS Contact_Hash
	, email.email 			AS Email
	, v.label 				AS Salutation      
	, c.first_name 			AS Firstname
	, c.last_name 			AS Lastname
	, c.birth_date			AS Birthday
	, c.formal_title 		AS Title        
	, address.postal_code 	AS ZIP
	, address.city 			AS City
	, ctry.iso_code 		AS Country
	, address.street_address AS Address
	, c.id 					AS Contact_ID
	, phone.phone			AS Telephone 
	, c.external_identifier AS PersonID_IMB
	, NULL					AS Package_id 
	, NULL					AS Segment_id 
	, NULL 					AS Community_NL
	, NULL 					AS Donation_Info
	, NULL 					AS Campaign_Topic
	, NULL 					AS Petition 
FROM civicrm_contact c 			
	LEFT JOIN civicrm_address address 		ON address.contact_id=c.id AND address.is_primary=1
	LEFT JOIN civicrm_email email 			ON email.contact_id=c.id  AND email.is_primary=1
	LEFT JOIN civicrm_phone phone			ON phone.contact_id=c.id  AND phone.is_primary=1
	LEFT JOIN civicrm_value_address_statistics address_stat ON address_stat.entity_id=address.id 
	LEFT JOIN civicrm_country ctry 			ON address.country_id=ctry.id
	LEFT JOIN civicrm_option_value v 		ON v.value=c.prefix_id AND v.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='individual_prefix') #6
WHERE c.do_not_email=0 AND c.is_opt_out=0 and c.contact_type='Individual' AND email.email IS NOT NULL AND c.is_deceased=0 AND c.is_deleted=0 
		and external_identifier not like 'USER-%' and c.id not in (SELECT contact_id FROM civicrm_uf_match)
;

#Add Status for Group 'Community NL'

UPDATE temp_versandtool versand
INNER JOIN civicrm_group_contact gc		ON gc.contact_id=versand.Contact_ID AND gc.group_id = (SELECT id FROM civicrm_group WHERE title ='Community NL')
SET Community_NL=gc.status
;

#Add Status for Group 'Donation Info'

UPDATE temp_versandtool versand
INNER JOIN civicrm_group_contact gc		ON gc.contact_id=versand.Contact_ID AND gc.group_id = (SELECT id FROM civicrm_group WHERE title ='Donation Info')
SET Donation_Info=gc.status
;

#Add Information for Petitions and Campaigns - Collect first all petitions and campaigns belonging to one contact
DROP TABLE IF EXISTS temp_versandtool_petition;

CREATE TABLE IF NOT EXISTS temp_versandtool_petition 
	(Contact_ID 			INT(10) 	PRIMARY KEY
	, Campaign_Topic 		VARCHAR(10000)
	, Petition      		VARCHAR(10000) 
    )
SELECT versand.Contact_ID
	, GROUP_CONCAT(DISTINCT s.title ORDER BY a.id SEPARATOR ', ') AS Petition
	, GROUP_CONCAT(DISTINCT c.title	ORDER BY a.id SEPARATOR ', ') AS Campaign_Topic
FROM temp_versandtool versand
	INNER JOIN civicrm_activity_contact ac 	ON versand.Contact_ID=ac.contact_id
	INNER JOIN civicrm_activity a 			ON ac.activity_id=a.id 
	INNER JOIN civicrm_option_value v 		ON v.value=a.activity_type_id AND option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='activity_type') and v.name='Petition'
	INNER JOIN civicrm_survey s 			ON s.id=a.source_record_id AND s.activity_type_id=v.value
	INNER JOIN civicrm_campaign c 			ON c.id=s.campaign_id
GROUP BY versand.Contact_ID
;

#Add Information for Petitions and Campaigns

UPDATE temp_versandtool versand
INNER JOIN temp_versandtool_petition p on versand.Contact_ID=p.Contact_ID
SET versand.Campaign_Topic=p.Campaign_Topic
	, versand.Petition=p.Petition
;

    "; // DO NOT REMOVE (end of SQL statements)
  }
}
