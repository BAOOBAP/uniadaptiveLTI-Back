<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;
use Error;

class SystemInfoService
{
    private ResponseService $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

    /**
     * This function obtains the current date and time.
     * 
     * @return array
     */
    public function getDate()
    {
        return response()->json($this->responseService->response(date('Y-m-d\TH:i')));
    }

    /**
     * This function authenticates the user as administrator.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function auth(Request $request)
    {
        // header('Access-Control-Allow-Origin: *');
        $response = null;
        $parameter = $request->password;
        if (null !== env('ADMIN_PASSWORD')) {
            $adminPassword = env('ADMIN_PASSWORD');
            try {
                if ($adminPassword == $parameter) {
                    $response = response()->json($this->responseService->response());
                } else {
                    $response = response()->json($this->responseService->errorResponse(null, 'INVALID_PASSWORD'), 500);
                }
            } catch (Error $e) {
                $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
            }
        } else {
            $response = response()->json($this->responseService->response());
        }

        return $response;
    }

    public function getResource()
    {
        try {
            return response()->json($this->responseService->response(getrusage()));
        } catch (\Exception $e) {
            return response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
        }
    }

    public function getServerInfo()
    {
        try {
            return response()->json($this->responseService->response(Carbon::createFromTimestamp(filemtime('/proc/uptime'))->toDateTimeString()));
        } catch (\Exception $e) {
            return response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
        }
    }

    public function getConfig()
    {
        $configFilePaths = [
            base_path('/config/frontendConfiguration.json'),
            base_path('/config/frontendDefaultConfiguration.json'),
        ];

        foreach ($configFilePaths as $filePath) {
            if (File::exists($filePath)) {
                $content = File::get($filePath);
                $json = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $json;
                }
            }
        }

        return null;
    }

    public function setConfig(array $settings)
    {
        $password = $settings['password'];
        $json = $settings['settings'];

        if ($password == env('ADMIN_PASSWORD')) {
            $configFilePath = base_path('/config/frontendConfiguration.json');

            if (File::put($configFilePath, json_encode($json, true)) !== false) {
                return response()->json($this->responseService->response());
            }
        }

        return response()->json($this->responseService->errorResponse(null, 'FAILURE_CHANGE_CONFIG'), 500);
    }

    public function ping(Request $request)
    {
        if ($request->has('ping')) {
            return ['data' => 'pong'];
        }
    }
}
