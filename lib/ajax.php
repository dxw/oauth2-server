<?php

class OAuth2Server_Ajax {
  function __construct() {
    add_action('wp_ajax_oauth2-auth', [$this, 'auth']);
    add_action('wp_ajax_nopriv_oauth2-auth', [$this, 'auth_nopriv']);
    add_action('wp_ajax_nopriv_oauth2-token', [$this, 'token']);
  }

  // This is where a user will generally enter the authentication process.
  //
  // Expected URL parameters:
  // - access_type=
  // - approval_prompt=
  // - client_id=123
  // - redirect_uri=http://abc/happy
  // - response_type=code
  // - scope=http://judiciary.ayumu/
  // - state=
  function auth() {
    $server = new \League\OAuth2\Server\Authorization(new ClientModel, new SessionModel, new ScopeModel);
    $server->addGrantType(new \League\OAuth2\Server\Grant\AuthCode());

    $grant = $server->getGrantType('authorization_code');
    $params = $grant->checkAuthoriseParams();

    // Skip the "Approve" button and just log the user in

    $code = $grant->newAuthoriseRequest('user', get_current_user_id(), $params);

    $uri = \League\OAuth2\Server\Util\RedirectUri::make(
      $params['client_details']['redirect_uri'], [
        'code' => $code,
        'state' => $params['state'],
      ]
    );

    wp_redirect($uri, 302); // 302 Found
    die();
  }

  // This is where a user will end up if they enter the authentication process without logging in.
  // It just redirects people to the login form, with the privileged auth ajax handler above as the
  // redirect target.
  function auth_nopriv() {
    $auth_url = admin_url('admin-ajax.php', 'absolute') . '?' . $_SERVER['QUERY_STRING'];

    wp_redirect(site_url("wp-login.php?redirect_to=" . urlencode($auth_url)), 301);
    die();
  }

  // This is the URL the client application will make to check that the user isn't lying.
  //
  // Expected POST data:
  // - client_id=123
  // - client_secret=456
  // - code=w7KWDULMgFmgleJN8ABZwEPdvqYafZs2UwNys9hA
  // - grant_type=authorization_code
  // - redirect_uri=http://abc/happy
  // - scope=http://judiciary.ayumu/
  function token() {
    global $wpdb;

    $debug = [];

    $t1 = microtime(true);

    $server = new \League\OAuth2\Server\Authorization(new ClientModel, new SessionModel, new ScopeModel);
    $server->addGrantType(new \League\OAuth2\Server\Grant\AuthCode());
    $server->addGrantType(new \League\OAuth2\Server\Grant\RefreshToken());

    $server->getGrantType('refresh_token')->rotateRefreshTokens(true);

    $debug['t1'] = microtime(true) - $t1;
    $t2 = microtime(true);

    try {
      $p = $server->issueAccessToken();
    } catch (\League\OAuth2\Server\Exception\ClientException $e) {
      $debug['t2'] = microtime(true) - $t2;

      $p = ['error' => true, 'message' => 'invalid grant type'];

      if (defined('OAUTH2_SERVER_DEBUG') && OAUTH2_SERVER_DEBUG === true) {
        $p['debug'] = $debug;
      }

      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode($p);
      die();
    }

    $debug['t2'] = microtime(true) - $t2;
    $t3 = microtime(true);

    // Add user data

    $user_id = absint($wpdb->get_var($wpdb->prepare("SELECT owner_id FROM {$wpdb->prefix}oauth2_server_sessions WHERE id = (SELECT session_id FROM {$wpdb->prefix}oauth2_server_access_tokens WHERE access_token=%s AND deleted_at='0000-00-00 00:00:00' LIMIT 1) AND deleted_at='0000-00-00 00:00:00'", $p['access_token'])));

    if ($user_id === 0) {
      $debug['t3'] = microtime(true) - $t3;

      $p = ['error' => true, 'message' => 'invalid auth code'];

      if (defined('OAUTH2_SERVER_DEBUG') && OAUTH2_SERVER_DEBUG === true) {
        $p['debug'] = $debug;
      }

      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode($p);
      die();
    }
    $user = get_user_by('id', $user_id);

    $debug['t3'] = microtime(true) - $t3;

    $p['information'] = [
      'email' => $user->data->user_email,
      'display_name' => $user->data->display_name,
      'extra' => apply_filters('oauth2_server_extra', [], $user_id),
    ];

    if (defined('OAUTH2_SERVER_DEBUG') && OAUTH2_SERVER_DEBUG === true) {
      $p['debug'] = $debug;
    }

    echo json_encode($p);
    die();
  }
}

new OAuth2Server_Ajax;
