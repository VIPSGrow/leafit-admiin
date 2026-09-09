<?php

/**
 * Admin Custom Fields — bootstrap-table for one list context (documents or bank_details).
 *
 * Data keys passed from the parent (view second argument):
 * - tab_pane_id          string  DOM id for the tab panel (e.g. tab-provider-profile)
 * - tab_pane_class       string  tab-pane CSS classes (include "show active" on first pane)
 * - table_id             string  Unique HTML id for the table element
 * - custom_fields_group  string  API group query: documents | bank_details
 */
$tab_pane_id = $tab_pane_id ?? 'tab-provider-profile';
$tab_pane_class = $tab_pane_class ?? 'tab-pane fade';
$table_id = $table_id ?? 'custom_fields_table';
$custom_fields_group = $custom_fields_group ?? 'documents';
$cf_perms = $cf_perms ?? ['create' => false, 'read' => true, 'update' => false, 'delete' => false];
?>
<div class="<?= esc($tab_pane_class, 'attr') ?>" id="<?= esc($tab_pane_id, 'attr') ?>" role="tabpanel">
    <table class="table " data-fixed-columns="true" id="<?= esc($table_id, 'attr') ?>"
        data-pagination-successively-size="2" data-toggle="table"
        data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
        data-search="false" data-show-columns="false" data-show-refresh="false"
        data-side-pagination="server" data-query-params="custom_fields_query_params"
        data-url="<?= base_url('admin/custom-fields/list?group=' . esc($custom_fields_group, 'url')) ?>"
        data-unique-id="id"
        data-sort-name="sort_order" data-sort-order="asc">
        <thead>
            <tr>
                <th data-field="id" class="text-center" data-sortable="true">
                    <?= labels('id', 'ID') ?>
                </th>
                <th data-field="field_label" class="text-center" data-sortable="true">
                    <?= labels('label', 'Label') ?>
                </th>
                <th data-field="field_type" class="text-center">
                    <?= labels('type', 'Type') ?>
                </th>
                <th data-field="field_group" class="text-center" data-visible="false">
                    <?= labels('group', 'Group') ?>
                </th>
                <th data-field="required" class="text-center">
                    <?= labels('required', 'Required') ?>
                </th>
                <th data-field="visible" class="text-center">
                    <?= labels('visible', 'Visible') ?>
                </th>
                <th data-field="reorder_icon" class="text-center">
                    <?= labels('reorder', 'Reorder') ?>
                </th>
                <th data-field="sort_order" class="text-center" data-visible="false">
                    <?= labels('sort_order', 'Sort Order') ?>
                </th>
                <th data-field="operations" class="text-center" data-events="custom_fields_action_events">
                    <?= labels('operations', 'Operations') ?>
                </th>
            </tr>
        </thead>
    </table>
    <div class="col-md d-flex justify-content-end mt-2">
        <?php if (!empty($cf_perms['update'])) { ?>
            <button class="btn btn-primary pcf-update-order-btn d-none" type="button"
                data-group="<?= esc($custom_fields_group, 'attr') ?>"
                data-table-id="<?= esc($table_id, 'attr') ?>">
                <?= labels('update_order', 'Update Order') ?>
            </button>
        <?php } ?>
    </div>
</div>
