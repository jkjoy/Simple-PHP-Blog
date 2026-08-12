<?php

declare(strict_types=1);

function nojs_excerpt(array $post, int $length = 100): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = derive_excerpt((string)($post['content'] ?? ''));
    }
    $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags($excerpt)) ?? $excerpt);
    return str_len_u($excerpt) > $length ? rtrim(str_sub_u($excerpt, 0, $length)) . '...' : $excerpt;
}

function nojs_render_post_list(array $posts, bool $withExcerpt = true): string
{
    ob_start();
    foreach ($posts as $post): ?>
      <section class="content__item">
        <article class="article">
          <div class="article-header">
            <a class="article-header__link" title="<?= h((string)$post['title']) ?>" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?php if (!empty($post['is_pinned'])): ?><span class="sticky__item" aria-label="<?= h(sblog_t('置顶')) ?>">●</span><?php endif; ?><?= h((string)$post['title']) ?></a>
          </div>
          <?php if ($withExcerpt): ?>
            <div class="article__content article__content--index"><p><?= h(nojs_excerpt($post)) ?></p></div>
            <div class="article__excerpt"><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>#more">阅读全文</a></div>
          <?php endif; ?>
        </article>
      </section>
    <?php endforeach;
    return (string)ob_get_clean();
}

function nojs_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    ob_start();
    if ($posts) {
        echo nojs_render_post_list($posts);
        if ($totalPages > 1): ?>
          <nav class="pagination" aria-label="<?= h(sblog_t('分页')) ?>">
            <?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>">上一页</a><?php endif; ?>
            <?php if ($page < $totalPages): ?><a href="<?= h(home_page_url($page + 1)) ?>">下一页</a><?php endif; ?>
          </nav>
        <?php endif;
    } else { ?>
      <section class="content__item"><p class="comment-empty">还没有已发布的文章。</p></section>
    <?php }
    return (string)ob_get_clean();
}

function nojs_render_archives(): string
{
    $posts = fetch_archive_posts();
    ob_start(); ?>
    <section class="content__item">
      <?php if ($posts): ?><ul class="article-header-list"><?php foreach ($posts as $post): ?><li><a title="<?= h((string)$post['title']) ?>" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a></li><?php endforeach; ?></ul><?php else: ?><p class="comment-empty">归档还是空的。</p><?php endif; ?>
    </section>
    <?php return (string)ob_get_clean();
}

function nojs_render_tags(): string
{
    $tags = tag_index_data();
    ob_start(); ?>
    <section class="content__item content__item--tags">
      <?php if ($tags): ?><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>" rel="tag" title="<?= h((string)$tag['count']) ?> 篇文章"><?= h((string)$tag['label']) ?> <sup><?= h((string)$tag['count']) ?></sup></a><?php endforeach; ?><?php else: ?><p class="comment-empty">还没有标签。</p><?php endif; ?>
    </section>
    <?php return (string)ob_get_clean();
}

function nojs_render_categories(): string
{
    $categories = all_rows(
        'SELECT c.*, COUNT(p.id) AS post_count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.kind = ? AND p.status = ? AND p.published_at <= ? GROUP BY c.id ORDER BY c.sort_order ASC, c.id DESC',
        ['post', 'published', time()]
    );
    ob_start(); ?>
    <section class="content__item">
      <?php if ($categories): ?><ul class="content__list"><?php foreach ($categories as $category): ?><li class="content__list-item"><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?>(<?= h((string)$category['post_count']) ?>)</a></li><?php endforeach; ?></ul><?php else: ?><p class="comment-empty">还没有分类。</p><?php endif; ?>
    </section>
    <?php return (string)ob_get_clean();
}

function nojs_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?>
    <section class="content__item">
      <?php if ($links): ?><ul class="article-header-list"><?php foreach ($links as $link): ?><li><a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"><span class="links_author"><?= h((string)$link['name']) ?></span></a><?php if (trim((string)$link['description']) !== ''): ?> - <?= h((string)$link['description']) ?><?php endif; ?></li><?php endforeach; ?></ul><?php else: ?><p class="comment-empty">还没有添加友情链接。</p><?php endif; ?>
    </section>
    <?php return (string)ob_get_clean();
}

function nojs_render_listing(string $title, array $posts): string
{
    ob_start(); ?>
    <section class="content__item content__item--search"><p><?= h($title) ?></p></section>
    <?php if ($posts) { echo nojs_render_post_list($posts); } else { ?><section class="content__item"><p class="comment-empty">这里还没有文章。</p></section><?php }
    return (string)ob_get_clean();
}

function nojs_extract_comments(string $content, int $postId): string
{
    if (preg_match('/<section class="comments"(?=[\s>]).*<\/section>\s*$/s', $content, $matches)) {
        return nojs_prepare_comments((string)$matches[0], $postId);
    }
    return '';
}

function nojs_comment_tree(array $comments): array
{
    $byId = [];
    foreach ($comments as $comment) {
        $byId[(int)$comment['id']] = $comment;
    }

    $children = [];
    $roots = [];
    foreach ($comments as $comment) {
        $id = (int)$comment['id'];
        $parentId = (int)$comment['parent_id'];
        if ($parentId > 0 && $parentId !== $id && isset($byId[$parentId])) {
            $children[$parentId][] = $id;
        } else {
            $roots[] = $id;
        }
    }

    return [$byId, $children, $roots];
}

function nojs_render_comment_items(array $ids, array $byId, array $children, bool $accepting, int $replyTargetId, array &$visited, int $depth = 0): string
{
    ob_start();
    foreach ($ids as $id) {
        $id = (int)$id;
        if (isset($visited[$id]) || !isset($byId[$id])) {
            continue;
        }
        $visited[$id] = true;
        $comment = $byId[$id];
        $authorUrl = safe_link_url((string)$comment['author_url']);
        $replyName = trim((string)$comment['reply_to_name']);
        $replyParentId = (int)$comment['parent_id'];
        $replyAnchorVisible = $replyParentId > 0 && isset($byId[$replyParentId]);
        $childIds = $children[$id] ?? [];
        ?>
        <li class="comment-item<?= $depth > 0 ? ' comment-item--reply' : '' ?>" id="comment-<?= h((string)$id) ?>" style="--comment-depth:<?= h((string)min($depth, 4)) ?>">
          <article class="comment-item__box">
            <header class="comment-item__meta">
              <img class="comment-item__avatar" src="<?= h(gravatar_url((string)$comment['author_email'])) ?>" width="36" height="36" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
              <span class="comment-item__identity">
                <?php if ($authorUrl !== '#'): ?>
                  <a class="comment-item__author" href="<?= h($authorUrl) ?>" target="_blank" rel="ugc nofollow noopener noreferrer"><?= h((string)$comment['author_name']) ?></a>
                <?php else: ?>
                  <strong class="comment-item__author"><?= h((string)$comment['author_name']) ?></strong>
                <?php endif; ?>
                <?php if ((int)($comment['user_id'] ?? 0) > 0): ?><span class="comment-item__owner">博主</span><?php endif; ?>
                <a class="comment-item__permalink" href="#comment-<?= h((string)$id) ?>" aria-label="<?= h('该评论的固定链接') ?>"><time class="comment-item__time" datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time></a>
              </span>
              <?php if ($accepting): ?>
                <button class="comment-reply-button" type="button" data-comment-reply data-comment-id="<?= h((string)$id) ?>" data-comment-author="<?= h((string)$comment['author_name']) ?>" aria-controls="comment-form" aria-pressed="<?= $replyTargetId === $id ? 'true' : 'false' ?>" aria-label="<?= h('回复 @' . (string)$comment['author_name']) ?>" title="<?= h('回复 @' . (string)$comment['author_name']) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 17-5-5 5-5"></path><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
                  <span>回复</span>
                </button>
              <?php endif; ?>
            </header>
            <div class="comment-item__body">
              <?php if ($replyName !== ''): ?>
                <?php if ($replyAnchorVisible): ?><a class="comment-item__reply-target" href="#comment-<?= h((string)$replyParentId) ?>">@<?= h($replyName) ?></a><?php else: ?><span class="comment-item__reply-target">@<?= h($replyName) ?></span><?php endif; ?>
              <?php endif; ?>
              <span class="comment-item__content"><?= nl2br(h((string)$comment['content']), false) ?></span>
            </div>
          </article>
          <?php if ($childIds): ?>
            <ol class="comment-children" aria-label="<?= h('对 ' . (string)$comment['author_name'] . ' 的回复') ?>">
              <?= nojs_render_comment_items($childIds, $byId, $children, $accepting, $replyTargetId, $visited, $depth + 1) ?>
            </ol>
          <?php endif; ?>
        </li>
        <?php
    }
    return (string)ob_get_clean();
}

function nojs_render_comment_list(int $postId, bool $accepting, int $replyTargetId): string
{
    $comments = public_comments_for_post($postId);
    if ($comments === []) {
        return '';
    }

    [$byId, $children, $roots] = nojs_comment_tree($comments);
    $visited = [];
    $items = nojs_render_comment_items($roots, $byId, $children, $accepting, $replyTargetId, $visited);

    // Corrupt or cyclic relationships must not make an approved comment disappear.
    $remaining = array_values(array_diff(array_keys($byId), array_keys($visited)));
    if ($remaining !== []) {
        $items .= nojs_render_comment_items($remaining, $byId, $children, $accepting, $replyTargetId, $visited);
    }

    return '<ol class="comment-list">' . $items . '</ol>';
}

function nojs_prepare_comments(string $comments, int $postId): string
{
    $placeholders = [
        'comment-author' => '称呼 *',
        'comment-email' => '邮箱 *',
        'comment-url' => '网站',
        'comment-content' => '请输入评论内容',
    ];
    foreach ($placeholders as $id => $placeholder) {
        $comments = str_replace('id="' . $id . '"', 'id="' . $id . '" placeholder="' . h($placeholder) . '"', $comments);
    }

    $accepting = str_contains($comments, 'id="comment-form"');
    $replyTargetId = preg_match('/name="parent_id" value="(\d*)"/', $comments, $matches) ? (int)$matches[1] : 0;
    $list = nojs_render_comment_list($postId, $accepting, $replyTargetId);
    if ($list !== '') {
        $comments = preg_replace_callback('/<ol class="comment-list">.*?<\/ol>/s', static fn(array $matches): string => $list, $comments, 1) ?? $comments;
    }
    return $comments;
}

function nojs_render_post(array $post, string $originalContent): string
{
    $neighbors = post_neighbors($post);
    $meta = one('SELECT p.views, c.name AS category_name, c.slug AS category_slug FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)$post['id']]) ?? [];
    $comments = nojs_extract_comments($originalContent, (int)$post['id']);
    ob_start(); ?>
    <section class="content__item" id="more">
      <article class="article">
        <div class="article__header-link"><h2><?= h((string)$post['title']) ?></h2></div>
        <span class="article__views" aria-label="浏览量">◉ <?= h((string)($meta['views'] ?? $post['views'] ?? 0)) ?></span>
        <div class="article__content"><?= markdown_to_html((string)$post['content']) ?></div>
        <div class="article__taxonomy">
          <?php if (trim((string)($meta['category_slug'] ?? '')) !== ''): ?>■ <a class="article__category-link" href="<?= h(url_for('category', ['slug' => (string)$meta['category_slug']])) ?>"><?= h((string)$meta['category_name']) ?></a><?php endif; ?>
          <?php foreach (post_tags($post) as $tag): ?>◆ <a class="article__category-link" href="<?= h(url_for('tag', ['slug' => tag_slug_for_label($tag)])) ?>"><?= h($tag) ?></a><?php endforeach; ?>
        </div>
        <div class="article__footer-link">上一篇：<?php if ($neighbors['newer']): ?><a href="<?= h(url_for('post', ['slug' => (string)$neighbors['newer']['slug']])) ?>"><?= h((string)$neighbors['newer']['title']) ?></a><?php else: ?>没有了<?php endif; ?></div>
        <div class="article__footer-link">下一篇：<?php if ($neighbors['older']): ?><a href="<?= h(url_for('post', ['slug' => (string)$neighbors['older']['slug']])) ?>"><?= h((string)$neighbors['older']['title']) ?></a><?php else: ?>没有了<?php endif; ?></div>
        <?php if (is_admin()): ?><div class="article__footer-link"><a href="<?= h(url_for('edit', ['id' => (int)$post['id']])) ?>" target="_blank">编辑</a></div><?php endif; ?>
      </article>
    </section>
    <?= $comments ?>
    <?php return (string)ob_get_clean();
}

function nojs_render_page(array $page, string $originalContent): string
{
    $comments = nojs_extract_comments($originalContent, (int)$page['id']);
    ob_start(); ?>
    <section class="content__item">
      <article class="article">
        <div class="article__header-link"><h2><?= h((string)$page['title']) ?></h2></div>
        <div class="article__content"><?= markdown_to_html((string)$page['content']) ?></div>
        <?php if (is_admin()): ?><div class="article__footer-link"><a href="<?= h(url_for('edit', ['id' => (int)$page['id']])) ?>" target="_blank">编辑</a></div><?php endif; ?>
      </article>
    </section>
    <?= $comments ?>
    <?php return (string)ob_get_clean();
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' nojs-theme'));

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_replace($labels, [
        'title' => '评论', 'form_title' => '留下评论', 'submit' => '提交评论',
        'cancel_reply' => '取消回复', 'cancel_reply_aria' => '取消回复',
        'empty' => '暂无评论', 'closed' => '评论已关闭',
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? 'home');
    $slug = trim((string)($_GET['slug'] ?? ''));

    if ($action === 'home') return nojs_render_home();
    if ($action === 'archives') return nojs_render_archives();
    if ($action === 'tags') return nojs_render_tags();
    if ($action === 'categories') return nojs_render_categories();
    if ($action === 'links') return nojs_render_links();
    if ($action === 'tag') {
        $label = tag_label_by_slug($slug) ?? $slug;
        return nojs_render_listing('标签 ' . $label . ' 下的文章', fetch_posts_by_tag_slug($slug));
    }
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [$slug]);
        if ($category) {
            $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
            return nojs_render_listing('分类 ' . (string)$category['name'] . ' 下的文章', $posts);
        }
    }
    if ($action === 'post' && ($post = fetch_post_by_identifier($slug, is_admin()))) return nojs_render_post($post, $content);
    if ($action === 'page' && ($page = fetch_page_by_identifier($slug, is_admin()))) return nojs_render_page($page, $content);

    return $content;
});
