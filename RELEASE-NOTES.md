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
用无痕浏览器检查 REST 列表与 Feed 的覆盖范围；排除关联页面回环请求 HTTP 530 原因；检查旧普通评论隐私及站点缓存/CDN 行为。

**特别提醒：** 旧 `wp-comments-post.php` 保存的记录仍是普通评论，没有自动拥有独立询盘的隐私保护。停用本插件会移除它对 `wem_inquiry` 的公开查询保护。

## main 分支后续调整（尚未包含在 rc.1 Tag 中）
- 根据使用需求移除独立询盘中的 `wp_mail()` 管理员通知。
- 独立询盘仍保存至原生 Comments / Comment Meta，由管理员进入 Inquiries 查看。
- 旧普通评论通知行为不变，仍由 WordPress 自身设置控制。


## v1.0.0 功能封板后变更（main 分支）

- 移除独立询盘管理员邮件通知；仅由管理员在后台查看。
- 在编辑询盘的联系信息框新增只读 **Source Page**，通过 `comment_post_ID` 定位来源页面。原生 **URL** 仍表示 `comment_author_url`。
- 核心功能冻结，后续新需求留待 v2；REST / Feed 的未完成安全边界检查继续如实保留。
