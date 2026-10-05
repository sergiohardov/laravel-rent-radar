<?php

// Copy to relay-config.php on the relay hosting (one level above the site root
// if possible) and put a random key of 32+ characters: openssl rand -hex 32.
// The same key goes into SOURCES_PROXY_URL on the bot's hosting.
return [
    'key' => '',
];
