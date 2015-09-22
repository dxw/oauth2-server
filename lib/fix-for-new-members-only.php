<?php

add_filter('new_members_only_redirect', function () {
  if (
    nmo_strip_query($_SERVER['REQUEST_URI']) === '/wp-admin/admin-ajax.php'
    &&
    ($_GET['action'] === 'oauth2-auth' || $_GET['action'] === 'oauth2-token')
  ) {
    return true;
  }

  return false;
});
