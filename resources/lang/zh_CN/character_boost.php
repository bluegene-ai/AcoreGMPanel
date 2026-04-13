<?php
return [
    'codes' => [
        'title' => '直升兑换码生成',
        'fields' => [
            'realm' => '服务器',
            'template' => '直升模板',
            'template_all' => '全部模板',
            'count' => '生成数量',
            'output' => '输出',
            'download' => '同时下载 txt 文件',
        ],
        'hint' => [
            'realm_from_server' => '随右上角服务器切换而变化',
            'count_limit' => '单次最多 10000。大量生成建议分批进行。',
            'download' => '勾选后会在写入数据库的同时下载 txt（便于留存/分发）。',
        ],
        'actions' => [
            'generate' => '生成兑换码',
        ],
        'generated' => [
            'title' => '生成结果',
            'hint' => '每行一个兑换码，可直接复制发放。',
        ],
        'success' => '已生成 :count 个兑换码。',
        'errors' => [
            'invalid_count' => '生成数量无效（范围 1-10000）。',
            'no_templates' => '当前服务器暂无可用模板。',
            'invalid_template' => '模板无效或不属于当前服务器。',
        ],
        'manage' => [
            'title' => '兑换码管理',
            'hint' => '仅允许删除“未使用”的兑换码；已使用的兑换码仅展示记录。',
            'fields' => [
                'template' => '直升模板',
                'unused_only' => '筛选',
                'unused_only_label' => '仅显示未使用',
            ],
            'stats' => [
                'title' => '统计',
                'total' => '总数',
                'unused' => '未使用',
                'used' => '已使用',
            ],
            'columns' => [
                'id' => 'ID',
                'template' => '模板',
                'code' => '兑换码',
                'status' => '状态',
                'used_by' => '使用信息',
                'created_at' => '创建时间',
                'actions' => '操作',
            ],
            'actions' => [
                'refresh' => '刷新',
                'purge_unused' => '清空未使用',
            ],
            'deleted' => '已删除未使用兑换码。',
            'purged' => '已清空 :count 条未使用兑换码。',
            'errors' => [
                'delete_failed' => '删除失败（可能已使用/不存在/不属于当前服务器）。',
            ],
        ],
    ],
    'templates' => [
        'title' => '直升模板',
        'create_title' => '创建直升模板',
        'edit_title' => '编辑直升模板 # :id',
        'edit_title_not_found' => '模板不存在',
        'create_heading' => '创建直升模板',
        'edit_heading' => '编辑直升模板 # :id',
        'columns' => [
            'name' => '名称',
            'target_level' => '目标等级',
            'money_gold' => '金币',
            'items' => '物品奖励',
            'class_rewards' => '职业套装奖励',
            'require_match' => '账号等级守卫',
            'actions' => '操作',
        ],
        'fields' => [
            'name' => '名称',
            'target_level' => '目标等级',
            'money_gold' => '金币(单位：金)',
            'require_match' => '账号等级守卫',
            'require_match_label' => '要求该账号已有角色最高等级 >= 目标等级',
            'items' => '物品(每行 entry:qty)',
            'class_rewards' => '职业奖励 tier（每行一个，例如 t2）',
        ],
        'hint' => [
            'realm' => '当前服务器 realm_id = :id',
            'items_format' => '示例：29434:1（每行一个，qty 可省略默认为 1）。',
            'class_rewards' => '可填写：t2（会按职业发放预设套装/兑换物）。',
        ],
        'actions' => [
            'create' => '创建模板',
            'edit' => '编辑',
            'delete' => '删除',
            'save' => '保存',
            'back' => '返回列表',
            'codes' => '兑换码生成',
            'public_redeem' => '打开公开兑换页',
        ],
        'empty' => '暂无模板',
        'saved' => '模板已保存。',
        'deleted' => '模板已删除。',
        'errors' => [
            'invalid_payload' => '参数无效（名称/等级/金币）。',
            'save_failed' => '保存失败（可能是名称重复或模板不存在）。',
            'delete_failed' => '删除失败（模板不存在或不属于当前服务器）。',
        ],
    ],
    'redeem' => [
        'title' => '兑换码直升',
        'fields' => [
            'realm' => '服务器',
            'template' => '直升模板',
            'template_loading' => '加载中…',
            'character_name' => '角色名',
            'code' => '兑换码',
        ],
        'hint' => [
            'template_auto' => '模板由兑换码决定（此处仅用于展示）。',
        ],
        'actions' => [
            'submit' => '兑换并直升',
        ],
        'success' => '兑换成功，直升已发放。',
        'errors' => [
            'invalid_code_format' => '兑换码格式无效（应为16位字母数字）。',
            'invalid_realm' => '服务器无效。',
            'code_not_found' => '兑换码不存在。',
            'code_used' => '兑换码已被使用。',
            'invalid_template' => '兑换码对应的模板无效。',
            'character_not_found' => '未找到指定角色。',
        ],
    ],
    'js' => [
        'modules' => [
            'character_boost' => [
                'common' => [
                    'ok' => '成功',
                    'error' => '错误',
                    'failed' => '失败',
                    'invalid_response' => '响应格式无效',
                    'network_error' => '网络异常',
                    'loading' => '加载中…',
                ],
                'templates' => [
                    'confirm' => [
                        'delete' => '确认删除模板 #:id 吗？',
                    ],
                ],
                'codes' => [
                    'table' => [
                        'empty' => '暂无兑换码',
                    ],
                    'status' => [
                        'used' => '已使用',
                        'unused' => '未使用',
                    ],
                    'actions' => [
                        'delete_unused' => '删除',
                    ],
                    'confirm' => [
                        'purge_unused' => '确认删除全部未使用兑换码？',
                        'delete_unused' => '确认删除这条未使用兑换码？',
                    ],
                    'pager' => [
                        'summary' => '第 :page / :pages 页 · 共 :total 条',
                    ],
                    'generated' => [
                        'download_ok' => '成功',
                        'download_ok_count' => '成功（:count）',
                        'template_named' => ':name (#:id)',
                        'template_fallback' => '模板 #:id',
                    ],
                    'usage' => [
                        'realm_suffix' => '（服务器 :id）',
                    ],
                ],
                'redeem' => [
                    'templates' => [
                        'empty' => '暂无模板',
                    ],
                    'realms' => [
                        'option' => '服务器 :id',
                    ],
                    'errors' => [
                        'load_options_failed' => '加载选项失败',
                    ],
                ],
            ],
        ],
    ],
];
