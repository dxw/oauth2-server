<?php

$GLOBALS['clients'] = [
  '123' => [
    'client_id' => '123',
    'client secret' => '456',
    'redirect_uri' => 'http://abc/happy',
    'name' => 'Happy Client'
  ],
];

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
