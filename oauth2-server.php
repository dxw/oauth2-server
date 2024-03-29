<?php

// Plugin Name: OAuth 2 Server
// Author: dxw
// Author URI: http://dxw.com/
// Version: 0.1.0
// Whippet: yes

include(__DIR__.'/vendor.phar');

include(__DIR__.'/lib/ajax.php');
include(__DIR__.'/lib/db.php');
include(__DIR__.'/lib/fix-for-basic-auth.php');
include(__DIR__.'/lib/fix-for-new-members-only.php');
include(__DIR__.'/lib/acf.php');

# Models for the OAuth2 library
include(__DIR__.'/lib/model-client.php');
include(__DIR__.'/lib/model-scope.php');
include(__DIR__.'/lib/model-session.php');
