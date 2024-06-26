<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app.php';

class DeleteUsersTest extends TestCase
{
    private $_slim;
    private $_mysqli;
    private $_userId;

    public function testActionDeleteLoans()
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

        $mysqli->query("DELETE FROM users WHERE id = $userId");
    }


    private function correctTest()
    {
        $request = new ServerRequest(
            'DELETE',
            "/users/{$this->_userId}",
            ['Content-Type' => 'application/json'],
            null,
            '1.1',
            ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->_slim->app->handle($request);
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data);
        $this->assertTrue($data['status']);
    }
}
?>
