<?php

// Force error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle static image files
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($requestUri, '/images/') === 0) {
    $imagePath = __DIR__ . '/../src' . $requestUri;
    
    // If file exists, serve it with appropriate headers
    if (file_exists($imagePath)) {
        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $contentType = 'application/octet-stream'; // Default
        
        // Set appropriate content type based on file extension
        switch(strtolower($extension)) {
            case 'png':
                $contentType = 'image/png';
                break;
            case 'jpg':
            case 'jpeg':
                $contentType = 'image/jpeg';
                break;
            case 'svg':
                $contentType = 'image/svg+xml';
                break;
            case 'ico':
                $contentType = 'image/x-icon';
                break;
            case 'webmanifest':
                $contentType = 'application/manifest+json';
                header('Content-Disposition: inline');
                break;
        }
        
        // Cache control
        $expires = 60*60*24*30; // 30 days
        header("Pragma: public");
        header("Cache-Control: max-age=".$expires);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($imagePath));
        readfile($imagePath);
        exit;
    }
    
    // If file doesn't exist, return 404
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    echo "404 Not Found";
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Router;
use Core\Auth;
use Core\Logger;
use Core\Config;

session_start();
// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $logger = Logger::getInstance();
    $logger->error("PHP Error: {$errstr}", [
        'errno' => $errno,
        'file' => $errfile,
        'line' => $errline
    ]);
    return false;
});

// Exception handling
set_exception_handler(function($exception) {
    $logger = Logger::getInstance();
    $logger->critical("Uncaught Exception: {$exception->getMessage()}", [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});

// Initialize router
$router = new Router();

// Auth routes
$router->add('GET', '/login', 'AuthController@showLogin');
$router->add('POST', '/login', 'AuthController@login');
$router->add('GET', '/register', 'AuthController@showRegister');
$router->add('POST', '/register', 'AuthController@register');
$router->add('POST', '/logout', 'AuthController@logout');

// Dashboard routes
$router->add('GET', '/', 'DashboardController@index');

// Monitor routes
$router->add('GET', '/monitors', 'MonitorController@index');
$router->add('GET', '/monitors/add', 'MonitorController@create');
$router->add('POST', '/monitors/add', 'MonitorController@store');
$router->add('GET', '/monitors/{id}', 'MonitorController@show');
$router->add('GET', '/monitors/{id}/edit', 'MonitorController@edit');
$router->add('POST', '/monitors/{id}/edit', 'MonitorController@update');
$router->add('POST', '/monitors/{id}/delete', 'MonitorController@delete');

// Status page routes
$router->add('GET', '/status-pages', 'StatusPageController@index');
$router->add('GET', '/status-pages/add', 'StatusPageController@create');
$router->add('POST', '/status-pages/add', 'StatusPageController@store');
$router->add('GET', '/status-pages/{id}/edit', 'StatusPageController@edit');
$router->add('POST', '/status-pages/{id}/edit', 'StatusPageController@update');
$router->add('GET', '/status/{slug}', 'StatusPageController@show');

// Handle the request
$router->handle($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));