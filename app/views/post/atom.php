<?php
$xml = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
$base = rtrim(CONFIG()->url_base, '/');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= $xml(CONFIG()->app_name) ?></title>
  <link href="<?= $xml($base . '/post/atom') ?>" rel="self"/>
  <link href="<?= $xml($base . '/post') ?>" rel="alternate"/>
  <id><?= $xml($base . '/post/atom?tags=' . rawurlencode((string) $this->params()->tags)) ?></id>
  <updated><?= gmdate(DATE_ATOM) ?></updated>
  <author><name><?= $xml(CONFIG()->app_name) ?></name></author>
  <?php foreach ($this->posts as $post) : ?>
    <entry>
      <title><?= $xml($post->cached_tags) ?></title>
      <link href="<?= $xml($base . '/post/show/' . $post->id) ?>" rel="alternate"/>
      <id><?= $xml($base . '/post/show/' . $post->id) ?></id>
      <updated><?= $xml(gmdate(DATE_ATOM, strtotime((string) $post->created_at))) ?></updated>
      <summary><?= $xml($post->cached_tags) ?></summary>
      <content type="xhtml"><div xmlns="http://www.w3.org/1999/xhtml">
        <a href="<?= $xml($base . '/post/show/' . $post->id) ?>"><img src="<?= $xml($post->preview_url()) ?>" alt="<?= $xml($post->cached_tags) ?>"/></a>
      </div></content>
      <author><name><?= $xml($post->author()) ?></name></author>
    </entry>
  <?php endforeach ?>
</feed>
