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

        $this->_initializeLoggers();
        $this->_initializeDatabase();
        $this->_initializeErrorHandler($app);

        $app->add([$this, 'addJsonHeaderMiddleware']);
        $this->_registerRoutes($app);

        $this->app = $app;
    }


    /**
     * Main routes of application
     * 
     * @param object $app Slim\App
     * 
     * @return void
     */
    private function _registerRoutes($app)
    {
        $app->get('/', [$this, 'actionIndex']);
        $app->get('/users', [$this, 'actionGetUsers']);
        $app->get('/loans[/{id}]', [$this, 'actionGetLoans']);
        $app->put('/loans/{id}', [$this, 'actionUpdateLoans']);
        $app->post('/loans', [$this, 'actionCreateLoan']);
        $app->delete('/loans/{id}', [$this, 'actionDeleteLoan']);
    }


    /**
     * Initialize accept and error logs
     * output files can be configured in config.ini
     * inside [logs] header
     * 
     * @return void
     */
    private function _initializeLoggers(): void
    {
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
    }


    /**
     * Initialize MySQL connect
     * and save it to class property
     * 
     * @return void
     */
    private function _initializeDatabase()
    {
        $config = @parse_ini_file('config.ini', true);
        if (!isset($config['mysql'])) {
            $this->_logAndTerminate('[mysql] header is missed in INI file', 500);
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
            $this->_errorLogger->error(
                'MySQL connect error: '
                . $mysqli->connect_error
            );
            $this->_logAndTerminate('Internal error was raised', 500);
        }

        $this->_mysqli = $mysqli;
    }


    /** 
     * Custom error handler
     * catch all errors during execution
     * then write them in logfile
     * and return JSON answer to client
     * 
     * @param object $app Rest\App
     * 
     * @return void
     */
    private function _initializeErrorHandler($app)
    {
        $errorHandler = function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) use ($app) {
            $this->_errorLogger->error(
                $exception->getMessage(), [
                'page' => $request->getUri()->getPath(),
                'remote' => $request->getServerParams()['REMOTE_ADDR']
                ]
            );

            $payload = [
                'status' => false,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode()
            ];

            $response = $app->getResponseFactory()->createResponse();
            $response->getBody()->write(
                json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                )
            );

            return $response
                ->withHeader('Content-type', 'application/json')
                ->withStatus($exception->getCode());
        };

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }


    /**
     * Catch all requests to API
     * and write them to logfile, if errors not occured
     * 
     * @param Request $request from Psr\Http\Message\ServerRequestInterface
     * @param object  $handler from Psr\Http\Message\ResponseInterface
     * 
     * @return void
     */
    public function addJsonHeaderMiddleware($request, $handler)
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() < 400) {
            $message = $_SERVER['REMOTE_ADDR'];
            $message .= " - {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}";
            $this->_accessLogger->info(
                $message, [
                'body' => $request->getParsedBody(),
                'response' => (string)$response->getBody()
                ]
            );
        }
        return $response->withHeader('Content-type', 'application/json');
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
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
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
        $sql = "SELECT * FROM loans AS l LEFT JOIN users AS u ON l.user_id = u.id"
             . " {$condition} ORDER BY l.create_time, amount";

        $res = $this->_mysqli->query($sql);
        $responseMessage = ['status' => false, 'message' => 'empty data'];

        if ($res && $res->num_rows > 0) {
            $details = [];
            while ($row = $res->fetch_assoc()) {
                $details[] = $row;
            }
            $responseMessage = [
                'status' => true,
                'message' => 'Data retrieved successfully',
                'details' => $details
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
        $responseMessage = ['status' => false, 'message' => 'Can\'t get data'];

        if ($res && $res->num_rows > 0) {
            $details = [];
            while ($row = $res->fetch_assoc()) {
                $details[] = $row;
            }
            $responseMessage = [
                'status' => true,
                'message' => 'Data retrieved successfully',
                'details' => $details
            ];
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
            return $this->respondWithError($response, 'Invalid JSON format', 400);
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

        if (empty($values)) {
            return $this->respondWithError(
                $response,
                'No valid parameters provided',
                400
            );
        }

        $values = join(',', $values);

        $sql = "UPDATE loans SET {$values} WHERE id = {$loanId} LIMIT 1";
        $status = false;
        try {
            $res = @$this->_mysqli->query($sql);
            $responseMessage = [
                'status'  => true,
                'message' => 'Loan was successfully updated!',
                'code'    => 200
            ];
        } catch (Exception $e) {
            $responseMessage = [
                'status'  => false,
                'message' => $e->getCode() == 1452 
                             ? 'Specified user_id does not exist!'
                             : 'Internal error was raised',
                'code'    => $e->getCode() == 1452 ? 400 : 500
            ];
        }

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
            return $this->respondWithError($response, 'Invalid JSON format', 400);
        }
        $requiredParams = ['user_id', 'amount', 'pay_time'];
        $values = [];
        foreach ($requiredParams as $param) {
            if (!isset($data[$param])) {
                return $this->respondWithError(
                    $response,
                    "Param \"{$param}\" is required!",
                    400
                );
            }

            $values[] = (int) $data[$param];
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
                'message' => 'Failed to create new loan'
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


    /**
     * Tranform string to JSON,
     * write it to response body and return with specified code
     * 
     * @param Response $response   from Psr\Http\Message\ResponseInterface
     * @param string   $message    message that will be outputed to user
     * @param int      $statusCode http code
     * 
     * @return Response
     */
    private function _respondWithError(
        Response $response,
        string $message,
        int $statusCode
    ): Response {
        $responseMessage = ['status' => false, 'message' => $message];
        $response->getBody()->write(json_encode($responseMessage, 448));
        return $response->withStatus($statusCode);
    }


    /**
     * If exception was raised or etc
     * BEFORE error collector initalize
     * then will write error to log and return code
     * 
     * @param string $message    message to output for user
     * @param int    $statusCode exit code
     * 
     * @return void
     */
    private function _logAndTerminate(string $message, int $statusCode): void
    {
        $this->_errorLogger->error($message);
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['status' => false, 'message' => $message], 448));
    }
}

?>
