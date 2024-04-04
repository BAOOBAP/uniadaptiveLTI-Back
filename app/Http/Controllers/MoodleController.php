<?php

namespace App\Http\Controllers;

use App\Services\MoodleApiService;
use Illuminate\Http\Request;

class MoodleController extends LtiController
{
    protected MoodleApiService $moodleApiService;

    public function __construct(
        MoodleApiService $moodleApiService,
    ) {
        $this->moodleApiService = $moodleApiService;
    }

    public function getUserSession(object $lastInserted, string $token_request)
    {
        return $this->moodleApiService->getUserSession($lastInserted, $token_request);
    }

    public function getDataModules(string $url_lms, string $course)
    {
        return $this->moodleApiService->getDataModules($url_lms, $course);
    }

    public function getDataModulesByType(Request $request, object $sessionData)
    {
        return $this->moodleApiService->getDataModulesByType($request, $sessionData);
    }

    public function getImgUser(string $token_request, string $url_lms, string $user_id)
    {
        return $this->moodleApiService->getImgUser($token_request, $url_lms, $user_id);
    }

    public function exportVersionMoodle(Request $request)
    {
        return $this->moodleApiService->exportVersionMoodle($request);
    }

    public function importRecursiveConditionsChange(string $url_lms, object $data, array $modules)
    {
        return $this->moodleApiService->importRecursiveConditionsChange($url_lms, $data, $modules);
    }

    public function getModuleById(int $instance, int $item_id)
    {
        return $this->moodleApiService->getModuleById($instance, $item_id);
    }

    public function getIdGrade(int $instance, object $module)
    {
        return $this->moodleApiService->getIdGrade($instance, $module);
    }

    public function getModulesNotSupported(Request $request, object $sessionData)
    {
        return $this->moodleApiService->getModulesNotSupported($request, $sessionData);
    }

    public function getGradeModule(string $url_lms, int $gradeId)
    {
        return $this->moodleApiService->getGradeModule($url_lms, $gradeId);
    }
}
