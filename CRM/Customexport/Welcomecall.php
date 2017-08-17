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

class CRM_Customexport_Welcomecall extends CRM_Customexport_Base {

  function __construct($params) {
    parent::__construct($params);
  }

  /**
   * Export all contacts meeting criteria
   */
  public function export() {
    $this->doQuery();
    $this->createMassActivity();

    $return['values']['count'] = count($this->exportLines);
    return $return;
  }

  public function createMassActivity($activity_params = array()) {
    try {
      // in this case, we need one activity per contact
      foreach ($this->contact_ids as $contact_id) {
        civicrm_api3('Activity', 'create', array(
          'status_id'          => 'Scheduled',
          'activity_type_id'   => 'Outgoing Call',
          'subject'            => 'Service Call',
          'activity_date_time' => date('YmdHis'),
          'campaign_id'        => 43, // "Welcome Calls"
          'assignee_id'        => array(94,77,105),
          'target_id'          => $contact_id,
          'source_contact_id'  => 16));
      }
    } catch (Exception $e) {
      CRM_Core_Error::debug_log_message('Customexport - Problem creating activity: ' . $e->getMessage());
    }
  }

  // pretend we've been uploaded
  protected function upload() {
    return TRUE;
  }

  /**
   * The keys we need in the csv export.  These MUST exist in the sql select (sqlFinalSelect)
   * "contact_id" field must exist for export activities to be created
   * @return array
   */
  function keys() {
    return array('contact_id');
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
      # ****************#

      SELECT DISTINCT contact_id
      FROM temp_welcome;";
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
  #OUT: deceased, deleted, do not phone, no empty phone number, membership in last month
  DROP TABLE IF EXISTS temp_welcome;
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome AS
    (
    SELECT DISTINCT c.id AS contact_id
      , m.id AS membership_id
      , 0 AS keep_contact
    FROM civicrm_contact c
      INNER JOIN civicrm_phone    phone ON phone.contact_id=c.id  AND phone.phone IS NOT NULL
      INNER JOIN civicrm_membership   m     ON m.contact_id=c.id    AND join_date >= (NOW() - INTERVAL 1 MONTH)
    WHERE c.is_deceased=0
      AND c.is_deleted=0
      AND c.do_not_phone=0
            AND DAY(NOW()) NOT IN (2,9,16,24)

    );

  ALTER TABLE temp_welcome ADD INDEX (contact_id, membership_id);

  #Table with all contacts, which should not receive any welcome mail
  DROP TABLE IF EXISTS temp_welcome_delete_contacts;
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete_contacts AS
    (
      #OUT: inaktiv, VIP, Firma, Schule
      SELECT DISTINCT et.entity_id AS contact_id
      FROM temp_welcome AS w
      INNER JOIN civicrm_entity_tag   et ON w.contact_id=et.entity_id
      LEFT JOIN civicrm_tag       ct ON et.tag_id=ct.id
      WHERE et.entity_table = 'civicrm_contact' AND ct.name IN ('inaktiv','VIP')
    )
   ;

  #OUT: Major Donors
  INSERT INTO temp_welcome_delete_contacts
  SELECT cb.contact_id
  FROM temp_welcome w
  INNER JOIN civicrm_contribution   cb ON cb.contact_id=w.contact_id
  INNER JOIN civicrm_option_value   v  ON cb.contribution_status_id=v.value AND option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='contribution_status')
                                           AND v.label='Completed'
  WHERE receive_date >= NOW() - INTERVAL 1 YEAR
  GROUP BY cb.contact_id
  HAVING SUM(cb.total_amount)>=1000

  ;

  #OUT: Hatte schon einmal einen Welcome Call
  INSERT INTO temp_welcome_delete_contacts
  SELECT DISTINCT ac.contact_id
  FROM temp_welcome w
  INNER JOIN civicrm_activity_contact ac      ON w.contact_id=ac.contact_id
  INNER JOIN civicrm_activity     a       ON a.id=ac.activity_id AND a.campaign_id IN (SELECT id FROM civicrm_campaign WHERE external_identifier='AKTION-7571' OR title = 'Welcome Calls')
  INNER JOIN civicrm_option_value   a_status  ON a_status.value=a.status_id    AND a_status.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='activity_status')
                                           AND a_status.label IN ('Completed','Scheduled')
  LEFT JOIN civicrm_value_activity_tmresponses tm ON tm.entity_id=a.id
  LEFT JOIN civicrm_option_value    v_tm    ON v_tm.value=tm.response     AND v_tm.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='response')
    WHERE record_type_id=3
    AND (
        a_status.label ='Scheduled'
        OR
                (a_status.label ='Completed' AND v_tm.label NOT IN
                          ('90 kein Anschluss'
                          ,'91 nicht erreicht'
                          ,'92 Anrufsperre kein Kontakt'
                          ,'93 nicht angegriffen')

                      AND a.subject NOT IN
                          ('90 kein Anschluss'
                          ,'91 nicht erreicht'
                          ,'92 Anrufsperre kein Kontakt'
                          ,'93 nicht angegriffen')
        )


            )
  ;

    #OUT: hatte eine Activity in TM Root Campaign in letzten 3 Monaten
  INSERT INTO temp_welcome_delete_contacts
  SELECT DISTINCT ac.contact_id
  FROM temp_welcome           w
  INNER JOIN civicrm_activity_contact ac      ON ac.contact_id=w.contact_id   AND record_type_id=3
  INNER JOIN civicrm_activity     a       ON a.id=ac.activity_id      AND activity_date_time>=NOW()- INTERVAL 3 MONTH
  INNER JOIN civicrm_option_value   a_status  ON a_status.value=a.status_id   AND a_status.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='activity_status')
                                        AND a_status.label='Completed'
  INNER JOIN civicrm_campaign     ca      ON ca.id=a.campaign_id
  LEFT JOIN civicrm_campaign      parent1   ON ca.parent_id=parent1.id
  LEFT JOIN civicrm_campaign      parent2   ON parent1.parent_id=parent2.id
  LEFT JOIN civicrm_campaign      parent3   ON parent2.parent_id=parent3.id
  LEFT JOIN civicrm_value_activity_tmresponses tm ON tm.entity_id=a.id
  LEFT JOIN civicrm_option_value    v_tm    ON v_tm.value=tm.response     AND v_tm.option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='response')

  WHERE (ca.name='TM' OR parent1.name='TM' OR parent2.name='TM' OR parent3.name='TM' )
    AND (
        (v_tm.label NOT IN ('90 kein Anschluss'
                  ,'91 nicht erreicht'
                  ,'92 Anrufsperre kein Kontakt'
                  ,'93 nicht angegriffen'))
      OR
        ( a.subject NOT IN  ('90 kein Anschluss'
                  ,'91 nicht erreicht'
                  ,'92 Anrufsperre kein Kontakt'
                  ,'93 nicht angegriffen'))
      )

  ;

  #Delete all contacts from the welcome-list which were collected in the delete-table
  DELETE
  FROM temp_welcome
  WHERE keep_contact=0 AND contact_id IN (SELECT contact_id FROM temp_welcome_delete_contacts)
  ;

  #Table with all contacts, which should not receive any welcome mail
  DROP TABLE IF EXISTS temp_welcome_delete_memberships;
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_delete_memberships AS
    (
      #OUT: Memberships mit Cancelled/Refunded contributions
      SELECT DISTINCT mc.membership_id
      FROM   temp_welcome w
      INNER JOIN civicrm_membership_payment mc ON w.membership_id=mc.membership_id
      INNER JOIN  civicrm_contribution AS contr ON contr.id=mc.contribution_id
      INNER JOIN civicrm_option_value v  ON contr.contribution_status_id=v.value AND option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='contribution_status') AND v.label IN ('Cancelled','Refunded')

    )
   ;

  #OUT: keine Memberships mit erfolgreichen Spenden
  INSERT INTO temp_welcome_delete_memberships
  SELECT DISTINCT mc.membership_id
  FROM   temp_welcome w
  INNER JOIN civicrm_membership_payment mc  ON w.membership_id=mc.membership_id
  INNER JOIN  civicrm_contribution AS contr ON contr.id=mc.contribution_id
  INNER JOIN civicrm_option_value v  ON contr.contribution_status_id=v.value AND option_group_id= (SELECT id FROM civicrm_option_group WHERE name ='contribution_status') AND v.label='Completed'
  ;
  #Delete all memberships from the welcome-list which were collected in the delete-table
  DELETE
  FROM temp_welcome
  WHERE keep_contact=0 AND membership_id IN (SELECT membership_id FROM temp_welcome_delete_memberships)
  ;

  # *******#
  #   IN   #
  # *******#

  #Table with all contacts, that have to be kept and should receive a welcome mail
  #IN: Membership Current/ Paused, Sepa RCUR / FRST, join date in last 6 months
  DROP TABLE IF EXISTS temp_welcome_keep;
  CREATE TEMPORARY TABLE IF NOT EXISTS temp_welcome_keep AS
    (
      #IN: Sepa Bankeinzug RCUR/FRST, Membserhisp status Current/Paused, Membershipts mit Campaign in DD-root-campaign
      SELECT DISTINCT m.id AS membership_id
      FROM temp_welcome AS w
      INNER JOIN civicrm_membership AS m  ON w.membership_id=m.id
      INNER JOIN civicrm_membership_status AS ms ON m.status_id=ms.id
      INNER JOIN civicrm_value_membership_payment AS mp ON mp.entity_id=m.id
      INNER JOIN civicrm_option_value v ON v.value=mp.payment_instrument AND v.option_group_id=(SELECT id FROM civicrm_option_group WHERE name ='payment_instrument')
      INNER JOIN civicrm_contribution_recur AS crecur ON crecur.id=mp.membership_recurring_contribution AND next_sched_contribution_date>NOW()
      LEFT JOIN civicrm_campaign AS c ON c.id=m.campaign_id
      LEFT JOIN civicrm_campaign AS parent1 ON c.parent_id=parent1.id
      LEFT JOIN civicrm_campaign AS parent2 ON parent1.parent_id=parent2.id
      LEFT JOIN civicrm_campaign AS parent3 ON parent2.parent_id=parent3.id

      WHERE ms.label IN ('Current','Paused') AND v.name IN ('RCUR','FRST')
        AND (c.name='DD' OR parent1.name='DD' OR parent2.name='DD' OR parent3.name='DD' )

    );

  ALTER TABLE temp_welcome_keep ADD PRIMARY KEY (membership_id);

  #Mark the contacts, which should definetly be on the welcome list
  UPDATE temp_welcome
  SET  keep_contact=1
  WHERE membership_id IN (SELECT membership_id FROM temp_welcome_keep);

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
