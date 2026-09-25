-- Optional Candy showcase content for a copied SQLite database.
-- Example: sqlite3 path/to/preview.sqlite < themes/candy/demo.sql
-- Existing posts and categories are left intact. Re-running this file adds no duplicates.

BEGIN;

INSERT OR IGNORE INTO categories (name, slug, description, sort_order, created_at, updated_at) VALUES
  ('日常记录', 'candy-life', '咖啡、散步和普通日子里值得记住的小事。', 10, unixepoch(), unixepoch()),
  ('影像与视频', 'candy-visual', '照片、短片和屏幕里的灵感。', 20, unixepoch(), unixepoch()),
  ('书影音', 'candy-culture', '读过的书、看过的电影，以及留下来的感受。', 30, unixepoch(), unixepoch()),
  ('设计手记', 'candy-design', '关于颜色、排版与日常创作的笔记。', 40, unixepoch(), unixepoch());

INSERT OR IGNORE INTO posts
  (author_id, category_id, slug, title, excerpt, content, kind, post_format, tags, status, published_at, created_at, updated_at)
VALUES
  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-life'),
   'candy-demo-yellow-flowers', '路边一簇明亮的黄',
   '出门买东西的路上，遇见一片比天气还要明亮的黄色。',
   '![阳光下的黄色花朵和蓝色天空](/themes/candy/demo-assets/flowers.jpg)

本来只是一次很普通的出门。拐过路口，黄色的花在蓝天前铺开，脚步就自然慢了下来。

## 把注意力交给颜色

不一定每次都要带相机。偶尔停一会儿，看看花瓣边缘的光和风吹过时的晃动，也算给这一天留下了一个记号。

回家之后还是拍了一张照片，和今天的几句话放在一起。照片：Unsplash。',
   'post', 'image', '["日常","摄影"]', 'published', unixepoch() - 1800, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-life'),
   'candy-demo-slow-morning', '把周末留给纸笔',
   '关掉通知，给一个普通的早晨留出完整的空白。',
   '![笔记本、咖啡与电脑摆在木桌上](/themes/candy/demo-assets/desk.jpg)

有些周末不需要安排得很满。把手机调成静音，写下这一周留下的三个问题，早晨就有了自己的节奏。

## 从一张空白纸开始

我先写想做的事，再划掉那些其实并不着急的。剩下的通常只有两三件：读几页书、走一段路、认真吃一顿饭。

> 留白不是暂停生活，而是让注意力回到自己手上。

今天的清单很短：

- 写一页没有标题的日记
- 整理桌上已经读完的书
- 在太阳落下前出门走走

照片：Unsplash。',
   'post', 'image', '["日常","手写"]', 'published', unixepoch() - 86400, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-visual'),
   'candy-demo-bilibili-video', '三分钟的音乐影像',
   '把一支短片放进文章里，记录刚好适合休息时看的画面。',
   '视频有时比一长段文字更适合保存当下的情绪。这是一篇 B 站视频嵌入示例，播放器会跟随文章宽度缩放。

https://www.bilibili.com/video/BV1xx411c7mD

## 看完以后

我喜欢把片段重新放一遍，留意画面切换和音乐节拍如何配合。即使只是一条收藏，也值得写一句当时为什么点开它。

视频来自 B 站；实际播放以平台可访问性为准。',
   'post', 'text', '["视频","收藏"]', 'published', unixepoch() - 172800, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-design'),
   'candy-demo-color-notes', '高饱和配色，也可以认真阅读',
   '让粉、黄、蓝各司其职：颜色负责吸引视线，文字负责把内容讲清楚。',
   'Candy 的颜色很响亮，文章里的阅读区域却需要安静。设计时我把颜色分成了三种任务：提示、分类和强调。

## 一张简单的配色表

| 用途 | 颜色 | 使用位置 |
| --- | --- | --- |
| 行动 | 热粉 #FF1493 | 主要按钮、进度条 |
| 提醒 | 电光黄 #FFE135 | 标签、局部高亮 |
| 信息 | 天蓝 #00BFFF | 分类提示、装饰 |

大面积正文仍然放在浅色背景上，用近黑文字保持对比。粗边框与硬阴影则负责把不同模块分开。

```css
.note {
  color: #0a0a0a;
  border: 3px solid #000;
  box-shadow: 5px 5px 0 #000;
}
```

当所有元素都争抢注意力时，减少一种颜色往往比再加一种更有效。',
   'post', 'text', '["设计","配色"]', 'published', unixepoch() - 259200, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-culture'),
   'candy-demo-film-note', '电影笔记：重看肖申克的救赎',
   '一张豆瓣电影卡片，以及重看老电影时记下的几个瞬间。',
   '重看熟悉的电影，注意力会从故事结局转向人物的选择。那些第一次观看时略过的细节，反而变得更清晰。

https://movie.douban.com/subject/1292052/

## 这次记下的事

电影里最打动我的不是某一句台词，而是人在漫长时间里仍然愿意保持耐心。好的作品总会随着观众的生活经验一起变化。

豆瓣卡片展示作品信息；评分和封面由豆瓣提供，无法访问时仍可通过链接打开条目。',
   'post', 'text', '["电影","豆瓣"]', 'published', unixepoch() - 345600, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-life'),
   'candy-demo-coffee-hour', '街角咖啡店的一小时',
   '在热水与咖啡粉之间，给忙碌的下午按一次暂停键。',
   '![桌上的拿铁、咖啡粉和咖啡豆](/themes/candy/demo-assets/coffee.jpg)

午后的咖啡店声音很多：磨豆机、杯子碰到托盘、门被推开时短促的风铃。坐下来之后，这些声音却慢慢变成了背景。

## 不赶时间的练习

我带了一本薄书，只读了十几页。剩下的时间用来观察窗边的光线移动，并在纸上记下突然想到的一句话。

一小时没有完成什么宏大的计划，但重新带回了专注。照片：Unsplash。',
   'post', 'image', '["咖啡","日常"]', 'published', unixepoch() - 432000, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-visual'),
   'candy-demo-youtube-video', '视频嵌入示例：YouTube',
   '一篇专门展示宽屏播放器、键盘操作与移动端比例的视频文章。',
   '这篇文章保留一个独立的视频段落，用来展示 YouTube 播放器在文章宽度内的表现。下方是公开示例视频。

https://www.youtube.com/watch?v=dQw4w9WgXcQ

## 观看笔记

横屏播放器使用 16:9 比例。移动端打开时，画面会随正文宽度缩小，仍可使用播放器自身的控制按钮。

视频来自 YouTube；实际播放以所在地区及平台可访问性为准。',
   'post', 'text', '["视频","YouTube"]', 'published', unixepoch() - 518400, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-culture'),
   'candy-demo-book-note', '书架上的一本重读',
   '读完之后再放回书架，有些句子会在不同年龄给出不同回答。',
   '有些书适合慢一点读。第一次读时急着知道接下来会发生什么，第二次读时才看见文字里那些不动声色的部分。

https://book.douban.com/subject/4913064/

## 读书时的三个记号

1. 第一次停下来回读的段落
2. 想和朋友讨论的一句话
3. 合上书后仍留在脑中的画面

这张豆瓣图书卡片让书名、评分与入口留在文章里，后面的感受则留给自己的文字。',
   'post', 'text', '["阅读","豆瓣"]', 'published', unixepoch() - 604800, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-visual'),
   'candy-demo-mountain-light', '晴天里的一段山路',
   '没有目的地的短途出走，最后记住的是层层叠叠的山和澄蓝天空。',
   '![蓝天下的山脉、森林与河谷](/themes/candy/demo-assets/landscape.jpg)

出门时还没有决定要走多远。转过弯以后，远处的山像一层层纸被叠起来，才终于想起拿出相机。

## 留在照片之外

照片保存了晴朗的天空，却没有保存风的温度，也没有保存回程路上听到的歌。图文文章的好处是，镜头之外的东西可以交给文字。

下一次路过这里，也许会是完全不同的天气。照片：Unsplash。',
   'post', 'image', '["摄影","出走"]', 'published', unixepoch() - 691200, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-culture'),
   'candy-demo-reading-room', '书店灯光下的半小时',
   '从书架前走到阅读桌边，翻过几页不打算立刻买下的书。',
   '![书店里一整面书架与暖色灯光](/themes/candy/demo-assets/books.jpg)

我喜欢在书店里随机抽出一本书，先读目录，再翻到中间的一页。这比看推荐语更容易发现一本书的声音。

## 今天带走了什么

最后没有买书，只在笔记里留下三条线索：一个陌生作者、一个有意思的章节标题、一个值得下次继续找的问题。

书店的灯光适合让人慢下来。照片：Unsplash。',
   'post', 'image', '["书店","阅读"]', 'published', unixepoch() - 777600, unixepoch(), unixepoch()),

  ((SELECT id FROM users ORDER BY id LIMIT 1),
   (SELECT id FROM categories WHERE slug = 'candy-life'),
   'candy-demo-weekly-fragments', '一周的零碎发现',
   '三件很小的事：走路、听歌，以及把想法及时写下来。',
   '不是每个想法都需要长文章。有时只要把几件小事放在一起，就能看见这一周的形状。

## 本周的三个片段

- 下班后多走一站路，发现常经过的街角新开了一家花店。
- 把收藏夹里存了很久的一支短片看完，终于知道当时为什么想留着它。
- 随手写下的一个标题，后来变成了完整的文章开头。

> 记录不一定要完整，但最好足够真实。

下周也许不会更特别，不过我希望自己继续留意这些细节。',
   'post', 'text', '["周记","随笔"]', 'published', unixepoch() - 864000, unixepoch(), unixepoch());

COMMIT;
