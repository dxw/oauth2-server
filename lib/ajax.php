<?php

class OAuth2Server_Ajax {
  function __construct() {
    add_action('wp_ajax_oauth2-auth', [$this, 'auth']);
    add_action('wp_ajax_oauth2-approvedeny', [$this, 'approvedeny']);
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

    update_user_meta(get_current_user_id(), 'oauth2_vars', [
      'server' => $server,
      'params' => $params,
    ]);

    ?>

    A client wants to do stuff.

    <form method="post" action="<?php echo esc_attr(get_admin_url(0, 'admin-ajax.php')) ?>">
      <input type="hidden" name="action" value="oauth2-approvedeny">
      <?php wp_nonce_field('approvedeny') ?>
      <input type="submit" name="approve" value="Approve">
    </form>

    <?php

    die();
  }

  function approvedeny() {
    // OAUTH2_SERVER_TEST_NONCE_OVERRIDE should never be set on a production server - it's used by the test suite
    if (!(wp_verify_nonce($_POST['_wpnonce'], 'approvedeny') || (defined('OAUTH2_SERVER_TEST_NONCE_OVERRIDE') && $_POST['_wpnonce'] === OAUTH2_SERVER_TEST_NONCE_OVERRIDE))) {
      header('HTTP/1.1 500 Internal Server Error');
      echo 'invalid nonce';
      die();
    }

    $vars = get_user_meta(get_current_user_id(), 'oauth2_vars', true);
    $server = $vars['server'];
    $params = $vars['params'];

    $grant = $server->getGrantType('authorization_code');

    if (isset($_POST['approve'])) {
      // Approved

      $code = $grant->newAuthoriseRequest('user', get_current_user_id(), $params);

      $uri = \League\OAuth2\Server\Util\RedirectUri::make(
        $params['client_details']['redirect_uri'], [
          'code' => $code,
          'state' => $params['state'],
        ]
      );

      wp_redirect($uri, 302); // 302 Found
      die();

    } else {
      // Denied

      echo 'denied';
    }

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

    $server = new \League\OAuth2\Server\Authorization(new ClientModel, new SessionModel, new ScopeModel);
    $server->addGrantType(new \League\OAuth2\Server\Grant\AuthCode());
    $server->addGrantType(new \League\OAuth2\Server\Grant\RefreshToken());

    $p = $server->issueAccessToken();

    // Add user data

    $user_id = absint($wpdb->get_var($wpdb->prepare("SELECT owner_id FROM wp_oauth2_server_sessions WHERE id IN (SELECT session_id FROM wp_oauth2_server_access_tokens WHERE access_token=%s)", $p['access_token'])));
    if ($user_id === 0) {
      trigger_error('UNKNOWN USER', E_USER_ERROR);
    }
    $user = get_user_by('id', $user_id);

    $p['information'] = [
      'email' => $user->data->user_email,
      'display_name' => $user->data->display_name,
    ];

    echo json_encode($p);

    die();
  }
}

new OAuth2Server_Ajax;
