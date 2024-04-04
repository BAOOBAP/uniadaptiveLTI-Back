<?php

namespace App\Services;

use App\Http\Controllers\ControllerFactory;
use App\Repositories\LtiInstanceRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LonghornOpen\LaravelCelticLTI\LtiTool;

class LtiSessionService
{
    protected $ltiRepository;

    protected $handleService;
    protected $responseService;
    protected $registerLogService;
    protected $tokenService;
    protected $moodleApiService;
    protected $sessionService;

    public function __construct(
        LtiInstanceRepository $ltiRepository,
        ResponseService $responseService,
        HandleRequestService $handleService,
        RegisterLogService $registerLogService,
        TokenService $tokenService,
        MoodleApiService $moodleApiService
    ) {
        $this->ltiRepository = $ltiRepository;
        $this->responseService = $responseService;
        $this->handleService = $handleService;
        $this->registerLogService = $registerLogService;
        $this->tokenService = $tokenService;
        $this->moodleApiService = $moodleApiService;
    }

    /**
     * @return array
     */
    public function getJWKS()
    {

        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));

        $tool = LtiTool::getLtiTool();

        return $tool->getJWKS();
    }

    /**
     * Function that obtains data from the LMS, stores it in the database (TEMPORARY) and redirects to the front end.
     * 
     * @return array
     */

    public function saveSession()
    {
        // Set Access-Control-Allow-Origin header
        header('Access-Control-Allow-Origin: ' . env('FRONT_URL'));

        // Handle proxy settings
        $this->handleService->handleProxySettings();

        // Get LTI tool instance
        $tool = LtiTool::getLtiTool();
        $tool->handleRequest();

        // Get JWT and message parameters
        $jwt = $tool->getJWT();
        $fire = $tool->getMessageParameters();
        $platform = $fire['tool_consumer_info_product_family_code'];
        $controller = ControllerFactory::create($platform);
        $token_request = $this->tokenService->getLmsToken($fire['platform_id'], $platform, true);
        $response = null;
        // Check if token is valid
        if ($token_request === null) {
            $response = response('The token used is not valid or the file multiple_lms_config.php does not exist', 500);
        }

        // Check Moodle web service token if platform is Moodle
        if ($platform === "moodle") {
            $result = $this->moodleApiService->checkMoodleWebServiceToken($fire['platform_id'], $token_request);
            if (!$result['ok']) {
                $response = response()->json($result, 500);
            }
        }

        // Handle session creation or update
        $response = $this->createOrUpdateSession($platform, $fire, $jwt, $token_request, $controller);

        return $response;
    }

    /**
     * Function that returns the user and course data.
     * 
     * @param Request $request
     * 
     * @return array
     */
    public function getSession(Request $request)
    {
        return $this->handleService->handleRequest($request, function ($request) {
            $response = null;
            $sessionData = $this->ltiRepository->getLtiInfoByToken($request->token);
            $this->registerLogService->registerLog('getSession', $sessionData);
            $platform = $sessionData->tool_consumer_info_product_family_code;

            $token_request = $this->tokenService->getLmsToken($sessionData->platform_id, $platform, true);

            $platformName = $this->tokenService->getLmsName($sessionData->platform_id);

            if ($token_request !== null && ($platformName !== null && is_string($platformName))) {
                $sessionData->platform_name = $platformName;
            }

            try {
                $controller = ControllerFactory::create($platform);
                $response = $controller->getUserSession($sessionData, $token_request);
            } catch (Exception $e) {
                $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
            }
            return $response;
        });
    }



    public function createOrUpdateSession($platform, $fire, $jwt, $token_request, $controller)
    {
        $response = null;
        $session = $this->ltiRepository->getSessionByUsserPlatformContextExpires(
            $fire['user_id'],
            $fire['platform_id'],
            $fire['context_id'],
            intval(Carbon::now()->valueOf())
        );

        if ($session) {
            // Update session
            $response = $this->updateSession($platform, $fire, $jwt, $token_request, $controller, $session);
        } else {
            // Create new session
            $response = $this->createSession($platform, $fire, $jwt, $token_request, $controller);
        }
        return $response;
    }

    public function updateSession($platform, $fire, $jwt, $token_request, $controller, $session)
    {
        $response = null;
        switch ($platform) {
            case 'moodle':
                try {
                    $insertOrUpdate = $this->ltiRepository->updateSessionMoodle(
                        $fire['user_id'],
                        $fire['platform_id'],
                        $fire['context_id'],
                        intval(Carbon::now()->valueOf()),
                        $controller->getImgUser(
                            $token_request,
                            $fire['platform_id'],
                            $fire['user_id']
                        ),
                        $fire['context_title'],
                        isset($fire['lis_person_name_full']) == false ? 'Usuario' : $fire['lis_person_name_full']
                    );
                    if ($insertOrUpdate) {
                        $response = $this->redirectToFront($fire);
                    }
                } catch (Exception $e) {
                    $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                }
                break;
            case 'sakai':
                $jwtPayload = $jwt->getPayload();
                $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;

                $sessionIdRequest = $controller->createSession($fire['platform_id'], $sakai_serverid, $token_request);

                if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                    $session_id = $sessionIdRequest['data']['user_id'];
                    try {
                        $insertOrUpdate = $this->ltiRepository->updateSessionSakai($fire['user_id'], $fire['platform_id'], $fire['context_id'], intval(Carbon::now()->valueOf()), $controller->getUrl($fire['platform_id'], $fire['context_id'], $controller->getId($fire['user_id'])), $fire['lis_person_name_full'], $session_id);
                        if ($insertOrUpdate) {
                            $response = $this->redirectToFront($fire);
                        }
                    } catch (Exception $e) {
                        $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                    }
                } else {
                    $response = response()->json($this->responseService->errorResponse(null, "CREATE_SESSION_ERROR"), 500);
                }
                break;
            default:
                $response = response()->json($this->responseService->errorResponse(null, "PLATFORM_NOT_SUPPORTED"), 500);
                break;
        }
        return $response;
    }

    public function createSession($platform, $fire, $jwt, $token_request, $controller)
    {
        $response = null;
        $currentDate = date('Y-m-d H:i:s');
        $expDate = Carbon::now()->addSeconds(30000)->valueOf();
        switch ($platform) {
            case 'moodle':
                try {
                    $insertOrUpdate = $this->ltiRepository->newSessionMoodle(
                        $platform,
                        $fire['context_id'],
                        $fire['context_title'],
                        $fire['launch_presentation_locale'],
                        $fire['platform_id'],
                        Str::uuid()->toString(),
                        $fire['launch_presentation_return_url'],
                        (string) $fire['user_id'],
                        isset($fire['lis_person_name_full']) == false ? 'Usuario' : $fire['lis_person_name_full'],
                        $controller->getImgUser($token_request, $fire['platform_id'], $fire['user_id']),
                        $fire['roles'],
                        $expDate,
                        $currentDate,
                    );
                    if ($insertOrUpdate) {
                        $response = $this->redirectToFront($fire);
                    }
                } catch (Exception $e) {
                    $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                }
                break;
            case 'sakai':
                $jwtPayload = $jwt->getPayload();
                $locale = $jwtPayload->locale;
                $sakai_serverid = $jwtPayload->{'https://www.sakailms.org/spec/lti/claim/extension'}->sakai_serverid;

                $sessionIdRequest = $controller->createSession($fire['platform_id'], $sakai_serverid, $token_request);
                if (isset($sessionIdRequest) && isset($sessionIdRequest['ok']) && $sessionIdRequest['ok'] === true) {
                    $session_id = $sessionIdRequest['data']['user_id'];
                    try {
                        $insertOrUpdate = $this->ltiRepository->newSessionSakai(
                            $fire['tool_consumer_info_product_family_code'],
                            $fire['context_id'],
                            $fire['context_title'],
                            $locale,
                            $fire['platform_id'],
                            Str::uuid()->toString(),
                            $sakai_serverid,
                            $session_id,
                            $fire['platform_id'] . '/portal/site/' . $fire['context_id'],
                            $fire['user_id'],
                            $fire['lis_person_name_full'],
                            $controller->getUrl($fire['platform_id'], $fire['context_id'], $controller->getId($fire['user_id'])),
                            $fire['roles'],
                            $expDate,
                            $currentDate,
                        );
                        if ($insertOrUpdate) {
                            $response = $this->redirectToFront($fire);
                        }
                    } catch (Exception $e) {
                        $response = response()->json($this->responseService->errorResponse(null, $e->getMessage()), $e->getCode());
                    }
                } else {
                    $response = response()->json($this->responseService->errorResponse(null, "CREATE_SESSION_ERROR"), 500);
                }
                break;
            default:
                $response = response()->json($this->responseService->errorResponse(null, "PLATFORM_NOT_SUPPORTED"), 500);
                break;
        }
        return $response;
    }

    private function redirectToFront($fire)
    {
        $response = null;
        $session = $this->ltiRepository->getSessionByUsserPlatformContextExpires($fire['user_id'], $fire['platform_id'], $fire['context_id'], Carbon::now()->valueOf());
        $headers = @get_headers(env('FRONT_URL'));
        $canSee = false;
        if ($headers) {
            foreach ($headers as $header) {
                if (strpos($header, '200 OK')) {
                    $canSee = true;
                    break;
                }
            }
        }

        if ($canSee) {
            $response = redirect()->to(env('FRONT_URL') . '?token=' . $session->token);
        } else {
            $response = response()->json([
                'error' => 'ERROR_TO_REDIRECT',
                'error_code' => 404,
                'message' => 'It is not possible to redirect to the Front. Check that the front is working correctly. Check the address in the .env or if it has been launched correctly.'
            ], 404);
        }
        return $response;
    }
}
