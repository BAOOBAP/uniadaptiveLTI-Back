<?php

namespace App\Services;

use App\Repositories\LtiInstanceRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HandleRequestService
{
    protected LtiInstanceRepository $ltiRepository;

    protected TokenService $tokenService;
    protected ResponseService $responseService;

    public function __construct(LtiInstanceRepository $ltiRepository, TokenService $tokenService, ResponseService $responseService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->tokenService = $tokenService;
        $this->responseService = $responseService;
    }

    public function handleRequest(Request $request, callable $callback)
    {
        $response = $this->responseService->generateErrorResponse(!isset($request->token) || !$this->tokenService->checkToken($request->token), 'INVALID_OR_EXPIRED_TOKEN');
        if ($response == null) {
            $response = $callback($request);
        }
        return $response;
    }

    public function handleProxySettings()
    {
        if (env('APP_PROXY') != '') {
            $proxy = env('APP_PROXY');
            if (strpos($proxy, 'http://') === false && strpos($proxy, 'https://') === false) {
                $proxy = 'https://' . $proxy;
            }
            $serverName = str_replace(['http://', 'https://'], '', $proxy);
            $_SERVER['SERVER_NAME'] = $serverName;
            $_SERVER['SERVER_PORT'] = env('APP_PROXY_PORT');
            if (strpos($proxy, 'https://') === 0) {
                $_SERVER['HTTPS'] = 'on';
            }
        }
    }
}
