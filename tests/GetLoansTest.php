<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class GetLoansTest extends TestCase
{
    public function testActionLoans()
    {
        $slim = new RestAPI();
        $request = new ServerRequest(
            'GET',
            '/loans',
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
            $keys = ['id', 'user_id', 'amount', 'pay_time'];
            foreach ($data['details'] as $loanInfo) {
                foreach ($keys as $key) {
                    $this->assertArrayHasKey($key, $loanInfo);
                    $this->assertStringMatchesFormat('%i', $loanInfo[$key]);
                }
            }
        }

        $request = new ServerRequest(
            'GET',
            '/loans/-1',
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