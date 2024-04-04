<?php

namespace App\Services;

use App\Libraries\ApiRequest;
use App\Repositories\LtiInstanceRepository;
use Carbon\Carbon;

class TokenService
{
    private LtiInstanceRepository $ltiRepository;
    private ApiRequest $apiRequest;
    private ResponseService $responseService;

    public function __construct(LtiInstanceRepository $ltiRepository, ApiRequest $apiRequest, ResponseService $responseService)
    {
        $this->ltiRepository = $ltiRepository;
        $this->apiRequest = $apiRequest;
        $this->responseService = $responseService;
    }

    /**
     * @param string $token
     * 
     * @return bool
     */
    public function checkToken(string $token): bool
    {
        $result = null;
        if ($this->checkExpiredToken($token)) {
            $sessionData = $this->ltiRepository->getLtiInfoByToken($token);
            if ($sessionData !== null) {
                $sessionData->session_active = intval(Carbon::now()->addMinutes(env('TIME_LIMIT'))->valueOf());
                $result = $this->ltiRepository->updateLtiInfo($sessionData);
            }
        } else {
            $result = false;
        }

        return $result;
    }
    /**
     * @param string $token
     * 
     * @return bool
     */
    public function checkExpiredToken(string $token): bool
    {
        return $this->ltiRepository->checkExpiredToken($token);
    }
    /**
     * @param string $platform
     * @param array $lms_data
     * 
     * @return string|array
     */
    public function getTokenByPlatform(string $platform, array $lms_data): string|array
    {
        switch ($platform) {
            case 'moodle':
                return trim($lms_data['token']);
            case 'sakai':
                $token = [
                    'user' => trim($lms_data['user']),
                    'password' => trim($lms_data['password']),
                ];

                if (isset($lms_data['cookieName'])) {
                    $token['cookieName'] = trim($lms_data['cookieName']);
                }
                return $token;
            default:
                return '';
        }
    }

    /**
     * @param string $url_lms
     * @param string $platform
     * 
     * @return string
     */
    public function getLmsToken(string $url_lms, string $platform): string|null
    {
        $response = null;
        if (config()->has('multiple_lms_config')) {
            $multiple_lms_config = config('multiple_lms_config.lms_data');
            foreach ($multiple_lms_config as $lms_data) {
                if ($lms_data['url'] == $url_lms) {
                    $response = $this->getTokenByPlatform($platform, $lms_data);
                }
            }
        }

        return $response;
    }
    /**
     * Function that returns the name (if it exists) of the url added in the function
     * 
     * @param string $url_lms
     * 
     * @return array
     */
    public function getLmsName(string $url_lms): string|null
    {
        $response = null;
        // Obtains from the multiple_lms_config.php configuration the lms_data that contains all the LMS grouped by url and token
        $multiple_lms_config = config('multiple_lms_config.lms_data');
        foreach ($multiple_lms_config as $name => $lms_data) {
            if ($lms_data['url'] == $url_lms) {
                $response = $name;
                break;
            }
        }
        return $response;
    }
}
