# v1.0.0-rc.1 — WEM Inquiry Final Review Candidate

> **Pre-release；不作为已经完成安全认证的正式版。**

## 本版包含
- 独立私密询盘 `[wem_inquiry_form]`，原生 WordPress `wp_comments` / `wp_commentmeta` 存储。
- 支持经典评论字段 `author`、`email`、`comment`、`phone`、`country` 的独立提交端点。
- 兼容旧版 Contact Us 使用 `wp-comments-post.php` 的 `phone`/`country`，以及 `comment_form()` 的 `wccf_phone`/`wccf_country`。
- 原生评论后台附加字段与独立询盘管理列表；防误批准，标准匿名 REST / 评论查询过滤。
- 中文 README：安装升级、底层原理、数据结构、安全边界、使用方法、测试清单。
- 长期保留人工回归测试模板（位于仓库 `tests/manual/`，不可视为生产页面）。

## 已有实测证据
旧联系表单与独立询盘字段正常保存；新询盘类型正确；误批准刷新后状态回到待审；无痕浏览器单条 REST 返回 `wem_inquiry_private` / 404。

## 仍待完成
验证管理员邮件实际送达；用无痕浏览器检查 REST 列表与 Feed 的覆盖范围；排除关联页面回环请求 HTTP 530 原因；检查旧普通评论隐私及站点缓存/CDN 行为。

**特别提醒：** 旧 `wp-comments-post.php` 保存的记录仍是普通评论，没有自动拥有独立询盘的隐私保护。停用本插件会移除它对 `wem_inquiry` 的公开查询保护。
