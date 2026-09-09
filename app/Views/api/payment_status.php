<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --error-color: #dc3545;
            --bg-color: #f8f9fa;
        }
        body {
            font-family: 'Outfit', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: var(--bg-color);
        }
        .container {
            background: white;
            padding: 3rem 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 400px;
            width: 90%;
            transition: transform 0.3s ease;
        }
        .container:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 1.5rem;
        }
        .icon-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        .icon-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-color);
        }
        h2 { margin: 0 0 1rem; color: #333; }
        p { color: #666; margin-bottom: 2rem; line-height: 1.5; }
        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        .btn:hover {
            background: #0056b3;
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-box <?= $error ? 'icon-error' : 'icon-success' ?>">
            <?php if ($error): ?>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php else: ?>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php endif; ?>
        </div>
        <div class="status-badge <?= $error ? 'badge-error' : 'badge-success' ?>">
            <?= $payment_status ?>
        </div>
        <h2><?= $message ?></h2>
        <p><?= labels('return_to_app_message', 'You can now return to the app to continue using our services.') ?></p>
        
        <?php if ($platform != 'web'): ?>
            <a href="<?= $deeplink ?>" id="return-btn" class="btn"><?= labels('return_to_app_btn', 'Return to App') ?></a>
        <?php else: ?>
            <p><?= labels('payment_complete_window_close_message', 'Your payment process is complete. You can close this window.') ?></p>
        <?php endif; ?>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function () {
            <?php if ($platform != 'web'): ?>
            let appScheme = "<?= $deeplink ?>";
            let androidAppStoreLink = '<?= $customerPlaystoreUrl ?>';
            let iosAppStoreLink = '<?= $customerAppStoreUrl ?>';
            let userAgent = navigator.userAgent || navigator.vendor || window.opera;
            let isAndroid = /android/i.test(userAgent);
            let isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
            let appStoreLink = isAndroid ? androidAppStoreLink : (isIOS ? iosAppStoreLink : androidAppStoreLink);

            function openApp() {
                window.location.href = appScheme;
                setTimeout(function () {
                    if (!document.hidden && !document.webkitHidden) {
                        if (confirm("<?= $appName ?> " + "<?= labels('app_not_installed_confirm', 'app is not installed. Would you like to download it from the app store?') ?>")) {
                            window.location.href = appStoreLink;
                        }
                    }
                }, 1500);
            }

            // Bind to button
            document.getElementById('return-btn').addEventListener('click', function(e) {
                e.preventDefault();
                openApp();
            });

            // Auto-redirect try
            setTimeout(openApp, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>
