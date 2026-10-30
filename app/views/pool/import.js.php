document.getElementById('posts').innerHTML = <?= json_encode($this->partial('import_list'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
