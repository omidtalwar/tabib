<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['role'] . '/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            header('Location: ' . BASE_URL . '/' . $user['role'] . '/');
            exit;
        }
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .bg-pattern {
            background-color: #0f2d6e;
            background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.04) 0%, transparent 50%),
                              radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 40%),
                              radial-gradient(circle at 60% 80%, rgba(255,255,255,0.03) 0%, transparent 40%);
        }
        .wave-lines {
            background-image: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 40px,
                rgba(255,255,255,0.015) 40px,
                rgba(255,255,255,0.015) 41px
            );
        }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- Left Panel -->
    <div class="hidden lg:flex lg:w-3/5 bg-pattern wave-lines flex-col justify-between p-12 relative overflow-hidden">

        <!-- Logo -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="text-white text-xl font-bold tracking-wide"><?= SITE_NAME ?></span>
        </div>

        <!-- Headline -->
        <div>
            <h1 class="text-white text-4xl font-extrabold leading-tight mb-4">
                Advanced Institute<br>Management System
            </h1>
            <p class="text-blue-200 text-base leading-relaxed max-w-md">
                Empowering institutions with a next-generation academic, administrative, and teacher–student ecosystem.
            </p>

            <!-- Feature Tiles -->
            <div class="grid grid-cols-2 gap-4 mt-10 max-w-md">
                <div class="bg-white bg-opacity-10 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Academic</p>
                        <p class="text-blue-200 text-xs">Student & Teacher Core</p>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Analytics</p>
                        <p class="text-blue-200 text-xs">Real-time Insights</p>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Secure</p>
                        <p class="text-blue-200 text-xs">Role-based Access</p>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-9 h-9 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-sm">Support</p>
                        <p class="text-blue-200 text-xs">24/7 Admin Help</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-blue-300 text-xs">© <?= date('Y') ?> <?= SITE_NAME ?>. All Rights Reserved.</p>
    </div>

    <!-- Right Panel: Login Form -->
    <div class="w-full lg:w-2/5 flex items-center justify-center bg-gray-100 p-8">
        <div class="w-full max-w-sm">

            <!-- Institute Badge -->
            <div class="flex justify-center mb-6">
                <div class="w-20 h-20 bg-white rounded-2xl shadow-md flex items-center justify-center">
                    <svg class="w-12 h-12 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>

            <h2 class="text-center text-xl font-bold text-gray-800 mb-6">Sign in to your account</h2>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm text-center">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <input type="email" name="email" required placeholder="Email address"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm shadow-sm">
                </div>
                <div>
                    <input type="password" name="password" required placeholder="Password"
                           class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm shadow-sm">
                </div>
                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-3 rounded-lg transition text-sm tracking-widest uppercase mt-2">
                    Sign In
                </button>
            </form>
        </div>
    </div>

</body>
</html>
