<?php
require 'config.php';
session_destroy();
json(['ok' => true]);