<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    /**
     * 检查密码验证状态
     */
    protected function checkPassword(Request $request)
    {
        // 检查 session 中是否有验证标记
        if (!$request->session()->has('chat_verified')) {
            return false;
        }

        // 检查验证是否过期（24小时）
        $verifiedTime = $request->session()->get('chat_verified_time', 0);
        if (time() - $verifiedTime > 86400) {
            $request->session()->forget('chat_verified');
            $request->session()->forget('chat_verified_time');
            return false;
        }

        return true;
    }

}
