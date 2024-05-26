<?php

require_once 'app.php';

try {
    $api = new RestAPI();
} catch (Exception $e) {
    die($e->getMessage());
}

$api->app->run();

?>