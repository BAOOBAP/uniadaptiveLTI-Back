<?php

namespace App\Services;

class ResponseService
{
    /**
     * @param array|object|null $data
     * 
     * @return array
     */
    public function response($data = null)
    {
        if ($data != null) {
            // dd($data);
            return ['ok' => true,  'data' => $data];
        }
        return ['ok' => true];
    }

    /**
     * @param object|null $data
     * @param string $error
     * @param int $errorCode
     * 
     * @return array
     */
    public function errorResponse(object $data = null, $error = '', $errorCode = 0)
    {
        if ($error != '') {
            if ($errorCode != 0) {
                return ['ok' => false,  'data' => ['error' => $error, 'error_code' => $errorCode]];
            } else {
                return ['ok' => false,  'data' => ['error' => $error]];
            }
        }
        return ['ok' => false,  'data' => ['error' => strtoupper($data->exception), 'error_code' => $data->errorcode], 'message' => $data->message];
    }

    public function generateErrorResponse($condition, $errorMessage)
    {
        if ($condition) {
            return response()->json($this->errorResponse(null, $errorMessage), 500);
        }
        return null;
    }
}
