<?php

namespace App\Http\Controllers;

use App\Models\MediaModel;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /**
     * 媒体文件列表页面
     */
    public function mediaList(Request $request)
    {
        // 检查密码验证
        if (!$this->checkPassword($request)) {
            return view('chat.password');
        }

        return view('media.list');
    }

    /**
     * 获取媒体文件列表（AJAX）
     */
    public function getMediaList(Request $request)
    {
        // 检查密码验证
        if (!$this->checkPassword($request)) {
            return response()->json(['error' => '需要密码验证'], 401);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $status = $request->get('status', ''); // 可选：筛选状态
        $filesizeMin = $request->get('filesize_min', '');
        $filesizeMax = $request->get('filesize_max', '');
        $md5sum = $request->get('md5sum', '');
        $dateRange = $request->get('date_range', '');

        // 构建查询
        $query = MediaModel::query();

        // 状态筛选
        if ($status !== '') {
            $query->where('status', $status);
        }

        // 文件大小筛选
        if ($filesizeMin !== '') {
            $query->where('filesize', '>=', (int)$filesizeMin);
        }
        if ($filesizeMax !== '') {
            $query->where('filesize', '<=', (int)$filesizeMax);
        }

        // MD5筛选（支持模糊搜索）
        if ($md5sum !== '') {
            $query->where('md5sum', 'like', '%' . $md5sum . '%');
        }

        // 时间范围筛选
        if ($dateRange !== '') {
            $dates = explode(' - ', $dateRange);
            if (count($dates) == 2) {
                $startDate = $dates[0] . ' 00:00:00';
                $endDate = $dates[1] . ' 23:59:59';
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }
        }

        // 分页查询
        $mediaList = $query->orderBy('media_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // 格式化数据
        $formattedMedia = $mediaList->map(function ($media) {
            // 格式化文件大小
            $fileSize = $media->filesize ?? 0;
            $fileSizeText = '';
            if ($fileSize > 0) {
                if ($fileSize < 1024) {
                    $fileSizeText = $fileSize . ' B';
                } elseif ($fileSize < 1024 * 1024) {
                    $fileSizeText = round($fileSize / 1024, 2) . ' KB';
                } elseif ($fileSize < 1024 * 1024 * 1024) {
                    $fileSizeText = round($fileSize / (1024 * 1024), 2) . ' MB';
                } else {
                    $fileSizeText = round($fileSize / (1024 * 1024 * 1024), 2) . ' GB';
                }
            }

            // 获取文件类型
            $fileType = '未知';
            $fileExtension = '';
            if ($media->file_url) {
                $fileExtension = strtolower(pathinfo(parse_url($media->file_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                    $fileType = '图片';
                } elseif (in_array($fileExtension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
                    $fileType = '视频';
                } elseif (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'aac', 'm4a'])) {
                    $fileType = '音频';
                } elseif (in_array($fileExtension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                    $fileType = '文档';
                } else {
                    $fileType = '其他';
                }
            }

            return [
                'media_id' => $media->media_id,
                'md5sum' => $media->md5sum ?? '',
                'file_url' => $media->file_url ?? '',
                'filesize' => $fileSize,
                'filesize_text' => $fileSizeText,
                'file_type' => $fileType,
                'file_extension' => $fileExtension,
                'status' => $media->status ?? 0,
                'status_text' => ($media->status ?? 0) == 1 ? '正常' : '异常',
                'created_at' => $media->created_at ? $media->created_at->format('Y-m-d H:i:s') : '',
                'updated_at' => $media->updated_at ? $media->updated_at->format('Y-m-d H:i:s') : '',
            ];
        });

        return response()->json([
            'code' => 0,
            'msg' => 'success',
            'count' => $mediaList->total(),
            'data' => $formattedMedia,
        ]);
    }

}

