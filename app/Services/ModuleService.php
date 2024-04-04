<?php

namespace App\Services;

use App\Http\Controllers\ControllerFactory;
use App\Repositories\LtiInstanceRepository;
use Exception;
use Illuminate\Http\Request;

class ModuleService
{
    private LtiInstanceRepository $ltiRepository;
    private ResponseService $responseService;
    private HandleRequestService $handleService;

    public function __construct(LtiInstanceRepository $ltiRepository, ResponseService $responseService, HandleRequestService $handleService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->responseService = $responseService;
        $this->handleService = $handleService;
    }

    /**
     * Function that returns the modules with a specific type of a course.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getModulesByType(Request $request)
    {
        // header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));
        return $this->handleService->handleRequest($request, function ($request) {
            $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
            $response = $this->responseService->generateErrorResponse(!isset($sessionData->tool_consumer_info_product_family_code), 'INVALID_SESSION_DATA');
            if ($response == null) {
                try {
                    $controller = ControllerFactory::create($sessionData->tool_consumer_info_product_family_code);

                    if (isset($request->type)) {
                        if ($request->type == 'unsupported') {
                            $response = $controller->getModulesNotSupported($request, $sessionData);
                        } else {
                            $response = $controller->getDataModulesByType($request, $sessionData);
                        }
                    } else {
                        $response = response()->json($this->responseService->errorResponse(null, 'TYPE_NOT_FOUND'), 500);
                    }
                } catch (Exception $e) {
                    $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                }
            }
            return $response;
        });
    }
}
