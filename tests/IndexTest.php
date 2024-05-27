<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class IndexTest extends TestCase
{
    public function testActionIndex()
    {
        $slim = new RestAPI();
        $request = new ServerRequest(
            'GET',
            '/',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $response = $slim->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
?>