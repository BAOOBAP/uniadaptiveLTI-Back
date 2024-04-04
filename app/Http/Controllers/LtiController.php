<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Services\ApiRequestService;
use App\Services\HandleRequestService;
use App\Services\LtiSessionService;
use App\Services\MapService;
use App\Services\ModuleService;
use App\Services\MoodleApiService;
use App\Services\RegisterLogService;
use App\Services\ResponseService;
use App\Services\SystemInfoService;
use App\Services\TokenService;
use App\Services\VersionService;

class LtiController extends Controller
{
    protected ResponseService $responseService;
    protected ApiRequestService $apiService;
    protected TokenService $tokenService;
    protected RegisterLogService $registerLogService;
    protected LtiSessionService $sessionService;
    protected SystemInfoService $systemInfoService;
    protected MapService $mapService;
    protected VersionService $versionService;
    protected ModuleService $moduleService;
    protected MoodleApiService $moodleApiService;
    protected HandleRequestService $handleService;

    public function __construct(
        ResponseService $responseService,
        ApiRequestService $apiService,
        TokenService $tokenService,
        RegisterLogService $registerLogService,
        LtiSessionService $sessionService,
        SystemInfoService $systemInfoService,
        MapService $mapService,
        VersionService $versionService,
        ModuleService $moduleService,
        MoodleApiService $moodleApiService,
        HandleRequestService $handleService

    ) {
        $this->responseService = $responseService;
        $this->apiService = $apiService;
        $this->tokenService = $tokenService;
        $this->registerLogService = $registerLogService;
        $this->sessionService = $sessionService;
        $this->systemInfoService = $systemInfoService;
        $this->mapService = $mapService;
        $this->versionService = $versionService;
        $this->moduleService = $moduleService;
        $this->moodleApiService = $moodleApiService;
        $this->handleService = $handleService;
    }

    public function getJWKS()
    {
        return $this->sessionService->getJWKS();
    }

    public function saveSession()
    {
        return $this->sessionService->saveSession();
    }

    public function getSession(Request $request)
    {
        return $this->sessionService->getSession($request);
    }

    public function getModules(Request $request)
    {
        return $this->apiService->getModules($request);
    }

    public function exportVersion(Request $request)
    {
        return $this->apiService->exportVersion($request);
    }

    public function auth(Request $request)
    {
        return $this->systemInfoService->auth($request);
    }

    public function getDate()
    {
        return $this->systemInfoService->getDate();
    }

    public function getResource()
    {
        return $this->systemInfoService->getResource();
    }

    public function getServerInfo()
    {
        return $this->systemInfoService->getServerInfo();
    }

    public function getConfig()
    {
        return $this->systemInfoService->getConfig();
    }

    public function setConfig(Request $request)
    {
        return $this->systemInfoService->setConfig($request->all());
    }

    public function ping(Request $request)
    {
        return $this->systemInfoService->ping($request);
    }

    public function getMap(Request $request)
    {
        return $this->mapService->getMap($request);
    }

    public function deleteMap(Request $request)
    {
        return $this->mapService->deleteMap($request);
    }

    public function getVersions(Request $request)
    {
        return $this->versionService->getVersions($request);
    }

    public function getVersion(Request $request)
    {
        return $this->versionService->getVersion($request);
    }

    public function storeVersion(Request $request)
    {
        return $this->versionService->storeVersion($request);
    }

    public function addVersion(Request $request)
    {
        return $this->versionService->addVersion($request);
    }

    public function deleteVersion(Request $request)
    {
        return $this->versionService->deleteVersion($request);
    }

    public function getModulesByType(Request $request)
    {
        return $this->moduleService->getModulesByType($request);
    }
}
