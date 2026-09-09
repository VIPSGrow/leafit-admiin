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

// Helper to convert shorthand php.ini sizes (e.g. 100M, 1G) to bytes.
if (!function_exists('edemand_bytes_from_ini_value')) {
  function edemand_bytes_from_ini_value($val)
  {
    $val = trim($val);
    if ($val === '') {
      return 0;
    }

    $last = strtolower(substr($val, -1));
    $num  = (float) $val;

    switch ($last) {
      case 'g':
        $num *= 1024;
        // no break
      case 'm':
        $num *= 1024;
        // no break
      case 'k':
        $num *= 1024;
        break;
    }

    return (int) $num;
  }
}

$uploadMaxRaw = ini_get("upload_max_filesize");
$postMaxRaw   = ini_get("post_max_size");
$uploadBytes  = edemand_bytes_from_ini_value($uploadMaxRaw);
$postBytes    = edemand_bytes_from_ini_value($postMaxRaw);
$recommendedBytes = 100 * 1024 * 1024; // 100M

$limits_ok = $uploadBytes >= $recommendedBytes && $postBytes >= $recommendedBytes;
?>
<div class="main-content">
  <section class="section">
    <div class="section-header mt-2">
      <h1><?= labels('system_updater', 'System Updater') ?></h1> &ensp; &ensp;
      <span class="badge badge-primary">
        <?php foreach ($version as $ver) : ?>
          <?= $ver->version ?>
        <?php endforeach; ?>
      </span>
      <div class="section-header-breadcrumb">
        <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
        <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
        <div class="breadcrumb-item"><?= labels('system_updater', 'System Updater') ?></div>
      </div>
    </div>

  </section>
  <section class="content">
    <div class="container-fluid">

      <?php if (!$limits_ok) : ?>
        <div class="alert alert-danger col-md-8">
          <div class="alert-title"><?= labels('warning', 'Warning') ?>:</div>
          <p class="mb-1">
            <?= labels(
              'system_update_size_warning',
              'Your server PHP upload_max_filesize and/or post_max_size are lower than the recommended 100M. Please update these values in php.ini before running the system updater. You can find detailed instructions in the official eDemand documentation.'
            ) ?>
            <a href="https://wrteam-in.github.io/eDemand-Doc/docs/admin-setup/admin-intro/" target="_blank" rel="noopener noreferrer">
              <?= labels('system_update_size_warning_link', 'View documentation for adjusting PHP limits') ?>
            </a>
          </p>
          <ul class="pl-3 d-flex flex-column gap-2 instructions-list mt-2">
            <li class="list-unstyled">
              <?= labels('current_upload_max_filesize', 'Current upload_max_filesize') ?>:
              <strong><?= esc($uploadMaxRaw) ?></strong>
            </li>
            <li class="list-unstyled">
              <?= labels('current_post_max_size', 'Current post_max_size') ?>:
              <strong><?= esc($postMaxRaw) ?></strong>
            </li>
            <li class="list-unstyled">
              <?= labels('recommended_size', 'Recommended value for both settings') ?>:
              <strong>100M</strong>
            </li>
          </ul>
        </div>
      <?php endif; ?>



      <div class="row">
        <div class="col-md-12">
          <div class="card card-info">

            <div class="col-md">

              <form class="form-horizontal form-submit-event" action="<?= base_url('admin/upload_update_file') ?>" method="POST" enctype="multipart/form-data">

                <div class="card-body">

                  <div class="form-group" style="padding-left: 0;">
                    <label for="purchase_code"><?= labels('purchase_code', 'Purchase Code') ?></label>
                    <input class="form-control" type="text" name="purchase_code" id="purchase_code" <?= $limits_ok ? '' : 'disabled' ?>>
                  </div>


                  <?php if ($limits_ok) : ?>
                    <div class="dropzone dz-clickable" id="system-update-dropzone">
                    </div>
                  <?php else : ?>
                    <div class="alert alert-warning mt-3 mb-0">
                      <?= labels(
                        'system_update_dropzone_disabled',
                        'File upload is disabled because your PHP upload_max_filesize or post_max_size is below 100M. Please increase these limits and reload this page to enable the updater.'
                      ) ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($permissions['update']['system_update'] == 1) : ?>

                    <div class="form-group justify-content-end d-flex" style="margin-top: 25px;margin-bottom: 25px">
                      <button class="btn btn-primary" id="system_update_btn" <?= $limits_ok ? '' : 'disabled' ?>><?= labels('update_the_system', 'Update The System') ?></button>
                    </div>
                  <?php endif; ?>

                </div>

                <div class="d-flex justify-content-center">
                  <div class="form-group" id="error_box">
                  </div>
                </div>
            </div>
            </form>

            <!-- Run Migrations Manually -->
            <hr>
            <div class="card-body pt-0">
              <h6 class="mb-2"><?= labels('run_migrations', 'Run Migrations') ?></h6>
              <div class="alert alert-danger mb-3">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong><?= labels('warning', 'Warning') ?>:</strong>
                <?= labels('run_migrations_warning', 'Running migrations will alter your database schema. Only use this if migrations failed during an update or if instructed by support.') ?>
              </div>
              <p class="text-muted mb-2"><?= labels('run_migrations_instruction', 'Copy and paste the following URL in your browser to run pending migrations:') ?></p>
              <div class="input-group">
                <input type="text" class="form-control" id="migrate-url" value="<?= base_url('/migrate') ?>" readonly>
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" onclick="copyMigrateUrl()">
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Demo Data Seeder -->
            <hr>
            <div class="card-body pt-0">
              <h6 class="mb-2"><?= labels('demo_data', 'Demo Data') ?></h6>
              <p class="text-muted mb-3">
                <?= labels('demo_seeder_description', 'Populate your database with sample categories, providers, services, and more for testing and demonstration.') ?>
              </p>
              <?php if ($permissions['update']['system_update'] == 1) : ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#seedModeModal">
                  <i class="fas fa-database mr-1"></i> <?= labels('seed_demo_data', 'Seed Demo Data') ?>
                </button>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Seed Mode Modal -->
<div class="modal fade" id="seedModeModal" tabindex="-1" role="dialog" aria-labelledby="seedModeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="seedModeModalLabel"><?= labels('seed_demo_data', 'Seed Demo Data') ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="text-muted"><?= labels('seed_mode_prompt', 'How would you like to seed the demo data?') ?></p>
        <div class="alert alert-info mb-0">
          <i class="fas fa-info-circle mr-1"></i> <?= labels('seeder_warning', 'It is recommended to run this on a fresh installation or take a backup before proceeding.') ?>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('cancel', 'Cancel') ?></button>
        <div>
          <button type="button" class="btn btn-outline-primary mr-1 btn-seed" data-mode="additive">
            <i class="fas fa-plus-circle mr-1"></i> <?= labels('keep_existing_data', 'Keep Existing Data') ?>
          </button>
          <button type="button" class="btn btn-danger btn-seed" data-mode="clean">
            <i class="fas fa-sync-alt mr-1"></i> <?= labels('clean_seed', 'Clean Seed') ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var purchase_code = document.getElementById('purchase_code');

  function copyMigrateUrl() {
    var input = document.getElementById('migrate-url');
    input.select();
    document.execCommand('copy');
    showToastMessage('<?= labels('copied', 'Copied!') ?>', 'success');
  }

  $(document).on('click', '.btn-seed', function () {
    var mode = $(this).data('mode');
    var $btn = $(this);
    var originalHtml = $btn.html();
    var seedingLabel = '<?= labels('seeding', 'Seeding') ?>...';

    $('.btn-seed').prop('disabled', true);
    $btn.html('<span class="spinner-border spinner-border-sm mr-1" role="status"></span> ' + seedingLabel);

    $.ajax({
      type: 'POST',
      url: siteUrl + '/admin/seed-demo-data',
      data: { mode: mode, [csrfName]: csrfHash },
      dataType: 'json',
      success: function (response) {
        csrfName = response.csrfName;
        csrfHash = response.csrfHash;
        if (response.error === false) {
          showToastMessage(response.message, 'success');
        } else {
          showToastMessage(response.message, 'error');
        }
      },
      error: function () {
        showToastMessage('<?= labels('SOMETHING_WENT_WRONG', 'Something went wrong.') ?>', 'error');
      },
      complete: function () {
        $btn.html(originalHtml);
        $('.btn-seed').prop('disabled', false);
        $('#seedModeModal').modal('hide');
      }
    });
  });
</script>