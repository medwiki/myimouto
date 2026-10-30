<h3><?= $this->t('.title') ?></h3>
<ul>
<?php foreach ($this->users as $user) : ?>
  <li><?= $this->linkTo($this->h($user->pretty_name()), ['post#index', 'tags' => 'vote:3:' . $user->name . ' order:vote']) ?></li>
<?php endforeach ?>
</ul>
<?= $this->partial('footer') ?>
