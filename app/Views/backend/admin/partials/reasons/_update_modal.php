<div class="modal fade" id="update_modal-<?= $type ?>" tabindex="-1" aria-labelledby="update_modal_thing-<?= $type ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= labels('edit_reason', 'Edit Reason') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?= form_open($update_url, ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_' . $type . '_reason_form', 'enctype' => "multipart/form-data", 'data-table' => 'user_list', 'data-modal' => '#update_modal-' . $type]); ?>
                <input type="hidden" name="id" id="id-<?= $type ?>">
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
                                <div class="language-modal-option-<?= $type ?> position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                    id="language-modal-<?= $type ?>-<?= $language['code'] ?>"
                                    data-language="<?= $language['code'] ?>"
                                    style="cursor: pointer; padding: 0.5rem 0;">
                                    <span class="language-modal-text-<?= $type ?> px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                        style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                        <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                    </span>
                                    <div class="language-modal-underline-<?= $type ?>"
                                        style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php
                foreach ($sorted_languages as $index => $language) {
                ?>
                    <div class="form-group translationModalDiv-<?= $type ?>" id="translationModalDiv-<?= $type ?>-<?= $language['code'] ?>" <?= $language['code'] == $default_language_code ? 'style="display: block;"' : 'style="display: none;"' ?>>
                        <label for="edit_reason-<?= $type ?>-<?= $language['code'] ?>" class="<?= $language['code'] == $default_language_code ? 'required' : '' ?>"><?= labels('reason', "Reason") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                        <input id="edit_reason-<?= $type ?>-<?= $language['code'] ?>" class="form-control" type="text" name="reason[<?= $language['code'] ?>]" placeholder="<?= labels('enter_the_reason_here', "Enter the reason here") ?>">
                    </div>
                <?php } ?>
                <div class="form-group">
                    <div class="col-md-12 ">
                        <label class=" mt-2">
                            <input type="checkbox" id="edit_needs_additional_info-<?= $type ?>" name="needs_additional_info" class="custom-switch-input">
                            <span class="custom-switch-indicator"></span>
                            <span class="custom-switch-description"><?= labels('needs_additional_info', 'Needs Additional Info') ?></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" name="submit"><?= labels('save_changes', "Save changes") ?></button>
                <?= form_close() ?>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
            </div>
        </div>
    </div>
</div>
