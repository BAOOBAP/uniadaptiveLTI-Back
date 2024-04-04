<?php

namespace App\Services;

use App\Repositories\CourseInstanceRepository;
use App\Repositories\LtiInstanceRepository;
use App\Repositories\MapInstanceRepository;
use App\Repositories\VersionInstanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionService
{
    private LtiInstanceRepository $ltiRepository;
    private CourseInstanceRepository $courseRepository;
    private MapInstanceRepository $mapRepository;
    private VersionInstanceRepository $versionRepository;
    private RegisterLogService $registerLogService;
    private ResponseService $responseService;
    private HandleRequestService $handleService;

    public function __construct(LtiInstanceRepository $ltiRepository, CourseInstanceRepository $courseRepository, MapInstanceRepository $mapRepository, VersionInstanceRepository $versionRepository, RegisterLogService $registerLogService, ResponseService $responseService, HandleRequestService $handleService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->courseRepository = $courseRepository;
        $this->mapRepository = $mapRepository;
        $this->versionRepository = $versionRepository;
        $this->registerLogService = $registerLogService;
        $this->responseService = $responseService;
        $this->handleService = $handleService;
    }

    /**
     * This function obtains data from a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getVersion(Request $request): JsonResponse
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $response = $this->responseService->generateErrorResponse(!isset($request->version_id), 'INVALID_VERSION');
            if ($response == null) {
                $dataVersion = $this->versionRepository->getVersionByCreatedId($request->version_id);
                $response = $this->responseService->generateErrorResponse(!isset($dataVersion->blocks_data), 'INVALID_BLOCKS_DATA');
                if ($response == null) {
                    $dataVersion->blocks_data = json_decode($dataVersion->blocks_data);
                    $response = response()->json($this->responseService->response($dataVersion));
                }
            }
            return $response;
        });
    }

    /**
     * This function obtains data from a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getVersions(Request $request): JsonResponse
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $mapId = $this->mapRepository->getMapIdByCreatedId($request->map_id);
            // dd($mapId);
            $dataVersions = $this->versionRepository->getVersionsByMapId($mapId->id);

            $response = $this->responseService->generateErrorResponse($dataVersions == null, 'INVALID_VERSION');
            if ($response == null) {
                $response = response()->json($this->responseService->response($dataVersions->toArray()));
            }
            return $response;
        });
    }

    /**
     * This function add a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function addVersion(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            try {
                $response = null;
                $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
                $version = $request->version;
                $dataMap = $this->mapRepository->getMapByCreatedId($request->map_id);
                $this->versionRepository->createVersion($version['id'], $dataMap->id, $version['name'], boolval($version['default']), json_encode($version['blocks_data']));
                $this->registerLogService->registerLog('addVersion', $sessionData);
                $response = response()->json($this->responseService->response());
            } catch (\Exception $e) {
                $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
            }
            return $response;
        });
    }

    /**
     * This function saves a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function storeVersion(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {

            $response = $this->responseService->generateErrorResponse(!isset($request->saveData), 'INVALID_DATA');
            if ($response == null) {
                try {
                    $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
                    $this->registerLogService->registerLog('storeVersion', $sessionData);
                    $saveData = $request->saveData;
                    $course = $this->courseRepository->getCourseByInstanceCourseId($saveData['instance_id'], $saveData['course_id']);

                    $mapData = $saveData['map'];
                    $map = $this->mapRepository->createOrUpdate($mapData, $course, $saveData['user_id']);

                    $versionsData = $mapData['versions'];
                    // dd($versionsData);
                    foreach ($versionsData as $versionData) {
                        $this->versionRepository->createOrUpdate($versionData['id'], $map->id, $versionData['name'], boolval($versionData['default']), json_encode($versionData['blocks_data']));
                    }
                    $response = response()->json($this->responseService->response());
                } catch (\Exception $e) {
                    // dd($e->getMessage());
                    abort($e->getCode(), $e->getMessage());
                    // dd($e->getMessage());
                    $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                }
            }
            return $response;
        });
    }

    /**
     * This function deletes a version of a map.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function deleteVersion(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $response = null;
            try {
                $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
                $this->versionRepository->deleteVersionByCreatedId($request->id);
                $this->registerLogService->registerLog('deleteVersion', $sessionData);
                $response = response()->json($this->responseService->response());
            } catch (\Exception $e) {
                error_log($e);
                $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
            }
            return $response;
        });
    }
}
