<?php

namespace App\Repositories;

use App\Models\LtiInfo;

class LtiInstanceRepository
{

    /**
     * @param string $token
     * 
     * @return object
     */
    public function getLtiInfoByToken(string $token): object
    {
        $ltiInfo = LtiInfo::where('token', '=', $token)
            ->first();
        return $ltiInfo;
    }

    /**
     * @param LtiInfo $ltiInfo
     * 
     * @return bool
     */
    public function updateLtiInfo(LtiInfo $ltiInfo): bool
    {
        return $ltiInfo->save();
    }

    public function checkExpiredToken(string $token): bool
    {
        $now = time();
        return LtiInfo::where('token', '=', $token)
            ->where('expires_at', '>=', $now)
            ->exists();
    }
    public function getSessionByUsserPlatformContextExpires(string $userId, string $platfomrId, int $contextId, int $expired)
    {
        return LtiInfo::where([
            ['user_id', '=', $userId],
            ['platform_id', '=', $platfomrId],
            ['context_id', '=', $contextId],
            ['expires_at', '>=', $expired],
        ])->first();
    }

    public function newSessionMoodle(string $platform, int $contextId, string $title, string $locale, string $platformId, string $token, string $returnUrl, string $userId, string $userName, string $profileUrl, string $roles, int $expDate, string $currentDate)
    {
        return LtiInfo::insert([
            'tool_consumer_info_product_family_code' => $platform,
            'context_id' => $contextId,
            'context_title' => $title,
            'launch_presentation_locale' =>  $locale,
            'platform_id' => $platformId,
            'token' => $token,
            'launch_presentation_return_url' => $returnUrl,
            'user_id' => $userId,
            'lis_person_name_full' => $userName,
            'profile_url' => $profileUrl,
            'roles' => $roles,
            'expires_at' => $expDate,
            'created_at' => $currentDate,
        ]);
    }

    public function newSessionSakai(string $platform, int $contextId, string $title, string $locale, string $platformId, string $token, string $serverId, string $sessionId, string $returnUrl, string $userId, string $userName, string $profileUrl, string $roles, int $expDate, string $currentDate)
    {
        return LtiInfo::insert([
            'tool_consumer_info_product_family_code' => $platform,
            'context_id' => $contextId,
            'context_title' => $title,
            'launch_presentation_locale' => $locale,
            'platform_id' => $platformId,
            'token' => $token,
            'ext_sakai_serverid' => $serverId,
            'session_id' => $sessionId,
            'launch_presentation_return_url' => $returnUrl,
            'user_id' => $userId,
            'lis_person_name_full' => $userName,
            'profile_url' => $profileUrl,
            'roles' => $roles,
            'expires_at' => $expDate,
            'created_at' => $currentDate,
            'updated_at' => $currentDate,
        ]);
    }


    public function updateSessionMoodle(string $userId, string $platfomrId, int $contextId, int $expired, string $imgUser, string $contextTitle, string $personName): bool
    {
        return LtiInfo::where([
            ['user_id', '=', $userId],
            ['platform_id', '=', $platfomrId],
            ['context_id', '=', $contextId],
            ['expires_at', '>=', $expired],
        ])->update([
            'profile_url' => $imgUser,
            'context_title' => $contextTitle,
            'lis_person_name_full' => $personName,
        ]);
    }
    public function updateSessionSakai(string $userId, string $platfomrId, int $contextId, int $expired, string $imgUser, string $personName, int $sessionId): bool
    {
        return LtiInfo::where([
            ['user_id', '=', $userId],
            ['platform_id', '=', $platfomrId],
            ['context_id', '=', $contextId],
            ['expires_at', '>=', $expired],
        ])->update([
            'profile_url' => $imgUser,
            'lis_person_name_full' => $personName,
            'session_id' => $sessionId,
        ]);
    }

    public function deleteByUserPlatformContextId(string $userId, string $platfomrId, int $contextId): bool
    {
        return LtiInfo::where([
            ['user_id', '=', $userId],
            ['platform_id', '=', $platfomrId],
            ['context_id', '=', $contextId],
        ])->delete();
    }
}
