<!-- Main Content -->
<style>
    /* Icon box styling - ensures perfectly square icons with consistent sizing */
    .icon_box {
        width: 48px;
        height: 48px;
        min-width: 48px;
        min-height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
    }

    /* Icon font size consistency */
    .icon_box i {
        font-size: 20px !important;
    }

    /* Responsive image styling - prevents overflow and maintains aspect ratio */
    .card img {
        max-width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 8px;
    }

    /* Timing details — per-day shift list */
    .timing-day-list .timing-day-item {
        border-color: #eef0f4;
        transition: background-color 0.2s ease;
    }

    .timing-day-list .timing-day-item:hover {
        background-color: #fafbff;
    }

    .timing-day-list .timing-day-item.is-closed {
        opacity: 0.7;
    }

    .timing-day-name {
        min-width: 140px;
    }

    .timing-day-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
    }

    .bg-soft-primary {
        background-color: rgba(103, 119, 239, 0.12);
    }

    .bg-soft-muted {
        background-color: rgba(108, 117, 125, 0.12);
    }

    .timing-shift-chip {
        display: inline-flex;
        align-items: center;
        background-color: #f5f6fb;
        border: 1px solid #e5e7f0;
        border-radius: 999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .timing-shift-num {
        font-weight: 600;
        color: #6777ef;
        margin-right: 0.5rem;
        padding-right: 0.5rem;
        border-right: 1px solid #e5e7f0;
    }

    .timing-shift-time {
        color: #34395e;
        font-variant-numeric: tabular-nums;
        font-weight: 500;
    }
</style>
<div class="main-content">
    <section class="section" id="pill-general_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('partner_details', 'Partner Details') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><?= labels('partner_details', 'Partner Details') ?></div>
                <div class="breadcrumb-item "><?= labels('company_information', 'Company Information') ?></div>
                <div class="breadcrumb-item "><?= htmlspecialchars($partner['rows'][0]['translated_company_name'] ?? $partner['rows'][0]['company_name'] ?? '') ?></div>
            </div>
        </div>
        <?php include "provider_details.php"; ?>
        <div class="section-body">
            <div id="output-status"></div>
            <div class="row mt-3">
                <!-- Company Details start -->
                <div class="col-md-12 col-sm-12 col-xl-8 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('company_details', 'Company Details') ?></div>
                            </div>
                            <div class="col d-flex justify-content-end mr-3 mt-4">
                                <?php
                                $label = ($partner['rows'][0]['is_approved_edit'] == 1) ?
                                    "<div class='tag border-0 rounded-md  bg-emerald-success text-emerald-success mx-2'>" . labels('approved', 'Approved') . "</div>" :
                                    "<div class='tag border-0 rounded-md  bg-emerald-danger text-emerald-danger mx-2'>" . labels('disapproved', 'Disapproved') . "</div>";
                                echo $label;
                                ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php $showMaxServiceableDistance = (($max_serviceable_distance_type ?? 'global') === 'provider_wise'); ?>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-building fa-lg text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('company_name', 'Company Name') ?></span>
                                            <p class="m-0"><?= htmlspecialchars($partner['rows'][0]['translated_company_name'] ?? $partner['rows'][0]['company_name'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fa-solid fa-t text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('type', 'Type') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['type'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fa-thin fa-dollar text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('visiting_charges', 'Visiting Charges') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['visiting_charges'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-map-marker-alt text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('company_address', 'Company Address') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['address'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('number_Of_members', 'Number Of Members') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['number_of_members'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="far fa-calendar-check text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('advance_booking_days', 'Advance Booking Days') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['advance_booking_days'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-location text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('latitude', 'Latitude') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['latitude'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-location text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('longitude', 'Longitude') ?></span>
                                            <p class="m-0"><?= $partner['rows']['0']['longitude'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-info text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('at_store_available', 'Available at store') ?></span>
                                            <p class="m-0"><?= ($partner['rows']['0']['at_store'] == "1")  ? labels('yes', 'Yes') : labels('no', 'No') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-info text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('at_doorstep_available', 'Available at doorstep') ?></span>
                                            <p class="m-0"><?= ($partner['rows']['0']['at_doorstep'] == "1")  ? labels('yes', 'Yes') : labels('no', 'No') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($showMaxServiceableDistance): ?>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-route text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('max_serviceable_distance', 'Max Serviceable Distance') ?></span>
                                            <?php $msd = $partner['rows']['0']['max_serviceable_distance'] ?? null; ?>
                                            <p class="m-0"><?= ($msd === null || $msd === '') ? '-' : htmlspecialchars((string) $msd, ENT_QUOTES, 'UTF-8') . ' ' . labels('km', 'km') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="col-12 col-sm-6 <?= $showMaxServiceableDistance ? 'col-md-4 col-xl-4' : 'col-md-8 col-xl-8' ?> mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('about_company', 'About Company') ?></span>
                                            <p class="m-0">
                                                <?php
                                                // Clean and decode the about text - use translated version with fallback
                                                // Model already handles fallback: selected language → default language → main table
                                                $about_text = html_entity_decode(trim($partner['rows'][0]['translated_about'] ?? $partner['rows'][0]['about'] ?? ''), ENT_QUOTES, 'UTF-8');
                                                $about_length = mb_strlen($about_text, 'UTF-8');

                                                // Debug: Check if text exists and has content
                                                if (!empty($about_text) && $about_length > 100) {
                                                    // Text is long enough to need read more functionality
                                                    $short_about = mb_substr($about_text, 0, 100, 'UTF-8');
                                                    $full_about = $about_text;
                                                ?>
                                                    <span id="shortDescription1"><?= htmlspecialchars($short_about) ?></span>
                                                    <span id="fullDescription1" style="display: none;"><?= htmlspecialchars($full_about) ?></span>
                                                    <span id="dots1">...</span>
                                                    <a href="javascript:void(0)" id="readMoreLink1" onclick="toggleDescription(1)"><?= labels('read_more', 'Read more') ?></a>
                                                <?php } else if (!empty($about_text)) {
                                                    // Text is short, show full text without read more
                                                    echo htmlspecialchars($about_text);
                                                } else {
                                                    // No text available
                                                    echo '<em>' . labels('no_description_available', 'No description available') . '</em>';
                                                } ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('long_description', 'Long Description') ?></span>
                                            <p class="m-0">
                                                <?php
                                                // Long description may contain rich HTML/CSS authored via WYSIWYG —
                                                // preserve markup as-is. Use plain-text length only to decide whether
                                                // the "Read more" toggle is needed, never to truncate the HTML itself
                                                // (truncating HTML by character count would break tags).
                                                $long_desc_html  = trim($partner['rows'][0]['translated_long_description'] ?? $partner['rows'][0]['long_description'] ?? '');
                                                $long_desc_plain = trim(strip_tags($long_desc_html));
                                                $long_desc_length = mb_strlen($long_desc_plain, 'UTF-8');

                                                if (!empty($long_desc_plain) && $long_desc_length > 500) {
                                                ?>
                                                    <div id="longDescriptionContent2" class="long-description-content" style="max-height: 8em; overflow: hidden;">
                                                        <?= $long_desc_html ?>
                                                    </div>
                                                    <a href="javascript:void(0)" id="readMoreLink2" onclick="toggleLongDescription()"><?= labels('read_more', 'Read more') ?></a>
                                                <?php } else if (!empty($long_desc_plain)) {
                                                    // Text is short, render full HTML without read more
                                                    echo $long_desc_html;
                                                } else {
                                                    echo '<em>' . labels('no_description_available', 'No description available') . '</em>';
                                                } ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Company Details end -->
                <!-- Personal Information start -->
                <div class="col-md-12 col-sm-12 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('personal_information', ' Personal Information') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-user text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('name', 'Name') ?></span>
                                            <p class="m-0 text-break"><?= htmlspecialchars($partner['rows'][0]['translated_partner_name'] ?? $partner['rows'][0]['partner_name'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-envelope text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('email', 'Email') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['email'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-phone-alt text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('phone', 'Phone') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['mobile'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-percent text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('commission', 'Commission') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['admin_commission'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-hashtag text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('provider_id', 'Provider ID') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['partner_id'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6 col-xl-12 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-city text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('city', 'City') ?></span>
                                            <p class="m-0 text-break"><?= $partner['rows']['0']['city'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-12 col-sm-6">
                                    <span class="title d-block mb-2"><?= labels('logo', 'Logo') ?></span>
                                    <img src="<?= $logo_url ?>" class="img-fluid rounded d-block mw-100" alt="">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <span class="title d-block mb-2"><?= labels('banner_image', 'Banner Image') ?></span>
                                    <img src="<?= $banner_url ?>" class="img-fluid rounded d-block mw-100" alt="">
                                </div>
                            </div>

                            <?php if (!empty($other_image_urls)): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="col-xl-12 col-md-12 mb-3">
                                            <span class="title"><b class="text-dark"><?= labels('other_images', 'other Images') ?></b></span>
                                        </div>
                                        <div class="row">
                                            <?php foreach ($other_image_urls as $otherImageUrl): ?>
                                                <div class="col-6 mb-3">
                                                    <img src="<?= $otherImageUrl ?>" class="img-fluid" style="border: solid #d6d6dd 1px; background-color:#f4f6f9; border-radius:4px; padding:5px;" alt="">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Personal Information end -->
            </div>
            <div class="row mt-3 ">
                <!-- Bank Details start -->
                <div class="col-md-12 col-sm-12 col-xl-4 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('additional_provider_details', 'Additional Provider Details') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                            $current_language_code = function_exists('get_current_language') ? get_current_language() : '';
                            $custom_field_values = $custom_field_values ?? [];
                            $custom_field_labels_by_language = $custom_field_labels_by_language ?? [];
                            $renderCustomFieldRow = function (array $field) use ($custom_field_values, $custom_field_labels_by_language, $current_language_code) {
                                $cfId       = (int) ($field['id'] ?? 0);
                                $fieldType  = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                                $rawValue   = $custom_field_values[$cfId] ?? '';
                                $label      = $custom_field_labels_by_language[$cfId][$current_language_code] ?? ($field['field_label'] ?? '');
                                $isFile     = $fieldType === 'file';
                                $hasValue   = $rawValue !== '' && $rawValue !== null;
                                $fileKind   = 'none';
                                $fileExt    = '';
                                $mimeType   = '';
                                if ($isFile && $hasValue) {
                                    $fileExt = strtolower(pathinfo(parse_url((string) $rawValue, PHP_URL_PATH) ?? (string) $rawValue, PATHINFO_EXTENSION));
                                    $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'bmp', 'avif'];
                                    $videoExts = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];
                                    $audioExts = ['mp3', 'wav', 'oga', 'aac', 'm4a', 'flac'];
                                    $pdfExts   = ['pdf'];
                                    if (in_array($fileExt, $imageExts, true)) {
                                        $fileKind = 'image';
                                    } elseif (in_array($fileExt, $videoExts, true)) {
                                        $fileKind = 'video';
                                        $videoMimeMap = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4'];
                                        $mimeType = $videoMimeMap[$fileExt] ?? 'video/mp4';
                                    } elseif (in_array($fileExt, $audioExts, true)) {
                                        $fileKind = 'audio';
                                        $audioMimeMap = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'oga' => 'audio/ogg', 'aac' => 'audio/aac', 'm4a' => 'audio/mp4', 'flac' => 'audio/flac'];
                                        $mimeType = $audioMimeMap[$fileExt] ?? 'audio/mpeg';
                                    } elseif (in_array($fileExt, $pdfExts, true)) {
                                        $fileKind = 'pdf';
                                    } else {
                                        $fileKind = 'other';
                                    }
                                }
                                $iconByKind = [
                                    'pdf'   => 'fa-file-pdf',
                                    'video' => 'fa-file-video',
                                    'audio' => 'fa-file-audio',
                                    'other' => 'fa-file-alt',
                                ];
                                ?>
                                <?php if ($isFile): ?>
                                    <div class="col-12 col-sm-6 col-md-6 col-xl-4 mb-3">
                                        <div class="h-100 d-flex flex-column">
                                            <span class="title mb-2 fw-bold text-dark"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="flex-grow-1 d-flex align-items-center justify-content-center border rounded p-2 bg-light" style="min-height: 180px;">
                                                <?php if (!$hasValue): ?>
                                                    <em class="text-muted">—</em>
                                                <?php elseif ($fileKind === 'image'): ?>
                                                    <a href="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                        <img src="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded" style="max-height: 180px; object-fit: contain;" alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                                    </a>
                                                <?php elseif ($fileKind === 'video'): ?>
                                                    <video controls preload="metadata" class="w-100 rounded" style="max-height: 180px;">
                                                        <source src="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($mimeType, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= labels('video_not_supported', 'Your browser does not support the video tag.') ?>
                                                    </video>
                                                <?php elseif ($fileKind === 'audio'): ?>
                                                    <audio controls preload="metadata" class="w-100">
                                                        <source src="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($mimeType, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= labels('audio_not_supported', 'Your browser does not support the audio tag.') ?>
                                                    </audio>
                                                <?php elseif ($fileKind === 'pdf'): ?>
                                                    <object data="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" type="application/pdf" class="w-100" style="height: 180px;">
                                                        <a href="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-center">
                                                            <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                                            <div class="small mt-1"><?= labels('view_file', 'View File') ?></div>
                                                        </a>
                                                    </object>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-center">
                                                        <i class="fas <?= $iconByKind[$fileKind] ?? 'fa-file-alt' ?> fa-2x text-primary"></i>
                                                        <div class="small mt-1">
                                                            <?= labels('view_file', 'View File') ?>
                                                            <?= $fileExt !== '' ? ' (' . htmlspecialchars(strtoupper($fileExt), ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                                        </div>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-12 col-sm-6 col-md-6 col-xl-6 mb-3">
                                        <div class="d-flex align-items-start">
                                            <div class="icon_box">
                                                <i class="fas fa-info-circle text-white"></i>
                                            </div>
                                            <div class="service_info flex-grow-1">
                                                <span class="title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                                <p class="m-0 text-break">
                                                    <?= $hasValue ? htmlspecialchars((string) $rawValue, ENT_QUOTES, 'UTF-8') : '<em class="text-muted">—</em>' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif;
                            };

                            $hasFieldValue = function (array $field) use ($custom_field_values): bool {
                                $cfId = (int) ($field['id'] ?? 0);
                                $val  = $custom_field_values[$cfId] ?? '';
                                if ($val === null || $val === '') {
                                    return false;
                                }
                                // File-type empty placeholders resolved to default.png are not real values.
                                $fieldType = strtolower(trim((string) ($field['field_type'] ?? 'text')));
                                if ($fieldType === 'file' && is_string($val) && stripos($val, 'default.png') !== false) {
                                    return false;
                                }
                                return true;
                            };

                            $documents_cf = array_values(array_filter($documents_custom_fields ?? [], $hasFieldValue));
                            $bank_cf      = array_values(array_filter($bank_details_custom_fields ?? [], $hasFieldValue));
                            ?>

                            <?php if (!empty($documents_cf)): ?>
                                <div class="mb-2">
                                    <h6 class="text-uppercase font-weight-bolder mb-3">
                                        <?= labels('provider_details', 'Provider Details') ?>
                                    </h6>
                                </div>
                                <div class="row mb-3">
                                    <?php foreach ($documents_cf as $field) { $renderCustomFieldRow($field); } ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($documents_cf) && !empty($bank_cf)): ?>
                                <hr class="my-3">
                            <?php endif; ?>

                            <?php if (!empty($bank_cf)): ?>
                                <div class="mb-2">
                                    <h6 class="text-uppercase font-weight-bolder mb-3">
                                        <?= labels('bank_details', 'Bank Details') ?>
                                    </h6>
                                </div>
                                <div class="row mb-3">
                                    <?php foreach ($bank_cf as $field) { $renderCustomFieldRow($field); } ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($documents_cf) && empty($bank_cf)): ?>
                                <em class="text-muted"><?= labels('no_data_available', 'No data available') ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Bank Details end -->
                <!-- Timing Details start -->
                <div class="col-md-12 col-sm-12 col-xl-8 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col ">
                                <div class="toggleButttonPostition"><?= labels('timing_details', 'Timing Details') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                            $timingPartnerId = $partner['rows'][0]['partner_id'] ?? 0;
                            $orderedDays = [
                                'monday'    => labels('monday', 'Monday'),
                                'tuesday'   => labels('tuesday', 'Tuesday'),
                                'wednesday' => labels('wednesday', 'Wednesday'),
                                'thursday'  => labels('thursday', 'Thursday'),
                                'friday'    => labels('friday', 'Friday'),
                                'saturday'  => labels('saturday', 'Saturday'),
                                'sunday'    => labels('sunday', 'Sunday'),
                            ];
                            $shiftsByDay = [];
                            $shiftRows = fetch_details('provider_shifts', ['partner_id' => $timingPartnerId], '', '', '', 'shift_number', 'ASC');
                            foreach ($shiftRows as $row) {
                                $shiftsByDay[$row['day']][] = $row;
                            }
                            ?>
                            <ul class="list-group list-group-flush timing-day-list">
                                <?php foreach ($orderedDays as $dayKey => $dayDefault):
                                    $shifts = $shiftsByDay[$dayKey] ?? [];
                                    $isOpen = !empty($shifts) && (int) $shifts[0]['is_open'] === 1;
                                    ?>
                                    <li class="list-group-item px-0 py-3 timing-day-item <?= $isOpen ? '' : 'is-closed' ?>">
                                        <div class="d-flex flex-wrap align-items-center">
                                            <div class="timing-day-name d-flex align-items-center mr-3">
                                                <span class="timing-day-icon mr-2 <?= $isOpen ? 'bg-soft-primary text-primary' : 'bg-soft-muted text-muted' ?>">
                                                    <i class="far fa-clock"></i>
                                                </span>
                                                <span class="font-weight-bold text-dark"><?= labels($dayKey, $dayDefault) ?></span>
                                            </div>

                                            <div class="timing-day-shifts flex-grow-1">
                                                <?php if ($isOpen && !empty($shifts)): ?>
                                                    <div class="d-flex flex-wrap">
                                                        <?php
                                                        $formatShiftTime = static function ($value): string {
                                                            $value = trim((string) $value);
                                                            if ($value === '') {
                                                                return '';
                                                            }
                                                            $ts = strtotime($value);
                                                            return $ts ? date('h:i A', $ts) : substr($value, 0, 5);
                                                        };
                                                        foreach ($shifts as $i => $shift):
                                                            $start = $formatShiftTime($shift['opening_time']);
                                                            $end   = $formatShiftTime($shift['closing_time']);
                                                            ?>
                                                            <span class="timing-shift-chip mr-2 mb-1">
                                                                <span class="timing-shift-num"><?= labels('shift', 'Shift') ?> <?= $i + 1 ?></span>
                                                                <span class="timing-shift-time">
                                                                    <?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>
                                                                    <span class="text-muted mx-1">—</span>
                                                                    <?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>
                                                                </span>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted font-italic"><?= labels('closed', 'Closed') ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="ml-auto">
                                                <?php if ($isOpen): ?>
                                                    <span class="tag border-0 rounded-md bg-emerald-success text-emerald-success">
                                                        <?= labels('open', 'Open') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="tag border-0 rounded-md bg-emerald-danger text-emerald-danger">
                                                        <?= labels('closed', 'Closed') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Timing Details end -->
            </div>
            <div class="row mt-3">
                <!-- Scheduling Configuration start -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col">
                                <div class="toggleButttonPostition"><?= labels('scheduling_configuration', 'Scheduling Configuration') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php
                                $ss = $slot_settings ?? [];
                                $ss_interval     = (int)($ss['slot_interval']             ?? 30);
                                $ss_allowMulti   = (int)($ss['allow_multiple_bookings']   ?? 0) === 1;
                                $ss_capacity     = (int)($ss['slot_capacity']             ?? 1);
                                $ss_minValue     = (int)($ss['min_advance_booking_value'] ?? 1);
                                $ss_minUnit      = (string)($ss['min_advance_booking_unit'] ?? 'hours');
                                $ss_maxDays      = (int)($ss['max_advance_booking_days']  ?? 30);
                                $ss_sameDay      = (int)($ss['same_day_booking']          ?? 1) === 1;
                                $ss_bufBefore    = (int)($ss['buffer_before']             ?? 0);
                                $ss_bufAfter     = (int)($ss['buffer_after']              ?? 0);
                                $unitLabel       = labels($ss_minUnit, ucfirst($ss_minUnit));
                                $yes             = labels('yes', 'Yes');
                                $no              = labels('no', 'No');
                                $minsTxt         = labels('minutes', 'minutes');
                            ?>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-stopwatch text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('slot_interval', 'Slot Interval') ?></span>
                                            <p class="m-0"><?= $ss_interval ?> <?= $minsTxt ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('allow_multiple_bookings', 'Allow Multiple Concurrent Bookings') ?></span>
                                            <p class="m-0"><?= $ss_allowMulti ? $yes : $no ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($ss_allowMulti): ?>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-layer-group text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('concurrent_booking_capacity', 'Concurrent Booking Capacity') ?></span>
                                            <p class="m-0"><?= $ss_capacity ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-hourglass-start text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('minimum_advance_booking', 'Minimum Advance Booking') ?></span>
                                            <p class="m-0"><?= $ss_minValue ?> <?= $unitLabel ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-calendar-alt text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('maximum_future_booking_days', 'Maximum Future Booking Days') ?></span>
                                            <p class="m-0"><?= $ss_maxDays ?> <?= labels('days', 'Days') ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="far fa-calendar-check text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('same_day_booking', 'Same Day Booking') ?></span>
                                            <p class="m-0"><?= $ss_sameDay ? $yes : $no ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-arrow-left text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('buffer_before_booking', 'Buffer Before Booking') ?></span>
                                            <p class="m-0"><?= $ss_bufBefore ?> <?= $minsTxt ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="icon_box">
                                            <i class="fas fa-arrow-right text-white"></i>
                                        </div>
                                        <div class="service_info flex-grow-1">
                                            <span class="title"><?= labels('buffer_after_booking', 'Buffer After Booking') ?></span>
                                            <p class="m-0"><?= $ss_bufAfter ?> <?= $minsTxt ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Scheduling Configuration end -->
            </div>
            <div class="row mt-3">
                <!-- Provider Leaves start -->
                <div class="col-md-12 col-sm-12 col-xl-12 mb-3">
                    <div class="card h-100">
                        <div class="row pl-3">
                            <div class="col">
                                <div class="toggleButttonPostition"><?= labels('leaves', 'Leaves') ?></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-lg">
                                    <table class="table" id="partner_company_leaves_list" data-toggle="table"
                                        data-url="<?= base_url('admin/partners/provider_leaves_list') ?>"
                                        data-side-pagination="server" data-pagination="true"
                                        data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                        data-sort-name="leave_date" data-sort-order="desc"
                                        data-query-params="partner_company_leaves_query_params"
                                        data-pagination-successively-size="1">
                                        <thead>
                                            <tr>
                                                <th data-field="leave_date" class="text-center" data-sortable="true"><?= labels('leave_date', 'Leave Date') ?></th>
                                                <th data-field="day" class="text-center" data-sortable="true"><?= labels('day', 'Day') ?></th>
                                                <th data-field="shifts_count" class="text-center" data-sortable="false"><?= labels('shifts_on_leave', 'Shifts On Leave') ?></th>
                                                <th data-field="shifts" class="text-center" data-sortable="false"><?= labels('shift_details', 'Shift Details') ?></th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Provider Leaves end -->
            </div>
        </div>
</div>
</section>
</div>
<script>
    var partnerCompanyLeavesUserSorted = false;
    var partnerCompanyLeavesPartnerId = <?= (int) ($partner['rows'][0]['partner_id'] ?? 0) ?>;

    $(document).ready(function () {
        $('#partner_company_leaves_list').on('sort.bs.table', function () {
            partnerCompanyLeavesUserSorted = true;
        });
    });

    function partner_company_leaves_query_params(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            user_sorted: partnerCompanyLeavesUserSorted ? 1 : 0,
            partner_id: partnerCompanyLeavesPartnerId,
        };
    }
</script>
<script>
    function toggleDescription(section) {
        var shortDescription = $("#shortDescription" + section);
        var fullDescription = $("#fullDescription" + section);
        var dots = $("#dots" + section);
        var readMoreLink = $("#readMoreLink" + section);

        // Debug: Check if elements exist
        if (shortDescription.length === 0 || fullDescription.length === 0 || dots.length === 0 || readMoreLink.length === 0) {
            console.error("Toggle elements not found for section " + section);
            return;
        }

        if (fullDescription.is(":visible")) {
            fullDescription.hide();
            shortDescription.show();
            dots.show();
            readMoreLink.text("<?= labels('read_more', 'Read more') ?>");
        } else {
            fullDescription.show();
            shortDescription.hide();
            dots.hide();
            readMoreLink.text("<?= labels('read_less', 'Read less') ?>");
        }
    }

    // Long description preserves authored HTML/CSS, so we cannot safely cut it
    // by character count. Toggle expand/collapse via a max-height cap instead.
    function toggleLongDescription() {
        var content = document.getElementById('longDescriptionContent2');
        var link    = document.getElementById('readMoreLink2');
        if (!content || !link) { return; }
        if (content.style.maxHeight) {
            content.style.maxHeight = '';
            link.textContent = "<?= labels('read_less', 'Read less') ?>";
        } else {
            content.style.maxHeight = '8em';
            link.textContent = "<?= labels('read_more', 'Read more') ?>";
        }
    }
</script>
