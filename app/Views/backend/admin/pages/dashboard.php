<!-- Main Content -->
<?php

$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
$current_url = current_url();
$session = \Config\Services::session();
$is_rtl = $session->get('is_rtl');
$language = $session->get('language');
$default_language = fetch_details('languages', ['is_default' => '1']);

// Only check default language's RTL status if no language is set in session
// Otherwise, use the explicit is_rtl value from session
if (empty($language) && !isset($is_rtl)) {
    $is_rtl = $default_language[0]['is_rtl'];
} elseif ($is_rtl === null) {
    // Fallback if is_rtl is not set but language is
    $is_rtl = 0;
}

// Convert to integer value for consistency
$is_rtl = (int)$is_rtl;
?>

<div class="main-content">
    <div id="filterBackdrop"></div>

    <section class="section mt-5">
        <div class="row mb-2">
            <div class="m-0 col-xxl-6 col-lg-12 col-xl-6 ">
                <div class="row ">
                    <div class="col-lg-6 col-md-12 col-xl-6 col-xxl-3 mb-30 ">
                        <div class="card h-100 mb-30 p-2 bg-new-primary">
                            <div class="business-summary-earning mt-1 mx-2">
                                <div class="mb-2" id="greeting"></div>
                                <h5 class=" pt-1"><?= labels('hello', "Hello ") ?>, <?= $user1[0]['username'] ?></h5>
                                <div class="pt-4"><?= labels('view_current_sale', "View Your Current Sales & Summary.") ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-12">
                        <div class="card card-statistic-1 " style="padding: 20px;">
                            <div class="content d-flex">
                                <div class="provider_a mr-2  bg-emerald-success text-light " style="box-shadow: 0px 8px 26px #47C36326;margin: 0;padding: 0;">
                                    <i class="fas fa-user text-emerald-success" style="    font-size: 24px;"></i>
                                </div>
                                <div class="card-body my-3 p-0" style="margin-left: 20px!important;">
                                    <span class="counter"><?= $total_customers ?></span>
                                    <h5 class="dashboard_small_label">
                                        <?= labels('total_customer', "Total Customer") ?></h5>
                                </div>
                            </div>
                            <a href=<?= base_url("admin/users") ?> class="text-dark">
                                <div class=" btn-lg  dashboard_extra_small_label" style="background-color:#f8f8fa">
                                    <?= labels('total_customer', "Total Customer") ?>
                                    <i class="fas fa-arrow-<?= ($is_rtl == 1) ? 'left' : 'right'; ?> mt-2" style="float: <?= ($is_rtl == 1) ? 'left' : 'right'; ?>;"></i>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-12">
                        <div class="card card-statistic-1 " style="padding: 20px;">
                            <div class="content d-flex">
                                <div class="provider_a mr-2   bg-emerald-blue text-light " style="box-shadow: 0px 8px 26px #3ABAF426;margin: 0;padding: 0;">
                                    <i class="fas fa-shopping-bag text-emerald-blue " style="    font-size: 24px;"> </i>
                                </div>
                                <div class="card-body my-3 p-0" style="margin-left: 20px!important;">
                                    <span class="counter"><?= $total_on_sale_service ?></span>
                                    <h5 class="dashboard_small_label">
                                        <?= labels('total_services', "Total Services") ?></h5>
                                </div>
                            </div>
                            <a href=<?= base_url("admin/services") ?> class="text-dark">
                                <div class=" btn-lg  dashboard_extra_small_label" style="background-color:#f8f8fa">
                                    <?= labels('total_services', "Total Services") ?></h5>
                                    <i class="fas fa-arrow-<?= ($is_rtl == 1) ? 'left' : 'right'; ?> mt-2" style="float: <?= ($is_rtl == 1) ? 'left' : 'right'; ?>;"></i>

                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-xl-6 col-lg-6 col-md-12">
                        <div class="card card-statistic-1 " style="padding: 20px;">
                            <div class="content d-flex">
                                <div class="provider_a mr-2   bg-emerald-warning text-warning " style="box-shadow: 0px 8px 26px #FFA42626;margin: 0;padding: 0;">
                                    <i class="fas fa-shopping-bag text-emerald-warning " style="    font-size: 24px;"> </i>
                                </div>
                                <div class="card-body my-3 p-0" style="margin-left: 20px!important;">
                                    <span class="counter"><?= $total_orders ?></span>
                                    <h5 class="dashboard_small_label">
                                        <?= labels('order_statistic', "Order Statistics") ?></h5>
                                </div>
                            </div>
                            <a href=<?= base_url("admin/orders") ?> class="text-dark">
                                <div class=" btn-lg  dashboard_extra_small_label" style="background-color:#f8f8fa">
                                    <?= labels('order_statistic', "Order Statistics") ?></h5>
                                    <i class="fas fa-arrow-<?= ($is_rtl == 1) ? 'left' : 'right'; ?> mt-2" style="float: <?= ($is_rtl == 1) ? 'left' : 'right'; ?>;"></i>

                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-6 col-xl-6 col-lg-12">
                <div class="card w-100">
                    <!-- Header section with proper responsive grid and vertical alignment -->
                    <div class="row pt-3 px-3 align-items-center" id="total">
                        <div class="col-12 col-md-6 position-relative d-flex align-items-center" style="min-height: 50px;">
                            <div class="toggleButttonPostition_dashboard" id="income_revenue" style="margin-top: 0; top: 50%; transform: translateY(-50%);"></div>
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center justify-content-md-end flex-wrap">
                            <div class='btn tag tag_custome mr-2 mb-2' id="income_revenue_filter_total" name="income_revenue_filter" value="income_revenue_filter"> <?= labels('total', "Total") ?></div>
                            <div class='btn tag tag_custome mr-2 mb-2' id="income_revenue_filter_admin" name="income_revenue_filter" value="income_revenue_filter"> <?= labels('admin', "Admin") ?></div>
                            <div class='btn tag tag_custome mr-2 mb-2' id="income_revenue_filter_provider" name="income_revenue_filter" value="income_revenue_filter"> <?= labels('provider', "Provider") ?></div>
                        </div>
                    </div>
                    <div class="row px-3 pb-2 align-items-center" id="year_pill_row">
                        <div class="col-12 d-flex align-items-center justify-content-start flex-wrap" id="year_pills"></div>
                    </div>
                    <div class="row g-3 px-3 pb-3 align-items-center">
                        <div class="col-12">
                            <div id="income_revenue_chart" style="background-color: rgb(248, 248, 250);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row ">
            <div class="col-lg-8 col-md-12 col-sm-12">
                <!-- <div class="col-md-12 p-0">
                    <?php if (empty($rating_wise_rating_data) && empty($rating_data)) { ?>
                        <div class="card px-4">
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-center">

                                    <div class="empty-state" data-height="400" style="height: 400px;">
                                        <div class="empty-state-icon bg-primary">
                                            <i class="fas fa-question text-white "></i>
                                        </div>
                                        <h2><?= labels('we_couldnt_find_any_providers', "We couldn't find any Providers") ?></h2>
                                        <p class="lead">
                                            <?= labels("sorry_we_cant_find_any_data", "Sorry we can't find any data, to get rid of this message, make at least 1 entry") ?>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="card px-4">
                            <div class="row  mt-3">
                                <div class="col mb-3 ">
                                    <div class="toggleButttonPostition_dashboard m-0"><?= labels('top_providers', 'Top  Providers') ?></div>
                                </div>
                                <div class="col-md-2 mb-3 d-flex justify-content-end">
                                    <select name="filter_top_providers" id="filter_top_providers" class="form-control dashboard_design select2" style="width: auto !important; min-width: fit-content;">
                                        <option value="orders"><?= labels('orders', 'Orders') ?></option>
                                        <option value="rating"><?= labels('rating', 'Ratings') ?></option>
                                    </select>
                                </div>
                            </div>

                            <?php
                            if (count($rating_data['data']) > 0 && count($rating_wise_rating_data['data']) > 0) { ?>

                                <div class="swiper mySwiper m-5 mt-1" id="order_wise_provider" style="padding:0!important ;margin:0!important">
                                    <div class="swiper-wrapper mt-3 ">
                                        <?php
                                        foreach ($rating_data['data'] as $row) {
                                        ?>
                                            <div class="swiper-slide">
                                                <a href="<?= base_url('/admin/partners/general_outlook/' . $row['partner_id'])  ?>" class=" my-5 provider_card" style="text-decoration: none;">

                                                    <?php
                                                    if (!empty($row['image'])) { ?>
                                                        <img src="<?= ((!empty(($row['image'])))) ? $row['image'] :  base_url('public/backend/assets/images/no-pictures.png') ?>" height="100px" width="100px" alt="">
                                                    <?php } else { ?>
                                                        <img src="<?= base_url('public/backend/assets/images/no-pictures.png') ?>" height="100px" width="100px" alt="">
                                                    <?php    }
                                                    ?>
                                                    <h6 class="m-3" style="word-break:break-word;"><?php
                                                                                                    $display_name = !empty($row['translated_company_name']) ? $row['translated_company_name'] : $row['company_name'];
                                                                                                    echo mb_strlen($display_name) > 15 ? mb_substr($display_name, 0, 15) . "..." : $display_name;
                                                                                                    ?></h6>
                                                    <div class="row">
                                                        <div class="partner-rating" id="<?= $row['id'] ?>" data-value="<?= $row['ratings'] ?>"></div>
                                                        <span class="small">
                                                            (<?= $row['ratings'] ?>)
                                                        </span>
                                                    </div>
                                                    <button type="button" class="btn bg-new-primary  left-icon-holder m-4" onclick="this.classList.add('active')" style="border-radius:8px;" style="word-break:break-word;">


                                                        <i class="fa fa-check-circle"></i>
                                                        &nbsp;&nbsp;&nbsp;
                                                        <?= $row['number_of_orders'] ?> <?= labels('order_completed', 'Order Completed') ?>
                                                    </button>
                                                </a>
                                            </div>
                                        <?php  }
                                        ?>
                                    </div>
                                </div>

                                <div class="swiper mySwiper m-5 mt-1" id="rating_wise_provider" style="padding:0!important ;margin:0!important">
                                    <div class="swiper-wrapper mt-3 ">
                                        <?php
                                        foreach ($rating_wise_rating_data['data'] as $row) { ?>
                                            <div class="swiper-slide">

                                                <a href="<?= base_url('/admin/partners/general_outlook/' . $row['partner_id'])  ?>" class=" my-5 provider_card" style="text-decoration: none;">
                                                    <?php
                                                    if (!empty($row['image'])) { ?>
                                                        <img src="<?= (!empty(($row['image']))) ? $row['image'] : base_url('public/backend/assets/images/no_image_avaialble.jpg')  ?>" height="100px" width="100px" alt="">
                                                    <?php } else { ?>
                                                        <img src="<?= base_url('public/backend/assets/images/no_image_avaialble.jpg') ?>" height="100px" width="100px" alt="">
                                                    <?php    }
                                                    ?>
                                                    <h6 class="m-3" style="word-break:break-word;"><?php
                                                                                                    $display_name = !empty($row['translated_company_name']) ? $row['translated_company_name'] : $row['company_name'];
                                                                                                    echo mb_strlen($display_name) > 15 ? mb_substr($display_name, 0, 15) . "..." : $display_name;
                                                                                                    ?></h6>
                                                    <div class="row">
                                                        <div class="partner-rating" id="order_<?= $row['id'] ?>" data-value="<?= $row['ratings'] ?>"></div>
                                                        <span class="small">
                                                            (<?= $row['ratings'] ?>)
                                                        </span>
                                                    </div>
                                                    <button type="button" class="btn bg-new-primary  left-icon-holder m-4" style="word-break:break-word;">
                                                        <i class="fa fa-check-circle"></i>
                                                        &nbsp;&nbsp;&nbsp;
                                                        <?= $row['number_of_orders'] ?> <?= labels('order_completed', 'Order Completed') ?>
                                                    </button>
                                                </a>
                                            </div>
                                        <?php  }
                                        ?>
                                    </div>
                                </div>


                            <?php } else { ?>

                                <div class="row">
                                    <div class="col-md-12 d-flex justify-content-center">

                                        <div class="empty-state" data-height="400" style="height: 400px;">
                                            <div class="empty-state-icon bg-primary">
                                                <i class="fas fa-question text-white "></i>
                                            </div>
                                            <h2><?= labels('we_couldnt_find_any_data', "We couldn't find any data") ?></h2>
                                            <p class="lead">
                                                <?= labels('sorry_we_cant_find_any_data', "Sorry we can't find any data, to get rid of this message, make at least 1 entry.") ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>


                            <?php    } ?>
                        </div>
                    <?php } ?>
                </div> -->
                <div class="col-md-12 p-0">

                    <?php if (empty($rating_wise_rating_data) && empty($rating_data)) { ?>

                        <div class="card px-4">
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-center">
                                    <div class="empty-state" data-height="400" style="height: 400px;">
                                        <div class="empty-state-icon bg-primary">
                                            <i class="fas fa-question text-white "></i>
                                        </div>
                                        <h2><?= labels('we_couldnt_find_any_providers', "We couldn't find any Providers") ?></h2>
                                        <p class="lead">
                                            <?= labels("sorry_we_cant_find_any_data", "Sorry we can't find any data, to get rid of this message, make at least 1 entry") ?>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php } else { ?>

                        <!-- ================= CLEANED + REFACTORED CARD ================= -->
                        <div class="card px-4">

                            <!-- Card Header -->
                            <div class="row card-header align-items-center g-0 mt-4">

                                <!-- Left -->
                                <div class="col-12 col-md-6 d-flex align-items-center mb-3 mb-md-0">
                                    <div class="toggleButttonPostition_dashboard m-0 w-100">
                                        <?= labels('top_providers', 'Top Providers') ?>
                                    </div>
                                </div>

                                <!-- Right -->
                                <div class="col-12 col-md-6 d-flex justify-content-start justify-content-md-end mt-0 mt-md-0">
                                    <div style="max-width:150px; width:100%;">
                                        <select name="filter_top_providers" id="filter_top_providers"
                                            class="form-control dashboard_design select2" style="width: auto !important; min-width: fit-content;">
                                            <option value="orders"><?= labels('orders', 'Orders') ?></option>
                                            <option value="rating"><?= labels('rating', 'Ratings') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>



                            <!-- Card Body -->
                            <div class="card-body px-0">

                                <?php if (count($rating_data['data']) > 0 && count($rating_wise_rating_data['data']) > 0) { ?>

                                    <!-- ORDER-WISE SWIPER -->
                                    <div class="swiper mySwiper mt-1" id="order_wise_provider" style="padding:0!important; margin:0!important;">
                                        <div class="swiper-wrapper mt-3">

                                            <?php foreach ($rating_data['data'] as $row) { ?>
                                                <div class="swiper-slide">
                                                    <a href="<?= base_url('/admin/partners/general_outlook/' . $row['partner_id']) ?>"
                                                        class="my-5 provider_card" style="text-decoration: none;">

                                                        <img src="<?= $row['image'] ?>" height="100px" width="100px" alt="">

                                                        <h6 class="m-3" style="word-break:break-word;">
                                                            <?php
                                                            // Display provider name with fallback logic:
                                                            // Priority: translated_company_name (current language → default language → base table) → company_name (base table)
                                                            // The applyTranslations method in Partners_model already handles the fallback chain,
                                                            // so translated_company_name should always have a value, but we keep base table as final fallback
                                                            $display_name = !empty(trim($row['translated_company_name'] ?? ''))
                                                                ? trim($row['translated_company_name'])
                                                                : (!empty(trim($row['company_name'] ?? '')) ? trim($row['company_name']) : '');
                                                            echo mb_strlen($display_name) > 15 ? mb_substr($display_name, 0, 15) . "..." : $display_name;
                                                            ?>
                                                        </h6>

                                                        <div class="row">
                                                            <div class="partner-rating" id="<?= $row['id'] ?>"
                                                                data-value="<?= $row['ratings'] ?>"></div>
                                                            <span class="small">(<?= $row['ratings'] ?>)</span>
                                                        </div>

                                                        <button type="button"
                                                            class="btn bg-new-primary left-icon-holder m-4"
                                                            onclick="this.classList.add('active')" style="border-radius:8px;">
                                                            <i class="fa fa-check-circle"></i>
                                                            &nbsp;&nbsp;&nbsp;
                                                            <?= $row['number_of_orders'] ?> <?= labels('order_completed', 'Order Completed') ?>
                                                        </button>

                                                    </a>
                                                </div>
                                            <?php } ?>

                                        </div>
                                    </div>

                                    <!-- RATING-WISE SWIPER -->
                                    <div class="swiper mySwiper mt-4" id="rating_wise_provider" style="padding:0!important; margin:0!important;">
                                        <div class="swiper-wrapper mt-3">

                                            <?php foreach ($rating_wise_rating_data['data'] as $row) { ?>
                                                <div class="swiper-slide">
                                                    <a href="<?= base_url('/admin/partners/general_outlook/' . $row['partner_id']) ?>"
                                                        class="my-5 provider_card" style="text-decoration: none;">

                                                        <img src="<?= $row['image'] ?>" height="100px" width="100px" alt="">

                                                        <h6 class="m-3" style="word-break:break-word;">
                                                            <?php
                                                            // Display provider name with fallback logic:
                                                            // Priority: translated_company_name (current language → default language → base table) → company_name (base table)
                                                            // The applyTranslations method in Partners_model already handles the fallback chain,
                                                            // so translated_company_name should always have a value, but we keep base table as final fallback
                                                            $display_name = !empty(trim($row['translated_company_name'] ?? ''))
                                                                ? trim($row['translated_company_name'])
                                                                : (!empty(trim($row['company_name'] ?? '')) ? trim($row['company_name']) : '');
                                                            echo mb_strlen($display_name) > 15 ? mb_substr($display_name, 0, 15) . "..." : $display_name;
                                                            ?>
                                                        </h6>

                                                        <div class="row">
                                                            <div class="partner-rating" id="order_<?= $row['id'] ?>"
                                                                data-value="<?= $row['ratings'] ?>"></div>
                                                            <span class="small">(<?= $row['ratings'] ?>)</span>
                                                        </div>

                                                        <button type="button" class="btn bg-new-primary left-icon-holder m-4">
                                                            <i class="fa fa-check-circle"></i>
                                                            &nbsp;&nbsp;&nbsp;
                                                            <?= $row['number_of_orders'] ?> <?= labels('order_completed', 'Order Completed') ?>
                                                        </button>

                                                    </a>
                                                </div>
                                            <?php } ?>

                                        </div>
                                    </div>

                                <?php } else { ?>

                                    <div class="row">
                                        <div class="col-md-12 d-flex justify-content-center">
                                            <div class="empty-state" data-height="400" style="height: 400px;">
                                                <div class="empty-state-icon bg-primary">
                                                    <i class="fas fa-question text-white"></i>
                                                </div>
                                                <h2><?= labels('we_couldnt_find_any_data', "We couldn't find any data") ?></h2>
                                                <p class="lead">
                                                    <?= labels('sorry_we_cant_find_any_data', "Sorry we can't find any data, to get rid of this message, make at least 1 entry.") ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                <?php } ?>

                            </div>
                            <!-- ================= END REFACTORED CARD ================= -->

                        </div>
                    <?php } ?>

                </div>

                <div class=" col-md-12 p-0">
                    <div class="card p-3">
                        <div class="row">
                            <div class="col mb-3">
                                <div class="toggleButttonPostition m-0"> <span class="text-dark"><?= labels('recent_booking', 'Recent Booking') ?> :
                                        <span class="" id="recent_booking"></span></span>
                                </div>
                            </div>
                        </div>
                        <table class="table " id="user_list" width="100%" data-detail-formatter="user_formater" data-trim-on-search="false" data-click-to-select="true" data-toggle="table" data-url="<?= base_url("admin/dashboard/recent_booking") ?>" data-side-pagination="server" d data-sort-name="id" data-sort-order="DESC" data-query-params="orders_query" data-mobile-responsive="true" data-responsive="true">
                            <thead>
                                <tr>
                                    <th data-field="partner" class="text-center"><?= labels('provider', 'Provider') ?></th>
                                    <th data-field="customer" class="text-center"><?= labels('customer', 'Customer') ?></th>
                                    <th data-field="new_start_time_with_date" class="text-center"><?= labels('starting_time', 'Starting time') ?></th>
                                    <th data-field="new_end_time_with_date" class="text-center"><?= labels('ending_time', 'Ending time') ?></th>
                                    <th data-field="status" class="text-center"><?= labels('status', 'Status') ?></th>
                                    <th data-field="operations" class="text-center"><?= labels('operation', 'Operation') ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>



            <div class="col-lg-4 col-md-12 col-sm-12 mb-30">
                <div class="card w-100 h-100">

                    <!-- Header -->
                    <div class="row card-header align-items-center g-0 mt-4">

                        <!-- Left -->
                        <div class="col-12 col-md-6 d-flex align-items-center mb-3 mb-md-0">
                            <div class="toggleButttonPostition_dashboard m-0 w-100">
                                <?= labels('top_trending_services', "Top Trending Services") ?>
                            </div>
                        </div>

                        <!-- Right -->
                        <div class="col-12 col-md-6 d-flex justify-content-start justify-content-md-end mt-0 mt-md-0">
                            <select name="filter_trending_services" id="filter_trending_services"
                                class="form-control dashboard_design select2 w-100 w-md-auto">
                                <option value=""><?= labels('all', 'All') ?></option>
                                <?php
                                // Categories are already translated based on currently selected language
                                // The get_categories_with_translated_names() function uses get_current_language()
                                // which gets the language from session, ensuring categories display in the correct language
                                // Fallback chain: current language translation → default language translation → base table name
                                foreach ($categories as $row) { ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                    </div>

                    <!-- Body -->
                    <div class="card-body">
                        <?php if (empty($top_trending_services)) { ?>
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-center">
                                    <div class="empty-state" data-height="400" style="height: 400px;">
                                        <div class="empty-state-icon bg-primary">
                                            <i class="fas fa-question text-white"></i>
                                        </div>
                                        <h2><?= labels('we_couldnt_find_any_data', "We couldn't find any data") ?></h2>
                                        <p class="lead">
                                            <?= labels('sorry_we_cant_find_any_data', "Sorry we can't find any data, to get rid of this message, make at least 1 entry.") ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <ul class="common-list m-0" id="trending_service">
                                <?php foreach ($top_trending_services as $service) { ?>
                                    <li class="d-flex flex-wrap gap-2 align-items-center justify-content-between" style="cursor: pointer;">
                                        <div class="media align-items-center gap-3">
                                            <div class="avatar avatar-lg" style="border-radius: 8px !important;">
                                                <img src="<?= $service['image'] ?>" height="100" width="100" alt="" class="avatar-img rounded">
                                            </div>

                                            <div class="media-body mt-3 ml-3">
                                                <b><?php
                                                    // Display service title with fallback logic:
                                                    // The fetch_top_trending_services() function already handles translation with fallback:
                                                    // Priority: current language translation → default language translation → base table title
                                                    // So $service['title'] should already be the translated version with proper fallback
                                                    echo !empty($service['title']) ? htmlspecialchars($service['title']) : '';
                                                    ?></b>
                                                <p>
                                                    <?php
                                                    if ($service['discounted_price'] != 0) {
                                                        echo $currency . $service['discounted_price'];
                                                        echo '<s class="ml-2 mr-2">' . $currency . $service['price'] . '</s>';
                                                    } else {
                                                        echo '<span class="ml-2 mr-2 mt-0">' . $currency . $service['price'] . '</span>';
                                                    }
                                                    ?>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="tag border-0 rounded-md bg-new-primary">
                                            <i class="fa fa-check-circle mr-2"></i>
                                            <?= $service['order_data'] ?>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>
</div>
</section>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    var swiper = new Swiper(".mySwiper", {
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        slidesPerView: 3,
        spaceBetween: 20,
        breakpoints: {
            320: {
                slidesPerView: 1,
                spaceBetween: 30,
            },
            640: {
                slidesPerView: 1,
                spaceBetween: 30,
            },
            768: {
                slidesPerView: 3,
                spaceBetween: 30,
            },
            769: {
                slidesPerView: 2,
                spaceBetween: 30,
            },
            1020: {
                slidesPerView: 2.1,
                spaceBetween: 30,
            },
            1200: {
                slidesPerView: 2,
                spaceBetween: 30,
            },
            1564: {
                slidesPerView: 3,
                spaceBetween: 40,
            },
        }
    });
    // Income revenue charts: type pills (Total/Admin/Provider) + year pills
    // Single ApexCharts instance reused; updateSeries() + updateOptions() on pill change.
    var incomeRevenueData = {
        total:    <?= json_encode($income_revenue) ?>,
        admin:    <?= json_encode($admin_income_revenue) ?>,
        provider: <?= json_encode($provider_income_revenue) ?>,
    };
    var incomeRevenueTitles = {
        total:    "<?= labels('income_revenue', 'Income Revenue') ?>",
        admin:    "<?= labels('admin_income_revenue', 'Admin Income Revenue') ?>",
        provider: "<?= labels('provider_income_revenue', 'Provider Income Revenue') ?>",
    };
    var currentIncomeType = 'total';
    var currentYear = null;

    function pickInitialYear(type) {
        var yrs = incomeRevenueData[type].available_years || [];
        return yrs.length ? yrs[yrs.length - 1] : null;
    }
    function seriesForYear(type, year) {
        var bucket = (incomeRevenueData[type].years || {})[year];
        return bucket ? bucket.income_revenue : [];
    }
    function labelsForYear(type, year) {
        var bucket = (incomeRevenueData[type].years || {})[year];
        return bucket ? bucket.month_name : [];
    }

    currentYear = pickInitialYear(currentIncomeType);

    var options = {
        series: [{
            name: incomeRevenueTitles[currentIncomeType],
            data: seriesForYear(currentIncomeType, currentYear),
        }, ],
        dropShadow: {
            enabled: true,
            color: "#000"
        },
        fill: {
            type: "gradient",
            gradient: {
                shadeIntensity: 5,
                type: "vertical",
                colorStops: [{
                        offset: 20,
                        color: "#a0def6",
                        opacity: 1
                    },
                    {
                        offset: 100,
                        color: "#e4f2f9",
                        opacity: 1
                    },
                ]
            }
        },
        chart: {
            height: 250,

            type: "area",
            zoom: {
                enabled: false
            },
            toolbar: {
                show: true,
                tools: {
                    download: false
                }
            },
        },
        noData: {
            text: "<?= labels('no_data', 'No data') ?>",
            align: "center",
            verticalAlign: "middle",
        },
        responsive: [{
            breakpoint: 768,
            options: {
                chart: {
                    height: 250,
                },
                xaxis: {
                    labels: {
                        rotate: -45,
                        rotateAlways: true,
                        style: {
                            fontSize: '10px',
                        },
                        maxHeight: 60,
                    }
                }
            }
        }, {
            breakpoint: 400,
            options: {
                chart: {
                    width: '100%',
                    height: 250,
                },
                xaxis: {
                    labels: {
                        rotate: -45,
                        rotateAlways: true,
                        style: {
                            fontSize: '9px',
                        },
                        maxHeight: 80,
                    }
                },
                legend: {
                    position: 'left',
                    horizontalAlign: 'right',
                }
            }
        }],
        colors: ["#a0def6"],
        tooltip: {
            x: {

            },
            y: {
                formatter: function(val) {
                    return val.toFixed(2);
                },
                title: {
                    formatter: () => incomeRevenueTitles[currentIncomeType]
                }
            }
        },
        xaxis: {
            categories: labelsForYear(currentIncomeType, currentYear),
            tickPlacement: 'on',
            labels: {
                style: {
                    fontSize: '12px',
                }
            }
        },
        markers: {
            size: 1,
            strokeColors: ['#a0def6', '#a0def6'],
            hover: {
                size: 1,
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: "straight",
            colors: ['#a0def6'],
            width: 1,
        },
        grid: {
            borderColor: " rgba(111,111,111,0.2) transparent",
        },
    };
    var incomeRevenueChart = new ApexCharts(document.querySelector("#income_revenue_chart"), options);
    incomeRevenueChart.render();

    function renderYearPills() {
        var yrs = incomeRevenueData[currentIncomeType].available_years || [];
        var $container = $('#year_pills').empty();
        if (yrs.length <= 1) {
            $('#year_pill_row').hide();
            return;
        }
        $('#year_pill_row').show();
        yrs.forEach(function(yr) {
            var $pill = $('<div class="btn tag tag_custome mr-2 mb-2 year_pill"></div>')
                .attr('data-year', yr).text(yr);
            if (yr == currentYear) $pill.addClass('tag_active');
            $container.append($pill);
        });
    }

    function refreshIncomeRevenueChart() {
        var data = seriesForYear(currentIncomeType, currentYear);
        var lbls = labelsForYear(currentIncomeType, currentYear);
        incomeRevenueChart.updateOptions({
            xaxis: { categories: lbls, tickPlacement: 'on' },
            series: [{ name: incomeRevenueTitles[currentIncomeType], data: data }],
        }, false, true);
    }

    $(document).on('click', '.year_pill', function() {
        currentYear = $(this).data('year');
        $('.year_pill').removeClass('tag_active');
        $(this).addClass('tag_active');
        refreshIncomeRevenueChart();
    });

    renderYearPills();
    const greeting = document.getElementById('greeting');
    const hour = new Date().getHours();
    const welcomeTypes = ['<?= labels('good_morning', 'Good morning') ?>', '<?= labels('good_afternoon', 'Good afternoon') ?>', '<?= labels('good_evening', 'Good evening') ?>'];
    let welcomeText = '';
    if (hour < 12) welcomeText = welcomeTypes[0];
    else if (hour < 18) welcomeText = welcomeTypes[1];
    else welcomeText = welcomeTypes[2];
    greeting.innerHTML = welcomeText;
    $('#user_list').on('load-success.bs.table', function(data) {
        // ...
        var numRecords = $('#user_list').bootstrapTable('getData').length;
        $("#recent_booking").text(numRecords);

        // Force LTR direction for date columns in RTL mode
        // Dates should always display left-to-right regardless of page direction
        var $table = $('#user_list');
        // Find date column headers by data-field attribute
        $table.find('th[data-field*="date"], th[data-field*="time"], th[data-field*="Date"], th[data-field*="Time"]').each(function() {
            var fieldName = $(this).attr('data-field');
            var columnIndex = $(this).index();
            // Apply LTR to all cells in this column
            $table.find('tbody tr').each(function() {
                var $cell = $(this).find('td').eq(columnIndex);
                $cell.css({
                    'direction': 'ltr',
                    'text-align': 'center'
                });
            });
        });
    })
    $(function() {
        $('#order_wise_provider').show();
        $('#rating_wise_provider').hide();

        // Ensure select2 dropdown width fits content
        // This prevents select2 from making the dropdown too wide
        if ($('#filter_top_providers').hasClass('select2-hidden-accessible')) {
            $('#filter_top_providers').next('.select2-container').css('width', 'auto');
        } else {
            // If select2 hasn't initialized yet, wait for it and then set width
            setTimeout(function() {
                $('#filter_top_providers').next('.select2-container').css('width', 'auto');
            }, 100);
        }

        $('#filter_top_providers').change(function() {
            if ($('#filter_top_providers').val() == 'orders') {
                $('#order_wise_provider').show();
                $('#rating_wise_provider').hide();
            } else {
                $('#rating_wise_provider').show();
                $('#order_wise_provider').hide();
            }
        });
    });
    $(function() {
        $('#income_revenue_filter_total').addClass("tag_active");
        const incomeRevenueHeading = document.getElementById('income_revenue');
        incomeRevenueHeading.innerHTML = incomeRevenueTitles[currentIncomeType];

        function switchIncomeType(type, activeBtnId) {
            currentIncomeType = type;
            currentYear = pickInitialYear(type);
            $('#income_revenue_filter_total, #income_revenue_filter_admin, #income_revenue_filter_provider').removeClass("tag_active");
            $('#' + activeBtnId).addClass("tag_active");
            incomeRevenueHeading.innerHTML = incomeRevenueTitles[type];
            renderYearPills();
            refreshIncomeRevenueChart();
        }

        $("#income_revenue_filter_total").click(function()    { switchIncomeType('total',    'income_revenue_filter_total'); });
        $("#income_revenue_filter_admin").click(function()    { switchIncomeType('admin',    'income_revenue_filter_admin'); });
        $("#income_revenue_filter_provider").click(function() { switchIncomeType('provider', 'income_revenue_filter_provider'); });
    });
    // Initialize Select2 for filter_trending_services with translated "No Results Found" message
    // Use setTimeout to ensure global Select2 initialization happens first
    // Check if select2 library is available before using it
    setTimeout(function() {
        // Check if select2 library is loaded and available
        if (typeof jQuery !== 'undefined' && jQuery().select2) {
            // Check if the dropdown exists
            if ($('#filter_trending_services').length > 0) {
                // Destroy existing Select2 instance if it was auto-initialized
                if ($('#filter_trending_services').hasClass('select2-hidden-accessible')) {
                    $('#filter_trending_services').select2('destroy');
                }
                // Re-initialize with language configuration for translated "No Results Found" message
                $('#filter_trending_services').select2({
                    language: {
                        noResults: function() {
                            return "<?= labels('no_results_found', 'No Results Found') ?>";
                        }
                    }
                });
            }
        } else {
            // If select2 is not loaded yet, retry after a longer delay
            // This handles cases where scripts load slowly
            setTimeout(function() {
                if (typeof jQuery !== 'undefined' && jQuery().select2 && $('#filter_trending_services').length > 0) {
                    if ($('#filter_trending_services').hasClass('select2-hidden-accessible')) {
                        $('#filter_trending_services').select2('destroy');
                    }
                    $('#filter_trending_services').select2({
                        language: {
                            noResults: function() {
                                return "<?= labels('no_results_found', 'No Results Found') ?>";
                            }
                        }
                    });
                }
            }, 500);
        }
    }, 200);
    $('#filter_trending_services').change(function() {
        var trending_filter = $('#filter_trending_services').val();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $.ajax({
            url: baseUrl + '/admin/dashboard/top_trending_services',
            type: 'POST',
            data: {
                data_trending_filter: trending_filter,
            },
            dataType: 'JSON',
            success: function(response) {

                let html = "";
                $.each(response.data, function(index, item) {
                    html += ' <li class="d-flex flex-wrap gap-2 align-items-center justify-content-between" style="cursor: pointer">' +
                        '<div class="media align-items-center gap-3">' +
                        ' <div class="avatar avatar-lg">' +
                        '<img class="avatar-img rounded" src="' + item.image + '" alt="">' +
                        '</div>' +
                        '<div class="media-body mt-3 ml-3">' +
                        '<b>' + item.title + '</b>' +
                        '<p>' + item.discounted_price + '<s class="ml-2">' + item.price + '</s></p>' +
                        ' </div>' +
                        ' </div>' +
                        '<div class="tag border-0 rounded-md bg-new-primary"><i class="fa fa-check-circle mr-2"></i>' + item.order_data +
                        '</div>' +
                        '</li>';
                });
                $('#trending_service').html(html);
            },
            error: function(err) {

            },
        });
    });
</script>
