<?php

acf_add_options_sub_page('OAuth2 Server');

if(function_exists("register_field_group")) {
  register_field_group(array (
    'id' => 'acf_oauth2-server',
    'title' => 'OAuth2 Server',
    'fields' => array (
      array (
        'key' => 'field_537244eb6da4b',
        'label' => 'Client applications',
        'name' => 'client_applications',
        'type' => 'repeater',
        'sub_fields' => array (
          array (
            'key' => 'field_5372454a6da4f',
            'label' => 'Name',
            'name' => 'name',
            'type' => 'text',
            'instructions' => 'Some kind of identifier (not shown to users) i.e. "Bob\'s Client Application"',
            'column_width' => '',
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'formatting' => 'html',
            'maxlength' => '',
          ),
          array (
            'key' => 'field_537245106da4c',
            'label' => 'Client ID',
            'name' => 'client_id',
            'type' => 'text',
            'instructions' => 'A textual identifier for the client application (must be shared with the client application)',
            'column_width' => '',
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'formatting' => 'html',
            'maxlength' => '',
          ),
          array (
            'key' => 'field_537245256da4d',
            'label' => 'Client secret',
            'name' => 'client_secret',
            'type' => 'text',
            'instructions' => 'Shared secret (must be shared with the client application and nobody else)',
            'column_width' => '',
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'formatting' => 'html',
            'maxlength' => '',
          ),
          array (
            'key' => 'field_537245406da4e',
            'label' => 'Redirect URI',
            'name' => 'redirect_uri',
            'type' => 'text',
            'instructions' => 'The URI which is redirected to after the user clicks "Approve" (must be shared with the client application)',
            'column_width' => '',
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'formatting' => 'html',
            'maxlength' => '',
          ),
        ),
        'row_min' => '',
        'row_limit' => '',
        'layout' => 'table',
        'button_label' => 'Add Row',
      ),
    ),
    'location' => array (
      array (
        array (
          'param' => 'options_page',
          'operator' => '==',
          'value' => 'acf-options-oauth2-server',
          'order_no' => 0,
          'group_no' => 0,
        ),
      ),
    ),
    'options' => array (
      'position' => 'normal',
      'layout' => 'no_box',
      'hide_on_screen' => array (
      ),
    ),
    'menu_order' => 0,
  ));
}
