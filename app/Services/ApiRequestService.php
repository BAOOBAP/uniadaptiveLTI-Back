<?php

namespace App\Services;

use App\Http\Controllers\ControllerFactory;
use App\Repositories\LtiInstanceRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiRequestService
{

    protected LtiInstanceRepository $ltiRepository;

    protected HandleRequestService $handleService;
    protected ResponseService $responseService;
    protected RegisterLogService $registerLogService;

    public function __construct(LtiInstanceRepository $ltiRepository, HandleRequestService $handleService, ResponseService $responseService, RegisterLogService $registerLogService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->handleService = $handleService;
        $this->responseService = $responseService;
        $this->registerLogService = $registerLogService;
    }

    public function getModules(Request $request): JsonResponse
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
            $response = $this->responseService->generateErrorResponse(!isset($sessionData->tool_consumer_info_product_family_code) || !isset($sessionData->platform_id) || !isset($sessionData->context_id), 'INVALID_SESSION_DATA');
            if ($response == null) {
                try {
                    $controller = ControllerFactory::create($sessionData->tool_consumer_info_product_family_code);
                    if ($sessionData->tool_consumer_info_product_family_code == 'moodle') {
                        // dd($controller->getModules($sessionData->platform_id, $sessionData->context_id));
                        $response = $controller->getDataModules($sessionData->platform_id, $sessionData->context_id);
                    } elseif ($sessionData->tool_consumer_info_product_family_code == 'sakai' && isset($request->lessonId) && isset($request->session_id)) {
                        $response = $controller->getDataModules($sessionData->platform_id, $request->lessonId, $sessionData->session_id, $sessionData->context_id);
                    } else {
                        $response = response()->json($this->responseService->errorResponse(null, 'LESSON_NOT_VALID'), 500);
                    }
                } catch (Exception $e) {
                    $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                }
            }
            return $response;
        });
    }

    /**
     * This function exports a version of a map to a course of a learning platform.
     * 
     * @param Request $request
     * 
     * @return array
     */

    public function exportVersion(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $response = null;
            $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
            $platform = $sessionData->tool_consumer_info_product_family_code;

            $controller = ControllerFactory::create($platform);
            $this->registerLogService->registerLog('exportVersion', $sessionData);
            switch ($platform) {
                case 'moodle':
                    $response = $controller->exportVersionMoodle($request);
                    break;
                case 'sakai':
                    $response = $controller->exportVersionSakai($request, $sessionData);
                    break;
                default:
                    $response = response()->json($this->responseService->errorResponse(null, 'PLATFORM_NOT_SUPPORTED'), 500);
                    break;
            }
            return $response;
        });
    }
}
