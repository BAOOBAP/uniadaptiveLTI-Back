<?php

namespace App\Repositories;

use App\Models\Instance;

class InstanceRepository
{

    /**
     * @param string $platform
     * @param string $urlLms
     * 
     * @return Instance
     */
    public function firstOrCreate(string $platform, string $urlLms): Instance
    {
        return Instance::firstOrCreate(
            ['platform' => $platform, 'url_lms' => $urlLms],
            ['platform' => $platform, 'url_lms' => $urlLms, 'timestamps' => now()]
        );
    }

    public function getUrlById($id)
    {
        return Instance::where('id', $id)
            ->select('url_lms')
            ->first();
    }
}
