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
    $code = $grant->newAuthoriseRequest('username', $params['client_id'], $params);

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

  public function __construct() {
  }

  public function createSession($clientId, $redirectUri, $type = 'user', $typeId = null, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested') {
    $this->db->query('
    INSERT INTO oauth_sessions (
      client_id,
      redirect_uri,
      owner_type,
      owner_id,
      auth_code,
      access_token,
      refresh_token,
      access_token_expires,
      stage,
      first_requested,
      last_updated
    )
    VALUES (
      :clientId,
      :redirectUri,
      :type,
      :typeId,
      :authCode,
      :accessToken,
      :refreshToken,
      :accessTokenExpire,
      :stage,
      UNIX_TIMESTAMP(NOW()),
      UNIX_TIMESTAMP(NOW())
    )', array(
      ':clientId' =>  $clientId,
      ':redirectUri'  =>  $redirectUri,
      ':type' =>  $type,
      ':typeId'   =>  $typeId,
      ':authCode' =>  $authCode,
      ':accessToken'  =>  $accessToken,
      ':refreshToken' =>  $refreshToken,
      ':accessTokenExpire'    =>  $accessTokenExpire,
      ':stage'    =>  $stage
    ));

    return $this->db->getInsertId();
  }

  public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested') {
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

  public function deleteSession($clientId, $type, $typeId) {
    $this->db->query('
    DELETE FROM oauth_sessions WHERE
    client_id = :clientId AND
    owner_type = :type AND
    owner_id = :typeId',
    array(
      ':clientId' =>  $clientId,
      ':type'  =>  $type,
      ':typeId' =>  $typeId
    ));
  }

  public function validateAuthCode($clientId, $redirectUri, $authCode) {
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
    // Not needed for this demo
    die(var_dump('validateAccessToken'));
  }

  public function getAccessToken($sessionId) {
    // Not needed for this demo
  }

  public function validateRefreshToken($refreshToken, $clientId) {
    // Not needed for this demo
  }

  public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires) {
    // Not needed for this demo
  }

  public function associateScope($sessionId, $scopeId) {
    $this->db->query('INSERT INTO oauth_session_scopes (session_id, scope_id) VALUE (:sessionId, :scopeId)', array(
      ':sessionId'    =>  $sessionId,
      ':scopeId'  =>  $scopeId
    ));
  }

  public function getScopes($accessToken) {
    // Not needed for this demo
  }

  public function associateRedirectUri($sessionId, $redirectUri) {
  }

  public function associateAccessToken($sessionId, $accessToken, $expireTime) {
  }

  public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId) {
  }

  public function associateAuthCode($sessionId, $authCode, $expireTime) {
  }

  public function removeAuthCode($sessionId) {
  }

  public function removeRefreshToken($refreshToken) {
  }

  public function associateAuthCodeScope($authCodeId, $scopeId) {
  }

  public function getAuthCodeScopes($oauthSessionAuthCodeId) {
  }

}
