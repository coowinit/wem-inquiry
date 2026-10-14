# WEM Inquiry v1.1.0 — Final Review（候选版）

## 已完成的源码/结构核查

- 插件入口、7 个 PHP 模块、后台字段设置、Shortcode 和主题隐藏字段函数均存在。
- 统一 POST 端点与 `wp_comments` / `wp_commentmeta` 数据契约未变；没有数据库迁移脚本。
- 产品来源的自定义字段范围由服务器端 Post Type 决定；其他来源读取现有字段配置。
- 编辑页只读详情只显示实际保存的自定义字段，缺少 IP/User Agent 的历史记录显示 Not recorded；Copy Inquiry 不复制环境信息。
- `comments_clauses`、`comment_feed_where`、REST 单条拦截、评论渲染过滤共同构成有限的隐私防线。

## 已由测试站反馈通过的范围

- Contact 页面与 Product 页面真实提交、来源关联与成功/失败反馈。
- Comments 原生 Approve、Unapprove、Spam、Trash。
- 五种自定义字段 Text、Textarea、Select、Radio、Checkbox 及配置持久化。
- 隐私检测曾发现 Feed 泄露；修复后再次运行，两个 Feed 均未发现测试标识。
- 只读详情、来源、IP/User Agent、Copy Inquiry、按实存字段显示及 Add/Remove 和 8 字段设置。

这些属于用户测试站反馈，不代表自动化集成测试或生产安全认证。

## 仍需关注的风险与不足

1. **匿名访问隐私并非绝对保证**：插件停用、第三方自行 SQL、非标准 REST、自定义缓存等不一定经过保护过滤器；Approved 询盘不应被任何主题公开显示。
2. **一键检测是服务器回环测试**：只检测有限的 HTTP 路径。状态 200 且未发现标识只代表此次样本无泄露；站外无痕浏览器验证仍必要。
3. **第三方反垃圾插件不一定处理 `wp_insert_comment()`**：Spam 的后台手工操作正常，不等于 Akismet/其他服务自动拦截新提交。
4. **自定义字段不是多表单系统**：非 Product 来源默认处理全部已注册字段；若配置 Required，主题必须提供该字段，除非通过服务器端 Filter 缩小字段范围。
5. **输入边界**：`validate_customs()` 用 `sanitize_text_field()` 清洗自定义 Textarea，会合并部分换行；如确有保留多行需求，需单独修复并回归测试。
6. **数据治理**：IP/User Agent 为可能识别个人的数据，需控制后台授权、保留期、备份和复制分享范围。
7. **历史普通评论**：旧 `wp-comments-post.php` 产生的是普通评论，可能不具备 `wem_inquiry` 私密类型的保护。
8. **上限策略**：新配置为最多 8 项；不应通过删除配置清空历史元数据。对于已有超过 8 项的站点，正式升级前请在测试站核对配置变更。
9. **复制功能**：剪贴板权限由浏览器控制，可能失败；即使复制成功也应遵守客户资料分享规范。

## 发布前回归清单

- [ ] 保留旧站历史询盘、Phone/Country、已删除配置字段数据。
- [ ] Contact 提交五种字段并刷新后台验证；Product 仅提交五内置字段。
- [ ] 字段 Add/Remove/8 上限与已保存字段的兼容行为。
- [ ] Comments 搜索、分页、Approved/Pending/Spam/Trash/Restore。
- [ ] Copy Inquiry 包含来源、提交时间、正文与实际保存的自定义字段，不包含 IP/UA。
- [ ] REST 单条/列表、全站和来源 Feed、来源 HTML 匿名检查。
- [ ] PHP/JavaScript 错误日志、移动端表单、缓存与重定向。
- [ ] 不在生产环境停用插件开展隐私测试。

## 当前阶段决策

这一轮以**文档与静态审计**为主，不新增功能、不调整已验收的存储与隐私代码。发现的新问题如自定义 Textarea 的换行清洗，应在用户决定是否需要该行为后独立修复和验收，而不是悄悄引入未经测试的逻辑变更。待最终回归确认后再做 GitHub Commit / Release。
