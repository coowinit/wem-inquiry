# WEM Inquiry — WordPress 表单接入指南

## 1. 决定哪些字段显示在哪里

WEM Inquiry 的后台字段管理相当于**服务端字段白名单**，并不是自动控制所有主题页面的布局。主题自己决定字段 HTML、CSS 与交互。

| 来源 | 插件允许的字段 | 主题推荐 |
|---|---|---|
| Product CPT | 内置 Name、Email、Phone、Country、Message | 产品详情弹窗只显示五项 |
| Contact Page（及其他非 Product 来源） | 五个内置字段 + 后台配置的自定义字段 | 联系页面按需显示这些字段；若字段 Required，必须实际提供 |

目前默认以 Post Type 判断，**不是可视化多表单配置器**。如同一非 Product 来源需要不同字段组合，应由开发者使用 `wem_inquiry_fields_for_source` 过滤器在服务端定义，不可只靠隐藏前端字段跳过服务器校验。

## 2. 后台配置示例

进入 **设置 → WEM Inquiry Fields**；点击 Add Field 新增配置，保存后刷新确认持久化。

| Key | Label | Type | 选项（每行一个） |
|---|---|---|---|
| `company` | Company | Text | 无 |
| `project_details` | Project Details | Textarea | 无 |
| `request_samples` | Request Samples | Checkbox | 无 |
| `product_interest` | Product Interest | Select | Outdoor Products / Indoor Products / Custom Products |
| `customer_type` | Customer Type | Radio | Distributor / Contractor / Other |

这五项默认可选。选项中的文字必须与 HTML 的 value **严格一致**，否则服务器拒绝保存该次提交。字段 Key 只能稳定使用，删配置不删旧数据。

## 3. 自定义 HTML：完整 PHP 例子

以下示例是**非 Product 来源**（例如 Contact Page）的 PHP 模板代码。将 CSS Class 换成你自己的主题样式即可。

```php
<?php
$post_id = get_queried_object_id();
if (function_exists('wem_inquiry_form_hidden_fields')) :
    $status = isset($_GET['wem_inquiry_status']) && is_string($_GET['wem_inquiry_status'])
        ? sanitize_key(wp_unslash($_GET['wem_inquiry_status'])) : '';
?>
    <?php if ($status === 'sent') : ?>
        <p role="status" class="form-success">Thank you. Your inquiry has been received.</p>
    <?php elseif ($status === 'error') : ?>
        <p role="alert" class="form-error">Submission failed. Please check your fields.</p>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wem_inquiry_form_hidden_fields($post_id); ?>
        <label>Name <input type="text" name="wem_name" maxlength="120" required></label>
        <label>Email <input type="email" name="wem_email" maxlength="190" required></label>
        <label>Phone <input type="tel" name="wem_phone" maxlength="80"></label>
        <label>Country <input type="text" name="wem_country" maxlength="120"></label>

        <label>Company <input type="text" name="wem_cf_company"></label>
        <label>Project Details <textarea name="wem_cf_project_details" maxlength="3000"></textarea></label>
        <label>Product Interest
            <select name="wem_cf_product_interest">
                <option value="">Select...</option>
                <option value="Outdoor Products">Outdoor Products</option>
                <option value="Indoor Products">Indoor Products</option>
                <option value="Custom Products">Custom Products</option>
            </select>
        </label>
        <fieldset><legend>Customer Type</legend>
            <label><input type="radio" name="wem_cf_customer_type" value="Distributor"> Distributor</label>
            <label><input type="radio" name="wem_cf_customer_type" value="Contractor"> Contractor</label>
            <label><input type="radio" name="wem_cf_customer_type" value="Other"> Other</label>
        </fieldset>
        <label><input type="checkbox" name="wem_cf_request_samples" value="1"> Request Samples</label>
        <label>Message <textarea name="wem_message" maxlength="10000" required></textarea></label>
        <button type="submit">Send Inquiry</button>
    </form>
<?php endif; ?>
```

**重要：** 这是主题 PHP 代码，不是直接粘贴到 WordPress 页面正文中的纯 HTML。`wem_inquiry_form_hidden_fields` 的内部输出包含服务器 Nonce，不能写死，也不要用 JS 自行生成。确保来源页面已发布并允许公开访问。禁用插件时主题应提供 Contact 链接或其他回退，而不是展示无法提交的表单。

## 4. 产品详情页

产品详情页同样使用上面的 `<form method="post">` 与隐藏字段函数，但**只输出五个内置字段**。不要把后台全站自定义字段循环输出到产品弹窗，也不要再调用 Shortcode 渲染产品弹窗。来源 ID 请传当前产品 ID，而非联系页 ID，以便 Comments 正确显示 Source。

## 5. Shortcode（向后兼容）

```text
[wem_inquiry_form]
[wem_inquiry_form post_id="123"]
```

Shortcode 由插件自动生成当前来源允许的字段。布局属于插件的默认 HTML，不保证与已有主题 Contact/Product 表单样式相同。新主题优先使用自定义 HTML；旧站无需强行删除现有 Shortcode。

## 6. 数据存储、反馈与排错

- 标准字段名：`wem_name`、`wem_email`、`wem_phone`、`wem_country`、`wem_message`。
- 自定义字段名：`wem_cf_` + 后台 Key。Phone/Country 不要重命名历史 Meta Key。
- 提交成功后返回来源 URL 并带 `wem_inquiry_status=sent`；失败返回 `error`。表单 JS 不应无条件阻止浏览器 POST；如自行校验，可在验证通过时允许提交。
- 错误排查：检查字段的 name、选项 value、后台 Required、来源 Post ID、Nonce、插件启用、60 秒限流以及浏览器 Network 的 303 重定向。失败状态并不提供每个字段的详细错误说明。
- 不要在前端显示客户历史询盘内容。WordPress Comments 管理页的 Approved 状态不是公开授权。
- 现有 PHP 验证只检查内置 Name/Email/Message 的基本要求；例如某个主题把 Country 标为必填，不能因此推断插件服务端也强制 Country 必填。
- IP 和浏览器信息仅供授权后台诊断；默认 Copy Inquiry 不包含这两项。跨平台转发复制内容时应注意客户隐私。

## 7. 建议回归顺序

1. Contact 的五种自定义字段保存、后台详情和复制；2. 产品仅五个内置字段；3. Approved/Spam/Trash；4. 后台隐私一键检测；5. 无痕浏览器检查 REST、Feed 与公开评论输出。测试失败时先核实缓存，再分析插件本身。
