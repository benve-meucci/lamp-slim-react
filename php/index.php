<?php
use Slim\Factory\AppFactory;
use Slim\Exception\HttpException;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/BankController.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$corsOrigin = getenv('CORS_ORIGIN') ?: '*';
$app->add(function (Request $request, $handler) use ($corsOrigin) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', $corsOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->options('/', function (Request $request, Response $response) {
    return $response;
});

$app->options('/api/', function (Request $request, Response $response) {
    return $response;
});

$app->options('/accounts/{routes:.*}', function (Request $request, Response $response) {
    return $response;
});

$app->options('/api/accounts/{routes:.*}', function (Request $request, Response $response) {
    return $response;
});

$bank = new BankController();

$app->get('/up', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("OK");
    return $response;
});

function bankRoutes($app, BankController $bank, string $prefix): void
{
    $app->get($prefix . '/', [$bank, 'index']);
    $app->get($prefix . '/accounts/{id}/transactions', [$bank, 'listTransactions']);
    $app->get($prefix . '/accounts/{id}/transactions/{transaction_id}', [$bank, 'showTransaction']);
    $app->post($prefix . '/accounts/{id}/deposits', [$bank, 'createDeposit']);
    $app->post($prefix . '/accounts/{id}/withdrawals', [$bank, 'createWithdrawal']);
    $app->put($prefix . '/accounts/{id}/transactions/{transaction_id}', [$bank, 'updateTransaction']);
    $app->delete($prefix . '/accounts/{id}/transactions/{transaction_id}', [$bank, 'deleteTransaction']);
    $app->get($prefix . '/accounts/{id}/balance', [$bank, 'balance']);
    $app->get($prefix . '/accounts/{id}/balance/convert/fiat', [$bank, 'convertFiat']);
    $app->get($prefix . '/accounts/{id}/balance/convert/crypto', [$bank, 'convertCrypto']);
}

bankRoutes($app, $bank, '');
bankRoutes($app, $bank, '/api');

$displayErrors = (getenv('APP_ENV') ?: 'development') !== 'production';
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app, $corsOrigin) {
    $statusCode = $exception instanceof HttpException ? $exception->getCode() : 500;
    $message = $statusCode === 500 ? 'Internal server error' : $exception->getMessage();

    $response = $app->getResponseFactory()->createResponse($statusCode);
    $response->getBody()->write(json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', $corsOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->run();
