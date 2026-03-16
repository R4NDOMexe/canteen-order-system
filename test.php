<?php
require_once 'includes/config.php';
echo "<h1>Session Debug</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "<p>isLoggedIn: " . (isLoggedIn() ? 'YES' : 'NO') . "</p>";
echo "<p>isMaster: " . (isMaster() ? 'YES' : 'NO') . "</p>";
echo "<p>hasRole('master'): " . (hasRole('master') ? 'YES' : 'NO') . "</p>";
?>
<a href='logout.php'>Logout</a> | <a href='index.php'>Login</a>