<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Instance;
use App\Models\Map;
use App\Models\Version;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SakaiController extends Controller
{
    public static function getCourse($course_id, $platform, $url_lms)
    {
        $dataInstance = Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' =>  $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        };
        $dataCourse = Course::firstOrCreate(
            ['instance_id' =>  $dataInstance->id, 'course_id' => $course_id],
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id, 'timestamps' => now()]
        );
        while (is_null($dataCourse->id)) {
            sleep(1);
        };
        $dataMaps = Map::select('id', 'created_id', 'course_id', 'name', 'updated_at')
            ->where('course_id', $dataCourse->id)
            ->get();
        $maps = [];
        foreach ($dataMaps as $map) {
            $dataVersions = Version::select('id', 'map_id', 'name', 'blocks_data', 'updated_at', 'default')
                ->where('map_id', $map->id)
                ->get();
            $versions = [];
            foreach ($dataVersions as $version) {

                array_push($versions, [
                    'id' => $version->id,
                    'map_id' => $version->map_id,
                    'name' => $version->name,
                    'updated_at' => $version->updated_at,
                    'default' => $version->default,
                    'blocksData' => json_decode($version->blocks_data),
                ]);
            }
            array_push($maps, [
                'id' => $map->created_id,
                'course_id' => $map->course_id,
                'name' => $map->name,
                'versions' => $versions,
            ]);
        }
        $course = [
            'maps' => $maps,
        ];

        return $course;
    }

    // Función que devuelve los datos del usuario y del curso
    public static function getSession(Object $lastInserted)
    {
        $data = [
            [
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => $lastInserted->profile_url,
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                // 'instance_id' => $this->getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'lessons' => SakaiController::getLessons($lastInserted->platform_id,$lastInserted->context_id,$lastInserted->session_id),
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'user_members' => SakaiController::getUserMembers($lastInserted->platform_id,$lastInserted->context_id,$lastInserted->session_id),
                'groups' => SakaiController::getGroups($lastInserted->platform_id,$lastInserted->context_id,$lastInserted->session_id)
            ],
            SakaiController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public static function createSession($url_lms, $sakaiServerId)
    {
        $client = new Client();
        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . env('SAKAI_USER') . '&pw=' . env('SAKAI_PASSWORD'));
        $content = $response->getBody()->getContents();
        $userId = $content . '.' . $sakaiServerId;
        return $userId;
    }

    public static function getLessons($url_lms, $contextId, $sessionId)
    {
        $data = SakaiController::createClient($url_lms.'/direct/lessons/site/'.$contextId.'.json', $sessionId);
        $lessons = [];
        foreach ($data->lessons_collection as $Lesson) {
            array_push($lessons, [
                'id' => $Lesson->id,
                'name' => $Lesson->lessonTitle
            ]);
        }
        return $lessons;
    }

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request,$sessionData)
    {   
        switch ($request->type) {
            case 'forum':
                // error_log('hola');
                return SakaiController::getForums($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'exam':
                // error_log('hola');
                return SakaiController::getAssessments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'assign':
                return SakaiController::getAssignments($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'text':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/plain');
                break;
            case 'url':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/url');
                break;
            case 'html':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'text/html');
                break;
            case 'folder':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, null);
                break;
            case 'resource':
                return SakaiController::getResources($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id, 'resource');
                break;
            default: 
                return response()->json(['ok' => false, 'errorType' => 'TYPE_NOT_SUPPORTED', 'data' => '']);
                break;
        }
    }

    // Función que devuelve los foros de un curso de Sakai
    public static function getForums($url_lms, $contextId, $sessionId)
    {
        $dataForums = SakaiController::createClient($url_lms.'/direct/forums/site/'.$contextId.'.json',$sessionId);
        $forums = [];
        foreach ($dataForums->forums_collection as $forum) {
            $forums[] = array(
                'id' => $forum->entityId,
                'name' => $forum->title
            );
        }
        return response()->json(['ok' => true, 'data' => $forums]);
    }

    // Función que devuelve las tareas de un curso de Sakai
    public static function getAssignments($url_lms, $contextId, $sessionId)
    {
        $dataAssignments = SakaiController::createClient($url_lms.'/direct/assignment/site/'.$contextId.'.json',$sessionId);
        $assignments = [];
        foreach ($dataAssignments->assignment_collection as $assignment) {
            $assignments[] = array(
                'id' => $assignment->entityId,
                'name' => $assignment->title
            );
        }
        return response()->json(['ok' => true, 'data' => $assignments]);
    }

    // Función que devuelve los recursos de un curso de Sakai dependiendo de su tipo
    public static function getResources($url_lms, $contextId, $sessionId, $type)
    {
        $dataContents = SakaiController::createClient($url_lms.'/direct/content/resources/group/'.$contextId.'.json?depth=3',$sessionId);
        $resources = [];
        if ($type === 'resource') {
            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                switch ($resource->mimeType) {
                    case 'text/plain':
                    case 'text/html':
                    case 'text/url':
                    case null:
                        break;
                    default:
                        array_push($resources, [
                            'id' => htmlspecialchars($resource->resourceId),
                            'name' => htmlspecialchars($resource->name)
                        ]);
                        break;
                }
            }
            return response()->json(['ok' => true, 'data' => $resources]);
        } else {
            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                if ($resource->mimeType === $type) {
                    array_push($resources, [
                        'id' => htmlspecialchars($resource->resourceId),
                        'name' => htmlspecialchars($resource->name)
                    ]);
                }
            }
            return response()->json(['ok' => true, 'data' => $resources]);
        }
    }
    public static function getUserMembers($url_lms, $contextId, $sessionId){
        $dataUsers = SakaiController::createClient($url_lms.'/direct/site/'.$contextId.'/memberships.json',$sessionId);
        $users = [];
        foreach ($dataUsers->membership_collection as $user) {
            $users[] = array(
                'id' => $user->userId,
                'name' => $user->userDisplayName
            );
        }
        return $users;
    }
    public static function getgroups($url_lms, $contextId, $sessionId){
        $dataGroups = SakaiController::createClient($url_lms.'/direct/site/'.$contextId.'/groups.json',$sessionId);
        $groups = [];
        foreach ($dataGroups as $group) {
            $groups[] = array(
                'id' => $group->id,
                'name' => $group->title
            );
        }
        return $groups;
    }

    public static function getModules($url_lms, $contextId, $sessionId){
        $modules = SakaiController::createClient($url_lms.'/direct/lessons/lesson/'.$contextId.'.json',$sessionId);
        foreach ($modules->contentsList as $index => $module) {
            $modules->contentsList[$index]->type = SakaiController::changeIdNameType($module->type);
        }
        return response()->json(['ok' => true, 'data' => $modules->contentsList]);
    }

    public static function changeIdNameType($type){
        switch ($type) {
            case 1:
                return 'resource';
                break;
            case 2:
                return 'page';
                break;
            case 3:
                return 'assignment';
                break;
            case 4:
                return 'assessment';
                break;
            case 5:
                return 'text';
                break;
            case 6:
                return 'url';
                break;
            case 8:
                return 'forum';
                break;
            case 20:
                return 'resource_folder';
                break;
            default:
                return 'generic';
                break;
        }
    }
    public static function getAssessments($url_lms, $contextId, $sessionId){
        $modules = SakaiController::createClient($url_lms.'/api/sites/'.$contextId.'/entities/assessments',$sessionId);
        $assesments = [];
        foreach ($modules as $assesment) {
            $assesments[] = array(
                'id' => $assesment->id,
                'name' => $assesment->title
            );
        }
        return response()->json(['ok' => true, 'data' => $assesments]);
    }

    public static function createClient($url, $sessionId, $type = 'GET'){
        $client = new Client();

        $response = $client->request($type, $url, [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $sessionId,
            ],
        ]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
}
