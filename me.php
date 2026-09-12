<?php
require 'config.php';

$role = currentRole();
if (!$role) {
    json(['ok' => false, 'role' => null]);
}
json(['ok' => true, 'role' => $role]);