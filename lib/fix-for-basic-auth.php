<?php

// league/oauth2-server attempts to be helpful and use the Basic auth username to determine the client_id
// That is wrong

unset($_SERVER['HTTP_AUTHORIZATION']);
