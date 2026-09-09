<?php
/**
 * Chat Questions — reusable tab panel for one question type.
 *
 * Data keys passed from the parent view:
 * - tab_id             string  DOM id prefix (e.g. pre-booking)
 * - tab_class          string  Tab pane CSS classes (include "show active" on first pane)
 * - question_type      string  API type: pre_booking | post_booking | customer_admin_support | provider_admin_support
 * - icon               string  FontAwesome icon name (without fa- prefix)
 * - title              string  Panel heading
 * - description        string  Panel description text
 * - sorted_languages   array   Languages sorted with default first
 * - default_lang_code  string  Default language code
 */
$tab_id            = $tab_id ?? '';
$tab_class         = $tab_class ?? 'tab-pane fade';
$question_type     = $question_type ?? '';
$icon              = $icon ?? 'comments';
$title             = $title ?? '';
$description       = $description ?? '';
$sorted_languages  = $sorted_languages ?? [];
$default_lang_code = $default_lang_code ?? 'en';
?>
<div class="<?= esc($tab_class, 'attr') ?>" id="tab-<?= esc($tab_id, 'attr') ?>" role="tabpanel" aria-labelledby="<?= esc($tab_id, 'attr') ?>-tab">
    <div class="card">
        <div class="card-body">
            <div class="row m-0">
                <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                    <div class="toggleButttonPostition"><i class="fas fa-<?= esc($icon, 'attr') ?> mr-2"></i><?= $title ?></div>
                </div>
            </div>
            <p class="text-muted mb-3"><?= $description ?></p>

            <!-- Language Tabs -->
            <div class="d-flex flex-wrap align-items-center mb-3" data-question-section="<?= esc($tab_id, 'attr') ?>">
                <?php foreach ($sorted_languages as $language) { ?>
                    <div class="lang-tab position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                        data-lang="<?= $language['code'] ?>"
                        data-section="<?= esc($tab_id, 'attr') ?>"
                        style="cursor: pointer; padding: 0.5rem 0; margin-right: 1rem;">
                        <span class="lang-tab-text px-2 <?= $language['is_default'] ? 'text-primary font-weight-bold' : 'text-muted' ?>"
                            style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                            <?= $language['language'] ?><?= $language['is_default'] ? ' (' . labels('default', 'Default') . ')' : '' ?>
                        </span>
                        <div class="lang-tab-underline"
                            style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: var(--primary-color, #6777ef); transition: width 0.3s ease; border-radius: 1px;"></div>
                    </div>
                <?php } ?>
            </div>

            <!-- Per-Language Inputs -->
            <?php foreach ($sorted_languages as $language) { ?>
                <div class="lang-input-group" data-section="<?= esc($tab_id, 'attr') ?>" data-lang="<?= $language['code'] ?>" style="<?= $language['code'] !== $default_lang_code ? 'display:none;' : '' ?>">
                    <div class="input-group mb-1">
                        <input type="text" class="form-control question-input"
                            id="<?= esc($tab_id, 'attr') ?>_input_<?= $language['code'] ?>"
                            placeholder="<?= labels('type_new_question', 'Type a new question here...') ?> (<?= $language['language'] ?>)"
                            data-section="<?= esc($tab_id, 'attr') ?>"
                            data-lang="<?= $language['code'] ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary btn-add-question" type="button" data-section="<?= esc($tab_id, 'attr') ?>" data-type="<?= esc($question_type, 'attr') ?>" data-edit-id="">
                                <i class="fas fa-save mr-1 btn-action-icon"></i><span class="btn-action-text"><?= labels('save', 'Save') ?></span>
                            </button>
                            <button class="btn btn-light btn-cancel-edit d-none" type="button" data-section="<?= esc($tab_id, 'attr') ?>">
                                <?= labels('cancel', 'Cancel') ?>
                            </button>
                        </div>
                    </div>
                    <?php if ($language['is_default']) { ?>
                        <small class="text-muted d-block mb-3"><?= labels('default_language_required', 'The default language is required. Other languages are optional.') ?></small>
                    <?php } ?>
                </div>
            <?php } ?>

            <!-- Questions Table (bootstrap-table, server-side) -->
            <table class="table" data-fixed-columns="true" id="<?= esc($tab_id, 'attr') ?>QuestionsTable"
                data-toggle="table"
                data-url="<?= base_url('admin/settings/chat-settings/list-questions?type=' . esc($question_type, 'url')) ?>"
                data-side-pagination="server"
                data-pagination="true" data-page-list="[5, 10, 25, 50, 100, All]"
                data-pagination-successively-size="2"
                data-search="false" data-show-columns="false" data-show-refresh="false"
                data-sort-name="sort_order" data-sort-order="asc"
                data-unique-id="id">
                <thead>
                    <tr>
                        <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                        <th data-field="question" class="text-center" data-sortable="true"><?= labels('question', 'Question') ?></th>
                        <th data-field="reorder_icon" class="text-center"><?= labels('reorder', 'Reorder') ?></th>
                        <th data-field="sort_order" class="text-center" data-visible="false"><?= labels('sort_order', 'Sort Order') ?></th>
                        <th data-field="operations" class="text-center" data-events="chat_question_events"><?= labels('operations', 'Operations') ?></th>
                    </tr>
                </thead>
            </table>
            <div class="col-md d-flex justify-content-end mt-2">
                <button class="btn btn-primary cq-update-order-btn d-none" type="button"
                    data-question-type="<?= esc($question_type, 'attr') ?>"
                    data-table-id="<?= esc($tab_id, 'attr') ?>QuestionsTable">
                    <?= labels('update_order', 'Update Order') ?>
                </button>
            </div>
        </div>
    </div>
</div>
