<?php

namespace App\Repositories;

use App\Models\Course;

class CourseInstanceRepository
{

    /**
     * @param string $instanceId
     * @param string $courseId
     * 
     * @return Course
     */
    public function getCourseByInstanceCourseId(string $instanceId, string $courseId): Course
    {
        return Course::where('instance_id', $instanceId)
            ->where('course_id', $courseId)
            ->first();
    }

    /**
     * @param int $instanceId
     * @param string $courseId
     * 
     * @return Course
     */
    public function firstOrCreate(int $instanceId, string $courseId): Course
    {
        return Course::firstOrCreate(
            ['instance_id' => $instanceId, 'course_id' => $courseId],
            ['instance_id' => $instanceId, 'course_id' => $courseId, 'timestamps' => now()]
        );
    }

    public function firstCourseByInstanceCourseId($course, $instance)
    {
        return Course::select('id')->where('course_id', $course)->where('instance_id', $instance)->first();
    }
}
