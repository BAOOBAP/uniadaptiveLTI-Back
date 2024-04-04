<?php

namespace App\Repositories;

use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;

class VersionInstanceRepository
{



    /**
     * Return all versions where $id equals to map_id.
     * @param int $id
     * 
     * @return Version
     */
    public function getVersionsByMapId(int $id): Collection
    {
        return Version::selectRaw('created_id as id, map_id, name')
            ->where('map_id', $id)
            ->get();
    }

    /**
     * Return a version when $id is equals to created_id.
     * @param int $id
     * 
     * @return Version
     */
    public function getVersionByCreatedId(int $id): Version
    {
        return Version::selectRaw('created_id as id, map_id, name, blocks_data')
            ->where('created_id', $id)
            ->first();
    }
    public function getVersionsIdByMapId(int $mapId): Collection
    {
        return Version::select('id')->where('map_id', $mapId)->get();
    }
    public function updateVersionDefaultById(int $id)
    {
        Version::where('id', '=', $id)
            ->update([
                'default' => '0',
            ]);
    }

    /**
     * This method creates or modifies a version depending on whether any of the parameters exist in a record. It is important to explain that the blocks_data passed to this function must be of type string.
     * @param int $mapId
     * @param int $id
     * @param string $name
     * @param bool $default
     * @param string $blocksData
     * 
     * @return Version
     */
    public function createOrUpdate(int $id, int $mapId, string $name, bool $default, string $blocksData): Version
    {
        return Version::updateOrCreate(
            ['map_id' => $mapId, 'created_id' => $id, 'name' => $name],
            ['default' => $default, 'blocks_data' => $blocksData]
        );
    }

    public function createVersion(int $id, int $mapId, string $name, bool $default, string $blocksData)
    {
        Version::create([
            'created_id' => $id,
            'map_id' => $mapId,
            'name' => $name,
            'default' => $default,
            'blocks_data' => $blocksData
        ]);
    }
    public function deleteVersionByCreatedId(int $createdId)
    {
        Version::where('created_id', '=', $createdId)
            ->delete();
    }
}
