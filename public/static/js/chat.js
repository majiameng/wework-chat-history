/**
 * 聊天页面 JavaScript
 * 需要在 layui.use 回调中调用 initChat 函数进行初始化
 */
(function() {
    'use strict';

    // 全局变量
    var currentGroupPage = 1;
    var hasMoreGroups = false;
    var companyId = null;
    var $ = null;
    var laypage = null;

    // 消息分页相关
    var currentMessagePage = 1;
    var currentGroupId = null;
    var currentGroupName = null;
    var hasMoreMessages = false;
    var isLoadingMessages = false;
    var isInitializing = false; // 标记是否正在初始化

    // 群组筛选相关
    var currentFilter = 'all'; // 当前筛选类型：'all', '1', '2'

    /**
     * 初始化聊天功能
     * @param {Object} options 配置选项
     * @param {Object} options.layui Layui 对象
     * @param {Object} options.pagination 分页配置
     * @param {Object} options.groups 群组配置
     * @param {Object} options.companyId 公司ID
     */
    window.initChat = function(options) {
        $ = options.layui.$;
        laypage = options.layui.laypage;
        currentGroupPage = options.groups.currentPage || 1;
        hasMoreGroups = options.groups.hasMorePages || false;
        companyId = options.companyId;

        // 初始化群组列表滚动监听
        if (hasMoreGroups) {
            $('#group-load-more').show();
        }

        // 页面加载时，如果URL中有group_id参数，自动加载对应的群组消息
        $(document).ready(function() {
            var urlParams = new URLSearchParams(window.location.search);
            var groupId = urlParams.get('group_id');

            // 如果页面中已经有消息（首次加载），初始化分页状态
            var $existingMessages = $('#message-content .message-item');
            if ($existingMessages.length > 0 && groupId) {
                isInitializing = true; // 标记正在初始化
                currentGroupId = parseInt(groupId);
                currentGroupName = $('#current-group-name').text() || '未命名群组';
                // 检查是否还有更多消息（如果有分页信息）
                if (options.pagination && options.pagination.enabled) {
                    currentMessagePage = options.pagination.currentPage;
                    var totalPages = Math.ceil(options.pagination.total / options.pagination.perPage);
                    hasMoreMessages = options.pagination.currentPage < totalPages;
                } else {
                    currentMessagePage = 1;
                    hasMoreMessages = false;
                }

                // 滚动到底部，然后启用滚动监听
                setTimeout(function() {
                    var $messageContent = $('#message-content');
                    $messageContent.scrollTop($messageContent[0].scrollHeight);
                    // 滚动完成后，初始化滚动监听并解除初始化标记
                    setTimeout(function() {
                        initMessageScrollListener();
                        isInitializing = false;
                    }, 200);
                }, 100);
            } else if (groupId) {
                // 查找对应的群组项
                var $groupItem = $('[data-group-id="' + groupId + '"]');
                if ($groupItem.length) {
                    var groupName = $groupItem.attr('data-group-name') || '未命名群组';
                    // 延迟一下，确保页面渲染完成
                    setTimeout(function() {
                        loadMessages(parseInt(groupId), groupName, null);
                    }, 100);
                } else {
                    // 如果群组不在当前列表中，尝试加载（可能是分页问题）
                    // 先尝试从服务器获取群组信息
                    $.ajax({
                        url: '/chat/groups',
                        type: 'GET',
                        data: { company_id: companyId },
                        dataType: 'json',
                        success: function(data) {
                            if (data.groups) {
                                var group = null;
                                $.each(data.groups, function(index, g) {
                                    if (g.group_id == groupId) {
                                        group = g;
                                        return false;
                                    }
                                });
                                if (group) {
                                    loadMessages(parseInt(groupId), group.group_name, null);
                                }
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('加载群组失败:', error);
                        }
                    });
                }
            }
        });

        // 监听浏览器前进/后退按钮
        $(window).on('popstate', function(event) {
            var urlParams = new URLSearchParams(window.location.search);
            var groupId = urlParams.get('group_id');

            if (groupId) {
                var $groupItem = $('[data-group-id="' + groupId + '"]');
                if ($groupItem.length) {
                    var groupName = $groupItem.attr('data-group-name') || '未命名群组';
                    loadMessages(parseInt(groupId), groupName, null);
                }
            } else {
                // 清除选中状态
                $('.group-item').removeClass('active');
                $('#current-group-name').text('请选择群组');
                $('#message-content').html('<div class="empty-state"><i class="layui-icon layui-icon-chat" style="font-size: 48px; color: #ccc;"></i><div style="margin-top: 10px;">请从左侧选择一个群组查看消息</div></div>');
            }
        });
    };

    /**
     * 获取 jQuery 对象（安全获取）
     */
    function getJQuery() {
        if (!$ && window.layui && window.layui.$) {
            $ = window.layui.$;
        }
        return $;
    }

    /**
     * 初始化消息滚动监听
     */
    function initMessageScrollListener() {
        var $ = getJQuery();
        if (!$) return;

        var $messageContent = $('#message-content');
        if ($messageContent.length === 0) return;

        // 移除之前的监听器（避免重复绑定）
        $messageContent.off('scroll.messageScroll');

        // 监听滚动事件
        $messageContent.on('scroll.messageScroll', function() {
            // 如果正在初始化、正在加载、没有更多消息或没有当前群组，则不处理
            if (isInitializing || isLoadingMessages || !hasMoreMessages || !currentGroupId) {
                return;
            }

            // 当滚动到顶部附近（距离顶部50px以内）时，加载更多
            // 但要确保不是刚初始化完成（scrollTop 为 0 或很小可能是初始化状态）
            var scrollTop = $messageContent.scrollTop();
            if (scrollTop <= 50 && scrollTop > 0) {
                loadMoreMessages();
            }
        });
    }

    /**
     * 加载消息（首次加载或切换群组）
     */
    window.loadMessages = function(groupId, groupName, event) {
        var $ = getJQuery();
        if (!$) {
            console.error('jQuery 未初始化，请先调用 initChat');
            return;
        }

        // 重置分页状态
        isInitializing = true; // 标记正在加载
        currentMessagePage = 1;
        currentGroupId = groupId;
        currentGroupName = groupName;
        hasMoreMessages = true;

        // 更新URL，但不刷新页面
        var url = new URL(window.location);
        url.searchParams.set('group_id', groupId);
        url.searchParams.delete('page'); // 移除page参数
        window.history.pushState({ groupId: groupId }, '', url);

        // 更新当前选中的群组
        $('.group-item').removeClass('active');
        if (event && event.currentTarget) {
            $(event.currentTarget).closest('.group-item').addClass('active');
        } else {
            // 通过 groupId 查找对应的群组项
            var $groupItem = $('.group-item[data-group-id="' + groupId + '"]');
            if ($groupItem.length) {
                $groupItem.addClass('active');
                // 滚动到可见区域
                $groupItem[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // 更新标题
        $('#current-group-name').text(groupName || '未命名群组');

        // 显示加载状态
        $('#message-content').html('<div class="loading"><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 加载中...</div>');

        // 加载消息（第一页）
        $.ajax({
            url: '/chat/messages',
            type: 'GET',
            data: { group_id: groupId, page: 1 },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    $('#message-content').html('<div class="empty-state">' + data.error + '</div>');
                    hasMoreMessages = false;
                    return;
                }

                // 渲染消息（倒序显示，所以需要反转数组）
                var html = '';
                if (data.messages && data.messages.length > 0) {
                    // 反转消息数组，使最新的消息显示在底部
                    var reversedMessages = data.messages.slice().reverse();
                    $.each(reversedMessages, function(index, message) {
                        html += renderMessage(message);
                    });

                    // 更新分页状态
                    if (data.pagination) {
                        hasMoreMessages = data.pagination.current_page < data.pagination.last_page;
                        currentMessagePage = data.pagination.current_page;
                    }
                } else {
                    html = '<div class="empty-state"><i class="layui-icon layui-icon-note" style="font-size: 48px; color: #ccc;"></i><div style="margin-top: 10px;">该群组暂无消息</div></div>';
                    hasMoreMessages = false;
                }

                $('#message-content').html(html);

                // 初始化滚动监听
                initMessageScrollListener();

                // 滚动到底部（最新消息在底部），然后解除初始化标记
                setTimeout(function() {
                    var $messageContent = $('#message-content');
                    $messageContent.scrollTop($messageContent[0].scrollHeight);
                    // 滚动完成后解除初始化标记
                    setTimeout(function() {
                        isInitializing = false;
                    }, 200);
                }, 100);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#message-content').html('<div class="empty-state"><i class="layui-icon layui-icon-close-fill" style="font-size: 48px; color: #f56c6c;"></i><div style="margin-top: 10px;">加载失败，请刷新重试</div></div>');
                hasMoreMessages = false;
            }
        });
    };

    /**
     * 加载更多消息（向上滚动时）
     */
    function loadMoreMessages() {
        var $ = getJQuery();
        if (!$ || !currentGroupId || isLoadingMessages || !hasMoreMessages) {
            return;
        }

        isLoadingMessages = true;
        var nextPage = currentMessagePage + 1;

        // 显示顶部加载提示
        var $messageContent = $('#message-content');
        var $loadingTip = $('<div class="loading" style="padding: 10px; text-align: center;"><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 加载中...</div>');
        $messageContent.prepend($loadingTip);

        // 记录当前滚动位置和第一个消息的高度
        var oldScrollTop = $messageContent.scrollTop();
        var oldScrollHeight = $messageContent[0].scrollHeight;
        var firstMessage = $messageContent.find('.message-item').first();
        var firstMessageHeight = firstMessage.length ? firstMessage.outerHeight(true) : 0;

        // 加载消息
        $.ajax({
            url: '/chat/messages',
            type: 'GET',
            data: { group_id: currentGroupId, page: nextPage },
            dataType: 'json',
            success: function(data) {
                $loadingTip.remove();
                isLoadingMessages = false;

                if (data.error || !data.messages || data.messages.length === 0) {
                    hasMoreMessages = false;
                    return;
                }

                // 将新消息插入到顶部
                // 后端返回的是倒序（最新的在前），但第2页、第3页等是更早的消息
                // 我们需要反转数组，使最旧的消息在顶部，较新的消息在底部
                var html = '';
                var reversedMessages = data.messages.slice().reverse();
                $.each(reversedMessages, function(index, message) {
                    html += renderMessage(message);
                });

                // 插入到顶部
                $messageContent.prepend(html);

                // 更新分页状态
                if (data.pagination) {
                    hasMoreMessages = data.pagination.current_page < data.pagination.last_page;
                    currentMessagePage = data.pagination.current_page;
                } else {
                    hasMoreMessages = false;
                }

                // 恢复滚动位置（保持用户看到的第一个消息位置不变）
                setTimeout(function() {
                    var newScrollHeight = $messageContent[0].scrollHeight;
                    var heightDiff = newScrollHeight - oldScrollHeight;
                    $messageContent.scrollTop(oldScrollTop + heightDiff);
                }, 10);
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $loadingTip.remove();
                isLoadingMessages = false;
                hasMoreMessages = false;
            }
        });
    }

    /**
     * 渲染单条消息
     */
    function renderMessage(message) {
        // 优先使用 display_name，否则使用 msgfrom
        var displayName = message.display_name || message.msgfrom || '未知用户';
        return '<div class="message-item">' +
                            '<div class="message-item-header">' +
                                '<span class="message-sender">' +
                    '<i class="layui-icon layui-icon-username"></i> ' + escapeHtml(displayName) +
                                '</span>' +
                                '<span class="message-time">' +
                                    '<i class="layui-icon layui-icon-time"></i> ' + message.formatted_time +
                                '</span>' +
                            '</div>' +
                            '<div class="message-body">' +
                                getMessageContent(message) +
                            '</div>' +
                        '</div>';
    }

    /**
     * 加载更多群组
     */
    window.loadMoreGroups = function() {
        var $ = getJQuery();
        if (!$) {
            console.error('jQuery 未初始化，请先调用 initChat');
            return;
        }

        if (!hasMoreGroups) return;

        $('#group-load-more').hide();
        $('#group-loading').show();

        currentGroupPage++;
        $.ajax({
            url: '/chat/groups',
            type: 'GET',
            data: { company_id: companyId, page: currentGroupPage },
            dataType: 'json',
            success: function(data) {
                $('#group-loading').hide();

                if (data.groups && data.groups.length > 0) {
                    var $groupListItems = $('#group-list-items');
                    var urlParams = new URLSearchParams(window.location.search);
                    var currentGroupId = urlParams.get('group_id');

                    $.each(data.groups, function(index, group) {
                        var isActive = currentGroupId && currentGroupId == group.group_id;
                        var $groupItem = $('<div>')
                            .addClass('group-item' + (isActive ? ' active' : ''))
                            .attr('data-group-id', group.group_id)
                            .attr('data-group-name', group.group_name)
                            .attr('data-group-type', group.type)
                            .html(
                                '<div class="group-item-header">' +
                                    '<div style="display: flex; align-items: center; flex: 1; min-width: 0;">' +
                                        '<i class="layui-icon ' + (group.type == 1 ? 'layui-icon-group' : 'layui-icon-user') + ' group-item-icon"></i>' +
                                        '<div class="group-item-name" title="' + escapeHtml(group.group_name) + '">' +
                                            escapeHtml(group.group_name) +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="group-item-info">' +
                                    '<span class="group-item-type">' +
                                        '<i class="layui-icon ' + (group.type == 1 ? 'layui-icon-group' : 'layui-icon-user') + '" style="font-size: 12px;"></i> ' + group.type_text +
                                    '</span>' +
                                    '<span class="group-item-time">' +
                                        '<i class="layui-icon layui-icon-time" style="font-size: 11px;"></i> ' + (group.updated_at_text || '暂无') +
                                    '</span>' +
                                '</div>'
                            )
                            .on('click', function(e) {
                                loadMessages(group.group_id, group.group_name, e);
                            });

                        $groupListItems.append($groupItem);
                    });

                    // 应用当前筛选
                    if (currentFilter !== 'all') {
                        filterGroups(currentFilter);
                    }

                    // 如果当前选中的群组在新加载的列表中，自动加载消息
                    if (currentGroupId) {
                        var loadedGroup = null;
                        $.each(data.groups, function(index, g) {
                            if (g.group_id == currentGroupId) {
                                loadedGroup = g;
                                return false;
                            }
                        });
                        if (loadedGroup) {
                            var $groupItem = $('[data-group-id="' + currentGroupId + '"]');
                            if ($groupItem.length && !$groupItem.hasClass('active')) {
                                setTimeout(function() {
                                    loadMessages(parseInt(currentGroupId), loadedGroup.group_name, null);
                                }, 100);
                            }
                        }
                    }

                    hasMoreGroups = data.pagination.current_page < data.pagination.last_page;
                    if (hasMoreGroups) {
                        $('#group-load-more').show();
                    }
                } else {
                    hasMoreGroups = false;
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#group-loading').hide();
                $('#group-load-more').show();
            }
        });
    };

    /**
     * 获取消息内容
     */
    window.getMessageContent = function(message) {
        if (message.msgtype === 'text') {
            return '<div class="message-text">' + escapeHtml(message.text || '') + '</div>';
        } else if (message.msgtype === 'image') {
            var imageUrl = message.media_url || message.media_path;
            if (imageUrl) {
                return '<div class="message-text"><i class="layui-icon layui-icon-picture"></i> [图片]</div>' +
                    '<div class="message-media">' +
                        '<img src="' + imageUrl + '" alt="图片" onclick="previewImage(\'' + imageUrl + '\')" />' +
                    '</div>';
            } else {
                return '<div class="message-text"><i class="layui-icon layui-icon-picture"></i> [图片] 媒体文件处理中...</div>';
            }
        } else if (message.msgtype === 'emotion') {
            var imageUrl = message.media_url || message.media_path;
            if (imageUrl) {
                return '<div class="message-text"><i class="layui-icon layui-icon-picture"></i> [表情图片]</div>' +
                    '<div class="message-media">' +
                        '<img src="' + imageUrl + '" alt="图片" onclick="previewImage(\'' + imageUrl + '\')" />' +
                    '</div>';
            } else {
                return '<div class="message-text"><i class="layui-icon layui-icon-picture"></i> [表情图片] 媒体文件处理中...</div>';
            }
        } else if (message.msgtype === 'video') {
            var videoUrl = message.media_url || message.media_path;
            if (videoUrl) {
                return '<div class="message-text"><i class="layui-icon layui-icon-video"></i> [视频]</div>' +
                    '<div class="message-media">' +
                        '<video controls>' +
                            '<source src="' + videoUrl + '" type="video/mp4">' +
                            '您的浏览器不支持视频播放' +
                        '</video>' +
                    '</div>';
            } else {
                return '<div class="message-text"><i class="layui-icon layui-icon-video"></i> [视频] 媒体文件处理中...</div>';
            }
        } else if (message.msgtype === 'voice') {
            var voiceUrl = message.media_url || message.media_path;
            if (voiceUrl) {
                var fileSize = message.media && message.media.filesize ? ' (' + Math.round(message.media.filesize / 1024 * 100) / 100 + ' KB)' : '';
                return '<div class="message-text"><i class="layui-icon layui-icon-voice"></i> [语音]' + fileSize + '</div>' +
                    '<div class="message-media">' +
                        '<audio controls preload="metadata" style="width: 100%; max-width: 300px;" onerror="console.error(\'音频加载失败:\', this.src)">' +
                            '<source src="' + voiceUrl + '" type="audio/mpeg">' +
                            '<source src="' + voiceUrl + '" type="audio/mp3">' +
                            '<source src="' + voiceUrl + '" type="audio/wav">' +
                            '<source src="' + voiceUrl + '" type="audio/ogg">' +
                            '<source src="' + voiceUrl + '" type="audio/aac">' +
                            '<source src="' + voiceUrl + '" type="audio/webm">' +
                            '您的浏览器不支持音频播放。如果音频无法播放，请<a href="' + voiceUrl + '" target="_blank" download>点击下载</a>' +
                        '</audio>' +
                    '</div>';
            } else {
                return '<div class="message-text"><i class="layui-icon layui-icon-voice"></i> [语音] 媒体文件处理中...</div>';
            }
        } else if (message.msgtype === 'file') {
            var fileUrl = message.media_url || message.media_path;
            if (fileUrl) {
                var fileName = fileUrl.split('/').pop() || '文件';
                return '<div class="message-text"><i class="layui-icon layui-icon-file"></i> [文件]</div>' +
                    '<a href="' + fileUrl + '" target="_blank" class="message-file" download>' +
                        '<i class="layui-icon layui-icon-download-circle message-file-icon"></i>' +
                        '<span>' + escapeHtml(fileName) + '</span>' +
                    '</a>';
            } else {
                return '<div class="message-text"><i class="layui-icon layui-icon-file"></i> [文件] 媒体文件处理中...</div>';
            }
        } else {
            return '<div class="message-text"><i class="layui-icon layui-icon-notice"></i> [' + message.msgtype + '] 暂不支持的消息类型</div>';
        }
    };

    /**
     * 预览图片
     */
    window.previewImage = function(url) {
        var $ = getJQuery();
        if (!$) {
            console.error('jQuery 未初始化，请先调用 initChat');
            return;
        }

        var $preview = $('#image-preview');
        if ($preview.length === 0) {
            var $div = $('<div>')
                .attr('id', 'image-preview')
                .addClass('image-preview')
                .html('<img src="' + url + '" alt="预览" />')
                .on('click', function() {
                    $(this).hide();
                });
            $('body').append($div);
            $div.show();
        } else {
            $preview.find('img').attr('src', url);
            $preview.show();
        }
    };

    /**
     * 转义HTML
     */
    window.escapeHtml = function(text) {
        var $ = getJQuery();
        if (!$) {
            // 如果 jQuery 未初始化，使用原生方法
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        var $div = $('<div>').text(text);
        return $div.html();
    };

    /**
     * 筛选群组
     * @param {string|number} filterType - 筛选类型：'all', 1, 2
     */
    window.filterGroups = function(filterType) {
        var $ = getJQuery();
        if (!$) {
            console.error('jQuery 未初始化，请先调用 initChat');
            return;
        }

        // 更新当前筛选类型
        currentFilter = filterType;

        // 更新筛选按钮状态
        $('.filter-btn').removeClass('active');
        $('.filter-btn[data-filter="' + filterType + '"]').addClass('active');

        // 筛选群组项
        var $groupItems = $('.group-item');
        var $groupCategories = $('.group-category');

        if (filterType === 'all') {
            // 显示所有群组
            $groupItems.show();
            $groupCategories.show();
        } else {
            // 根据类型筛选
            $groupItems.each(function() {
                var $item = $(this);
                var itemType = $item.attr('data-group-type');
                if (itemType == filterType) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });

            // 隐藏空的分类标题
            $groupCategories.each(function() {
                var $category = $(this);
                var $visibleItems = $category.find('.group-item:visible');
                if ($visibleItems.length > 0) {
                    $category.show();
                } else {
                    $category.hide();
                }
            });
        }

        // 如果没有可见的群组，显示提示
        var $visibleItems = $('.group-item:visible');
        var $emptyState = $('#group-list-items .empty-state');
        if ($visibleItems.length === 0) {
            if ($emptyState.length === 0) {
                $('#group-list-items').append(
                    '<div class="empty-state" style="padding: 40px; text-align: center;">' +
                    '<i class="layui-icon layui-icon-note" style="font-size: 48px; color: #ccc;"></i>' +
                    '<div style="margin-top: 10px; color: #999;">暂无符合条件的群组</div>' +
                    '</div>'
                );
            }
        } else {
            $emptyState.remove();
        }
    };
})();

