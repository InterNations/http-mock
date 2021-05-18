<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Request\SerializableRequest;
use InterNations\Component\HttpMock\Server\ServerApplication;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use function file_exists;
use function getenv;
use function implode;
use function is_file;
use function php_sapi_name;
use function preg_replace;
use function sprintf;

$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaderFound = false;

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    throw new RuntimeException(
        sprintf('Could not locate autoloader file. Tried "%s"', implode('", "', $autoloadFiles))
    );
}

Request::setFactory(static fn (...$args) => new SerializableRequest(...$args));
$app = new ServerApplication('prod', getenv('HTTP_MOCK_TESTSUITE') === 'true');
$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();
$app->terminate($request, $response);
