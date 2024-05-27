<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class PostUsersTest extends TestCase
{
    private $_mysqli;
    private $_slim;

    public function testActionCreateUsers()
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

        $this->_slim = new RestAPI();
        $this->_mysqli = $mysqli;
        $this->correctTest();
        $this->requiredParamsTest();
        $this->wrongJSONTest();
    }

    private function wrongJSONTest()
    {
        $request = new ServerRequest(
            'POST',
            '/users',
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
            'phone' => '+70123456789'
        ];

        $request = new ServerRequest(
            'POST',
            '/users',
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


    private function correctTest()
    {
        $body = [
            'first_name'  => 'TestGeneratedName\'SELECT * FROM mysql.user\'',
            'last_name'   => 'TestGeneratedLast',
            'phone'       => '+79876543210',
            'birth_date'  => date('Y-m-d')
        ];

        $request = new ServerRequest(
            'POST',
            '/users',
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

        $this->_mysqli->query("DELETE FROM users WHERE id = {$data['row_id']} LIMIT 1");
    }
}
?>