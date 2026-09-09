<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= $custom_job['service_title'] ?> <?= labels('bids', "Bids") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= $custom_job['service_title'] ?> <?= labels('bids', "Bids") ?></a></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="container-fluid card">
                    <div class="col-xl-12 col-md-12 col-sm-12 mb-sm-2 mt-4">
                        <div class="service_info">
                            <span class="title"><?= labels('description', 'Description') ?></span>
                            <p class="m-0">
                                <?php
                                $description = $custom_job['service_short_description'];
                                $descriptionLength = strlen($description);
                                $shortLength = 100;

                                if ($descriptionLength > $shortLength) {
                                    // Description is longer than 100 characters, show toggle functionality
                                    $shortDesc = substr($description, 0, $shortLength);
                                    $fullDesc = substr($description, $shortLength);
                                ?>
                                    <span id="shortDescription1"><?= $shortDesc ?></span>
                                    <span id="fullDescription1" style="display: none;"><?= $fullDesc ?></span>
                                    <span id="dots1">...</span>
                                    <a href="javascript:void(0)" id="readMoreLink1" onclick="toggleDescription(1)"><?= labels('read_more', 'Read more') ?></a>
                                <?php } else { ?>
                                    <!-- Description is short, show full text without toggle -->
                                    <span><?= $description ?></span>
                                <?php } ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($custom_job_files)) : ?>
                    <div class="col-xl-12 col-md-12 col-sm-12 mb-sm-2 mt-2 px-3">
                        <div class="service_info">
                            <span class="title"><?= labels('attachments', 'Attachments') ?></span>
                            <div class="custom-job-attachments mt-2 d-flex flex-wrap" style="gap: 12px;">
                                <?php foreach ($custom_job_files as $file) : ?>
                                    <?php if ($file['type'] === 'image') : ?>
                                        <a href="<?= esc($file['url']) ?>" data-lightbox="cj-attachments" data-title="<?= esc($file['name']) ?>">
                                            <img src="<?= esc($file['url']) ?>" alt="<?= esc($file['name']) ?>"
                                                class="cj-attachment-thumb cj-attachment-img"
                                                title="<?= esc($file['name']) ?>">
                                        </a>
                                    <?php elseif ($file['type'] === 'video') : ?>
                                        <div class="cj-attachment-thumb cj-attachment-video-wrap">
                                            <video controls preload="metadata" class="w-100 h-100" style="object-fit: cover; border-radius: 6px;" title="<?= esc($file['name']) ?>">
                                                <source src="<?= esc($file['url']) ?>">
                                            </video>
                                        </div>
                                    <?php else : ?>
                                        <a href="<?= esc($file['url']) ?>" target="_blank" class="cj-attachment-thumb cj-attachment-doc d-flex flex-column align-items-center justify-content-center text-center text-decoration-none"
                                            title="<?= esc($file['name']) ?>">
                                            <i class="fas fa-file-alt fa-2x text-primary mb-1"></i>
                                            <small class="text-muted" style="max-width:90px; word-break:break-all; font-size:10px;"><?= esc($file['name']) ?></small>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="row mt-4 mb-3 ">
                                    <div class="col-md-4 col-sm-2 mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" id="customSearchBtn" type="button">
                                                    <i class="fa fa-search d-inline"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown d-inline ml-2">
                                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                            <a class="dropdown-item" onclick="custome_export('pdf','FAQs list','user_list');"><?= labels('pdf', 'PDF') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('excel','FAQs list','user_list');"><?= labels('excel', 'Excel') ?></a>
                                            <a class="dropdown-item" onclick="custome_export('csv','FAQs list','user_list')"><?= labels('csv', 'CSV') ?></a>
                                        </div>
                                    </div>
                                </div>
                                <!-- <table class="table " data-fixed-columns="true" id="user_list" data-detail-formatter="user_formater"
                                    data-auto-refresh="true" data-toggle="table"
                                    data-url="<?= base_url("admin/custom-job/bidders-list/" . $custom_job['id']) ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                    data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                                    data-query-params="faqs_query_params" data-pagination-successively-size="2" data-page-size="10">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="provider_name" class="text-center"><?= labels('provider', 'Provider') ?></th>
                                            <th data-field="counter_price" class="text-center"><?= labels('counter_price', 'Counter Price') ?></th>
                                            <th data-field="duration" class="text-center"><?= labels('duration', 'Duration') ?></th>
                                            <th data-field="truncateWords_note" data-formatter="partnerBidNoteFormatter" class="text-center"><?= labels('note', 'Note') ?></th>
                                        </tr>
                                    </thead>
                                </table> -->
                                <table class="table " data-fixed-columns="true" id="user_list" data-detail-formatter="user_formater"
                                    data-auto-refresh="true" data-toggle="table"
                                    data-url="<?= base_url("admin/custom-job/bidders-list/" . $custom_job['id']) ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                    data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                                    data-query-params="faqs_query_params" data-pagination-successively-size="2" data-page-size="5">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="provider_name" class="text-center"><?= labels('provider', 'Provider') ?></th>
                                            <th data-field="counter_price" class="text-center"><?= labels('counter_price', 'Counter Price') ?></th>
                                            <th data-field="duration" class="text-center"><?= labels('duration', 'Duration') ?></th>
                                            <th data-field="note" class="text-center" data-formatter="partnerBidNoteFormatter"><?= labels('note', 'Note') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<style>
    .cj-attachment-thumb {
        width: 110px;
        height: 110px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        overflow: hidden;
        flex-shrink: 0;
    }
    .cj-attachment-img {
        width: 110px;
        height: 110px;
        object-fit: cover;
        display: block;
        transition: opacity .2s;
    }
    .cj-attachment-img:hover { opacity: .85; }
    .cj-attachment-video-wrap { background: #000; }
    .cj-attachment-doc {
        background: #f8f9fa;
        padding: 8px 4px;
        color: inherit;
    }
    .cj-attachment-doc:hover { background: #e9ecef; }
</style>
<script>
    // Search button click handler - triggers table refresh when search button is clicked
    $("#customSearchBtn").on('click', function() {
        $('#user_list').bootstrapTable('refresh');
    });

    // Allow Enter key to trigger search button click
    $("#customSearch").on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#customSearchBtn').click();
        }
    });
</script>
<script>
    // Match the blog list behavior by showing a trimmed preview with a View More button.
    var BID_NOTE_CHAR_LIMIT = 30;

    function escapeHtml(text) {
        return $('<div/>').text(text || '').html();
    }

    window.partnerBidNoteFormatter = function partnerBidNoteFormatter(value, row, index) {
        var fullNote = row.note || '';
        if (!fullNote.trim()) {
            return '';
        }

        var safeFullNote = escapeHtml(fullNote);

        if (fullNote.length <= BID_NOTE_CHAR_LIMIT) {
            return '<span>' + safeFullNote + '</span>';
        }

        var uniqueId = 'bid_note_' + row.id + '_' + index;
        var trimmedText = escapeHtml(fullNote.substring(0, BID_NOTE_CHAR_LIMIT)) + '...';
        var encodedFullText = btoa(unescape(encodeURIComponent(fullNote)));

        return '<div class="bid-note-content" id="' + uniqueId + '">' +
            '<span class="bid-note-text">' + trimmedText + '</span>' +
            '<br><button type="button" class="btn btn-link btn-sm p-0 mt-1 text-primary bid-note-view-more" ' +
            'onclick="toggleBidNote(\'' + uniqueId + '\', \'' + encodedFullText + '\')">' +
            '<i class="fas fa-eye"></i> <?= labels("view_more", "View More") ?></button>' +
            '</div>';
    };

    window.toggleBidNote = function(elementId, fullTextEncoded) {
        var container = document.getElementById(elementId);
        if (!container) {
            return;
        }

        var button = container.querySelector('.bid-note-view-more');
        var textSpan = container.querySelector('.bid-note-text');
        if (!button || !textSpan) {
            return;
        }

        var fullText = decodeURIComponent(escape(atob(fullTextEncoded)));

        if (button.innerHTML.includes('<?= labels("view_more", "View More") ?>')) {
            textSpan.innerHTML = escapeHtml(fullText);
            button.innerHTML = '<i class="fas fa-eye-slash"></i> <?= labels("view_less", "View Less") ?>';
        } else {
            var trimmedText = fullText.substring(0, BID_NOTE_CHAR_LIMIT) + '...';
            textSpan.innerHTML = escapeHtml(trimmedText);
            button.innerHTML = '<i class="fas fa-eye"></i> <?= labels("view_more", "View More") ?>';
        }
    };

    function toggleDescription(section) {
        var shortDescription = $("#shortDescription" + section);
        var fullDescription = $("#fullDescription" + section);
        var dots = $("#dots" + section);
        var readMoreLink = $("#readMoreLink" + section);

        // Check if elements exist before proceeding
        if (fullDescription.length === 0 || readMoreLink.length === 0) {
            console.log('Toggle elements not found for section: ' + section);
            return;
        }

        if (fullDescription.is(":visible")) {
            // Currently showing full description, switch to short
            fullDescription.hide();
            dots.show();
            readMoreLink.text("<?= labels('read_more', 'Read more') ?>");
        } else {
            // Currently showing short description, switch to full
            fullDescription.show();
            dots.hide();
            readMoreLink.text("<?= labels('read_less', 'Read less') ?>");
        }
    }
</script>