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
            ['platform' => $platform, 'url_lms' => $url_lms],
            ['platform' => $platform, 'url_lms' => $url_lms, 'timestamps' => now()]
        );
        while (is_null($dataInstance->id)) {
            sleep(1);
        }
        ;
        $dataCourse = Course::firstOrCreate(
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id],
            ['instance_id' => $dataInstance->id, 'course_id' => $course_id, 'timestamps' => now()]
        );
        while (is_null($dataCourse->id)) {
            sleep(1);
        }
        ;
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
    public static function getSession(object $lastInserted)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id);
        $data = [
            [
                'user_id' => SakaiController::getId($lastInserted->user_id),
                'name' => $lastInserted->lis_person_name_full,
                'profile_url' => SakaiController::getUrl($lastInserted->platform_id, $lastInserted->context_id, SakaiController::getId($lastInserted->user_id)),
                'roles' => $lastInserted->roles
            ],
            [
                'name' => $lastInserted->context_title,
                // 'instance_id' => $this->getinstance($lastInserted->tool_consumer_info_product_family_code, $lastInserted->platform_id),
                'lessons' => SakaiController::getLessons($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id),
                'course_id' => $lastInserted->context_id,
                'session_id' => $lastInserted->session_id,
                'platform' => $lastInserted->tool_consumer_info_product_family_code,
                'lms_url' => $lastInserted->platform_id,
                'return_url' => $lastInserted->launch_presentation_return_url,
                'user_members' => SakaiController::getUserMembers($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id),
                'sakai_groups' => SakaiController::getGroups($lastInserted->platform_id, $lastInserted->context_id, $lastInserted->session_id)
            ],
            SakaiController::getCourse(
                $lastInserted->context_id,
                $lastInserted->tool_consumer_info_product_family_code,
                $lastInserted->platform_id
            )
        ];
        // dd($data);
        return response()->json(['ok' => true, 'data' => $data]);
    }

    public static function createSession($url_lms, $sakaiServerId)
    {
        $client = new Client();
        $response = $client->request('GET', $url_lms . '/sakai-ws/rest/login/login?id=' . env('SAKAI_USER') . '&pw=' . env('SAKAI_PASSWORD'));
        $content = $response->getBody()->getContents();
        $user_id = $content . '.' . $sakaiServerId;
        return $user_id;
    }

    public static function getLessons($url_lms, $context_id, $session_id)
    {
        // header('Access-Control-Allow-Origin: *');
        $data = SakaiController::createClient($url_lms . '/direct/lessons/site/' . $context_id . '.json', $session_id);

        $lessons = [];
        foreach ($data->lessons_collection as $Lesson) {
            array_push($lessons, [
                'id' => $Lesson->id,
                'name' => $Lesson->lessonTitle,
                'page_id' => SakaiController::getPageIdLesson($url_lms, $Lesson->id, $session_id)
            ]);
        }
        return $lessons;
    }

    public static function getPageIdLesson($url_lms, $context_id, $session_id)
    {
        $data = SakaiController::createClient($url_lms . '/direct/lessons/lesson/' . $context_id . '.json', $session_id);
        return $data->sakaiId;
    }

    // Función que devuelve los modulos con tipo en concreto de un curso
    public static function getModulesByType(Request $request, $sessionData)
    {
        switch ($request->type) {
            case 'forum':
                return SakaiController::getForums($sessionData->platform_id, $sessionData->context_id, $sessionData->session_id);
                break;
            case 'exam':
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
    public static function getForums($url_lms, $context_id, $session_id)
    {
        $dataForums = SakaiController::createClient($url_lms . '/direct/forums/site/' . $context_id . '.json', $session_id);
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
    public static function getAssignments($url_lms, $context_id, $session_id)
    {
        $dataAssignments = SakaiController::createClient($url_lms . '/direct/assignment/site/' . $context_id . '.json', $session_id);
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
    public static function getResources($url_lms, $context_id, $session_id, $type)
    {
        $dataContents = SakaiController::createClient($url_lms . '/direct/content/resources/group/' . $context_id . '.json?depth=3', $session_id);
        $resources = [];

        function decode_unicode($str) {
            $str = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $str);
            return $str;
        }

        if ($type === 'resource') {
            function process_resource($resource, &$resources) {
                $id = decode_unicode(str_replace('\/', '/', $resource->resourceId));
        
                switch ($resource->mimeType) {
                    case 'text/html':
                    case 'text/url':
                    case null:
                        break;
                    default:
                        array_push($resources, [
                            'id' => htmlspecialchars($id),
                            'name' => htmlspecialchars($resource->name)
                        ]);
                        break;
                }
                error_log("223 " . print_r($resource, true));
                if (count($resource->resourceChildren) >= 1) {
                    foreach ($resource->resourceChildren as $child) {
                        process_resource($child, $resources);
                    }
                }
            }
        
            foreach ($dataContents->content_collection[0]->resourceChildren as $resource) {
                process_resource($resource, $resources);
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
    public static function getUserMembers($url_lms, $context_id, $session_id)
    {
        $dataUsers = SakaiController::createClient($url_lms . '/direct/site/' . $context_id . '/memberships.json', $session_id);
        $users = [];
        foreach ($dataUsers->membership_collection as $user) {
            $users[] = array(
                'id' => $user->userId,
                'name' => $user->userDisplayName
            );
        }
        return $users;
    }
    public static function getGroups($url_lms, $context_id, $session_id)
    {
        $dataGroups = SakaiController::createClient($url_lms . '/direct/site/' . $context_id . '/groups.json', $session_id);
        $groups = [];
        foreach ($dataGroups as $group) {
            $groups[] = array(
                'id' => $group->reference,
                'name' => $group->title
            );
        }
        return $groups;
    }

    public static function getModules($url_lms, $context_id, $session_id)
    {
        // header('Access-Control-Allow-Origin: *');
        // dd($url_lms.'/direct/lessons/lesson/'.$context_id.'.json');
        $modulesData = SakaiController::createClient($url_lms . '/direct/lessons/lesson/' . $context_id . '.json', $session_id);
        $modules = [];
        $section = 0;
        $column = 0;
        $order = 1;

        if ($modulesData->contentsList != null) {
            foreach ($modulesData->contentsList as $index => $module) {
                $modulesData->contentsList[$index]->type = SakaiController::changeIdNameType($module->type);

                if ($modulesData->contentsList[$index]->type == 'break') {
                    $format = isset($modulesData->contentsList[$index]->format);
                    if ($format) {
                        switch ($modulesData->contentsList[$index]->format) {
                            case 'section':
                                $section++;
                                break;

                            case 'column':
                                $column++;
                                break;
                        }
                    } else {
                        $section++;
                    }
                    $order = 1;
                } else if ($modulesData->contentsList[$index]->type != 'break' /*&& $modulesData->contentsList[$index]->type != 'generic'*/&& $modulesData->contentsList[$index]->type != 'page' && $modulesData->contentsList[$index]->type != 'text') {
                    // $modulesData->contentsList[$index]->section = $section;
                    switch ($modulesData->contentsList[$index]->type) {
                        case 'exam':
                        case 'assign':
                        case 'forum':
                            $a = substr($modulesData->contentsList[$index]->sakaiId, 1);
                            $dataSakaiId = explode('/', $a);
                            $sakaiId = end($dataSakaiId);
                            break;
                        case 'break':
                            break;
                        default:
                            $sakaiId = $modulesData->contentsList[$index]->sakaiId;
                            break;
                    }

                    array_push(
                        $modules,
                        [
                            "sakaiId" => $sakaiId,
                            "name" => $modulesData->contentsList[$index]->name,
                            "modname" => $modulesData->contentsList[$index]->type,
                            "pageId" => $modulesData->contentsList[$index]->pageId,
                            "section" => $section,
                            "indent" => $column,
                            "order" => $order++
                        ]

                    );
                }
            }
            // dd($modules);
            return response()->json(['ok' => true, 'data' => $modules]);
        }
        return response()->json(['ok' => false, 'data' => '', 'errorType' => 'DONT_CONTAINS_MODULES']);
        // dd( $modules);

    }

    public static function changeIdNameType($type)
    {
        switch ($type) {
            case 1:
                return 'resource';
                break;
            case 2:
                return 'page';
                break;
            case 3:
                return 'assign';
                break;
            case 4:
                return 'exam';
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
            case 14:
                return 'break';
                break;
            case 20:
                return 'folder';
                break;
            default:
                return 'generic';
                break;
        }
    }
    public static function getAssessments($url_lms, $context_id, $session_id)
    {
        $modules = SakaiController::createClient($url_lms . '/api/sites/' . $context_id . '/entities/assessments', $session_id);
        $assesments = [];
        foreach ($modules as $assesment) {
            $assesments[] = array(
                'id' => $assesment->id,
                'name' => $assesment->title
            );
        }
        return response()->json(['ok' => true, 'data' => $assesments]);
    }

    public static function createClient($url, $session_id, $type = 'GET', $bodyData = [])
    {
        $client = new Client();
        $options = [
            'headers' => [
                'Cookie' => 'JSESSIONID=' . $session_id
            ],
        ];
        switch ($type) {
            case "GET":
            case "DELETE":
                // Both GET and DELETE share the same request options
                $response = $client->request($type, $url, $options);
                if ($type === "DELETE") {
                    $statusCode = $response->getStatusCode();
                    return $statusCode;
                }
                break;

            case "BATCH":
            case "POST":
                // Convert the $bodyData array to JSON
                $options['json'] = $bodyData;
                $options['headers']['Content-Type'] = 'application/json';
                if ($type === "POST") {
                    $response = $client->post($url, $options);
                } else {
                    $response = $client->patch($url, $options);
                }
                break;
            default:
                // Handle unsupported request types here
                return ['error' => 'Unsupported request type'];
        }
        $content = $response->getBody()->getContents();
        $data = json_decode($content);
        return $data;
    }
    public static function getId($user_id)
    {
        $url = explode('/', $user_id);
        return $url[count($url) - 1];
    }

    public static function getUrl($url_lms, $context_id, $user_id)
    {
        return ($url_lms . '/direct/profile/' . $user_id . '/image/thumb?siteId=' . $context_id);
    }

    public static function find($array, $callback) {
        return current(array_filter($array, $callback));
    }

    public static function exportVersion(Request $request, $sessionData)
    {
        //header('Access-Control-Allow-Origin: *');
        $nodes = $request->nodes;

        $nodes = array_map(function ($item) {
            if ($item['title'] === null) {
                $item['title'] = "";
            }
            return $item;
        }, $nodes);

        $nodesToUpdate = $request->nodesToUpdate;
        $conditionList = $request->conditionList;

        $firstNode = reset($nodes);
        $firstPageId = $firstNode['pageId'];

        $allHaveSamePageId = true;

        foreach ($nodes as $node) {
            if (!isset($node['pageId']) || $node['pageId'] !== $firstPageId) {
                $allHaveSamePageId = false;
                break;
            }
        }

        if ($allHaveSamePageId) {

            $conditionsDelete = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/conditions', $sessionData->session_id, 'DELETE');
            $lessonItemsDelete = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items', $sessionData->session_id, 'DELETE');
            if ($conditionsDelete === 200 && $lessonItemsDelete === 200) {
                if (count($nodesToUpdate) >= 1) {
                    $nodesBulkUpdate = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/entities', $sessionData->session_id, 'BATCH', $nodesToUpdate);

                }
                
                $nodesBulkCreation = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/lessons/' . $request->lessonId . '/items/bulk', $sessionData->session_id, 'POST', $nodes);
                
                /*
                if ($nodesBulkCreation) {
                    $nodesIdList = [];

                    foreach ($nodesBulkCreation as $node) {
                        if (isset($node->type) && $node->type !== 14) {
                            $nodeJson = json_encode(['id' => $node->id, 'contentRef' => $node->contentRef]);
                            array_push($nodesIdList, $nodeJson);
                        }
                    }

                    $filteredArray = array_filter($conditionList, function ($condition) use ($nodesIdList) {
                        foreach ($nodesIdList as $idObject) {
                            $idObject = json_decode($idObject, true);
                            if (isset($condition['itemId']) && $condition['itemId'] === $idObject['contentRef']) {
                                error_log(print_r($idObject['id'], true));
                                return true;
                            }
                        }
                        return false;
                    });

                    foreach ($filteredArray as &$root) {
                        foreach ($nodesIdList as $idObject) {
                            $idObject = json_decode($idObject, true);
                            if ($root['itemId'] == $idObject['contentRef']) {
                                $root['itemId'] = $idObject['id'];
                                break;
                            }
                        }

                        if (isset($root['subConditions'])) {
                            foreach ($root['subConditions'] as &$parent) {
                                error_log(print_r($parent, true));
                                if (isset($parent['subConditions']) && count($parent['subConditions']) >= 1) {
                                    foreach ($parent['subConditions'] as &$childCondition) {
                                        foreach ($nodesIdList as $idObject) {
                                            $idObject = json_decode($idObject, true);
                                            if ($childCondition['itemId'] == $idObject['contentRef']) {
                                                $childCondition['itemId'] = $idObject['id'];
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    unset($root, $subCondition, $childCondition);
                    
                    $conditionsParsedList = array_values($filteredArray);
                    error_log(print_r($conditionsParsedList, true));

                    $nodesBulkCreation = SakaiController::createClient($sessionData->platform_id . '/api/sites/' . $sessionData->context_id . '/conditions/bulk', $sessionData->session_id, 'POST', $conditionsParsedList);
                    return response()->json(['ok' => true, 'errorType' => 'EXPORTACION_CON_EXITO', 'data' => '']);
                }*/
                return response()->json(['ok' => true, 'data' => '']);
            } else {
                return response()->json(['ok' => false, 'errorType' => 'LESSON_DELETE_ERROR', 'data' => '']);
            }
        } else {
            return response()->json(['ok' => false, 'errorType' => 'PAGE_EXPORT_ERROR', 'data' => '']);
        }

        // header('Access-Control-Allow-Origin: *');
        // dd($request->nodes);
    }
}