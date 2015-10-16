<?php

class ClientModel implements \League\OAuth2\Server\Storage\ClientInterface
{
    // Get client array from ACF option field
    public function getClient($clientId = null, $clientSecret = null, $redirectUri = null, $grantType = null)
    {
        $num_clients = (int) get_option('options_client_applications');

        for ($i = 0; $i < $num_clients; ++$i) {
            $id = get_option(sprintf('options_client_applications_%d_client_id', $i));

            if ($id === $clientId) {
                return [
                    'client_id' => $id,
                    'client secret' => get_option(sprintf('options_client_applications_%d_client_secret', $i)),
                    'redirect_uri' => get_option(sprintf('options_client_applications_%d_redirect_uri', $i)),
                    'name' => get_option(sprintf('options_client_applications_%d_name', $i)),
                ];
            }
        }

        return false;
    }
}
