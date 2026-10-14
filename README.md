# WEM Inquiry — WordPress 轻量级询盘插件

基于 WordPress 原生 **Comments** 的企业询盘插件。主题自行控制表单 HTML、CSS 与交互；插件负责字段配置、提交校验、询盘保存、后台只读详情和常见公开接口的隐私防护。**不开发独立询盘管理系统，不创建专用数据库表。**

> **当前状态：v1.1.0 功能验收候选版。** Contact / Product 询盘提交、五种自定义字段、原生 Comments 审核、一键复制及隐私检测已通过测试站阶段性验收。尚未进行完整生产安全认证；本次仓库整理不代表已发布新的 Tag / Release。

## 核心能力

| 模块 | 说明 |
| --- | --- |
| 原生后台管理 | 直接使用 WordPress「评论 / Comments」列表、搜索、分页、Approve、Pending、Spam、Trash |
| 内置字段 | Name、Email、Phone、Country、Message |
| 自定义字段 | 最多新增 8 项，支持 Text、Textarea、Select、Radio、Checkbox；已有超额历史配置不会被自动截断 |
| 表单接入 | 主题自定义 HTML（推荐）；保留 `[wem_inquiry_form]` Shortcode 兼容 |
| 数据存储 | `wp_comments` + `wp_commentmeta`，询盘类型为 `wem_inquiry` |
| 后台详情 | 来源链接、联系方式、实际保存的扩展字段、IP / User Agent 只读展示 |
| Copy Inquiry | 复制业务字段、消息、来源和提交时间；默认不复制 IP / User Agent |
| 隐私检测 | 管理员主动执行 REST、评论 Feed、前台 HTML 的虚构数据匿名回环检测 |

## 项目目录

本仓库以 **插件源码作为根目录**，不再额外嵌套同名文件夹：

```text
wem-inquiry/                  # GitHub 仓库根目录
├── wem-inquiry.php          # WordPress 插件入口（v1.1.0）
├── includes/
│   ├── class-fields.php       # 字段定义、配置和验证
│   ├── class-form.php         # Shortcode、提交及隐藏字段
│   ├── class-storage.php      # Comments / Comment Meta 存储
│   ├── class-admin.php        # 字段配置及原生评论详情展示
│   ├── class-privacy.php      # 公开评论、REST、Feed 的隐私保护
│   ├── class-diagnostics.php  # 管理员一键隐私检测
│   └── class-legacy.php       # 历史评论字段兼容
├── docs/
│   ├── FORM-GUIDE.md       # 表单接入完整指南
│   └── FINAL-REVIEW.md     # 测试记录与风险边界
├── README.md               # 本文档
└── .gitignore
```

旧版 PHP 代码、临时测试模板和重复 README 不必保留在工作树中；原有 Git 提交与 Tag 历史仍可追溯。

## 环境与安装

- WordPress **6.4+**、PHP **7.4+**（插件头部声明的最低要求）。
- 先备份网站数据库和旧插件，在测试站替换验收后再考虑生产部署。
- **GitHub 仓库结构与 WordPress 安装 ZIP 不同**：从本仓库制作插件 ZIP 时，压缩包顶层必须包含 `wem-inquiry/` 目录，其内部再放 `wem-inquiry.php`、`includes/`、`docs/`、`README.md`。不要将仓库根目录文件直接压缩为没有插件目录的 ZIP。
- 安装后进入 **设置 → WEM Inquiry Fields** 配置字段，进入 **设置 → WEM Inquiry Privacy** 运行隐私检测；日常询盘在 **评论（Comments）** 中管理。

## 后台字段设置

五个内置字段中 Name、Email、Message 由服务端要求必填，Phone、Country 默认可选。自定义字段需要设置 **Field Key、Label、Type、Required**；Select / Radio 还需填写每行一个的 Options。字段 Key 应使用稳定的小写字母和下划线，已有历史数据后不要随意更名。

以 Contact 的测试字段为例：

| Field Key | Label | Type |
| --- | --- | --- |
| `company` | Company | Text |
| `project_details` | Project Details | Textarea |
| `request_samples` | Request Samples | Checkbox |
| `product_interest` | Product Interest | Select |
| `customer_type` | Customer Type | Radio |

后台删除一个字段的**配置**不会删除此前保存的 Comment Meta；历史详情仍尽量展示实际保存的数据。后台详情只显示该询盘真正存储过的自定义字段，不会因全站新增字段而出现一堆空项。

## 表单接入与字段范围

**推荐方式：在主题中自行编写 `<form>`，让插件处理提交。** 不必强制使用 Shortcode 来生成整套表单外观。

```php
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wem_inquiry_form_hidden_fields( get_queried_object_id() ); ?>
    <label for="wem-name">Name</label>
    <input id="wem-name" name="wem_name" required>
    <label for="wem-email">Email</label>
    <input id="wem-email" type="email" name="wem_email" required>
    <label for="wem-message">Message</label>
    <textarea id="wem-message" name="wem_message" required></textarea>
    <button type="submit">Send Inquiry</button>
</form>
```

仅在已启用插件、且来源文章可公开访问的情况下输出真实提交表单；生产模板需要判断 `function_exists( 'wem_inquiry_form_hidden_fields' )` 并处理回退。上方只是**最小接入示例**，更多字段 HTML、验证、状态反馈和缓存注意事项参见 [表单接入指南](docs/FORM-GUIDE.md)。

- 产品详情 `post_type=product`：默认只接收内置五字段，不需要提交 Contact 的扩展字段。
- Contact 等其他公开来源：允许提交后台配置的自定义字段，具体服务端必填要求按来源适用规则执行。
- 服务端按真实来源决定允许字段，不依赖客户端声明。
- Shortcode `[wem_inquiry_form]` / `[wem_inquiry_form post_id="123"]` 继续保留向后兼容，不作为本主题主要布局方案。
- 成功和失败跳转状态为 `wem_inquiry_status=sent|error`；`sent` 表示存储成功，不代表邮件已发送，插件本身不发送通知邮件。

## 数据与历史兼容

| 信息 | 存储位置 |
| --- | --- |
| 姓名、邮箱、正文、来源文章 ID、时间、IP、User Agent、审核状态 | `wp_comments` |
| 私密询盘标识 | `comment_type = wem_inquiry` |
| 电话、国家 | Comment Meta：`phone`、`country` |
| 新自定义字段 | Comment Meta：`_wem_inquiry_{field_key}` |
| 字段配置 | Options：`wem_inquiry_fields` |

旧的 Phone / Country、普通评论兼容代码和原来的 Shortcode 均保留。旧普通评论不会自动迁移为私密询盘，也不会因为插件安装而自动得到私密保护。

## 隐私、安全与已知限制

- **Approve 不等于公开询盘。** 已批准的 `wem_inquiry` 仍应被插件从标准匿名评论查询、REST 读取和评论 Feed 中排除。
- 在测试站，一键隐私检测曾发现评论 Feed 暴露虚构的 Approved 测试记录；修复后复测的 REST、Feed、来源页 HTML 均未发现相同测试标识。**这一结论仅覆盖当次检测路径。**
- 检测使用服务器侧无登录 Cookie 的 HTTP 回环请求，不等于真实外部渗透测试；对第三方自定义 SQL/API、缓存历史副本、插件停用后的风险不做安全保证。
- **不要在含真实客户资料的正式网站上停用本插件测试隐私保护。** 正式环境应管理访问权限、备份和保留策略。
- 目前自定义 Textarea 的清洗可能压缩换行；按既定验收决定暂不修改。对确需保留原始段落的业务应另行评估。
- IP 和完整 User Agent 仅用于后台诊断，不包含在默认一键复制文本中。

## 测试与维护

发布前至少检查 Contact / Product 匿名提交、原生 Comments 审核与垃圾箱、五种自定义字段、旧 Meta、Copy Inquiry、缓存、REST 与 Feed 外部匿名访问。详见 [Final Review](docs/FINAL-REVIEW.md)。

项目的方向是 **小核心、原生管理、表单外观自主、历史数据不迁移**。目前不扩展独立询盘管理列表、Ajax、邮件系统、文件上传或多表单可视化构建器。

## 版本状态

- `v1.0.0`：旧版表单/Comments 数据与历史兼容基础，历史内容可从 Git 提交或既有 Tag 查看。
- `v1.1.0`：架构重写及测试站功能验收候选。本次整理用于准备 GitHub Commit，**未自动创建 Commit、Tag 或 Release**。

许可证：GPL-2.0-or-later（以插件头部及仓库授权文件为准）。
