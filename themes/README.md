# 主题开发

默认主题内置于核心程序，不对应 `themes/<slug>/` 目录。核心源码不附带自定义主题；官方自定义主题统一维护在 [`jkjoy/SBlog-Extensions`](https://github.com/jkjoy/SBlog-Extensions)，可从后台扩展商店安装和升级。

每个自定义主题放在 `themes/<slug>/`。目录名只能包含小写字母、数字、连字符和下划线，并必须提供 `theme.json`：

```json
{
  "name": "主题名称",
  "version": "1.0.0",
  "author": "作者",
  "url": "https://example.com/theme-author",
  "description": "主题说明"
}
```

`url` 可选，用于主题管理中的作者链接，只接受完整的 HTTP 或 HTTPS 地址。

主题可包含以下文件：

- `style.css`：通过标准 `head` action 自动在内置前台样式之后加载。
- `functions.php`：注册 action 和 filter 钩子。
- `layout.php`：可选，输出完整 HTML 文档并接管前台布局。
- 其他 CSS、JavaScript 和图片：使用 `theme_asset_url('assets/app.js')` 获取当前主题资源地址。

安装后进入“后台 -> 主题管理”预览或启用。主题 PHP 是服务器端可信代码，只安装来源可信的主题。

## 钩子 API

注册 action：

```php
add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#101820">';
});
```

注册 filter：

```php
add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' my-theme');
});
```

可用 action：

- `head`
- `body_open`
- `header_before`、`header_after`
- `content_before`、`content_after`
- `footer_before`、`footer_after`
- `body_close`

可用 filter：

- `document_title`
- `description`
- `body_class`
- `content`

action 回调接收 `$context`；返回字符串会被输出，也可以在回调中直接输出。filter 回调依次接收当前值和 `$context`，应返回过滤后的值。第三个参数可设置优先级，数值越小越先执行：

```php
add_theme_filter('content', $callback, 20);
```

`$context` 包含 `title`、`full_title`、`description`、`content`、`options`、`site_name`、`active`、`admin`、`nav_pages`、`theme`、`body_class`、`style_url` 和 `flash`。

## 自定义布局

`layout.php` 在 `render_layout()` 的局部作用域中加载，可以直接使用 `$title`、`$content`、`$options`、`$siteName`、`$fullTitle`、`$description`、`$bodyClass`、`$theme`、`$themeContext`、`$flash` 和 `$navPages`，也可以调用博客现有的 URL 与转义辅助函数。

自定义布局必须输出完整 HTML 文档。必须在 `<head>` 中调用 `head` action 才会自动加载 `style.css`；建议保留：

```php
<?php theme_action('head', $themeContext); ?>
<?php theme_action('body_open', $themeContext); ?>
<?php theme_action('content_before', $themeContext); ?>
<?= $content ?>
<?php theme_action('content_after', $themeContext); ?>
<?php theme_action('body_close', $themeContext); ?>
```

若 `functions.php`、钩子回调或 `layout.php` 抛出异常，程序会记录到 PHP error log，并尽可能使用内置布局继续响应。主题被删除或清单失效时会自动回退到内置主题。

自行重绘公开评论列表的主题应在作者昵称后依次调用 `render_comment_identity($comment, ['post' => $post, 'comments' => $comments])` 与 `render_comment_meta($comment, ['post' => $post, 'comments' => $comments])`，保留插件注入评论身份和派生信息的能力。`$comments` 应传入当前页的完整评论数组；主题还需为返回的 HTML 安排合适的布局。

公开主题必须保留内容门禁：文章或独立页正文使用 `render_content_html($post)` 输出，不要直接把 `$post['content']` 传给 `markdown_to_html()`。`fetch_published_posts()`、`fetch_feed_posts()` 与 `fetch_posts_by_tag_slug()` 已返回脱敏后的公开数据；主题自行查询 `posts` 表并在列表、搜索、封面或摘要中使用正文时，必须先调用 `public_content_context($post)`。该函数会移除密码哈希、密码正文和回复隐藏区块，因此不要用它替代详情页的 `render_content_html()`。
