<?php

class SessionModel implements \League\OAuth2\Server\Storage\SessionInterface {

  // CRUD session

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

  // Auth code

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

  // Associate things

  // Do nothing - we only use one scope
  public function associateScope($sessionId, $scopeId) {
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

  // Do nothing - we only have one scope
  public function associateAuthCodeScope($authCodeId, $scopeId) {
  }

  // Other stuff

  public function getAuthCodeScopes($oauthSessionAuthCodeId) {
    return [
      ['scope_id' => 'main'],
    ];
  }

  // Unimplemented but required by the interface

  public function getScopes($accessToken) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function removeRefreshToken($refreshToken) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested') {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }
  public function validateAccessToken($accessToken) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function getAccessToken($sessionId) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function validateRefreshToken($refreshToken, $clientId) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }
}
