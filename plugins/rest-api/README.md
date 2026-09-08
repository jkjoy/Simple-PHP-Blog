# WordPress REST API

该插件为 SBlog 提供兼容 WordPress REST API v2 常用资源的 JSON 接口，适合连接 WordPress 客户端、迁移脚本和自动化工具。

本文档对应插件清单版本 `1.0.1`。实现以 WordPress 的 URL、字段命名、分页响应头和错误对象为参照，但不是完整的 WordPress REST API。

## 启用与入口

在 SBlog 后台的插件页面启用 **WordPress REST API**。插件设置页可以：

- 允许或禁止匿名读取公开内容；
- 允许或禁止 API 写操作；
- 创建、查看使用记录和撤销 Application Password；
- 在可信内网中临时允许通过 HTTP 发送凭据。

启用伪静态 URL 时，API 根地址为：

```text
https://example.com/wp-json/
```

未启用伪静态时，可以使用 WordPress 的 `rest_route` 查询参数：

```text
https://example.com/index.php?rest_route=/wp/v2/posts
```

下面的示例统一使用：

```bash
BASE_URL="https://example.com/wp-json"
```

## 鉴权

公开内容默认允许匿名读取。以下请求必须使用 Application Password：

- 所有创建、更新和删除操作；
- `context=edit` 请求；
- `/wp/v2/users/me`；
- 后台关闭匿名读取后的所有资源请求。

在插件设置页为当前 SBlog 用户创建 Application Password。密码只显示一次，数据库仅保存不可逆哈希。请求使用 HTTP Basic Authentication，用户名可以是 SBlog 用户名或邮箱；密码中的分组空格可以保留或删除。

```bash
curl --user "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  "$BASE_URL/wp/v2/users/me?context=edit"
```

默认仅允许通过 HTTPS 发送凭据。`localhost`、`127.0.0.1`、`::1`，以及后台明确开启的 HTTP 调试环境除外。不要在不可信网络中启用 HTTP 鉴权。

## 请求约定

### 方法与请求体

接口支持 `GET`、`HEAD`、`POST`、`PUT`、`PATCH`、`DELETE` 和 `OPTIONS`。写入数据可以使用 JSON 或常规表单字段：

```http
Content-Type: application/json
```

`PUT`、`PATCH` 和 `DELETE` 也可以通过 `X-HTTP-Method-Override` 请求头或 `_method` 参数覆盖实际 HTTP 方法。

JSON 请求体必须是对象。字段同时出现在查询字符串和请求体时，请求体优先。

### 通用查询参数

| 参数 | 适用范围 | 说明 |
| --- | --- | --- |
| `context` | 资源读取 | `view`（默认）仅返回公开字段；`edit` 返回原始字段并要求鉴权 |
| `page` | 集合 | 页码，从 `1` 开始，默认 `1` |
| `per_page` | 集合 | 每页数量，范围 `1` 到 `100`，默认 `10` |
| `search` | 主要集合 | 按资源支持的文本字段模糊搜索 |
| `_fields` | 所有成功响应 | 逗号分隔的顶层字段白名单，例如 `_fields=id,title,slug` |
| `_embed` | 文章、页面 | 真值时嵌入作者信息；当前仅实现 `author` |

集合响应在 JSON 数组之外还包含：

```http
X-WP-Total: 42
X-WP-TotalPages: 5
Link: <https://example.com/wp-json/wp/v2/posts?page=2>; rel="next"
```

超出有效范围的 `page` 返回 `400 rest_post_invalid_page_number`。`HEAD` 返回与 `GET` 相同的状态码和响应头，但没有响应体。

## 端点总览

| 资源 | 集合端点 | 单项端点 | 能力 |
| --- | --- | --- | --- |
| API 发现 | `/`、`/wp/v2` | - | 读取命名空间和路由 |
| 文章 | `/wp/v2/posts` | `/wp/v2/posts/{id}` | 读取、创建、更新、删除 |
| 页面 | `/wp/v2/pages` | `/wp/v2/pages/{id}` | 读取、创建、更新、删除 |
| 分类 | `/wp/v2/categories` | `/wp/v2/categories/{id}` | 读取、创建、更新、删除 |
| 标签 | `/wp/v2/tags` | `/wp/v2/tags/{id}` | 读取、创建、更新、删除 |
| 评论 | `/wp/v2/comments` | `/wp/v2/comments/{id}` | 只读 |
| 媒体 | `/wp/v2/media` | `/wp/v2/media/{id}` | 读取、上传、更新元数据、删除 |
| 用户 | `/wp/v2/users` | `/wp/v2/users/{id}`、`/wp/v2/users/me` | 只读 |
| 类型 | `/wp/v2/types` | `/wp/v2/types/{type}` | 只读发现接口 |
| 状态 | `/wp/v2/statuses` | `/wp/v2/statuses/{status}` | 只读发现接口 |
| 分类法 | `/wp/v2/taxonomies` | `/wp/v2/taxonomies/{taxonomy}` | 只读发现接口 |

发现接口支持的单项值为：

- `type`：`post`、`page`、`attachment`；
- `status`：`publish`、`future`、`draft`；
- `taxonomy`：`category`、`post_tag`。

## 文章与页面

### 读取列表

```http
GET /wp/v2/posts
GET /wp/v2/pages
```

文章和页面集合支持：

| 参数 | 说明 |
| --- | --- |
| `after`、`before` | 可由 PHP `strtotime` 解析的日期边界，包含边界值 |
| `author` | 作者 ID；可传逗号分隔列表或数组 |
| `include`、`exclude` | 包含或排除的内容 ID 列表 |
| `slug` | 逗号分隔的 slug |
| `status` | `publish`、`future`、`draft` 或 `any`；非公开状态需要鉴权并设置 `context=edit` |
| `order` | `asc` 或 `desc`，默认 `desc` |
| `orderby` | `date`（默认）、`id`、`modified`、`title` 或 `slug` |

文章集合额外支持：

| 参数 | 说明 |
| --- | --- |
| `categories` | 分类 ID 列表 |
| `tags` | 标签 ID 列表 |
| `tags_relation` | 多个标签之间使用 `or`（默认）或 `and` |

例如读取一个分类下最近更新的文章：

```bash
curl "$BASE_URL/wp/v2/posts?categories=3&orderby=modified&order=desc&per_page=20&_fields=id,date,slug,title,link"
```

读取草稿或定时发布内容必须同时鉴权并使用编辑上下文：

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  "$BASE_URL/wp/v2/posts?context=edit&status=draft,future"
```

### 读取单项

```http
GET /wp/v2/posts/{id}
GET /wp/v2/pages/{id}
```

`view` 上下文只允许读取已经发布且发布时间不晚于当前时间的内容。`context=edit` 会增加 `title.raw`、`content.raw` 和 `excerpt.raw`，其中 `content.raw` 是 SBlog Markdown，`content.rendered` 是渲染后的 HTML。

### 创建与更新

```http
POST  /wp/v2/posts
POST  /wp/v2/pages
POST  /wp/v2/posts/{id}
PUT   /wp/v2/posts/{id}
PATCH /wp/v2/posts/{id}
```

页面使用相同方法，将路径中的 `posts` 换成 `pages`。创建成功返回 `201` 和 `Location` 响应头；更新成功返回 `200`。

| 字段 | 文章 | 页面 | 说明 |
| --- | --- | --- | --- |
| `title` | 是 | 是 | 字符串，或包含 `raw`/`rendered` 的对象 |
| `content` | 是 | 是 | Markdown 字符串，或包含 `raw`/`rendered` 的对象 |
| `excerpt` | 是 | 是 | 摘要字符串，或包含 `raw`/`rendered` 的对象 |
| `slug` | 是 | 是 | URL slug；留空时由核心校验和生成逻辑处理 |
| `status` | 是 | 是 | `publish`、`future` 或 `draft`；新内容默认 `draft` |
| `date`、`date_gmt` | 是 | 是 | 发布时间；`date_gmt` 按 UTC 解析 |
| `comment_status` | 是 | 是 | `open` 或 `closed` |
| `categories` | 是 | 否 | 分类 ID 数组；SBlog 每篇文章只保存第一个有效分类 |
| `tags` | 是 | 否 | 标签 ID 数组；未知 ID 会被忽略 |
| `sticky` | 是 | 否 | 是否置顶，布尔值 |
| `format` | 是 | 否 | `standard` 或 `image` |

创建草稿：

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"title":"API draft","content":"## Markdown body","status":"draft","categories":[3],"tags":[5,8]}' \
  "$BASE_URL/wp/v2/posts"
```

创建定时文章时，`status` 必须为 `future`，且日期必须晚于服务器当前时间：

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"title":"Scheduled post","content":"Body","status":"future","date_gmt":"2030-01-01T02:00:00"}' \
  "$BASE_URL/wp/v2/posts"
```

### 删除

```http
DELETE /wp/v2/posts/{id}
DELETE /wp/v2/pages/{id}
```

默认删除会把内容改为草稿，并返回更新后的内容。传入 `force=true` 才会永久删除：

```bash
curl --user "admin:APPLICATION_PASSWORD" -X DELETE \
  "$BASE_URL/wp/v2/posts/42?force=true"
```

永久删除响应：

```json
{
  "deleted": true,
  "previous": {
    "id": 42,
    "status": "draft"
  }
}
```

## 分类与标签

### 读取

```http
GET /wp/v2/categories
GET /wp/v2/categories/{id}
GET /wp/v2/tags
GET /wp/v2/tags/{id}
```

集合支持以下参数：

| 参数 | 说明 |
| --- | --- |
| `search` | 按名称或 slug 搜索 |
| `include`、`exclude` | 包含或排除的术语 ID 列表 |
| `slug` | 逗号分隔的 slug |
| `post` | 仅返回指定文章使用的分类或标签 |
| `hide_empty` | 真值时仅返回至少有一篇公开文章的术语 |

### 创建与更新

```http
POST  /wp/v2/categories
POST  /wp/v2/tags
POST  /wp/v2/categories/{id}
PUT   /wp/v2/categories/{id}
PATCH /wp/v2/categories/{id}
```

标签的更新路径与分类相同。`name` 为必填字段；`slug` 可选；分类还接受 `description`。SBlog 分类当前不支持父子层级，响应中的 `parent` 固定为 `0`。标签名不能包含逗号。

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"name":"API","slug":"api","description":"API articles"}' \
  "$BASE_URL/wp/v2/categories"
```

### 删除

```http
DELETE /wp/v2/categories/{id}
DELETE /wp/v2/tags/{id}
```

删除标签会同步从所有文章移除该标签。删除分类会把其中的文章移动到另一个现有分类；最后一个分类不能删除。成功响应包含 `deleted: true` 和删除前的 `previous` 对象。

## 评论

评论 API 只读：

```http
GET /wp/v2/comments
GET /wp/v2/comments/{id}
```

公开读取只返回已批准评论，并且对应文章必须已公开。`context=edit` 要求鉴权，会额外返回原始正文、作者邮箱、IP 和 User-Agent，并支持读取非公开状态。

| 参数 | 说明 |
| --- | --- |
| `post` | 文章 ID 列表 |
| `parent` | 父评论 ID 列表；`0` 表示顶级评论 |
| `search` | 按作者名称或评论正文搜索 |
| `order` | 按创建时间 `asc` 或 `desc`，默认 `desc` |
| `status` | 编辑上下文中使用：`approved`、`hold`、`spam` 或 `any` |

```bash
curl "$BASE_URL/wp/v2/comments?post=42&parent=0&order=asc"
```

## 媒体

### 读取

```http
GET /wp/v2/media
GET /wp/v2/media/{id}
```

媒体集合支持 `search`、`media_type=image` 和 `mime_type`。`mime_type` 可以是完整类型（如 `image/png`）或通配类型（如 `image/*`）。

### 上传

```http
POST /wp/v2/media
```

支持 `multipart/form-data` 和原始二进制两种方式，单个文件最大 `30 MB`。允许的扩展名和实际 MIME 类型如下：

| 扩展名 | MIME 类型 |
| --- | --- |
| `jpg`、`jpeg` | `image/jpeg` |
| `png` | `image/png` |
| `gif` | `image/gif` |
| `webp` | `image/webp` |
| `pdf` | `application/pdf` |
| `txt`、`md` | `text/plain` |
| `zip` | `application/zip`、`application/x-zip-compressed` |

multipart 上传的文件字段名必须为 `file`，还可以同时提交 `title`、`caption` 和 `alt_text`：

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  -F "file=@cover.jpg" \
  -F "title=Cover" \
  -F "alt_text=Article cover" \
  "$BASE_URL/wp/v2/media"
```

原始二进制上传必须通过 `Content-Disposition` 提供文件名：

```bash
curl --user "admin:APPLICATION_PASSWORD" \
  -H 'Content-Type: image/jpeg' \
  -H 'Content-Disposition: attachment; filename="cover.jpg"' \
  --data-binary @cover.jpg \
  "$BASE_URL/wp/v2/media"
```

上传成功返回 `201`、`Location` 响应头和媒体对象。若启用了 S3 存储插件，上传和删除会沿用 SBlog 的附件存储过滤器。

### 更新与删除

```http
POST  /wp/v2/media/{id}
PUT   /wp/v2/media/{id}
PATCH /wp/v2/media/{id}
DELETE /wp/v2/media/{id}
```

更新接受 `title`、`caption`、`description` 和 `alt_text`。当 `description` 非空时，它会作为 SBlog 保存的附件说明；删除会同时删除底层存储对象和数据库记录。

## 用户

用户 API 只读：

```http
GET /wp/v2/users
GET /wp/v2/users/{id}
GET /wp/v2/users/me
```

公开列表只显示至少发布过一篇公开文章的用户，支持 `search` 和 `include`。`context=edit` 要求鉴权，并返回 `username`、`email`、`registered_date`、`roles` 和权限字段。

`/users/me` 始终要求鉴权，用于验证 Application Password 或让 WordPress 客户端识别当前账户：

```bash
curl --user "admin:APPLICATION_PASSWORD" "$BASE_URL/wp/v2/users/me?context=edit"
```

## 响应与错误

成功响应使用 `application/json; charset=UTF-8`。创建资源通常返回 `201 Created`，更新和读取返回 `200 OK`，删除返回资源对象或包含 `deleted`、`previous` 的对象。

错误遵循 WordPress REST API 的基本对象结构：

```json
{
  "code": "rest_invalid_param",
  "message": "per_page must be between 1 and 100.",
  "data": {
    "status": 400,
    "params": {
      "per_page": "Invalid value."
    }
  }
}
```

常见错误：

| HTTP 状态 | code | 场景 |
| --- | --- | --- |
| `400` | `rest_invalid_json` | JSON 无效或请求体不是对象 |
| `400` | `rest_invalid_param` | 参数、日期、状态或字段校验失败 |
| `400` | `rest_missing_callback_param` | 缺少必填字段，例如术语 `name` |
| `400` | `rest_upload_file_too_big` | 上传超过 30 MB |
| `400` | `rest_upload_sideload_error` | 上传扩展名或实际 MIME 类型不允许 |
| `401` | `rest_not_logged_in` | 请求要求鉴权但未提供凭据 |
| `401` | `rest_application_password_invalid` | 用户名或 Application Password 无效 |
| `401` | `rest_application_password_requires_https` | 在不允许的 HTTP 连接中发送凭据 |
| `403` | `rest_cannot_create` | 管理员关闭了 API 写入 |
| `404` | `rest_no_route` | 路径或 HTTP 方法不受支持 |
| `404` | `rest_post_invalid_id` | 文章、页面或媒体不存在 |
| `404` | `rest_term_invalid` | 分类或标签不存在 |
| `500` | `rest_upload_sideload_error` | 附件存储插件保存失败 |
| `500` | `rest_unknown_error` | 未预期的服务器内部错误 |

`401` 响应会包含：

```http
WWW-Authenticate: Basic realm="SBlog REST API"
```

## 兼容边界

- 这是 WordPress REST API v2 的常用子集，不提供区块、修订版、菜单、插件、主题、角色管理等端点。
- SBlog 正文原始格式是 Markdown，不是 WordPress 块标记或 HTML；`content.raw` 返回 Markdown，`content.rendered` 返回 HTML。
- 评论和用户资源只读。
- SBlog 每篇文章只有一个分类；写入多个 `categories` 时只使用第一个有效 ID。
- SBlog 分类没有层级，`parent` 固定为 `0`。
- 媒体不会生成 WordPress 风格的多尺寸缩略图，`media_details.sizes` 最多包含原图 `full`。
- `_embed` 目前只在文章和页面中嵌入作者；`_fields` 只筛选顶层字段，不解析点号嵌套路径。
- 接口没有 WordPress nonce 或 Cookie 鉴权，只支持 Application Password 的 HTTP Basic Authentication。

需要判断客户端能力时，先请求 `/wp-json/` 或 `/wp-json/wp/v2`，并以返回的 `routes` 为准。
