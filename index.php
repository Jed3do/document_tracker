<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: 
                linear-gradient(135deg, rgba(30, 60, 114, 0.85) 0%, rgba(42, 82, 152, 0.85) 100%),
                url('bisu.background.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Responsive background */
        @media (max-width: 768px) {
            .gradient-bg {
                background-attachment: scroll;
            }
        }
        
        .btn-primary {
            background: linear-gradient(to right, #3b82f6, #1d4ed8);
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #2563eb, #1e40af);
        }
        
        /* Enhance card readability */
        .content-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="container mx-auto px-4 py-16">
        <div class="max-w-4xl mx-auto content-card rounded-2xl shadow-2xl overflow-hidden">
            <div class="md:flex">
                <div class="md:w-1/2 p-12">
                    <div class="flex items-center mb-8">
                        <!-- Updated: Larger logo with better visibility -->
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mr-4 overflow-hidden bg-white border-2 border-blue-100 shadow-md backdrop-blur-sm">
                            <img src="logo.jpg" alt="Document Tracker Logo" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h1 class="text-4xl font-bold text-gray-800">Document <span class="text-blue-600">Tracker</span></h1>
                            <p class="text-gray-600 mt-1 text-sm">Efficient Document Tracking System</p>
                        </div>
                    </div>
                    
                    <h2 class="text-3xl font-bold text-gray-800 mb-6">Streamline Your Document Management</h2>
                    <p class="text-gray-600 mb-8 text-lg">
                        A comprehensive solution for tracking, managing, and organizing your  documents efficiently. 
                        Secure, reliable, and easy to use.
                    </p>
                    
                    <div class="space-y-4 mb-12">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-cyan-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Real-time document tracking</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Department-level access control</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-gray-700">Secure authentication system</span>
                        </div>
                    </div>
                </div>
                
                <div class="md:w-1/2 bg-gradient-to-br from-cyan-50/95 to-blue-50/95 p-12 flex flex-col justify-center">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-800 mb-8">Get Started Now</h3>
                        <div class="space-y-4">
                            <a href="login.php" class="block w-full btn-primary text-white font-semibold py-3 px-6 rounded-lg transition duration-300 transform hover:scale-105 shadow-lg">
                                Sign In to Your Account
                            </a>
                            <p class="text-gray-600 my-4">OR</p>
                            <a href="register.php" class="block w-full bg-white/95 border-2 border-blue-500 text-blue-600 font-semibold py-3 px-6 rounded-lg hover:bg-blue-50 transition duration-300 transform hover:scale-105">
                                Create New Account
                            </a>
                        </div>
                        
                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-700 mb-4">Technologies Used</h4>
                            <div class="flex justify-center space-x-6">
                                <div class="text-center">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <span class="font-bold text-blue-600">PHP</span>
                                    </div>
                                    <span class="text-sm text-gray-600">PHP 8.x</span>
                                </div>
                                <div class="text-center">
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <span class="font-bold text-red-600">XAMPP</span>
                                    </div>
                                    <span class="text-sm text-gray-600">XAMPP</span>
                                </div>
                                <div class="text-center">
                                    <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                        <span class="font-bold text-cyan-600">PG</span>
                                    </div>
                                    <span class="text-sm text-gray-600">pgAdmin 4</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-8 text-white drop-shadow-lg">
            <p class="opacity-90">© 202 Document Tracker System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>