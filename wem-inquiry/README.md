# WEM Inquiry

轻量级 WordPress 询盘与历史评论字段兼容插件。**v1.0.0-rc.1（正式发布候选，尚未封板）**。以 WordPress 原生 `wp_comments` 和 `wp_commentmeta` 保存数据，不建自定义数据库表。兼顾新网站的独立询盘表单与旧网站的 `Phone / Country` 评论表单。

> **最重要的区别**：`[wem_inquiry_form]` 创建 `comment_type=wem_inquiry` 的独立询盘；旧主题提交到 `wp-comments-post.php` 创建的是**普通评论**。普通评论没有本插件的独立询盘隔离保护。旧版的“前台不显示评论”不等于“数据不可被匿名读取”。发布生产环境前须分别验证两个入口的隐私行为。

## 1. 功能与范围

| 功能 | 独立询盘 | 旧评论兼容 |
| --- | --- | --- |
| 提交入口 | `[wem_inquiry_form]` → `admin-post.php` | `comment_form()` 或旧主题 `<form>` → `wp-comments-post.php` |
| 存储 | `wp_comments` + `wp_commentmeta` | 相同表 |
| `comment_type` | `wem_inquiry` | WordPress 普通评论类型 |
| 电话、国家元数据键 | `phone`、`country` | `phone`、`country` |
| 页面是否需要开放评论 | 不需要 | 需要遵循 WordPress 评论开放与提交规则 |
| 后台 | 评论 → Inquiries | 评论 → 所有评论 |
| 隔离 | 插件针对标准公开评论查询/REST 进行防护 | **没有自动转为私密** |
| 邮件 | 尝试通知管理员 | 沿用 WordPress 评论通知规则 |

不提供：附件上传、CRM、询盘 CSV 导出、自动回复、独立数据表、多图、整套反垃圾服务及自动迁移历史普通评论。

## 2. 原理和数据流

### 2.1 新的独立询盘

```text
页面 / Product 详情中的 [wem_inquiry_form]
    └─ name / email / phone / country / message
          ↓ POST（Nonce + 蜜罐 + 基础限流）
/wp-admin/admin-post.php?action=wem_inquiry_submit
          ↓ 数据校验、清理
wp_insert_comment()
    ├─ wp_comments: comment_type=wem_inquiry, comment_approved=0,
    │               comment_post_ID、姓名、邮箱、留言、时间
    └─ wp_commentmeta: phone、country
          ↓
wp_mail() 尝试通知站点管理员
          ↓
303 重定向原关联文章 URL，显示成功/失败消息
```

`wp_mail()` 返回或服务器投递情况不决定记录是否已保存：**邮件发送失败时已保存记录仍保留**。表单成功消息只代表数据写入流程成功，不代表邮件投递成功。`wem_inquiry_status=sent` 是 URL 状态提示，可人工修改，不能视为不可伪造的提交证明。

### 2.2 旧版兼容流程

```text
旧主题的 Contact Us HTML 表单 / WordPress comment_form()
          ↓ POST
/wp-comments-post.php
          ↓ WordPress 校验、插入普通评论
comment_post Hook
          ↓ 插件读取 phone/country 或 wccf_phone/wccf_country
wp_commentmeta: phone、country
          ↓
后台“评论”列表和评论编辑页面显示/编辑 Phone、Country
```

原站手写页面字段如下即可被兼容：

```html
<form method="post" action="/wp-comments-post.php">
  <!-- WordPress 原生字段：author、email、comment、comment_post_ID、comment_parent -->
  <input name="phone" type="text">
  <input name="country" type="text">
</form>
```

**以上只是字段命名说明，并非可直接使用的完整表单。** 实际旧模板仍需包含 WordPress 必要字段、有效文章 ID、表单校验及安全设置。旧的 `page-contact-us.php` 可继续使用，插件不要求重写页面布局。

## 3. 安装与升级

**建议环境**：WordPress 6.4+，PHP 7.4+（实际部署应使用仍在维护的 PHP 版本）。

1. 备份数据库（尤其 `wp_comments`、`wp_commentmeta`）以及当前插件代码。
2. 停用旧版 **Custom Comment Fields**，避免表单字段、管理列重复；不能和本插件同时启用。
3. 将 ZIP 通过 WordPress 后台 **插件 → 安装插件 → 上传插件** 安装；或解压 `wem-inquiry/` 至 `wp-content/plugins/`。
4. 启用 **WEM Inquiry**。升级当前测试版时，使用同名 `wem-inquiry` 目录覆盖，保持 WordPress 站点备份。
5. 测试旧联系页面提交姓名、邮箱、留言、电话、国家，并在 **评论** 后台检查保存、编辑、清空是否正常。
6. 在新的 Page 或 Product 详情插入 `[wem_inquiry_form]`，测试私密询盘和 `评论 → Inquiries`。
7. 验证匿名 API、Feed、邮件、缓存、日志后才用于真实访客。

停用插件不会主动删除 `wp_comments`、`wp_commentmeta` 中的数据，但**停用后私密询盘的插件层隔离也会停止**；生产站点不要仅为排错长期停用防护。切勿把已有普通评论直接批量 SQL 修改为私密询盘，先备份并制定转换与回归方案。

## 4. 新主题使用：Shortcode

在 Page / Post / 公开 CPT 内容中：

```text
[wem_inquiry_form]
```

可明确绑定产品（例：ID 52）：

```text
[wem_inquiry_form post_id="52"]
```

PHP 模板中：

```php
<?php echo do_shortcode( '[wem_inquiry_form]' ); ?>
```

Shortcode 只对已发布且前台可访问的文章或内容类型输出；默认关联当前查询对象。主题可通过 `.wem-inquiry-form` 以及元素选择器设置样式。插件不强制加载全站 CSS/JS，不控制表单视觉风格。联系页面如需专属 UI，可由主题定制。注意确认隐藏字段 `wem_post_id` 对应正确的页面/产品。

字段：Name、Email、Message 必填；Phone、Country 可选。新的独立询盘不要求目标文章启用普通评论。

## 5. 旧网站兼容：字段对应表

| 提交场景 | 请求字段 | 写入 comment meta |
| --- | --- | --- |
| 旧手写表单 | `phone` / `country` | `phone` / `country` |
| `comment_form()` | `wccf_phone` / `wccf_country` | `phone` / `country` |
| 独立询盘 Shortcode | `wem_phone` / `wem_country` | `phone` / `country` |

- 旧 `comment_form()` 自动增加电话和国家输入框（支持已登录/未登录访客）。
- 历史 Meta Key 未更改，已有值不需要数据库迁移。
- `comment_post` Hook 负责保存旧表单附加字段；字段值被清理并限制长度。
- `comment_form()` 额外提交 `wccf_fields_nonce`；若提交该字段，则必须通过验证；为了兼容旧手写表单，**没有该 nonce 的请求也可以保存附加字段**。这不是与新独立询盘同等级的安全模型。
- 旧普通评论仍然是普通评论，是否公开由 WordPress 评论机制和站点其他代码决定。
- 旧表单原本未保存的空字段无法自动恢复；升级只保证未来请求的保存与已有历史值读取。

## 6. 后台管理

### 私密询盘

后台 **评论 → Inquiries**：只读概览列表，列出日期、关联页面、姓名、邮箱、电话、国家和留言摘录。点击姓名使用 WordPress 原生评论编辑界面，更改电话和国家。仅具有 `moderate_comments` 权限的用户可访问该列表。

### 普通评论

后台 **评论 → 所有评论** 增加 Phone、Country 列。普通评论编辑页增加对应字段，并保持旧数据。保存时校验编辑权限和专用 Nonce。

### 邮件

新询盘会尝试通过 `wp_mail()` 给 **设置 → 常规 → 管理员电子邮件地址**发送通知。主机必须具备可用邮件发送配置；同时检查垃圾邮件箱及邮件送达日志。邮件包含客户联系方式和留言，请评估邮箱安全、转发链路及数据保留策略。

## 7. 安全与隐私边界

**独立询盘：** 默认写为待审状态，阻止常规批准流把它变为已批准；对无 `moderate_comments` 权限的标准评论查询追加类型过滤，并拦截 REST `/wp/v2/comments/{id}` 的直接读取。提供 Nonce、蜜罐与每 IP+页面 60 秒基础限流。

**不能将其理解为绝对隔离：** 这不覆盖数据库直接读取、其他插件自行 SQL 查询、非标准 API 或被入侵的管理员账户；插件停用也会取消过滤保护。公开 Nonce 并非身份认证或强反垃圾机制。生产环境建议进行 API/Feed/主题自定义接口审查、日志监控和反垃圾策略检查。

**普通评论：** 不自动隐藏、不自动转换为私密询盘。若历史 Contact Us 留言包含客户个人信息，必须单独检查它们的审核状态、公开评论、REST API、评论 Feed、第三方接口和搜索引擎收录。仅在模板中删除 `comments_template()` 并不足够。

**隐私运营：** 邮箱通知、备份、副本、访问权限、保留期限和删除请求均需站点负责人落实。插件不会自动加密数据库内容。

## 8. 实站回归测试清单

- [ ] 旧联系页 `phone` / `country` 提交成功，后台读取正确。
- [ ] 原生 `comment_form()` 的 `wccf_phone` / `wccf_country` 提交成功。
- [ ] 历史评论电话、国家正确显示，编辑、清空、保存成功。
- [ ] 新页面 `[wem_inquiry_form]` 可匿名提交，关联 `comment_post_ID` 正确。
- [ ] 新表单无效邮箱、空留言、错误 Nonce、蜜罐和 60 秒限流正确处理。
- [ ] 目标文章关闭评论时，新独立表单仍可提交。
- [ ] `评论 → Inquiries` 中新记录为 `wem_inquiry`，初始待审。
- [ ] 匿名 REST 评论列表、单条 REST、评论 Feed、主题评论展示均无法取得新询盘。
- [ ] 检查 **旧普通评论** 中客户信息是否经任何公开接口暴露。
- [ ] 管理员误批准新询盘后仍不公开；停用及重新启用插件的风险可控。
- [ ] 通知邮件实际送达；模拟邮件发送失败时数据库记录仍保留。
- [ ] 有页面缓存、CDN 时分别测试匿名表单 Nonce；观察 PHP 和浏览器错误日志。

建议至少在未登录浏览器、管理员登录浏览器和不同设备/缓存状态下交叉验证。静态 PHP 语法检查不能替代 WordPress 实站安全验收。

## 9. 本轮实站验收记录（2026-10-11）

以下仅记录**已提供截图或由用户明确反馈**的事实，不代表完成渗透测试：

| 项目 | 本轮结果 | 说明 |
| --- | --- | --- |
| 旧版 Contact Us 的 Phone、Country | 已通过 | 用户在旧站实测保存与后台显示 |
| 新独立询盘提交、关联文章、Phone/Country | 已通过 | 管理后台截图与测试页验证 |
| `comment_type=wem_inquiry` | 已通过 | 诊断读取数据库状态 |
| 管理员误批准后状态 | 已通过初步验证 | 批准后刷新，状态仍为 `0` |
| 匿名 REST 单条 `/wp/v2/comments/1452` | 已通过本次请求 | 返回插件 `wem_inquiry_private`、404 |
| REST 列表及评论 Feed | 仅本次未发现 / 部分无法判断 | 多数 HTTP 200 未发现测试邮箱；两项关联页自请求为 HTTP 530 |
| 邮件实际送达 | 尚未验收 | 需要检查邮箱和站点邮件日志 |
| 禁用插件时的公开访问风险 | 尚未验收 | 禁用会移除保护，禁止在有真实数据的生产站点试验 |
| 缓存/CDN 和其他插件自定义公开接口 | 尚未验收 | 需要部署环境专项检查 |

测试页仅用于人工回归，不是安全认证工具。HTTP 200、未出现某一个邮箱、HTTP 530 均不足以证明接口绝对安全。

## 10. 文件结构

```text
wem-inquiry/               # 可安装的生产插件目录
├── wem-inquiry.php        # 表单、提交、存储、隐私保护、后台、旧站兼容
└── README.md              # 原理、安装、兼容、安全与回归测试

tests/manual/             # 仓库测试资料，不部署到插件目录
├── page-wem-inquiry-test.php
└── README.md
```

目前保持单文件 PHP 核心，避免为少量功能过度拆分。`tests/manual/` 仅供测试站点使用，不随生产安装目录发布。后续若业务量增长，再按 Form / Storage / Admin / Legacy 模块拆分。

## 11. 版本历史与发布建议

- **v0.1.0 RC**：独立询盘短代码、评论存储、后台查看与隐私过滤基础。
- **v0.1.1 RC**：恢复旧评论表单字段、历史字段读取及后台编辑。
- **v0.1.2 RC**：兼容原站手写 `phone`、`country` 字段保存；已在旧站由用户验证。
- **v0.1.3 RC**：整理完整技术与使用文档；增加空 Post Type / `WP_Error` 的防御性检查；不改变已验证的数据协议。

推荐先发布 **v1.0.0-rc.1 Pre-release**。完成剩余邮件、缓存和匿名查询边界验收后，再发布正式 `v1.0.0`。

许可证：**GPL-2.0-or-later**。
