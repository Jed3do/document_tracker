<?php
/**
 * Archive Document Handler
 * 
 * Purpose: Marks a specific document as archived when the user clicks an "Archive" button.
 * Only the document owner (uploader) can archive their own documents.
 * 
 * This script is called via POST request from a form on track.php
 */

// Relative paths work best here since these are in the same folder
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check for POST request AND required parameter 'doc_id'
// Prevents direct URL access or malformed requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'])) {
    
    // Establish database connection
    $pdo = getPDO();
    
    // Get the document ID from the submitted form
    $doc_id = $_POST['doc_id'];
    
    // Get the currently logged-in user's ID from session
    $user_id = $_SESSION['user_id'];

    try {
        /**
         * Soft-delete/Archive the document
         * 
         * - Uses UPDATE instead of DELETE (preserves data for audit/legal purposes)
         * - Table name "document" is quoted because it's a reserved keyword in PostgreSQL
         * - WHERE clause includes BOTH id AND uploader_id for security:
         *   Prevents users from archiving documents they don't own
         */
        $stmt = $pdo->prepare("UPDATE \"document\" SET is_archived = TRUE WHERE id = ? AND uploader_id = ?");
        $stmt->execute([$doc_id, $user_id]);

        /**
         * Redirect back to the tracking page with success indicator
         * 
         * The ?archived=success query parameter allows track.php to:
         * - Show a confirmation message to the user
         * - Refresh the document list without showing archived items (or showing them filtered)
         */
        header('Location: track.php?archived=success');
        exit(); // Always call exit after header redirect to stop script execution
        
    } catch (PDOException $e) {
        /**
         * Error handling: Display database error and stop execution
         * 
         * Note: In production, you might want to log this error instead
         * of showing it directly to the user for security reasons.
         */
        die("Error archiving document: " . $e->getMessage());
    }
}

// If this point is reached, the script was accessed without proper POST data
// (Intentionally no response - might want to add a redirect or error message here)
?>