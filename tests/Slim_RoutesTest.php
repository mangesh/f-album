<?php
include __DIR__ .'/../public/inc/helper-functions.php';
require_once __DIR__ . '/../lib/vendor/autoload.php';

error_reporting(E_ALL);
ini_set("display_errors", 1);
set_time_limit(0);

use Slim\Environment;

class RoutesTest extends PHPUnit_Framework_TestCase
{
    public function request($method, $path, $options = array())
    {
        // Capture STDOUT
        ob_start();
        // Prepare a mock environment
        Environment::mock(array_merge(array(
            'REQUEST_METHOD' => $method,
            'PATH_INFO' => $path,
            'SERVER_NAME' => 'fb.dev',
        ), $options));
        $app = new \Slim\Slim();
        $this->app = $app;
        $this->request = $app->request();
        $this->response = $app->response();
        // Return STDOUT
        return ob_get_clean();
    }
    public function get($path, $options = array())
    {
        $this->request('GET', $path, $options);
    }
    public function testIndex()
    {
        $this->get('/');
        $this->assertEquals('200', $this->response->status());
    }
}