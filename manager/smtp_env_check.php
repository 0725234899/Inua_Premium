<?php
$host = getenv('SMTP_HOST');
$port = getenv('SMTP_PORT');
$username = getenv('SMTP_USERNAME');
$password = getenv('SMTP_PASSWORD');
$fromEmail = getenv('SMTP_FROM_EMAIL');

echo 'HOST=' . ($host ?: 'empty') . PHP_EOL;
echo 'PORT=' . ($port ?: 'empty') . PHP_EOL;
echo 'USERNAME=' . ($username ?: 'empty') . PHP_EOL;
echo 'PASSWORD=' . ($password ?: 'empty') . PHP_EOL;
echo 'FROM=' . ($fromEmail ?: 'empty') . PHP_EOL;
