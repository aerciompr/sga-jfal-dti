<?php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'sga');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'justica_senhas');
define('SYS_NAME', getenv('SYS_NAME') ?: 'Justiça Federal');


define('DB_PORT', getenv('DB_PORT') ?: '3306');
