<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// 1. Fetch offices grouped by division for the dropdown
$pdo = getPDO();
// We select 'division' first because FETCH_GROUP uses the first column as the array key
$stmt = $pdo->query("SELECT division, id, office_name FROM office ORDER BY division, office_name");
$offices = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'employee_number' => $_POST['employee_number'],
        'name'            => $_POST['name'],
        'email'           => $_POST['email'],
        'password'        => $_POST['password'],
        'position'        => $_POST['position'],
        'office_id'       => $_POST['office_id'] 
    ];
    
    $result = registerUser($data);
    
    if ($result['success']) {
        loginUser($data['email'], $data['password']);
        header('Location: dashboard.php');
        exit();
    } else {
        $error = $result['message'];
    }
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Document Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        .register-bg {
            background: 
                linear-gradient(135deg, rgba(106, 17, 203, 0.85) 0%, rgba(37, 117, 252, 0.85) 100%),
                url('bisu.background.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        .btn-register { background: linear-gradient(to right, #3b82f6, #1d4ed8); }
        .register-form { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); }
        
        /* IMPROVED PLACEHOLDER VISIBILITY */
        ::placeholder {
            color: #4b5563 !important; /* Darker gray (Tailwind gray-600) */
            opacity: 1 !important;     /* Firefox fix */
        }

        :-ms-input-placeholder { color: #4b5563 !important; }
        ::-ms-input-placeholder { color: #4b5563 !important; }

        /* Custom styling for Tom Select */
        .ts-control {
            padding: 0.75rem 0.75rem 0.75rem 2.5rem !important;
            border-radius: 0.5rem !important;
            border:2px solid #cbd5e1 !important;
            font-size: 1rem !important;
        }

        /* Tom Select Placeholder Visibility */
        .ts-wrapper .ts-control input::placeholder {
            color: #4b5563 !important;
            opacity: 1 !important;
        }
        .ts-wrapper .items-placeholder {
            color: #4b5563 !important;
            opacity: 1 !important;
        }

        .ts-wrapper.focus .ts-control {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
            border-color: #3b82f6 !important;
        }
        .ts-dropdown { border-radius: 0.5rem !important; margin-top: 5px !important; }
        
        select:focus { outline: none; }
    </style>
</head>
<body class="register-bg py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mr-5 overflow-hidden bg-white border-2 border-blue-100 shadow-md">
                        <img src="logo.jpg" alt="Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-white drop-shadow-lg">Document <span class="text-yellow-300">Tracker</span></h1>
                        <p class="text-white/90 text-lg drop-shadow mt-1">Registration Portal</p>
                    </div>
                </div>
            </div>
            
            <div class="register-form rounded-2xl shadow-2xl overflow-hidden">
                <div class="p-8">
                    <?php if (isset($error)): ?>
                        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">Employee Information</h3>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee Number *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fas fa-id-badge"></i>
                                </span>
                                <input type="text" name="employee_number" required class="pl-10 w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="e.g. 2024-001">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" name="name" required class="pl-10 w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter Full Name">
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-4">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">Work Details</h3>
                        </div>

                        <div class="md:col-span-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" name="email" required class="pl-10 w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="email@bisu.edu.ph">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fas fa-briefcase"></i>
                                </span>
                                <input type="text" name="position" required class="pl-10 w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="e.g. Instructor">
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Office / Department *</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 z-10">
                                    <i class="fas fa-building"></i>
                                </span>
                                <select id="office_select" name="office_id" required class="w-full">
                                    <option value="" disabled selected>Search and select office...</option>
                                    <?php if (!empty($offices)): ?>
                                        <?php foreach ($offices as $division => $items): ?>
                                            <optgroup label="<?php echo htmlspecialchars($division); ?>">
                                                <?php foreach ($items as $office): ?>
                                                    <option value="<?php echo htmlspecialchars($office['id']); ?>">
                                                        <?php echo htmlspecialchars($office['office_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No offices available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-4">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">Security</h3>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                            <div class="relative">
                                <input type="password" id="password" name="password" required class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Min. 6 characters">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" onclick="togglePassword('password')" class="text-gray-400"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Re-type password">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <button type="button" onclick="togglePassword('confirm_password')" class="text-gray-400"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 mt-8">
                            <button type="submit" class="w-full btn-register text-white font-semibold py-4 rounded-lg hover:shadow-xl transition transform hover:-translate-y-0.5">
                                Create Account
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-8 text-center text-gray-600">
                        Already have an account? <a href="login.php" class="text-blue-600 font-semibold hover:underline">Sign in here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        new TomSelect("#office_select", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "Search and select office...",
            maxOptions: 100
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const pw = document.getElementById('password').value;
            const cpw = document.getElementById('confirm_password').value;
            if (pw !== cpw) {
                e.preventDefault();
                alert('Passwords do not match!');
            } else if (pw.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters.');
            }
        });

        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>