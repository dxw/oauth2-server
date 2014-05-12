<?php

$GLOBALS['clients'] = [
  '123' => [
    'client_id' => '123',
    'client secret' => '456',
    'redirect_uri' => 'http://abc/happy',
    'name' => 'Happy Client'
  ],
];

// add_action('wp_ajax_nopriv_oauth2-entrypoint', function () {
//   echo 'Not logged in. Log in plz and try again.';
//   die();
// });

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
add_action('wp_ajax_oauth2-auth', function () {
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
    <?php #TODO nonce ?>
    <input type="submit" name="approve" value="Approve">
    <input type="submit" name="deny" value="Deny">
  </form>

  <?php

  die();
});

add_action('wp_ajax_oauth2-approvedeny', function () {
  //TODO: check nonce

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
});

// This is the URL the client application will make to check that the user isn't lying.
//
// Expected POST data:
// - client_id=123
// - client_secret=456
// - code=w7KWDULMgFmgleJN8ABZwEPdvqYafZs2UwNys9hA
// - grant_type=authorization_code
// - redirect_uri=http://abc/happy
// - scope=http://judiciary.ayumu/
add_action('wp_ajax_nopriv_oauth2-token', function () {
  $server = new \League\OAuth2\Server\Authorization(new ClientModel, new SessionModel, new ScopeModel);
  $server->addGrantType(new \League\OAuth2\Server\Grant\AuthCode());

  $p = $server->issueAccessToken();

  echo json_encode($p);

  die();
});


//////////////////////////////////////////////////////////////////////////////
// Classes and stuff

class ClientModel implements \League\OAuth2\Server\Storage\ClientInterface {

  public function getClient($clientId = null, $clientSecret = null, $redirectUri = null, $grantType = null) {

    //TODO: clients should be stored in the DB and we should check that clientId/redirectUri match

    // echo(htmlspecialchars(json_encode([$clientId, $clientSecret, $redirectUri, $grantType]))."<br>\n");
    if (isset($GLOBALS['clients'][$clientId])) {
      return $GLOBALS['clients'][$clientId];
    }

    return false;
  }

}

class ScopeModel implements \League\OAuth2\Server\Storage\ScopeInterface {

  // public function __construct() {
  // }

  // Only return one scope
  public function getScope($scope, $clientId = null, $grantType = null) {
    $s = get_bloginfo('url') . '/';

    if ($scope !== $s || $grantType !== 'authorization_code') {
      return false;
    }

    return [
      'id'  =>  'main',
      'scope' =>  $s,
      'name'  =>  'Main scope',
      'description' => '',
    ];
  }
}

class SessionModel implements \League\OAuth2\Server\Storage\SessionInterface {

  // public function __construct() {
  // }

  public function createSession($clientId, $ownerType, $ownerId) {
    global $wpdb;

    if ($ownerType !== 'user') {
      return false;
    }

    $c = $wpdb->insert($wpdb->prefix.'oauth2_server_sessions', [
      'client_id' => $clientId,
      'owner_type' => $ownerType,
      'owner_id' => $ownerId, // this is the user's ID
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ]);

    if ($c === 1) {
      return $wpdb->insert_id;
    }

    return false;
  }

  public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested') {
    trigger_error(json_encode(2), E_USER_ERROR);
    $this->db->query('
    UPDATE oauth_sessions SET
    auth_code = :authCode,
    access_token = :accessToken,
    refresh_token = :refreshToken,
    access_token_expires = :accessTokenExpire,
    stage = :stage,
    last_updated = UNIX_TIMESTAMP(NOW())
    WHERE id = :sessionId',
    array(
      ':authCode' =>  $authCode,
      ':accessToken'  =>  $accessToken,
      ':refreshToken' =>  $refreshToken,
      ':accessTokenExpire'    =>  $accessTokenExpire,
      ':stage'    =>  $stage,
      ':sessionId'    =>  $sessionId
    ));
  }

  public function deleteSession($clientId, $ownerType, $ownerId) {
    global $wpdb;

    $wpdb->update(
      $wpdb->prefix.'oauth2_server_sessions',
      [
        'deleted_at' => current_time('mysql'),
      ],
      [
      'client_id' => $clientId,
      'owner_type' => $ownerType,
      'owner_id' => $ownerId,
      ]
    );
    // Ignore errors
  }

  public function validateAuthCode($clientId, $redirectUri, $authCode) {
    global $wpdb;

    $sql = $wpdb->prepare("
    SELECT session_id, id AS authcode_id
    FROM {$wpdb->prefix}oauth2_server_auth_codes
    WHERE session_id IN (
      SELECT id FROM {$wpdb->prefix}oauth2_server_sessions WHERE client_id=%s AND deleted_at='0000-00-00 00:00:00'
    )
    AND auth_code=%s
    AND deleted_at='0000-00-00 00:00:00'
    ", $clientId, $authCode);

    $row = $wpdb->get_row($sql, ARRAY_A);

    if ($row !== false) {
      return [
        'session_id' => $row['session_id'],
        'authcode_id' => $row['authcode_id'],
      ];
    }

    return false;
  }

  public function validateAccessToken($accessToken) {
    trigger_error(json_encode(4), E_USER_ERROR);
  }

  public function getAccessToken($sessionId) {
    trigger_error(json_encode(5), E_USER_ERROR);
  }

  public function validateRefreshToken($refreshToken, $clientId) {
    trigger_error(json_encode(6), E_USER_ERROR);
  }

  public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires) {
    trigger_error(json_encode(7), E_USER_ERROR);
  }

  // Do nothing - we only use one scope
  public function associateScope($sessionId, $scopeId) {
  }

  public function getScopes($accessToken) {
    trigger_error(json_encode(9), E_USER_ERROR);
  }

  // Do nothing - we get the redirectUri from the client ID
  public function associateRedirectUri($sessionId, $redirectUri) {
  }

  public function associateAccessToken($sessionId, $accessToken, $expireTime) {
    global $wpdb;

    $c = $wpdb->insert($wpdb->prefix.'oauth2_server_access_tokens', [
      'session_id' => $sessionId,
      'access_token' => $accessToken,
      'expire_time' => $expireTime,
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ]);

    if ($c === 1) {
      return $wpdb->insert_id;
    }

    return false;
  }

  public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId) {
    trigger_error(json_encode(12), E_USER_ERROR);
  }

  public function associateAuthCode($sessionId, $authCode, $expireTime) {
    global $wpdb;

    $c = $wpdb->insert($wpdb->prefix.'oauth2_server_auth_codes', [
      'session_id' => $sessionId,
      'auth_code' => $authCode,
      'expire_time' => $expireTime,
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ]);

    if ($c === 1) {
      return $wpdb->insert_id;
    }

    return false;
  }

  public function removeAuthCode($sessionId) {
    global $wpdb;

    $wpdb->update(
      $wpdb->prefix.'oauth2_server_auth_codes',
      [
        'deleted_at' => current_time('mysql'),
      ],
      [
      'session_id' => $sessionId,
      ]
    );
  }

  public function removeRefreshToken($refreshToken) {
    trigger_error(json_encode(15), E_USER_ERROR);
  }

  // Do nothing - we only have one scope
  public function associateAuthCodeScope($authCodeId, $scopeId) {
  }

  public function getAuthCodeScopes($oauthSessionAuthCodeId) {
    return [
      ['scope_id' => 'main'],
    ];
  }

}
