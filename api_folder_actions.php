<?php
/**
 * API endpoint for managing folders (create/delete)
 * 
 * This script handles AJAX requests from the frontend to:
 * - Create new folders for the user's office
 * - Delete existing folders (with security checks)
 * 
 * All responses are returned as JSON for client-side processing
 */

// Load configuration and authentication functions
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Set response format to JSON for all outputs from this script
header('Content-Type: application/json');

/**
 * Check if user is logged in
 * Prevents unauthorized access to folder management functions
 */
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit; // Stop execution immediately
}

// Initialize database connection
$pdo = getPDO();

// Parse incoming JSON request body (from fetch/axios/AJAX calls)
$data = json_decode(file_get_contents('php://input'), true);

// Determine which action the client wants to perform
$action = $data['action'] ?? '';

// Get current user's identifiers from session (set during login)
$user_id = $_SESSION['user_id'];     // Who is performing the action
$office_id = $_SESSION['office_id']; // Which office the user belongs to

/**
 * ACTION 1: CREATE a new folder
 * 
 * Purpose: Allow users to organize documents by creating named folders
 * within their office's workspace
 */
if ($action === 'create') {
    // Sanitize and trim the folder name provided by the user
    $name = trim($data['folder_name'] ?? '');
    
    // Validation: Folder name cannot be empty
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Folder name is required']);
        exit;
    }

    try {
        /**
         * Insert new folder into database
         * - folder_name: User-provided name
         * - office_id: Ensures folders are scoped to the correct office
         * - creator_id: Tracks who created the folder (audit trail)
         */
        $stmt = $pdo->prepare("INSERT INTO folders (folder_name, office_id, creator_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $office_id, $user_id]);
        
        // Success response - client can now refresh folder list
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        /**
         * Handle duplicate folder names
         * '23505' is PostgreSQL's unique violation error code
         * Prevents two folders with the same name within an office
         */
        if ($e->getCode() == '23505') {
            echo json_encode(['success' => false, 'message' => 'This folder already exists in your office.']);
        } else {
            // Catch any other database errors (connection, schema issues, etc.)
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
    }
} 

/**
 * ACTION 2: DELETE an existing folder
 * 
 * Purpose: Allow users to remove unwanted folders
 * Includes security check to prevent deleting folders from other offices
 */
elseif ($action === 'delete') {
    $folder_id = $data['folder_id'] ?? 0;
    
    /**
     * SECURITY CHECK: Delete ONLY if folder belongs to the user's office
     * 
     * This prevents:
     * - Users from guessing folder IDs and deleting other offices' folders
     * - Accidental cross-office data contamination
     * 
     * The WHERE clause with both id AND office_id ensures office isolation
     */
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ? AND office_id = ?");
    $stmt->execute([$folder_id, $office_id]);
    
    /**
     * Check if any row was actually deleted
     * rowCount() > 0 means deletion succeeded
     * rowCount() == 0 means folder didn't exist or didn't belong to this office
     */
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Folder not found or access denied.']);
    }
}

// Note: Invalid or missing actions simply return no response (intentional)
// The client should only send 'create' or 'delete' actions
?>