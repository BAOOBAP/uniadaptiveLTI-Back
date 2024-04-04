<?php

namespace App\Repositories;

use App\Models\Map;
use Illuminate\Database\Eloquent\Collection;

class MapInstanceRepository
{
    /**
     * @param string $createdId
     * 
     * @return Map
     */
    public function getMapByCreatedId(string $createdId): Map
    {
        return Map::where('created_id', $createdId)
            ->first();
    }
    public function getmapsIdByCourseId(int $courseId): Collection
    {
        return Map::select('id')->where('course_id', $courseId)->get();
    }
    /**
     * @param string $createdId
     * 
     * @return Map
     */
    public function getMapIdByCreatedId(string $createdId): Map
    {
        return Map::select('id')
            ->where('created_id', $createdId)
            ->first();
    }

    /**
     * @param int $courseId
     * @param int $userId
     * 
     * @return Collection
     */
    public function getMapIdByCourseIdUserId(int $courseId, int $userId): Collection
    {
        return Map::select('created_id', 'course_id', 'name')
            ->where('course_id', $courseId)
            ->where('user_id', $userId)
            ->get();
    }


    public function createOrUpdate($map, $course, $userId)
    {
        return Map::updateOrCreate(
            ['created_id' => $map['id'], 'course_id' => $course->id, 'user_id' => (string)$userId],
            ['name' => $map['name']]
        );
    }
    public function deleteMapByCreatedId(int $createdId)
    {
        Map::where('created_id', '=', $createdId)
            ->delete();
    }
}
