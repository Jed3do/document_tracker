<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) { exit("Access Denied"); }

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']); // Security: prevent directory traversal
    $filepath = 'uploads/' . $filename;

    if (file_exists($filepath)) {
        // Determine the content type (e.g., image/jpeg or application/pdf)
        $mime_type = mime_content_type($filepath);
        header("Content-Type: $mime_type");
        readfile($filepath);
        exit;
    } else {
        echo "Error: The physical file was not found in the uploads folder.";
    }
}
?>