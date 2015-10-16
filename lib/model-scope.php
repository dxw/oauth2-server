<?php

class ScopeModel implements \League\OAuth2\Server\Storage\ScopeInterface
{
    // Only return one scope
    public function getScope($scope, $clientId = null, $grantType = null)
    {
        $s = get_bloginfo('url').'/';

        if ($scope !== $s || $grantType !== 'authorization_code') {
            return false;
        }

        return [
            'id' => 'main',
            'scope' => $s,
            'name' => 'Main scope',
            'description' => '',
        ];
    }
}
