<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class PostLoansTest extends TestCase
{
    private $_slim;
    private $_mysqli;
    private $_userId;

    public function testActionCreateLoans()
    {
        $config = parse_ini_file(dirname(__DIR__) . '/config.ini', true)
            or die("config ini do not exists!");
        $db = $config['mysql'];
        $mysqli = new mysqli(
            $db['host'],
            $db['user'],
            $db['pass'],
            $db['database'],
            $db['port']
        );

        $mysqli->query("INSERT INTO users (first_name) VALUES ('PHPUnitTest')");
        $userId = $mysqli->insert_id;

        $slim = new RestAPI();
        $this->_mysqli = $mysqli;
        $this->_slim = $slim;
        $this->_userId = $userId;
        $this->correctTest();
        $this->unknownUserTest();
        $this->requiredParamsTest();
        $this->wrongJSONTest();

        $mysqli->query("DELETE FROM users WHERE id = $userId LIMIT 1");
    }

    private function wrongJSONTest()
    {
        $request = new ServerRequest(
            'POST',
            '/loans',
            ['Content-Type' => 'application/json'],
            'wrong json',
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->_slim->app->handle($request);
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['status']);
        $this->assertEquals('Invalid JSON format', $data['message']);
    }


    private function requiredParamsTest()
    {
        $body = [
            'user_id' => $this->_userId,
            'amount'  => 2000
        ];

        $request = new ServerRequest(
            'POST',
            '/loans',
            ['Content-Type' => 'application/json'],
            json_encode($body),
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->_slim->app->handle($request);
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['status']);
        $this->assertMatchesRegularExpression('/^Param\s"([a-zA-Z_]+)"\sis\srequired!$/', $data['message']);
    }


    private function unknownUserTest()
    {
        $body = [
            'user_id' => 2147483647,
            'amount'  => 2000,
            'pay_time' => time()
        ];

        $request = new ServerRequest(
            'POST',
            '/loans',
            ['Content-Type' => 'application/json'],
            json_encode($body),
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->_slim->app->handle($request);
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($data['status']);
        $this->assertArrayHasKey('status', $data);
    }


    private function correctTest()
    {
        $body = [
            'user_id'  => $this->_userId,
            'amount'   => 2000,
            'pay_time' => time()
        ];

        $request = new ServerRequest(
            'POST',
            '/loans',
            ['Content-Type' => 'application/json'],
            json_encode($body),
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->_slim->app->handle($request);
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('row_id', $data);
        $this->assertTrue($data['status']);
        $this->assertStringMatchesFormat('%i', $data['row_id']);

        $this->_mysqli->query("DELETE FROM loans WHERE id = {$data['row_id']} LIMIT 1");
    }
}
?>
