<?php
$xml = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
$base = rtrim(CONFIG()->url_base, '/');
?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $xml(CONFIG()->app_name . '/' . (string) $this->params()->tags) ?></title>
  <link><?= $xml($base . '/') ?></link>
  <description><?= $xml(CONFIG()->app_name) ?> PicLens RSS Feed</description>
  <?php foreach ($this->posts as $post) : ?>
    <item>
      <title><?= $xml($post->cached_tags) ?></title>
      <link><?= $xml($base . '/post/show/' . $post->id) ?></link>
      <guid><?= $xml($base . '/post/show/' . $post->id) ?></guid>
      <media:thumbnail url="<?= $xml($post->preview_url()) ?>"/>
      <media:content url="<?= $xml(CONFIG()->image_samples ? $post->sample_url() : $post->file_url()) ?>"/>
    </item>
  <?php endforeach ?>
</channel>
</rss>
