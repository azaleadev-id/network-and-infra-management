<?php
require_once __DIR__ . '/mobile_bootstrap.php';

mobile_require_method('GET');

$account = mobile_require_account();
$items = mobile_fetch_tenants_by_ids(mobile_accessible_tenant_ids($account));

mobile_json_response([
    'success' => true,
    'data' => array_map(static function (array $item): array {
        $item['id'] = (int) $item['id'];
        return $item;
    }, $items),
]);
