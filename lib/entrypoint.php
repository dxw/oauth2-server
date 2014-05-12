<?php

// add_action('wp_ajax_nopriv_oauth2-entrypoint', function () {
//   echo 'Not logged in. Log in plz and try again.';
//   die();
// });

add_action('wp_ajax_oauth2-auth', function () {
  $server = new \League\OAuth2\Server\Authorization(new ClientModel, new SessionModel, new ScopeModel);
  $server->addGrantType(new \League\OAuth2\Server\Grant\AuthCode());
  $grant = $server->getGrantType('authorization_code');
  $params = $grant->checkAuthoriseParams();

  ?>

  A client wants to do stuff.

  <form method="post" action="<?php echo esc_attr(get_admin_url(0, 'admin-ajax.php')) ?>">
    <input type="hidden" name="action" value="oauth2-approvedeny">
    <?php #TODO nonce ?>
    <input type="hidden" name="server" value="<?php echo esc_attr(json_encode(serialize($server))) ?>">
    <input type="hidden" name="params" value="<?php echo esc_attr(json_encode(serialize($params))) ?>">
    <input type="submit" name="approve" value="Approve">
    <input type="submit" name="deny" value="Deny">
  </form>

  <?php

  die();
});

add_action('wp_ajax_oauth2-approvedeny', function () {
  //TODO: check nonce

  $server = unserialize(json_decode(stripslashes($_POST['server']))); //TODO: this is wrong
  $params = unserialize(json_decode(stripslashes($_POST['params']))); //TODO: this is wrong

  $grant = $server->getGrantType('authorization_code');

  if (isset($_POST['approve'])) {
    // Approved

    echo 'approved';
    $code = $grant->newAuthoriseRequest('username???', $params['client_details']['client_id'], $params);

    echo(htmlspecialchars(json_encode($code))."<br>\n");

  } else {
    // Denied

    echo 'denied';
  }

  die();
});


//////////////////////////////////////////////////////////////////////////////
// Classes and stuff

class ClientModel implements \League\OAuth2\Server\Storage\ClientInterface {

  public function getClient($clientId = null, $clientSecret = null, $redirectUri = null, $grantType = null) {

    //TODO: clients should be stored in the DB and we should check that clientId/redirectUri match

    // echo(htmlspecialchars(json_encode([$clientId, $clientSecret, $redirectUri, $grantType]))."<br>\n");
    if ($clientId !== '123') {
      return false;
    }

    return array(
      'client_id' => '123',
      'client secret' => '456',
      'redirect_uri' => 'http://abc/happy',
      'name' => 'Happy Client'
    );
  }

}

class ScopeModel implements \League\OAuth2\Server\Storage\ScopeInterface {

  // public function __construct() {
  // }

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
    // trigger_error(json_encode(func_get_args()), E_USER_ERROR);

    // TODO: store a record and return its ID
    return 7;
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
    // $x=[$clientId, $ownerType, $ownerId];
    // trigger_error(json_encode($x), E_USER_ERROR);
    //TODO: do something here???
  }

  public function validateAuthCode($clientId, $redirectUri, $authCode) {
    trigger_error(json_encode(3), E_USER_ERROR);
    $result = $this->db->query('
    SELECT * FROM oauth_sessions WHERE
    client_id = :clientId AND
    redirect_uri = :redirectUri AND
    auth_code = :authCode',
    array(
      ':clientId' =>  $clientId,
      ':redirectUri'  =>  $redirectUri,
      ':authCode' =>  $authCode
    ));

    while ($row = $result->fetch())
    {
      return (array) $row;
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

  public function associateScope($sessionId, $scopeId) {
    trigger_error(json_encode(8), E_USER_ERROR);
    $this->db->query('INSERT INTO oauth_session_scopes (session_id, scope_id) VALUE (:sessionId, :scopeId)', array(
      ':sessionId'    =>  $sessionId,
      ':scopeId'  =>  $scopeId
    ));
  }

  public function getScopes($accessToken) {
    trigger_error(json_encode(9), E_USER_ERROR);
  }

  public function associateRedirectUri($sessionId, $redirectUri) {
    // TODO associate record $sessionId with $redirectUri
  }

  public function associateAccessToken($sessionId, $accessToken, $expireTime) {
    trigger_error(json_encode(11), E_USER_ERROR);
  }

  public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId) {
    trigger_error(json_encode(12), E_USER_ERROR);
  }

  public function associateAuthCode($sessionId, $authCode, $expireTime) {
    //TODO associate record $sessionId with $authCode and $expireTime
    // And return an ID
    return 8;
  }

  public function removeAuthCode($sessionId) {
    trigger_error(json_encode(14), E_USER_ERROR);
  }

  public function removeRefreshToken($refreshToken) {
    trigger_error(json_encode(15), E_USER_ERROR);
  }

  public function associateAuthCodeScope($authCodeId, $scopeId) {
    //TODO associate auth code ID $authCodeId with $scopeId
  }

  public function getAuthCodeScopes($oauthSessionAuthCodeId) {
    trigger_error(json_encode(17), E_USER_ERROR);
  }

}
