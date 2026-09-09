<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <meta name="description" content="<?= esc($meta_description) ?>">
    <link rel="icon" href="<?= base_url() . 'public/uploads/site/' . ($settings['favicon'] ?? '') ?>" type="image/gif" sizes="16x16">
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #212529;
            background: #f5f6f8;
            line-height: 1.7;
        }

        .legal-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }

        .legal-header img {
            height: 36px;
            width: auto;
        }

        .legal-header h1 {
            font-size: 1.1rem;
            margin: 0;
            font-weight: 600;
        }

        .legal-content-wrapper {
            max-width: 800px;
            margin: 2.5rem auto;
            padding: 2rem 2.5rem;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .legal-content-wrapper h1,
        .legal-content-wrapper h2,
        .legal-content-wrapper h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .legal-content-wrapper p {
            margin-bottom: 1rem;
        }

        .legal-content-wrapper ul,
        .legal-content-wrapper ol {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
        }

        .legal-content-wrapper img {
            max-width: 100%;
            height: auto;
        }

        .legal-content-wrapper table {
            max-width: 100%;
            overflow-x: auto;
            display: block;
        }

        @media print {
            body {
                background: #fff;
            }

            .legal-content-wrapper {
                box-shadow: none;
                border: none;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <header class="legal-header">
        <div class="legal-header-brand">
            <?php if (!empty($settings['favicon'])) : ?>
                <img src="<?= base_url() . 'public/uploads/site/' . $settings['favicon'] ?>" alt="<?= esc($settings['company_title'] ?? '') ?>">
            <?php endif; ?>
            <h1><?= esc($settings['company_title'] ?? '') ?></h1>
        </div>
    </header>
    <main class="legal-content-wrapper">
        <?= html_entity_decode($content) ?>
    </main>
</body>

</html>
