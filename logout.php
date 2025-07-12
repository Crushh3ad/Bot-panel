<?php
session_start();

// Session zerstören
session_destroy();
 
// Weiterleitung zur Login-Seite
header('Location: index.php');
exit;
?> 