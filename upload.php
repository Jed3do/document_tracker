<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$pdo = getPDO();
$message = '';
$messageType = '';

$offices_stmt = $pdo->query("SELECT * FROM office ORDER BY office_name ASC");
$offices = $offices_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    $uploader_id = $_SESSION['user_id'];
    $receiver_office_id = $_POST['receiver_office_id'];
    $receiver_user_id = $_POST['receiver_user_id'];
    
    $document_title = !empty($_POST['document_title']) ? trim($_POST['document_title']) : basename($_FILES['document']['name']);
    $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    $file = $_FILES['document'];
    $fileName = basename($file['name']);
    $targetDir = "uploads/";
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $uniqueName = time() . "_" . $fileName;
    $targetFilePath = $targetDir . $uniqueName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $allowTypes = array('pdf', 'doc', 'docx', 'jpg', 'png');

    if (in_array(strtolower($fileType), $allowTypes)) {
        if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
            try {
                // Begin transaction to ensure both records are saved together
                $pdo->beginTransaction();

                // 1. Save the document
                $sql = "INSERT INTO document (document_title, description, filename, file_path, uploader_id, status, receiver_office_id, receiver_user_id, due_date) 
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$document_title, $description, $uniqueName, $targetFilePath, $uploader_id, $receiver_office_id, $receiver_user_id, $due_date]);
                
                $newDocId = $pdo->lastInsertId();

                // 2. Create the "Initial Upload" history record (Required for Outgoing list)
                $histSql = "INSERT INTO document_history (document_id, sender_id, receiver_id, office_id, status, remarks, moved_at) 
                            VALUES (?, ?, ?, ?, 'uploaded', 'Initial document upload', NOW())";
                $histStmt = $pdo->prepare($histSql);
                $histStmt->execute([$newDocId, $uploader_id, $receiver_user_id, $receiver_office_id]);

                $pdo->commit();
                $message = "Success! Document \"$document_title\" sent.";
                $messageType = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Database Error: " . $e->getMessage();
                $messageType = "error";
            }
        } else {
            $message = "Error: Could not move file.";
            $messageType = "error";
        }
    } else {
        $message = "Error: Invalid file type.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - DocuTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        .sidebar-gradient { background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); }
        .card-shadow { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .ts-control { border-radius: 0.75rem !important; padding: 0.75rem !important; border: 1.5px solid #475569 !important; color: #0f172a !important; background-color: #ffffff !important; }
        .ts-control input { color: #0f172a !important; font-weight: 500 !important; }
        .ts-control input::placeholder { color: #475569 !important; opacity: 1; }
        .ts-dropdown .option { padding: 10px 12px !important; color: #1e293b !important; }
        .ts-dropdown .active { background-color: #2563eb !important; color: #ffffff !important; }
        .ts-wrapper.disabled .ts-control { background-color: #f1f5f9 !important; border-color: #cbd5e1 !important; opacity: 0.7; }
        label { color: #1e293b !important; font-weight: 600 !important; }
        input[type="text"], textarea, input[type="date"] { border: 1.5px solid #475569 !important; color: #0f172a !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex">

   <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 p-8">
        <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl card-shadow border border-gray-100">
            <h1 class="text-2xl font-bold mb-2 text-gray-800">Upload & Send Document</h1>
            <p class="text-gray-500 mb-8 text-sm">Fill in the details below to start the document routing process.</p>
            
            <?php if ($message): ?>
                <script>
                    Swal.fire({
                        icon: '<?php echo $messageType; ?>',
                        title: '<?php echo $message; ?>',
                        confirmButtonColor: '#3b82f6'
                    });
                </script>
            <?php endif; ?>

            <form action="upload.php" method="post" enctype="multipart/form-data" class="space-y-5">
                <div>
                    <label class="block text-sm mb-2">Document Title</label>
                    <input type="text" name="document_title" required placeholder="e.g. Budget Proposal 2026"
                           class="w-full p-3 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm mb-2">Description / Purpose (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Briefly describe the document contents..."
                              class="w-full p-3 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition resize-none"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-2">Destination Office</label>
                        <select name="receiver_office_id" id="officeSelect" required placeholder="Search Office...">
                            <option value=""></option>
                            <?php foreach ($offices as $office): ?>
                                <option value="<?= $office['id'] ?>"><?= htmlspecialchars($office['office_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm mb-2">Specific Recipient</label>
                        <select name="receiver_user_id" id="userSelect" required placeholder="Search Person...">
                            <option value=""></option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">Deadline (Optional)</label>
                    <input type="date" name="due_date" 
                           class="w-full p-3 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>
                <div class="border-2 border-dashed border-slate-400 rounded-xl p-8 text-center hover:border-blue-500 transition cursor-pointer bg-slate-50/50" 
                     onclick="document.getElementById('fileInput').click()">
                    <input type="file" name="document" id="fileInput" class="hidden" required onchange="displayFileName()">
                    <div class="text-4xl mb-3 text-blue-600"><i class="fas fa-cloud-upload-alt"></i></div>
                    <p class="text-slate-800 font-bold">Click to Select Document</p>
                    <div id="file-name-display" class="mt-4 text-blue-700 font-bold italic text-sm"></div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i> Submit for Routing
                </button>
            </form>
        </div>
    </main>

    <script>
        let officePicker = new TomSelect("#officeSelect", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            onChange: function(value) { fetchUsers(value); }
        });
        let userPicker = new TomSelect("#userSelect", {
            create: false,
            sortField: { field: "text", direction: "asc" }
        });
        userPicker.disable();

        function displayFileName() {
            const input = document.getElementById('fileInput');
            const display = document.getElementById('file-name-display');
            if (input.files.length > 0) {
                display.innerHTML = `<i class="fas fa-file-alt mr-2"></i> Selected: ${input.files[0].name}`;
            }
        }

        async function fetchUsers(officeId) {
            if (!officeId) return;
            userPicker.clearOptions();
            userPicker.disable();
            try {
                const response = await fetch(`get_users_by_office.php?office_id=${officeId}`);
                const users = await response.json();
                if (users.length > 0) {
                    const options = users.map(user => ({ value: user.id, text: user.name }));
                    userPicker.addOptions(options);
                    userPicker.enable();
                } else {
                    Swal.fire({ icon: 'info', title: 'Empty Office', text: 'No employees found in this office.', timer: 2500, showConfirmButton: false });
                }
            } catch (error) { console.error('Error loading users:', error); }
        }
    </script>
</body>
</html>