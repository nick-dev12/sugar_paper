<?php

$christmas = require __DIR__ . '/../config/christmas.php';
$christmas_version = filemtime(__DIR__ . '/../js/christmas-theme.js');
$christmas_style_version = filemtime(__DIR__ . '/../css/christmas-theme.css');
?>
<script defer src="/js/christmas-theme.js?v=<?= $christmas_version ?>"
    data-style-version="<?= $christmas_style_version ?>"
    data-enabled="<?= empty($christmas['enabled']) ? '0' : '1' ?>"
    data-snow="<?= empty($christmas['snow']) ? '0' : '1' ?>"
    data-snowflakes="<?= max(0, min(40, (int) $christmas['snowflakes'])) ?>"></script>
