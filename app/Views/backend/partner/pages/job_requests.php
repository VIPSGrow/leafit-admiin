<?php
use Carbon\Carbon;
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);
$default_is_rtl = (!empty($default_language) && isset($default_language[0]['is_rtl'])) ? (int) $default_language[0]['is_rtl'] : 0;
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_is_rtl;
} elseif ($is_rtl === null) {
    $is_rtl = 0;
}
$is_rtl = (int) $is_rtl;

$settings = get_settings('general_settings', true);
$currency = $settings['currency'] ?? '$';
$default_logo = base_url("public/backend/assets/default.png");
?>
<style>
    /* ===== BOOTSTRAP-BASED CUSTOM JOB CSS ===== */
    .jc-container {
        border-radius: 0.5rem;
        border: 1px solid #e9ecef;
        background-color: #fff;
    }

    .jc-list-pane {
        width: 400px;
        flex-shrink: 0;
        background-color: #f8f9fa;
        border-right: 1px solid #e9ecef;
    }

    .jc-details-pane {
        min-height: 600px;
        background-color: #ffffff;
    }

    .jc-scrollable-list {
        max-height: calc(100vh - 280px);
        overflow-y: auto;
        padding: 1rem;
    }

    .jc-scrollable-details {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        padding: 1.5rem;
    }

    /* Custom Scrollbar */
    .jc-scrollable-list::-webkit-scrollbar,
    .jc-scrollable-details::-webkit-scrollbar {
        width: 6px;
    }
    .jc-scrollable-list::-webkit-scrollbar-thumb,
    .jc-scrollable-details::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 10px;
    }

    .jc-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border-left: 4px solid transparent !important;
    }
    .jc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.08) !important;
    }
    .jc-card.active {
        border-left-color: var(--primary-color) !important;
    }

    .jc-card-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: bold;
        color: white;
        object-fit: cover;
        flex-shrink: 0;
    }

    /* Modal Categories */
    .jc-category-card {
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        background-color: #fff;
    }
    .jc-category-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        border-color: #d3d9df;
    }
    .jc-category-card.selected {
        background-color: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
        border-color: var(--primary-color) !important;
    }

    .jc-selected-badge {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        transition: all 0.2s ease-in-out;
        border: 2px solid #dee2e6;
        background-color: transparent;
        color: transparent;
    }
    .jc-category-card.selected .jc-selected-badge {
        border-color: var(--primary-color);
        background-color: var(--primary-color);
        color: white;
    }

    .jc-info-card {
        border: 1px solid var(--primary-color) !important;
        box-shadow: 1px 3px 10px -4px var(--primary-color) !important;
        background-color: #fff !important;
    }

    .jc-category-card {
        flex-direction: row !important;
    }
    .jc-category-card .jc-category-image {
        margin-bottom: 0 !important;
    }

    @media (max-width: 991.98px) {
        .jc-category-card {
            flex-direction: column !important;
            text-align: center;
        }
        .jc-category-card .jc-category-image {
            margin: 0 0 0.5rem 0 !important;
        }
        .jc-category-card > div {
            width: 100%;
            flex-direction: column !important;
            text-align: center;
        }
        .jc-category-card .jc-selected-badge {
            margin: 0.5rem 0 0 0 !important;
        }
    }

    @media (max-width: 991px) {
        .jc-container {
            flex-direction: column;
            border: none;
        }
        .jc-list-pane {
            width: 100%;
            border-right: none;
            background-color: #fff;
        }
        .jc-details-pane {
            display: none;
            width: 100%;
            min-height: auto;
        }
        .jc-scrollable-list {
            max-height: none;
            overflow: visible;
            padding: 1rem 0;
        }
        .jc-scrollable-details {
            max-height: none;
            overflow: visible;
            padding: 1rem 0;
        }
        .jc-container.show-details .jc-list-pane {
            display: none !important;
        }
        .jc-container.show-details .jc-details-pane {
            display: flex !important;
        }
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <h1><?= labels('custom_job_requests', "Custom Job Requests") ?></h1>
                <p class="text-muted mt-2 mb-0">
                    <?= labels('browse_bid_custom_job_requests', 'Browse and bid on custom job requests from customers.') ?>
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <button class="btn btn-outline-secondary mr-2" data-toggle="modal"
                    data-target="#manage_categories" style="font-size: 0.9rem">
                    <i class="fas fa-cog fa-1x mr-1"></i><?= labels('manage_categories', 'Manage Categories') ?>
                </button>
                <button
                    class="btn <?= ($is_accepting_custom_jobs == 1) ? 'btn-outline-primary' : 'btn-outline-secondary' ?> "
                    data-toggle="modal" data-target="#manage_custom_job_setting" style="font-size: 0.9rem">
                    <?php if ($is_accepting_custom_jobs == 1): ?>
                        <i class="fas fa-circle text-success mr-1" style="font-size: 8px; vertical-align: middle;"></i>
                        <?= labels('disable_custom_job_request', 'Disable Custom Job Request') ?>
                    <?php else: ?>
                        <i class="fas fa-circle text-secondary mr-1" style="font-size: 8px; vertical-align: middle;"></i>
                        <?= labels('enable_custom_job_request', 'Enable Custom Job Request') ?>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div class="jc-container d-flex align-items-stretch shadow-sm" id="main-container">
            <!-- LEFT PANE: LIST -->
            <div class="jc-list-pane d-flex flex-column">
                <div class="p-3 border-bottom bg-white">
                    <ul class="nav nav-pills nav-justified" id="jobTabs">
                        <li class="nav-item">
                            <a class="nav-link jc-tab active font-weight-bold" id="tab-open" href="javascript:void(0)" onclick="switchTab('open')">
                                <i class="fas fa-briefcase mr-1"></i> <?= labels('open_jobs', 'Open Jobs') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link jc-tab font-weight-bold" id="tab-applied" href="javascript:void(0)" onclick="switchTab('applied')">
                                <i class="fas fa-check-circle mr-1"></i> <?= labels('applied_jobs', 'Applied Jobs') ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Open Jobs List -->
                <div class="jc-scrollable-list flex-grow-1" id="list-open">
                    <?php if (empty($custom_job_requests)): ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center py-5">
                            <div class="text-muted mb-3" style="font-size: 3rem;"><i class="fas fa-search"></i></div>
                            <h6 class="text-dark font-weight-bold"><?= labels('no_requests_available', 'No custom job requests available') ?></h6>
                            <p class="text-muted"><?= labels('no_requests_message', 'No custom job requests available right now.') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($custom_job_requests as $request):
                            $atts = $request['attachments'] ?? ['images' => [], 'videos' => [], 'audios' => [], 'documents' => []];
                            $disk = fetch_current_file_manager();
                            $cat_img = ($disk == 'local_server') ? base_url('/public/uploads/categories/' . $request['category_image']) : (($disk == 'aws_s3') ? fetch_cloud_front_url('categories', $request['category_image']) : $request['category_image']);
                            ?>
                            <div class="card jc-card shadow-sm mb-3 border" onclick="showDetails(this, 'open')" data-job='<?= htmlspecialchars(json_encode([
                                    "id" => $request["id"],
                                    "title" => $request["service_title"],
                                    "category" => $request["category_name"],
                                    "time_ago" => $request["time_ago"],
                                    "username" => $request["username"],
                                    "initial" => $request["fallback"]["initial"] ?? "U",
                                    "bgColor" => $request["fallback"]["bgColor"] ?? "#6777ef",
                                    "has_image" => $request["has_image"],
                                    "image_url" => $request["image_url"] ?? "",
                                    "min_price" => $request["min_price"],
                                    "max_price" => $request["max_price"],
                                    "starts_at" => $request["starts_at"] ? date("d/m/Y - h:i a", strtotime($request["starts_at"])) : "",
                                    "deadline" => $request["deadline"] ? date("d/m/Y - h:i a", strtotime($request["deadline"])) : "",
                                    "desc" => $request["service_short_description"],
                                    "attachments" => $atts
                                ]), ENT_QUOTES) ?>'>
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-new-primary text-white px-2 py-1"><?= htmlspecialchars($request['category_name']) ?></span>
                                        <small class="text-dark"><i class="far fa-clock mr-1"></i> <?= $request['time_ago'] ?></small>
                                    </div>
                                    <h6 class="card-title text-dark font-weight-bold mb-3" style="line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($request['service_title']) ?>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="d-flex align-items-center">
                                            <?php if ($request['has_image']): ?>
                                                <img src="<?= $request['image_url'] ?>" class="jc-card-avatar shadow-sm mr-2" style="width: 32px; height: 32px; font-size: 14px;" alt="" onerror="this.src='<?= $default_logo ?>'">
                                            <?php else: ?>
                                                <div class="jc-card-avatar shadow-sm mr-2" style="width: 32px; height: 32px; font-size: 14px; background:<?= $request['fallback']['bgColor'] ?>">
                                                    <?= $request['fallback']['initial'] ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="text-dark font-weight-600 text-truncate" style="max-width: 90px; font-size: 0.9rem;"><?= htmlspecialchars($request['username']) ?></span>
                                        </div>
                                        <div class="text-primary font-weight-bold" style="font-size: 0.95rem;">
                                            <?= $currency . number_format((int) $request['min_price'], 0) ?> - <?= $currency . number_format((int) $request['max_price'], 0) ?>
                                        </div>
                                    </div>
                                    <?php
                                    $numImg = count($atts['images']);
                                    $numMic = count($atts['audios']);
                                    $numVid = count($atts['videos']);
                                    $numDoc = count($atts['documents']);
                                    if ($numImg > 0 || $numMic > 0 || $numVid > 0 || $numDoc > 0):
                                        ?>
                                        <div class="mt-3 border-top pt-2 d-flex text-muted small" style="gap:15px;">
                                            <?php if ($numImg > 0): ?><span><i class="far fa-image mr-1"></i><?= $numImg ?></span><?php endif; ?>
                                            <?php if ($numMic > 0): ?><span><i class="fas fa-microphone mr-1"></i><?= $numMic ?></span><?php endif; ?>
                                            <?php if ($numVid > 0): ?><span><i class="fas fa-video mr-1"></i><?= $numVid ?></span><?php endif; ?>
                                            <?php if ($numDoc > 0): ?><span><i class="far fa-file-alt mr-1"></i><?= $numDoc ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Applied Jobs List -->
                <div class="jc-scrollable-list flex-grow-1" id="list-applied" style="display: none;">
                    <?php if (empty($applied_jobs)): ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-center py-5">
                            <div class="text-muted mb-3" style="font-size: 3rem;"><i class="fas fa-file-signature fa-2xs"></i></div>
                            <h6 class="text-dark font-weight-bold"><?= labels('no_jobs_applied', 'No Applied Jobs') ?></h6>
                            <p class="text-muted"><?= labels('no_custom_jobs_applied', 'You have not applied for any custom jobs yet. Please explore available custom job requests and apply to get started.') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applied_jobs as $request):
                            $atts = $request['attachments'] ?? ['images' => [], 'videos' => [], 'audios' => [], 'documents' => []];
                            ?>
                            <div class="card jc-card shadow-sm mb-3 border" onclick="showDetails(this, 'applied')" data-job='<?= htmlspecialchars(json_encode([
                                    "id" => $request["id"],
                                    "title" => $request["service_title"],
                                    "category" => $request["category_name"],
                                    "time_ago" => $request["time_ago"],
                                    "username" => $request["username"],
                                    "initial" => $request["fallback"]["initial"] ?? "U",
                                    "bgColor" => $request["fallback"]["bgColor"] ?? "#6777ef",
                                    "has_image" => $request["has_image"],
                                    "image_url" => $request["image_url"] ?? "",
                                    "min_price" => $request["min_price"],
                                    "max_price" => $request["max_price"],
                                    "starts_at" => $request["starts_at"] ? date("d/m/Y - h:i a", strtotime($request["starts_at"])) : "",
                                    "deadline" => $request["deadline"] ? date("d/m/Y - h:i a", strtotime($request["deadline"])) : "",
                                    "desc" => $request["service_short_description"],
                                    "attachments" => $atts,
                                    "bid" => [
                                        "counter_price" => $request["counter_price"],
                                        "duration" => $request["duration"],
                                        "tax_amount" => $request["tax_amount"],
                                        "tax_percentage" => $request["tax_percentage"],
                                        "note" => $request["note"],
                                        "status" => $request["status"],
                                        "cancel_reason" => $request["cancel_reason"] ?? null,
                                        "cancel_additional_info" => $request["cancel_additional_info"] ?? null
                                    ]
                                ]), ENT_QUOTES) ?>'>
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-new-primary text-white px-2 py-1"><?= htmlspecialchars($request['category_name']) ?></span>
                                        <small class="text-muted"><i class="far fa-clock mr-1"></i> <?= $request['time_ago'] ?></small>
                                    </div>
                                    <h6 class="card-title text-dark font-weight-bold mb-3" style="line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($request['service_title']) ?>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="d-flex align-items-center text-muted">
                                            <i class="fas fa-gavel mr-1 text-warning"></i> <?= labels('your_bid', 'Your Bid') ?>:
                                            <span class="text-dark font-weight-bold ml-1"><?= $currency . number_format($request['counter_price'], 0) ?></span>
                                        </div>
                                        <?php
                                        $st_badge = ['pending' => 'badge-warning', 'booked' => 'badge-success', 'cancelled' => 'badge-danger'];
                                        $b_class = $st_badge[$request['status']] ?? 'badge-secondary';
                                        ?>
                                        <span class="badge <?= $b_class ?> text-capitalize px-2 py-1"><?= $request['status'] ?></span>
                                    </div>
                                    <?php
                                    $numImg = count($atts['images']);
                                    $numMic = count($atts['audios']);
                                    $numVid = count($atts['videos']);
                                    $numDoc = count($atts['documents']);
                                    if ($numImg > 0 || $numMic > 0 || $numVid > 0 || $numDoc > 0):
                                        ?>
                                        <div class="mt-3 border-top pt-2 d-flex text-muted small" style="gap:15px;">
                                            <?php if ($numImg > 0): ?><span><i class="far fa-image mr-1"></i><?= $numImg ?></span><?php endif; ?>
                                            <?php if ($numMic > 0): ?><span><i class="fas fa-microphone mr-1"></i><?= $numMic ?></span><?php endif; ?>
                                            <?php if ($numVid > 0): ?><span><i class="fas fa-video mr-1"></i><?= $numVid ?></span><?php endif; ?>
                                            <?php if ($numDoc > 0): ?><span><i class="far fa-file-alt mr-1"></i><?= $numDoc ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT PANE: DETAILS -->
            <div class="jc-details-pane flex-grow-1 d-flex flex-column">
                <!-- Empty State -->
                <div id="jd-empty" class="d-flex flex-column align-items-center justify-content-center h-100 text-center p-5">
                    <div class="mb-4 text-primary opacity-50" style="font-size: 4rem;">
                        <i class="fas fa-clipboard-list fa-1x"></i>
                    </div>
                    <h4 class="text-dark font-weight-bold"><?= labels('select_job', 'Select a Request a quote') ?></h4>
                    <p class="text-muted mt-2" style="max-width: 400px;">
                        <?= labels('select_job_message', 'Click on any custom job request from the list to view its complete details and submit your bid.') ?>
                    </p>
                </div>

                <!-- Content -->
                <div id="jd-content" class="jc-scrollable-details" style="display: none;">
                    <button class="btn btn-light btn-sm d-lg-none mb-4 font-weight-bold" onclick="hideDetails()">
                        <i class="fas fa-arrow-left mr-2"></i> <?= labels('back', 'Back') ?>
                    </button>

                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <span id="jd-category" class="badge bg-new-primary text-white px-3 py-2 mb-2 font-weight-bold"></span>
                            <h3 id="jd-title" class="text-dark font-weight-bold mb-0" style="line-height: 1.3;"></h3>
                        </div>
                        <div class="text-right">
                            <div class="text-dark text-uppercase font-weight-bold mb-1"><?= labels('customer_budget', 'Customer Budget') ?></div>
                            <h5 id="jd-budget" class="text-primary font-weight-bold mb-0"></h5>
                        </div>
                    </div>

                    <!-- Info Cards Row -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card shadow-sm jc-info-card h-100">
                                <div class="card-body p-3 d-flex align-items-center">
                                    <div id="jd-avatar" class="jc-card-avatar shadow-sm mr-3" style="width: 45px; height: 45px;"></div>
                                    <div>
                                        <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('posted_by', 'Posted By') ?></div>
                                        <div id="jd-username" class="text-dark font-weight-bold" style="font-size: 1.1rem;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card shadow-sm jc-info-card h-100">
                                <div class="card-body p-3 d-flex align-items-center">
                                    <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center shadow-secondary mr-3" style="width: 45px; height: 45px; font-size: 18px;">
                                        <i class="far fa-clock fa-1x"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('starts_at', 'Starts At') ?></div>
                                        <div id="jd-starts-at" class="text-dark font-weight-bold" style="font-size: 1.1rem;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm jc-info-card h-100">
                                <div class="card-body p-3 d-flex align-items-center">
                                    <div class="rounded-circle bg-white text-warning d-flex align-items-center justify-content-center shadow-secondary mr-3" style="width: 45px; height: 45px; font-size: 18px;">
                                        <i class="far fa-calendar-alt fa-1x"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('expires_on', 'Expires On') ?></div>
                                        <div id="jd-deadline" class="text-dark font-weight-bold" style="font-size: 1.1rem;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="card shadow-sm border-0 mb-4" style="background-color: #f8f9fa;">
                        <div class="card-body p-4">
                            <h5 class="text-dark font-weight-bold mb-3"><i class="far fa-file-alt fa-1x text-primary mr-2"></i><?= labels('job_description', 'Request a quote Description') ?></h5>
                            <div id="jd-desc" class="text-dark" style="line-height: 1.6; font-size: 1.05rem;"></div>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div id="jd-atts-section" class="card shadow-sm border-0 mb-4 bg-light" style="display: none;">
                        <div class="card-body p-4">
                            <h5 class="text-dark font-weight-bold mb-3"><i class="fas fa-paperclip text-primary mr-2"></i><?= labels('attachments', 'Attachments') ?></h5>
                            <div class="d-flex flex-wrap align-items-start mb-2">
                                <div id="jd-images" class="d-flex flex-wrap"></div>
                                <div id="jd-videos" class="d-flex flex-wrap"></div>
                            </div>
                            <div id="jd-audios" class="d-flex flex-column mb-2"></div>
                            <div id="jd-docs" class="d-flex flex-wrap"></div>
                        </div>
                    </div>

                    <!-- Bid Form Section (Open Jobs) -->
                    <div id="jd-bid-form-wrap" class="card shadow-sm mb-4" style="display: none; border: 1px solid var(--primary-color);">
                        <div class="card-header bg-new-primary text-white">
                            <h5 class="mb-0 text-white font-weight-bold"><i class="fas fa-gavel fa-1x mr-2"></i><?= labels('submit_your_bid', 'Submit Your Bid') ?></h5>
                        </div>
                        <div class="card-body p-4 bg-white">
                            <?= form_open('partner/make_bid', ['method' => 'post', 'class' => 'form-submit-event jc-bid-form', 'id' => 'apply_bid_form']) ?>
                            <input type="hidden" name="id" id="job-id-input">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="required font-weight-bold"><?= labels('counter_price', 'Counter Price') ?> (<?= $currency ?>)</label>
                                    <input type="number" min="0" class="form-control" name="counter_price" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-bold"><?= labels('select_tax', 'Select Tax') ?></label>
                                    <select name="tax_id" class="form-control">
                                        <option value=""><?= labels('select_tax', 'Select Tax') ?></option>
                                        <?php foreach ($tax_data as $pn): ?>
                                            <option value="<?= $pn['id'] ?>"><?= $pn['title'] ?> (<?= $pn['percentage'] ?>%)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label class="font-weight-bold"><?= labels('how_much_time_you_need_to_perform_this_service', 'Estimated Duration') ?></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="duration" min="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-light font-weight-bold"><?= labels('minutes', 'Min') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="required font-weight-bold"><?= labels('write_cover_note', 'Cover Note') ?></label>
                                    <textarea class="form-control" name="cover_note" rows="4" required></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn bg-new-primary text-white btn-lg btn-block mt-3 shadow-sm font-weight-bold"><i class="fas fa-paper-plane mr-2"></i><?= labels('submit_bid', 'Submit Bid') ?></button>
                            <?= form_close() ?>
                        </div>
                    </div>

                    <!-- Applied Bid Status (Applied Jobs) -->
                    <div id="jd-bid-status-wrap" class="card shadow-sm mb-4 border-0" style="display: none; background-color: #f8f9fa;">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-dark font-weight-bold"><i class="fas fa-file-invoice fa-1x mr-2 text-primary"></i><?= labels('your_bid_details', 'Your Bid Details') ?></h5>
                            <span id="jd-bid-status-banner" class="badge badge-pill py-2 px-3 text-uppercase shadow-sm"></span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-sm-4 mb-3">
                                    <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('your_bid', 'Your Bid') ?></div>
                                    <div id="jd-bid-price" class="h5 text-primary font-weight-bold mb-0"></div>
                                </div>
                                <div class="col-sm-4 mb-3">
                                    <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('duration', 'Duration') ?></div>
                                    <div id="jd-bid-duration" class="h5 text-dark font-weight-bold mb-0"></div>
                                </div>
                                <div class="col-sm-4 mb-3">
                                    <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('tax_details', 'Tax Details') ?></div>
                                    <div id="jd-bid-tax" class="h5 text-dark font-weight-bold mb-0"></div>
                                </div>
                                <div class="col-12 mt-3">
                                    <div class="text-muted small text-uppercase font-weight-bold mb-2"><?= labels('cover_note', 'Cover Note') ?></div>
                                    <div id="jd-bid-note" class="bg-white p-4 rounded text-dark border shadow-sm" style="border-left: 4px solid #6777ef !important; font-size: 1rem; line-height: 1.6;"></div>
                                </div>
                                <div id="jd-cancel-wrap" class="col-12 mt-3" style="display:none;">
                                    <div class="bg-white p-4 rounded border shadow-sm" style="border-left: 4px solid #dc3545 !important;">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-times-circle text-danger mr-2" style="font-size:1.2rem;"></i>
                                            <h6 class="mb-0 text-danger font-weight-bold text-uppercase"><?= labels('cancellation_details', 'Cancellation Details') ?></h6>
                                        </div>
                                        <div id="jd-cancel-reason-row" class="mb-3" style="display:none;">
                                            <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('cancel_reason', 'Cancel Reason') ?></div>
                                            <div id="jd-cancel-reason" class="text-dark" style="font-size:1rem; line-height:1.5;"></div>
                                        </div>
                                        <div id="jd-cancel-info-row" style="display:none;">
                                            <div class="text-muted small text-uppercase font-weight-bold mb-1"><?= labels('additional_info', 'Additional Info') ?></div>
                                            <div id="jd-cancel-info" class="text-dark" style="font-size:1rem; line-height:1.5; white-space:pre-wrap;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<!-- Attachment Preview Modal -->
<div class="modal fade" id="jd_att_preview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0 py-2">
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:1;"><span>×</span></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center" id="jd-att-preview-body" style="min-height:300px;"></div>
        </div>
    </div>
</div>

<!-- MODALS (Manage Categories & Disable Job Settings) -->
<div class="modal fade" id="manage_categories" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold text-dark">
                    <?= labels('manage_category_preference', 'Manage Category Preference') ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>×</span></button>
            </div>
            <div class="modal-body p-4">
                <?= form_open('partner/manage_category_preference', ['method' => 'post', 'class' => 'form-submit-event', 'id' => 'add_Category']) ?>

                <!-- Select All Option -->
                <div
                    class="jc-select-all-wrapper border rounded-md p-3 mb-4 d-flex align-items-center justify-content-between" style="background-color: #f8f9fa;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-cog mr-2 text-primary"></i>
                        <span
                            class="font-weight-bold text-dark"><?= labels('choose_your_service_categories', 'Choose your service categories') ?></span>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="select_all_categories">
                        <label class="custom-control-label font-weight-bold text-dark" for="select_all_categories"
                            style="cursor: pointer;"><?= labels('select_all', 'Select All') ?></label>
                    </div>
                </div>

                <div class="row align-items-stretch">
                    <?php
                    $disk = fetch_current_file_manager();
                    foreach ($categories_name as $d):
                        $cat_img = get_file_url($disk, 'public/uploads/categories/' . $d['image'], 'public/backend/assets/default.png', 'categories');
                        ?>
                        <div class="col-6 col-md-4 mb-3">
                            <label class="jc-category-card <?= in_array($d['id'], $custom_job_categories) ? 'selected' : '' ?> d-flex flex-column flex-md-row align-items-center p-3 rounded-md h-100 m-0" style="width: 100%;">
                                <input type="checkbox" name="category_id[]" value="<?= $d['id'] ?>" <?= in_array($d['id'], $custom_job_categories) ? 'checked' : '' ?> class="jc-category-input" style="position: absolute; opacity: 0; width: 1px; height: 1px; overflow: hidden;">
                                <img class="jc-category-image rounded mb-2 mb-md-0 mr-md-3 flex-shrink-0" src="<?= $cat_img ?>" alt="" style="width: 48px; height: 48px; object-fit: cover;">
                                <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-center align-items-md-center flex-grow-1 text-center text-md-left" style="min-width: 0; width: 100%;">
                                    <div class="jc-category-name m-0 font-weight-bold text-dark pr-md-2" style="font-size: 0.95rem; line-height: 1.3; word-break: break-word;">
                                        <?= $d['name'] ?>
                                    </div>
                                    <div class="jc-selected-badge flex-shrink-0 ml-md-auto mt-2 mt-md-0">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer px-0 pb-0 pt-3 mt-2 border-top">
                    <button type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?= labels('close', 'Close') ?></button>
                    <button type="submit"
                        class="btn bg-new-primary text-white submit_btn font-weight-bold px-4"><?= labels('submit', 'Submit') ?></button>
                </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manage_custom_job_setting" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <?= form_open('partner/manage_accepting_custom_jobs', ['method' => 'post', 'class' => 'form-submit-event']) ?>
                <img src="<?= base_url('public/uploads/site/think.png') ?>" class="img-fluid mb-3"
                    style="max-height:120px" alt="">
                <p class="font-weight-bold text-dark mb-1" style="font-size: 1rem;"><?= labels('are_you_sure', 'Are You Sure?') ?></p>
                <?php if ($is_accepting_custom_jobs == 1): ?>
                    <p class="text-muted">
                        <?= labels('disable_job_service_warning', "You are going to disable open Request a quote service request's.") ?>
                    </p>
                    <input type="hidden" name="custom_job_value" value="0">
                <?php else: ?>
                    <p class="text-muted">
                        <?= labels('custom_service_eligibility', 'You will be eligible to receive Request a quotes.') ?>
                    </p>
                    <input type="hidden" name="custom_job_value" value="1">
                <?php endif; ?>
                <div class="d-flex justify-content-center gap-4 mt-3">
                    <button type="button" class="btn btn-secondary"
                        data-dismiss="modal"><?= labels('not_yet', 'Not Yet') ?></button>
                    <button type="submit"
                        class="btn bg-new-primary text-white submit_btn"><?= labels('continue', 'Continue') ?></button>
                </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<script>
    var appCurrency = '<?= $currency ?>';
    var defaultLogo = '<?= $default_logo ?>';

    function switchTab(tab) {
        $('.jc-tab').removeClass('active');
        $('#tab-' + tab).addClass('active');

        $('#list-open').toggle(tab === 'open');
        $('#list-applied').toggle(tab === 'applied');

        // reset details pane
        hideDetails();
    }

    function hideDetails() {
        $('#main-container').removeClass('show-details');
        $('#jd-empty').removeClass('d-none').addClass('d-flex');
        $('#jd-content').hide();
        $('.jc-card').removeClass('active');
    }

    function showDetails(el, type) {
        // highlight card
        $('.jc-card').removeClass('active');
        $(el).addClass('active');

        // show details pane
        $('#jd-empty').addClass('d-none').removeClass('d-flex');
        $('#jd-content').show();
        $('#main-container').addClass('show-details');

        var d = $(el).data('job');
        if (typeof d === 'string') d = JSON.parse(d);

        // populate data
        $('#jd-category').text(d.category);
        $('#jd-title').text(d.title);
        $('#jd-budget').html(appCurrency + Number(d.min_price).toLocaleString() + ' <span class="text-dark font-weight-lighter mx-1">to</span> ' + appCurrency + Number(d.max_price).toLocaleString());

        if (d.has_image && d.image_url) {
            $('#jd-avatar').html('<img src="' + d.image_url + '" class="rounded-circle shadow-sm" style="width:100%;height:100%;object-fit:cover;" onerror="this.src=\'' + defaultLogo + '\'">').css('backgroundColor', 'transparent');
        } else {
            $('#jd-avatar').html(d.initial).css('backgroundColor', d.bgColor);
        }

        $('#jd-username').text(d.username);
        $('#jd-deadline').text(d.deadline || '-');
        $('#jd-starts-at').text(d.starts_at || '-');
        $('#jd-desc').text(d.desc);

        // attachments
        var atts = d.attachments;
        var hasAtts = (atts.images.length || atts.videos.length || atts.audios.length || atts.documents.length);
        $('#jd-atts-section').toggle(hasAtts > 0);

        $('#jd-images').html(atts.images.map(i => '<a href="' + i.url + '" data-lightbox="job-atts" class="mr-2 mb-2 d-inline-block"><img src="' + i.url + '" class="rounded shadow-sm" style="width:80px; height:80px; object-fit:cover; border: 1px solid #e9ecef;" onerror="this.src=\'' + defaultLogo + '\'"></a>').join(''));
        $('#jd-videos').html(atts.videos.map(v => '<a href="javascript:void(0)" class="jc-att-thumb mr-2 mb-2 d-inline-flex align-items-center justify-content-center bg-dark text-white rounded shadow-sm position-relative" data-att-type="video" data-att-url="' + v.url + '" style="width:80px; height:80px; text-decoration:none; border:1px solid #e9ecef;"><i class="fas fa-play" style="font-size:1.5rem;"></i></a>').join(''));
        $('#jd-audios').html(atts.audios.map(a => '<div class="w-100 mb-2"><audio controls class="w-100 rounded shadow-sm" style="height: 40px;"><source src="' + a.url + '"></audio></div>').join(''));
        $('#jd-docs').html(atts.documents.map(doc => '<a href="' + doc.url + '" target="_blank" class="btn btn-outline-primary btn-sm text-left text-truncate mb-2 mr-2" style="max-width: 250px;"><i class="far fa-file-alt mr-2"></i>' + doc.name + '</a>').join(''));

        // form vs status
        if (type === 'open') {
            $('#jd-bid-status-wrap').hide();
            $('#jd-bid-form-wrap').show();
            $('#job-id-input').val(d.id);
        } else {
            $('#jd-bid-form-wrap').hide();
            $('#jd-bid-status-wrap').show();

            var b = d.bid;
            var stLabel = b.status.toUpperCase();
            var badgeClass = b.status === 'booked' ? 'badge-success' : (b.status === 'cancelled' ? 'badge-danger' : 'badge-warning');
            var icon = b.status === 'booked' ? 'check-circle' : (b.status === 'cancelled' ? 'times-circle' : 'clock');
            
            $('#jd-bid-status-banner').attr('class', 'badge badge-pill py-2 px-3 text-uppercase shadow-sm ' + badgeClass);
            $('#jd-bid-status-banner').html('<i class="fas fa-' + icon + ' mr-1"></i> ' + stLabel);

            $('#jd-bid-price').text(appCurrency + Number(b.counter_price).toLocaleString());
            $('#jd-bid-duration').text(b.duration + ' Mins');
            $('#jd-bid-tax').text(b.tax_amount > 0 ? (appCurrency + Number(b.tax_amount).toLocaleString() + ' (' + b.tax_percentage + '%)') : 'No Tax');
            $('#jd-bid-note').text(b.note);

            var hasReason = b.cancel_reason && String(b.cancel_reason).trim() !== '';
            var hasInfo = b.cancel_additional_info && String(b.cancel_additional_info).trim() !== '';
            if (hasReason || hasInfo) {
                $('#jd-cancel-wrap').show();
                if (hasReason) {
                    $('#jd-cancel-reason').text(b.cancel_reason);
                    $('#jd-cancel-reason-row').show();
                } else {
                    $('#jd-cancel-reason-row').hide();
                }
                if (hasInfo) {
                    $('#jd-cancel-info').text(b.cancel_additional_info);
                    $('#jd-cancel-info-row').show();
                } else {
                    $('#jd-cancel-info-row').hide();
                }
            } else {
                $('#jd-cancel-wrap').hide();
            }
        }
    }

    // Category preference interactions
    $(document).ready(function () {
        // Handle checkbox change (browser handles label click naturally)
        $(document).on('change', '.jc-category-input', function () {
            var card = $(this).closest('.jc-category-card');

            if (this.checked) {
                card.addClass('selected');
            } else {
                card.removeClass('selected');
            }
            updateSelectAllState();
        });

        // Select All toggle
        $(document).on('change', '#select_all_categories', function () {
            var isChecked = this.checked;
            $('.jc-category-input').each(function () {
                if (this.checked !== isChecked) {
                    $(this).prop('checked', isChecked).trigger('change');
                }
            });
        });

        function updateSelectAllState() {
            var total = $('.jc-category-input').length;
            var selected = $('.jc-category-input:checked').length;
            var selectAll = $('#select_all_categories');

            if (total > 0) {
                selectAll.prop('checked', total === selected);
                selectAll.prop('indeterminate', selected > 0 && selected < total);
            }
        }

        // Initial check
        updateSelectAllState();

        // Attachment preview modal
        $(document).on('click', '.jc-att-thumb', function () {
            var type = $(this).data('att-type');
            var url = $(this).data('att-url');
            var html = '';
            if (type === 'image') {
                html = '<img src="' + url + '" class="img-fluid" style="max-height:80vh; object-fit:contain;" onerror="this.src=\'' + defaultLogo + '\'">';
            } else if (type === 'video') {
                html = '<video controls autoplay style="max-width:100%; max-height:80vh; background:#000; display:block; margin:auto;"><source src="' + url + '"></video>';
            }
            $('#jd-att-preview-body').html(html);
            $('#jd_att_preview').modal('show');
        });

        // Stop video on modal close
        $('#jd_att_preview').on('hidden.bs.modal', function () {
            $('#jd-att-preview-body').empty();
        });
    });
</script>