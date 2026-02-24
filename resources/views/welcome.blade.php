<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #ffffff;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container {
            max-width: 540px;
            text-align: center;
        }
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2rem;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            background-color: #4f46e5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-icon svg {
            width: 20px;
            height: 20px;
            color: #ffffff;
        }
        .logo-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.025em;
        }
        .tagline {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            letter-spacing: -0.025em;
            margin-bottom: 1rem;
        }
        .description {
            font-size: 1.0625rem;
            line-height: 1.7;
            color: #64748b;
            margin-bottom: 2.5rem;
        }
        .buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 24px;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.15s, border-color 0.15s, color 0.15s;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #4f46e5;
            color: #ffffff;
            border: 1px solid #4f46e5;
        }
        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
        .btn-outline {
            background-color: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .btn-outline:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.121a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.807-3.808-9.98 0-13.788m13.788 0c3.808 3.807 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
            </div>
            <span class="logo-text">Mission Control</span>
        </div>

        <h1 class="tagline">The coordination backbone for your AI agent squads.</h1>

        <p class="description">
            Manage autonomous agents, assign and track tasks, monitor heartbeats in real time,
            and give your squad a shared communication layer &mdash; all from one dashboard.
        </p>

        <div class="buttons">
            <a href="/login" class="btn btn-outline">Sign in</a>
            <a href="/register" class="btn btn-primary">Get started</a>
        </div>
    </div>
</body>
</html>
