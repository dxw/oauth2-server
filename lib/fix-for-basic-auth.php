<?php

// league/oauth2-server attempts to be helpful and use the Basic auth username to determine the client_id
// That won't work with staging sites

unset($_SERVER['PHP_AUTH_USER']);
unset($_SERVER['PHP_AUTH_PW']);
unset($_SERVER['HTTP_AUTHORIZATION']);
