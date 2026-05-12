<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $result = loginUser($email, $password);
    
    if ($result['success']) {
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
    <title>Login - Document Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Font Awesome for eye icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-bg {
            background: 
                linear-gradient(rgba(102, 126, 234, 0.85), rgba(118, 75, 162, 0.85)),
                url('bisu.background.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Ensure the image covers properly on all devices */
        @media (max-width: 768px) {
            .login-bg {
                background-attachment: scroll;
            }
        }
        
        .btn-login {
            background: linear-gradient(to right, #3b82f6, #1d4ed8);
        }
        .btn-login:hover {
            background: linear-gradient(to right, #2563eb, #1e40af);
        }
        
        /* Glass effect for the login box */
        .login-box {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        /* Eye icon styling */
        .eye-toggle {
            cursor: pointer;
            transition: color 0.2s;
        }
        .eye-toggle:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body class="login-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden login-box">
            <!-- Header with semi-transparent background to show more of the background image -->
            <div class="bg-gradient-to-r from-blue-600/90 to-cyan-500/90 p-8 text-center">
                <div class="flex items-center justify-center mb-4">
                    <!-- Updated: Replace SVG with logo.jpg -->
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mr-4 overflow-hidden bg-white border-2 border-blue-100 shadow-md backdrop-blur-sm">
                        <img src="logo.jpg" alt="Document Tracker Logo" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white">Document <span class="text-yellow-300">Tracker</span></h1>
                       
                    </div>
                </div>
                <p class="text-white/90">Sign in to your account</p>
            </div>
            
            <!-- Login Form -->
            <div class="p-8">
                <?php if (isset($error)): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <input type="email" id="email" name="email" required
                                class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your email">
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="pl-10 pr-10 w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter your password">
                            <!-- Eye icon for password toggle -->
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" onclick="togglePassword()" class="eye-toggle text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800">
                            Forgot password?
                        </a>
                    </div>
                    
                    <button type="submit"
                        class="w-full btn-login text-white font-semibold py-3 px-4 rounded-lg hover:shadow-lg transition duration-300 transform hover:-translate-y-0.5">
                        Sign In
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Don't have an account?
                        <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-800 ml-1">
                            Sign up here
                        </a>
                    </p>
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <p class="text-center text-sm text-gray-500">
                        © 2026 Document Tracker System
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Simple JavaScript for password toggle -->
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.eye-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>