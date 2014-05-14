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

  public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId) {
    global $wpdb;

    $c = $wpdb->insert($wpdb->prefix.'oauth2_server_refresh_tokens', [
      'access_token_id' => $accessTokenId,
      'refresh_token' => $refreshToken,
      'expire_time' => $expireTime,
      'client_id' => $clientId,
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql'),
    ]);

    if ($c === 1) {
      return $wpdb->insert_id;
    }

    return false;
  }

  public function validateRefreshToken($refreshToken, $clientId) {
    global $wpdb;

    $sql = $wpdb->prepare("
    SELECT access_token_id
    FROM {$wpdb->prefix}oauth2_server_refresh_tokens
    WHERE client_id=%s
    AND refresh_token=%s
    AND deleted_at='0000-00-00 00:00:00'
    ", $clientId, $refreshToken);

    $var = absint($wpdb->get_var($sql));

    if ($var > 0) {
      return $var;
    }

    return false;
  }

  public function removeRefreshToken($refreshToken) {
    global $wpdb;

    $wpdb->update(
      $wpdb->prefix.'oauth2_server_refresh_tokens',
      [
        'deleted_at' => current_time('mysql'),
      ],
      [
      'refresh_token' => $refreshToken,
      ]
    );
  }

  public function getAccessToken($sessionId) {
    global $wpdb;

    $sql = $wpdb->prepare("
    SELECT id, session_id, access_token, expire_time
    FROM {$wpdb->prefix}oauth2_server_access_tokens
    WHERE session_id=%s
    AND deleted_at='0000-00-00 00:00:00'
    ", $sessionId);

    $row = $wpdb->get_row($sql, ARRAY_A);

    if ($row !== false) {
      return [
        'id' => $row['id'],
        'session_id' => $row['session_id'],
        'access_token' => $row['access_token'],
        'access_token_expires' => $row['expire_time'],
      ];
    }

    return false;
  }

  public function getScopes($accessToken) {
    return [
      [
        'id' => 1,
        'scope' => get_bloginfo('url') . '/',
        'name' => '',
        'description' => '',
      ]
    ];
  }

  // Do nothing - we only have one scope
  public function associateAuthCodeScope($authCodeId, $scopeId) {
  }

  // Other stuff

  public function getAuthCodeScopes($oauthSessionAuthCodeId) {
    return [
      // ['scope_id' => get_bloginfo('url') . '/'],
      ['scope_id' => 'main'],
    ];
  }

  // Unimplemented but required by the interface

  public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested') {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }
  public function validateAccessToken($accessToken) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }

  public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires) {
    trigger_error('NOT IMPLEMENTED', E_USER_ERROR);
  }
}
