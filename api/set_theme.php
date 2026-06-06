<?php
session_start();
$theme = $_GET['theme'] ?? 'system';
if (in_array($theme, ['light', 'dark', 'system'], true)) {
    $_SESSION['theme'] = $theme;
}
http_response_code(204);
