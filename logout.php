<?php
/**
 * sms/logout.php
 * 
 * Standalone session logout handler.
 */
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
