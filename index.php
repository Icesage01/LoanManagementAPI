<?php

/**
 * @category App
 * @package  App
 * @author   Icesage <crescenti400@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @version  7.4
 * @link     test.com
 * Slim 4 based REST API Application
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// Обработчик ошибок
$errorHandler = function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    ?LoggerInterface $logger = null
) use ($app) {
    if ($logger) {
        $logger->error($exception->getMessage());
    }
    
    $payload = [
        'status' => false,
        'message' => $exception->getMessage(),
        'code' => $exception->getCode()
    ];
    
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(
        json_encode($payload, JSON_UNESCAPED_UNICODE)
    );

    return $response
            ->withAddedHeader('Content-type', 'application/json');
};
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

$app->get(
    '/', function (Request $request, Response $response, $args) {
        $data = ['status' => true, 'message' => 'it works!'];
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

$app->get(
    '/users', function (Request $request, Response $response, $args) {
        $mysqli = mysql_connect();
        $res = $mysqli->query('SELECT * FROM users');
        if ($res && $res->num_rows > 0) {
            $details = [];
            while ($row = $res->fetch_assoc()) {
                $details[] = $row;
            }
            $responseMessage = [
            'status' => true,
            'message' => 'data got successfull',
            'details' => $details
            ];
        } else {
            $responseMessage = [
            'status' => false,
            'message' => 'can\'t get data'
            ];
        }

        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

$app->get(
    '/loans[/{id}]', function (Request $request, Response $response, $args) {
        $mysqli = mysql_connect();
        $condition = (isset($args['id'])) ? 'WHERE l.id = ' . (int) $args['id'] : '';
        $sql = <<<SQL
    SELECT *
    FROM loans AS l
      LEFT JOIN users AS u
        ON l.user_id = u.id
    {$condition}
    ORDER BY l.create_time, amount
    SQL;
        $res = $mysqli->query($sql);
        if ($res && $res->num_rows > 0) {
            $details = [];
            while ($row = $res->fetch_assoc()) {
                $details[] = $row;
            }
            $responseMessage = [
            'status' => true,
            'message' => 'data got successfull',
            'details' => $details
            ];
        } else {
            $responseMessage = [
            'status' => false,
            'message' => 'can\'t get data by specified data'
            ];
        }

        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

$app->put(
    '/loans/{id}', function (Request $request, Response $response, $args) {
        $loanId = (int) $args['id'];
        $data = getJSON($request);
        if ($data === null) {
            $responseMessage = [
            'status' => false,
            'message' => 'Invalid JSON format'
            ];
            $response->getBody()->write(json_encode($responseMessage, 448));
            return $response
                ->withStatus(400)
                ->withAddedHeader('Content-type', 'application/json');
        }

        $validParams = ['user_id', 'amount', 'create_time', 'pay_time'];
        $values = [];
        foreach ($validParams as $param) {
            if (!isset($data[$param])) {
                continue;
            }

            settype($data[$param], 'int');
            $values[] = "{$param} = {$data[$param]}";
        }
        $values = join(',', $values);

        $mysqli = mysql_connect();
        $sql = "UPDATE loans SET {$values} WHERE id = {$loanId} LIMIT 1";
        $status = false;
        try {
            $res = @$mysqli->query($sql);
            $message = 'Loan was successfull updated!';
            $status = true;
        } catch (Exception $e) {
            $message = ($e->getCode() == 1452)
                    ? 'Specified user_id do not exists!'
                    : 'Internal error was raised';
        }
    
        $responseMessage = [
        'status' => $status,
        'message' => $message
        ];

        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

$app->delete(
    '/loans/{id}', function (Request $request, Response $response, $args) {
        $loanId = (int) $args['id'];
        $mysqli = mysql_connect();
        try {
            $mysqli->query("DELETE FROM loans WHERE id = {$loanId} LIMIT 1");
            $responseMessage = [
            'status' => true,
            'message' => "Loan with ID {$loanId} was successfully deleted!"
            ];
        } catch (Exception $e) {
            $responseMessage = [
            'status' => false,
            'message' => 'Internal error was raised, cant delete specified loan!'
            ];
        }
    
        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

$app->post(
    '/loans', function (Request $request, Response $response, $args) {
        $data = getJSON($request);
        if ($data === null) {
            $responseMessage = [
            'status' => false,
            'message' => 'Invalid JSON format'
            ];
            $response->getBody()->write(json_encode($responseMessage, 448));
            return $response
                ->withStatus(400)
                ->withAddedHeader('Content-type', 'application/json');
        }
        $requiredParams = ['user_id', 'amount', 'pay_time'];
        $values = [];
        foreach ($requiredParams as $param) {
            if (isset($data[$param])) {
                $values[] = (int) $data[$param];
                continue;
            }

            $responseMessage = [
            'status' => false,
            'message' => "Param \"{$param}\" is required!"
            ];
            $response->getBody()->write(json_encode($responseMessage, 448));
            return $response
                    ->withAddedHeader('Content-type', 'application/json');
        }
        $values = join(', ', $values);

        $mysqli = mysql_connect($values);
        $sql = "INSERT INTO loans (user_id, amount, pay_time) VALUES ($values)";
        $res = $mysqli->query($sql);
        if ($res) {
            $responseMessage = [
            'status' => true,
            'message' => 'success',
            'row_id' => $mysqli->insert_id
            ];
        } else {
            $responseMessage = [
            'status' => false,
            'message' => 'new loan create was failed!'
            ];
        }
        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response
                ->withAddedHeader('Content-type', 'application/json');
    }
);

/**
 * Create new mysql connect
 * 
 * @return Mysqli::class|null
 */ 
function Mysql_connect()
{
    $config = 'config.ini';
    if (!file_exists($config)) {
        die("Конфиг файл config.ini не существует!\n");
    }
    
    $config = parse_ini_file('config.ini');
    $result = null;
    try {
        $mysqli = @new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['database']
        );
        if (!$mysqli->connect_error) {
            $result = $mysqli;
        }
    } catch (Exception $e) {
    }
    return $result;
}

/**
 * Get JSON information from request or php://input
 * 
 * @param Request $request from Psr\Http\Message\ServerRequestInterface
 * 
 * @return array|null
 */
function getJSON(Request $request): ?array
{
    $result = $request->getParsedBody();
    if (empty($data)) {
        $result = json_decode(file_get_contents('php://input'), true);
    }

    // Проверяем, что данные были отправлены в формате JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result = null;
    }
    return $result;
}

$app->run();

?>
