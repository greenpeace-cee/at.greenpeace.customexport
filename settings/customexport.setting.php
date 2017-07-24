<?php

return array(
//webshop_exports
  'webshop_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'webshop_exports',
    'type' => 'String',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"webshopdefault","remote":"sftp:\/\/test:test@example.org\/default\/"},"1":{"file":"webshop1","remote":"sftp:\/\/test1:test1@example.org\/webshop1\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Webshop Exports',
    'html_attributes' => array(),
  ),
  //versandtool_exports
  'versandtool_exports' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'versandtool_exports',
    'type' => 'String',
    'html_type' => 'Textarea',
    'default' => '{"default":{"file":"versandtool","remote":"sftp:\/\/test:test@example.org\/default\/"}}',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Versandtool Exports',
    'html_attributes' => array(),
  ),
  //versandtool_batchsize
  'versandtool_batchsize' => array(
    'group_name' => 'Customexport Preferences',
    'group' => 'Customexport',
    'name' => 'versandtool_batchsize',
    'type' => 'Integer',
    'html_type' => 'Text',
    'default' => '100000',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Number of contacts to process in an iteration (adjust to optimise memory usage/sql queries).  All matching contacts will be exported',
    'html_attributes' => array(),
  ),
);
