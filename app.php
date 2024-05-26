<?php

/**
 * @category App
 * @package  App
 * @author   Icesage <crescenti400@gmail.com>
 * @license  https://opensource.org/licenses/MIT MIT
 * @version  PHP 7.4
 * @link     https://github.com/Icesage01/LoanManagementAPI
 * Slim 4 based REST API Application
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Slim\Exception\HttpException;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

require __DIR__ . '/vendor/autoload.php';

class RestAPI
{

    /**
     * AppFactory::create object
     */
    public $app;

    /**
     * Mysqli object
     */
    private $_mysqli;

    /**
     * Logger object
     */
    private $_accessLogger;

    /**
     * Logger object
     */
    private $_errorLogger;

    /**
     * Create Slim4 App And mysqli instantiation 
     * And setting up all routes
     * Also configuring errorHandler, saving messages in logfiles
     * Default: logs/error.log | logs/accept.log
     * 
     * @return void
     */
    public function __construct()
    {
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();

        $errorHandler = function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) use ($app) {
            if ($this->_errorLogger) {
                $message = $exception->getMessage();
                $message .= " PAGE: {$_SERVER['REQUEST_URI']}.";
                $message .= " REMOTE: {$_SERVER['REMOTE_ADDR']}";

                $this->_errorLogger->error($message);
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
                ->withAddedHeader('Content-type', 'application/json')
                ->withStatus($exception->getCode());
        };

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
        
        $config = @parse_ini_file('config.ini', true);

        $datetime = "Y-m-d H:i:s";
        $output = "[%datetime%][%level_name%] %message% %context% %extra%\n";
        $logFormatter = new LineFormatter($output, $datetime);
        $accessLogger = new Logger('access');
        $accessLogger->pushHandler(
            (new RotatingFileHandler(
                $config['logs']['access_log'] ?? './logs/access.log',
                7,
                Logger::INFO,
                true,
                0666
            ))->setFormatter($logFormatter)
        );
        $this->_accessLogger = $accessLogger;

        $errorLogger = new Logger('errors');
        $errorLogger->pushHandler(
            (new RotatingFileHandler(
                $config['logs']['error_log'] ?? './logs/error.log',
                7,
                Logger::ERROR,
                true,
                0666
            ))->setFormatter($logFormatter)
        );
        $this->_errorLogger = $errorLogger;

        try {
            if (!isset($config['mysql'])) {
                throw new Exception('Can\'t find [mysql] header inside INI file');
            }
            $db = $config['mysql'];
            $mysqli = new mysqli(
                $db['host'] ?? '',
                $db['user'] ?? '',
                $db['pass'] ?? '',
                $db['database'] ?? '',
                $db['port'] ?? 3306
            );
            if ($mysqli->connect_error) {
                $errorLogger->error('MySQL connect error: ' . $e->getMessage());
            }
            $this->_mysqli = $mysqli;
        } catch (Exception $e) {
            $errorLogger->error($e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            die(
                json_encode(
                    [
                    'status' => false,
                    'message' => 'internal error was raised'
                    ], 448
                )
            );
        }

        // Add to every response JSON header and logger
        $middleware = function ($request, $handler) {
            $response = $handler->handle($request);
            if ($response->getStatusCode() < 400) {
                $message = $_SERVER['REMOTE_ADDR'];
                $message .= " - {$_SERVER['REQUEST_METHOD']}";
                $message .= " {$_SERVER['REQUEST_URI']}";
                $this->_accessLogger->info(
                    $message,
                    $request->getParsedBody(),
                    $response->getBody()
                );
            }
            return $response->withAddedHeader('Content-type', 'application/json');
        };
        $app->add($middleware);

        // API Endpoints
        $app->get('/', [$this, 'actionIndex']);
        $app->get('/users', [$this, 'actionGetUsers']);
        $app->get('/loans[/{id}]', [$this, 'actionGetLoans']);
        $app->put('/loans/{id}', [$this, 'actionUpdateLoans']);
        $app->post('/loans', [$this, 'actionCreateLoan']);
        $app->delete('/loans/{id}', [$this, 'actionDeleteLoan']);

        $this->app = $app;
    }


    /**
     * Index function
     * shows the status that the API is available
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionIndex(Request $request, Response $response, $args)
    {
        $data = ['status' => true, 'message' => 'it works!'];
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response;
    }


    /**
     * Get all or specific one loan info from MySQL Table
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionGetLoans(Request $request, Response $response, $args)
    {
        $condition = (isset($args['id'])) ? 'WHERE l.id = ' . (int) $args['id'] : '';
        $sql = <<<SQL
        SELECT *
        FROM loans AS l
          LEFT JOIN users AS u
            ON l.user_id = u.id
        {$condition}
        ORDER BY l.create_time, amount
        SQL;
        $res = $this->_mysqli->query($sql);
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
        return $response;
    }


    /**
     * Get all users info
     * more useful for create/updating loans
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionGetUsers(Request $request, Response $response, $args)
    {
        $res = $this->_mysqli->query('SELECT * FROM users');
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
            throw new HttpException($request, "can't get data", 400);
        }

        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response;
    }


    /**
     * Updating specific loans by ID 
     * GET param accept loanId, POST accept only JSON
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionUpdateLoans(Request $request, Response $response, $args)
    {
        $loanId = (int) $args['id'];
        $data = $this->_getJSON($request);
        if ($data === null) {
            $responseMessage = [
            'status' => false,
            'message' => 'Invalid JSON format'
            ];
            $response->getBody()->write(json_encode($responseMessage, 448));
            return $response->withStatus(400);
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

        $sql = "UPDATE loans SET {$values} WHERE id = {$loanId} LIMIT 1";
        $status = false;
        try {
            $res = @$this->_mysqli->query($sql);
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
        return $response;
    }


    /**
     * Create new loan with specified data
     * Accept only JSON
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionCreateLoan(Request $request, Response $response, $args)
    {
        $data = $this->_getJSON($request);
        if ($data === null) {
            $responseMessage = [
            'status' => false,
            'message' => 'Invalid JSON format'
            ];
            $response->getBody()->write(json_encode($responseMessage, 448));
            return $response->withStatus(400);
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

        $sql = "INSERT INTO loans (user_id, amount, pay_time) VALUES ($values)";
        $res = $this->_mysqli->query($sql);
        if ($res) {
            $responseMessage = [
            'status' => true,
            'message' => 'success',
            'row_id' => $this->_mysqli->insert_id
            ];
        } else {
            $responseMessage = [
            'status' => false,
            'message' => 'new loan create was failed!'
            ];
        }
        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response;
    }


    /**
     * Delete loan by specified ID
     * 
     * @param Request  $request  from Psr\Http\Message\ServerRequestInterface
     * @param Response $response from Psr\Http\Message\ResponseInterface
     * @param array    $args     $_GET data
     * 
     * @return Response $response
     */
    public function actionDeleteLoan(Request $request, Response $response, $args)
    {
        $loanId = (int) $args['id'];
        try {
            $this->_mysqli->query("DELETE FROM loans WHERE id = {$loanId} LIMIT 1");
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

    /**
     * Get JSON information from request or php://input
     * 
     * @param Request $request from Psr\Http\Message\ServerRequestInterface
     * 
     * @return array|null
     */
    private function _getJSON(Request $request = null): ?array
    {
        $result = null;
        if (gettype($request) == 'object'
            && method_exists($request, 'getParsedBody')
        ) {
            $result = $request->getParsedBody();
        }
        if (empty($result)) {
            $result = json_decode(file_get_contents('php://input'), true);
        }

        // Проверяем, что данные были отправлены в формате JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result = null;
        }
        return $result;
    }
}

?>
