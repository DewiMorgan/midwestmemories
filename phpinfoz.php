<!DOCTYPE html>
<!-- ToDo: DELETEME - this file is only to get info on the local PHP install. Not a useful part of the project. -->
<html lang="en">
<head><title>PHP Info</title>
<body>
<?php
echo "<h1>PHP Tests:</h1>\n";
echo "<p>class_exists('PDO')? -> " . (class_exists('PDO') ? 'exists' : 'not found') . "</p>\n";
echo "<p>class_exists('mysqli')? -> " . (class_exists('mysqli') ? 'exists' : 'not found') . "</p>\n";
echo "<p>class_exists('mysqli_stmt')? -> " . (class_exists('mysqli_stmt') ? 'exists' : 'not found') . "</p>\n";
echo "<p>method_exists('mysqli_stmt::get_result')? -> "
    . (method_exists('mysqli_stmt', 'get_result') ? 'exists' : 'not found') . "</p>\n";
echo "<p>function_exists('mysqli_stmt_get_result')? -> "
    . (function_exists('mysqli_stmt_get_result') ? 'exists' : 'not found') . "</p>\n";
echo "<p>All declared classes:</p>\n";
echo '<pre>' . var_export(get_declared_classes(), true) . "</pre>\n";
echo "<h1>PHP Info:</h1>\n";
/** @noinspection ForgottenDebugOutputInspection */
phpinfo();
?>
</body>
</html>
