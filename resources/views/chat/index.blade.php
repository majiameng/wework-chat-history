<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业微信聊天记录</title>
    <link href="/static/layui-v2.13.2/css/layui.css" rel="stylesheet">
    <link href="/static/css/chat.css" rel="stylesheet">
</head>
<body>
    <div class="chat-container">
        <!-- 左侧群组列表 -->
        <div class="group-list">
            <div class="group-list-header">
                <h2><i class="layui-icon layui-icon-group"></i> 群组列表</h2>
            </div>
            <!-- 群组分类筛选 -->
            <div class="group-filter">
                <button class="filter-btn active" data-filter="all" onclick="filterGroups('all')">
                    <i class="layui-icon layui-icon-list"></i> 全部
                </button>
                <button class="filter-btn" data-filter="1" onclick="filterGroups(1)">
                    <i class="layui-icon layui-icon-group"></i> 企业群
                </button>
                <button class="filter-btn" data-filter="2" onclick="filterGroups(2)">
                    <i class="layui-icon layui-icon-user"></i> 单聊
                </button>
            </div>
            <div class="group-list-content" id="group-list-content">
                <div id="group-list-items">
                    @forelse($groups as $group)
                        @php
                            $updatedAt = $group->updated_at;
                            if ($updatedAt) {
                                // 处理 Carbon 对象
                                if ($updatedAt instanceof \Carbon\Carbon) {
                                    $timestamp = $updatedAt->timestamp;
                                } elseif (is_numeric($updatedAt)) {
                                    $timestamp = $updatedAt;
                                } else {
                                    $timestamp = strtotime($updatedAt);
                                }
                                $diff = time() - $timestamp;
                                if ($diff < 60) {
                                    $updatedAtText = '刚刚';
                                } elseif ($diff < 3600) {
                                    $updatedAtText = floor($diff / 60) . '分钟前';
                                } elseif ($diff < 86400) {
                                    $updatedAtText = floor($diff / 3600) . '小时前';
                                } elseif ($diff < 604800) {
                                    $updatedAtText = floor($diff / 86400) . '天前';
                                } else {
                                    $updatedAtText = date('m-d H:i', $timestamp);
                                }
                            } else {
                                $updatedAtText = '暂无';
                            }
                        @endphp
                        <div class="group-item {{ $selectedGroupId == $group->group_id ? 'active' : '' }}"
                             data-group-id="{{ $group->group_id }}"
                             data-group-name="{{ $group->group_name ?: '未命名群组' }}"
                             data-group-type="{{ $group->type }}"
                             onclick="loadMessages({{ $group->group_id }}, '{{ addslashes($group->group_name ?: '未命名群组') }}', event)"
                             style="cursor: pointer;">
                            <div class="group-item-header">
                                <div style="display: flex; align-items: center; flex: 1; min-width: 0;">
                                    <i class="layui-icon {{ $group->type == 1 ? 'layui-icon-group' : 'layui-icon-user' }} group-item-icon"></i>
                                    <div class="group-item-name" title="{{ $group->group_name ?: '未命名群组' }}">
                                        {{ $group->group_name ?: '未命名群组' }}
                                    </div>
                                </div>
                            </div>
                            <div class="group-item-info">
                                <span class="group-item-type">
                                    @if($group->type == 1)
                                        <i class="layui-icon layui-icon-group" style="font-size: 12px;"></i> 企业群
                                    @else
                                        <i class="layui-icon layui-icon-user" style="font-size: 12px;"></i> 单聊
                                    @endif
                                </span>
                                <span class="group-item-time">
                                    <i class="layui-icon layui-icon-time" style="font-size: 11px;"></i> {{ $updatedAtText }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding: 40px; text-align: center;">
                            <i class="layui-icon layui-icon-note" style="font-size: 48px; color: #ccc;"></i>
                            <div style="margin-top: 10px; color: #999;">暂无群组</div>
                        </div>
                    @endforelse
                </div>
                <div id="group-load-more" style="text-align: center; padding: 15px; display: none;">
                    <button class="layui-btn layui-btn-sm layui-btn-primary" onclick="loadMoreGroups()">加载更多</button>
                </div>
                <div id="group-loading" style="text-align: center; padding: 15px; display: none;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 加载中...
                </div>
            </div>
        </div>

        <!-- 右侧消息区域 -->
        <div class="message-area">
            <div class="layui-card">
                <div class="layui-card-header" style="background-color: #fafafa;">
                    <i class="layui-icon layui-icon-chat"></i>
                    <span id="current-group-name">{{ $selectedGroup ? ($selectedGroup->group_name ?: '未命名群组') : '请选择群组' }}</span>
                </div>
                <div class="layui-card-body" style="padding: 0;">
                    <div class="message-content" id="message-content">
                        @if($selectedGroupId && $messages->count() > 0)
                            @foreach($messages->reverse() as $message)
                                <div class="message-item">
                                    <div class="message-item-header">
                                        <span class="message-sender">
                                            <i class="layui-icon layui-icon-username"></i>
                                            @php
                                                $displayName = $message->msgfrom;
                                                if ($message->fromUser) {
                                                    $displayName = $message->fromUser->truename ?? $message->fromUser->username ?? $message->msgfrom;
                                                }
                                            @endphp
                                            {{ $displayName }}
                                        </span>
                                        <span class="message-time">
                                            <i class="layui-icon layui-icon-time"></i> {{ date('Y-m-d H:i:s', $message->msgtime / 1000) }}
                                        </span>
                                    </div>
                                    <div class="message-body">
                                        @if($message->msgtype == 'text')
                                            <div class="message-text">{{ $message->text }}</div>
                                        @elseif($message->msgtype == 'image')
                                            @php
                                                $imageUrl = ($message->media && $message->media->file_url) ? $message->media->file_url : ($message->media_path ?? null);
                                            @endphp
                                            @if($imageUrl)
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-picture"></i> [图片]
                                                </div>
                                                <div class="message-media">
                                                    <img src="{{ $imageUrl }}" alt="图片" onclick="previewImage('{{ $imageUrl }}')" />
                                                </div>
                                            @else
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-picture"></i> [图片] 媒体文件处理中...
                                                </div>
                                            @endif
                                        @elseif($message->msgtype == 'emotion')
                                            @php
                                                $imageUrl = ($message->media && $message->media->file_url) ? $message->media->file_url : ($message->media_path ?? null);
                                            @endphp
                                            @if($imageUrl)
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-picture"></i> [表情图片]
                                                </div>
                                                <div class="message-media">
                                                    <img src="{{ $imageUrl }}" alt="图片" onclick="previewImage('{{ $imageUrl }}')" />
                                                </div>
                                            @else
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-picture"></i> [表情图片] 媒体文件处理中...
                                                </div>
                                            @endif
                                        @elseif($message->msgtype == 'video')
                                            @php
                                                $videoUrl = ($message->media && $message->media->file_url) ? $message->media->file_url : ($message->media_path ?? null);
                                            @endphp
                                            @if($videoUrl)
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-video"></i> [视频]
                                                </div>
                                                <div class="message-media">
                                                    <video controls>
                                                        <source src="{{ $videoUrl }}" type="video/mp4">
                                                        您的浏览器不支持视频播放
                                                    </video>
                                                </div>
                                            @else
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-video"></i> [视频] 媒体文件处理中...
                                                </div>
                                            @endif
                                        @elseif($message->msgtype == 'voice')
                                            @php
                                                $voiceUrl = ($message->media && $message->media->file_url) ? $message->media->file_url : ($message->media_path ?? null);
                                                $fileSize = $message->media ? ($message->media->filesize ?? 0) : 0;
                                                $fileSizeText = $fileSize > 0 ? ' (' . round($fileSize / 1024, 2) . ' KB)' : '';
                                            @endphp
                                            @if($voiceUrl)
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-voice"></i> [语音]{{ $fileSizeText }}
                                                </div>
                                                <div class="message-media">
                                                    <audio controls preload="metadata" style="width: 100%; max-width: 300px;" onerror="console.error('音频加载失败:', this.src)">
                                                        <source src="{{ $voiceUrl }}" type="audio/mpeg">
                                                        <source src="{{ $voiceUrl }}" type="audio/mp3">
                                                        <source src="{{ $voiceUrl }}" type="audio/wav">
                                                        <source src="{{ $voiceUrl }}" type="audio/ogg">
                                                        <source src="{{ $voiceUrl }}" type="audio/aac">
                                                        <source src="{{ $voiceUrl }}" type="audio/webm">
                                                        您的浏览器不支持音频播放。如果音频无法播放，请<a href="{{ $voiceUrl }}" target="_blank" download>点击下载</a>
                                                    </audio>
                                                </div>
                                            @else
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-voice"></i> [语音] 媒体文件处理中...
                                                </div>
                                            @endif
                                        @elseif($message->msgtype == 'file')
                                            @php
                                                $fileUrl = ($message->media && $message->media->file_url) ? $message->media->file_url : ($message->media_path ?? null);
                                            @endphp
                                            @if($fileUrl)
                                                @php
                                                    $fileName = basename($fileUrl);
                                                @endphp
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-file"></i> [文件]
                                                </div>
                                                <a href="{{ $fileUrl }}" target="_blank" class="message-file" download>
                                                    <i class="layui-icon layui-icon-download-circle message-file-icon"></i>
                                                    <span>{{ $fileName }}</span>
                                                </a>
                                            @else
                                                <div class="message-text">
                                                    <i class="layui-icon layui-icon-file"></i> [文件] 媒体文件处理中...
                                                </div>
                                            @endif
                                        @else
                                            <div class="message-text">
                                                <i class="layui-icon layui-icon-notice"></i> [{{ $message->msgtype }}] 暂不支持的消息类型
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @elseif($selectedGroupId)
                            <div class="empty-state">
                                <i class="layui-icon layui-icon-note" style="font-size: 48px; color: #ccc;"></i>
                                <div style="margin-top: 10px;">该群组暂无消息</div>
                            </div>
                        @else
                            <div class="empty-state">
                                <i class="layui-icon layui-icon-chat" style="font-size: 48px; color: #ccc;"></i>
                                <div style="margin-top: 10px;">请从左侧选择一个群组查看消息</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 图片预览遮罩 -->
    <div id="image-preview" class="image-preview" onclick="this.style.display='none'">
        <img src="" alt="预览" />
    </div>
    <script src="/static/layui-v2.13.2/layui.js"></script>
    <script src="/static/js/chat.js"></script>
    <script>
        layui.use(['jquery', 'element', 'layer', 'laypage'], function(){
            var layuiModules = {
                $: layui.$,
                element: layui.element,
                layer: layui.layer,
                laypage: layui.laypage
            };

            // 初始化聊天功能
            initChat({
                layui: layuiModules,
                pagination: @if($selectedGroupId && $messages->hasPages())
                {
                    enabled: true,
                    total: {{ $messages->total() }},
                    currentPage: {{ $messages->currentPage() }},
                    perPage: {{ $messages->perPage() }}
                }
                @else
                null
                @endif,
                groups: {
                    currentPage: {{ $groups->currentPage() }},
                    hasMorePages: {{ $groups->hasMorePages() ? 'true' : 'false' }}
                },
                companyId: {{ $companyId }}
            });
        });
    </script>
</body>
</html>

