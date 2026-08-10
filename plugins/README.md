# 插件开发

每个插件放在 `plugins/<slug>/`。目录名只能包含小写字母、数字、连字符和下划线，并必须包含 `plugin.json` 与 `plugin.php`：

```json
{
  "name": "插件名称",
  "version": "1.0.0",
  "author": "作者",
  "url": "https://example.com/plugin-author",
  "description": "插件说明",
  "settings_action": "admin_example",
  "exclusive_group": "language"
}
```

`settings_action` 可选。填写后，插件启用时“插件管理”会显示设置入口；对应 action 应由插件的 `request` 回调处理。内置功能插件以此作为唯一设置入口，不额外注册侧边栏菜单。

`url` 可选，用于插件管理中的作者链接，只接受完整的 HTTP 或 HTTPS 地址。`exclusive_group` 也可选；启用插件时，同一互斥组内已启用的其他插件会自动停用，适合语言包等不能同时工作的插件。

安装后进入“后台 -> 插件管理”启用。插件 PHP 是服务器端可信代码，只安装来源可信的插件。

## Action

使用 `add_plugin_action($hook, $callback, $priority)` 注册：

```php
add_plugin_action('post_saved', static function (array $context): void {
    error_log('Saved post: ' . $context['post_id']);
});
```

核心提供以下 action：

- `plugins_loaded`：所有已启用插件加载完成
- `request`：路由确定后、执行页面逻辑前
- `post_saved`：文章或独立页面保存后
- `comment_created`：评论写入数据库后
- `plugin_status_changed`：管理员启用或停用插件后

回调接收一个 `$context` 数组。插件抛出的异常会写入 PHP error log，不会中断其他回调。

## Filter

使用 `add_plugin_filter($hook, $callback, $priority)` 注册，数值越小越先执行：

```php
add_plugin_filter('output_html', static function (string $html, array $context): string {
    return str_replace('Hello', 'Hello!', $html);
}, 20);
```

核心提供以下 filter：

- `route_action`：修改当前路由 action
- `post_fields_before_defaults`：在留空的 Slug 和摘要应用核心默认值前提供候选值
- `post_data_before_save`：在文章写入数据库前修改数据
- `admin_sidebar_links`：修改后台侧边栏链接数组
- `site_mail_send`：处理站点邮件发送
- `notification_recipient`：修改评论通知收件邮箱
- `attachment_storage`：接管编辑器附件的最终存储位置
- `editor_field_actions_html`：在 `slug`、`excerpt` 或 `content` 标签旁注入编辑器操作
- `editor_after_form_html`：在文章编辑表单后注入弹窗等插件界面
- `output_html`：过滤最终 HTML 文档

filter 回调依次接收当前值和 `$context`，必须返回过滤后的值。`output_html` 的 context 包含 `action` 与 `content_type`。

`post_fields_before_defaults` 仅在基础校验通过且 Slug 或摘要留空时执行。当前值包含 `slug`、`excerpt`，context 包含 `title`、`content`、`kind`、`post_id`；核心只采纳原本留空字段的字符串候选值，空结果继续使用内置默认规则。

## 语言目录

语言插件应注册固定翻译键，并在模板中通过命名参数格式化动态内容。翻译键可以使用稳定的语义键，也可以使用类似 gettext 的默认中文消息。语言插件不得通过 `output_html` 扫描或替换最终 HTML，也不要把文章标题、正文、评论等用户内容作为翻译键传入。

```php
sblog_i18n_register('en', [
    'post_navigation.previous' => 'Previous post',
    'post_navigation.previous_label' => 'Previous post: {title}',
]);
sblog_i18n_set_locale('en');

$label = sblog_t('post_navigation.previous_label', [
    'title' => (string)$post['title'],
]);

sblog_i18n_register('en', [
    '{count} 篇文章' => [
        'one' => '{count} post',
        'other' => '{count} posts',
    ],
]);
$countLabel = sblog_tn('{count} 篇文章', $count);

sblog_i18n_register_client('en', [
    'open_menu' => 'Open menu',
]);
```

`sblog_t()` 会先读取当前语言，再依次回退到基础语言代码和内置中文目录。`sblog_tn()` 根据当前语言选择 `one`、`few`、`many` 或 `other` 复数形式。参数只替换 `{name}` 占位符，不会再次进入翻译流程；输出到 HTML 时仍需使用 `h()` 转义。

`sblog_i18n_locale()` 用于 HTML `lang`、响应头或日期格式化。核心和内置主题会在 `<head>` 中输出 `sblog_i18n_head()`，将当前语言的客户端目录安全写入 `window.sblogI18n`。

## 资源文件

插件可使用 `plugin_asset_url($slug, $path)` 获取自身 CSS、JavaScript 或图片地址：

```php
add_plugin_action('request', static function (): void {
    $GLOBALS['my_plugin_css'] = plugin_asset_url('my-plugin', 'assets/app.css');
});
```

插件目录中的 PHP、JSON 和 Markdown 文件应由 Web 服务器禁止直接访问。仓库自带的 `.htaccess` 已包含对应规则；使用其他服务器时需要配置等效规则。
