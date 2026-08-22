# 自定义头像源

启用插件后，在“后台 -> 插件管理 -> 自定义头像源 -> 设置”中选择评论头像服务。配置为站点级设置，对所有调用 SBlog 评论头像 API 的主题统一生效，切换主题后无需重新配置。

支持以下来源：

- Gravatar
- Cravatar
- Libravatar
- 自定义 URL 模板

自定义模板可使用 `{hash}`、`{email}`、`{size}`、`{default}` 和 `{rating}` 占位符。模板必须使用 HTTP 或 HTTPS，并至少包含 `{hash}` 或 `{email}`。为避免向头像服务暴露评论者邮箱，建议优先使用 `{hash}`。
