<?php

namespace App\Http\Controllers;

use App\Models\GroupModel;
use App\Models\MessageModel;
use App\Models\MediaModel;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * 聊天记录首页
     */
    public function index(Request $request)
    {
        // 检查密码验证
        if (!$this->checkPassword($request)) {
            return view('chat.password');
        }

        $companyId = $request->get('company_id', 1); // 默认公司ID，可以从session或auth获取

        // 获取群组列表（分页）
        $groups = GroupModel::where('company_id', $companyId)
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        // 获取选中的群组ID
        $selectedGroupId = $request->get('group_id');

        // 获取选中群组的信息
        $selectedGroup = null;
        if ($selectedGroupId) {
            $selectedGroup = GroupModel::where('group_id', $selectedGroupId)
                ->where('company_id', $companyId)
                ->first();
        }

        // 获取选中群组的消息（使用 with 关联查询）
        $messages = collect([]);
        if ($selectedGroupId && $selectedGroup) {
            $page = $request->get('page', 1);
            $messages = MessageModel::where('group_id', $selectedGroupId)
                ->with(['media', 'fromUser'])
                ->orderBy('msgtime', 'desc')
                ->paginate(50, ['*'], 'page', $page);
        }

        return view('chat.index', [
            'groups' => $groups,
            'messages' => $messages,
            'selectedGroupId' => $selectedGroupId,
            'selectedGroup' => $selectedGroup,
            'companyId' => $companyId,
        ]);
    }

    /**
     * 验证密码
     */
    public function verifyPassword(Request $request)
    {
        $password = $request->input('password');
        $correctPassword = config('app.chat_password', 'admin123'); // 默认密码，可在 .env 中配置 CHAT_PASSWORD

        if (empty($password)) {
            return response()->json(['success' => false, 'message' => '请输入密码'], 400);
        }

        if ($password === $correctPassword) {
            $request->session()->put('chat_verified', true);
            $request->session()->put('chat_verified_time', time());
            return response()->json(['success' => true, 'message' => '验证成功']);
        } else {
            return response()->json(['success' => false, 'message' => '密码错误'], 401);
        }
    }

    /**
     * 获取群组列表（AJAX分页）
     */
    public function getGroups(Request $request)
    {
        // 检查密码验证
        if (!$this->checkPassword($request)) {
            return response()->json(['error' => '需要密码验证'], 401);
        }

        $companyId = $request->get('company_id', 1);
        $page = $request->get('page', 1);

        $groups = GroupModel::where('company_id', $companyId)
            ->orderBy('updated_at', 'desc')
            ->paginate(20, ['*'], 'page', $page);

        $formattedGroups = $groups->map(function ($group) {
            $updatedAtText = '暂无';
            if ($group->updated_at) {
                // 处理 Carbon 对象或时间戳
                if ($group->updated_at instanceof \Carbon\Carbon) {
                    $updatedAtText = self::formatTime($group->updated_at->timestamp);
                } elseif (is_numeric($group->updated_at)) {
                    $updatedAtText = self::formatTime($group->updated_at);
                } else {
                    $updatedAtText = self::formatTime(strtotime($group->updated_at));
                }
            }

            return [
                'group_id' => $group->group_id,
                'group_name' => $group->group_name ?: '未命名群组',
                'type' => $group->type,
                'type_text' => $group->type == 1 ? '企业群' : '单聊',
                'updated_at_text' => $updatedAtText,
            ];
        });

        return response()->json([
            'groups' => $formattedGroups,
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * 获取群组消息（AJAX）
     */
    public function getMessages(Request $request)
    {
        // 检查密码验证
        if (!$this->checkPassword($request)) {
            return response()->json(['error' => '需要密码验证'], 401);
        }

        $groupId = $request->get('group_id');
        $page = $request->get('page', 1);

        if (!$groupId) {
            return response()->json(['error' => '群组ID不能为空'], 400);
        }

        // 使用 with 关联查询，避免 N+1 问题
        $messages = MessageModel::where('group_id', $groupId)
            ->with(['media', 'fromUser'])
            ->orderBy('msgtime', 'desc')
            ->paginate(50, ['*'], 'page', $page);

        // 格式化消息数据
        $formattedMessages = $messages->map(function ($message) {
            // 优先使用关联的 media 的 file_url，否则使用 media_path
            $mediaUrl = null;
            if ($message->media && $message->media->file_url) {
                $mediaUrl = $message->media->file_url;
            } elseif ($message->media_path) {
                $mediaUrl = $message->media_path;
            }

            // 优先使用 fromUser 的 truename，否则使用 username，最后使用 msgfrom
            $displayName = $message->msgfrom;
            if ($message->fromUser) {
                $displayName = $message->fromUser->truename ?? $message->fromUser->username ?? $message->msgfrom;
            }

            return [
                'id' => $message->id,
                'msgfrom' => $message->msgfrom,
                'display_name' => $displayName,
                'text' => $message->text,
                'msgtype' => $message->msgtype,
                'msgtime' => $message->msgtime,
                'media_id' => $message->media_id,
                'media_path' => $message->media_path,
                'media_url' => $mediaUrl,
                'media' => $message->media ? [
                    'file_url' => $message->media->file_url,
                    'filesize' => $message->media->filesize,
                ] : null,
                'formatted_time' => date('Y-m-d H:i:s', $message->msgtime / 1000),
            ];
        });

        return response()->json([
            'messages' => $formattedMessages,
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * 格式化时间显示
     */
    public static function formatTime($time)
    {
        // 处理 Carbon 对象
        if ($time instanceof \Carbon\Carbon) {
            $timestamp = $time->timestamp;
        } elseif (is_numeric($time)) {
            $timestamp = $time;
        } else {
            $timestamp = strtotime($time);
        }

        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . '天前';
        } else {
            return date('m-d H:i', $timestamp);
        }
    }

}

