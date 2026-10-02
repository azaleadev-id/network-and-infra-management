<?php
require_once __DIR__ . '/../config/auth.php';
app_require_auth_page();

header('Location: ' . app_url('settings') . '#mikrotikSettingsSection');
exit;

