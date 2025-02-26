<?php

// Force error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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