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

return array(
//webshop_exports
  'webshop_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'webshop_exports',
    'type' => 'Json',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"webshopdefault","remote":"sftp:\/\/test:test@example.org\/default\/"},"1":{"file":"webshop1","remote":"sftp:\/\/test1:test1@example.org\/webshop1\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Webshop Export sFTP upload details',
    'html_attributes' => array(),
  ),
  //versandtool_exports
  'versandtool_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'versandtool_exports',
    'type' => 'Json',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"versandtool","remote":"sftp:\/\/test:test@example.org\/default\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Versandtool Export sFTP upload details',
    'html_attributes' => array(),
  ),
  //welcomepackagepost_exports
  'welcomepackagepost_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'welcomepackagepost_exports',
    'type' => 'Json',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"welcomepackagepost","remote":"sftp:\/\/test:test@example.org\/default\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'WelcomePackagePost Export sFTP upload details',
    'html_attributes' => array(),
  ),
  //welcomepackagepost_campaign_externalidentifier
  'welcomepackagepost_campaign_externalidentifier' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'welcomepackagepost_campaign_externalidentifier',
    'type' => 'String',
    'html_type' => 'Text',
    'default' => 'AKTION-7767',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'WelcomePackagePost Campaign External Identifier',
    'html_attributes' => array(),
  ),
  //welcomepackageemail_exports
  'welcomepackageemail_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'welcomepackageemail_exports',
    'type' => 'Json',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"welcomepackageemail","remote":"sftp:\/\/test:test@example.org\/default\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'WelcomePackageEmail Export sFTP upload details',
    'html_attributes' => array(),
  ),
  //welcomepackageemail_campaign_externalidentifier
  'welcomepackageemail_campaign_externalidentifier' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'welcomepackageemail_campaign_externalidentifier',
    'type' => 'String',
    'html_type' => 'Text',
    'default' => 'AKTION-7769',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'WelcomePackageEmail Campaign External Identifier',
    'html_attributes' => array(),
  ),
);
