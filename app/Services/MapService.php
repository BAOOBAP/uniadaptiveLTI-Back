<?php

namespace App\Services;

use App\Repositories\LtiInstanceRepository;
use App\Repositories\MapInstanceRepository;
use Illuminate\Http\Request;

class MapService
{
    private LtiInstanceRepository $ltiRepository;
    private MapInstanceRepository $mapRepository;
    private RegisterLogService $registerLogService;
    private ResponseService $responseService;
    private HandleRequestService $handleService;

    public function __construct(LtiInstanceRepository $ltiRepository, MapInstanceRepository $mapRepository, RegisterLogService $registerLogService, ResponseService $responseService, HandleRequestService $handleService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->mapRepository = $mapRepository;
        $this->registerLogService = $registerLogService;
        $this->responseService = $responseService;
        $this->handleService = $handleService;
    }

    /**
     * This function obtains data from a map.
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function getMap(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $dataMap = $this->mapRepository->getMapByCreatedId($request->map_id);

            $response = $this->responseService->generateErrorResponse($dataMap == null, 'INVALID_MAP');
            if ($response == null) {
                $response = response()->json($this->responseService->response($dataMap));
            }
            return $response;
        });
    }

    /**
     * Delete a map by its created ID.
     *
     * @param int $createdId
     * @param object $sessionData
     * @return array
     */
    public function deleteMapByCreatedId(int $createdId, object $sessionData)
    {
        $response = null;
        try {
            // Eliminar el mapa utilizando el repositorio
            $this->mapRepository->deleteMapByCreatedId($createdId);

            // Registrar el evento en el servicio de registro de log
            $this->registerLogService->registerLog('deleteMap', $sessionData);

            // Devolver una respuesta exitosa
            $response = $this->responseService->response();
        } catch (\Exception $e) {
            $response = $this->responseService->errorResponse(null, $e->getMessage(), $e->getCode());
        }
        return $response;
    }

    public function getMapByCreatedId(string $createdId)
    {
        return $this->mapRepository->getMapByCreatedId($createdId);
    }

    /**
     * This function deletes a map.
     * 
     * @param Request $request
     * @return array
     */
    public function deleteMap(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $response = null;
            try {
                // Obtén información de sesión
                $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);

                // Elimina el mapa utilizando el servicio MapService
                $this->deleteMapByCreatedId($request->id, $sessionData);

                // Devuelve una respuesta exitosa
                $response = response()->json($this->responseService->response());
            } catch (\Exception $e) {
                error_log($e);
                $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
            }
            return $response;
        });
    }




    // Otros métodos similares para otras operaciones con mapas
}
