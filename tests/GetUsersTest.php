<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class GetUsersTest extends TestCase
{
    public function testActionUsers()
    {
        $slim = new RestAPI();
        $request = new ServerRequest(
            'GET',
            '/users',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $response = $slim->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('details', $data);
        $this->assertTrue($data['status']);
        if (isset($data['details'][0])) {
            $keys = ['id', 'first_name', 'last_name', 'phone', 'birth_date'];
            foreach ($data['details'] as $userInfo) {
                foreach ($keys as $key) {
                    $this->assertArrayHasKey($key, $userInfo);
                    if ($key == 'id') {
                        $this->assertStringMatchesFormat('%i', $userInfo[$key]);
                    }
                }
            }
        }

        $request = new ServerRequest(
            'GET',
            '/users/-1',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $response = $slim->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('status', $data);
        $this->assertFalse($data['status']);
        $this->assertArrayNotHasKey('details', $data);
    }
}
?>
