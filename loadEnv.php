<?php
/*
* Function to load env variaables from the .env file
*/
function loadEnv($file)
{
    if (!file_exists($file)) {
        die("ERROR: .env file not found: $file\n");
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if (str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}