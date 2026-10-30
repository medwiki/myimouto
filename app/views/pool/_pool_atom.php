<?php if ($this->sample) : ?>
<?= $this->linkTo($this->imageTag(rtrim(CONFIG()->url_base, '/') . '/' . ltrim($this->sample->preview_url(), '/'), ['title' => $this->pool->pretty_name()]), $this->pool_url) ?>
<?php endif ?>
