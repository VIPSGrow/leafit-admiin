<div class="container-fluid card">
    <div class="row ">
        <div class="col mb-12" style="border-bottom: solid 1px #e5e6e9;">
            <div class="toggleButttonPostition"><?= $add_title ?></div>
        </div>
    </div>
    <div class="card-body">
        <?= form_open($save_url, ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_' . $type . '_reason', 'enctype' => "multipart/form-data", 'data-table' => 'user_list']); ?>
        <input type="hidden" name="type" value="<?= $type ?>">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <?php
                    $sorted_languages = [];
                    $default_language_code = null;

                    foreach ($languages as $language) {
                        if ($language['is_default'] == 1) {
                            $sorted_languages[] = $language;
                            $default_language_code = $language['code'];
                            break;
                        }
                    }
                    foreach ($languages as $language) {
                        if ($language['is_default'] != 1) {
                            $sorted_languages[] = $language;
                        }
                    }

                    foreach ($sorted_languages as $index => $language) {
                    ?>
                        <div class="language-option-<?= $type ?> position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                            id="language-<?= $type ?>-<?= $language['code'] ?>"
                            data-language="<?= $language['code'] ?>"
                            style="cursor: pointer; padding: 0.5rem 0;">
                            <span class="language-text-<?= $type ?> px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                            </span>
                            <div class="language-underline-<?= $type ?>"
                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="row">
            <?php
            foreach ($sorted_languages as $index => $language) {
            ?>
                <div class="col-md-12 translationDiv-<?= $type ?>" id="translationDiv-<?= $type ?>-<?= $language['code'] ?>" <?= $language['code'] == $default_language_code ? 'style="display: block;"' : 'style="display: none;"' ?>>
                    <div class="form-group">
                        <label for="reason-<?= $type ?>-<?= $language['code'] ?>" class="<?= $language['code'] == $default_language_code ? 'required' : '' ?>"><?= labels('reason', "Reason") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                        <input id="reason-<?= $type ?>-<?= $language['code'] ?>" class="form-control" type="text" name="reason[<?= $language['code'] ?>]" placeholder="<?= labels('enter_the_reason_here', "Enter the reason here") ?>">
                    </div>
                </div>
            <?php } ?>
        </div>
        <div class="row">
            <div class="col-md-12 form-group">
                <label class=" mt-2">
                    <input type="checkbox" id="needs_additional_info-<?= $type ?>" name="needs_additional_info" class="custom-switch-input">
                    <span class="custom-switch-indicator"></span>
                    <span class="custom-switch-description"><?= labels('needs_additional_info', 'Needs Additional Info') ?></span>
                </label>
            </div>
        </div>
        <div class="row">
            <div class="col-md d-flex justify-content-end">
                <button type="submit" class="btn btn-primary submit_btn"><?= labels('add', "Add") ?></button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>
