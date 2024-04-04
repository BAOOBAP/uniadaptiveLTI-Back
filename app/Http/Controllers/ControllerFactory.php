<?php

namespace App\Http\Controllers;

use Exception;

class ControllerFactory extends Controller
{
    public static function create(string $platform)
    {
        $controllerClass = ucfirst($platform) . 'Controller';
        $controllerNamespace = 'App\Http\Controllers\\';

        if (class_exists($controllerNamespace . $controllerClass)) {
            return app($controllerNamespace . $controllerClass);
        } else {
            throw new Exception('PLATFORM_NOT_SUPPORTED');
        }
    }
}
