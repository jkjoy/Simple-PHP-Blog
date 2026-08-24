# WordPress REST API

启用后，SBlog 会提供 WordPress 风格的 REST API：

- `/wp-json/`：API 发现文档
- `/wp-json/wp/v2/posts`：文章读取、创建、更新和删除
- `/wp-json/wp/v2/pages`：页面读取、创建、更新和删除
- `/wp-json/wp/v2/categories`：分类读取、创建、更新和删除
- `/wp-json/wp/v2/tags`：标签读取、创建、更新和删除
- `/wp-json/wp/v2/comments`：评论读取
- `/wp-json/wp/v2/media`：媒体读取、上传和元数据更新
- `/wp-json/wp/v2/users` 与 `/wp-json/wp/v2/users/me`：用户读取
- `/wp-json/wp/v2/types`、`statuses`、`taxonomies`：WordPress 客户端发现接口

未开启伪静态时也可以使用 WordPress 的查询参数形式：

```text
/index.php?rest_route=/wp/v2/posts
```

## 鉴权

公开内容默认允许匿名读取。写操作以及草稿、待审核评论等非公开数据需要 HTTP Basic Authentication：用户名填写 SBlog 用户名，密码填写插件设置页生成的 Application Password。

Application Password 只显示一次，数据库中仅保存不可逆哈希。默认只允许 HTTPS；`localhost`、`127.0.0.1` 和后台明确开启的 HTTP 调试例外除外。

```bash
curl --user "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"title":"API draft","content":"Markdown body","status":"draft"}' \
  https://example.com/wp-json/wp/v2/posts
```

## 兼容边界

响应字段、错误对象、分页参数、`X-WP-Total`/`X-WP-TotalPages` 响应头和常用过滤参数遵循 WordPress REST API v2。SBlog 没有 WordPress 的区块、修订版、菜单、插件、主题和角色权限模型，因此不提供这些端点。文章正文的 `content.raw` 使用 SBlog Markdown，`content.rendered` 返回渲染后的 HTML。
